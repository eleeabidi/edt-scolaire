<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email envoyé au directeur quand l'Admin refuse son inscription.
 */
class InscriptionRefuseeNotification extends Notification
{
    public function __construct(public ?string $motif = null) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Votre inscription sur EDT Scolaire')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line("Nous sommes désolés : votre inscription pour l'école « {$notifiable->ecole?->nom} » n'a pas été acceptée.");

        if ($this->motif) {
            $message->line("Motif : {$this->motif}");
        }

        return $message->line('Pour toute question, vous pouvez répondre à cet email.');
    }
}
