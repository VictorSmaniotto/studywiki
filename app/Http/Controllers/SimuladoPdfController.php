<?php

namespace App\Http\Controllers;

use App\Models\Geracao;
use App\Models\RespostaSimulado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SimuladoPdfController extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        $geracao = Geracao::where('id', $id)
            ->where('tipo', 'simulado')
            ->where('status', 'ok')
            ->with('fontes.pagina')
            ->firstOrFail();

        $secoesPermitidas = ['prova_branca', 'gabarito', 'respostas'];
        $secoes = array_values(array_intersect(
            (array) $request->input('secoes', ['prova_branca']),
            $secoesPermitidas
        ));

        if (empty($secoes)) {
            $secoes = ['prova_branca'];
        }

        $resposta = null;
        if (in_array('respostas', $secoes)) {
            $resposta = RespostaSimulado::where('geracao_id', $id)->latest()->first();
        }

        $disciplina = $geracao->escopo['disciplina'] ?? 'Disciplina';
        $perfil = $geracao->escopo['perfil'] ?? null;
        $questoesME = $geracao->payload['questoes_me'] ?? $geracao->payload['questoes'] ?? [];
        $questoesDis = $geracao->payload['questoes_dis'] ?? [];
        $fontesPaginas = $geracao->fontes->keyBy('pagina_id');

        $pdf = Pdf::loadView('pdf.simulado', compact(
            'geracao',
            'secoes',
            'resposta',
            'disciplina',
            'perfil',
            'questoesME',
            'questoesDis',
            'fontesPaginas',
        ))->setPaper('a4');

        $filename = sprintf('simulado-%d-%s.pdf', $geracao->id, now()->format('Ymd'));

        return $pdf->download($filename);
    }
}
