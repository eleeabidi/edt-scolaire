<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T07.5 — Gestion des classes : ajouter, modifier, supprimer (pas de groupes).
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

    public function test_the_new_name_must_not_be_used_by_another_class_of_the_school()
    {
        Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => '3e année C']);
        $classe = Classe::factory()->niveau(3)->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => '3e année D']);

        $this->actingAs($this->directeur)
            ->patch(route('classes.update', $classe), $this->donnees())
            ->assertSessionHasErrors(['nom' => 'Une classe de ce nom existe déjà dans votre école.']);
    }

    public function test_all_fields_are_required_and_the_size_is_a_positive_number()
    {
        $this->actingAs($this->directeur)
            ->post(route('classes.store'), [])
            ->assertSessionHasErrors(['nom', 'niveau', 'effectif']);

        foreach ([0, -3, 'trente'] as $effectif) {
            $this->actingAs($this->directeur)
                ->post(route('classes.store'), $this->donnees(['effectif' => $effectif]))
                ->assertSessionHasErrors('effectif');
        }
    }

    public function test_the_directeur_deletes_a_class_of_his_school()
    {
        $classe = Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)->delete(route('classes.destroy', $classe))->assertSessionHasNoErrors();

        $this->assertModelMissing($classe);
    }

    public function test_there_are_no_groups_and_no_in_service_state_for_classes()
    {
        $this->assertFalse(Schema::hasTable('groupes'));
        $this->assertFalse(Schema::hasColumn('classes', 'en_service'));
        $this->assertFalse(Route::has('classes.groupes'));
    }

    public function test_the_directeur_cannot_touch_a_class_of_another_school()
    {
        $autre = Classe::factory()->create();

        $this->actingAs($this->directeur)->patch(route('classes.update', $autre), $this->donnees())->assertForbidden();
        $this->actingAs($this->directeur)->delete(route('classes.destroy', $autre))->assertForbidden();

        $this->assertModelExists($autre);
    }
}
