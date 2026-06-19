<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Flashcard;
use App\Models\Pagina;
use App\Services\AI\FlashcardsGenerator;
use App\Services\Retrieval\Escopo;
use App\Services\SpacedRepetitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function srs_flashcardsResponse(array $cards): StructuredResponse
{
    return new StructuredResponse(
        steps: new Collection([]),
        text: json_encode(['cards' => $cards]),
        structured: ['cards' => $cards],
        finishReason: FinishReason::Stop,
        usage: new Usage(100, 200),
        meta: new Meta('anthropic', 'claude-sonnet-4-6'),
        additionalContent: [],
    );
}

function srs_card(int $paginaId, int $chunkId, string $frente = 'Conceito de compiladores léxico'): array
{
    return [
        'frente' => $frente,
        'verso' => 'Compiladores analisam tokens do código fonte léxico',
        'fontes' => [['pagina_id' => $paginaId, 'chunk_id' => $chunkId]],
    ];
}

function srs_contexto(): array
{
    $disciplina = Disciplina::factory()->create(['slug' => 'compiladores-srs']);
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => 'compiladores analisam tokens código fonte léxico',
    ]);

    return compact('disciplina', 'pagina', 'chunk');
}

function srs_novo(array $overrides = []): Flashcard
{
    return Flashcard::factory()->create(array_merge([
        'proxima_revisao' => Carbon::today()->toDateString(),
        'intervalo' => 1,
        'facilidade' => 2.50,
        'repeticoes' => 0,
    ], $overrides));
}

// ─── FlashcardsGenerator cria registros Flashcard ─────────────────────────

it('gerar ok cria registros Flashcard no banco', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = srs_contexto();

    $card = srs_card($pagina->id, $chunk->id);
    Prism::fake([srs_flashcardsResponse([$card])]);

    $geracao = app(FlashcardsGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug),
        1
    );

    expect($geracao->status)->toBe('ok');
    expect(Flashcard::where('geracao_id', $geracao->id)->count())->toBe(1);

    $flashcard = Flashcard::where('geracao_id', $geracao->id)->first();
    expect($flashcard->frente)->toBe($card['frente'])
        ->and($flashcard->verso)->toBe($card['verso'])
        ->and($flashcard->proxima_revisao->isToday())->toBeTrue()
        ->and($flashcard->intervalo)->toBe(1)
        ->and($flashcard->repeticoes)->toBe(0);
});

it('gerar rejeitado NAO cria registros Flashcard', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = srs_contexto();

    $cardSemFonte = srs_card($pagina->id, $chunk->id);
    $cardSemFonte['fontes'] = [];

    Prism::fake([
        srs_flashcardsResponse([$cardSemFonte]),
        srs_flashcardsResponse([$cardSemFonte]),
    ]);

    $geracao = app(FlashcardsGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug),
        1
    );

    expect($geracao->status)->toBe('rejeitado');
    expect(Flashcard::where('geracao_id', $geracao->id)->count())->toBe(0);
});

it('gerar ok com multiplos cards cria um Flashcard por card', function (): void {
    ['pagina' => $pagina, 'chunk' => $chunk, 'disciplina' => $disciplina] = srs_contexto();

    $cards = [
        srs_card($pagina->id, $chunk->id, 'Conceito A de compiladores léxico'),
        srs_card($pagina->id, $chunk->id, 'Conceito B de compiladores tokens código'),
    ];
    Prism::fake([srs_flashcardsResponse($cards)]);

    $geracao = app(FlashcardsGenerator::class)->gerar(
        new Escopo(disciplina: $disciplina->slug),
        2
    );

    expect($geracao->status)->toBe('ok');
    expect(Flashcard::where('geracao_id', $geracao->id)->count())->toBe(2);
});

// ─── Flashcard::devePraticar ───────────────────────────────────────────────

it('devePraticar retorna true quando proxima_revisao e hoje', function (): void {
    $card = srs_novo(['proxima_revisao' => Carbon::today()->toDateString()]);
    expect($card->devePraticar())->toBeTrue();
});

it('devePraticar retorna true quando proxima_revisao e no passado', function (): void {
    $card = srs_novo(['proxima_revisao' => Carbon::yesterday()->toDateString()]);
    expect($card->devePraticar())->toBeTrue();
});

it('devePraticar retorna false quando proxima_revisao e no futuro', function (): void {
    $card = srs_novo(['proxima_revisao' => Carbon::tomorrow()->toDateString()]);
    expect($card->devePraticar())->toBeFalse();
});

// ─── SpacedRepetitionService::revisar — lembrei ───────────────────────────

it('primeira revisao lembrei: repeticoes=1 intervalo=1 proxima_revisao=amanha', function (): void {
    $card = srs_novo(['repeticoes' => 0, 'intervalo' => 1]);
    $service = new SpacedRepetitionService;

    $service->revisar($card, lembrei: true);

    expect($card->fresh()->repeticoes)->toBe(1)
        ->and($card->fresh()->intervalo)->toBe(1)
        ->and($card->fresh()->proxima_revisao->isNextDay())->toBeTrue();
});

it('segunda revisao lembrei: intervalo=6 proxima_revisao=hoje+6', function (): void {
    $card = srs_novo(['repeticoes' => 1, 'intervalo' => 1]);
    $service = new SpacedRepetitionService;

    $service->revisar($card, lembrei: true);

    expect($card->fresh()->repeticoes)->toBe(2)
        ->and($card->fresh()->intervalo)->toBe(6)
        ->and($card->fresh()->proxima_revisao->eq(Carbon::today()->addDays(6)))->toBeTrue();
});

it('terceira revisao lembrei: intervalo=round(intervalo_anterior*facilidade)', function (): void {
    $card = srs_novo(['repeticoes' => 2, 'intervalo' => 6, 'facilidade' => 2.50]);
    $service = new SpacedRepetitionService;

    $service->revisar($card, lembrei: true);

    $esperado = (int) round(6 * 2.50); // 15
    expect($card->fresh()->repeticoes)->toBe(3)
        ->and($card->fresh()->intervalo)->toBe($esperado)
        ->and($card->fresh()->proxima_revisao->eq(Carbon::today()->addDays($esperado)))->toBeTrue();
});

it('intervalo cresce corretamente em revisoes subsequentes', function (): void {
    $card = srs_novo(['repeticoes' => 3, 'intervalo' => 15, 'facilidade' => 2.50]);
    $service = new SpacedRepetitionService;

    $service->revisar($card, lembrei: true);

    $esperado = (int) round(15 * 2.50); // 38
    expect($card->fresh()->intervalo)->toBe($esperado);
});

// ─── SpacedRepetitionService::revisar — esqueci ───────────────────────────

it('esqueci reseta repeticoes e intervalo, penaliza facilidade', function (): void {
    $card = srs_novo(['repeticoes' => 5, 'intervalo' => 30, 'facilidade' => 2.50]);
    $service = new SpacedRepetitionService;

    $service->revisar($card, lembrei: false);

    expect($card->fresh()->repeticoes)->toBe(0)
        ->and($card->fresh()->intervalo)->toBe(1)
        ->and((float) $card->fresh()->facilidade)->toBe(2.30)
        ->and($card->fresh()->proxima_revisao->isNextDay())->toBeTrue();
});

it('facilidade nao cai abaixo de 1.3 com multiplos esqueci', function (): void {
    $card = srs_novo(['facilidade' => 1.30]);
    $service = new SpacedRepetitionService;

    $service->revisar($card, lembrei: false);

    expect((float) $card->fresh()->facilidade)->toBe(1.30);
});
