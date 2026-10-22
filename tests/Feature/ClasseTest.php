<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Groupe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T07.6 — Gestion des classes et des groupes.
 */
class ClasseTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directeur = User::factory()->directeur()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(array $remplacements = []): array
    {
        return ['nom' => '3e année C', 'niveau' => 3, 'effectif' => 30, 'cours_samedi' => true, ...$remplacements];
    }

    public function test_only_the_directeur_can_manage_classes()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('classes.index'))->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create())->get(route('classes.index'))->assertForbidden();
        $this->actingAs($this->directeur)->get(route('classes.index'))->assertOk();
    }

    public function test_the_directeur_only_sees_the_classes_of_his_school()
    {
        Classe::factory(3)->create(['ecole_id' => $this->directeur->ecole_id]);
        Classe::factory(2)->create();

        $this->actingAs($this->directeur)
            ->get(route('classes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('classes/Index')
                ->where('classes.total', 3));
    }

    public function test_the_list_can_be_filtered_by_level()
    {
        Classe::factory()->niveau(1)->create(['ecole_id' => $this->directeur->ecole_id]);
        Classe::factory(2)->niveau(4)->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)
            ->get(route('classes.index', ['niveau' => 4]))
            ->assertInertia(fn (Assert $page) => $page->where('classes.total', 2));
    }

    public function test_the_directeur_adds_a_class_in_his_school()
    {
        $this->actingAs($this->directeur)
            ->post(route('classes.store'), $this->donnees())
            ->assertSessionHasNoErrors();

        $classe = Classe::where('nom', '3e année C')->first();
        $this->assertSame($this->directeur->ecole_id, $classe->ecole_id);
        $this->assertSame(3, $classe->niveau);
        $this->assertTrue($classe->cours_samedi);
    }

    public function test_saturday_is_only_a_choice_in_3rd_year()
    {
        // 1re et 2e année : toujours non, même si on demande oui
        $this->actingAs($this->directeur)->post(route('classes.store'), $this->donnees(['nom' => '1re A', 'niveau' => 1, 'cours_samedi' => true]));
        $this->assertFalse(Classe::where('nom', '1re A')->value('cours_samedi'));

        // 4e à 6e année : toujours oui, même si on demande non
        $this->actingAs($this->directeur)->post(route('classes.store'), $this->donnees(['nom' => '5e A', 'niveau' => 5, 'cours_samedi' => false]));
        $this->assertTrue((bool) Classe::where('nom', '5e A')->value('cours_samedi'));

        // 3e année : le choix du directeur
        $this->actingAs($this->directeur)->post(route('classes.store'), $this->donnees(['nom' => '3e A', 'cours_samedi' => false]));
        $this->assertFalse((bool) Classe::where('nom', '3e A')->value('cours_samedi'));
    }

    public function test_changing_the_level_applies_the_saturday_rule()
    {
        $classe = Classe::factory()->niveau(3)->create(['ecole_id' => $this->directeur->ecole_id, 'cours_samedi' => true]);

        $this->actingAs($this->directeur)
            ->patch(route('classes.update', $classe), $this->donnees(['nom' => $classe->nom, 'niveau' => 2]));

        $this->assertFalse($classe->refresh()->cours_samedi);
    }

    public function test_the_level_must_be_between_1_and_6()
    {
        $this->actingAs($this->directeur)
            ->post(route('classes.store'), $this->donnees(['niveau' => 7]))
            ->assertSessionHasErrors('niveau');
    }

    public function test_two_classes_of_the_same_school_cannot_have_the_same_name()
    {
        Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => '3e année C']);

        $this->actingAs($this->directeur)
            ->post(route('classes.store'), $this->donnees())
            ->assertSessionHasErrors(['nom' => 'Une classe de ce nom existe déjà dans votre école.']);
    }

    public function test_deleting_a_class_deletes_its_groups()
    {
        $classe = Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id]);
        Groupe::factory(2)->create(['classe_id' => $classe->id]);

        $this->actingAs($this->directeur)->delete(route('classes.destroy', $classe));

        $this->assertModelMissing($classe);
        $this->assertDatabaseCount('groupes', 0);
    }

    public function test_the_groups_of_a_class_are_saved_at_once()
    {
        $classe = Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id]);
        [$garde, $retire] = Groupe::factory(2)->create(['classe_id' => $classe->id]);

        $this->actingAs($this->directeur)
            ->put(route('classes.groupes', $classe), ['groupes' => [
                ['id' => $garde->id, 'nom' => 'Groupe A', 'effectif' => 15],
                ['id' => null, 'nom' => 'Groupe B', 'effectif' => 14],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Groupe A', 'Groupe B'], $classe->groupes()->orderBy('nom')->pluck('nom')->all());
        $this->assertModelMissing($retire);
        $this->assertSame(15, $garde->refresh()->effectif);
    }

    public function test_a_class_can_have_no_group()
    {
        $classe = Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id]);
        Groupe::factory()->create(['classe_id' => $classe->id]);

        $this->actingAs($this->directeur)
            ->put(route('classes.groupes', $classe), ['groupes' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $classe->groupes()->count());
    }

    public function test_two_groups_cannot_have_the_same_name()
    {
        $classe = Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)
            ->put(route('classes.groupes', $classe), ['groupes' => [
                ['nom' => 'Groupe 1', 'effectif' => 15],
                ['nom' => 'Groupe 1', 'effectif' => 15],
            ]])
            ->assertSessionHasErrors();
    }

    public function test_the_directeur_cannot_touch_a_class_of_another_school()
    {
        $autre = Classe::factory()->create();

        $this->actingAs($this->directeur)->patch(route('classes.update', $autre), $this->donnees())->assertForbidden();
        $this->actingAs($this->directeur)->put(route('classes.groupes', $autre), ['groupes' => []])->assertForbidden();
        $this->actingAs($this->directeur)->delete(route('classes.destroy', $autre))->assertForbidden();

        $this->assertModelExists($autre);
    }
}
