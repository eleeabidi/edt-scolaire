<?php

namespace Database\Factories;

use App\Enums\Jour;
use App\Models\Ecole;
use App\Models\Horaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horaire>
 */
class HoraireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $heure = fake()->unique()->numberBetween(8, 16);

        return [
            'ecole_id' => Ecole::factory(),
            'jour' => fake()->randomElement(Jour::cases()),
            'heure_debut' => sprintf('%02d:00', $heure),
            'heure_fin' => sprintf('%02d:00', $heure + 1),
        ];
    }
}
