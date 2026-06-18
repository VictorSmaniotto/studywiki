<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Services\AI\FlashcardsGenerator;
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

function fakeFlashcardsResponse(array $structured, int $inputTokens = 100, int $outputTokens = 200): StructuredResponse
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

function cardAncorado(int $paginaId, int $chunkId, string $frente = 'O que são compiladores?'): array
{
    return [
        'frente' => $frente,
        'verso' => 'Compiladores analisam código fonte léxico e sintático.',
        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
        'disciplina' => 'Compiladores',
        'tags' => ['compiladores', 'léxico'],
    ];
}

function criarContextoFlashcards(
    string $disciplinaSlug = 'compiladores',
    string $conteudo = 'compiladores analisam código fonte léxico sintático semântica tokens gramática'
): array {
    $disciplina = Disciplina::factory()->create(['slug' => $disciplinaSlug]);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create(['pagina_id' => $pagina->id, 'conteudo' => $conteudo]);

    return compact('disciplina', 'pagina', 'chunk');
}

// ─── AC-F1: verso suportado pelo texto-fonte ──────────────────────────────

it('gera flashcards aprovados quando verso tem fontes válidas e ancoradas', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $cards = ['cards' => [cardAncorado($pagina->id, $chunk->id)]];
    Prism::fake([fakeFlashcardsResponse($cards)]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao)->toBeInstanceOf(Geracao::class)
        ->and($geracao->status)->toBe('ok')
        ->and($geracao->tipo)->toBe('flashcards')
        ->and($geracao->modelo)->toBe('claude-sonnet-4-6')
        ->and($geracao->custo_tokens)->toBe(300);
});

it('persiste como rejeitado quando verso não tem fontes', function () {
    ['disciplina' => $disciplina] = criarContextoFlashcards();

    $cardSemFontes = cardAncorado(1, 1);
    $cardSemFontes['fontes'] = [];

    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardSemFontes]]),
        fakeFlashcardsResponse(['cards' => [$cardSemFontes]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

it('persiste como rejeitado quando verso referencia fonte fantasma', function () {
    ['disciplina' => $disciplina] = criarContextoFlashcards();

    $cardFantasma = cardAncorado(9999, 9999);

    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

it('persiste como rejeitado quando verso tem overlap insuficiente com chunks', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $cardInventado = [
        'frente' => 'O que é fotossíntese?',
        'verso' => 'fotossíntese clorofila absorção luz solar plantas verdes',
        'fontes' => [['pagina_id' => $pagina->id, 'chunk_id' => $chunk->id]],
        'disciplina' => 'Compiladores',
        'tags' => [],
    ];

    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardInventado]]),
        fakeFlashcardsResponse(['cards' => [$cardInventado]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC-F2: frente e verso não-vazios ────────────────────────────────────

it('persiste como rejeitado quando frente está vazia', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $cardSemFrente = cardAncorado($pagina->id, $chunk->id);
    $cardSemFrente['frente'] = '';

    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardSemFrente]]),
        fakeFlashcardsResponse(['cards' => [$cardSemFrente]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

it('persiste como rejeitado quando verso está vazio', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $cardSemVerso = cardAncorado($pagina->id, $chunk->id);
    $cardSemVerso['verso'] = '';

    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardSemVerso]]),
        fakeFlashcardsResponse(['cards' => [$cardSemVerso]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado');
});

// ─── AC-F3: sem card duplicado ────────────────────────────────────────────

it('persiste como rejeitado quando há frentes duplicadas', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $card1 = cardAncorado($pagina->id, $chunk->id, 'O que são compiladores?');
    $card2 = cardAncorado($pagina->id, $chunk->id, 'O que são compiladores?');

    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$card1, $card2]]),
        fakeFlashcardsResponse(['cards' => [$card1, $card2]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 2);

    expect($geracao->status)->toBe('rejeitado');
});

it('aceita cards com frentes diferentes', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $card1 = cardAncorado($pagina->id, $chunk->id, 'O que são compiladores?');
    $card2 = cardAncorado($pagina->id, $chunk->id, 'O que é análise léxica de compiladores?');

    Prism::fake([fakeFlashcardsResponse(['cards' => [$card1, $card2]])]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 2);

    expect($geracao->status)->toBe('ok')
        ->and($geracao->payload['cards'])->toHaveCount(2);
});

// ─── Pipeline: regeneração ─────────────────────────────────────────────────

it('chama LLM duas vezes e rejeita quando ambas falham', function () {
    ['disciplina' => $disciplina] = criarContextoFlashcards();

    $cardFantasma = cardAncorado(9999, 9999);

    $fake = Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('rejeitado')
        ->and($geracao->regeneracoes)->toBe(1);

    $fake->assertCallCount(2);
});

it('aprova na segunda tentativa e registra regeneracao', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    $cardFantasma = cardAncorado(9999, 9999);
    $cardValido = cardAncorado($pagina->id, $chunk->id);

    $fake = Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
        fakeFlashcardsResponse(['cards' => [$cardValido]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->status)->toBe('ok')
        ->and($geracao->regeneracoes)->toBe(1);

    $fake->assertCallCount(2);
});

// ─── Sem chunks → rejeita sem chamar LLM ─────────────────────────────────

it('rejeita sem chamar LLM quando escopo não tem chunks', function () {
    $fake = Prism::fake([]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: 'disciplina-inexistente'), 5);

    expect($geracao->status)->toBe('rejeitado')
        ->and($geracao->custo_tokens)->toBe(0);

    $fake->assertCallCount(0);
});

// ─── Estrutura e persistência ─────────────────────────────────────────────

it('payload contém cards com frente e verso', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    Prism::fake([fakeFlashcardsResponse(['cards' => [cardAncorado($pagina->id, $chunk->id)]])]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect($geracao->tipo)->toBe('flashcards')
        ->and($geracao->payload['cards'])->toHaveCount(1)
        ->and($geracao->payload['cards'][0])->toHaveKey('frente')
        ->and($geracao->payload['cards'][0])->toHaveKey('verso');
});

it('cria GeracaoFonte para cada pagina_id único dos cards', function () {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = criarContextoFlashcards();

    Prism::fake([fakeFlashcardsResponse(['cards' => [cardAncorado($pagina->id, $chunk->id)]])]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect(GeracaoFonte::where('geracao_id', $geracao->id)->count())->toBe(1)
        ->and(GeracaoFonte::where('geracao_id', $geracao->id)->first()->pagina_id)->toBe($pagina->id);
});

it('não cria GeracaoFonte quando status é rejeitado', function () {
    ['disciplina' => $disciplina] = criarContextoFlashcards();

    $cardFantasma = cardAncorado(9999, 9999);
    Prism::fake([
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
        fakeFlashcardsResponse(['cards' => [$cardFantasma]]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 1);

    expect(GeracaoFonte::where('geracao_id', $geracao->id)->count())->toBe(0);
});

it('persiste como rejeitado quando cards está vazio', function () {
    ['disciplina' => $disciplina] = criarContextoFlashcards();

    Prism::fake([
        fakeFlashcardsResponse(['cards' => []]),
        fakeFlashcardsResponse(['cards' => []]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(new Escopo(disciplina: $disciplina->slug), 5);

    expect($geracao->status)->toBe('rejeitado');
});
