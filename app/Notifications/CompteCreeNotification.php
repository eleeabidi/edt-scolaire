<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email envoyé quand l'Admin ou un Directeur crée un compte :
 * il contient le mot de passe temporaire.
 */
class CompteCreeNotification extends Notification
{
    public function __construct(public string $motDePasseTemporaire) {}

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
            ->subject('Votre compte EDT Scolaire a été créé')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Un compte EDT Scolaire vient d\'être créé pour vous.')
            ->line("Email : {$notifiable->email}")
            ->line("Mot de passe temporaire : {$this->motDePasseTemporaire}")
            ->action('Se connecter', route('login'))
            ->line('Pour votre sécurité, changez ce mot de passe dès votre première connexion, depuis la page « Mon profil ».');
    }
}
