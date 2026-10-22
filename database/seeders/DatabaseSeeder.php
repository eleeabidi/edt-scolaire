<?php

namespace Database\Seeders;

use App\Enums\StatutCompte;
use App\Enums\TypeSalle;
use App\Models\Ecole;
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
        // Sprint 2 : programme et règles nationales (communs à toutes les écoles)
        $this->call([ProgrammeNationalSeeder::class, ReglesNationalesSeeder::class]);

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

        User::factory()->enseignant($ecole)->create([
            'nom' => 'Trabelsi',
            'prenom' => 'Amira',
            'email' => 'enseignant@edt-scolaire.test',
        ]);

        User::factory(5)->enseignant($ecole)->create();

        // Deux inscriptions de directeurs en attente de validation
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->sallesEtClasses($ecole);
    }

    /**
     * Sprint 2 : salles, classes et groupes de l'école de démonstration.
     */
    private function sallesEtClasses(Ecole $ecole): void
    {
        foreach (['Salle 1' => 32, 'Salle 2' => 30, 'Salle 3' => 30, 'Salle 4' => 28] as $nom => $capacite) {
            $ecole->salles()->create(['nom' => $nom, 'type' => TypeSalle::Normale, 'capacite' => $capacite]);
        }
        $ecole->salles()->create(['nom' => 'Salle de sport', 'type' => TypeSalle::Sport, 'capacite' => 60]);

        // [nom, niveau, effectif, cours le samedi (choisi seulement en 3e année)]
        $classes = [
            ['1re année A', 1, 28, false],
            ['1re année B', 1, 27, false],
            ['2e année A', 2, 30, false],
            ['3e année A', 3, 32, true],
            ['3e année B', 3, 31, false],
            ['4e année A', 4, 30, true],
            ['5e année A', 5, 29, true],
            ['6e année A', 6, 30, true],
        ];

        foreach ($classes as [$nom, $niveau, $effectif, $coursSamedi]) {
            $ecole->classes()->create([
                'nom' => $nom,
                'niveau' => $niveau,
                'effectif' => $effectif,
                'cours_samedi' => $coursSamedi,
            ]);
        }

        $ecole->classes()->where('nom', '3e année A')->first()->groupes()->createMany([
            ['nom' => 'Groupe 1', 'effectif' => 16],
            ['nom' => 'Groupe 2', 'effectif' => 16],
        ]);
    }
}
