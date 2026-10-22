<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Matiere;
use App\Models\User;
use Database\Seeders\ProgrammeNationalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T06.6 — Enseignants : affectations, volume horaire maximum, école.
 */
class AffectationTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProgrammeNationalSeeder::class);
        $this->directeur = User::factory()->directeur()->create();
    }

    private function enseignant(array $attributs = []): User
    {
        return User::factory()->enseignant($this->directeur->ecole)->create($attributs);
    }

    private function classe(int $niveau, array $attributs = []): Classe
    {
        return Classe::factory()->niveau($niveau)->create(['ecole_id' => $this->directeur->ecole_id, ...$attributs]);
    }

    private function matiere(string $nom): Matiere
    {
        return Matiere::where('nom', $nom)->firstOrFail();
    }

    private function affecter(User $enseignant, Classe $classe, string $matiere)
    {
        return $this->actingAs($this->directeur)->post(route('affectations.store', $enseignant), [
            'classe_id' => $classe->id,
            'matiere_id' => $this->matiere($matiere)->id,
        ]);
    }

    public function test_the_maximum_volume_is_18_hours_by_default()
    {
        $this->assertTrue(Schema::hasColumn('users', 'volume_horaire_max'));
        $this->assertFalse(Schema::hasColumn('users', 'specialite'));

        $id = User::factory()->enseignant()->create()->id;
        $this->assertSame(18, User::find($id)->volume_horaire_max);
    }

    public function test_only_the_directeur_manages_assignments()
    {
        $enseignant = $this->enseignant();

        $this->actingAs(User::factory()->admin()->create())->get(route('affectations.show', $enseignant))->assertForbidden();
        $this->actingAs($enseignant)->get(route('affectations.show', $enseignant))->assertForbidden();

        $this->actingAs($this->directeur)
            ->get(route('affectations.show', $enseignant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('enseignants/Affectations')
                ->where('enseignant.volume_horaire_max', 18)
                ->where('totalMinutes', 0));
    }

    public function test_the_directeur_assigns_a_subject_of_a_class()
    {
        $enseignant = $this->enseignant();
        $classe = $this->classe(3);

        $this->affecter($enseignant, $classe, 'Français')->assertSessionHasNoErrors();

        $this->assertSame(1, $enseignant->affectations()->count());
        $this->assertSame(480, Affectation::minutesPourEnseignant($enseignant->id)); // 8 h en 3e année
    }

    public function test_a_subject_of_a_class_has_only_one_teacher()
    {
        $classe = $this->classe(3);
        $this->affecter($this->enseignant(['nom' => 'Hammami', 'prenom' => 'Sonia']), $classe, 'Français');

        $this->affecter($this->enseignant(), $classe, 'Français')
            ->assertSessionHasErrors(['matiere_id' => "Cette matière est déjà enseignée en {$classe->nom} par Sonia Hammami."]);

        $this->assertSame(1, Affectation::count());
    }

    public function test_the_total_never_exceeds_the_maximum_volume()
    {
        $enseignant = $this->enseignant(); // 18 h
        $this->affecter($enseignant, $this->classe(3), 'Français'); // 8 h
        $this->affecter($enseignant, $this->classe(3), 'Français'); // 16 h

        // Langue arabe en 6e année (6 h) → 22 h > 18 h : refusé avec le total et le maximum
        $this->affecter($enseignant, $this->classe(6), 'Langue arabe')
            ->assertSessionHasErrors(['matiere_id' => 'Impossible : avec cette affectation, le total passerait à 22 h, au-delà du maximum de 18 h de cet enseignant.']);

        $this->assertSame(960, Affectation::minutesPourEnseignant($enseignant->id));
    }

    public function test_the_main_teacher_of_1st_year_needs_19_hours()
    {
        // 1re année : tout sauf l'éducation physique = 19 h
        $principal = $this->enseignant();
        $classe = $this->classe(1);
        $matieres = ['Langue arabe', 'Mathématiques', 'Éveil scientifique', 'Éducation islamique', 'Éducation technologique', 'Éducation musicale', 'Éducation artistique'];

        // Avec le maximum par défaut (18 h), la dernière matière est refusée
        foreach ($matieres as $matiere) {
            $reponse = $this->affecter($principal, $classe, $matiere);
        }
        $reponse->assertSessionHasErrors(['matiere_id' => 'Impossible : avec cette affectation, le total passerait à 19 h, au-delà du maximum de 18 h de cet enseignant.']);

        // Le directeur monte son maximum à 19 h : l'affectation passe
        $this->actingAs($this->directeur)->patch(route('affectations.volume', $principal), ['volume_horaire_max' => 19])->assertSessionHasNoErrors();
        $this->affecter($principal, $classe, 'Éducation artistique')->assertSessionHasNoErrors();

        $this->assertSame(1140, Affectation::minutesPourEnseignant($principal->id));

        // L'éducation physique a son propre enseignant
        $this->affecter($this->enseignant(), $classe, 'Éducation physique')->assertSessionHasNoErrors();
    }

    public function test_the_main_teacher_of_2nd_year_with_a_french_teacher()
    {
        // 2e année : un principal (tout sauf le français et l'EPS = 19 h) + un enseignant de français
        $principal = $this->enseignant(['volume_horaire_max' => 19]);
        $classe = $this->classe(2);

        foreach (['Langue arabe', 'Mathématiques', 'Éveil scientifique', 'Éducation islamique', 'Éducation technologique', 'Éducation musicale', 'Éducation artistique'] as $matiere) {
            $this->affecter($principal, $classe, $matiere)->assertSessionHasNoErrors();
        }

        $this->affecter($this->enseignant(), $classe, 'Français')->assertSessionHasNoErrors();
        $this->assertSame(1140, Affectation::minutesPourEnseignant($principal->id));
    }

    public function test_a_subject_must_be_in_the_programme_of_the_class_level()
    {
        // Pas de français en 1re année
        $this->affecter($this->enseignant(), $this->classe(1), 'Français')
            ->assertSessionHasErrors(['matiere_id' => "Cette matière n'est pas enseignée en 1re année."]);
    }

    public function test_no_assignment_for_a_class_of_another_school()
    {
        $autre = Classe::factory()->niveau(3)->create();

        $this->affecter($this->enseignant(), $autre, 'Français')->assertSessionHasErrors('classe_id');
        $this->assertSame(0, Affectation::count());
    }

    public function test_no_assignment_for_a_teacher_of_another_school_or_a_deactivated_teacher()
    {
        $classe = $this->classe(3);

        $this->affecter(User::factory()->enseignant()->create(), $classe, 'Français')->assertForbidden();
        $this->affecter($this->enseignant(['statut' => StatutCompte::Desactive]), $classe, 'Français')->assertStatus(409);

        $this->assertSame(0, Affectation::count());
    }

    public function test_the_maximum_cannot_be_lower_than_the_hours_already_assigned()
    {
        $enseignant = $this->enseignant();
        $this->affecter($enseignant, $this->classe(3), 'Français'); // 8 h

        $this->actingAs($this->directeur)
            ->patch(route('affectations.volume', $enseignant), ['volume_horaire_max' => 7])
            ->assertSessionHasErrors(['volume_horaire_max' => 'Le maximum ne peut pas être inférieur aux heures déjà affectées (8 h).']);

        $this->actingAs($this->directeur)
            ->patch(route('affectations.volume', $enseignant), ['volume_horaire_max' => 20])
            ->assertSessionHasNoErrors();
        $this->assertSame(20, $enseignant->refresh()->volume_horaire_max);
    }

    public function test_an_assignment_can_be_removed()
    {
        $enseignant = $this->enseignant();
        $this->affecter($enseignant, $this->classe(3), 'Français');
        $affectation = $enseignant->affectations()->first();

        $this->actingAs($this->directeur)
            ->delete(route('affectations.destroy', ['enseignant' => $enseignant, 'affectation' => $affectation]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($affectation);
    }

    public function test_deleting_a_class_deletes_its_assignments()
    {
        $classe = $this->classe(3);
        $this->affecter($this->enseignant(), $classe, 'Français');

        $this->actingAs($this->directeur)->delete(route('classes.destroy', $classe));

        $this->assertSame(0, Affectation::count());
    }

    public function test_the_teachers_list_shows_classes_subjects_and_hours()
    {
        $enseignant = $this->enseignant(['nom' => 'Hammami']);
        $this->affecter($enseignant, $this->classe(3, ['nom' => '3e année A']), 'Français');
        $this->enseignant(['nom' => 'Ayari']); // sans affectation

        $this->actingAs($this->directeur)
            ->get(route('enseignants.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enseignants.data.1.nom', 'Hammami')
                ->where('enseignants.data.1.classes', ['3e année A'])
                ->where('enseignants.data.1.matieres', ['Français'])
                ->where('enseignants.data.1.minutes', 480)
                ->where('enseignants.data.1.volume_horaire_max', 18));

        $this->actingAs($this->directeur)
            ->get(route('enseignants.index', ['statut' => 'SANS_AFFECTATION']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('enseignants.total', 1)
                ->where('enseignants.data.0.nom', 'Ayari'));
    }

    public function test_the_admin_teacher_form_has_no_volume()
    {
        // L'Admin ne peut pas modifier le volume horaire depuis sa fenêtre enseignant
        $enseignant = User::factory()->enseignant()->create();

        $this->actingAs(User::factory()->admin()->create())->patch(route('enseignants.update', $enseignant), [
            'nom' => $enseignant->nom,
            'prenom' => $enseignant->prenom,
            'email' => $enseignant->email,
            'ecole_id' => $enseignant->ecole_id,
            'volume_horaire_max' => 30,
        ]);

        $this->assertSame(18, $enseignant->refresh()->volume_horaire_max);
    }
}
