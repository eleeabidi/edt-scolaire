<?php

namespace Database\Seeders;

use App\Enums\StatutCompte;
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
    }
}
