<?php

namespace App\Console\Commands;

use App\Models\Chunk;
use App\Services\AI\EmbeddingService;
use Illuminate\Console\Command;

class EmbedChunks extends Command
{
    protected $signature = 'studywiki:embed
                            {--batch=128 : Chunks por requisição à API}
                            {--force : Re-embeda chunks já embedados (troca de modelo)}';

    protected $description = 'Gera embeddings dos chunks via VoyageAI e persiste no banco';

    public function handle(EmbeddingService $service): int
    {
        $batchSize = (int) $this->option('batch');

        $query = $this->option('force')
            ? Chunk::query()
            : $service->pendingQuery();

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nenhum chunk pendente de embedding.');

            return self::SUCCESS;
        }

        $this->info("Embedando {$total} chunks em lotes de {$batchSize}...");

        $embedded = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($batchSize, function ($chunks) use ($service, &$embedded, $bar): void {
            $embedded += $service->embedBatch($chunks);
            $bar->advance($chunks->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info("Concluído: {$embedded} chunks embedados com o modelo '".EmbeddingService::MODEL."'.");

        return self::SUCCESS;
    }
}
