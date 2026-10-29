<?php

namespace Database\Seeders;

use App\Enums\Jour;
use App\Enums\StatutCompte;
use App\Enums\TypeSalle;
use App\Models\Ecole;
use App\Models\Horaire;
use App\Models\NiveauMatiere;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        // Deux inscriptions de directeurs en attente de validation
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->sallesEtClasses($ecole);
        $this->enseignantsEtAffectations($ecole);
    }

    /**
     * Enseignants et affectations de démonstration : toutes les matières de toutes les classes ont
     * un enseignant, selon l'organisation du backlog (sprint 2), pour pouvoir générer l'emploi du temps
     * (sprint 3). Au plus 18 h par enseignant, 19 h pour les enseignants principaux de 1re et 2e année.
     */
    private function enseignantsEtAffectations(Ecole $ecole): void
    {
        $enseignant = fn (string $prenom, string $nom, ?string $email = null, int $maximum = 18) => User::factory()->enseignant($ecole)->create([
            'prenom' => $prenom,
            'nom' => $nom,
            'email' => $email ?? Str::of("{$prenom}.{$nom}")->ascii()->lower()->replace(' ', '-').'@edt-scolaire.test',
            'volume_horaire_max' => $maximum,
        ]);

        $classe = fn (string $nom) => $ecole->classes()->where('nom', $nom)->firstOrFail();

        // Affecte à l'enseignant les matières données (ou toutes sauf celles de $sauf)
        $affecter = function (User $enseignant, string $nomClasse, array $matieres = [], array $sauf = []) use ($classe) {
            $c = $classe($nomClasse);

            foreach (NiveauMatiere::where('niveau', $c->niveau)->with('matiere')->get() as $volume) {
                $nom = $volume->matiere->nom;

                if (($matieres === [] || in_array($nom, $matieres, true)) && ! in_array($nom, $sauf, true)) {
                    $enseignant->affectations()->create(['classe_id' => $c->id, 'matiere_id' => $volume->matiere_id]);
                }
            }
        };

        // 1re année : un enseignant principal pour tout sauf l'éducation physique (19 h)
        $affecter($enseignant('Amira', 'Trabelsi', 'enseignant@edt-scolaire.test', 19), '1re année A', sauf: ['Éducation physique']);
        $affecter($enseignant('Hela', 'Mansour', maximum: 19), '1re année B', sauf: ['Éducation physique']);

        // 2e année : un principal (tout sauf le français et l'éducation physique, 19 h) et un enseignant de français
        $affecter($enseignant('Sonia', 'Hammami', maximum: 19), '2e année A', sauf: ['Français', 'Éducation physique']);

        // À partir de la 3e année : un enseignant pour l'arabe, un pour le français, un pour les sciences
        $arabe34 = ['Langue arabe', 'Éducation islamique', 'Éducation musicale', 'Éducation artistique'];
        $arabe56 = ['Langue arabe', 'Éducation islamique', 'Histoire', 'Géographie', 'Éducation civique'];
        $francais = ['Français', 'Anglais'];
        $sciences34 = ['Mathématiques', 'Éveil scientifique', 'Éducation technologique'];
        $sciences56 = [...$sciences34, 'Éducation musicale', 'Éducation artistique'];

        $karim = $enseignant('Karim', 'Jaziri');      // arabe 3e A et B : 18 h
        $nour = $enseignant('Nour', 'Ayari');         // français 3e A et B : 16 h
        $mehdi = $enseignant('Mehdi', 'Bouzid');      // sciences 3e A et B : 14 h
        $rim = $enseignant('Rim', 'Saidi');           // arabe 4e A et 5e A : 18 h
        $leila = $enseignant('Leila', 'Haddad');      // français 4e A et 5e A : 18 h
        $walid = $enseignant('Walid', 'Gharbi');      // sciences 4e A et 5e A : 15 h
        $imen = $enseignant('Imen', 'Chaabane');      // arabe et sciences 6e A : 17 h
        $anis = $enseignant('Anis', 'Jlassi');        // français 6e A et 2e A : 12 h

        foreach (['3e année A', '3e année B'] as $nom) {
            $affecter($karim, $nom, $arabe34);
            $affecter($nour, $nom, $francais);
            $affecter($mehdi, $nom, $sciences34);
        }

        $affecter($rim, '4e année A', $arabe34);
        $affecter($leila, '4e année A', $francais);
        $affecter($walid, '4e année A', $sciences34);
        $affecter($rim, '5e année A', $arabe56);
        $affecter($leila, '5e année A', $francais);
        $affecter($walid, '5e année A', $sciences56);
        $affecter($imen, '6e année A', [...$arabe56, ...$sciences56]);
        $affecter($anis, '6e année A', $francais);
        $affecter($anis, '2e année A', ['Français']);

        // L'éducation physique a toujours son propre enseignant (1 h par classe : 8 h)
        $eps = $enseignant('Youssef', 'Khelifi');
        foreach ($ecole->classes as $c) {
            $affecter($eps, $c->nom, ['Éducation physique']);
        }

        // Une indisponibilité : Nour Ayari n'est pas disponible le mercredi après-midi
        foreach (Horaire::all() as $horaire) {
            $mercrediApresMidi = $horaire->jour === Jour::Mercredi && $horaire->heure_debut >= '13:00';

            if (! $mercrediApresMidi) {
                $nour->disponibilites()->create([
                    'jour' => $horaire->jour,
                    'heure_debut' => $horaire->heure_debut,
                    'heure_fin' => $horaire->heure_fin,
                ]);
            }
        }
    }

    /**
     * Sprint 2 : salles et classes de l'école de démonstration.
     */
    private function sallesEtClasses(Ecole $ecole): void
    {
        foreach (['Salle 1' => 32, 'Salle 2' => 30, 'Salle 3' => 30, 'Salle 4' => 28, 'Salle 5' => 32, 'Salle 6' => 32, 'Salle 7' => 32, 'Salle 8' => 32] as $nom => $capacite) {
            $ecole->salles()->create(['nom' => $nom, 'type' => TypeSalle::Cours, 'capacite' => $capacite]);
        }
        $ecole->salles()->create(['nom' => 'Salle de sport', 'type' => TypeSalle::Sport, 'capacite' => 60]);
        $ecole->salles()->create(['nom' => 'Salle informatique', 'type' => TypeSalle::Technologie, 'capacite' => 32]);

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
