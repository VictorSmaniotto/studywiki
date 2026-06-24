<?php

namespace App\Services\AI;

use App\Models\TokenUsageLog;

class TokenUsageLogger
{
    // Preços claude-sonnet-4-6 por token (USD por 1M tokens)
    private const INPUT_USD_PER_MTOK = 3.0;

    private const OUTPUT_USD_PER_MTOK = 15.0;

    private const CACHE_WRITE_USD_PER_MTOK = 3.75;

    private const CACHE_READ_USD_PER_MTOK = 0.30;

    public function calcularCusto(
        int $inputTokens,
        int $outputTokens,
        int $cacheWriteTokens = 0,
        int $cacheReadTokens = 0,
    ): float {
        return ($inputTokens * self::INPUT_USD_PER_MTOK
            + $outputTokens * self::OUTPUT_USD_PER_MTOK
            + $cacheWriteTokens * self::CACHE_WRITE_USD_PER_MTOK
            + $cacheReadTokens * self::CACHE_READ_USD_PER_MTOK
        ) / 1_000_000;
    }

    public function log(
        int $inputTokens,
        int $outputTokens,
        string $origem,
        int $cacheWriteTokens = 0,
        int $cacheReadTokens = 0,
    ): TokenUsageLog {
        return TokenUsageLog::create([
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_write_tokens' => $cacheWriteTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'custo_estimado_usd' => $this->calcularCusto($inputTokens, $outputTokens, $cacheWriteTokens, $cacheReadTokens),
            'origem' => $origem,
        ]);
    }

    public function gastoAcumulado(): float
    {
        return (float) TokenUsageLog::sum('custo_estimado_usd');
    }

    public function orcamento(): float
    {
        return (float) config('studywiki.budget_usd', 3.25);
    }

    public function alertaThreshold(): float
    {
        return (float) config('studywiki.budget_alert_usd', 0.50);
    }

    public function saldoRestante(): float
    {
        return max(0, $this->orcamento() - $this->gastoAcumulado());
    }

    public function emAlerta(): bool
    {
        return $this->saldoRestante() < $this->alertaThreshold();
    }
}
