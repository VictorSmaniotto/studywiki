<?php

use App\Services\AI\GroundingValidator;

// ─── helpers ───────────────────────────────────────────────────────────────

function chunk_ctx(int $chunkId, int $paginaId, string $conteudo): array
{
    return [
        'chunk_id' => $chunkId,
        'pagina_id' => $paginaId,
        'heading_path' => null,
        'conteudo' => $conteudo,
        'tokens' => 50,
        'titulo_pagina' => 'Página Teste',
        'path_relativo' => 'teste.md',
        'score' => null,
    ];
}

function item_ancorado(string $texto, array $fontes): array
{
    return ['texto' => $texto, 'fontes' => $fontes];
}

// ─── AC-G1: item deve ter ao menos uma fonte ───────────────────────────────

it('reprova item sem fontes', function () {
    $validator = new GroundingValidator;
    $contexto = [chunk_ctx(1, 1, 'compiladores analisam código fonte')];

    $result = $validator->validate(item_ancorado('análise léxica de compiladores', []), $contexto);

    expect($result->aprovado)->toBeFalse()
        ->and($result->motivo)->toBe('sem_fontes');
});

// ─── AC-G2: fonte fantasma ─────────────────────────────────────────────────

it('reprova item com pagina_id que nao existe no contexto', function () {
    $validator = new GroundingValidator;
    $contexto = [chunk_ctx(1, 10, 'conteúdo real sobre compiladores')];

    $result = $validator->validate(
        item_ancorado('compiladores', [['pagina_id' => 99]]),
        $contexto
    );

    expect($result->aprovado)->toBeFalse()
        ->and($result->motivo)->toBe('fonte_fantasma');
});

it('reprova item com chunk_id que nao existe no contexto da pagina', function () {
    $validator = new GroundingValidator;
    $contexto = [chunk_ctx(5, 10, 'conteúdo real sobre compiladores')];

    $result = $validator->validate(
        item_ancorado('compiladores', [['pagina_id' => 10, 'chunk_id' => 99]]),
        $contexto
    );

    expect($result->aprovado)->toBeFalse()
        ->and($result->motivo)->toBe('fonte_fantasma');
});

it('aceita fonte que referencia apenas pagina_id sem chunk_id', function () {
    $texto = 'compiladores realizam análise léxica do código fonte';
    $validator = new GroundingValidator;
    $contexto = [chunk_ctx(5, 10, 'compiladores realizam análise léxica do código fonte')];

    $result = $validator->validate(
        item_ancorado($texto, [['pagina_id' => 10]]),
        $contexto
    );

    expect($result->aprovado)->toBeTrue();
});

// ─── AC-G3: overlap léxico ─────────────────────────────────────────────────

it('aprova item ancorado com overlap suficiente', function () {
    $validator = new GroundingValidator;
    $conteudo = 'compiladores realizam análise léxica sintática e semântica do código fonte';
    $contexto = [chunk_ctx(1, 1, $conteudo)];

    $result = $validator->validate(
        item_ancorado(
            'análise léxica é a primeira fase dos compiladores processando código fonte',
            [['pagina_id' => 1, 'chunk_id' => 1]]
        ),
        $contexto
    );

    expect($result->aprovado)->toBeTrue();
});

it('reprova item cujo texto nao tem overlap com o chunk citado', function () {
    $validator = new GroundingValidator;
    // Chunk fala de compiladores; item fala de banco de dados — sem interseção
    $contexto = [chunk_ctx(1, 1, 'compiladores realizam análise léxica sintática')];

    $result = $validator->validate(
        item_ancorado(
            'índices btree melhoram desempenho consultas relacionais',
            [['pagina_id' => 1, 'chunk_id' => 1]]
        ),
        $contexto
    );

    expect($result->aprovado)->toBeFalse()
        ->and($result->motivo)->toBe('overlap_insuficiente')
        ->and($result->detalhes['overlap'])->toBeLessThan(0.10);
});

it('retorna overlap nos detalhes quando reprova por overlap_insuficiente', function () {
    $validator = new GroundingValidator;
    $contexto = [chunk_ctx(1, 1, 'redes neurais gradient descent backpropagation')];

    $result = $validator->validate(
        item_ancorado('algoritmos ordenação quicksort mergesort', [['pagina_id' => 1, 'chunk_id' => 1]]),
        $contexto
    );

    expect($result->detalhes)->toHaveKeys(['overlap', 'limiar']);
});

// ─── múltiplas fontes ──────────────────────────────────────────────────────

it('aceita item com multiplas fontes todas validas e com overlap', function () {
    $validator = new GroundingValidator;
    $contexto = [
        chunk_ctx(1, 1, 'compiladores análise léxica tokens identificadores'),
        chunk_ctx(2, 2, 'análise sintática árvore derivação gramática'),
    ];

    $result = $validator->validate(
        item_ancorado(
            'compiladores usam análise léxica para tokens e análise sintática para gramática',
            [['pagina_id' => 1, 'chunk_id' => 1], ['pagina_id' => 2, 'chunk_id' => 2]]
        ),
        $contexto
    );

    expect($result->aprovado)->toBeTrue();
});

it('reprova se qualquer uma das fontes for fantasma', function () {
    $validator = new GroundingValidator;
    $contexto = [chunk_ctx(1, 1, 'compiladores análise léxica')];

    $result = $validator->validate(
        item_ancorado(
            'compiladores análise léxica',
            [['pagina_id' => 1, 'chunk_id' => 1], ['pagina_id' => 99]]
        ),
        $contexto
    );

    expect($result->aprovado)->toBeFalse()
        ->and($result->motivo)->toBe('fonte_fantasma');
});
