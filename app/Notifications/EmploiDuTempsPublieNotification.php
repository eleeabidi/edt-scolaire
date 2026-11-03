<?php

namespace App\Notifications;

use App\Models\EmploiDuTemps;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * T17.4 — Email envoyé à chaque enseignant de l'école quand le directeur publie l'emploi du temps.
 */
class EmploiDuTempsPublieNotification extends Notification
{
    public function __construct(public EmploiDuTemps $emploi) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("L'emploi du temps {$this->emploi->annee_scolaire} est publié")
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line(self::message($this->emploi))
            ->action('Voir mon emploi du temps', route('mon-emploi.index'));
    }

    /**
     * Sprint 4 (maquette 28) : « L'emploi du temps 2026-2027 de l'école Ibn Khaldoun est publié. Vous pouvez consulter vos séances. »
     * Le mot « École » au début du nom n'est pas répété (« de l'école Les Jasmins »).
     */
    public static function message(EmploiDuTemps $emploi): string
    {
        $ecole = preg_replace('/^École\s+/u', '', $emploi->ecole->nom);

        return "L'emploi du temps {$emploi->annee_scolaire} de l'école {$ecole} est publié. Vous pouvez consulter vos séances.";
    }
}
