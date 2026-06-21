<?php

use App\Livewire\Chat;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Services\AI\ChatService;
use App\Services\Retrieval\Escopo;
use App\Services\Retrieval\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// helpers
// ──────────────────────────────────────────────

function fakeChatResponse(string $text, int $inputTokens = 80, int $outputTokens = 120): TextResponseFake
{
    return TextResponseFake::make()
        ->withText($text)
        ->withUsage(new Usage($inputTokens, $outputTokens))
        ->withMeta(new Meta('anthropic', 'claude-sonnet-4-6'))
        ->withFinishReason(FinishReason::Stop);
}

function criarContextoChat(
    string $disciplinaSlug = 'redes',
    string $conteudo = 'protocolo TCP garante entrega ordenada de pacotes via handshake',
): array {
    $disciplina = Disciplina::factory()->create(['slug' => $disciplinaSlug]);
    $pagina = Pagina::factory()->create([
        'disciplina_id' => $disciplina->id,
        'titulo' => 'Redes de Computadores',
        'path_relativo' => 'redes/tcp.md',
    ]);
    $chunk = Chunk::factory()->create([
        'pagina_id' => $pagina->id,
        'conteudo' => $conteudo,
        'heading_path' => 'TCP > Handshake',
        'embedding_model' => 'voyage-3-lite',
    ]);

    return compact('disciplina', 'pagina', 'chunk');
}

// ──────────────────────────────────────────────
// C1 — responde com fontes quando há chunks
// ──────────────────────────────────────────────

it('C1: ChatService retorna resposta com fontes quando chunks sao encontrados', function () {
    ['pagina' => $pagina, 'chunk' => $chunk] = criarContextoChat();

    $retrieval = Mockery::mock(RetrievalService::class);
    $retrieval->shouldReceive('forQuery')
        ->once()
        ->andReturn([[
            'chunk_id' => $chunk->id,
            'pagina_id' => $pagina->id,
            'heading_path' => 'TCP > Handshake',
            'conteudo' => 'protocolo TCP garante entrega ordenada de pacotes',
            'tokens' => 10,
            'titulo_pagina' => 'Redes de Computadores',
            'path_relativo' => 'redes/tcp.md',
            'score' => 0.9,
        ]]);

    Prism::fake([fakeChatResponse('O TCP usa handshake de 3 vias para garantir entrega.')]);

    $service = new ChatService($retrieval);
    $resultado = $service->responder('Como funciona o TCP?', new Escopo(disciplina: 'redes'));

    expect($resultado['resposta'])->toContain('TCP')
        ->and($resultado['fontes'])->toHaveCount(1)
        ->and($resultado['fontes'][0]['titulo_pagina'])->toBe('Redes de Computadores')
        ->and($resultado['tokens'])->toBe(200);
});

// ──────────────────────────────────────────────
// C2 — sem chunks relevantes
// ──────────────────────────────────────────────

it('C2: ChatService retorna mensagem padrao quando nao ha chunks relevantes', function () {
    $retrieval = Mockery::mock(RetrievalService::class);
    $retrieval->shouldReceive('forQuery')
        ->once()
        ->andReturn([]);

    Prism::fake([]);

    $service = new ChatService($retrieval);
    $resultado = $service->responder('Qual é o sentido da vida?', new Escopo);

    expect($resultado['resposta'])->toContain('Não encontrei')
        ->and($resultado['fontes'])->toBeEmpty()
        ->and($resultado['tokens'])->toBe(0);
});

// ──────────────────────────────────────────────
// C3 — fontes incluem chunk de origem
// ──────────────────────────────────────────────

it('C3: ChatService inclui chunk_id e pagina_id na lista de fontes', function () {
    ['pagina' => $pagina, 'chunk' => $chunk] = criarContextoChat();

    $retrieval = Mockery::mock(RetrievalService::class);
    $retrieval->shouldReceive('forQuery')
        ->once()
        ->andReturn([[
            'chunk_id' => $chunk->id,
            'pagina_id' => $pagina->id,
            'heading_path' => 'TCP > Handshake',
            'conteudo' => 'protocolo TCP garante entrega',
            'tokens' => 8,
            'titulo_pagina' => 'Redes de Computadores',
            'path_relativo' => 'redes/tcp.md',
            'score' => 0.85,
        ]]);

    Prism::fake([fakeChatResponse('Resposta sobre TCP.')]);

    $service = new ChatService($retrieval);
    $resultado = $service->responder('TCP?', new Escopo);

    expect($resultado['fontes'][0])->toMatchArray([
        'chunk_id' => $chunk->id,
        'pagina_id' => $pagina->id,
        'heading_path' => 'TCP > Handshake',
    ]);
});

// ──────────────────────────────────────────────
// C4 — Livewire Chat renderiza
// ──────────────────────────────────────────────

it('C4: pagina chat renderiza input e botao de enviar', function () {
    Livewire::test(Chat::class)
        ->assertOk()
        ->assertSee('Chat com a Vault')
        ->assertSee('Enviar');
});

// ──────────────────────────────────────────────
// C5 — enviar adiciona mensagem do usuário
// ──────────────────────────────────────────────

it('C5: enviar adiciona mensagem do usuario ao historico', function () {
    ['pagina' => $pagina, 'chunk' => $chunk] = criarContextoChat();

    $retrieval = Mockery::mock(RetrievalService::class);
    $retrieval->shouldReceive('forQuery')
        ->once()
        ->andReturn([[
            'chunk_id' => $chunk->id,
            'pagina_id' => $pagina->id,
            'heading_path' => 'TCP > Handshake',
            'conteudo' => 'protocolo TCP garante entrega',
            'tokens' => 8,
            'titulo_pagina' => 'Redes de Computadores',
            'path_relativo' => 'redes/tcp.md',
            'score' => 0.9,
        ]]);

    app()->instance(RetrievalService::class, $retrieval);
    Prism::fake([fakeChatResponse('TCP usa handshake.')]);

    Livewire::test(Chat::class)
        ->set('pergunta', 'O que é TCP?')
        ->call('enviar')
        ->assertSet('pergunta', '')
        ->assertSet('carregando', false)
        ->assertSee('O que é TCP?');
});

// ──────────────────────────────────────────────
// C6 — enviar adiciona resposta da IA
// ──────────────────────────────────────────────

it('C6: enviar adiciona resposta da IA ao historico com fontes', function () {
    ['pagina' => $pagina, 'chunk' => $chunk] = criarContextoChat();

    $retrieval = Mockery::mock(RetrievalService::class);
    $retrieval->shouldReceive('forQuery')
        ->once()
        ->andReturn([[
            'chunk_id' => $chunk->id,
            'pagina_id' => $pagina->id,
            'heading_path' => 'TCP > Handshake',
            'conteudo' => 'protocolo TCP garante entrega',
            'tokens' => 8,
            'titulo_pagina' => 'Redes de Computadores',
            'path_relativo' => 'redes/tcp.md',
            'score' => 0.9,
        ]]);

    app()->instance(RetrievalService::class, $retrieval);
    Prism::fake([fakeChatResponse('TCP usa o three-way handshake.')]);

    $component = Livewire::test(Chat::class)
        ->set('pergunta', 'O que é TCP?')
        ->call('enviar');

    $historico = $component->get('historico');

    expect($historico)->toHaveCount(2)
        ->and($historico[0]['role'])->toBe('user')
        ->and($historico[1]['role'])->toBe('assistant')
        ->and($historico[1]['content'])->toContain('TCP')
        ->and($historico[1]['fontes'])->not->toBeEmpty();
});

// ──────────────────────────────────────────────
// C7 — limpar apaga histórico
// ──────────────────────────────────────────────

it('C7: limpar apaga historico da sessao', function () {
    session(['chat_historico' => [
        ['role' => 'user', 'content' => 'Pergunta antiga', 'fontes' => []],
    ]]);

    Livewire::test(Chat::class)
        ->call('limpar')
        ->assertSet('historico', [])
        ->assertDontSee('Pergunta antiga');

    expect(session('chat_historico'))->toBeNull();
});

// ──────────────────────────────────────────────
// C8 — histórico persiste na sessão
// ──────────────────────────────────────────────

it('C8: historico persiste na sessao entre mounts', function () {
    ['pagina' => $pagina, 'chunk' => $chunk] = criarContextoChat();

    $retrieval = Mockery::mock(RetrievalService::class);
    $retrieval->shouldReceive('forQuery')
        ->once()
        ->andReturn([[
            'chunk_id' => $chunk->id,
            'pagina_id' => $pagina->id,
            'heading_path' => null,
            'conteudo' => 'protocolo TCP',
            'tokens' => 5,
            'titulo_pagina' => 'Redes de Computadores',
            'path_relativo' => 'redes/tcp.md',
            'score' => 0.8,
        ]]);

    app()->instance(RetrievalService::class, $retrieval);
    Prism::fake([fakeChatResponse('TCP é confiável.')]);

    Livewire::test(Chat::class)
        ->set('pergunta', 'Primeira pergunta')
        ->call('enviar');

    $historico = session('chat_historico');
    expect($historico)->toHaveCount(2);

    Livewire::test(Chat::class)
        ->assertSee('Primeira pergunta');
});
