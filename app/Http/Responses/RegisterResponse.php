<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * Après l'inscription, Fortify connecte automatiquement l'utilisateur.
 * Ici, le compte est EN_ATTENTE : on le déconnecte et on revient sur
 * l'inscription, qui affiche la fenêtre « Inscription envoyée ».
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register')->with('inscriptionEnvoyee', true);
    }
}
