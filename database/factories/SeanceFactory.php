<?php

namespace Database\Factories;

use App\Enums\Jour;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Horaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seance>
 */
class SeanceFactory extends Factory
{
    /**
     * Par défaut : le lundi de 8 h à 9 h 55.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emploi_du_temps_id' => EmploiDuTemps::factory(),
            'classe_id' => Classe::factory(),
            'horaire_id' => fn () => Horaire::firstOrCreate(
                ['jour' => Jour::Lundi, 'heure_debut' => '08:00:00'],
                ['heure_fin' => '09:55:00'],
            )->id,
            'enseignant_id' => User::factory()->enseignant(),
            'salle_id' => Salle::factory(),
        ];
    }
}
