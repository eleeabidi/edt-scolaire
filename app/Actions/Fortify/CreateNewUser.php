<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Ecole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Inscription d'un directeur : création de son école et de son compte
 * au statut EN_ATTENTE (l'Admin devra le valider).
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            // Étape 1 : le directeur
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            // Étape 2 : l'école
            'ecole_nom' => ['required', 'string', 'max:255'],
            'ecole_adresse' => ['required', 'string', 'max:255'],
            'ecole_ville' => ['required', 'string', 'max:255'],
            'ecole_telephone' => ['required', 'string', 'max:20'],
        ], [], [
            'ecole_nom' => "nom de l'école",
            'ecole_adresse' => "adresse de l'école",
            'ecole_ville' => "ville de l'école",
            'ecole_telephone' => "téléphone de l'école",
        ])->validate();

        // Les deux enregistrements sont créés ensemble, ou pas du tout
        return DB::transaction(function () use ($input) {
            $ecole = Ecole::create([
                'nom' => $input['ecole_nom'],
                'adresse' => $input['ecole_adresse'],
                'ville' => $input['ecole_ville'],
                'telephone' => $input['ecole_telephone'],
            ]);

            return User::create([
                'nom' => $input['nom'],
                'prenom' => $input['prenom'],
                'email' => $input['email'],
                'telephone' => $input['telephone'] ?? null,
                'password' => $input['password'],
                'role' => Role::Directeur,
                'statut' => StatutCompte::EnAttente,
                'ecole_id' => $ecole->id,
            ]);
        });
    }
}
