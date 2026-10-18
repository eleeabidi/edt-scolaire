<?php

namespace Database\Factories;

use App\Enums\Jour;
use App\Models\Disponibilite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disponibilite>
 */
class DisponibiliteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enseignant_id' => User::factory()->enseignant(),
            'jour' => fake()->randomElement(Jour::cases()),
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
        ];
    }
}
