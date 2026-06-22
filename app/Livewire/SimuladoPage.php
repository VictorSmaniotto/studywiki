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

    public int $tempoDecorrido = 0;

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
            'tempo_realizado_segundos' => $this->tempoDecorrido > 0 ? $this->tempoDecorrido : null,
        ]);

        $this->enviado = true;
    }

    /**
     * Exporta o simulado em PDF via diálogo nativo de salvar (NativePHP desktop).
     * No navegador (sem runtime nativo) é um no-op — o download web continua via SimuladoPdfController.
     *
     * @param  array<int, string>  $secoes
     */
    public function salvarPdfNativo(array $secoes = ['prova_branca']): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        $service = app(SimuladoPdfService::class);
        $secoes = $service->normalizarSecoes($secoes);

        $destino = app(Dialog::class)
            ->title('Salvar simulado em PDF')
            ->defaultPath($service->nomeArquivo($this->geracao->id))
            ->filter('PDF', ['pdf'])
            ->save();

        if (! $destino) {
            return; // usuário cancelou o diálogo
        }

        file_put_contents($destino, $service->montar($this->geracao->id, $secoes)->output());

        Notification::title('StudyWiki')
            ->message('Simulado exportado em PDF.')
            ->show();
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
            ->layout('layouts.app')
            ->title('Simulado');
    }
}
