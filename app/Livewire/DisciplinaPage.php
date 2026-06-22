<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Models\Geracao;
use App\Services\AI\FlashcardsGenerator;
use App\Services\AI\MapaMentalGenerator;
use App\Services\AI\ResumoGenerator;
use App\Services\AI\SimuladoGenerator;
use App\Services\EvolucaoService;
use App\Services\LacunaService;
use App\Services\Retrieval\Escopo;
use Livewire\Component;

class DisciplinaPage extends Component
{
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

        $geracao = app(ResumoGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug, query: $this->queryResumo ?: null)
        );

        if ($geracao->status === 'ok') {
            $this->expandidos[] = $geracao->id;
        } else {
            $this->erroResumo = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
    }

    public function gerarFlashcards(): void
    {
        $this->erroFlashcards = '';

        $geracao = app(FlashcardsGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug, query: $this->queryFlashcards ?: null)
        );

        if ($geracao->status === 'ok') {
            $this->expandidos[] = $geracao->id;
        } else {
            $this->erroFlashcards = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
    }

    public function gerarSimulado(): void
    {
        $this->erroSimulado = '';

        $tempoEstimado = match ($this->perfil) {
            'universitario' => 36 * 60,
            'vestibular' => 120 * 60,
            default => 0,
        };

        $geracao = app(SimuladoGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug, query: $this->querySimulado ?: null),
            $this->nQuestoes,
            $this->nDissertativas,
            $this->dificuldade,
            $this->perfil,
            $tempoEstimado,
        );

        if ($geracao->status === 'ok') {
            $this->expandidos[] = $geracao->id;
        } else {
            $this->erroSimulado = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
    }

    public function gerarMapaMental(): void
    {
        $this->erroMapaMental = '';

        $geracao = app(MapaMentalGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug)
        );

        if ($geracao->status === 'ok') {
            $this->expandidos[] = $geracao->id;
        } else {
            $this->erroMapaMental = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
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
}
