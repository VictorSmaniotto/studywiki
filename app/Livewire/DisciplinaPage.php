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

    public ?Geracao $geracao = null;

    public string $erro = '';

    public function mount(string $slug): void
    {
        $this->disciplina = Disciplina::where('slug', $slug)->firstOrFail();
    }

    public function gerarResumo(): void
    {
        $this->erro = '';
        $this->geracao = null;

        $geracao = app(ResumoGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug)
        );

        if ($geracao->status === 'ok') {
            $this->geracao = $geracao;
            $this->dispatch('geracaoCompleta', tipo: 'resumo');
        } else {
            $this->erro = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
    }

    public function gerarFlashcards(): void
    {
        $this->erro = '';
        $this->geracao = null;

        $geracao = app(FlashcardsGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug)
        );

        if ($geracao->status === 'ok') {
            $this->geracao = $geracao;
            $this->dispatch('geracaoCompleta', tipo: 'flashcards');
        } else {
            $this->erro = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
    }

    public function gerarSimulado(): void
    {
        $this->erro = '';
        $this->geracao = null;

        $geracao = app(SimuladoGenerator::class)->gerar(
            new Escopo(disciplina: $this->disciplina->slug)
        );

        if ($geracao->status === 'ok') {
            $this->geracao = $geracao;
            $this->dispatch('geracaoCompleta', tipo: 'simulado');
        } else {
            $this->erro = 'Geração rejeitada: conteúdo insuficiente para ancoragem. Tente novamente.';
        }
    }

    public function render()
    {
        $paginas = $this->disciplina->paginas()
            ->with('tags')
            ->orderBy('titulo')
            ->get();

        $fontesPaginas = $this->geracao
            ? $this->geracao->fontes()->with('pagina')->get()->keyBy('pagina_id')
            : collect();

        return view('livewire.disciplina', compact('paginas', 'fontesPaginas'))
            ->layout('layouts.app');
    }
}
