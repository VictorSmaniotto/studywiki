<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Services\AI\SimuladoGenerator;
use App\Services\Retrieval\Escopo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function fakeStructuredResponse(array $structured, int $inputTokens = 100, int $outputTokens = 200): StructuredResponse
{
    return new StructuredResponse(
        steps: new Collection([]),
        text: json_encode($structured),
        structured: $structured,
        finishReason: FinishReason::Stop,
        usage: new Usage($inputTokens, $outputTokens),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );
}

function questaoAncorada(int $paginaId, int $chunkId, string $texto = 'análise léxica de compiladores'): array
{
    return [
        'contexto' => "Contexto sobre {$texto}.",
        'enunciado' => "O que é {$texto}?",
        'formato' => 'direto',
        'alternativas' => [
            'a' => "Definição correta de {$texto} compiladores analisam",
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
            'a' => 'Correto pois está no texto.',
            'b' => 'Incorreto.',
            'c' => 'Incorreto.',
            'd' => 'Incorreto.',
            'e' => 'Incorreto.',
        ],
    ];
}

function criarPaginaComChunk(string $disciplinaSlug = 'compiladores', string $conteudo = 'compiladores analisam código fonte léxico'): array
{
    $disciplina = Disciplina::factory()->create(['slug' => $disciplinaSlug]);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => $conteudo,
    ]);

    return ['disciplina' => $disciplina, 'pagina' => $pagina, 'chunk' => $chunk];
}

// ─── AC: schema válido e persiste como aprovado ───────────────────────────

it('gera simulado aprovado quando LLM retorna questão válida e ancorada', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarPaginaComChunk();

    $questao = questaoAncorada($pagina->id, $chunk->id);
    Prism::fake([fakeStructuredResponse(['questoes' => [$questao]])]);

    $escopo = new Escopo(disciplina: $disciplina->slug);
    $geracao = app(SimuladoGenerator::class)->gerar($escopo, quantidade: 1);

    expect($geracao)->toBeInstanceOf(Geracao::class)
        ->and($geracao->status)->toBe('ok')
        ->and($geracao->tipo)->toBe('simulado')
        ->and($geracao->modelo)->toBe('claude-sonnet-4-6')
        ->and($geracao->payload['questoes'])->toHaveCount(1)
        ->and($geracao->custo_tokens)->toBe(300);
});

it('toda questão no payload tem campo fontes', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarPaginaComChunk();

    $questoes = [
        questaoAncorada($pagina->id, $chunk->id, 'análise léxica de compiladores'),
        questaoAncorada($pagina->id, $chunk->id, 'análise léxica de compiladores código'),
    ];
    Prism::fake([fakeStructuredResponse(['questoes' => $questoes])]);

    $escopo = new Escopo(disciplina: $disciplina->slug);
    $geracao = app(SimuladoGenerator::class)->gerar($escopo, quantidade: 2);

    expect($geracao->status)->toBe('ok');
    foreach ($geracao->payload['questoes'] as $q) {
        expect($q['fontes'])->not->toBeEmpty();
    }
});

it('cria registros GeracaoFonte para cada pagina_id único das fontes', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarPaginaComChunk();

    $questao = questaoAncorada($pagina->id, $chunk->id);
    Prism::fake([fakeStructuredResponse(['questoes' => [$questao]])]);

    $geracao = app(SimuladoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect(GeracaoFonte::where('geracao_id', $geracao->id)->count())->toBe(1)
        ->and(GeracaoFonte::where('geracao_id', $geracao->id)->first()->pagina_id)->toBe($pagina->id);
});

// ─── AC: status=rejeitado quando não ancora ───────────────────────────────

it('persiste como rejeitado quando questão não tem fontes', function () {
    ['disciplina' => $disciplina] = criarPaginaComChunk();

    $questaoSemFontes = questaoAncorada(99, 99);
    $questaoSemFontes['fontes'] = [];
    Prism::fake([
        fakeStructuredResponse(['questoes' => [$questaoSemFontes]]),
        fakeStructuredResponse(['questoes' => [$questaoSemFontes]]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

it('persiste como rejeitado quando questão referencia fonte fantasma', function () {
    ['disciplina' => $disciplina] = criarPaginaComChunk();

    $questaoComFantasma = questaoAncorada(9999, 9999);
    Prism::fake([
        fakeStructuredResponse(['questoes' => [$questaoComFantasma]]),
        fakeStructuredResponse(['questoes' => [$questaoComFantasma]]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

it('persiste como rejeitado quando LLM retorna questoes vazio', function () {
    ['disciplina' => $disciplina] = criarPaginaComChunk();

    Prism::fake([
        fakeStructuredResponse(['questoes' => []]),
        fakeStructuredResponse(['questoes' => []]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC: regenera até máx 2 tentativas ────────────────────────────────────

it('chama LLM duas vezes e rejeita quando ambas falham', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarPaginaComChunk();

    $questaoFantasma = questaoAncorada(9999, 9999);

    $fake = Prism::fake([
        fakeStructuredResponse(['questoes' => [$questaoFantasma]]),
        fakeStructuredResponse(['questoes' => [$questaoFantasma]]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado')
        ->and($geracao->regeneracoes)->toBe(1);

    $fake->assertCallCount(2);
});

it('aprova na segunda tentativa e registra regeneracao', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarPaginaComChunk();

    $questaoFantasma = questaoAncorada(9999, 9999);
    $questaoValida = questaoAncorada($pagina->id, $chunk->id);

    $fake = Prism::fake([
        fakeStructuredResponse(['questoes' => [$questaoFantasma]]),
        fakeStructuredResponse(['questoes' => [$questaoValida]]),
    ]);

    $geracao = app(SimuladoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('ok')
        ->and($geracao->regeneracoes)->toBe(1);

    $fake->assertCallCount(2);
});

// ─── AC: sem chunks no escopo → rejeita sem chamar LLM ───────────────────

it('rejeita sem chamar LLM quando escopo nao tem chunks', function () {
    $fake = Prism::fake([]);

    $escopo = new Escopo(disciplina: 'disciplina-inexistente');
    $geracao = app(SimuladoGenerator::class)->gerar($escopo, 1);

    expect($geracao->status)->toBe('rejeitado')
        ->and($geracao->custo_tokens)->toBe(0);

    $fake->assertCallCount(0);
});
