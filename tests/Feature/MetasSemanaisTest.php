<?php

use App\Livewire\Metas;
use App\Models\Flashcard;
use App\Models\Geracao;
use App\Models\RespostaSimulado;
use App\Models\Setting;
use App\Services\MetaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// M1 — banco vazio retorna zeros em todos os contadores
it('retorna zeros quando não há atividade na semana', function () {
    $progresso = app(MetaService::class)->progressoSemana();

    expect($progresso['simulados']['atual'])->toBe(0)
        ->and($progresso['flashcards']['atual'])->toBe(0)
        ->and($progresso['geracoes']['atual'])->toBe(0);
});

// M2 — conta simulados concluídos na semana atual
it('conta simulados concluídos na semana atual', function () {
    $geracao = Geracao::factory()->create(['tipo' => 'simulado', 'status' => 'ok']);
    RespostaSimulado::factory()->create([
        'geracao_id' => $geracao->id,
        'created_at' => Carbon::now()->startOfWeek()->addDays(1),
    ]);

    $progresso = app(MetaService::class)->progressoSemana();

    expect($progresso['simulados']['atual'])->toBe(1);
});

// M3 — não conta simulados de semana anterior
it('não conta simulados da semana anterior', function () {
    $geracao = Geracao::factory()->create(['tipo' => 'simulado', 'status' => 'ok']);
    RespostaSimulado::factory()->create([
        'geracao_id' => $geracao->id,
        'created_at' => Carbon::now()->startOfWeek()->subDay(),
    ]);

    $progresso = app(MetaService::class)->progressoSemana();

    expect($progresso['simulados']['atual'])->toBe(0);
});

// M4 — conta flashcards revisados (updated_at > created_at, dentro da semana)
it('conta flashcards revisados na semana atual', function () {
    $passado = Carbon::now()->startOfWeek()->subHour();
    $revisado = Carbon::now()->startOfWeek()->addDays(2);

    // revisado esta semana (updated_at > created_at)
    Flashcard::factory()->create([
        'created_at' => $passado,
        'updated_at' => $revisado,
    ]);

    // criado mas não revisado (updated_at == created_at, fora da semana)
    Flashcard::factory()->create([
        'created_at' => $passado,
        'updated_at' => $passado,
    ]);

    $progresso = app(MetaService::class)->progressoSemana();

    expect($progresso['flashcards']['atual'])->toBe(1);
});

// M5 — conta apenas gerações com status=ok na semana atual
it('conta gerações ok na semana atual e ignora rejeitadas', function () {
    Geracao::factory()->create([
        'status' => 'ok',
        'created_at' => Carbon::now()->startOfWeek()->addDay(),
    ]);
    Geracao::factory()->create([
        'status' => 'rejeitado',
        'created_at' => Carbon::now()->startOfWeek()->addDay(),
    ]);

    $progresso = app(MetaService::class)->progressoSemana();

    expect($progresso['geracoes']['atual'])->toBe(1);
});

// M6 — salvarMetas persiste settings e progressoSemana reflete as metas salvas
it('salvarMetas persiste metas e progressoSemana as recupera', function () {
    $service = app(MetaService::class);
    $service->salvarMetas(5, 20, 10);

    $progresso = $service->progressoSemana();

    expect($progresso['simulados']['meta'])->toBe(5)
        ->and($progresso['flashcards']['meta'])->toBe(20)
        ->and($progresso['geracoes']['meta'])->toBe(10);
});

// M7 — componente Livewire renderiza e salvar via form atualiza settings
it('componente Metas renderiza barras de progresso e salva metas via form', function () {
    Setting::set('meta_simulados', '3');
    Setting::set('meta_flashcards', '15');
    Setting::set('meta_geracoes', '5');

    Livewire::test(Metas::class)
        ->assertSet('metaSimulados', 3)
        ->assertSet('metaFlashcards', 15)
        ->assertSet('metaGeracoes', 5)
        ->set('metaSimulados', 7)
        ->call('salvar')
        ->assertSet('salvo', true);

    expect(Setting::get('meta_simulados'))->toBe('7');
});
