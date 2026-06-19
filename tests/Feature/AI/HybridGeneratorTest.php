<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Services\AI\EmbeddingService;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Prism\Prism\Embeddings\Response as EmbeddingResponse;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function hybridEmbedResponse(array $vector): EmbeddingResponse
{
    return new EmbeddingResponse(
        embeddings: [Embedding::fromArray($vector)],
        usage: new EmbeddingsUsage(5),
        meta: new Meta('fake', EmbeddingService::MODEL),
    );
}

function hybridStructuredResponse(array $structured): StructuredResponse
{
    return new StructuredResponse(
        steps: new Collection([]),
        text: json_encode($structured),
        structured: $structured,
        finishReason: FinishReason::Stop,
        usage: new Usage(100, 200),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );
}

/** Cria pagina + chunk COM embedding (1024d) para testes híbridos. */
function paginaComChunkEmbedado(string $disciplinaSlug = 'compiladores', string $conteudo = 'compiladores analisam codigo fonte lexico tokens'): array
{
    $disciplina = Disciplina::factory()->create(['slug' => $disciplinaSlug]);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $vector = array_merge([1.0], array_fill(0, 1023, 0.0));
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => $conteudo,
        'embedding' => '['.implode(',', $vector).']',
        'embedding_model' => EmbeddingService::MODEL,
    ]);

    return ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk, 'vector' => $vector];
}

/** Questão válida ancorada nos ids reais. */
function questaoHybrid(int $paginaId, int $chunkId, string $conteudo = 'compiladores analisam codigo fonte lexico tokens'): array
{
    return [
        'contexto' => "Contexto sobre {$conteudo}.",
        'enunciado' => 'O que analisam os compiladores no codigo fonte lexico?',
        'formato' => 'direto',
        'alternativas' => [
            'a' => 'Compiladores analisam tokens do codigo fonte lexico',
            'b' => 'Alternativa errada B',
            'c' => 'Alternativa errada C',
            'd' => 'Alternativa errada D',
            'e' => 'Alternativa errada E',
        ],
        'correta' => 'a',
        'fontes' => [
            ['pagina_id' => $paginaId, 'chunk_id' => $chunkId],
        ],
        'comentario_gabarito' => [
            'a' => 'Correto conforme o texto.',
            'b' => 'Incorreto.',
            'c' => 'Incorreto.',
            'd' => 'Incorreto.',
            'e' => 'Incorreto.',
        ],
    ];
}

// ─── modo híbrido vs estruturado ──────────────────────────────────────────

it('modo hibrido: usa forQuery (chama embedding API) quando escopo tem query', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'vector' => $vector] = paginaComChunkEmbedado();

    $questao = questaoHybrid($pagina->id, $chunk->id);
    $fake = Prism::fake([
        hybridEmbedResponse($vector),                              // 1) embedQuery
        hybridStructuredResponse(['questoes_me' => [$questao], 'questoes_dis' => []]),     // 2) LLM
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(query: 'compiladores lexico analise'),
        1
    );

    expect($geracao->status)->toBe('ok');
    $fake->assertCallCount(2);
});

it('modo estruturado: NAO chama embedding quando escopo sem query', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = paginaComChunkEmbedado();

    $questao = questaoHybrid($pagina->id, $chunk->id);
    $fake = Prism::fake([
        hybridStructuredResponse(['questoes_me' => [$questao], 'questoes_dis' => []]),     // somente LLM
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug),
        1
    );

    expect($geracao->status)->toBe('ok');
    $fake->assertCallCount(1);
});

it('query e salva no campo escopo JSON da geracao', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'vector' => $vector] = paginaComChunkEmbedado();

    Prism::fake([
        hybridEmbedResponse($vector),
        hybridStructuredResponse(['questoes_me' => [questaoHybrid($pagina->id, $chunk->id)], 'questoes_dis' => []]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(query: 'compiladores lexico'),
        1
    );

    expect($geracao->escopo['query'])->toBe('compiladores lexico');
});

// ─── AC-G1 em modo híbrido ────────────────────────────────────────────────

it('AC-G1 hibrido: questao sem fontes e rejeitada mesmo via forQuery', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'vector' => $vector] = paginaComChunkEmbedado();

    $questaoSemFonte = questaoHybrid($pagina->id, $chunk->id);
    $questaoSemFonte['fontes'] = [];

    Prism::fake([
        hybridEmbedResponse($vector),
        hybridStructuredResponse(['questoes_me' => [$questaoSemFonte], 'questoes_dis' => []]),   // tentativa 1
        hybridStructuredResponse(['questoes_me' => [$questaoSemFonte], 'questoes_dis' => []]),   // tentativa 2 (regeneração)
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(query: 'compiladores'),
        1
    );

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC-G2 em modo híbrido ────────────────────────────────────────────────

it('AC-G2 hibrido: fonte fantasma (pagina_id inexistente) e rejeitada', function (): void {
    ['vector' => $vector] = paginaComChunkEmbedado();

    $questaoFonteFake = [
        'contexto' => 'Contexto.',
        'enunciado' => 'Questao teste?',
        'formato' => 'direto',
        'alternativas' => ['a' => 'Resp A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
        'correta' => 'a',
        'fontes' => [['pagina_id' => 9999, 'chunk_id' => 9999]],
        'comentario_gabarito' => ['a' => 'ok', 'b' => 'x', 'c' => 'x', 'd' => 'x', 'e' => 'x'],
    ];

    Prism::fake([
        hybridEmbedResponse($vector),
        hybridStructuredResponse(['questoes_me' => [$questaoFonteFake], 'questoes_dis' => []]),
        hybridStructuredResponse(['questoes_me' => [$questaoFonteFake], 'questoes_dis' => []]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(query: 'compiladores'),
        1
    );

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC-G3 em modo híbrido ────────────────────────────────────────────────

it('AC-G3 hibrido: overlap lexico insuficiente e rejeitado', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'vector' => $vector] = paginaComChunkEmbedado();

    $questaoSemOverlap = questaoHybrid($pagina->id, $chunk->id);
    // Sobrescreve TODOS os campos de texto para eliminar qualquer overlap com o chunk
    $questaoSemOverlap['contexto'] = 'xyzzy quux frobnicator context baz';
    $questaoSemOverlap['enunciado'] = 'xyzzy quux frobnicator baz?';
    $questaoSemOverlap['alternativas']['a'] = 'xyzzy frobnicator quux baz qux';

    Prism::fake([
        hybridEmbedResponse($vector),
        hybridStructuredResponse(['questoes_me' => [$questaoSemOverlap], 'questoes_dis' => []]),
        hybridStructuredResponse(['questoes_me' => [$questaoSemOverlap], 'questoes_dis' => []]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(
        new Escopo(query: 'compiladores'),
        1
    );

    expect($geracao->status)->toBe('rejeitado');
});
