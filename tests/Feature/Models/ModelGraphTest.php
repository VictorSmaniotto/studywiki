<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria disciplina e suas paginas via factory', function () {
    $disciplina = Disciplina::factory()
        ->has(Pagina::factory()->count(3))
        ->create();

    expect($disciplina->paginas)->toHaveCount(3);
    expect($disciplina->paginas->first()->disciplina_id)->toBe($disciplina->id);
});

it('cria pagina com chunks e tags via factory', function () {
    $pagina = Pagina::factory()
        ->has(Chunk::factory()->count(3))
        ->hasAttached(Tag::factory()->count(2))
        ->create();

    $loaded = Pagina::with('chunks', 'tags')->find($pagina->id);

    expect($loaded->chunks)->toHaveCount(3)
        ->and($loaded->tags)->toHaveCount(2);
});

it('carrega Pagina::with chunks e tags sem N+1', function () {
    Pagina::factory()
        ->has(Chunk::factory()->count(2))
        ->hasAttached(Tag::factory()->count(2))
        ->count(3)
        ->create();

    $paginas = Pagina::with('chunks', 'tags')->get();

    expect($paginas)->toHaveCount(3);
    $paginas->each(function ($p) {
        expect($p->chunks->count())->toBe(2)
            ->and($p->tags->count())->toBe(2);
    });
});

it('cria geracao com fontes rastreando paginas e chunks', function () {
    $pagina = Pagina::factory()->has(Chunk::factory())->create();
    $chunk = $pagina->chunks->first();

    $geracao = Geracao::factory()->create();
    GeracaoFonte::factory()->create([
        'geracao_id' => $geracao->id,
        'pagina_id' => $pagina->id,
        'chunk_id' => $chunk->id,
    ]);

    expect($geracao->fontes)->toHaveCount(1)
        ->and($geracao->fontes->first()->chunk_id)->toBe($chunk->id)
        ->and($geracao->fontes->first()->pagina_id)->toBe($pagina->id);
});

it('chunk pertence a pagina', function () {
    $chunk = Chunk::factory()->create();

    expect($chunk->pagina)->toBeInstanceOf(Pagina::class);
});

it('pagina com soft delete nao aparece em consultas normais', function () {
    $pagina = Pagina::factory()->create();
    $pagina->delete();

    expect(Pagina::find($pagina->id))->toBeNull()
        ->and(Pagina::withTrashed()->find($pagina->id))->not->toBeNull();
});
