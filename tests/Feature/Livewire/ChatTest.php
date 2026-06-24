<?php

use App\Jobs\ChatResponseJob;
use App\Livewire\Chat;
use App\Models\ChatSessao;
use App\Models\Disciplina;
use App\Models\Pagina;
use App\Services\AI\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── helpers ──────────────────────────────────────────────────────────────

function chatServiceFake(string $resposta = 'Resposta teste'): ChatService
{
    $mock = Mockery::mock(ChatService::class);
    $mock->shouldReceive('responder')
        ->once()
        ->andReturn(['resposta' => $resposta, 'fontes' => [], 'tokens' => 10]);
    app()->instance(ChatService::class, $mock);

    return $mock;
}

// ─── renderização ─────────────────────────────────────────────────────────

it('renderiza o campo de pergunta e botão enviar', function (): void {
    Livewire::test(Chat::class)
        ->assertSee('Pergunte algo sobre seus apontamentos')
        ->assertSee('Enviar');
});

it('lista todas as disciplinas no dropdown', function (): void {
    Disciplina::factory()->create(['nome' => 'Algoritmos', 'slug' => 'algoritmos']);
    Disciplina::factory()->create(['nome' => 'Redes', 'slug' => 'redes']);

    Livewire::test(Chat::class)
        ->assertSee('Algoritmos')
        ->assertSee('Redes');
});

// ─── multi-disciplina ─────────────────────────────────────────────────────

it('inicia sem nenhuma disciplina selecionada', function (): void {
    Livewire::test(Chat::class)
        ->assertSet('disciplinasSlugs', []);
});

it('adiciona e remove disciplinas via wire:model', function (): void {
    Livewire::test(Chat::class)
        ->set('disciplinasSlugs', ['estruturas-de-dados'])
        ->assertSet('disciplinasSlugs', ['estruturas-de-dados'])
        ->set('disciplinasSlugs', [])
        ->assertSet('disciplinasSlugs', []);
});

it('removerDisciplina remove apenas o slug alvo', function (): void {
    Livewire::test(Chat::class)
        ->set('disciplinasSlugs', ['algoritmos', 'redes', 'compiladores'])
        ->call('removerDisciplina', 'redes')
        ->assertSet('disciplinasSlugs', ['algoritmos', 'compiladores']);
});

// ─── enviar (fila assíncrona) ──────────────────────────────────────────────

it('enviar adiciona mensagem do usuário e placeholder pendente imediatamente', function (): void {
    Queue::fake();

    Livewire::test(Chat::class)
        ->set('pergunta', 'O que é herança?')
        ->call('enviar')
        ->assertSet('historico', fn (array $h) => count($h) === 2
            && $h[0]['role'] === 'user'
            && $h[0]['content'] === 'O que é herança?'
            && $h[1]['role'] === 'assistant'
            && $h[1]['status'] === 'pending');
});

it('enviar despacha ChatResponseJob com disciplinas e pergunta corretas', function (): void {
    Queue::fake();

    $discA = Disciplina::factory()->create(['slug' => 'sistemas-operacionais']);
    Pagina::factory()->create(['disciplina_id' => $discA->id]);

    Livewire::test(Chat::class)
        ->set('disciplinasSlugs', ['sistemas-operacionais'])
        ->set('pergunta', 'O que é um processo?')
        ->call('enviar');

    Queue::assertPushed(ChatResponseJob::class, function ($job) {
        $ref = new ReflectionClass($job);
        $pergunta = $ref->getProperty('pergunta')->getValue($job);
        $slugs = $ref->getProperty('disciplinasSlugs')->getValue($job);

        return $pergunta === 'O que é um processo?'
            && $slugs === ['sistemas-operacionais'];
    });
});

it('enviar nao despacha segundo job enquanto resposta anterior está pendente', function (): void {
    Queue::fake();

    $sessao = ChatSessao::create([
        'titulo' => 'Conversa',
        'historico' => [
            ['role' => 'user', 'content' => 'Pergunta 1', 'fontes' => [], 'status' => 'done'],
            ['role' => 'assistant', 'content' => '', 'fontes' => [], 'status' => 'pending'],
        ],
    ]);

    Livewire::test(Chat::class)
        ->set('sessaoId', $sessao->id)
        ->set('historico', $sessao->historico)
        ->set('pergunta', 'Pergunta 2')
        ->call('enviar');

    Queue::assertNothingPushed();
});

it('ChatResponseJob substitui placeholder pendente com resposta real', function (): void {
    $sessao = ChatSessao::create([
        'titulo' => 'Test',
        'historico' => [
            ['role' => 'user', 'content' => 'Explique herança', 'fontes' => [], 'status' => 'done'],
            ['role' => 'assistant', 'content' => '', 'fontes' => [], 'status' => 'pending'],
        ],
    ]);

    $mock = Mockery::mock(ChatService::class);
    $mock->shouldReceive('responder')
        ->once()
        ->andReturn(['resposta' => 'Herança é...', 'fontes' => [], 'tokens' => 10]);

    $job = new ChatResponseJob($sessao->id, 'Explique herança', [], []);
    $job->handle($mock);

    $sessao->refresh();
    expect($sessao->historico)->toHaveCount(2)
        ->and($sessao->historico[1]['status'])->toBe('done')
        ->and($sessao->historico[1]['content'])->toBe('Herança é...');
});

it('refreshHistorico recarrega historico a partir do banco', function (): void {
    $sessao = ChatSessao::create([
        'titulo' => 'Teste',
        'historico' => [
            ['role' => 'user', 'content' => 'oi', 'fontes' => [], 'status' => 'done'],
            ['role' => 'assistant', 'content' => 'Resposta do banco', 'fontes' => [], 'status' => 'done'],
        ],
    ]);

    Livewire::test(Chat::class)
        ->set('sessaoId', $sessao->id)
        ->set('historico', [['role' => 'user', 'content' => 'oi', 'fontes' => [], 'status' => 'done']])
        ->call('refreshHistorico')
        ->assertSet('historico', fn (array $h) => count($h) === 2
            && $h[1]['content'] === 'Resposta do banco');
});

// ─── auto-save ────────────────────────────────────────────────────────────

it('enviar cria uma ChatSessao automaticamente na primeira mensagem', function (): void {
    chatServiceFake();

    Livewire::test(Chat::class)
        ->set('pergunta', 'O que é herança?')
        ->call('enviar');

    expect(ChatSessao::count())->toBe(1);
    $sessao = ChatSessao::first();
    expect($sessao->titulo)->toContain('O que é herança');
    expect($sessao->historico)->toHaveCount(2);
});

it('enviar atualiza a sessao existente sem criar duplicata', function (): void {
    chatServiceFake();

    $component = Livewire::test(Chat::class)
        ->set('pergunta', 'Primeira pergunta')
        ->call('enviar');

    expect(ChatSessao::count())->toBe(1);
    $idPrimeiro = ChatSessao::first()->id;

    chatServiceFake('Segunda resposta');
    $component
        ->set('pergunta', 'Segunda pergunta')
        ->call('enviar');

    expect(ChatSessao::count())->toBe(1);
    expect(ChatSessao::first()->id)->toBe($idPrimeiro);
    expect(ChatSessao::first()->historico)->toHaveCount(4);
});

// ─── histórico ────────────────────────────────────────────────────────────

it('carregarSessao restaura o historico e o sessaoId', function (): void {
    $sessao = ChatSessao::create([
        'titulo' => 'Herança de classes',
        'historico' => [
            ['role' => 'user', 'content' => 'Explique herança', 'fontes' => [], 'status' => 'done'],
            ['role' => 'assistant', 'content' => 'Herança é...', 'fontes' => [], 'status' => 'done'],
        ],
    ]);

    Livewire::test(Chat::class)
        ->call('carregarSessao', $sessao->id)
        ->assertSet('sessaoId', $sessao->id)
        ->assertSet('historico', fn (array $h) => count($h) === 2);
});

it('deletarSessao remove do banco e limpa a view se for a sessao ativa', function (): void {
    $sessao = ChatSessao::create([
        'titulo' => 'Para deletar',
        'historico' => [['role' => 'user', 'content' => 'oi', 'fontes' => [], 'status' => 'done']],
    ]);

    Livewire::test(Chat::class)
        ->set('sessaoId', $sessao->id)
        ->set('historico', $sessao->historico)
        ->call('deletarSessao', $sessao->id)
        ->assertSet('sessaoId', null)
        ->assertSet('historico', []);

    expect(ChatSessao::find($sessao->id))->toBeNull();
});

it('deletarSessao de outra sessao nao afeta a sessao ativa', function (): void {
    $ativa = ChatSessao::create([
        'titulo' => 'Ativa',
        'historico' => [['role' => 'user', 'content' => 'ativa', 'fontes' => [], 'status' => 'done']],
    ]);
    $outra = ChatSessao::create([
        'titulo' => 'Outra',
        'historico' => [['role' => 'user', 'content' => 'outra', 'fontes' => [], 'status' => 'done']],
    ]);

    Livewire::test(Chat::class)
        ->set('sessaoId', $ativa->id)
        ->set('historico', $ativa->historico)
        ->call('deletarSessao', $outra->id)
        ->assertSet('sessaoId', $ativa->id)
        ->assertSet('historico', fn (array $h) => count($h) === 1);

    expect(ChatSessao::find($outra->id))->toBeNull();
    expect(ChatSessao::find($ativa->id))->not->toBeNull();
});

// ─── limpar ───────────────────────────────────────────────────────────────

it('limpar apaga o historico e reseta sessaoId sem deletar do banco', function (): void {
    $sessao = ChatSessao::create([
        'titulo' => 'Manter no banco',
        'historico' => [['role' => 'user', 'content' => 'oi', 'fontes' => [], 'status' => 'done']],
    ]);

    Livewire::test(Chat::class)
        ->set('sessaoId', $sessao->id)
        ->set('historico', $sessao->historico)
        ->call('limpar')
        ->assertSet('historico', [])
        ->assertSet('sessaoId', null);

    expect(ChatSessao::find($sessao->id))->not->toBeNull();
});
