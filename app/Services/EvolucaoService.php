<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EvolucaoService
{
    public function scoresPorSessao(string $slug): array
    {
        return DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->whereRaw("g.escopo->>'disciplina' = ?", [$slug])
            ->selectRaw("to_char(rs.created_at, 'DD/MM/YY') as data")
            ->selectRaw('rs.acertos, rs.total, rs.notas_dissertativas')
            ->orderBy('rs.created_at')
            ->get()
            ->map(function ($row) {
                $scoreME = $row->total > 0
                    ? round($row->acertos / $row->total * 100, 1)
                    : null;

                $notas = json_decode($row->notas_dissertativas ?? 'null', true) ?? [];
                $scoreDis = ! empty($notas)
                    ? round(collect($notas)->avg('nota_total') * 100, 1)
                    : null;

                return [
                    'data' => $row->data,
                    'score_me' => $scoreME,
                    'score_dis' => $scoreDis,
                ];
            })
            ->values()
            ->all();
    }

    public function errosPorTopico(string $slug): array
    {
        $rows = DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->whereRaw("g.escopo->>'disciplina' = ?", [$slug])
            ->select('rs.respostas', 'g.payload')
            ->get();

        $chunkIds = [];
        $wrongQuestionsChunkIds = [];

        foreach ($rows as $row) {
            $respostas = json_decode($row->respostas, true) ?? [];
            $payload = json_decode($row->payload, true) ?? [];
            $questoesME = $payload['questoes_me'] ?? [];

            foreach ($questoesME as $i => $questao) {
                $escolha = $respostas[(string) $i] ?? null;
                if ($escolha !== null && $escolha !== ($questao['correta'] ?? null)) {
                    $ids = collect($questao['fontes'] ?? [])
                        ->map(fn ($f) => $f['chunk_id'] ?? null)
                        ->filter()
                        ->values()
                        ->all();

                    if (! empty($ids)) {
                        $wrongQuestionsChunkIds[] = $ids;
                        foreach ($ids as $id) {
                            $chunkIds[$id] = true;
                        }
                    }
                }
            }
        }

        if (empty($chunkIds)) {
            return [];
        }

        $headings = DB::table('chunks')
            ->whereIn('id', array_keys($chunkIds))
            ->pluck('heading_path', 'id');

        $contagem = [];
        foreach ($wrongQuestionsChunkIds as $ids) {
            $heading = null;
            foreach ($ids as $id) {
                if (! empty($headings[$id])) {
                    $heading = explode(' > ', $headings[$id])[0];
                    break;
                }
            }
            if ($heading) {
                $contagem[$heading] = ($contagem[$heading] ?? 0) + 1;
            }
        }

        arsort($contagem);

        return array_map(
            fn ($h, $e) => ['heading' => $h, 'erros' => $e],
            array_keys($contagem),
            $contagem
        );
    }

    public function tempoVsEstimado(string $slug): array
    {
        return DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->whereRaw("g.escopo->>'disciplina' = ?", [$slug])
            ->whereNotNull('rs.tempo_realizado_segundos')
            ->selectRaw("to_char(rs.created_at, 'DD/MM/YY') as data")
            ->selectRaw('rs.tempo_realizado_segundos')
            ->selectRaw("(g.escopo->>'tempo_estimado_segundos')::int as tempo_estimado_segundos")
            ->orderBy('rs.created_at')
            ->get()
            ->map(fn ($row) => [
                'data' => $row->data,
                'realizado_min' => round($row->tempo_realizado_segundos / 60, 1),
                'estimado_min' => ($row->tempo_estimado_segundos ?? 0) > 0
                    ? round($row->tempo_estimado_segundos / 60, 1)
                    : null,
            ])
            ->values()
            ->all();
    }

    public function distribuicaoQuestoes(string $slug): array
    {
        $row = DB::table('geracoes')
            ->where('tipo', 'simulado')
            ->where('status', 'ok')
            ->whereRaw("escopo->>'disciplina' = ?", [$slug])
            ->selectRaw("SUM(jsonb_array_length(COALESCE(payload->'questoes_me', '[]'::jsonb))) as total_me")
            ->selectRaw("SUM(jsonb_array_length(COALESCE(payload->'questoes_dis', '[]'::jsonb))) as total_dis")
            ->first();

        return [
            'me' => (int) ($row->total_me ?? 0),
            'dissertativas' => (int) ($row->total_dis ?? 0),
        ];
    }

    public function criteriosMaisPerdidos(string $slug): array
    {
        $rows = DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->whereRaw("g.escopo->>'disciplina' = ?", [$slug])
            ->whereNotNull('rs.notas_dissertativas')
            ->pluck('rs.notas_dissertativas');

        return $this->agregarCriteriosPerdidos($rows);
    }

    public function scoresMediaPorDisciplina(): array
    {
        return DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->selectRaw("g.escopo->>'disciplina' as disciplina")
            ->selectRaw('ROUND((AVG(rs.acertos::float / NULLIF(rs.total, 0)) * 100)::numeric, 1) as media_score')
            ->groupByRaw("g.escopo->>'disciplina'")
            ->orderByDesc('media_score')
            ->get()
            ->map(fn ($r) => [
                'disciplina' => $r->disciplina ?? '(sem disciplina)',
                'media_score' => (float) $r->media_score,
            ])
            ->values()
            ->all();
    }

    public function criteriosPerdidosGlobais(): array
    {
        $rows = DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->whereNotNull('rs.notas_dissertativas')
            ->pluck('rs.notas_dissertativas');

        return $this->agregarCriteriosPerdidos($rows);
    }

    private function agregarCriteriosPerdidos(Collection $rows): array
    {
        $total = [];
        $contador = [];

        foreach ($rows as $notasJson) {
            $notasSimulado = json_decode($notasJson, true) ?? [];
            foreach ($notasSimulado as $notaQuestao) {
                foreach ($notaQuestao['notas'] ?? [] as $item) {
                    $criterio = $item['criterio'] ?? 'Desconhecido';
                    $perdido = 1.0 - ($item['nota'] ?? 1.0);
                    $total[$criterio] = ($total[$criterio] ?? 0.0) + $perdido;
                    $contador[$criterio] = ($contador[$criterio] ?? 0) + 1;
                }
            }
        }

        $resultado = [];
        foreach ($total as $criterio => $soma) {
            $resultado[] = [
                'criterio' => $criterio,
                'media_perdido' => round($soma / $contador[$criterio] * 100, 1),
            ];
        }

        usort($resultado, fn ($a, $b) => $b['media_perdido'] <=> $a['media_perdido']);

        return array_slice($resultado, 0, 8);
    }
}
