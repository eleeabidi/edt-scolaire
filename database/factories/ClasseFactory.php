<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\Ecole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classe>
 */
class ClasseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $annee = fake()->numberBetween(1, 6);

        return [
            'ecole_id' => Ecole::factory(),
            'nom' => $annee.'e '.fake()->unique()->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
            'niveau' => $annee === 1 ? '1re année' : "{$annee}e année",
            'effectif' => fake()->numberBetween(20, 32),
        ];
    }
}
