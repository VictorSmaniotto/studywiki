<?php

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\Geracao;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Flashcard>
 */
class FlashcardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'geracao_id' => Geracao::factory(),
            'frente' => $this->faker->sentence(4),
            'verso' => $this->faker->sentence(8),
            'fontes' => [['pagina_id' => 1, 'chunk_id' => 1]],
            'proxima_revisao' => Carbon::today()->toDateString(),
            'intervalo' => 1,
            'facilidade' => 2.50,
            'repeticoes' => 0,
        ];
    }
}
