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
// TL4 — marcarRevisao (SM-2)
// ──────────────────────────────────────────────

it('TL4: marcarRevisao acerto na primeira repeticao seta intervalo=1 e incrementa repeticoes', function () {
    $card = Flashcard::factory()->create(['repeticoes' => 0, 'intervalo' => 1, 'facilidade' => 2.5]);

    (new TrilhaService)->marcarRevisao($card->id, true);

    $card->refresh();
    expect($card->repeticoes)->toBe(1)
        ->and($card->intervalo)->toBe(1)
        ->and($card->proxima_revisao->toDateString())->toBe(Carbon::today()->addDay()->toDateString());
});

it('TL4: marcarRevisao acerto na segunda repeticao seta intervalo=6', function () {
    $card = Flashcard::factory()->create(['repeticoes' => 1, 'intervalo' => 1, 'facilidade' => 2.5]);

    (new TrilhaService)->marcarRevisao($card->id, true);

    $card->refresh();
    expect($card->repeticoes)->toBe(2)
        ->and($card->intervalo)->toBe(6);
});

it('TL4: marcarRevisao acerto apos segunda repeticao multiplica intervalo pela facilidade', function () {
    $card = Flashcard::factory()->create(['repeticoes' => 2, 'intervalo' => 6, 'facilidade' => 2.5]);

    (new TrilhaService)->marcarRevisao($card->id, true);

    $card->refresh();
    expect($card->intervalo)->toBe(15) // round(6 * 2.5)
        ->and($card->repeticoes)->toBe(3);
});

it('TL4: marcarRevisao erro reseta repeticoes e intervalo para 1', function () {
    $card = Flashcard::factory()->create(['repeticoes' => 5, 'intervalo' => 30, 'facilidade' => 2.5]);

    (new TrilhaService)->marcarRevisao($card->id, false);

    $card->refresh();
    expect($card->repeticoes)->toBe(0)
        ->and($card->intervalo)->toBe(1)
        ->and($card->proxima_revisao->toDateString())->toBe(Carbon::today()->addDay()->toDateString());
});

it('TL4: marcarRevisao erro diminui facilidade respeitando minimo de 1.3', function () {
    $card = Flashcard::factory()->create(['facilidade' => 1.4]);

    (new TrilhaService)->marcarRevisao($card->id, false);

    $card->refresh();
    expect($card->facilidade)->toBe(1.3);
});

it('TL4: marcarRevisao acerto aumenta facilidade', function () {
    $card = Flashcard::factory()->create(['repeticoes' => 3, 'intervalo' => 10, 'facilidade' => 2.5]);

    (new TrilhaService)->marcarRevisao($card->id, true);

    $card->refresh();
    expect($card->facilidade)->toBe(2.6);
});

// ──────────────────────────────────────────────
// Livewire Trilha — renderização
// ──────────────────────────────────────────────

it('pagina trilha renderiza com texto Trilha', function () {
    Livewire::test(Trilha::class)
        ->assertOk()
        ->assertSee('Trilha');
});

it('pagina trilha exibe contador de flashcards vencidos', function () {
    Flashcard::factory()->count(3)->create(['proxima_revisao' => Carbon::today()->toDateString()]);

    Livewire::test(Trilha::class)
        ->assertSee('3');
});

it('pagina trilha nao exibe card futuro no sumario', function () {
    Flashcard::factory()->create([
        'frente' => 'Card do futuro',
        'proxima_revisao' => Carbon::tomorrow()->toDateString(),
    ]);

    Livewire::test(Trilha::class)
        ->assertDontSee('Card do futuro');
});

it('pagina trilha exibe streak atual', function () {
    Setting::set('streak_last_date', Carbon::today()->toDateString());
    Setting::set('streak_count', '4');

    Livewire::test(Trilha::class)
        ->assertSee('4');
});

// ──────────────────────────────────────────────
// Livewire Trilha — fluxo de revisão
// ──────────────────────────────────────────────

it('iniciarRevisao transiciona para modoRevisao e exibe frente do card', function () {
    Flashcard::factory()->create([
        'frente' => 'O que é habeas corpus?',
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->assertSet('modoRevisao', true)
        ->assertSee('O que é habeas corpus?');
});

it('iniciarRevisao nao faz nada quando nao ha cards vencidos', function () {
    Flashcard::factory()->create(['proxima_revisao' => Carbon::tomorrow()->toDateString()]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->assertSet('modoRevisao', false);
});

it('revelarResposta exibe verso do card', function () {
    Flashcard::factory()->create([
        'frente' => 'Pergunta',
        'verso' => 'Resposta esperada do verso',
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->call('revelarResposta')
        ->assertSet('respostaRevelada', true)
        ->assertSee('Resposta esperada do verso');
});

it('avaliar acerto avanca para o proximo card', function () {
    Flashcard::factory()->create(['proxima_revisao' => Carbon::today()->toDateString()]);
    Flashcard::factory()->create([
        'frente' => 'Segundo card',
        'proxima_revisao' => Carbon::today()->toDateString(),
    ]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->call('revelarResposta')
        ->call('avaliar', true)
        ->assertSet('indiceAtual', 1)
        ->assertSet('acertos', 1)
        ->assertSee('Segundo card');
});

it('avaliar erro avanca para o proximo card e conta erro', function () {
    Flashcard::factory()->create(['proxima_revisao' => Carbon::today()->toDateString()]);
    Flashcard::factory()->create(['proxima_revisao' => Carbon::today()->toDateString()]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->call('revelarResposta')
        ->call('avaliar', false)
        ->assertSet('indiceAtual', 1)
        ->assertSet('erros', 1);
});

it('ultimo card avaliado seta sessaoConcluida e registra streak', function () {
    Flashcard::factory()->create(['proxima_revisao' => Carbon::today()->toDateString()]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->call('revelarResposta')
        ->call('avaliar', true)
        ->assertSet('sessaoConcluida', true);

    expect(Setting::get('streak_count'))->toBe('1');
});

it('encerrarRevisao reseta todos os estados', function () {
    Flashcard::factory()->create(['proxima_revisao' => Carbon::today()->toDateString()]);

    Livewire::test(Trilha::class)
        ->call('iniciarRevisao')
        ->call('encerrarRevisao')
        ->assertSet('modoRevisao', false)
        ->assertSet('sessaoConcluida', false)
        ->assertSet('indiceAtual', 0)
        ->assertSet('acertos', 0)
        ->assertSet('erros', 0);
});
