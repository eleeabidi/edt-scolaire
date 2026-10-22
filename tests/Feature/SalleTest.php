<?php

namespace Tests\Feature;

use App\Enums\TypeSalle;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T09.5 — Gestion des salles.
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

    public function test_the_directeur_adds_a_room_in_his_school()
    {
        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 5', 'type' => 'sport', 'capacite' => 40])
            ->assertSessionHasNoErrors();

        $salle = Salle::where('nom', 'Salle 5')->first();
        $this->assertSame($this->directeur->ecole_id, $salle->ecole_id);
        $this->assertSame(TypeSalle::Sport, $salle->type);
        $this->assertSame(40, $salle->capacite);
    }

    public function test_type_and_capacity_are_required()
    {
        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 5'])
            ->assertSessionHasErrors(['type', 'capacite']);
    }

    public function test_only_normal_or_sport_rooms_exist()
    {
        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Labo', 'type' => 'laboratoire', 'capacite' => 20])
            ->assertSessionHasErrors('type');
    }

    public function test_two_rooms_of_the_same_school_cannot_have_the_same_name()
    {
        Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Salle 1']);
        Salle::factory()->create(['nom' => 'Salle 2']); // autre école : autorisé

        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 1', 'type' => 'normale', 'capacite' => 30])
            ->assertSessionHasErrors(['nom' => 'Une salle de ce nom existe déjà dans votre école.']);

        $this->actingAs($this->directeur)
            ->post(route('salles.store'), ['nom' => 'Salle 2', 'type' => 'normale', 'capacite' => 30])
            ->assertSessionHasNoErrors();
    }

    public function test_the_directeur_updates_and_deletes_a_room_of_his_school()
    {
        $salle = Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)
            ->patch(route('salles.update', $salle), ['nom' => 'Salle A', 'type' => 'normale', 'capacite' => 25])
            ->assertSessionHasNoErrors();
        $this->assertSame('Salle A', $salle->refresh()->nom);

        $this->actingAs($this->directeur)->delete(route('salles.destroy', $salle));
        $this->assertModelMissing($salle);
    }

    public function test_the_directeur_cannot_touch_a_room_of_another_school()
    {
        $autre = Salle::factory()->create();

        $this->actingAs($this->directeur)
            ->patch(route('salles.update', $autre), ['nom' => 'X', 'type' => 'normale', 'capacite' => 10])
            ->assertForbidden();

        $this->actingAs($this->directeur)->delete(route('salles.destroy', $autre))->assertForbidden();
        $this->assertModelExists($autre);
    }
}
