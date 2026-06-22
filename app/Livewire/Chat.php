<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Services\AI\ChatService;
use App\Services\Retrieval\Escopo;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Chat extends Component
{
    private const SESSION_KEY = 'chat_historico';

    #[Validate('required|string|min:3|max:500')]
    public string $pergunta = '';

    public string $escopoSlug = '';

    public bool $carregando = false;

    /** @var array<int, array{role: string, content: string, fontes: array}> */
    public array $historico = [];

    public function mount(): void
    {
        $this->historico = session(self::SESSION_KEY, []);
    }

    public function enviar(): void
    {
        $this->validate();

        $pergunta = trim($this->pergunta);
        $this->pergunta = '';
        $this->carregando = true;

        $this->historico[] = [
            'role' => 'user',
            'content' => $pergunta,
            'fontes' => [],
        ];

        $escopo = new Escopo(
            disciplina: $this->escopoSlug !== '' ? $this->escopoSlug : null,
        );

        $resultado = app(ChatService::class)->responder($pergunta, $escopo, $this->historico);

        $this->historico[] = [
            'role' => 'assistant',
            'content' => $resultado['resposta'],
            'fontes' => $resultado['fontes'],
        ];

        session([self::SESSION_KEY => $this->historico]);

        $this->carregando = false;
    }

    public function limpar(): void
    {
        $this->historico = [];
        session()->forget(self::SESSION_KEY);
    }

    public function render()
    {
        return view('livewire.chat', [
            'disciplinas' => Disciplina::orderBy('nome')->get(['id', 'nome', 'slug']),
        ])->layout('layouts.app')->title('Chat');
    }
}
