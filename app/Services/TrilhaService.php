<?php

namespace App\Services;

use App\Models\Flashcard;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TrilhaService
{
    public function flashcardsVencidos(): Collection
    {
        return Flashcard::where('proxima_revisao', '<=', Carbon::today()->toDateString())
            ->orderBy('proxima_revisao')
            ->get();
    }

    public function topicosPrioritarios(int $limite = 5): array
    {
        $rows = DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
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

        return array_slice(
            array_map(
                fn ($h, $e) => ['heading' => $h, 'erros' => $e],
                array_keys($contagem),
                $contagem
            ),
            0,
            $limite
        );
    }

    public function streakAtual(): int
    {
        $ultimo = Setting::get('streak_last_date');

        if (empty($ultimo)) {
            return 0;
        }

        $ultimaData = Carbon::parse($ultimo)->startOfDay();
        $ontem = Carbon::yesterday()->startOfDay();

        if ($ultimaData->lt($ontem)) {
            return 0;
        }

        return (int) Setting::get('streak_count', '0');
    }

    public function registrarSessao(): void
    {
        $hoje = Carbon::today()->toDateString();
        $ultimo = Setting::get('streak_last_date');

        if ($ultimo === $hoje) {
            return;
        }

        $ontem = Carbon::yesterday()->toDateString();
        $streak = ($ultimo === $ontem)
            ? (int) Setting::get('streak_count', '0') + 1
            : 1;

        Setting::set('streak_count', (string) $streak);
        Setting::set('streak_last_date', $hoje);
    }
}
