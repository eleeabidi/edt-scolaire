<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
