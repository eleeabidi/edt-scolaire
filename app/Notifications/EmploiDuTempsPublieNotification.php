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
            ->line("Le directeur de l'école {$this->emploi->ecole->nom} vient de publier l'emploi du temps de l'année scolaire {$this->emploi->annee_scolaire}.")
            ->line('Vous pouvez consulter vos séances de la semaine dans « Mon emploi du temps ».')
            ->action('Voir mon emploi du temps', route('mon-emploi.index'));
    }
}
