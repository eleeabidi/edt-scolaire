<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\Groupe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Groupe>
 */
class GroupeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'nom' => 'Groupe '.fake()->unique()->numberBetween(1, 999),
            'effectif' => fake()->numberBetween(10, 16),
        ];
    }
}
