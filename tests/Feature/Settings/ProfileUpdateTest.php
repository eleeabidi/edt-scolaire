<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_for_every_role()
    {
        foreach ([User::factory()->admin(), User::factory()->directeur(), User::factory()->enseignant()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('profile.edit'))
                ->assertOk();
        }
    }

    public function test_profile_page_shows_the_school()
    {
        $user = User::factory()->enseignant()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('profil/Edit')
                ->where('role', 'Enseignant')
                ->where('ecole.nom', $user->ecole->nom));
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'nom' => 'User',
                'prenom' => 'Test',
                'email' => 'test@example.com',
                'telephone' => '22 111 222',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('22 111 222', $user->telephone);
    }

    public function test_role_and_specialite_cannot_be_changed_from_the_profile()
    {
        $user = User::factory()->enseignant()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'nom' => 'User',
            'prenom' => 'Test',
            'email' => $user->email,
            'role' => 'admin',
            'specialite' => 'Autre',
        ]);

        $user->refresh();

        $this->assertTrue($user->isEnseignant());
        $this->assertNotSame('Autre', $user->specialite);
    }

    public function test_email_must_be_unique()
    {
        $autre = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'nom' => 'User',
                'prenom' => 'Test',
                'email' => $autre->email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password1',
                'password' => 'nouveau-mdp1',
                'password_confirmation' => 'nouveau-mdp1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('nouveau-mdp1', $user->refresh()->password));
    }

    public function test_correct_current_password_is_required()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'mauvais-mdp1',
                'password' => 'nouveau-mdp1',
                'password_confirmation' => 'nouveau-mdp1',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_new_password_must_follow_the_rules()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password1',
                'password' => 'courtx1',
                'password_confirmation' => 'courtx1',
            ])
            ->assertSessionHasErrors('password');
    }
}
