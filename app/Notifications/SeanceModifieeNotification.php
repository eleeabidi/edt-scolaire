<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * T17.4 — Email envoyé à l'enseignant d'une séance modifiée dans un emploi du temps déjà publié.
 */
class SeanceModifieeNotification extends Notification
{
    /**
     * @param  string  $modification  ex. « Votre séance de Français en 3e année A a été déplacée du lundi 8 h – 9 h 55 au jeudi 13 h – 14 h 55. »
     */
    public function __construct(public string $modification) {}

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
            ->subject('Une de vos séances a été modifiée')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Le directeur a modifié l\'emploi du temps publié.')
            ->line($this->modification)
            ->action('Voir mon emploi du temps', route('mon-emploi.index'));
    }
}
