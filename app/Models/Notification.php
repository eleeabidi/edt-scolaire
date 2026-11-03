<?php

namespace App\Models;

use App\Enums\TypeNotification;
use App\Events\NotificationEnvoyee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * Notification reçue par un enseignant (publication de l'emploi du temps ou modification d'une de ses séances).
 * Elle est enregistrée, puis diffusée en temps réel sur le canal privé de l'enseignant (Reverb).
 *
 * @property int $id
 * @property int $enseignant_id
 * @property TypeNotification $type
 * @property string $message
 * @property CarbonImmutable $date_envoi
 * @property bool $lue
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['enseignant_id', 'type', 'message', 'date_envoi', 'lue'])]
class Notification extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lue' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeNotification::class,
            'date_envoi' => 'datetime',
            'lue' => 'boolean',
        ];
    }

    /**
     * T20.2 — Enregistre la notification de l'enseignant puis la diffuse sur son canal.
     * Si le serveur temps réel ne répond pas, la notification reste enregistrée.
     */
    public static function envoyer(User $enseignant, TypeNotification $type, string $message): self
    {
        $notification = self::create([
            'enseignant_id' => $enseignant->id,
            'type' => $type,
            'message' => $message,
            'date_envoi' => now(),
        ]);

        // Après un premier échec (serveur arrêté), on n'essaie plus de diffuser pendant cette requête
        if (! self::$diffusionImpossible) {
            try {
                NotificationEnvoyee::dispatch($notification);
            } catch (Throwable $e) {
                report($e);
                self::$diffusionImpossible = true;
            }
        }

        return $notification;
    }

    private static bool $diffusionImpossible = false;

    public function marquerCommeLue(): void
    {
        if (! $this->lue) {
            $this->update(['lue' => true]);
        }
    }

    /**
     * Ce que la page « Mes notifications » affiche.
     *
     * @return array{id: int, type: string, titre: string, message: string, date: string, lue: bool}
     */
    public function pourAffichage(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'titre' => $this->type->label(),
            'message' => $this->message,
            'date' => self::dateLisible($this->date_envoi),
            'lue' => $this->lue,
        ];
    }

    /**
     * « Aujourd'hui, 09:40 », « Hier, 16:15 », sinon « 05 oct. 2026 ».
     */
    public static function dateLisible(CarbonImmutable $date): string
    {
        return match (true) {
            $date->isToday() => "Aujourd'hui, ".$date->format('H:i'),
            $date->isYesterday() => 'Hier, '.$date->format('H:i'),
            default => $date->settings(['locale' => 'fr'])->translatedFormat('d M Y'),
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
