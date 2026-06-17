<?php

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Geracao;
use App\Models\GeracaoFonte;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeracaoFonte>
 */
class GeracaoFonteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'geracao_id' => Geracao::factory(),
            'pagina_id' => Pagina::factory(),
            'chunk_id' => null,
        ];
    }

    public function comChunk(): static
    {
        return $this->state(fn (array $attributes) => [
            'chunk_id' => Chunk::factory()->state(['pagina_id' => $attributes['pagina_id']]),
        ]);
    }
}
