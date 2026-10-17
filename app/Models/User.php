<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatutCompte;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string|null $specialite
 * @property string|null $motif_refus
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nom', 'prenom', 'email', 'password', 'telephone', 'role', 'statut', 'ecole_id', 'specialite', 'motif_refus'])]
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

    public function isActif(): bool
    {
        return $this->statut === StatutCompte::Actif;
    }
}
