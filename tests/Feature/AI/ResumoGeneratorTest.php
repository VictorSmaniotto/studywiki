<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Services\AI\ResumoGenerator;
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

function fakeResumoResponse(array $structured, int $inputTokens = 100, int $outputTokens = 200): StructuredResponse
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

/**
 * Cria um resumo com bullets ancorados. O conteúdo do chunk é intencionalmente
 * longo para garantir que AC-R3 (resumo < chunks) passe nos testes.
 */
function resumoAncorado(int $paginaId, int $chunkId, string $termo = 'compiladores analisam'): array
{
    return [
        'titulo' => "Resumo sobre {$termo}",
        'secoes' => [
            [
                'heading' => 'Conceitos',
                'bullets' => [
                    [
                        'texto' => 'Compiladores analisam código fonte léxico.',
                        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
                    ],
                ],
            ],
        ],
        'fontes_globais' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
    ];
}

function criarContextoResumo(
    string $disciplinaSlug = 'compiladores',
    string $conteudo = 'compiladores analisam código fonte léxico tokens gramática sintaxe semântica geração'
): array {
    $disciplina = Disciplina::factory()->create(['slug' => $disciplinaSlug]);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'conteudo' => $conteudo]);

    return compact('disciplina', 'pagina', 'chunk');
}

// ─── AC-R1: cada bullet cita fonte ────────────────────────────────────────

it('gera resumo aprovado quando bullets têm fontes válidas', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoResumo();

    $resumo = resumoAncorado($pagina->id, $chunk->id);
    Prism::fake([fakeResumoResponse($resumo)]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao)->toBeInstanceOf(Geracao::class)
        ->and($geracao->status)->toBe('ok')
        ->and($geracao->tipo)->toBe('resumo')
        ->and($geracao->modelo)->toBe('claude-sonnet-4-6')
        ->and($geracao->custo_tokens)->toBe(300);
});

it('persiste como rejeitado quando bullet não tem fontes', function () {
    ['disciplina' => $disciplina] = criarContextoResumo();

    $resumoSemFontes = [
        'titulo' => 'Resumo',
        'secoes' => [
            [
                'heading' => 'Seção',
                'bullets' => [
                    ['texto' => 'bullet sem fonte', 'fontes' => []],
                ],
            ],
        ],
        'fontes_globais' => [],
    ];

    Prism::fake([
        fakeResumoResponse($resumoSemFontes),
        fakeResumoResponse($resumoSemFontes),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('rejeitado');
});

it('persiste como rejeitado quando bullet referencia fonte fantasma', function () {
    ['disciplina' => $disciplina] = criarContextoResumo();

    $resumoComFantasma = resumoAncorado(9999, 9999);

    Prism::fake([
        fakeResumoResponse($resumoComFantasma),
        fakeResumoResponse($resumoComFantasma),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC-R2: não introduz conceito ausente nos chunks ──────────────────────

it('persiste como rejeitado quando bullet tem overlap insuficiente com chunks', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoResumo(
        conteudo: 'compiladores analisam código fonte léxico tokens gramática sintaxe semântica geração'
    );

    $resumoComConteudoInventado = [
        'titulo' => 'Resumo',
        'secoes' => [
            [
                'heading' => 'Seção',
                'bullets' => [
                    [
                        'texto' => 'fotossíntese clorofila absorção luz solar plantas verdes',
                        'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
                    ],
                ],
            ],
        ],
        'fontes_globais' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
    ];

    Prism::fake([
        fakeResumoResponse($resumoComConteudoInventado),
        fakeResumoResponse($resumoComConteudoInventado),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC-R3: resumo mais curto que a soma dos chunks ───────────────────────

it('persiste como rejeitado quando resumo é maior que os chunks', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoResumo(
        conteudo: 'compiladores analisam'
    );

    $textoLongo = str_repeat('compiladores analisam ', 50);
    $resumoMuitoLongo = [
        'titulo' => 'Resumo',
        'secoes' => [
            [
                'heading' => 'Seção',
                'bullets' => [
                    [
                        'texto' => $textoLongo,
                        'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
                    ],
                ],
            ],
        ],
        'fontes_globais' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
    ];

    Prism::fake([
        fakeResumoResponse($resumoMuitoLongo),
        fakeResumoResponse($resumoMuitoLongo),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('rejeitado');
});

// ─── Pipeline: regeneração ─────────────────────────────────────────────────

it('chama LLM duas vezes e rejeita quando ambas falham', function () {
    ['disciplina' => $disciplina] = criarContextoResumo();

    $resumoComFantasma = resumoAncorado(9999, 9999);

    $fake = Prism::fake([
        fakeResumoResponse($resumoComFantasma),
        fakeResumoResponse($resumoComFantasma),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('rejeitado')
        ->and($geracao->regeneracoes)->toBe(1);

    $fake->assertCallCount(2);
});

it('aprova na segunda tentativa e registra regeneracao', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoResumo();

    $resumoComFantasma = resumoAncorado(9999, 9999);
    $resumoValido = resumoAncorado($pagina->id, $chunk->id);

    $fake = Prism::fake([
        fakeResumoResponse($resumoComFantasma),
        fakeResumoResponse($resumoValido),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('ok')
        ->and($geracao->regeneracoes)->toBe(1);

    $fake->assertCallCount(2);
});

// ─── Sem chunks → rejeita sem chamar LLM ─────────────────────────────────

it('rejeita sem chamar LLM quando escopo não tem chunks', function () {
    $fake = Prism::fake([]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: 'disciplina-inexistente'));

    expect($geracao->status)->toBe('rejeitado')
        ->and($geracao->custo_tokens)->toBe(0);

    $fake->assertCallCount(0);
});

// ─── Estrutura e persistência ─────────────────────────────────────────────

it('payload contém titulo e secoes', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoResumo();

    Prism::fake([fakeResumoResponse(resumoAncorado($pagina->id, $chunk->id))]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->tipo)->toBe('resumo')
        ->and($geracao->payload)->toHaveKey('titulo')
        ->and($geracao->payload)->toHaveKey('secoes')
        ->and($geracao->payload['secoes'])->not->toBeEmpty();
});

it('cria GeracaoFonte para cada pagina_id único dos bullets', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoResumo();

    Prism::fake([fakeResumoResponse(resumoAncorado($pagina->id, $chunk->id))]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect(GeracaoFonte::where('geracao_id', $geracao->id)->count())->toBe(1)
        ->and(GeracaoFonte::where('geracao_id', $geracao->id)->first()->pagina_id)->toBe($pagina->id);
});

it('não cria GeracaoFonte quando status é rejeitado', function () {
    ['disciplina' => $disciplina] = criarContextoResumo();

    $resumoComFantasma = resumoAncorado(9999, 9999);
    Prism::fake([
        fakeResumoResponse($resumoComFantasma),
        fakeResumoResponse($resumoComFantasma),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect(GeracaoFonte::where('geracao_id', $geracao->id)->count())->toBe(0);
});

it('persiste como rejeitado quando secoes está vazio', function () {
    ['disciplina' => $disciplina] = criarContextoResumo();

    $resumoVazio = [
        'titulo' => 'Sem conteúdo',
        'secoes' => [],
        'fontes_globais' => [],
    ];

    Prism::fake([
        fakeResumoResponse($resumoVazio),
        fakeResumoResponse($resumoVazio),
    ]);

    $geracao = app(ResumoGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug));

    expect($geracao->status)->toBe('rejeitado');
});
