<?php

namespace App\Filament\Widgets;

use App\Services\AI\TokenUsageLogger;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TokenBudgetWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $logger = app(TokenUsageLogger::class);

        $gasto = $logger->gastoAcumulado();
        $orcamento = $logger->orcamento();
        $saldo = $logger->saldoRestante();
        $emAlerta = $logger->emAlerta();

        $porcentagem = $orcamento > 0 ? round($gasto / $orcamento * 100, 1) : 0;

        return [
            Stat::make('Gasto estimado', '$'.number_format($gasto, 4))
                ->description("Anthropic claude-sonnet-4-6 — {$porcentagem}% do orçamento")
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('Orçamento configurado', '$'.number_format($orcamento, 2))
                ->description('ANTHROPIC_BUDGET_USD')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Saldo restante', '$'.number_format($saldo, 4))
                ->description($emAlerta ? 'Abaixo do limite de alerta!' : 'OK')
                ->color($emAlerta ? 'danger' : 'success')
                ->icon('heroicon-o-chart-bar'),
        ];
    }
}
