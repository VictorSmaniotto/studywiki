<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\Pagina;
use Livewire\Attributes\Url;
use Livewire\Component;

class Biblioteca extends Component
{
    #[Url(as: 'q')]
    public string $busca = '';

    public function render()
    {
        $disciplinas = Disciplina::withCount('paginas')
            ->when($this->busca, fn ($q) => $q->where('nome', 'like', '%'.$this->busca.'%'))
            ->orderBy('nome')
            ->get();

        $totalDisciplinas = Disciplina::count();
        $totalPaginas = Pagina::count();
        $totalGeracoes = Geracao::where('status', 'ok')->count();

        return view('livewire.biblioteca', compact('disciplinas', 'totalDisciplinas', 'totalPaginas', 'totalGeracoes'))
            ->layout('layouts.app')
            ->title('Biblioteca');
    }
}
