<?php

namespace App\Filament\Widgets;

use App\Models\Geracao;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GeracaoStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $total = Geracao::count();
        $rejeitadas = Geracao::where('status', 'rejeitado')->count();
        $taxaRejeicao = $total > 0 ? round($rejeitadas / $total * 100, 1) : 0;
        $totalTokens = Geracao::sum('custo_tokens');

        return [
            Stat::make('Total de Gerações', $total),
            Stat::make('Taxa de Rejeição', "{$taxaRejeicao}%")
                ->description("{$rejeitadas} rejeitadas de {$total}")
                ->color($taxaRejeicao > 30 ? 'danger' : 'success'),
            Stat::make('Tokens Consumidos', number_format($totalTokens))
                ->description('soma acumulada'),
        ];
    }
}
