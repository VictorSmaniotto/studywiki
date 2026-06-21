<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TemaFactory extends Factory
{
    public function definition(): array
    {
        $nome = $this->faker->unique()->words(2, true);

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome),
        ];
    }
}
