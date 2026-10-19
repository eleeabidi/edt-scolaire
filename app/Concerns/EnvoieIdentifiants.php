<?php

namespace App\Concerns;

use App\Models\User;
use App\Notifications\CompteCreeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

/**
 * Envoi des identifiants (mot de passe temporaire) d'un compte créé par l'Admin ou un directeur.
 * Ajouter un enseignant 6a : si l'email échoue, le compte reste créé, l'échec est signalé
 * et le système propose de renvoyer l'email.
 */
trait EnvoieIdentifiants
{
    protected function genererMotDePasseTemporaire(): string
    {
        return Str::password(10, symbols: false); // lettres et chiffres
    }

    /**
     * Envoie l'email ; renvoie false si l'envoi a échoué.
     */
    protected function envoyerIdentifiants(User $compte, string $motDePasse): bool
    {
        try {
            $compte->notify(new CompteCreeNotification($motDePasse));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Réponse après la création d'un compte, selon que l'email est parti ou non.
     */
    protected function reponseApresCreation(User $compte, bool $emailEnvoye, string $messageSucces): RedirectResponse
    {
        if ($emailEnvoye) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $messageSucces]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => "Le compte a été créé, mais l'email n'a pas pu être envoyé."]);

        return back()->with('echecEnvoi', ['id' => $compte->id, 'nom' => $compte->name]);
    }

    /**
     * Renvoyer l'email : un nouveau mot de passe temporaire est généré et envoyé.
     */
    protected function renvoyer(User $compte): RedirectResponse
    {
        $motDePasse = $this->genererMotDePasseTemporaire();
        $compte->update(['password' => $motDePasse]);

        return $this->reponseApresCreation(
            $compte,
            $this->envoyerIdentifiants($compte, $motDePasse),
            "L'email avec le nouveau mot de passe temporaire a été envoyé.",
        );
    }
}
