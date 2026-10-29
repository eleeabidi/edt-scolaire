<?php

namespace Database\Factories;

use App\Enums\StatutEmploi;
use App\Models\Ecole;
use App\Models\EmploiDuTemps;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploiDuTemps>
 */
class EmploiDuTempsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ecole_id' => Ecole::factory(),
            'annee_scolaire' => '2026-2027',
            'statut' => StatutEmploi::Brouillon,
        ];
    }

    /**
     * Un emploi du temps publié.
     */
    public function publie(): static
    {
        return $this->state(fn (array $attributes) => ['statut' => StatutEmploi::Publie]);
    }
}
