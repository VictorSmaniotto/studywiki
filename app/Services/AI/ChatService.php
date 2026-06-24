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

    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly TokenUsageLogger $usageLogger = new TokenUsageLogger,
    ) {}

    /**
     * Responde a pergunta com base nos chunks da vault.
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
            ->withSystemPrompt($this->systemPrompt())
            ->withMessages($messages)
            ->asText();

        $inputTokens = $response->usage->promptTokens;
        $outputTokens = $response->usage->completionTokens;
        $cacheWrite = $response->usage->cacheWriteInputTokens ?? 0;
        $cacheRead = $response->usage->cacheReadInputTokens ?? 0;

        $this->usageLogger->log($inputTokens, $outputTokens, 'chat', $cacheWrite, $cacheRead);

        return [
            'resposta' => $response->text,
            'fontes' => $this->extrairFontes($chunks),
            'tokens' => $inputTokens + $outputTokens,
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

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é um tutor que auxilia o aluno a compreender profundamente o material de estudos.

REGRA DE FATOS: todo fato, dado, data ou afirmação deve ter origem nos blocos [CHUNK] fornecidos. Não introduza informações ausentes nos apontamentos.

No entanto: explicar, raciocinar, conectar ideias entre blocos e ilustrar com exemplos NÃO é "inventar" — é o seu papel pedagógico. Exerça-o com liberdade.

Para cada resposta:
1. Identifique o conceito central da pergunta.
2. Explique não apenas O QUE o material diz, mas POR QUE e COMO: mecanismos, causas, consequências e implicações.
3. Conecte informações de blocos diferentes quando houver relação entre eles.
4. Utilize exemplos ou analogias para esclarecer pontos difíceis, indicando quando o exemplo é ilustrativo e não consta nos apontamentos.
5. Aponte nuances, exceções ou equívocos comuns presentes no material.

Profundidade é mais importante que brevidade. Prefira explicar bem a resumir.
Adote um tom formal e pedagógico, como de professor para aluno. Sem informalidades ou emojis.

Se o material não contiver a resposta, informe o que falta. Se houver informação parcial relacionada, explique o que é possível inferir e o que permanece em aberto.
PROMPT;
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
