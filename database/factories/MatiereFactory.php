<?php

namespace Database\Factories;

use App\Enums\TypeSalle;
use App\Models\Ecole;
use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matiere>
 */
class MatiereFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ecole_id' => Ecole::factory(),
            'nom' => fake()->unique()->randomElement(['Mathématiques', 'Français', 'Arabe', 'Anglais', 'Sciences', 'Histoire-Géographie', 'Éducation islamique', 'Éducation civique', 'Dessin', 'Musique', 'Informatique']),
            'duree_seance' => fake()->randomElement([45, 60]),
            'type_salle' => TypeSalle::Normale,
        ];
    }

    /**
     * Une matière qui doit être placée dans une salle de sport.
     */
    public function sport(): static
    {
        return $this->state(fn (array $attributes) => [
            'nom' => 'Éducation physique',
            'type_salle' => TypeSalle::Sport,
        ]);
    }
}
