<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * S'inscrire, étape 7 : email de confirmation envoyé au directeur
 * juste après son inscription (compte EN_ATTENTE).
 */
class InscriptionRecueNotification extends Notification
{
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
            ->subject('Votre inscription sur EDT Scolaire a bien été reçue')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("Nous avons bien reçu votre inscription pour l'école « {$notifiable->ecole?->nom} ».")
            ->line("Votre compte sera activé après validation par l'administrateur. Vous recevrez alors un nouvel email.");
    }
}
