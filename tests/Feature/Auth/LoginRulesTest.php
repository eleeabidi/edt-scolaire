<?php

namespace Tests\Feature\Auth;

use App\Enums\StatutCompte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_accounts_can_login()
    {
        foreach ([StatutCompte::EnAttente, StatutCompte::Refuse, StatutCompte::Desactive] as $statut) {
            $user = User::factory()->directeur()->statut($statut)->create();

            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password1',
            ])->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);

            $this->assertGuest();
        }
    }

    public function test_wrong_password_shows_the_generic_message()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'mauvais123',
        ])->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);
    }

    public function test_a_user_deactivated_while_logged_in_is_logged_out()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->update(['statut' => StatutCompte::Desactive]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_home_redirects_each_role_to_its_start_page()
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('home'))->assertRedirect(route('dashboard'));

        $this->actingAs(User::factory()->directeur()->create());
        $this->get(route('home'))->assertRedirect(route('enseignants.index'));

        $this->actingAs(User::factory()->enseignant()->create());
        $this->get(route('home'))->assertRedirect(route('profile.edit'));
    }

    public function test_reset_link_expires_after_30_minutes()
    {
        $this->assertSame(30, config('auth.passwords.users.expire'));
    }
}
