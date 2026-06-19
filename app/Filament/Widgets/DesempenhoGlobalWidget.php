<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DesempenhoGlobalWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $row = DB::table('geracoes')
            ->selectRaw('SUM(custo_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as total_geracoes')
            ->selectRaw("SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitadas")
            ->first();

        $total = (int) ($row->total_geracoes ?? 0);
        $rejeitadas = (int) ($row->rejeitadas ?? 0);
        $taxa = $total > 0 ? round($rejeitadas / $total * 100, 1) : 0;

        return [
            Stat::make('Tokens consumidos', number_format((int) ($row->total_tokens ?? 0)))
                ->description('soma acumulada de todas as gerações')
                ->icon('heroicon-o-cpu-chip'),
            Stat::make('Total de gerações', $total)
                ->description('resumos + flashcards + simulados')
                ->icon('heroicon-o-document-text'),
            Stat::make('Taxa de rejeição global', "{$taxa}%")
                ->description("{$rejeitadas} rejeitadas de {$total}")
                ->color($taxa > 30 ? 'danger' : 'success')
                ->icon('heroicon-o-shield-check'),
        ];
    }
}
