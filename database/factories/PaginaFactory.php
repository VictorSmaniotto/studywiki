<?php

namespace Database\Factories;

use App\Models\Disciplina;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pagina>
 */
class PaginaFactory extends Factory
{
    public function definition(): array
    {
        $titulo = $this->faker->sentence(3);

        return [
            'disciplina_id' => Disciplina::factory(),
            'tipo' => $this->faker->randomElement(['disciplina', 'conceito', 'autor', 'fonte', 'sintese']),
            'titulo' => $titulo,
            'slug' => str($titulo)->slug(),
            'path_relativo' => $this->faker->unique()->filePath(),
            'frontmatter' => [],
            'corpo' => $this->faker->paragraphs(3, true),
            'hash' => hash('sha256', $this->faker->text()),
            'atualizado_na_vault' => now(),
        ];
    }
}
