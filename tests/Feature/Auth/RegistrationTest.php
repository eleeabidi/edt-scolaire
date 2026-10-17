<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    /**
     * @return array<string, string>
     */
    private function donneesInscription(array $remplacements = []): array
    {
        return [
            'nom' => 'Ben Salah',
            'prenom' => 'Karim',
            'email' => 'karim@example.com',
            'telephone' => '22 333 444',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'ecole_nom' => 'École Ibn Khaldoun',
            'ecole_adresse' => '12 rue de la Liberté',
            'ecole_ville' => 'Tunis',
            'ecole_telephone' => '71 000 000',
            ...$remplacements,
        ];
    }

    public function test_registration_screen_can_be_rendered()
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_a_directeur_can_register_with_his_school()
    {
        $response = $this->post(route('register.store'), $this->donneesInscription());

        // Le compte est en attente : pas de connexion, retour à la page de connexion
        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user = User::where('email', 'karim@example.com')->first();

        $this->assertSame(Role::Directeur, $user->role);
        $this->assertSame(StatutCompte::EnAttente, $user->statut);
        $this->assertSame('École Ibn Khaldoun', $user->ecole->nom);
        $this->assertSame('Tunis', $user->ecole->ville);
    }

    public function test_a_pending_directeur_cannot_login()
    {
        $this->post(route('register.store'), $this->donneesInscription());

        $this->post(route('login.store'), [
            'email' => 'karim@example.com',
            'password' => 'password1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_school_information_is_required()
    {
        $this->post(route('register.store'), $this->donneesInscription(['ecole_nom' => '', 'ecole_ville' => '']))
            ->assertSessionHasErrors(['ecole_nom', 'ecole_ville']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('ecoles', 0);
    }

    public function test_error_messages_are_in_french()
    {
        $this->post(route('register.store'), $this->donneesInscription(['ecole_nom' => '']))
            ->assertSessionHasErrors(['ecole_nom' => "Le champ nom de l'école est obligatoire."]);

        $this->post(route('register.store'), $this->donneesInscription([
            'password' => 'seulementdeslettres',
            'password_confirmation' => 'seulementdeslettres',
        ]))->assertSessionHasErrors(['password' => 'Le champ mot de passe doit contenir au moins un chiffre.']);
    }

    public function test_password_needs_letters_and_numbers()
    {
        $this->post(route('register.store'), $this->donneesInscription([
            'password' => 'seulementdeslettres',
            'password_confirmation' => 'seulementdeslettres',
        ]))->assertSessionHasErrors('password');

        $this->post(route('register.store'), $this->donneesInscription([
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ]))->assertSessionHasErrors('password');
    }
}
