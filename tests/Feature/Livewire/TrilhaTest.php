<?php

use App\Livewire\Trilha;
use App\Models\Chunk;
use App\Models\Disciplina;
use App\Models\Flashcard;
use App\Models\Geracao;
use App\Models\Pagina;
use App\Models\RespostaSimulado;
use App\Models\Setting;
use App\Services\TrilhaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────
// TL1 — flashcardsVencidos
// ──────────────────────────────────────────────

it('TL1: flashcardsVencidos retorna cards com proxima_revisao <= hoje', function () {
    $hoje = Flashcard::factory()->create(['proxima_revisao' => Carbon::today()->toDateString()]);
    $ontem = Flashcard::factory()->create(['proxima_revisao' => Carbon::yesterday()->toDateString()]);
    $amanha = Flashcard::factory()->create(['proxima_revisao' => Carbon::tomorrow()->toDateString()]);

    $service = new TrilhaService;
    $vencidos = $service->flashcardsVencidos();

    expect($vencidos->pluck('id')->all())
        ->toContain($hoje->id)
        ->toContain($ontem->id);

    expect($vencidos->pluck('id')->all())
        ->not->toContain($amanha->id);
});

it('TL1: flashcardsVencidos retorna collection vazia quando nenhum card venceu', function () {
    Flashcard::factory()->create(['proxima_revisao' => Carbon::tomorrow()->toDateString()]);

    $service = new TrilhaService;

    expect($service->flashcardsVencidos())->toBeEmpty();
});

// ──────────────────────────────────────────────
// TL1 — topicosPrioritarios
// ──────────────────────────────────────────────

it('TL1: topicosPrioritarios retorna headings ordenados por erros descendente', function () {
    $disciplina = Disciplina::factory()->create();
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);
    $chunkA = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Pilha > Operações']);
    $chunkB = Chunk::factory()->create(['pagina_id' => $pagina->id, 'heading_path' => 'Fila > Operações']);

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => $disciplina->slug],
        'payload' => [
            'questoes_me' => [
                ['correta' => 'a', 'fontes' => [['chunk_id' => $chunkA->id]]],
                ['correta' => 'b', 'fontes' => [['chunk_id' => $chunkA->id]]],
                ['correta' => 'c', 'fontes' => [['chunk_id' => $chunkB->id]]],
            ],
        ],
    ]);

    RespostaSimulado::create([
        'geracao_id' => $geracao->id,
        'respostas' => ['0' => 'b', '1' => 'c', '2' => 'a'],
        'acertos' => 0,
        'total' => 3,
    ]);

    $service = new TrilhaService;
    $topicos = $service->topicosPrioritarios();

    expect($topicos)->toHaveCount(2)
        ->and($topicos[0]['heading'])->toBe('Pilha')
        ->and($topicos[0]['erros'])->toBe(2)
        ->and($topicos[1]['heading'])->toBe('Fila')
        ->and($topicos[1]['erros'])->toBe(1);
});

it('TL1: topicosPrioritarios retorna array vazio quando nao ha respostas', function () {
    $service = new TrilhaService;

    expect($service->topicosPrioritarios())->toBeEmpty();
});

it('TL1: topicosPrioritarios respeita o limite informado', function () {
    $disciplina = Disciplina::factory()->create();
    $pagina = Pagina::factory()->create(['disciplina_id' => $disciplina->id]);

    $chunks = Chunk::factory()->count(4)->sequence(
        ['heading_path' => 'A'],
        ['heading_path' => 'B'],
        ['heading_path' => 'C'],
        ['heading_path' => 'D'],
    )->create(['pagina_id' => $pagina->id]);

    $geracao = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => $disciplina->slug],
        'payload' => [
            'questoes_me' => $chunks->map(fn ($c) => [
                'correta' => 'a',
                'fontes' => [['chunk_id' => $c->id]],
            ])->values()->all(),
        ],
    ]);

    RespostaSimulado::create([
        'geracao_id' => $geracao->id,
        'respostas' => ['0' => 'b', '1' => 'b', '2' => 'b', '3' => 'b'],
        'acertos' => 0,
        'total' => 4,
    ]);

    $service = new TrilhaService;

    expect($service->topicosPrioritarios(2))->toHaveCount(2);
});

// ──────────────────────────────────────────────
// TL2 — registrarSessao
// ──────────────────────────────────────────────

it('TL2: registrarSessao pela primeira vez seta streak=1', function () {
    $service = new TrilhaService;
    $service->registrarSessao();

    expect(Setting::get('streak_count'))->toBe('1')
        ->and(Setting::get('streak_last_date'))->toBe(Carbon::today()->toDateString());
});

it('TL2: registrarSessao duas vezes no mesmo dia nao incrementa streak', function () {
    $service = new TrilhaService;
    $service->registrarSessao();
    $service->registrarSessao();

    expect(Setting::get('streak_count'))->toBe('1');
});

it('TL2: registrarSessao em dias consecutivos incrementa streak', function () {
    Setting::set('streak_last_date', Carbon::yesterday()->toDateString());
    Setting::set('streak_count', '3');

    $service = new TrilhaService;
    $service->registrarSessao();

    expect(Setting::get('streak_count'))->toBe('4')
        ->and(Setting::get('streak_last_date'))->toBe(Carbon::today()->toDateString());
});

it('TL2: registrarSessao apos gap reseta streak para 1', function () {
    Setting::set('streak_last_date', Carbon::today()->subDays(3)->toDateString());
    Setting::set('streak_count', '10');

    $service = new TrilhaService;
    $service->registrarSessao();

    expect(Setting::get('streak_count'))->toBe('1');
});

// ──────────────────────────────────────────────
// TL3 — streakAtual (persistência)
// ──────────────────────────────────────────────

it('TL3: streakAtual retorna 0 quando nunca houve sessao', function () {
    $service = new TrilhaService;

    expect($service->streakAtual())->toBe(0);
});

it('TL3: streakAtual retorna valor correto quando ultima sessao foi hoje', function () {
    Setting::set('streak_last_date', Carbon::today()->toDateString());
    Setting::set('streak_count', '5');

    $service = new TrilhaService;

    expect($service->streakAtual())->toBe(5);
});

it('TL3: streakAtual retorna valor correto quando ultima sessao foi ontem', function () {
    Setting::set('streak_last_date', Carbon::yesterday()->toDateString());
    Setting::set('streak_count', '7');

    $service = new TrilhaService;

    expect($service->streakAtual())->toBe(7);
});

it('TL3: streakAtual retorna 0 quando ultima sessao foi ha mais de 1 dia', function () {
    Setting::set('streak_last_date', Carbon::today()->subDays(2)->toDateString());
    Setting::set('streak_count', '10');

    $service = new TrilhaService;

    expect($service->streakAtual())->toBe(0);
});

// ──────────────────────────────────────────────
// Livewire Trilha
// ──────────────────────────────────────────────

it('pagina trilha renderiza com texto Trilha', function () {
    Livewire::test(Trilha::class)
        ->assertOk()
        ->assertSee('Trilha');
});

it('pagina trilha exibe flashcard vencido', function () {
    Flashcard::factory()->create([
        'frente' => 'Conceito de pilha léxica',
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);
    Flashcard::factory()->create([
        'proxima_revisao' => Carbon::tomorrow()->toDateString(),
    ]);

    Livewire::test(Trilha::class)
        ->assertSee('Conceito de pilha léxica');
});

it('pagina trilha nao exibe card futuro', function () {
    Flashcard::factory()->create([
        'frente' => 'Card do futuro',
        'proxima_revisao' => Carbon::tomorrow()->toDateString(),
    ]);

    Livewire::test(Trilha::class)
        ->assertDontSee('Card do futuro');
});

it('registrarSessao via livewire seta sessaoRegistrada e persiste streak', function () {
    Livewire::test(Trilha::class)
        ->call('registrarSessao')
        ->assertSet('sessaoRegistrada', true);

    expect(Setting::get('streak_count'))->toBe('1');
});

it('pagina trilha exibe streak atual', function () {
    Setting::set('streak_last_date', Carbon::today()->toDateString());
    Setting::set('streak_count', '4');

    Livewire::test(Trilha::class)
        ->assertSee('4');
});
