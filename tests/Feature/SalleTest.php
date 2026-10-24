<?php

namespace Tests\Feature;

use App\Enums\TypeSalle;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T09.5 — Gestion des salles : mise en service, modification, mise hors service (jamais de suppression).
 */
class SalleTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directeur = User::factory()->directeur()->create();
    }

    public function test_only_the_directeur_can_manage_rooms()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('salles.index'))->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create())->get(route('salles.index'))->assertForbidden();
        $this->actingAs($this->directeur)->get(route('salles.index'))->assertOk();
    }

    public function test_the_directeur_only_sees_the_rooms_of_his_school()
    {
        Salle::factory(3)->create(['ecole_id' => $this->directeur->ecole_id]);
        Salle::factory(2)->create(); // autre école

        $this->actingAs($this->directeur)
            ->get(route('salles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('salles/Index')
                ->where('salles.total', 3));
    }

    public function test_the_list_can_be_filtered_by_type_and_searched()
    {
        Salle::factory(2)->create(['ecole_id' => $this->directeur->ecole_id]);
        Salle::factory()->sport()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Gymnase']);

        $this->actingAs($this->directeur)
            ->get(route('salles.index', ['type' => 'sport']))
            ->assertInertia(fn (Assert $page) => $page->where('salles.total', 1));

        $this->actingAs($this->directeur)
            ->get(route('salles.index', ['q' => 'gymn']))
            ->assertInertia(fn (Assert $page) => $page->where('salles.data.0.nom', 'Gymnase'));
    }

    public function test_the_list_shows_the_state_of_each_room()
    {
        Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Salle 1']);
        Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Salle 2', 'en_service' => false]);

        $this->actingAs($this->directeur)
            ->get(route('salles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('salles.data.0.en_service', true)
                ->where('salles.data.1.en_service', false));
    }

    public function test_the_list_can_be_filtered_by_cours_and_technologie()
    {
        Salle::factory(2)->create(['ecole_id' => $this->directeur->ecole_id]);
        Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'type' => TypeSalle::Technologie]);

        $this->actingAs($this->directeur)
            ->get(route('salles.index', ['type' => 'cours']))
            ->assertInertia(fn (Assert $page) => $page->where('salles.total', 2)->where('salles.data.0.type', 'cours'));

        $this->actingAs($this->directeur)
            ->get(route('salles.index', ['type' => 'technologie']))
            ->assertInertia(fn (Assert $page) => $page->where('salles.total', 1));
    }

    public function test_the_directeur_puts_a_new_room_in_service()
    {
        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 5', 'type' => 'technologie', 'capacite' => 30])
            ->assertSessionHasNoErrors();

        $salle = Salle::where('nom', 'Salle 5')->first();
        $this->assertSame($this->directeur->ecole_id, $salle->ecole_id);
        $this->assertSame(TypeSalle::Technologie, $salle->type);
        $this->assertSame(30, $salle->capacite);
        $this->assertTrue($salle->en_service);
    }

    public function test_the_room_types_are_cours_sport_and_technologie()
    {
        $this->assertSame(['cours', 'sport', 'technologie'], array_column(TypeSalle::cases(), 'value'));
        $this->assertSame(TypeSalle::Cours, (new Salle)->type); // par défaut

        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Labo', 'type' => 'laboratoire', 'capacite' => 20])
            ->assertSessionHasErrors('type');
    }

    public function test_all_fields_are_required_and_the_capacity_is_a_positive_number()
    {
        $this->actingAs($this->directeur)
            ->post(route('salles.store'), [])
            ->assertSessionHasErrors(['nom', 'type', 'capacite']);

        foreach ([0, -5, 'trente'] as $capacite) {
            $this->actingAs($this->directeur)
                ->post(route('salles.store'), ['nom' => 'Salle 9', 'type' => 'cours', 'capacite' => $capacite])
                ->assertSessionHasErrors('capacite');
        }
    }

    public function test_two_rooms_of_the_same_school_cannot_have_the_same_name()
    {
        Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Salle 1']);
        $salle3 = Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Salle 3']);
        Salle::factory()->create(['nom' => 'Salle 2']); // autre école : autorisé

        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 1', 'type' => 'cours', 'capacite' => 30])
            ->assertSessionHasErrors(['nom' => 'Une salle de ce nom existe déjà dans votre école.']);

        // À la modification aussi
        $this->actingAs($this->directeur)
            ->patch(route('salles.update', $salle3), ['nom' => 'Salle 1', 'type' => 'cours', 'capacite' => 30])
            ->assertSessionHasErrors(['nom' => 'Une salle de ce nom existe déjà dans votre école.']);

        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 2', 'type' => 'cours', 'capacite' => 30])
            ->assertSessionHasNoErrors();
    }

    public function test_the_directeur_updates_a_room_of_his_school()
    {
        $salle = Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)
            ->patch(route('salles.update', $salle), ['nom' => 'Salle A', 'type' => 'sport', 'capacite' => 25])
            ->assertSessionHasNoErrors();

        $salle->refresh();
        $this->assertSame('Salle A', $salle->nom);
        $this->assertSame(TypeSalle::Sport, $salle->type);
        $this->assertSame(25, $salle->capacite);
    }

    public function test_a_room_is_never_deleted_but_put_out_of_service_and_back()
    {
        $this->assertFalse(Route::has('salles.destroy'));

        $salle = Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)->patch(route('salles.hors-service', $salle))->assertSessionHasNoErrors();
        $this->assertFalse($salle->refresh()->en_service);
        $this->assertModelExists($salle);

        $this->actingAs($this->directeur)->patch(route('salles.en-service', $salle))->assertSessionHasNoErrors();
        $this->assertTrue($salle->refresh()->en_service);
    }

    public function test_the_directeur_cannot_touch_a_room_of_another_school()
    {
        $autre = Salle::factory()->create();

        $this->actingAs($this->directeur)
            ->patch(route('salles.update', $autre), ['nom' => 'X', 'type' => 'cours', 'capacite' => 10])
            ->assertForbidden();

        $this->actingAs($this->directeur)->patch(route('salles.hors-service', $autre))->assertForbidden();
        $this->actingAs($this->directeur)->patch(route('salles.en-service', $autre))->assertForbidden();
        $this->assertTrue($autre->refresh()->en_service);
    }
}
