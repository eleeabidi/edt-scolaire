<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Ecole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password1'),
            'telephone' => fake()->phoneNumber(),
            'role' => Role::Enseignant,
            'statut' => StatutCompte::Actif,
            'ecole_id' => null,
            'specialite' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Un compte Admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Admin,
        ]);
    }

    /**
     * Un Directeur avec sa propre école.
     */
    public function directeur(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Directeur,
            'ecole_id' => Ecole::factory(),
        ]);
    }

    /**
     * Un Enseignant (rattaché à une école).
     */
    public function enseignant(?Ecole $ecole = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Enseignant,
            'ecole_id' => $ecole->id ?? Ecole::factory(),
            'specialite' => fake()->randomElement(['Mathématiques', 'Français', 'Arabe', 'Anglais', 'Sciences', 'Histoire-Géographie', 'Éducation physique']),
        ]);
    }

    /**
     * Un compte avec un statut donné.
     */
    public function statut(StatutCompte $statut): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => $statut,
        ]);
    }
}
