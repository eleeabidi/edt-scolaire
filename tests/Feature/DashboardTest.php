<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_visit_the_dashboard()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_directeur_and_enseignant_cannot_visit_the_dashboard()
    {
        $this->actingAs(User::factory()->directeur()->create());
        $this->get(route('dashboard'))->assertForbidden();

        $this->actingAs(User::factory()->enseignant()->create());
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_the_dashboard_shows_the_key_figures_and_pending_registrations()
    {
        User::factory(3)->directeur()->create();
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();
        User::factory()->directeur()->statut(StatutCompte::Refuse)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('statistiques.ecoles', 3)
                ->where('statistiques.directeursActifs', 3)
                ->where('statistiques.inscriptionsEnAttente', 2)
                ->has('inscriptions', 2));
    }

    public function test_forbidden_pages_use_the_french_error_page()
    {
        $this->actingAs(User::factory()->enseignant()->create())
            ->get(route('dashboard'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erreur')
                ->where('statut', 403));
    }
}
