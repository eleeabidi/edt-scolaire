<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US20 — « Mes notifications » (enseignant) : publication de l'emploi du temps ou modification d'une de ses séances (T20.4).
 */
class NotificationController extends Controller
{
    /**
     * Les notifications de l'enseignant, de la plus récente à la plus ancienne. Celles qui n'étaient pas lues
     * sont affichées dans « Nouvelles » puis marquées comme lues : la pastille revient à zéro.
     */
    public function consulterNotifications(Request $request): Response
    {
        $notifications = $request->user()->notificationsRecues()
            ->orderByDesc('date_envoi')
            ->orderByDesc('id')
            ->get();

        $affichage = $notifications->map(fn (Notification $n) => $n->pourAffichage())->values()->all();

        foreach ($notifications as $notification) {
            $notification->marquerCommeLue();
        }

        return Inertia::render('notifications/Index', [
            'notifications' => $affichage,
        ]);
    }
}
