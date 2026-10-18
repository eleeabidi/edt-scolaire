<?php

namespace Database\Factories;

use App\Enums\TypeSalle;
use App\Models\Ecole;
use App\Models\Salle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salle>
 */
class SalleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ecole_id' => Ecole::factory(),
            'nom' => 'Salle '.fake()->unique()->numberBetween(1, 99),
            'type' => TypeSalle::Normale,
            'capacite' => fake()->randomElement([25, 30, 35]),
        ];
    }

    /**
     * Une salle de sport.
     */
    public function sport(): static
    {
        return $this->state(fn (array $attributes) => [
            'nom' => 'Salle de sport',
            'type' => TypeSalle::Sport,
            'capacite' => 60,
        ]);
    }
}
