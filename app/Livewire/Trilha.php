<?php

namespace App\Livewire;

use App\Models\Flashcard;
use App\Services\TrilhaService;
use Livewire\Component;

class Trilha extends Component
{
    public bool $modoRevisao = false;

    public bool $sessaoConcluida = false;

    public array $idsParaRevisar = [];

    public int $indiceAtual = 0;

    public string $respostaUsuario = '';

    public bool $respostaRevelada = false;

    public int $acertos = 0;

    public int $erros = 0;

    public function iniciarRevisao(): void
    {
        $ids = app(TrilhaService::class)->flashcardsVencidos()->pluck('id')->all();

        if (empty($ids)) {
            return;
        }

        $this->idsParaRevisar = $ids;
        $this->indiceAtual = 0;
        $this->modoRevisao = true;
        $this->respostaUsuario = '';
        $this->respostaRevelada = false;
        $this->acertos = 0;
        $this->erros = 0;
    }

    public function revelarResposta(): void
    {
        $this->respostaRevelada = true;
    }

    public function avaliar(bool $acertou): void
    {
        if (empty($this->idsParaRevisar)) {
            return;
        }

        $cardId = $this->idsParaRevisar[$this->indiceAtual];
        $service = app(TrilhaService::class);
        $service->marcarRevisao($cardId, $acertou);

        $acertou ? $this->acertos++ : $this->erros++;

        $this->respostaUsuario = '';
        $this->respostaRevelada = false;

        if ($this->indiceAtual + 1 >= count($this->idsParaRevisar)) {
            $service->registrarSessao();
            $this->sessaoConcluida = true;
        } else {
            $this->indiceAtual++;
        }
    }

    public function encerrarRevisao(): void
    {
        $this->modoRevisao = false;
        $this->sessaoConcluida = false;
        $this->idsParaRevisar = [];
        $this->indiceAtual = 0;
        $this->acertos = 0;
        $this->erros = 0;
        $this->respostaUsuario = '';
        $this->respostaRevelada = false;
    }

    public function render()
    {
        $service = app(TrilhaService::class);

        $flashcardAtual = null;
        if ($this->modoRevisao && ! $this->sessaoConcluida && ! empty($this->idsParaRevisar)) {
            $flashcardAtual = Flashcard::find($this->idsParaRevisar[$this->indiceAtual]);
        }

        $flashcardsVencidos = collect();
        $topicosPrioritarios = [];
        if (! $this->modoRevisao) {
            $flashcardsVencidos = $service->flashcardsVencidos();
            $topicosPrioritarios = $service->topicosPrioritarios();
        }

        return view('livewire.trilha', [
            'streak' => $service->streakAtual(),
            'flashcardAtual' => $flashcardAtual,
            'flashcardsVencidos' => $flashcardsVencidos,
            'topicosPrioritarios' => $topicosPrioritarios,
            'totalCards' => count($this->idsParaRevisar),
        ])->layout('layouts.app');
    }
}
