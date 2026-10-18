<?php

namespace Database\Seeders;

use App\Enums\Jour;
use App\Enums\StatutCompte;
use App\Enums\TypeSalle;
use App\Models\Classe;
use App\Models\Ecole;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Tous les comptes de démonstration ont le mot de passe « password1 ».
     */
    public function run(): void
    {
        // L'administrateur de la plateforme
        User::factory()->admin()->create([
            'nom' => 'Admin',
            'prenom' => 'Super',
            'email' => 'admin@edt-scolaire.test',
        ]);

        // Une école de démonstration avec son directeur et ses enseignants
        $ecole = Ecole::factory()->create(['nom' => 'École Les Jasmins']);

        User::factory()->directeur()->create([
            'nom' => 'Ben Ali',
            'prenom' => 'Sami',
            'email' => 'directeur@edt-scolaire.test',
            'ecole_id' => $ecole->id,
        ]);

        $amira = User::factory()->enseignant($ecole)->create([
            'nom' => 'Trabelsi',
            'prenom' => 'Amira',
            'email' => 'enseignant@edt-scolaire.test',
            'specialite' => 'Mathématiques',
            'volume_horaire_max' => 20,
        ]);

        User::factory(5)->enseignant($ecole)->create();

        // Deux inscriptions de directeurs en attente de validation
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->donneesDeBase($ecole, $amira);
    }

    /**
     * Sprint 2 : salles, classes, matières et disponibilités de l'école de démonstration.
     */
    private function donneesDeBase(Ecole $ecole, User $amira): void
    {
        foreach (['Salle 1', 'Salle 2', 'Salle 3'] as $nom) {
            Salle::create(['ecole_id' => $ecole->id, 'nom' => $nom, 'type' => TypeSalle::Normale, 'capacite' => 30]);
        }
        Salle::create(['ecole_id' => $ecole->id, 'nom' => 'Salle de sport', 'type' => TypeSalle::Sport, 'capacite' => 60]);

        $matieres = collect([
            ['nom' => 'Mathématiques', 'volume' => 5],
            ['nom' => 'Français', 'volume' => 4],
            ['nom' => 'Arabe', 'volume' => 6],
            ['nom' => 'Sciences', 'volume' => 2],
            ['nom' => 'Éducation physique', 'volume' => 2, 'type_salle' => TypeSalle::Sport],
        ])->map(fn (array $m) => [
            'matiere' => Matiere::create([
                'ecole_id' => $ecole->id,
                'nom' => $m['nom'],
                'duree_seance' => 60,
                'type_salle' => $m['type_salle'] ?? TypeSalle::Normale,
            ]),
            'volume' => $m['volume'],
        ]);

        foreach ([['1re A', '1re année'], ['2e A', '2e année'], ['3e A', '3e année']] as [$nom, $niveau]) {
            $classe = Classe::create(['ecole_id' => $ecole->id, 'nom' => $nom, 'niveau' => $niveau, 'effectif' => 26]);

            $classe->groupes()->createMany([
                ['nom' => 'Groupe 1', 'effectif' => 13],
                ['nom' => 'Groupe 2', 'effectif' => 13],
            ]);

            foreach ($matieres as $m) {
                $classe->matieres()->attach($m['matiere']->id, ['volume_horaire_hebdo' => $m['volume']]);
            }
        }

        // Amira enseigne les mathématiques et les sciences, du lundi au vendredi matin
        $amira->matieres()->attach($matieres->whereIn('matiere.nom', ['Mathématiques', 'Sciences'])->pluck('matiere.id'));

        foreach ([Jour::Lundi, Jour::Mardi, Jour::Mercredi, Jour::Jeudi, Jour::Vendredi] as $jour) {
            $amira->disponibilites()->create(['jour' => $jour, 'heure_debut' => '08:00', 'heure_fin' => '12:00']);
        }
    }
}
