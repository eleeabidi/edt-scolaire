<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;

/**
 * Réinitialiser le mot de passe, scénario 4a : l'email ne correspond à aucun compte.
 * Le système affiche le même message de confirmation qu'en cas de succès, mais n'envoie
 * rien, pour ne pas révéler quels comptes existent. Les autres échecs (trop de demandes)
 * restent affichés comme des erreurs.
 */
class FailedPasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponseContract
{
    public function __construct(protected string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        if ($this->status === Password::INVALID_USER) {
            return back()->with('status', trans(Password::RESET_LINK_SENT));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($this->status)]);
    }
}
