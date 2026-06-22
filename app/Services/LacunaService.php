<?php

namespace App\Services;

use App\Models\Disciplina;
use Illuminate\Support\Facades\DB;

class LacunaService
{
    public function detectar(Disciplina $disciplina): array
    {
        $rows = DB::table('resposta_simulados as rs')
            ->join('geracoes as g', 'g.id', '=', 'rs.geracao_id')
            ->where('g.tipo', 'simulado')
            ->where('g.status', 'ok')
            ->whereRaw("g.escopo->>'disciplina' = ?", [$disciplina->slug])
            ->select('rs.respostas', 'g.payload')
            ->get();

        if ($rows->count() < 2) {
            return [];
        }

        $allChunkIds = [];
        foreach ($rows as $row) {
            $payload = json_decode($row->payload, true) ?? [];
            foreach ($payload['questoes_me'] ?? [] as $questao) {
                foreach ($questao['fontes'] ?? [] as $f) {
                    $cid = (int) ($f['chunk_id'] ?? 0);
                    if ($cid > 0) {
                        $allChunkIds[$cid] = true;
                    }
                }
            }
        }

        if (empty($allChunkIds)) {
            return [];
        }

        $headings = DB::table('chunks')
            ->whereIn('id', array_keys($allChunkIds))
            ->pluck('heading_path', 'id');

        $errosPorHeading = [];
        $totalPorHeading = [];

        foreach ($rows as $row) {
            $respostas = json_decode($row->respostas, true) ?? [];
            $payload = json_decode($row->payload, true) ?? [];

            foreach ($payload['questoes_me'] ?? [] as $i => $questao) {
                $escolha = $respostas[(string) $i] ?? null;
                if ($escolha === null) {
                    continue;
                }

                $heading = null;
                foreach ($questao['fontes'] ?? [] as $f) {
                    $cid = (int) ($f['chunk_id'] ?? 0);
                    if ($cid > 0 && ! empty($headings[$cid])) {
                        $heading = explode(' > ', $headings[$cid])[0];
                        break;
                    }
                }

                if ($heading === null) {
                    continue;
                }

                $totalPorHeading[$heading] = ($totalPorHeading[$heading] ?? 0) + 1;
                if ($escolha !== ($questao['correta'] ?? null)) {
                    $errosPorHeading[$heading] = ($errosPorHeading[$heading] ?? 0) + 1;
                }
            }
        }

        if (empty($errosPorHeading)) {
            return [];
        }

        $resultado = [];
        foreach ($errosPorHeading as $heading => $erros) {
            $total = $totalPorHeading[$heading] ?? 1;
            $resultado[] = [
                'heading' => $heading,
                'erros' => $erros,
                'total' => $total,
                'taxa_erro' => round($erros / $total * 100, 1),
            ];
        }

        usort($resultado, fn ($a, $b) => $b['taxa_erro'] <=> $a['taxa_erro']);

        return array_slice($resultado, 0, 3);
    }
}
