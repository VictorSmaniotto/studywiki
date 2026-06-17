<?php

namespace Database\Factories;

use App\Models\Geracao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Geracao>
 */
class GeracaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tipo' => $this->faker->randomElement(['resumo', 'flashcards', 'simulado']),
            'escopo' => ['disciplina' => $this->faker->word()],
            'status' => $this->faker->randomElement(['ok', 'rejeitado']),
            'payload' => [],
            'custo_tokens' => $this->faker->numberBetween(100, 5000),
            'modelo' => 'claude-sonnet-4-6',
            'regeneracoes' => 0,
        ];
    }
}
