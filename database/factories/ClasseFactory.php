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
        $niveau = fake()->numberBetween(1, 6);

        return [
            'ecole_id' => Ecole::factory(),
            'nom' => ($niveau === 1 ? '1re' : "{$niveau}e").' année '.fake()->unique()->bothify('?#'),
            'niveau' => $niveau,
            'effectif' => fake()->numberBetween(24, 32),
            'cours_samedi' => false,
        ];
    }

    /**
     * Une classe d'un niveau donné.
     */
    public function niveau(int $niveau): static
    {
        return $this->state(fn (array $attributes) => [
            'niveau' => $niveau,
            'nom' => ($niveau === 1 ? '1re' : "{$niveau}e").' année '.fake()->unique()->bothify('?#'),
        ]);
    }
}
