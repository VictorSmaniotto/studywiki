<?php

use App\Filament\Widgets\GeracaoStatsWidget;
use App\Models\Geracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('exibe taxa de rejeição zerada quando não há gerações', function () {
    Livewire::test(GeracaoStatsWidget::class)
        ->assertSee('0%');
});

it('calcula e exibe taxa de rejeição corretamente', function () {
    Geracao::factory()->count(3)->create(['status' => 'ok', 'payload' => []]);
    Geracao::factory()->count(1)->create(['status' => 'rejeitado', 'payload' => []]);

    Livewire::test(GeracaoStatsWidget::class)
        ->assertSee('25%')
        ->assertSee('1 rejeitadas de 4');
});

it('exibe total de gerações', function () {
    Geracao::factory()->count(5)->create(['status' => 'ok', 'payload' => []]);

    Livewire::test(GeracaoStatsWidget::class)
        ->assertSee('5');
});
