<?php

namespace App\Livewire;

use App\Jobs\ChatResponseJob;
use App\Models\ChatSessao;
use App\Models\Disciplina;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Chat extends Component
{
    private const SESSION_KEY = 'chat_sessao_id';

    #[Validate('required|string|min:3|max:500')]
    public string $pergunta = '';

    /** @var string[] */
    public array $disciplinasSlugs = [];

    public ?int $sessaoId = null;

    /** @var array<int, array{role: string, content: string, fontes: array, status: string}> */
    public array $historico = [];

    public bool $sidebarAberta = false;

    public function mount(): void
    {
        $sessaoId = session(self::SESSION_KEY);
        if ($sessaoId) {
            $sessao = ChatSessao::find($sessaoId);
            if ($sessao) {
                $this->sessaoId = $sessao->id;
                $this->historico = $sessao->historico;
            }
        }
    }

    public function enviar(): void
    {
        if ($this->sessaoId) {
            $this->refreshHistorico();
        }

        if ($this->temPendente()) {
            return;
        }

        $this->validate();

        $pergunta = trim($this->pergunta);
        $this->pergunta = '';

        $historicoPrevio = array_values(array_filter(
            $this->historico,
            fn ($m) => ($m['status'] ?? 'done') !== 'pending',
        ));

        $this->historico[] = [
            'role' => 'user',
            'content' => $pergunta,
            'fontes' => [],
            'status' => 'done',
        ];

        $this->historico[] = [
            'role' => 'assistant',
            'content' => '',
            'fontes' => [],
            'status' => 'pending',
        ];

        $this->autoSalvar();

        ChatResponseJob::dispatch($this->sessaoId, $pergunta, $this->disciplinasSlugs, $historicoPrevio);
    }

    public function refreshHistorico(): void
    {
        if (! $this->sessaoId) {
            return;
        }

        $sessao = ChatSessao::find($this->sessaoId);
        if ($sessao) {
            $this->historico = $sessao->historico ?? [];
        }
    }

    public function carregarSessao(int $id): void
    {
        $sessao = ChatSessao::find($id);
        if ($sessao) {
            $this->sessaoId = $sessao->id;
            $this->historico = $sessao->historico;
            session([self::SESSION_KEY => $this->sessaoId]);
        }
    }

    public function deletarSessao(int $id): void
    {
        ChatSessao::destroy($id);

        if ($this->sessaoId === $id) {
            $this->historico = [];
            $this->sessaoId = null;
            session()->forget(self::SESSION_KEY);
        }
    }

    public function removerDisciplina(string $slug): void
    {
        $this->disciplinasSlugs = array_values(
            array_filter($this->disciplinasSlugs, fn (string $s) => $s !== $slug)
        );
    }

    public function limpar(): void
    {
        $this->historico = [];
        $this->sessaoId = null;
        session()->forget(self::SESSION_KEY);
    }

    private function temPendente(): bool
    {
        return collect($this->historico)->contains(fn ($m) => ($m['status'] ?? 'done') === 'pending');
    }

    private function autoSalvar(): void
    {
        if ($this->sessaoId) {
            ChatSessao::where('id', $this->sessaoId)
                ->update(['historico' => json_encode($this->historico)]);
        } else {
            $firstMsg = collect($this->historico)->firstWhere('role', 'user');
            $titulo = $firstMsg ? Str::limit($firstMsg['content'], 55) : 'Conversa';

            $sessao = ChatSessao::create([
                'titulo' => $titulo,
                'historico' => $this->historico,
            ]);
            $this->sessaoId = $sessao->id;
        }

        session([self::SESSION_KEY => $this->sessaoId]);
    }

    public function render()
    {
        return view('livewire.chat', [
            'disciplinas' => Disciplina::orderBy('nome')->get(['id', 'nome', 'slug']),
            'sessoes' => ChatSessao::orderByDesc('updated_at')->limit(40)->get(['id', 'titulo', 'updated_at']),
        ])->layout('layouts.app')->title('Chat');
    }
}
