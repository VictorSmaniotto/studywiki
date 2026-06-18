<?php

namespace App\Services\AI;

use App\Models\Chunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;

class EmbeddingService
{
    public const MODEL = 'voyage-3-lite';

    public const PROVIDER = 'voyageai';

    public const BATCH_SIZE = 128;

    /**
     * Embed a batch of chunks and persist the results.
     *
     * @param  Collection<int, Chunk>  $chunks
     * @return int number of chunks embedded
     */
    public function embedBatch(Collection $chunks): int
    {
        if ($chunks->isEmpty()) {
            return 0;
        }

        $response = Prism::embeddings()
            ->using(self::PROVIDER, self::MODEL)
            ->fromArray($chunks->pluck('conteudo')->all())
            ->asEmbeddings();

        $embeddings = $response->embeddings;

        $chunks->each(function (Chunk $chunk, int $index) use ($embeddings): void {
            /** @var Embedding $embedding */
            $embedding = $embeddings[$index];

            $chunk->update([
                'embedding' => $embedding->embedding,
                'embedding_model' => self::MODEL,
            ]);
        });

        return $chunks->count();
    }

    /**
     * Embed a single text string and return the raw vector.
     *
     * @return float[]
     */
    public function embedQuery(string $text): array
    {
        $response = Prism::embeddings()
            ->using(self::PROVIDER, self::MODEL)
            ->fromInput($text)
            ->asEmbeddings();

        return $response->embeddings[0]->embedding;
    }

    /**
     * Query chunks that still need embedding.
     *
     * @return Builder<Chunk>
     */
    public function pendingQuery(): Builder
    {
        return Chunk::whereNull('embedding_model');
    }
}
