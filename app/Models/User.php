<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatutCompte;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nom
 * @property string $prenom
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $telephone
 * @property Role $role
 * @property StatutCompte $statut
 * @property int|null $ecole_id
 * @property int $volume_horaire_max
 * @property string|null $motif_refus
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nom', 'prenom', 'email', 'password', 'telephone', 'role', 'statut', 'ecole_id', 'volume_horaire_max', 'motif_refus'])]
#[Hidden(['password', 'remember_token'])]
#[Appends(['name'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'statut' => StatutCompte::class,
            'volume_horaire_max' => 'integer',
        ];
    }

    /**
     * Nom complet « Prénom Nom », utilisé pour l'affichage.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->prenom} {$this->nom}"));
    }

    /**
     * L'école du directeur ou de l'enseignant.
     *
     * @return BelongsTo<Ecole, $this>
     */
    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class);
    }

    /**
     * Les affectations d'un enseignant (classe + matière).
     *
     * @return HasMany<Affectation, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class, 'enseignant_id');
    }

    /**
     * Les créneaux où l'enseignant est disponible (aucune ligne = disponible partout).
     *
     * @return HasMany<Disponibilite, $this>
     */
    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class, 'enseignant_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isDirecteur(): bool
    {
        return $this->role === Role::Directeur;
    }

    public function isEnseignant(): bool
    {
        return $this->role === Role::Enseignant;
    }

    /**
     * Réinitialiser le mot de passe — précondition « compte actif » : un compte en attente,
     * refusé ou désactivé ne reçoit pas le lien (le message affiché reste le même).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->isActif()) {
            $this->notify(new ResetPassword($token));
        }
    }

    public function isActif(): bool
    {
        return $this->statut === StatutCompte::Actif;
    }

    /**
     * Les séances que l'enseignant assure dans les emplois du temps.
     *
     * @return HasMany<Seance, $this>
     */
    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class, 'enseignant_id');
    }
}
