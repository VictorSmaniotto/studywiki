<?php

namespace App\Services\AI;

use App\Models\Geracao;
use App\Services\Retrieval\Escopo;
use Illuminate\Support\Collection;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\Response as StructuredResponse;

class FlashcardsGenerator extends AbstractGenerator
{
    private int $quantidade = 10;

    public function gerar(Escopo $escopo, int $quantidade = 10): Geracao
    {
        $this->quantidade = $quantidade;

        return $this->executarPipeline($escopo);
    }

    protected function tipo(): string
    {
        return 'flashcards';
    }

    protected function chamarLLM(array $chunks): StructuredResponse
    {
        return Prism::structured()
            ->using('anthropic', static::MODELO)
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->userPrompt($this->amostrarChunks($chunks), $this->quantidade))
            ->withSchema($this->buildSchema())
            ->asStructured();
    }

    protected function validarConteudo(array $payload, array $chunks): bool
    {
        $cards = $payload['cards'] ?? [];

        if (empty($cards)) {
            return false;
        }

        // AC-F3: frentes normalizadas devem ser únicas.
        $frentesVistas = [];
        foreach ($cards as $card) {
            $normalizada = $this->normalizarFrente($card['frente'] ?? '');
            if (isset($frentesVistas[$normalizada])) {
                return false;
            }
            $frentesVistas[$normalizada] = true;
        }

        // AC-F1 + AC-F2: verso ancorado e frente/verso não-vazios.
        foreach ($cards as $card) {
            if (empty(trim($card['frente'] ?? '')) || empty(trim($card['verso'] ?? ''))) {
                return false;
            }

            $fontes = array_values(array_map(
                fn ($f) => array_filter([
                    'pagina_id' => (int) ($f['pagina_id'] ?? 0),
                    'chunk_id' => isset($f['chunk_id']) ? (int) $f['chunk_id'] : null,
                ], fn ($v) => $v !== null),
                $card['fontes'] ?? []
            ));

            if (! $this->validator->validate([
                'texto' => $card['verso'],
                'fontes' => $fontes,
            ], $chunks)->aprovado) {
                return false;
            }
        }

        return true;
    }

    protected function extrairPaginaIds(array $payload): Collection
    {
        return collect($payload['cards'] ?? [])
            ->flatMap(fn ($c) => $c['fontes'] ?? [])
            ->map(fn ($f) => (int) ($f['pagina_id'] ?? 0))
            ->filter();
    }

    private function normalizarFrente(string $frente): string
    {
        $frente = mb_strtolower($frente, 'UTF-8');
        $frente = preg_replace('/[^\p{L}\p{N}\s]/u', '', $frente) ?? $frente;

        return trim(preg_replace('/\s+/', ' ', $frente) ?? $frente);
    }

    private function systemPrompt(): string
    {
        return 'Você é um gerador de flashcards de estudo. Crie cards baseados EXCLUSIVAMENTE no conteúdo entre tags [CHUNK]. Não invente fatos ausentes nos chunks. Cada card deve ter: frente (pergunta ou conceito) e verso (resposta objetiva). O verso DEVE referenciar os chunk_id e pagina_id que o fundamentam. Não repita conceitos — cada frente deve ser única.';
    }

    private function userPrompt(array $chunks, int $quantidade): string
    {
        $contexto = collect($chunks)
            ->map(fn ($c) => "[CHUNK pagina_id={$c['pagina_id']} chunk_id={$c['chunk_id']}]\n{$c['conteudo']}\n[/CHUNK]")
            ->implode("\n\n");

        return "Gere {$quantidade} flashcards baseados EXCLUSIVAMENTE no seguinte conteúdo:\n\n{$contexto}\n\nRegras: frente é pergunta ou conceito-chave; verso é resposta objetiva e concisa; liste pagina_id e chunk_id das fontes do verso; frentes únicas (sem repetição de conceito).";
    }

    private function buildSchema(): ObjectSchema
    {
        $fonteSchema = new ObjectSchema(
            name: 'fonte',
            description: 'Referência a um chunk do conteúdo recuperado',
            properties: [
                new NumberSchema('pagina_id', 'ID numérico da página'),
                new NumberSchema('chunk_id', 'ID numérico do chunk'),
            ],
            requiredFields: ['pagina_id', 'chunk_id'],
        );

        $cardSchema = new ObjectSchema(
            name: 'card',
            description: 'Flashcard com frente, verso e fontes',
            properties: [
                new StringSchema('frente', 'Pergunta ou conceito-chave'),
                new StringSchema('verso', 'Resposta objetiva e concisa'),
                new ArraySchema('fontes', 'Fontes que fundamentam o verso', $fonteSchema),
                new StringSchema('disciplina', 'Nome da disciplina'),
                new ArraySchema('tags', 'Tags relacionadas ao card', new StringSchema('tag', 'Tag')),
            ],
            requiredFields: ['frente', 'verso', 'fontes'],
        );

        return new ObjectSchema(
            name: 'flashcards',
            description: 'Baralho de flashcards ancorados nos chunks',
            properties: [
                new ArraySchema('cards', 'Lista de flashcards', $cardSchema),
            ],
            requiredFields: ['cards'],
        );
    }
}
