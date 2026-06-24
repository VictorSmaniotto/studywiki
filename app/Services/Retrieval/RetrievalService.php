<?php

namespace App\Services\Retrieval;

use App\Models\Chunk;
use App\Services\AI\EmbeddingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetrievalService
{
    public function __construct(private readonly EmbeddingService $embeddingService) {}

    /**
     * Structured retrieval: returns chunks matching the scope (no ranking).
     *
     * @return list<array{chunk_id: int, pagina_id: int, heading_path: string|null, conteudo: string, tokens: int, titulo_pagina: string, path_relativo: string, score: null}>
     */
    public function forScope(Escopo $escopo): array
    {
        $query = Chunk::query()
            ->select([
                'chunks.id as chunk_id',
                'chunks.pagina_id',
                'chunks.heading_path',
                'chunks.conteudo',
                'chunks.tokens',
                'paginas.titulo as titulo_pagina',
                'paginas.path_relativo',
            ])
            ->join('paginas', 'paginas.id', '=', 'chunks.pagina_id')
            ->whereNull('paginas.deleted_at');

        $this->applyEscopoFilters($query, $escopo);

        return $query
            ->orderBy('paginas.id')
            ->orderBy('chunks.ordem')
            ->get()
            ->map(fn ($row) => [
                'chunk_id' => $row->chunk_id,
                'pagina_id' => $row->pagina_id,
                'heading_path' => $row->heading_path,
                'conteudo' => $row->conteudo,
                'tokens' => $row->tokens,
                'titulo_pagina' => $row->titulo_pagina,
                'path_relativo' => $row->path_relativo,
                'score' => null,
            ])
            ->all();
    }

    /**
     * Hybrid retrieval: vector similarity + full-text search merged with RRF.
     * When FTS returns empty, falls back to prefix-keyword search with unaccent.
     *
     * @return list<array{chunk_id: int, pagina_id: int, heading_path: string|null, conteudo: string, tokens: int, titulo_pagina: string, path_relativo: string, score: float}>
     */
    public function forQuery(string $query, Escopo $escopo, int $limit = 20): array
    {
        $queryVector = $this->embeddingService->embedQuery($query);

        $vectorResults = $this->vectorSearch($queryVector, $escopo, $limit * 3);
        $ftsResults = $this->ftsSearch($query, $escopo, $limit * 3);

        return $this->mergeWithRRF($vectorResults, $ftsResults, $limit);
    }

    /**
     * @param  float[]  $queryVector
     * @return list<array{chunk_id: int, pagina_id: int, heading_path: string|null, conteudo: string, tokens: int, titulo_pagina: string, path_relativo: string, score: float}>
     */
    private function vectorSearch(array $queryVector, Escopo $escopo, int $limit): array
    {
        $vectorStr = '['.implode(',', $queryVector).']';

        $query = Chunk::query()
            ->select([
                'chunks.id as chunk_id',
                'chunks.pagina_id',
                'chunks.heading_path',
                'chunks.conteudo',
                'chunks.tokens',
                'paginas.titulo as titulo_pagina',
                'paginas.path_relativo',
            ])
            ->selectRaw('1 - (chunks.embedding <=> CAST(? AS vector)) as score', [$vectorStr])
            ->join('paginas', 'paginas.id', '=', 'chunks.pagina_id')
            ->whereNull('paginas.deleted_at')
            ->whereNotNull('chunks.embedding')
            ->orderByRaw('chunks.embedding <=> CAST(? AS vector)', [$vectorStr])
            ->limit($limit);

        $this->applyEscopoFilters($query, $escopo);

        return $query->get()->map(fn ($row) => [
            'chunk_id' => $row->chunk_id,
            'pagina_id' => $row->pagina_id,
            'heading_path' => $row->heading_path,
            'conteudo' => $row->conteudo,
            'tokens' => $row->tokens,
            'titulo_pagina' => $row->titulo_pagina,
            'path_relativo' => $row->path_relativo,
            'score' => (float) $row->score,
        ])->all();
    }

    /**
     * FTS search with unaccent-prefix fallback when the tsquery matches nothing.
     *
     * @return list<array{chunk_id: int, pagina_id: int, heading_path: string|null, conteudo: string, tokens: int, titulo_pagina: string, path_relativo: string, score: float}>
     */
    private function ftsSearch(string $query, Escopo $escopo, int $limit): array
    {
        $baseQuery = Chunk::query()
            ->select([
                'chunks.id as chunk_id',
                'chunks.pagina_id',
                'chunks.heading_path',
                'chunks.conteudo',
                'chunks.tokens',
                'paginas.titulo as titulo_pagina',
                'paginas.path_relativo',
            ])
            ->selectRaw(
                "ts_rank(to_tsvector('portuguese', chunks.conteudo), websearch_to_tsquery('portuguese', ?)) as score",
                [$query]
            )
            ->join('paginas', 'paginas.id', '=', 'chunks.pagina_id')
            ->whereNull('paginas.deleted_at')
            ->whereRaw(
                "to_tsvector('portuguese', chunks.conteudo) @@ websearch_to_tsquery('portuguese', ?)",
                [$query]
            )
            ->orderByDesc('score')
            ->limit($limit);

        $this->applyEscopoFilters($baseQuery, $escopo);

        $results = $baseQuery->get()->map(fn ($row) => [
            'chunk_id' => $row->chunk_id,
            'pagina_id' => $row->pagina_id,
            'heading_path' => $row->heading_path,
            'conteudo' => $row->conteudo,
            'tokens' => $row->tokens,
            'titulo_pagina' => $row->titulo_pagina,
            'path_relativo' => $row->path_relativo,
            'score' => (float) $row->score,
        ])->all();

        if (! empty($results)) {
            return $results;
        }

        return $this->keywordFallbackSearch($query, $escopo, $limit);
    }

    /**
     * Fallback: splits query into significant words, matches by unaccented prefix.
     * Handles singular/plural and accent variations that confuse the FTS stemmer.
     *
     * @return list<array{chunk_id: int, pagina_id: int, heading_path: string|null, conteudo: string, tokens: int, titulo_pagina: string, path_relativo: string, score: float}>
     */
    private function keywordFallbackSearch(string $query, Escopo $escopo, int $limit): array
    {
        $stopWords = ['de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
            'a', 'o', 'as', 'os', 'e', 'para', 'com', 'por', 'que', 'se',
            'um', 'uma', 'ou', 'ao', 'aos', 'às', 'the', 'and'];

        $words = array_values(array_filter(
            preg_split('/\s+/', mb_strtolower($query)) ?: [],
            fn (string $w) => mb_strlen($w) >= 4 && ! in_array($w, $stopWords)
        ));

        if (empty($words)) {
            return [];
        }

        // Prefix = first 75% of each word so singular/plural share the same prefix.
        $patterns = array_map(function (string $w): string {
            $prefix = mb_substr($w, 0, max(3, (int) ceil(mb_strlen($w) * 0.75)));

            return '%'.$prefix.'%';
        }, $words);

        $caseParts = array_fill(
            0,
            count($patterns),
            'CASE WHEN unaccent(lower(chunks.conteudo)) LIKE unaccent(lower(?)) THEN 1 ELSE 0 END'
        );

        $baseQuery = Chunk::query()
            ->select([
                'chunks.id as chunk_id',
                'chunks.pagina_id',
                'chunks.heading_path',
                'chunks.conteudo',
                'chunks.tokens',
                'paginas.titulo as titulo_pagina',
                'paginas.path_relativo',
            ])
            ->selectRaw('('.implode(' + ', $caseParts).') as keyword_score', $patterns)
            ->join('paginas', 'paginas.id', '=', 'chunks.pagina_id')
            ->whereNull('paginas.deleted_at')
            ->where(function (Builder $q) use ($patterns): void {
                foreach ($patterns as $p) {
                    $q->orWhereRaw('unaccent(lower(chunks.conteudo)) LIKE unaccent(lower(?))', [$p]);
                }
            })
            ->orderByDesc('keyword_score')
            ->limit($limit);

        $this->applyEscopoFilters($baseQuery, $escopo);

        return $baseQuery->get()->map(fn ($row): array => [
            'chunk_id' => $row->chunk_id,
            'pagina_id' => $row->pagina_id,
            'heading_path' => $row->heading_path,
            'conteudo' => $row->conteudo,
            'tokens' => $row->tokens,
            'titulo_pagina' => $row->titulo_pagina,
            'path_relativo' => $row->path_relativo,
            'score' => 0.05,
        ])->all();
    }

    /**
     * Reciprocal Rank Fusion (k=60): merges two ranked lists without normalising their scores.
     *
     * @param  list<array{chunk_id: int, ...}>  $vectorResults
     * @param  list<array{chunk_id: int, ...}>  $ftsResults
     * @return list<array{chunk_id: int, ..., score: float}>
     */
    private function mergeWithRRF(array $vectorResults, array $ftsResults, int $limit, int $k = 60): array
    {
        $scores = [];
        $data = [];

        foreach ($vectorResults as $rank => $result) {
            $id = $result['chunk_id'];
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / ($k + $rank + 1);
            $data[$id] = $result;
        }

        foreach ($ftsResults as $rank => $result) {
            $id = $result['chunk_id'];
            $scores[$id] = ($scores[$id] ?? 0.0) + 1.0 / ($k + $rank + 1);
            $data[$id] = $result;
        }

        arsort($scores);

        return array_values(array_map(
            fn (int $id) => array_merge($data[$id], ['score' => $scores[$id]]),
            array_slice(array_keys($scores), 0, $limit, true)
        ));
    }

    private function applyEscopoFilters(Builder $query, Escopo $escopo): void
    {
        if ($escopo->temaId !== null) {
            $query->join('disciplinas', 'disciplinas.id', '=', 'paginas.disciplina_id')
                ->join('disciplina_tema', 'disciplina_tema.disciplina_id', '=', 'disciplinas.id')
                ->where('disciplina_tema.tema_id', $escopo->temaId);
        } elseif ($escopo->disciplinas !== []) {
            $slugs = array_map(fn (string $d) => Str::slug($d), $escopo->disciplinas);
            $query->join('disciplinas', 'disciplinas.id', '=', 'paginas.disciplina_id')
                ->whereIn('disciplinas.slug', $slugs);
        } elseif ($escopo->disciplina !== null) {
            $slug = Str::slug($escopo->disciplina);
            $query->join('disciplinas', 'disciplinas.id', '=', 'paginas.disciplina_id')
                ->where('disciplinas.slug', $slug);
        }

        if ($escopo->tags !== []) {
            $slugs = array_map(fn (string $t) => Str::slug($t), $escopo->tags);
            $query->whereExists(
                fn ($sub) => $sub->select(DB::raw(1))
                    ->from('pagina_tag')
                    ->join('tags', 'tags.id', '=', 'pagina_tag.tag_id')
                    ->whereColumn('pagina_tag.pagina_id', 'paginas.id')
                    ->whereIn('tags.slug', $slugs)
            );
        }

        if ($escopo->paginas !== []) {
            $query->whereIn('chunks.pagina_id', $escopo->paginas);
        }
    }
}
