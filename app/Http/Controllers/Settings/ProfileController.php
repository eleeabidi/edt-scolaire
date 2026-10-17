<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mon profil » : commun aux trois acteurs (Admin, Directeur, Enseignant).
 */
class ProfileController extends Controller
{
    /**
     * Affiche la page Mon profil.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('profil/Edit', [
            'role' => $user->role->label(),
            'ecole' => $user->ecole?->only(['nom', 'adresse', 'ville', 'telephone']),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Modifie les informations personnelles.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Vos informations ont été mises à jour.']);

        return to_route('profile.edit');
    }

    /**
     * Change le mot de passe (l'ancien mot de passe est demandé).
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Votre mot de passe a été modifié.']);

        return to_route('profile.edit');
    }
}
