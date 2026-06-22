<?php

namespace App\Services;

use App\Models\Geracao;
use App\Models\RespostaSimulado;
use Barryvdh\DomPDF\Facade\Pdf;

class SimuladoPdfService
{
    private const SECOES_PERMITIDAS = ['prova_branca', 'gabarito', 'respostas'];

    /**
     * Monta o PDF de um simulado para as seções pedidas.
     *
     * @param  array<int, string>  $secoes
     * @return \Barryvdh\DomPDF\PDF
     */
    public function montar(int $geracaoId, array $secoes)
    {
        $geracao = Geracao::where('id', $geracaoId)
            ->where('tipo', 'simulado')
            ->where('status', 'ok')
            ->with('fontes.pagina')
            ->firstOrFail();

        $secoes = $this->normalizarSecoes($secoes);

        $resposta = null;
        if (in_array('respostas', $secoes)) {
            $resposta = RespostaSimulado::where('geracao_id', $geracaoId)->latest()->first();
        }

        $disciplina = $geracao->escopo['disciplina'] ?? 'Disciplina';
        $perfil = $geracao->escopo['perfil'] ?? null;
        $questoesME = $geracao->payload['questoes_me'] ?? $geracao->payload['questoes'] ?? [];
        $questoesDis = $geracao->payload['questoes_dis'] ?? [];
        $fontesPaginas = $geracao->fontes->keyBy('pagina_id');

        return Pdf::loadView('pdf.simulado', compact(
            'geracao',
            'secoes',
            'resposta',
            'disciplina',
            'perfil',
            'questoesME',
            'questoesDis',
            'fontesPaginas',
        ))->setPaper('a4');
    }

    public function nomeArquivo(int $geracaoId): string
    {
        return sprintf('simulado-%d-%s.pdf', $geracaoId, now()->format('Ymd'));
    }

    /**
     * @param  array<int, string>  $secoes
     * @return array<int, string>
     */
    public function normalizarSecoes(array $secoes): array
    {
        $filtradas = array_values(array_intersect($secoes, self::SECOES_PERMITIDAS));

        return empty($filtradas) ? ['prova_branca'] : $filtradas;
    }
}
