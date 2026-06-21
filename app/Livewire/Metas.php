<?php

namespace App\Livewire;

use App\Services\MetaService;
use Livewire\Component;

class Metas extends Component
{
    public int $metaSimulados = 0;

    public int $metaFlashcards = 0;

    public int $metaGeracoes = 0;

    public bool $salvo = false;

    public function mount(): void
    {
        $progresso = app(MetaService::class)->progressoSemana();
        $this->metaSimulados = $progresso['simulados']['meta'];
        $this->metaFlashcards = $progresso['flashcards']['meta'];
        $this->metaGeracoes = $progresso['geracoes']['meta'];
    }

    public function salvar(): void
    {
        $this->validate([
            'metaSimulados' => 'required|integer|min:0',
            'metaFlashcards' => 'required|integer|min:0',
            'metaGeracoes' => 'required|integer|min:0',
        ]);

        app(MetaService::class)->salvarMetas(
            $this->metaSimulados,
            $this->metaFlashcards,
            $this->metaGeracoes,
        );

        $this->salvo = true;
    }

    public function render()
    {
        return view('livewire.metas', [
            'progresso' => app(MetaService::class)->progressoSemana(),
        ])->layout('layouts.app');
    }
}
