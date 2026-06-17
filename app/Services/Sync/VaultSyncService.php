<?php

namespace App\Services\Sync;

use App\Models\Disciplina;
use App\Models\Pagina;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class VaultSyncService
{
    private const TIPOS_VALIDOS = ['disciplina', 'conceito', 'autor', 'fonte', 'sintese'];

    public function __construct(private readonly ChunkingService $chunking) {}

    /**
     * @return array{criadas: int, atualizadas: int, removidas: int, ignoradas: int}
     */
    public function sync(string $vaultPath): array
    {
        $stats = ['criadas' => 0, 'atualizadas' => 0, 'removidas' => 0, 'ignoradas' => 0];

        $arquivos = $this->coletarArquivos($vaultPath);
        $pathsProcessados = [];

        foreach ($arquivos as $absolutePath) {
            $pathRelativo = $this->pathRelativo($vaultPath, $absolutePath);
            $pathsProcessados[] = $pathRelativo;

            $conteudo = file_get_contents($absolutePath);
            $hash = hash('sha256', $conteudo);

            $pagina = Pagina::withTrashed()->where('path_relativo', $pathRelativo)->first();

            if ($pagina && $pagina->hash === $hash && ! $pagina->trashed()) {
                // Re-sincroniza tags a partir do frontmatter salvo para recuperar syncs interrompidos.
                $this->sincronizarTags($pagina, $pagina->frontmatter['tags'] ?? []);
                $stats['ignoradas']++;

                continue;
            }

            try {
                $document = YamlFrontMatter::parse($conteudo);
                $matter = $document->matter();
                $corpo = trim($document->body());
            } catch (\Throwable) {
                $matter = [];
                $corpo = trim($conteudo);
            }

            $titulo = $matter['titulo'] ?? pathinfo($absolutePath, PATHINFO_FILENAME);
            $data = [
                'disciplina_id' => $this->resolverDisciplina($matter['disciplina'] ?? null),
                'tipo' => $this->resolverTipo($matter['tipo'] ?? null),
                'titulo' => $titulo,
                'slug' => Str::slug($titulo),
                'frontmatter' => $matter,
                'corpo' => $corpo ?: null,
                'hash' => $hash,
                'atualizado_na_vault' => Carbon::createFromTimestamp(filemtime($absolutePath)),
                'deleted_at' => null,
            ];

            if ($pagina) {
                $pagina->fill($data);
                $pagina->deleted_at = null;
                $pagina->save();
                $stats['atualizadas']++;
            } else {
                $pagina = Pagina::create(array_merge($data, ['path_relativo' => $pathRelativo]));
                $stats['criadas']++;
            }

            $this->sincronizarTags($pagina, $matter['tags'] ?? []);
            $this->sincronizarChunks($pagina, $corpo);
        }

        Pagina::whereNotIn('path_relativo', $pathsProcessados)
            ->whereNull('deleted_at')
            ->each(function (Pagina $p) use (&$stats): void {
                $p->delete();
                $stats['removidas']++;
            });

        return $stats;
    }

    /** @return string[] */
    private function coletarArquivos(string $vaultPath): array
    {
        if (! is_dir($vaultPath)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($vaultPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $arquivos = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $arquivos[] = $file->getPathname();
            }
        }

        return $arquivos;
    }

    private function pathRelativo(string $vaultPath, string $absolutePath): string
    {
        $base = rtrim($vaultPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $relativo = str_replace($base, '', $absolutePath);

        return str_replace('\\', '/', $relativo);
    }

    private function resolverTipo(?string $tipo): string
    {
        return in_array($tipo, self::TIPOS_VALIDOS, true) ? $tipo : 'conceito';
    }

    private function resolverDisciplina(?string $nome): ?int
    {
        if (! $nome) {
            return null;
        }

        return Disciplina::firstOrCreate(
            ['slug' => Str::slug($nome)],
            ['nome' => $nome]
        )->id;
    }

    private function sincronizarChunks(Pagina $pagina, string $corpo): void
    {
        $pagina->chunks()->delete();

        if ($corpo === '') {
            return;
        }

        foreach ($this->chunking->chunk($corpo) as $ordem => $data) {
            $pagina->chunks()->create([
                'ordem' => $ordem,
                'conteudo' => $data['conteudo'],
                'heading_path' => $data['heading_path'],
                'tokens' => $data['tokens'],
                'embedding' => null,
                'embedding_model' => null,
            ]);
        }
    }

    /** @param mixed $tags */
    private function sincronizarTags(Pagina $pagina, $tags): void
    {
        $tags = is_array($tags) ? $tags : (is_string($tags) ? [$tags] : []);

        $tagIds = collect($tags)
            ->filter()
            ->map(fn (string $nome) => Tag::firstOrCreate(
                ['slug' => Str::slug($nome)],
                ['nome' => $nome]
            )->id)
            ->all();

        $pagina->tags()->sync($tagIds);
    }
}
