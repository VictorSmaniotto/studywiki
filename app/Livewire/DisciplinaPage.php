<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Models\Geracao;
use App\Services\AI\FlashcardsGenerator;
use App\Services\AI\ResumoGenerator;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Livewire\Component;

class DisciplinaPage extends Component
{
    public Disciplina $disciplina;

    public string $erroResumo = '';

    public string $erroFlashcards = '';

    public string $erroSimulado = '';

    public string $dificuldade = 'medio';

    public int $nQuestoes = 5;

    public int $nDissertativas = 3;

    public array $expandidos = [];

    public function mount(string $slug): void
    {
        $this->disciplina = Disciplina::where('slug', $slug)->firstOrFail();
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
            new Escopo(disciplina: $this->disciplina->slug)
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
            new Escopo(disciplina: $this->disciplina->slug)
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

        $geracao = app(SimuladoGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug),
            $this->nQuestoes,
            $this->nDissertativas,
            $this->dificuldade,
        );

        if ($geracao->status === 'ok') {
            $this->expandidos[] = $geracao->id;
        } else {
            $this->erroSimulado = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
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
            ->latest()
            ->get();

        return view('livewire.disciplina', [
            'paginas' => $paginas,
            'geracoesResumo' => $carregar('resumo'),
            'geracoesFlashcards' => $carregar('flashcards'),
            'geracoesSimulado' => $carregar('simulado'),
        ])->layout('layouts.app');
    }
}
