<?php

namespace App\Services;

use App\Models\Flashcard;
use Illuminate\Support\Carbon;

class SpacedRepetitionService
{
    private const FACILIDADE_MINIMA = 1.3;

    private const FACILIDADE_DECREMENTO = 0.2;

    /**
     * Aplica o algoritmo SM-2 simplificado ao flashcard.
     *
     * "lembrei" avança o intervalo (1 → 6 → round(n * facilidade)).
     * "esqueci" reseta o intervalo para 1 dia e penaliza a facilidade.
     */
    public function revisar(Flashcard $card, bool $lembrei): void
    {
        if ($lembrei) {
            $card->repeticoes += 1;

            $card->intervalo = match (true) {
                $card->repeticoes === 1 => 1,
                $card->repeticoes === 2 => 6,
                default => (int) round($card->intervalo * $card->facilidade),
            };

            $card->proxima_revisao = Carbon::today()->addDays($card->intervalo);
        } else {
            $card->repeticoes = 0;
            $card->intervalo = 1;
            $card->facilidade = max(
                self::FACILIDADE_MINIMA,
                $card->facilidade - self::FACILIDADE_DECREMENTO
            );
            $card->proxima_revisao = Carbon::today()->addDay();
        }

        $card->save();
    }
}
