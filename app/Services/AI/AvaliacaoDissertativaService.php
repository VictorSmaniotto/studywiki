<?php

namespace App\Services\AI;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

class AvaliacaoDissertativaService
{
    private const MODELO = 'claude-sonnet-4-6';

    /**
     * Avalia uma resposta dissertativa segundo a rubrica da questão.
     *
     * @param  array  $questaoDis  Questão dissertativa com rubrica + gabarito_referencia
     * @param  string  $resposta  Texto livre digitado pelo aluno
     * @return array{notas: list<array{criterio: string, nota: float, feedback: string}>, nota_total: float, feedback_geral: string}
     */
    public function avaliar(array $questaoDis, string $resposta): array
    {
        $rubricaTexto = collect($questaoDis['rubrica'] ?? [])
            ->map(fn ($r) => "- {$r['criterio']} (peso {$r['peso']})")
            ->implode("\n");

        $prompt = <<<PROMPT
Você é um avaliador de provas. Avalie a resposta do aluno para a questão abaixo segundo a rubrica fornecida.
Atribua nota de 0.0 a 1.0 para cada critério e calcule nota_total como média ponderada pelos pesos.

**Questão:**
{$questaoDis['enunciado']}

**Gabarito de referência:**
{$questaoDis['gabarito_referencia']}

**Rubrica:**
{$rubricaTexto}

**Resposta do aluno:**
{$resposta}
PROMPT;

        $result = Prism::structured()
            ->using('anthropic', self::MODELO)
            ->withPrompt($prompt)
            ->withSchema($this->buildSchema())
            ->asStructured();

        return $result->structured;
    }

    private function buildSchema(): ObjectSchema
    {
        $notaItemSchema = new ObjectSchema(
            name: 'nota_criterio',
            description: 'Nota para um critério da rubrica',
            properties: [
                new StringSchema('criterio', 'Nome do critério avaliado'),
                new NumberSchema('nota', 'Nota de 0.0 a 1.0'),
                new StringSchema('feedback', 'Feedback explicando a nota'),
            ],
            requiredFields: ['criterio', 'nota', 'feedback'],
        );

        return new ObjectSchema(
            name: 'avaliacao',
            description: 'Avaliação da resposta dissertativa',
            properties: [
                new ArraySchema('notas', 'Notas por critério da rubrica', $notaItemSchema),
                new NumberSchema('nota_total', 'Nota total de 0.0 a 1.0 (média ponderada pelos pesos)'),
                new StringSchema('feedback_geral', 'Feedback geral sobre a resposta do aluno'),
            ],
            requiredFields: ['notas', 'nota_total', 'feedback_geral'],
        );
    }
}
