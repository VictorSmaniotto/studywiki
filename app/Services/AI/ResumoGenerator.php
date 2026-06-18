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

class ResumoGenerator extends AbstractGenerator
{
    public function gerar(Escopo $escopo): Geracao
    {
        return $this->executarPipeline($escopo);
    }

    protected function tipo(): string
    {
        return 'resumo';
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
        $secoes = $payload['secoes'] ?? [];
        if (empty($secoes)) {
            return false;
        }

        $totalBulletChars = 0;
        $bullets = [];

        foreach ($secoes as $secao) {
            foreach ($secao['bullets'] ?? [] as $bullet) {
                $totalBulletChars += mb_strlen($bullet['texto'] ?? '', 'UTF-8');
                $bullets[] = $bullet;
            }
        }

        if (empty($bullets)) {
            return false;
        }

        // AC-R3: resumo deve ser mais curto que a soma dos chunks.
        $totalChunkChars = array_sum(
            array_map(fn ($c) => mb_strlen($c['conteudo'], 'UTF-8'), $chunks)
        );

        if ($totalBulletChars >= $totalChunkChars) {
            return false;
        }

        // AC-R1 + AC-R2: cada bullet referencia fontes válidas e passa no overlap léxico.
        foreach ($bullets as $bullet) {
            $fontes = array_values(array_map(
                fn ($f) => array_filter([
                    'pagina_id' => (int) ($f['pagina_id'] ?? 0),
                    'chunk_id' => isset($f['chunk_id']) ? (int) $f['chunk_id'] : null,
                ], fn ($v) => $v !== null),
                $bullet['fontes'] ?? []
            ));

            if (! $this->validator->validate([
                'texto' => $bullet['texto'] ?? '',
                'fontes' => $fontes,
            ], $chunks)->aprovado) {
                return false;
            }
        }

        return true;
    }

    protected function extrairPaginaIds(array $payload): Collection
    {
        return collect($payload['secoes'] ?? [])
            ->flatMap(fn ($s) => $s['bullets'] ?? [])
            ->flatMap(fn ($b) => $b['fontes'] ?? [])
            ->map(fn ($f) => (int) ($f['pagina_id'] ?? 0))
            ->filter();
    }

    private function systemPrompt(): string
    {
        return 'Você é um gerador de resumos de estudo. Crie um resumo estruturado baseado EXCLUSIVAMENTE no conteúdo entre tags [CHUNK]. Não invente fatos ausentes nos chunks. Se o conteúdo for insuficiente, gere menos bullets. Cada bullet DEVE referenciar os chunk_id e pagina_id que o fundamentam. O resumo total deve ser mais curto que os chunks fornecidos.';
    }

    private function userPrompt(array $chunks): string
    {
        $contexto = collect($chunks)
            ->map(fn ($c) => "[CHUNK pagina_id={$c['pagina_id']} chunk_id={$c['chunk_id']}]\n{$c['conteudo']}\n[/CHUNK]")
            ->implode("\n\n");

        return "Gere um resumo estruturado baseado EXCLUSIVAMENTE no seguinte conteúdo:\n\n{$contexto}\n\nRegras: organize em seções com heading; cada bullet deve ser conciso e citar as fontes (pagina_id e chunk_id); o resumo deve ser mais curto que o conteúdo original.";
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

        $bulletSchema = new ObjectSchema(
            name: 'bullet',
            description: 'Ponto do resumo com sua fonte',
            properties: [
                new StringSchema('texto', 'Texto conciso do bullet'),
                new ArraySchema('fontes', 'Fontes que fundamentam este bullet', $fonteSchema),
            ],
            requiredFields: ['texto', 'fontes'],
        );

        $secaoSchema = new ObjectSchema(
            name: 'secao',
            description: 'Seção temática do resumo',
            properties: [
                new StringSchema('heading', 'Título da seção'),
                new ArraySchema('bullets', 'Pontos desta seção', $bulletSchema),
            ],
            requiredFields: ['heading', 'bullets'],
        );

        return new ObjectSchema(
            name: 'resumo',
            description: 'Resumo estruturado ancorado nos chunks',
            properties: [
                new StringSchema('titulo', 'Título do resumo'),
                new ArraySchema('secoes', 'Seções do resumo', $secaoSchema),
                new ArraySchema('fontes_globais', 'Todas as fontes usadas no resumo', $fonteSchema),
            ],
            requiredFields: ['titulo', 'secoes', 'fontes_globais'],
        );
    }
}
