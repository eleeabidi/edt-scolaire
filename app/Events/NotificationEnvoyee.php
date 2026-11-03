<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T20.2 — Nouvelle notification diffusée tout de suite (sans file d'attente) sur le canal privé
 * de son enseignant : sa pastille, son emploi du temps et « Mes notifications » se mettent à jour.
 */
class NotificationEnvoyee implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('enseignant.'.$this->notification->enseignant_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.envoyee';
    }

    /**
     * @return array{notification: array<string, mixed>, nonLues: int}
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification->pourAffichage(),
            'nonLues' => Notification::where('enseignant_id', $this->notification->enseignant_id)->where('lue', false)->count(),
        ];
    }
}
