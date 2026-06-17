<?php

namespace App\Console\Commands;

use App\Services\Sync\VaultSyncService;
use Illuminate\Console\Command;

class StudyWikiSync extends Command
{
    protected $signature = 'studywiki:sync';

    protected $description = 'Sincroniza a vault Obsidian com o banco de dados';

    public function handle(VaultSyncService $syncService): int
    {
        $vaultPath = config('studywiki.vault_path');

        if (! $vaultPath) {
            $this->error('OBSIDIAN_VAULT_PATH não configurado no .env');

            return self::FAILURE;
        }

        $this->info("Sincronizando vault: {$vaultPath}");

        $stats = $syncService->sync($vaultPath);

        $this->info(sprintf(
            'Criadas: %d | Atualizadas: %d | Removidas: %d | Ignoradas: %d',
            $stats['criadas'],
            $stats['atualizadas'],
            $stats['removidas'],
            $stats['ignoradas'],
        ));

        return self::SUCCESS;
    }
}
