<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Services\AI\EmbeddingService;
use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Embeddings\Response as EmbeddingResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function vectorResponse(array $vector): EmbeddingResponse
{
    return new EmbeddingResponse(
        embeddings: [Embedding::fromArray($vector)],
        usage: new EmbeddingsUsage(5),
        meta: new Meta('fake', EmbeddingService::MODEL),
    );
}

function unitVector(int $position, int $dim = 1024): array
{
    $v = array_fill(0, $dim, 0.0);
    $v[$position] = 1.0;

    return $v;
}

function paginaEmDiscip(Disciplina $disciplina): Pagina
{
    return Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
}

function chunkEmbedado(Pagina $pagina, array $vector, string $conteudo = 'texto de teste'): Chunk
{
    return Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => $conteudo,
        'embedding' => '['.implode(',', $vector).']',
        'embedding_model' => EmbeddingService::MODEL,
    ]);
}

// ─── estrutura de retorno ─────────────────────────────────────────────────

it('forQuery retorna campos obrigatorios com score float > 0', function (): void {
    $discA = Disciplina::factory()->create();
    $pagina = paginaEmDiscip($discA);
    $v = unitVector(0);
    chunkEmbedado($pagina, $v);

    Prism::fake([vectorResponse($v)]);

    $results = app(RetrievalService::class)->forQuery('qualquer coisa', new Escopo);

    expect($results)->not->toBeEmpty();
    $first = $results[0];
    expect($first)->toHaveKeys(['chunk_id', 'pagina_id', 'heading_path', 'conteudo', 'tokens', 'titulo_pagina', 'path_relativo', 'score']);
    expect($first['score'])->toBeFloat()->toBeGreaterThan(0);
});

it('forQuery retorna vazio quando nao ha chunks embedados nem match FTS', function (): void {
    $disciplina = Disciplina::factory()->create();
    $pagina = paginaEmDiscip($disciplina);
    Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => 'zzz zzz zzz',
        'embedding' => null,
        'embedding_model' => null,
    ]);

    Prism::fake([vectorResponse(unitVector(0))]);

    $results = app(RetrievalService::class)->forQuery('busca sem match', new Escopo);

    expect($results)->toBeEmpty();
});

// ─── AC: cross-disciplina ─────────────────────────────────────────────────

it('AC: forQuery cross-disciplina recupera chunk relevante nao obvio por tag, fonte viaja junto', function (): void {
    $discA = Disciplina::factory()->create(['slug' => 'direito-civil']);
    $discB = Disciplina::factory()->create(['slug' => 'direito-penal']);

    $paginaA = paginaEmDiscip($discA);
    $paginaB = paginaEmDiscip($discB);

    $queryVec = unitVector(0);
    $otherVec = unitVector(1);

    // Chunk relevante fica em discB — "não-óbvio por tag": disciplina diferente, sem tags especiais
    $chunkRelevante = chunkEmbedado($paginaB, $queryVec, 'responsabilidade civil penal contratos');

    // Chunk irrelevante fica em discA com vetor ortogonal
    chunkEmbedado($paginaA, $otherVec, 'posse propriedade imóveis');

    Prism::fake([vectorResponse($queryVec)]);

    $results = app(RetrievalService::class)->forQuery('responsabilidade civil', new Escopo);

    $ids = collect($results)->pluck('chunk_id');
    expect($ids)->toContain($chunkRelevante->id);

    // Fonte viaja junto
    $hit = collect($results)->firstWhere('chunk_id', $chunkRelevante->id);
    expect($hit['titulo_pagina'])->not->toBeEmpty();
    expect($hit['path_relativo'])->not->toBeEmpty();
    expect($hit['score'])->toBeFloat()->toBeGreaterThan(0);
});

// ─── escopo respeita filtro de disciplina ─────────────────────────────────

it('forQuery respeita filtro de disciplina do escopo', function (): void {
    $discA = Disciplina::factory()->create(['slug' => 'disc-a-hybrid']);
    $discB = Disciplina::factory()->create(['slug' => 'disc-b-hybrid']);

    $v = array_fill(0, 1024, 0.5);

    $paginaA = paginaEmDiscip($discA);
    $paginaB = paginaEmDiscip($discB);

    chunkEmbedado($paginaA, $v, 'conteudo disciplina A');
    $chunkB = chunkEmbedado($paginaB, $v, 'conteudo disciplina B');

    Prism::fake([vectorResponse($v)]);

    $results = app(RetrievalService::class)->forQuery('teste', new Escopo(disciplina: 'disc-b-hybrid'));

    $ids = collect($results)->pluck('chunk_id');
    expect($ids)->toContain($chunkB->id)->and($ids)->toHaveCount(1);
});

// ─── FTS sozinho (sem embeddings) ─────────────────────────────────────────

it('forQuery retorna resultado via FTS quando chunk nao tem embedding', function (): void {
    $disciplina = Disciplina::factory()->create();
    $pagina = paginaEmDiscip($disciplina);

    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => 'polimorfismo herança encapsulamento',
        'embedding' => null,
        'embedding_model' => null,
    ]);

    Prism::fake([vectorResponse(unitVector(0))]);

    $results = app(RetrievalService::class)->forQuery('polimorfismo herança', new Escopo);

    $ids = collect($results)->pluck('chunk_id');
    expect($ids)->toContain($chunk->id);
});

// ─── RRF: chunk em ambas as listas tem score maior ────────────────────────

it('chunk presente em vetor e FTS tem score RRF maior que chunk so em um dos resultados', function (): void {
    $disciplina = Disciplina::factory()->create();
    $pagina = paginaEmDiscip($disciplina);

    $queryVec = unitVector(0);

    // Chunk que aparece em vetor E FTS
    $chunkAmbos = chunkEmbedado($pagina, $queryVec, 'polimorfismo orientado objetos');

    // Chunk que aparece so no vetor (vetor similar, texto sem match FTS)
    $chunkSoVetor = chunkEmbedado($pagina, unitVector(0), 'xyzzy frobnicator quux');

    Prism::fake([vectorResponse($queryVec)]);

    $results = app(RetrievalService::class)->forQuery('polimorfismo orientado', new Escopo);

    $scoreAmbos = collect($results)->firstWhere('chunk_id', $chunkAmbos->id)['score'] ?? 0;
    $scoreSoVetor = collect($results)->firstWhere('chunk_id', $chunkSoVetor->id)['score'] ?? 0;

    expect($scoreAmbos)->toBeGreaterThan($scoreSoVetor);
});

// ─── multi-disciplina ─────────────────────────────────────────────────────

it('forQuery respeita filtro de multiplas disciplinas no escopo', function (): void {
    $discA = Disciplina::factory()->create(['slug' => 'disc-multi-a']);
    $discB = Disciplina::factory()->create(['slug' => 'disc-multi-b']);
    $discC = Disciplina::factory()->create(['slug' => 'disc-multi-c']);

    $v = array_fill(0, 1024, 0.5);

    $chunkA = chunkEmbedado(paginaEmDiscip($discA), $v, 'conteudo disciplina A');
    $chunkB = chunkEmbedado(paginaEmDiscip($discB), $v, 'conteudo disciplina B');
    $chunkC = chunkEmbedado(paginaEmDiscip($discC), $v, 'conteudo disciplina C');

    Prism::fake([vectorResponse($v)]);

    $results = app(RetrievalService::class)->forQuery(
        'teste',
        new Escopo(disciplinas: ['disc-multi-a', 'disc-multi-b'])
    );

    $ids = collect($results)->pluck('chunk_id');
    expect($ids)->toContain($chunkA->id)
        ->and($ids)->toContain($chunkB->id)
        ->and($ids)->not->toContain($chunkC->id);
});

// ─── fallback por prefixo (sem embeddings, FTS falha em variação) ─────────

it('forQuery usa fallback por prefixo quando FTS nao encontra variacao de palavra', function (): void {
    $disciplina = Disciplina::factory()->create();
    $pagina = paginaEmDiscip($disciplina);

    // conteúdo tem "paradigma" (singular); query usa "paradigmas" (plural)
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => 'paradigma de programação orientado a objetos',
        'embedding' => null,
        'embedding_model' => null,
    ]);

    Prism::fake([vectorResponse(unitVector(0))]);

    $results = app(RetrievalService::class)->forQuery('paradigmas programação', new Escopo);

    expect(collect($results)->pluck('chunk_id'))->toContain($chunk->id);
});

it('forQuery fallback por prefixo respeita filtro de disciplina', function (): void {
    $discA = Disciplina::factory()->create(['slug' => 'disc-fallback-a']);
    $discB = Disciplina::factory()->create(['slug' => 'disc-fallback-b']);

    $chunkA = Chunk::factory()->create([
        'pagina_id' => paginaEmDiscip($discA)->id,
        'conteudo' => 'paradigma de programação funcional',
        'embedding' => null,
        'embedding_model' => null,
    ]);

    Chunk::factory()->create([
        'pagina_id' => paginaEmDiscip($discB)->id,
        'conteudo' => 'paradigma de programação imperativo',
        'embedding' => null,
        'embedding_model' => null,
    ]);

    Prism::fake([vectorResponse(unitVector(0))]);

    $results = app(RetrievalService::class)->forQuery(
        'paradigmas programação',
        new Escopo(disciplinas: ['disc-fallback-a'])
    );

    $ids = collect($results)->pluck('chunk_id');
    expect($ids)->toContain($chunkA->id)->and($ids)->toHaveCount(1);
});
