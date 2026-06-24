<?php

use App\Filament\Widgets\TokenBudgetWidget;
use App\Models\TokenUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    $this->actingAs($user);
});

it('exibe gasto acumulado e orçamento no widget', function () {
    config(['studywiki.budget_usd' => 3.25]);

    TokenUsageLog::create([
        'input_tokens' => 1000,
        'output_tokens' => 500,
        'cache_write_tokens' => 0,
        'cache_read_tokens' => 0,
        'custo_estimado_usd' => 0.01050,
        'origem' => 'geracao',
    ]);

    Livewire::test(TokenBudgetWidget::class)
        ->assertSee('0.0105')
        ->assertSee('3.25');
});

it('widget exibe cor danger quando saldo abaixo do threshold', function () {
    config(['studywiki.budget_usd' => 0.5, 'studywiki.budget_alert_usd' => 0.3]);

    TokenUsageLog::create([
        'input_tokens' => 0,
        'output_tokens' => 0,
        'cache_write_tokens' => 0,
        'cache_read_tokens' => 0,
        'custo_estimado_usd' => 0.25,
        'origem' => 'geracao',
    ]);

    Livewire::test(TokenBudgetWidget::class)
        ->assertSee('Abaixo do limite de alerta!');
});
