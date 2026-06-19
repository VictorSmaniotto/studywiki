<?php

use App\Filament\Pages\DesempenhoDashboard;
use App\Filament\Widgets\DesempenhoGlobalWidget;
use App\Models\Geracao;
use App\Models\RespostaSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renderiza o dashboard sem dados com mensagem vazia', function () {
    Livewire::test(DesempenhoDashboard::class)
        ->assertSee('Nenhuma geração registrada ainda.');
});

it('exibe tokens e gerações por disciplina', function () {
    Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'compiladores'],
        'custo_tokens' => 1000,
        'payload' => [],
    ]);
    Geracao::factory()->create([
        'tipo' => 'flashcards',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'compiladores'],
        'custo_tokens' => 500,
        'payload' => [],
    ]);

    $component = Livewire::test(DesempenhoDashboard::class);
    $component->assertSee('compiladores');

    $dados = $component->instance()->getDadosPorDisciplina();
    $comp = collect($dados)->firstWhere('disciplina', 'compiladores');

    expect($comp['total_tokens'])->toBe(1500)
        ->and($comp['resumos'])->toBe(1)
        ->and($comp['flashcards'])->toBe(1)
        ->and($comp['simulados'])->toBe(0)
        ->and($comp['taxa_rejeicao'])->toBe(0.0);
});

it('calcula taxa de rejeição por disciplina', function () {
    Geracao::factory()->count(3)->create([
        'status' => 'ok',
        'escopo' => ['disciplina' => 'redes'],
        'payload' => [],
        'custo_tokens' => 100,
    ]);
    Geracao::factory()->count(1)->create([
        'status' => 'rejeitado',
        'escopo' => ['disciplina' => 'redes'],
        'payload' => [],
        'custo_tokens' => 100,
    ]);

    $dados = Livewire::test(DesempenhoDashboard::class)
        ->instance()
        ->getDadosPorDisciplina();

    $redes = collect($dados)->firstWhere('disciplina', 'redes');

    expect($redes['taxa_rejeicao'])->toBe(25.0)
        ->and($redes['rejeitadas'])->toBe(1)
        ->and($redes['total_geracoes'])->toBe(4);
});

it('exibe desempenho médio nos simulados por disciplina', function () {
    $g1 = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'banco-de-dados'],
        'payload' => [],
        'custo_tokens' => 800,
    ]);
    $g2 = Geracao::factory()->create([
        'tipo' => 'simulado',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'banco-de-dados'],
        'payload' => [],
        'custo_tokens' => 800,
    ]);

    RespostaSimulado::create(['geracao_id' => $g1->id, 'respostas' => [], 'acertos' => 8, 'total' => 10]);
    RespostaSimulado::create(['geracao_id' => $g2->id, 'respostas' => [], 'acertos' => 6, 'total' => 10]);

    $dados = Livewire::test(DesempenhoDashboard::class)
        ->instance()
        ->getDadosPorDisciplina();

    $bd = collect($dados)->firstWhere('disciplina', 'banco-de-dados');

    expect($bd['media_acertos_pct'])->toBe(70.0)
        ->and($bd['simulados_respondidos'])->toBe(2);
});

it('disciplina sem simulados respondidos exibe traço no desempenho', function () {
    Geracao::factory()->create([
        'tipo' => 'resumo',
        'status' => 'ok',
        'escopo' => ['disciplina' => 'so'],
        'payload' => [],
        'custo_tokens' => 300,
    ]);

    $dados = Livewire::test(DesempenhoDashboard::class)
        ->instance()
        ->getDadosPorDisciplina();

    $so = collect($dados)->firstWhere('disciplina', 'so');

    expect($so['media_acertos_pct'])->toBeNull()
        ->and($so['simulados_respondidos'])->toBe(0);
});

it('widget de totais globais exibe tokens e taxa de rejeição', function () {
    Geracao::factory()->create([
        'status' => 'ok',
        'escopo' => ['disciplina' => 'a'],
        'custo_tokens' => 1000,
        'payload' => [],
    ]);
    Geracao::factory()->create([
        'status' => 'rejeitado',
        'escopo' => ['disciplina' => 'b'],
        'custo_tokens' => 500,
        'payload' => [],
    ]);

    Livewire::test(DesempenhoGlobalWidget::class)
        ->assertSee('1,500')
        ->assertSee('50%');
});

it('disciplinas são ordenadas por tokens decrescente', function () {
    Geracao::factory()->create([
        'status' => 'ok',
        'escopo' => ['disciplina' => 'menor'],
        'custo_tokens' => 100,
        'payload' => [],
    ]);
    Geracao::factory()->create([
        'status' => 'ok',
        'escopo' => ['disciplina' => 'maior'],
        'custo_tokens' => 9000,
        'payload' => [],
    ]);

    $dados = Livewire::test(DesempenhoDashboard::class)
        ->instance()
        ->getDadosPorDisciplina();

    expect($dados[0]['disciplina'])->toBe('maior')
        ->and($dados[1]['disciplina'])->toBe('menor');
});
