<?php

namespace App\Livewire;

use App\Models\Geracao;
use App\Models\RespostaSimulado;
use App\Services\AI\AvaliacaoDissertativaService;
use Livewire\Component;

class SimuladoPage extends Component
{
    public Geracao $geracao;

    /** @var array<string, string> Letra escolhida por índice de questão ME */
    public array $respostas = [];

    /** @var array<string, string> Texto digitado por índice de questão dissertativa */
    public array $respostasDissertativas = [];

    public bool $enviado = false;

    public ?RespostaSimulado $resultado = null;

    public function mount(int $id): void
    {
        $this->geracao = Geracao::where('id', $id)
            ->where('tipo', 'simulado')
            ->where('status', 'ok')
            ->firstOrFail();
    }

    public function enviar(): void
    {
        if ($this->enviado) {
            return;
        }

        $questoesME = $this->geracao->payload['questoes_me'] ?? $this->geracao->payload['questoes'] ?? [];
        $questoesDis = $this->geracao->payload['questoes_dis'] ?? [];

        $acertos = 0;
        foreach ($questoesME as $i => $questao) {
            if (($this->respostas[(string) $i] ?? null) === $questao['correta']) {
                $acertos++;
            }
        }

        $notasDis = [];
        if (! empty($questoesDis)) {
            $avaliador = app(AvaliacaoDissertativaService::class);
            foreach ($questoesDis as $i => $questaoDis) {
                $resposta = $this->respostasDissertativas[(string) $i] ?? '';
                $notasDis[] = $avaliador->avaliar($questaoDis, $resposta);
            }
        }

        $this->resultado = RespostaSimulado::create([
            'geracao_id' => $this->geracao->id,
            'respostas' => $this->respostas,
            'acertos' => $acertos,
            'total' => count($questoesME),
            'respostas_dissertativas' => $this->respostasDissertativas ?: null,
            'notas_dissertativas' => $notasDis ?: null,
        ]);

        $this->enviado = true;
    }

    public function render()
    {
        $fontesPaginas = $this->geracao
            ->fontes()
            ->with('pagina')
            ->get()
            ->keyBy('pagina_id');

        $questoesME = $this->geracao->payload['questoes_me'] ?? $this->geracao->payload['questoes'] ?? [];
        $questoesDis = $this->geracao->payload['questoes_dis'] ?? [];

        return view('livewire.simulado', compact('fontesPaginas', 'questoesME', 'questoesDis'))
            ->layout('layouts.app');
    }
}
