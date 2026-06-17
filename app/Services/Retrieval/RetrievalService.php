<?php

namespace App\Services\Retrieval;

use App\Models\Chunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetrievalService
{
    /**
     * Retorna chunks do escopo estruturado com fonte rastreável.
     *
     * @return list<array{
     *   chunk_id: int,
     *   pagina_id: int,
     *   heading_path: string|null,
     *   conteudo: string,
     *   tokens: int,
     *   titulo_pagina: string,
     *   path_relativo: string,
     *   score: null,
     * }>
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

        if ($escopo->disciplina !== null) {
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
}
