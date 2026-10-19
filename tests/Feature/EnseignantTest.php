<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\Ecole;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EnseignantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function donnees(array $remplacements = []): array
    {
        return [
            'nom' => 'Haddad',
            'prenom' => 'Leila',
            'email' => 'leila@example.com',
            'telephone' => '22 555 666',
            ...$remplacements,
        ];
    }

    public function test_enseignant_cannot_access_the_list()
    {
        $this->actingAs(User::factory()->enseignant()->create())
            ->get(route('enseignants.index'))
            ->assertForbidden();
    }

    public function test_directeur_only_sees_teachers_of_his_school()
    {
        $directeur = User::factory()->directeur()->create();
        User::factory(3)->enseignant($directeur->ecole)->create();
        User::factory(2)->enseignant()->create(); // autre école

        $this->actingAs($directeur)
            ->get(route('enseignants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('enseignants/Index')
                ->where('enseignants.total', 3)
                ->where('estAdmin', false));
    }

    public function test_admin_sees_all_teachers_and_can_filter_by_school()
    {
        $ecole = Ecole::factory()->create();
        User::factory(3)->enseignant($ecole)->create();
        User::factory(2)->enseignant()->create();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('enseignants.index'))
            ->assertInertia(fn (Assert $page) => $page->where('enseignants.total', 5));

        $this->actingAs($admin)
            ->get(route('enseignants.index', ['ecole' => $ecole->id]))
            ->assertInertia(fn (Assert $page) => $page->where('enseignants.total', 3));
    }

    public function test_search_filters_the_list()
    {
        $directeur = User::factory()->directeur()->create();
        User::factory()->enseignant($directeur->ecole)->create(['nom' => 'Zitouni']);
        User::factory()->enseignant($directeur->ecole)->create(['nom' => 'Mansour']);

        $this->actingAs($directeur)
            ->get(route('enseignants.index', ['q' => 'zito']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enseignants.total', 1)
                ->where('enseignants.data.0.nom', 'Zitouni'));
    }

    public function test_list_can_be_filtered_by_status()
    {
        $directeur = User::factory()->directeur()->create();
        User::factory(2)->enseignant($directeur->ecole)->create();
        User::factory()->enseignant($directeur->ecole)->statut(StatutCompte::Desactive)->create();

        $this->actingAs($directeur)
            ->get(route('enseignants.index', ['statut' => 'DESACTIVE']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enseignants.total', 1)
                ->where('ecoleNom', $directeur->ecole->nom));
    }

    public function test_directeur_adds_a_teacher_in_his_school_with_a_temporary_password()
    {
        Notification::fake();
        $directeur = User::factory()->directeur()->create();
        $autreEcole = Ecole::factory()->create();

        // Même s'il essaie d'envoyer une autre école, c'est la sienne qui est utilisée
        $this->actingAs($directeur)
            ->post(route('enseignants.store'), $this->donnees(['ecole_id' => $autreEcole->id]))
            ->assertSessionHasNoErrors();

        $enseignant = User::where('email', 'leila@example.com')->first();

        $this->assertSame(Role::Enseignant, $enseignant->role);
        $this->assertSame(StatutCompte::Actif, $enseignant->statut);
        $this->assertSame($directeur->ecole_id, $enseignant->ecole_id);

        Notification::assertSentTo($enseignant, function (CompteCreeNotification $notification) use ($enseignant) {
            return Hash::check($notification->motDePasseTemporaire, $enseignant->password);
        });
    }

    public function test_the_new_teacher_can_login_with_the_temporary_password()
    {
        Notification::fake();
        $directeur = User::factory()->directeur()->create();

        $this->actingAs($directeur)->post(route('enseignants.store'), $this->donnees());
        $this->post(route('logout'));

        $enseignant = User::where('email', 'leila@example.com')->first();
        $motDePasse = Notification::sent($enseignant, CompteCreeNotification::class)->first()->motDePasseTemporaire;

        $this->post(route('login.store'), ['email' => 'leila@example.com', 'password' => $motDePasse]);

        $this->assertAuthenticatedAs($enseignant);
    }

    public function test_admin_must_choose_a_school()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('enseignants.store'), $this->donnees())
            ->assertSessionHasErrors('ecole_id');

        $ecole = Ecole::factory()->create();

        Notification::fake();
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('enseignants.store'), $this->donnees(['ecole_id' => $ecole->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($ecole->id, User::where('email', 'leila@example.com')->value('ecole_id'));
    }

    public function test_email_must_be_unique()
    {
        $directeur = User::factory()->directeur()->create();

        $this->actingAs($directeur)
            ->post(route('enseignants.store'), $this->donnees(['email' => $directeur->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_directeur_can_update_a_teacher_of_his_school()
    {
        $directeur = User::factory()->directeur()->create();
        $enseignant = User::factory()->enseignant($directeur->ecole)->create();

        $this->actingAs($directeur)
            ->patch(route('enseignants.update', $enseignant), $this->donnees(['email' => $enseignant->email]))
            ->assertSessionHasNoErrors();

        $enseignant->refresh();
        $this->assertSame('Haddad', $enseignant->nom);
        $this->assertSame('22 555 666', $enseignant->telephone);
    }

    public function test_a_teacher_has_no_specialite_anymore()
    {
        // Dictionnaire de données du sprint 1 : l'Enseignant n'a aucun attribut propre
        $this->assertFalse(Schema::hasColumn('users', 'specialite'));
    }

    public function test_directeur_cannot_touch_a_teacher_of_another_school()
    {
        $directeur = User::factory()->directeur()->create();
        $autre = User::factory()->enseignant()->create();

        $this->actingAs($directeur)
            ->patch(route('enseignants.update', $autre), $this->donnees(['email' => $autre->email]))
            ->assertForbidden();

        $this->actingAs($directeur)
            ->delete(route('enseignants.destroy', $autre))
            ->assertForbidden();

        $this->assertSame(StatutCompte::Actif, $autre->refresh()->statut);
    }

    public function test_cannot_manage_a_non_teacher_account_through_this_page()
    {
        $admin = User::factory()->admin()->create();
        $directeur = User::factory()->directeur()->create();

        $this->actingAs($admin)
            ->delete(route('enseignants.destroy', $directeur))
            ->assertForbidden();
    }

    public function test_deleting_a_teacher_deactivates_the_account()
    {
        $directeur = User::factory()->directeur()->create();
        $enseignant = User::factory()->enseignant($directeur->ecole)->create();

        $this->actingAs($directeur)
            ->delete(route('enseignants.destroy', $enseignant))
            ->assertSessionHasNoErrors();

        // Le compte existe toujours, mais il est désactivé
        $this->assertSame(StatutCompte::Desactive, $enseignant->refresh()->statut);
    }
}
