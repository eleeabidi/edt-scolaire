<?php

namespace App\Actions\Fortify;

use App\Concerns\EcoleValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Ecole;
use App\Models\User;
use App\Notifications\InscriptionRecueNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Throwable;

/**
 * Inscription d'un directeur : création de son école et de son compte
 * au statut EN_ATTENTE (l'Admin devra le valider).
 */
class CreateNewUser implements CreatesNewUsers
{
    use EcoleValidationRules, PasswordValidationRules, ProfileValidationRules;

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
            // Étape 2 : l'école (refusée si une école du même nom existe dans la même ville)
            ...$this->ecoleRules($input['ecole_ville'] ?? null),
        ])->validate();

        // Les deux enregistrements sont créés ensemble, ou pas du tout
        $directeur = DB::transaction(function () use ($input) {
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

        // Étape 7 : email de confirmation (une panne d'email ne bloque pas l'inscription)
        try {
            $directeur->notify(new InscriptionRecueNotification);
        } catch (Throwable $e) {
            report($e);
        }

        return $directeur;
    }
}
