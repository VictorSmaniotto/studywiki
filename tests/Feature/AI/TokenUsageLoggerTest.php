<?php

use App\Models\TokenUsageLog;
use App\Services\AI\TokenUsageLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calcula custo de input tokens corretamente', function () {
    $logger = new TokenUsageLogger;

    // 1M input tokens = $3.00
    $custo = $logger->calcularCusto(1_000_000, 0);

    expect(round($custo, 4))->toBe(3.0);
});

it('calcula custo de output tokens corretamente', function () {
    $logger = new TokenUsageLogger;

    // 1M output tokens = $15.00
    $custo = $logger->calcularCusto(0, 1_000_000);

    expect(round($custo, 4))->toBe(15.0);
});

it('calcula custo de cache tokens corretamente', function () {
    $logger = new TokenUsageLogger;

    // 1M cache_write = $3.75, 1M cache_read = $0.30 → total $4.05
    $custo = $logger->calcularCusto(0, 0, 1_000_000, 1_000_000);

    expect(round($custo, 2))->toBe(4.05);
});

it('persiste log no banco com custo calculado', function () {
    $logger = new TokenUsageLogger;

    $log = $logger->log(1000, 500, 'geracao', 200, 100);

    expect(TokenUsageLog::count())->toBe(1);
    expect($log->input_tokens)->toBe(1000);
    expect($log->output_tokens)->toBe(500);
    expect($log->cache_write_tokens)->toBe(200);
    expect($log->cache_read_tokens)->toBe(100);
    expect($log->origem)->toBe('geracao');
    expect($log->custo_estimado_usd)->toBeGreaterThan(0.0);
});

it('gastoAcumulado soma todos os registros', function () {
    TokenUsageLog::create([
        'input_tokens' => 100,
        'output_tokens' => 50,
        'cache_write_tokens' => 0,
        'cache_read_tokens' => 0,
        'custo_estimado_usd' => 0.001,
        'origem' => 'geracao',
    ]);
    TokenUsageLog::create([
        'input_tokens' => 200,
        'output_tokens' => 100,
        'cache_write_tokens' => 0,
        'cache_read_tokens' => 0,
        'custo_estimado_usd' => 0.002,
        'origem' => 'chat',
    ]);

    $logger = new TokenUsageLogger;

    expect(round($logger->gastoAcumulado(), 6))->toBe(0.003);
});

it('emAlerta retorna true quando saldo < threshold', function () {
    config(['studywiki.budget_usd' => 0.5, 'studywiki.budget_alert_usd' => 0.3]);

    TokenUsageLog::create([
        'input_tokens' => 0,
        'output_tokens' => 0,
        'cache_write_tokens' => 0,
        'cache_read_tokens' => 0,
        'custo_estimado_usd' => 0.25, // saldo = 0.25 < threshold 0.30
        'origem' => 'geracao',
    ]);

    $logger = new TokenUsageLogger;

    expect($logger->emAlerta())->toBeTrue();
});
