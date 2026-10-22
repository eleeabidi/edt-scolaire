<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Models\Classe;
use App\Models\Matiere;
use App\Models\User;
use Database\Seeders\ProgrammeNationalSeeder;
use Database\Seeders\ReglesNationalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T10.5 — Saisie des disponibilités.
 */
class DisponibiliteTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ProgrammeNationalSeeder::class, ReglesNationalesSeeder::class]);
        $this->directeur = User::factory()->directeur()->create();
    }

    private function enseignant(array $attributs = []): User
    {
        return User::factory()->enseignant($this->directeur->ecole)->create($attributs);
    }

    private function enregistrer(User $enseignant, array $indisponibles)
    {
        return $this->actingAs($this->directeur)
            ->put(route('disponibilites.update', $enseignant), ['indisponibles' => $indisponibles]);
    }

    public function test_only_the_directeur_manages_availabilities()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('disponibilites.index'))->assertForbidden();
        $this->actingAs($this->enseignant())->get(route('disponibilites.index'))->assertForbidden();
        $this->actingAs($this->directeur)->get(route('disponibilites.index'))->assertOk();
    }

    public function test_the_grid_lists_only_the_active_teachers_of_the_school()
    {
        $actif = $this->enseignant(['nom' => 'Ayari']);
        $this->enseignant(['statut' => StatutCompte::Desactive]);
        User::factory()->enseignant()->create(); // autre école

        $this->actingAs($this->directeur)
            ->get(route('disponibilites.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('disponibilites/Index')
                ->has('enseignants', 1)
                ->where('enseignantId', $actif->id)
                ->has('creneaux', 28)); // lundi → vendredi : 5 créneaux, samedi matin : 3
    }

    public function test_a_teacher_without_any_line_is_available_everywhere()
    {
        $enseignant = $this->enseignant();

        $this->actingAs($this->directeur)
            ->get(route('disponibilites.index', ['enseignant' => $enseignant->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('indisponibles', [])
                ->where('minutesDisponibles', 50 * 60)); // 5 × 9 h + 5 h le samedi
    }

    public function test_unavailable_slots_are_saved_as_available_slots()
    {
        $enseignant = $this->enseignant();

        $this->enregistrer($enseignant, ['1-08:00', '3-13:00'])->assertSessionHasNoErrors();

        // 28 créneaux - 2 indisponibles = 26 lignes « disponible »
        $this->assertSame(26, $enseignant->disponibilites()->count());
        $this->assertFalse($enseignant->disponibilites()->where('jour', 1)->where('heure_debut', '08:00')->exists());

        $this->actingAs($this->directeur)
            ->get(route('disponibilites.index', ['enseignant' => $enseignant->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('indisponibles', ['1-08:00', '3-13:00'])
                ->where('minutesDisponibles', 46 * 60));
    }

    public function test_making_every_slot_available_again_removes_the_lines()
    {
        $enseignant = $this->enseignant();
        $this->enregistrer($enseignant, ['1-08:00']);

        $this->enregistrer($enseignant, [])->assertSessionHasNoErrors();

        $this->assertSame(0, $enseignant->disponibilites()->count());
    }

    public function test_saturday_afternoon_cannot_be_chosen()
    {
        // Le samedi après-midi n'existe pas dans les créneaux (grisé)
        $this->enregistrer($this->enseignant(), ['6-13:00'])->assertSessionHasErrors('indisponibles');
    }

    public function test_a_warning_is_shown_when_available_hours_are_lower_than_assigned_hours()
    {
        $enseignant = $this->enseignant();
        $classe = Classe::factory()->niveau(3)->create(['ecole_id' => $this->directeur->ecole_id]);
        $enseignant->affectations()->create([
            'classe_id' => $classe->id,
            'matiere_id' => Matiere::where('nom', 'Français')->value('id'),
        ]); // 8 h affectées

        // Disponible seulement le lundi matin (4 h)
        $tout = collect(range(1, 6))->flatMap(fn (int $j) => ["{$j}-08:00", "{$j}-10:05", "{$j}-12:00", "{$j}-13:00", "{$j}-15:05"]);
        $indisponibles = $tout->reject(fn (string $c) => in_array($c, ['1-08:00', '1-10:05'], true))
            ->reject(fn (string $c) => in_array($c, ['6-13:00', '6-15:05'], true))
            ->values()
            ->all();

        $this->enregistrer($enseignant, $indisponibles)
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'warning')
            ->assertInertiaFlash('toast.message', 'Disponibilités enregistrées, mais attention : 4 h disponibles pour 8 h affectées.');
    }

    public function test_the_directeur_cannot_edit_a_teacher_of_another_school_or_a_deactivated_teacher()
    {
        $this->enregistrer(User::factory()->enseignant()->create(), [])->assertForbidden();
        $this->enregistrer($this->enseignant(['statut' => StatutCompte::Desactive]), [])->assertStatus(409);
    }
}
