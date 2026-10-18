<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\EcoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nom
 * @property string $adresse
 * @property string $ville
 * @property string $telephone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nom', 'adresse', 'ville', 'telephone'])]
class Ecole extends Model
{
    /** @use HasFactory<EcoleFactory> */
    use HasFactory;

    /**
     * Le directeur qui dirige l'école (1–1).
     *
     * @return HasOne<User, $this>
     */
    public function directeur(): HasOne
    {
        return $this->hasOne(User::class)->where('role', Role::Directeur);
    }

    /**
     * Les enseignants de l'école (0..*).
     *
     * @return HasMany<User, $this>
     */
    public function enseignants(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::Enseignant);
    }

    /**
     * @return HasMany<Classe, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    /**
     * @return HasMany<Matiere, $this>
     */
    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class);
    }

    /**
     * @return HasMany<Salle, $this>
     */
    public function salles(): HasMany
    {
        return $this->hasMany(Salle::class);
    }

    /**
     * Les créneaux horaires de l'école.
     *
     * @return HasMany<Horaire, $this>
     */
    public function horaires(): HasMany
    {
        return $this->hasMany(Horaire::class);
    }
}
