<?php

namespace App\Services\AI;

use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class ChatService
{
    private const MODELO = 'claude-sonnet-4-6';

    private const MAX_CHUNKS = 15;

    private const RESPOSTA_SEM_CONTEXTO = 'Não encontrei isso nos seus apontamentos. Tente uma pergunta diferente ou verifique se o conteúdo foi sincronizado.';

    public function __construct(private readonly RetrievalService $retrieval) {}

    /**
     * Responde a pergunta com base nos chunks da vault.
     * Retorna resposta, fontes consultadas e custo em tokens.
     *
     * @param  array<int, array{role: string, content: string, fontes: array}>  $historico
     * @return array{resposta: string, fontes: list<array{chunk_id: int, pagina_id: int, titulo_pagina: string, heading_path: string|null}>, tokens: int}
     */
    public function responder(string $pergunta, Escopo $escopo, array $historico = []): array
    {
        $chunks = $this->retrieval->forQuery($pergunta, $escopo, self::MAX_CHUNKS);

        if (empty($chunks)) {
            return [
                'resposta' => self::RESPOSTA_SEM_CONTEXTO,
                'fontes' => [],
                'tokens' => 0,
            ];
        }

        $messages = $this->buildMessages($pergunta, $chunks, $historico);

        $response = Prism::text()
            ->using('anthropic', self::MODELO)
            ->withSystemPrompt($this->systemPrompt($chunks))
            ->withMessages($messages)
            ->asText();

        return [
            'resposta' => $response->text,
            'fontes' => $this->extrairFontes($chunks),
            'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $chunks
     * @param  array<int, array{role: string, content: string, fontes: array}>  $historico
     * @return list<UserMessage|AssistantMessage>
     */
    private function buildMessages(string $pergunta, array $chunks, array $historico): array
    {
        $messages = [];

        foreach ($historico as $msg) {
            if ($msg['role'] === 'user') {
                $messages[] = new UserMessage($msg['content']);
            } elseif ($msg['role'] === 'assistant') {
                $messages[] = new AssistantMessage($msg['content']);
            }
        }

        $contexto = collect($chunks)
            ->map(fn ($c) => "[CHUNK pagina_id={$c['pagina_id']} chunk_id={$c['chunk_id']} fonte=\"{$c['titulo_pagina']}\"]\n{$c['conteudo']}\n[/CHUNK]")
            ->implode("\n\n");

        $messages[] = new UserMessage(
            "Pergunta: {$pergunta}\n\nContexto dos apontamentos:\n{$contexto}"
        );

        return $messages;
    }

    private function systemPrompt(array $chunks): string
    {
        return 'Você é um assistente de estudos. Responda à pergunta do usuário com base EXCLUSIVAMENTE no conteúdo entre tags [CHUNK] fornecido. Não invente informações ausentes nos apontamentos. Seja direto e didático. Se o contexto não contiver a resposta, diga isso claramente.';
    }

    /**
     * @param  list<array<string, mixed>>  $chunks
     * @return list<array{chunk_id: int, pagina_id: int, titulo_pagina: string, heading_path: string|null}>
     */
    private function extrairFontes(array $chunks): array
    {
        $vistas = [];
        $fontes = [];

        foreach ($chunks as $chunk) {
            $key = "{$chunk['pagina_id']}-{$chunk['heading_path']}";
            if (! isset($vistas[$key])) {
                $vistas[$key] = true;
                $fontes[] = [
                    'chunk_id' => $chunk['chunk_id'],
                    'pagina_id' => $chunk['pagina_id'],
                    'titulo_pagina' => $chunk['titulo_pagina'],
                    'heading_path' => $chunk['heading_path'],
                ];
            }
        }

        return $fontes;
    }
}
