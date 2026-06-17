<?php

use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Models\Tag;
use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── helpers ───────────────────────────────────────────────────────────────

function pagina_com_chunks(array $paginaAttrs = [], int $qtdChunks = 2): Pagina
{
    $pagina = Pagina::factory()->create($paginaAttrs);
    Chunk::factory()->count($qtdChunks)->create(['pagina_id' => $pagina->id]);

    return $pagina;
}

// ─── estrutura de retorno ──────────────────────────────────────────────────

it('retorna campos obrigatorios chunk_id pagina_id heading_path conteudo', function () {
    pagina_com_chunks();

    $resultado = app(RetrievalService::class)->forScope(new Escopo);

    expect($resultado)->not->toBeEmpty();
    $primeiro = $resultado[0];
    expect($primeiro)->toHaveKeys(['chunk_id', 'pagina_id', 'heading_path', 'conteudo', 'tokens', 'titulo_pagina', 'path_relativo', 'score']);
    expect($primeiro['score'])->toBeNull();
});

it('ordena chunks por pagina e por ordem dentro da pagina', function () {
    $pagina = Pagina::factory()->create();
    Chunk::factory()->create(['pagina_id' => $pagina->id, 'ordem' => 1]);
    Chunk::factory()->create(['pagina_id' => $pagina->id, 'ordem' => 0]);

    $resultado = app(RetrievalService::class)->forScope(new Escopo);

    $ordens = collect($resultado)
        ->where('pagina_id', $pagina->id)
        ->pluck('conteudo')
        ->keys()
        ->all();

    expect($resultado[0]['pagina_id'])->toBe($resultado[1]['pagina_id'])
        ->and($resultado[0]['pagina_id'])->toBe($pagina->id);

    // Ordem 0 deve vir antes da ordem 1
    $ordemNaLista = collect($resultado)
        ->where('pagina_id', $pagina->id)
        ->values();
    expect(Chunk::where('pagina_id', $pagina->id)->orderBy('ordem')->first()->id)
        ->toBe($ordemNaLista[0]['chunk_id']);
});

// ─── filtro de disciplina (AC principal) ───────────────────────────────────

it('respeita filtro de disciplina retornando so chunks da disciplina', function () {
    $disc = Disciplina::factory()->create(['nome' => 'Redes de Computadores', 'slug' => 'redes-de-computadores']);
    $outra = Disciplina::factory()->create(['nome' => 'Algoritmos', 'slug' => 'algoritmos']);

    pagina_com_chunks(['disciplina_id' => $disc->id]);
    pagina_com_chunks(['disciplina_id' => $outra->id]);

    $resultado = app(RetrievalService::class)->forScope(new Escopo(disciplina: 'Redes de Computadores'));

    $paginas = collect($resultado)->pluck('pagina_id')->unique()->values();
    expect(Pagina::whereIn('id', $paginas)->pluck('disciplina_id')->unique()->all())
        ->toBe([$disc->id]);
});

it('disciplina inexistente retorna lista vazia', function () {
    pagina_com_chunks();

    $resultado = app(RetrievalService::class)->forScope(new Escopo(disciplina: 'Inexistente'));

    expect($resultado)->toBeEmpty();
});

// ─── filtro de tags ────────────────────────────────────────────────────────

it('respeita filtro de tags retornando so chunks de paginas com a tag', function () {
    $tag = Tag::factory()->create(['nome' => 'oop', 'slug' => 'oop']);
    $comTag = pagina_com_chunks();
    $comTag->tags()->attach($tag);

    pagina_com_chunks(); // sem tag

    $resultado = app(RetrievalService::class)->forScope(new Escopo(tags: ['oop']));

    expect(collect($resultado)->pluck('pagina_id')->unique()->all())
        ->toBe([$comTag->id]);
});

it('tag inexistente retorna lista vazia', function () {
    pagina_com_chunks();

    $resultado = app(RetrievalService::class)->forScope(new Escopo(tags: ['tag-que-nao-existe']));

    expect($resultado)->toBeEmpty();
});

// ─── filtro por paginas ────────────────────────────────────────────────────

it('respeita filtro por ids de paginas', function () {
    $alvo = pagina_com_chunks();
    pagina_com_chunks(); // outra página, não deve aparecer

    $resultado = app(RetrievalService::class)->forScope(new Escopo(paginas: [$alvo->id]));

    expect(collect($resultado)->pluck('pagina_id')->unique()->all())
        ->toBe([$alvo->id]);
});

// ─── soft delete ───────────────────────────────────────────────────────────

it('nao retorna chunks de paginas com soft delete', function () {
    $ativa = pagina_com_chunks();
    $deletada = pagina_com_chunks();
    $deletada->delete();

    $resultado = app(RetrievalService::class)->forScope(new Escopo);

    expect(collect($resultado)->pluck('pagina_id')->unique()->all())
        ->toBe([$ativa->id]);
});

// ─── filtros combinados ────────────────────────────────────────────────────

it('combina filtro de disciplina e tags', function () {
    $disc = Disciplina::factory()->create(['nome' => 'SO', 'slug' => 'so']);
    $tag = Tag::factory()->create(['nome' => 'kernel', 'slug' => 'kernel']);

    $alvo = pagina_com_chunks(['disciplina_id' => $disc->id]);
    $alvo->tags()->attach($tag);

    // página da disciplina mas sem a tag
    pagina_com_chunks(['disciplina_id' => $disc->id]);

    // página com a tag mas sem a disciplina
    $semDisc = pagina_com_chunks();
    $semDisc->tags()->attach($tag);

    $resultado = app(RetrievalService::class)->forScope(new Escopo(disciplina: 'SO', tags: ['kernel']));

    expect(collect($resultado)->pluck('pagina_id')->unique()->all())
        ->toBe([$alvo->id]);
});

// ─── escopo vazio ──────────────────────────────────────────────────────────

it('escopo sem filtros retorna todos os chunks de paginas ativas', function () {
    pagina_com_chunks(qtdChunks: 3);
    pagina_com_chunks(qtdChunks: 2);

    $resultado = app(RetrievalService::class)->forScope(new Escopo);

    expect(count($resultado))->toBe(5);
});
