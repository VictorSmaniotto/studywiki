<?php

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pagina_id' => Pagina::factory(),
            'ordem' => $this->faker->numberBetween(0, 10),
            'conteudo' => $this->faker->paragraphs(2, true),
            'heading_path' => $this->faker->words(3, true),
            'tokens' => $this->faker->numberBetween(50, 512),
            'embedding' => null,
            'embedding_model' => null,
        ];
    }
}
