<?php

namespace App\Livewire;

use App\Jobs\GerarConteudoJob;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Services\EvolucaoService;
use App\Services\LacunaService;
use Flux\Flux;
use Livewire\Component;

class DisciplinaPage extends Component
{
    private const ERRO_PROP = [
        'resumo' => 'erroResumo',
        'flashcards' => 'erroFlashcards',
        'simulado' => 'erroSimulado',
        'mapa_mental' => 'erroMapaMental',
    ];

    public Disciplina $disciplina;

    public string $erroResumo = '';

    public string $erroFlashcards = '';

    public string $erroSimulado = '';

    public string $erroMapaMental = '';

    public string $perfil = 'personalizado';

    public string $dificuldade = 'medio';

    public int $nQuestoes = 5;

    public int $nDissertativas = 3;

    public array $expandidos = [];

    public string $queryResumo = '';

    public string $queryFlashcards = '';

    public string $querySimulado = '';

    /** @var string[] tipos com job em andamento */
    public array $tiposGerando = [];

    /** @var array<string, int> max geracao_id por tipo no momento do dispatch */
    public array $latestIds = [];

    public function mount(string $slug): void
    {
        $this->disciplina = Disciplina::where('slug', $slug)->firstOrFail();
    }

    public function updatedPerfil(): void
    {
        match ($this->perfil) {
            'universitario' => [$this->nQuestoes, $this->nDissertativas, $this->dificuldade] = [3, 3, 'medio'],
            'vestibular' => [$this->nQuestoes, $this->nDissertativas, $this->dificuldade] = [10, 10, 'dificil'],
            default => null,
        };
    }

    public function toggleExpandir(int $id): void
    {
        $this->expandidos = in_array($id, $this->expandidos)
            ? array_values(array_filter($this->expandidos, fn ($i) => $i !== $id))
            : [...$this->expandidos, $id];
    }

    public function gerarResumo(): void
    {
        $this->erroResumo = '';
        $this->despacharJob('resumo', $this->queryResumo ?: null);
    }

    public function gerarFlashcards(): void
    {
        $this->erroFlashcards = '';
        $this->despacharJob('flashcards', $this->queryFlashcards ?: null);
    }

    public function gerarSimulado(): void
    {
        $this->erroSimulado = '';

        $tempoEstimado = match ($this->perfil) {
            'universitario' => 36 * 60,
            'vestibular' => 120 * 60,
            default => 0,
        };

        $this->despacharJob('simulado', $this->querySimulado ?: null, $tempoEstimado);
    }

    public function gerarMapaMental(): void
    {
        $this->erroMapaMental = '';
        $this->despacharJob('mapa_mental');
    }

    public function verificarGeracoes(): void
    {
        $slug = $this->disciplina->slug;
        $concluidos = [];

        foreach ($this->tiposGerando as $tipo) {
            $nova = Geracao::whereRaw("escopo->>'disciplina' = ?", [$slug])
                ->where('tipo', $tipo)
                ->where('id', '>', $this->latestIds[$tipo] ?? 0)
                ->latest('id')
                ->first();

            if (! $nova) {
                continue;
            }

            $concluidos[] = $tipo;

            if ($nova->status === 'ok') {
                $this->expandidos[] = $nova->id;
                Flux::toast($this->labelToast($tipo).' gerado com sucesso!', variant: 'success');
            } else {
                $prop = self::ERRO_PROP[$tipo];
                $this->$prop = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
                Flux::toast('Geração rejeitada: conteúdo insuficiente para ancoragem.', variant: 'danger');
            }
        }

        $this->tiposGerando = array_values(
            array_filter($this->tiposGerando, fn ($t) => ! in_array($t, $concluidos))
        );
    }

    public function revisarTopico(string $topico): void
    {
        $this->queryResumo = $topico;
        $this->dispatch('sw-mudar-aba', aba: 'resumo');
    }

    public function render()
    {
        $paginas = $this->disciplina->paginas()
            ->with('tags')
            ->orderBy('titulo')
            ->get();

        $slug = $this->disciplina->slug;
        $carregar = fn (string $tipo) => Geracao::whereRaw("escopo->>'disciplina' = ?", [$slug])
            ->where('tipo', $tipo)
            ->with('fontes.pagina')
            ->withCount('respostas')
            ->latest()
            ->get();

        $evolucao = app(EvolucaoService::class);

        return view('livewire.disciplina', [
            'paginas' => $paginas,
            'geracoesResumo' => $carregar('resumo'),
            'geracoesFlashcards' => $carregar('flashcards'),
            'geracoesSimulado' => $carregar('simulado'),
            'geracoesMapaMental' => $carregar('mapa_mental'),
            'scoresPorSessao' => $evolucao->scoresPorSessao($slug),
            'errosPorTopico' => $evolucao->errosPorTopico($slug),
            'tempoVsEstimado' => $evolucao->tempoVsEstimado($slug),
            'distribuicaoQuestoes' => $evolucao->distribuicaoQuestoes($slug),
            'criteriosMaisPerdidos' => $evolucao->criteriosMaisPerdidos($slug),
            'lacunas' => app(LacunaService::class)->detectar($this->disciplina),
        ])->layout('layouts.app')->title($this->disciplina->nome);
    }

    private function despacharJob(string $tipo, ?string $query = null, int $tempoEstimado = 0): void
    {
        $this->latestIds[$tipo] = Geracao::whereRaw("escopo->>'disciplina' = ?", [$this->disciplina->slug])
            ->where('tipo', $tipo)
            ->max('id') ?? 0;

        $this->tiposGerando = array_unique([...$this->tiposGerando, $tipo]);

        GerarConteudoJob::dispatch(
            $tipo,
            $this->disciplina->slug,
            $query,
            $this->nQuestoes,
            $this->nDissertativas,
            $this->dificuldade,
            $this->perfil,
            $tempoEstimado,
        );
    }

    private function labelToast(string $tipo): string
    {
        return match ($tipo) {
            'resumo' => 'Resumo',
            'flashcards' => 'Flashcards',
            'simulado' => 'Simulado',
            'mapa_mental' => 'Mapa mental',
            default => ucfirst($tipo),
        };
    }
}
