<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email envoyé au directeur quand l'Admin valide son inscription.
 */
class InscriptionValideeNotification extends Notification
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
            ->subject('Votre inscription sur EDT Scolaire est validée')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("Bonne nouvelle : votre inscription pour l'école « {$notifiable->ecole?->nom} » a été validée.")
            ->line('Vous pouvez maintenant vous connecter avec votre email et le mot de passe choisi lors de l\'inscription.')
            ->action('Se connecter', route('login'));
    }
}
