<?php

namespace App\Services\AI;

use App\Models\Geracao;
use App\Services\Retrieval\Escopo;
use Illuminate\Support\Collection;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\Response as StructuredResponse;

class SimuladoGenerator extends AbstractGenerator
{
    private int $nMe = 5;

    private int $nDis = 0;

    private string $dificuldade = 'medio';

    public function gerar(Escopo $escopo, int $n_me = 5, int $n_dis = 0, string $dificuldade = 'medio'): Geracao
    {
        $this->nMe = $n_me;
        $this->nDis = $n_dis;
        $this->dificuldade = $dificuldade;

        return $this->executarPipeline($escopo);
    }

    protected function tipo(): string
    {
        return 'simulado';
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
        $questoesME = $payload['questoes_me'] ?? [];
        $questoesDis = $payload['questoes_dis'] ?? [];

        if (empty($questoesME) && empty($questoesDis)) {
            return false;
        }

        foreach ($questoesME as $questao) {
            $texto = trim(
                ($questao['contexto'] ?? '').' '.
                ($questao['enunciado'] ?? '').' '.
                ($questao['alternativas'][$questao['correta'] ?? ''] ?? '')
            );

            if (! $this->validator->validate(['texto' => $texto, 'fontes' => $this->normalizarFontes($questao['fontes'] ?? [])], $chunks)->aprovado) {
                return false;
            }
        }

        foreach ($questoesDis as $questao) {
            $texto = trim(($questao['enunciado'] ?? '').' '.($questao['gabarito_referencia'] ?? ''));

            if (! $this->validator->validate(['texto' => $texto, 'fontes' => $this->normalizarFontes($questao['fontes'] ?? [])], $chunks)->aprovado) {
                return false;
            }
        }

        return true;
    }

    protected function extrairPaginaIds(array $payload): Collection
    {
        $meIds = collect($payload['questoes_me'] ?? [])
            ->flatMap(fn ($q) => $q['fontes'] ?? [])
            ->map(fn ($f) => (int) ($f['pagina_id'] ?? 0));

        $disIds = collect($payload['questoes_dis'] ?? [])
            ->flatMap(fn ($q) => $q['fontes'] ?? [])
            ->map(fn ($f) => (int) ($f['pagina_id'] ?? 0));

        return $meIds->merge($disIds)->filter();
    }

    private function normalizarFontes(array $fontes): array
    {
        return array_values(array_map(
            fn ($f) => array_filter([
                'pagina_id' => (int) ($f['pagina_id'] ?? 0),
                'chunk_id' => isset($f['chunk_id']) ? (int) $f['chunk_id'] : null,
            ], fn ($v) => $v !== null),
            $fontes
        ));
    }

    private function systemPrompt(): string
    {
        $tipos = [];
        if ($this->nMe > 0) {
            $tipos[] = "{$this->nMe} questões de múltipla escolha";
        }
        if ($this->nDis > 0) {
            $tipos[] = "{$this->nDis} questões dissertativas";
        }
        $descTipos = implode(' e ', $tipos);

        return "Você é um gerador de simulados de prova. Crie {$descTipos} ancoradas EXCLUSIVAMENTE no conteúdo entre tags [CHUNK]. Não invente fatos ausentes nos chunks. Se o conteúdo for insuficiente, gere menos questões. Cada questão DEVE referenciar os chunk_id e pagina_id que a fundamentam.";
    }

    private function userPrompt(array $chunks): string
    {
        $contexto = collect($chunks)
            ->map(fn ($c) => "[CHUNK pagina_id={$c['pagina_id']} chunk_id={$c['chunk_id']}]\n{$c['conteudo']}\n[/CHUNK]")
            ->implode("\n\n");

        $instrucoes = [];

        if ($this->nMe > 0) {
            $instrucoes[] = "- {$this->nMe} questões de múltipla escolha de nível {$this->dificuldade}: parágrafo de contexto, 5 alternativas (a-e), uma correta, distratores plausíveis, misture formatos direto e I/II/III, comentário de gabarito por alternativa.";
        }

        if ($this->nDis > 0) {
            $instrucoes[] = "- {$this->nDis} questões dissertativas de nível {$this->dificuldade}: enunciado claro, rubrica com 2-4 critérios (nome + peso, pesos somam 1.0), gabarito_referencia completo.";
        }

        $instrText = implode("\n", $instrucoes);

        return "Gere um simulado baseado EXCLUSIVAMENTE no seguinte conteúdo:\n\n{$contexto}\n\nGere:\n{$instrText}\n\nListe pagina_id e chunk_id das fontes em cada questão.";
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

        $alternativasSchema = new ObjectSchema(
            name: 'alternativas',
            description: 'Texto de cada alternativa (a–e)',
            properties: [
                new StringSchema('a', 'Alternativa A'),
                new StringSchema('b', 'Alternativa B'),
                new StringSchema('c', 'Alternativa C'),
                new StringSchema('d', 'Alternativa D'),
                new StringSchema('e', 'Alternativa E'),
            ],
            requiredFields: ['a', 'b', 'c', 'd', 'e'],
        );

        $comentarioSchema = new ObjectSchema(
            name: 'comentario_gabarito',
            description: 'Comentário explicativo para cada alternativa',
            properties: [
                new StringSchema('a', 'Por que A está certa ou errada'),
                new StringSchema('b', 'Por que B está certa ou errada'),
                new StringSchema('c', 'Por que C está certa ou errada'),
                new StringSchema('d', 'Por que D está certa ou errada'),
                new StringSchema('e', 'Por que E está certa ou errada'),
            ],
            requiredFields: ['a', 'b', 'c', 'd', 'e'],
        );

        $questaoMESchema = new ObjectSchema(
            name: 'questao_me',
            description: 'Questão de múltipla escolha',
            properties: [
                new StringSchema('contexto', 'Parágrafo de contexto antes da questão'),
                new StringSchema('enunciado', 'Enunciado da questão'),
                new EnumSchema('formato', 'Formato da questão', ['direto', 'I_II_III']),
                $alternativasSchema,
                new EnumSchema('correta', 'Letra da alternativa correta', ['a', 'b', 'c', 'd', 'e']),
                new ArraySchema('fontes', 'Fontes que fundamentam a questão', $fonteSchema),
                $comentarioSchema,
            ],
            requiredFields: ['contexto', 'enunciado', 'formato', 'alternativas', 'correta', 'fontes', 'comentario_gabarito'],
        );

        $rubricaItemSchema = new ObjectSchema(
            name: 'criterio_rubrica',
            description: 'Critério de avaliação da questão dissertativa',
            properties: [
                new StringSchema('criterio', 'Descrição do critério de avaliação'),
                new NumberSchema('peso', 'Peso do critério (0.0–1.0; todos devem somar 1.0)'),
            ],
            requiredFields: ['criterio', 'peso'],
        );

        $questaoDissSchema = new ObjectSchema(
            name: 'questao_dissertativa',
            description: 'Questão dissertativa com rubrica explícita',
            properties: [
                new StringSchema('enunciado', 'Enunciado da questão dissertativa'),
                new ArraySchema('rubrica', 'Critérios de avaliação com pesos', $rubricaItemSchema),
                new StringSchema('gabarito_referencia', 'Resposta modelo completa para o avaliador'),
                new ArraySchema('fontes', 'Fontes que fundamentam a questão', $fonteSchema),
            ],
            requiredFields: ['enunciado', 'rubrica', 'gabarito_referencia', 'fontes'],
        );

        return new ObjectSchema(
            name: 'simulado',
            description: 'Simulado híbrido com múltipla escolha e dissertativas',
            properties: [
                new ArraySchema('questoes_me', 'Questões de múltipla escolha', $questaoMESchema),
                new ArraySchema('questoes_dis', 'Questões dissertativas', $questaoDissSchema),
            ],
            requiredFields: ['questoes_me', 'questoes_dis'],
        );
    }
}
