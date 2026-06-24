<?php

namespace App\Jobs;

use App\Models\ChatSessao;
use App\Services\AI\ChatService;
use App\Services\Retrieval\Escopo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ChatResponseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  string[]  $disciplinasSlugs
     * @param  list<array{role: string, content: string, fontes: array, status: string}>  $historicoPrevio
     */
    public function __construct(
        private readonly int $sessaoId,
        private readonly string $pergunta,
        private readonly array $disciplinasSlugs,
        private readonly array $historicoPrevio,
    ) {}

    public function handle(ChatService $service): void
    {
        $sessao = ChatSessao::find($this->sessaoId);
        if (! $sessao) {
            return;
        }

        $escopo = new Escopo(disciplinas: $this->disciplinasSlugs);
        $resultado = $service->responder($this->pergunta, $escopo, $this->historicoPrevio);

        $historico = $sessao->historico ?? [];

        foreach ($historico as $i => $msg) {
            if ($msg['role'] === 'assistant' && ($msg['status'] ?? '') === 'pending') {
                $historico[$i] = [
                    'role' => 'assistant',
                    'content' => $resultado['resposta'],
                    'fontes' => $resultado['fontes'],
                    'status' => 'done',
                ];
                break;
            }
        }

        $sessao->update(['historico' => $historico]);
    }
}
