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

class MapaMentalGenerator extends AbstractGenerator
{
    public function gerar(Escopo $escopo): Geracao
    {
        return $this->executarPipeline($escopo);
    }

    protected function tipo(): string
    {
        return 'mapa_mental';
    }

    protected function chamarLLM(array $chunks): StructuredResponse
    {
        return Prism::structured()
            ->using('anthropic', static::MODELO)
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->userPrompt($this->amostrarChunks($chunks)))
            ->withSchema($this->buildSchema())
            ->asStructured();
    }

    protected function validarConteudo(array $payload, array $chunks): bool
    {
        $nos = $payload['nos'] ?? [];
        if (empty($nos)) {
            return false;
        }

        foreach ($nos as $no) {
            $fontes = array_values(array_map(
                fn ($f) => [
                    'pagina_id' => (int) ($f['pagina_id'] ?? 0),
                    'chunk_id' => (int) ($f['chunk_id'] ?? 0),
                ],
                $no['fontes'] ?? []
            ));

            if (! $this->validator->validate([
                'texto' => $no['texto'] ?? '',
                'fontes' => $fontes,
            ], $chunks)->aprovado) {
                return false;
            }
        }

        return true;
    }

    protected function extrairPaginaIds(array $payload): Collection
    {
        return collect($payload['nos'] ?? [])
            ->flatMap(fn ($no) => $no['fontes'] ?? [])
            ->map(fn ($f) => (int) ($f['pagina_id'] ?? 0))
            ->filter();
    }

    /**
     * Converte os nós estruturados para sintaxe Mermaid mindmap.
     * Os nós devem estar em ordem: nó raiz (nivel=0) seguido de filhos em nivel crescente.
     *
     * @param  array<int, array{texto: string, nivel: int}>  $nos
     */
    public static function gerarMermaidCode(string $titulo, array $nos): string
    {
        $linhas = ['mindmap', '  root(('.$titulo.'))'];

        foreach ($nos as $no) {
            $indent = str_repeat('  ', $no['nivel'] + 2);
            $texto = addslashes($no['texto'] ?? '');
            $linhas[] = $indent.$texto;
        }

        return implode("\n", $linhas);
    }

    private function systemPrompt(): string
    {
        return 'Você é um gerador de mapas mentais de estudo. Crie um mapa mental estruturado baseado EXCLUSIVAMENTE no conteúdo entre tags [CHUNK]. Não invente fatos ausentes. Cada nó DEVE referenciar os chunk_id e pagina_id que o fundamentam. O nó raiz representa o tema geral; filhos são subtemas; netos são detalhes.';
    }

    private function userPrompt(array $chunks): string
    {
        $contexto = collect($chunks)
            ->map(fn ($c) => "[CHUNK pagina_id={$c['pagina_id']} chunk_id={$c['chunk_id']}]\n{$c['conteudo']}\n[/CHUNK]")
            ->implode("\n\n");

        return "Gere um mapa mental baseado EXCLUSIVAMENTE no seguinte conteúdo:\n\n{$contexto}\n\nRegras: organize em níveis hierárquicos (nivel 1 = subtemas principais, nivel 2 = detalhes); cada nó deve citar as fontes (pagina_id e chunk_id); máximo 3 níveis.";
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

        $noSchema = new ObjectSchema(
            name: 'no',
            description: 'Nó do mapa mental',
            properties: [
                new StringSchema('texto', 'Texto conciso do nó (máx 60 chars)'),
                new NumberSchema('nivel', 'Nível hierárquico: 1=subtema principal, 2=detalhe, 3=detalhe profundo'),
                new ArraySchema('fontes', 'Fontes que fundamentam este nó', $fonteSchema),
            ],
            requiredFields: ['texto', 'nivel', 'fontes'],
        );

        return new ObjectSchema(
            name: 'mapa_mental',
            description: 'Mapa mental ancorado nos chunks',
            properties: [
                new StringSchema('titulo', 'Título/tema central do mapa'),
                new ArraySchema('nos', 'Nós do mapa mental em ordem hierárquica', $noSchema),
            ],
            requiredFields: ['titulo', 'nos'],
        );
    }
}
