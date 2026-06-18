<?php

namespace App\Livewire;

use App\Models\Geracao;
use App\Models\RespostaSimulado;
use Livewire\Component;

class SimuladoPage extends Component
{
    public Geracao $geracao;

    /** @var array<string, string> Letra escolhida por índice de questão */
    public array $respostas = [];

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

        $questoes = $this->geracao->payload['questoes'] ?? [];
        $acertos = 0;

        foreach ($questoes as $i => $questao) {
            if (($this->respostas[(string) $i] ?? null) === $questao['correta']) {
                $acertos++;
            }
        }

        $this->resultado = RespostaSimulado::create([
            'geracao_id' => $this->geracao->id,
            'respostas' => $this->respostas,
            'acertos' => $acertos,
            'total' => count($questoes),
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

        return view('livewire.simulado', compact('fontesPaginas'))
            ->layout('layouts.app');
    }
}
