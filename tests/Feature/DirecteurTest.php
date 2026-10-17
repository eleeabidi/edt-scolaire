<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use App\Notifications\InscriptionRefuseeNotification;
use App\Notifications\InscriptionValideeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirecteurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @return array<string, string>
     */
    private function donnees(array $remplacements = []): array
    {
        return [
            'nom' => 'Gharbi',
            'prenom' => 'Nadia',
            'email' => 'nadia@example.com',
            'telephone' => '22 777 888',
            'ecole_nom' => 'École El Amal',
            'ecole_adresse' => '5 avenue Habib Bourguiba',
            'ecole_ville' => 'Sfax',
            'ecole_telephone' => '74 000 000',
            ...$remplacements,
        ];
    }

    public function test_only_admin_can_manage_directeurs()
    {
        $directeur = User::factory()->directeur()->create();

        $this->actingAs($directeur)->get(route('directeurs.index'))->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create())->get(route('directeurs.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('directeurs.index'))->assertOk();
    }

    public function test_list_can_be_filtered_by_status_and_counts_are_given()
    {
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();
        User::factory(3)->directeur()->create();

        $this->actingAs($this->admin)
            ->get(route('directeurs.index', ['statut' => 'EN_ATTENTE']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('directeurs/Index')
                ->where('directeurs.total', 2)
                ->where('compteurs.EN_ATTENTE', 2)
                ->where('compteurs.ACTIF', 3));
    }

    public function test_search_also_looks_at_the_school_name()
    {
        $directeur = User::factory()->directeur()->create();
        $directeur->ecole->update(['nom' => 'École Pasteur']);
        User::factory()->directeur()->create();

        $this->actingAs($this->admin)
            ->get(route('directeurs.index', ['q' => 'pasteur']))
            ->assertInertia(fn (Assert $page) => $page->where('directeurs.total', 1));
    }

    public function test_pending_count_is_shared_with_the_admin_menu()
    {
        User::factory(2)->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->actingAs($this->admin)
            ->get(route('directeurs.index'))
            ->assertInertia(fn (Assert $page) => $page->where('inscriptionsEnAttente', 2));
    }

    public function test_admin_adds_an_active_directeur_with_his_school()
    {
        Notification::fake();

        $this->actingAs($this->admin)
            ->post(route('directeurs.store'), $this->donnees())
            ->assertSessionHasNoErrors();

        $directeur = User::where('email', 'nadia@example.com')->first();

        $this->assertSame(Role::Directeur, $directeur->role);
        $this->assertSame(StatutCompte::Actif, $directeur->statut);
        $this->assertSame('École El Amal', $directeur->ecole->nom);
        Notification::assertSentTo($directeur, CompteCreeNotification::class);
    }

    public function test_school_information_is_required_when_adding()
    {
        $this->actingAs($this->admin)
            ->post(route('directeurs.store'), $this->donnees(['ecole_nom' => '']))
            ->assertSessionHasErrors('ecole_nom');

        $this->assertDatabaseCount('ecoles', 0);
    }

    public function test_admin_updates_a_directeur_and_his_school()
    {
        $directeur = User::factory()->directeur()->create();

        $this->actingAs($this->admin)
            ->patch(route('directeurs.update', $directeur), $this->donnees(['email' => $directeur->email]))
            ->assertSessionHasNoErrors();

        $directeur->refresh();
        $this->assertSame('Gharbi', $directeur->nom);
        $this->assertSame('Sfax', $directeur->ecole->ville);
    }

    public function test_deleting_a_directeur_deactivates_the_account()
    {
        $directeur = User::factory()->directeur()->create();

        $this->actingAs($this->admin)->delete(route('directeurs.destroy', $directeur));

        $this->assertSame(StatutCompte::Desactive, $directeur->refresh()->statut);
    }

    public function test_the_detail_page_shows_the_registration()
    {
        $directeur = User::factory()->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->actingAs($this->admin)
            ->get(route('directeurs.show', $directeur))
            ->assertInertia(fn (Assert $page) => $page
                ->component('directeurs/Show')
                ->where('directeur.email', $directeur->email)
                ->where('directeur.ecole.nom', $directeur->ecole->nom));
    }

    public function test_this_page_cannot_be_used_for_a_teacher()
    {
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs($this->admin)->get(route('directeurs.show', $enseignant))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('directeurs.destroy', $enseignant))->assertNotFound();
    }

    public function test_admin_validates_a_registration()
    {
        Notification::fake();
        $directeur = User::factory()->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->actingAs($this->admin)
            ->post(route('directeurs.valider', $directeur))
            ->assertRedirect(route('directeurs.index'));

        $this->assertSame(StatutCompte::Actif, $directeur->refresh()->statut);
        Notification::assertSentTo($directeur, InscriptionValideeNotification::class);
    }

    public function test_a_validated_directeur_can_login()
    {
        Notification::fake();
        $directeur = User::factory()->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->actingAs($this->admin)->post(route('directeurs.valider', $directeur));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $directeur->email, 'password' => 'password1']);

        $this->assertAuthenticatedAs($directeur);
    }

    public function test_admin_refuses_a_registration_with_a_reason()
    {
        Notification::fake();
        $directeur = User::factory()->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->actingAs($this->admin)
            ->post(route('directeurs.refuser', $directeur), ['motif' => 'École introuvable'])
            ->assertRedirect(route('directeurs.index'));

        $directeur->refresh();
        $this->assertSame(StatutCompte::Refuse, $directeur->statut);
        $this->assertSame('École introuvable', $directeur->motif_refus);

        Notification::assertSentTo(
            $directeur,
            InscriptionRefuseeNotification::class,
            fn (InscriptionRefuseeNotification $n) => $n->motif === 'École introuvable',
        );
    }

    public function test_the_reason_is_optional()
    {
        Notification::fake();
        $directeur = User::factory()->directeur()->statut(StatutCompte::EnAttente)->create();

        $this->actingAs($this->admin)
            ->post(route('directeurs.refuser', $directeur))
            ->assertSessionHasNoErrors();

        $this->assertSame(StatutCompte::Refuse, $directeur->refresh()->statut);
        $this->assertNull($directeur->motif_refus);
    }

    public function test_a_registration_cannot_be_processed_twice()
    {
        $directeur = User::factory()->directeur()->create(); // déjà ACTIF

        $this->actingAs($this->admin)->post(route('directeurs.refuser', $directeur))->assertStatus(409);

        $this->assertSame(StatutCompte::Actif, $directeur->refresh()->statut);
    }
}
