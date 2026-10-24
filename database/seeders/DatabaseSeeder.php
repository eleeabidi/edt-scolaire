<?php

namespace Database\Seeders;

use App\Enums\StatutCompte;
use App\Enums\TypeSalle;
use App\Models\Classe;
use App\Models\Ecole;
use App\Models\Matiere;
use App\Models\NiveauMatiere;
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

        $amira = User::factory()->enseignant($ecole)->create([
            'nom' => 'Trabelsi',
            'prenom' => 'Amira',
            'email' => 'enseignant@edt-scolaire.test',
        ]);

        $enseignants = User::factory(5)->enseignant($ecole)->create();

        // Deux inscriptions de directeurs en attente de validation
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->sallesEtClasses($ecole);
        $this->affectations($ecole, $amira, $enseignants->all());
    }

    /**
     * Sprint 2 : affectations de démonstration, selon l'organisation du backlog.
     *
     * @param  array<int, User>  $enseignants
     */
    private function affectations(Ecole $ecole, User $amira, array $enseignants): void
    {
        $classe = fn (string $nom) => $ecole->classes()->where('nom', $nom)->firstOrFail();
        $matiere = fn (string $nom) => Matiere::where('nom', $nom)->firstOrFail();

        $affecter = function (User $enseignant, Classe $classe, array $sauf = []) {
            $niveau = NiveauMatiere::where('niveau', $classe->niveau)->with('matiere')->get();

            foreach ($niveau as $volume) {
                if (! in_array($volume->matiere->nom, $sauf, true)) {
                    $enseignant->affectations()->create(['classe_id' => $classe->id, 'matiere_id' => $volume->matiere_id]);
                }
            }
        };

        // 1re année A : un enseignant principal pour tout sauf l'éducation physique (19 h)
        $amira->update(['volume_horaire_max' => 19]);
        $affecter($amira, $classe('1re année A'), ['Éducation physique']);

        // 2e année A : un principal (tout sauf le français et l'éducation physique, 19 h)…
        [$eps, $principal2e, $francais] = $enseignants;
        $principal2e->update(['volume_horaire_max' => 19]);
        $affecter($principal2e, $classe('2e année A'), ['Français', 'Éducation physique']);

        // … un enseignant de français (2 h en 2e année, 8 h en 3e année A et B = 18 h)
        foreach (['2e année A', '3e année A', '3e année B'] as $nom) {
            $francais->affectations()->create(['classe_id' => $classe($nom)->id, 'matiere_id' => $matiere('Français')->id]);
        }

        // L'éducation physique a toujours son propre enseignant (1 h par classe)
        foreach ($ecole->classes as $c) {
            $eps->affectations()->create(['classe_id' => $c->id, 'matiere_id' => $matiere('Éducation physique')->id]);
        }
    }

    /**
     * Sprint 2 : salles et classes de l'école de démonstration.
     */
    private function sallesEtClasses(Ecole $ecole): void
    {
        foreach (['Salle 1' => 32, 'Salle 2' => 30, 'Salle 3' => 30, 'Salle 4' => 28] as $nom => $capacite) {
            $ecole->salles()->create(['nom' => $nom, 'type' => TypeSalle::Cours, 'capacite' => $capacite]);
        }
        $ecole->salles()->create(['nom' => 'Salle de sport', 'type' => TypeSalle::Sport, 'capacite' => 60]);
        $ecole->salles()->create(['nom' => 'Salle informatique', 'type' => TypeSalle::Technologie, 'capacite' => 30]);

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
    }
}
