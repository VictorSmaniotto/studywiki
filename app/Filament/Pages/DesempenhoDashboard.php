<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DesempenhoGlobalWidget;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DesempenhoDashboard extends Page
{
    protected string $view = 'filament.pages.desempenho-dashboard';

    protected static ?string $title = 'Dashboard de Desempenho';

    protected static ?string $navigationLabel = 'Desempenho';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Observabilidade';
    }

    protected function getHeaderWidgets(): array
    {
        return [DesempenhoGlobalWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    public function getDadosPorDisciplina(): array
    {
        $custos = DB::table('geracoes')
            ->selectRaw("escopo->>'disciplina' as disciplina")
            ->selectRaw('SUM(custo_tokens) as total_tokens')
            ->selectRaw('COUNT(*) as total_geracoes')
            ->selectRaw("SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitadas")
            ->selectRaw("SUM(CASE WHEN tipo = 'resumo' THEN 1 ELSE 0 END) as resumos")
            ->selectRaw("SUM(CASE WHEN tipo = 'flashcards' THEN 1 ELSE 0 END) as flashcards_count")
            ->selectRaw("SUM(CASE WHEN tipo = 'simulado' THEN 1 ELSE 0 END) as simulados")
            ->groupByRaw("escopo->>'disciplina'")
            ->orderByDesc('total_tokens')
            ->get()
            ->keyBy('disciplina');

        $desempenho = DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->selectRaw("g.escopo->>'disciplina' as disciplina")
            ->selectRaw('AVG(rs.acertos::float / NULLIF(rs.total, 0)) * 100 as media_acertos_pct')
            ->selectRaw('COUNT(rs.id) as simulados_respondidos')
            ->where('g.tipo', 'simulado')
            ->groupByRaw("g.escopo->>'disciplina'")
            ->get()
            ->keyBy('disciplina');

        return $custos->map(function ($row) use ($desempenho) {
            $desemp = $desempenho->get($row->disciplina);

            return [
                'disciplina' => $row->disciplina ?? '(sem disciplina)',
                'total_tokens' => (int) $row->total_tokens,
                'total_geracoes' => (int) $row->total_geracoes,
                'rejeitadas' => (int) $row->rejeitadas,
                'taxa_rejeicao' => $row->total_geracoes > 0
                    ? round($row->rejeitadas / $row->total_geracoes * 100, 1)
                    : 0,
                'resumos' => (int) $row->resumos,
                'flashcards' => (int) $row->flashcards_count,
                'simulados' => (int) $row->simulados,
                'media_acertos_pct' => $desemp ? round($desemp->media_acertos_pct, 1) : null,
                'simulados_respondidos' => $desemp ? (int) $desemp->simulados_respondidos : 0,
            ];
        })->values()->all();
    }
}
