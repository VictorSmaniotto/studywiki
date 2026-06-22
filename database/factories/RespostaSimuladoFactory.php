<?php

namespace Database\Factories;

use App\Models\Geracao;
use Illuminate\Database\Eloquent\Factories\Factory;

class RespostaSimuladoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'geracao_id' => Geracao::factory(),
            'respostas' => [],
            'acertos' => 0,
            'total' => 5,
            'respostas_dissertativas' => [],
            'notas_dissertativas' => [],
            'tempo_realizado_segundos' => null,
        ];
    }
}
