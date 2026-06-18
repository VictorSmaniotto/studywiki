<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Services\AI\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Embeddings\Response as EmbeddingResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function fakeEmbeddingResponse(int $count, int $dim = 1024): EmbeddingResponse
{
    $vector = array_fill(0, $dim, 0.01);
    $embeddings = array_map(fn () => Embedding::fromArray($vector), range(0, $count - 1));

    return new EmbeddingResponse(
        embeddings: $embeddings,
        usage: new EmbeddingsUsage(10 * $count),
        meta: new Meta('fake-id', EmbeddingService::MODEL),
    );
}

function makeChunkWithoutEmbedding(): Chunk
{
    $disciplina = Disciplina::factory()->create();
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);

    return Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'embedding' => null,
        'embedding_model' => null,
    ]);
}

function makeChunkWithEmbedding(): Chunk
{
    $disciplina = Disciplina::factory()->create();
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $vector = '['.implode(',', array_fill(0, 1024, 0.01)).']';

    return Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'embedding' => $vector,
        'embedding_model' => EmbeddingService::MODEL,
    ]);
}

// ─── EmbeddingService::embedBatch ─────────────────────────────────────────

it('embeds a batch and persists embedding + model', function (): void {
    $chunk = makeChunkWithoutEmbedding();
    $fake = Prism::fake([fakeEmbeddingResponse(1)]);

    $service = new EmbeddingService;
    $count = $service->embedBatch(collect([$chunk]));

    expect($count)->toBe(1);

    $chunk->refresh();
    expect($chunk->embedding_model)->toBe(EmbeddingService::MODEL)
        ->and($chunk->embedding)->not->toBeNull();

    $fake->assertCallCount(1);
});

it('returns 0 and makes no API call for empty batch', function (): void {
    $fake = Prism::fake([]);

    $service = new EmbeddingService;
    $count = $service->embedBatch(collect());

    expect($count)->toBe(0);
    $fake->assertCallCount(0);
});

it('embeds multiple chunks in a single batch call', function (): void {
    $chunks = collect([makeChunkWithoutEmbedding(), makeChunkWithoutEmbedding(), makeChunkWithoutEmbedding()]);
    $fake = Prism::fake([fakeEmbeddingResponse(3)]);

    $service = new EmbeddingService;
    $count = $service->embedBatch($chunks);

    expect($count)->toBe(3);
    $fake->assertCallCount(1);

    $chunks->each(fn (Chunk $c) => expect($c->fresh()->embedding_model)->toBe(EmbeddingService::MODEL));
});

// ─── EmbeddingService::pendingQuery ────────────────────────────────────────

it('pendingQuery returns only chunks without embedding_model', function (): void {
    makeChunkWithEmbedding();
    $pending = makeChunkWithoutEmbedding();

    $service = new EmbeddingService;
    $ids = $service->pendingQuery()->pluck('id');

    expect($ids)->toContain($pending->id)
        ->and($ids)->toHaveCount(1);
});

// ─── studywiki:embed command ───────────────────────────────────────────────

it('command reports no pending chunks when all are already embedded', function (): void {
    makeChunkWithEmbedding();
    Prism::fake([]);

    $this->artisan('studywiki:embed')
        ->expectsOutput('Nenhum chunk pendente de embedding.')
        ->assertSuccessful();
});

it('command embeds pending chunks and reports count', function (): void {
    makeChunkWithoutEmbedding();
    makeChunkWithoutEmbedding();
    Prism::fake([fakeEmbeddingResponse(2)]);

    $this->artisan('studywiki:embed')
        ->assertSuccessful()
        ->expectsOutputToContain('2 chunks embedados');
});

// ─── AC: idempotência ─────────────────────────────────────────────────────

it('re-running the command does NOT re-embed already embedded chunks (AC)', function (): void {
    $chunk = makeChunkWithoutEmbedding();
    $fake = Prism::fake([fakeEmbeddingResponse(1), fakeEmbeddingResponse(1)]);

    // Primeira execução: embeda
    $this->artisan('studywiki:embed')->assertSuccessful();
    $chunk->refresh();
    expect($chunk->embedding_model)->toBe(EmbeddingService::MODEL);

    // Segunda execução: não deve re-embedar
    $this->artisan('studywiki:embed')
        ->expectsOutput('Nenhum chunk pendente de embedding.')
        ->assertSuccessful();

    // Somente 1 chamada à API ao total
    $fake->assertCallCount(1);
});

it('--force flag re-embeds already embedded chunks', function (): void {
    $chunk = makeChunkWithEmbedding();
    Prism::fake([fakeEmbeddingResponse(1)]);

    $this->artisan('studywiki:embed --force')->assertSuccessful()
        ->expectsOutputToContain('1 chunks embedados');

    $chunk->refresh();
    expect($chunk->embedding_model)->toBe(EmbeddingService::MODEL);
});
