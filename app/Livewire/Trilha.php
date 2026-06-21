<?php

namespace App\Livewire;

use App\Services\TrilhaService;
use Livewire\Component;

class Trilha extends Component
{
    public bool $sessaoRegistrada = false;

    public function registrarSessao(): void
    {
        app(TrilhaService::class)->registrarSessao();
        $this->sessaoRegistrada = true;
    }

    public function render()
    {
        $service = app(TrilhaService::class);

        return view('livewire.trilha', [
            'flashcardsVencidos' => $service->flashcardsVencidos(),
            'topicosPrioritarios' => $service->topicosPrioritarios(),
            'streak' => $service->streakAtual(),
        ])->layout('layouts.app');
    }
}
