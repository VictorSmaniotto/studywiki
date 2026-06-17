<?php

namespace App\Services\AI;

use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Illuminate\Support\Facades\DB;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\Response as StructuredResponse;

class SimuladoGenerator
{
    private const MODELO = 'claude-sonnet-4-6';

    private const MAX_TENTATIVAS = 2;

    // ~400 tokens/chunk; mantém o prompt abaixo de ~16k tokens
    private const MAX_CHUNKS_POR_CHAMADA = 30;

    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly GroundingValidator $validator,
    ) {}

    public function gerar(Escopo $escopo, int $quantidade = 5, string $dificuldade = 'medio'): Geracao
    {
        $chunks = $this->retrieval->forScope($escopo);

        if (empty($chunks)) {
            return $this->persistir([], $escopo, 'rejeitado', 0, 0);
        }

        $totalTokens = 0;
        $ultimasQuestoes = [];
        $regeneracoes = 0;

        for ($tentativa = 1; $tentativa <= self::MAX_TENTATIVAS; $tentativa++) {
            $response = $this->chamarLLM($chunks, $quantidade, $dificuldade);
            $totalTokens += $response->usage->promptTokens + $response->usage->completionTokens;
            $questoes = $response->structured['questoes'] ?? [];
            $ultimasQuestoes = $questoes;

            if ($tentativa > 1) {
                $regeneracoes++;
            }

            if ($this->todasAncoradas($questoes, $chunks)) {
                return $this->persistir($questoes, $escopo, 'ok', $totalTokens, $regeneracoes);
            }
        }

        return $this->persistir($ultimasQuestoes, $escopo, 'rejeitado', $totalTokens, $regeneracoes);
    }

    private function chamarLLM(array $chunks, int $quantidade, string $dificuldade): StructuredResponse
    {
        return Prism::structured()
            ->using('anthropic', self::MODELO)
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->userPrompt($this->amostrarChunks($chunks), $quantidade, $dificuldade))
            ->withSchema($this->buildSchema())
            ->asStructured();
    }

    /**
     * Amostra chunks distribuídos entre páginas para não explodir o contexto.
     * Mantém diversidade de conteúdo ao escolher chunks de páginas diferentes.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, array<string, mixed>>
     */
    private function amostrarChunks(array $chunks): array
    {
        if (count($chunks) <= self::MAX_CHUNKS_POR_CHAMADA) {
            return $chunks;
        }

        $porPagina = [];
        foreach ($chunks as $chunk) {
            $porPagina[$chunk['pagina_id']][] = $chunk;
        }

        $paginas = array_keys($porPagina);
        $resultado = [];
        $i = 0;

        while (count($resultado) < self::MAX_CHUNKS_POR_CHAMADA) {
            $pagina = $paginas[$i % count($paginas)];
            if (! empty($porPagina[$pagina])) {
                $resultado[] = array_shift($porPagina[$pagina]);
            }
            $i++;

            if (array_sum(array_map('count', $porPagina)) === 0) {
                break;
            }
        }

        return $resultado;
    }

    private function todasAncoradas(array $questoes, array $chunks): bool
    {
        if (empty($questoes)) {
            return false;
        }

        foreach ($questoes as $questao) {
            $texto = trim(
                ($questao['contexto'] ?? '').' '.
                ($questao['enunciado'] ?? '').' '.
                ($questao['alternativas'][$questao['correta'] ?? ''] ?? '')
            );

            $fontes = array_values(array_map(
                fn ($f) => array_filter([
                    'pagina_id' => (int) ($f['pagina_id'] ?? 0),
                    'chunk_id' => isset($f['chunk_id']) ? (int) $f['chunk_id'] : null,
                ], fn ($v) => $v !== null),
                $questao['fontes'] ?? []
            ));

            if (! $this->validator->validate(['texto' => $texto, 'fontes' => $fontes], $chunks)->aprovado) {
                return false;
            }
        }

        return true;
    }

    private function persistir(
        array $questoes,
        Escopo $escopo,
        string $status,
        int $tokens,
        int $regeneracoes,
    ): Geracao {
        return DB::transaction(function () use ($questoes, $escopo, $status, $tokens, $regeneracoes) {
            $geracao = Geracao::create([
                'tipo' => 'simulado',
                'escopo' => [
                    'disciplina' => $escopo->disciplina,
                    'tags' => $escopo->tags,
                    'paginas' => $escopo->paginas,
                ],
                'status' => $status,
                'payload' => ['questoes' => $questoes],
                'custo_tokens' => $tokens,
                'modelo' => self::MODELO,
                'regeneracoes' => $regeneracoes,
            ]);

            if ($status === 'ok') {
                $paginaIds = collect($questoes)
                    ->flatMap(fn ($q) => $q['fontes'] ?? [])
                    ->map(fn ($f) => (int) ($f['pagina_id'] ?? 0))
                    ->filter()
                    ->unique();

                foreach ($paginaIds as $paginaId) {
                    GeracaoFonte::firstOrCreate([
                        'geracao_id' => $geracao->id,
                        'pagina_id' => $paginaId,
                    ]);
                }
            }

            return $geracao;
        });
    }

    private function systemPrompt(): string
    {
        return 'Você é um gerador de simulados de prova. Crie questões de múltipla escolha ancoradas EXCLUSIVAMENTE no conteúdo entre tags [CHUNK]. Não invente fatos ausentes nos chunks. Se o conteúdo for insuficiente, gere menos questões. Cada questão DEVE referenciar os chunk_id e pagina_id que a fundamentam.';
    }

    private function userPrompt(array $chunks, int $quantidade, string $dificuldade): string
    {
        $contexto = collect($chunks)
            ->map(fn ($c) => "[CHUNK pagina_id={$c['pagina_id']} chunk_id={$c['chunk_id']}]\n{$c['conteudo']}\n[/CHUNK]")
            ->implode("\n\n");

        return "Gere {$quantidade} questões de nível {$dificuldade} baseadas EXCLUSIVAMENTE no seguinte conteúdo:\n\n{$contexto}\n\nRegras: parágrafo de contexto antes de cada questão; 5 alternativas (a-e), uma correta; distratores plausíveis; misture formatos direto e I/II/III; liste pagina_id e chunk_id das fontes; comente cada alternativa no gabarito.";
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

        $questaoSchema = new ObjectSchema(
            name: 'questao',
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

        return new ObjectSchema(
            name: 'simulado',
            description: 'Simulado com questões de múltipla escolha',
            properties: [
                new ArraySchema('questoes', 'Lista de questões geradas', $questaoSchema),
            ],
            requiredFields: ['questoes'],
        );
    }
}
