<?php

namespace Tests\Feature;

use App\Enums\Jour;
use App\Enums\TypeSalle;
use App\Models\Classe;
use App\Models\Disponibilite;
use App\Models\Ecole;
use App\Models\Groupe;
use App\Models\Horaire;
use App\Models\Matiere;
use App\Models\Salle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 2, étape 1 : migrations et modèles de US07 à US11 (+ relations T06.2).
 */
class DonneesDeBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_class_belongs_to_a_school_and_contains_groups()
    {
        $classe = Classe::factory()->create();
        Groupe::factory(2)->create(['classe_id' => $classe->id]);

        $this->assertTrue($classe->ecole->is(Ecole::find($classe->ecole_id)));
        $this->assertCount(2, $classe->groupes);
        $this->assertCount(1, $classe->ecole->classes);
    }

    public function test_a_class_can_have_no_group()
    {
        $this->assertCount(0, Classe::factory()->create()->groupes);
    }

    public function test_two_classes_of_the_same_school_cannot_have_the_same_name()
    {
        $ecole = Ecole::factory()->create();
        Classe::factory()->create(['ecole_id' => $ecole->id, 'nom' => '1re A']);

        // Le même nom dans une autre école est autorisé
        Classe::factory()->create(['nom' => '1re A']);

        $this->expectException(QueryException::class);
        Classe::factory()->create(['ecole_id' => $ecole->id, 'nom' => '1re A']);
    }

    public function test_a_subject_is_placed_in_a_normal_room_by_default()
    {
        $matiere = Matiere::create(['ecole_id' => Ecole::factory()->create()->id, 'nom' => 'Lecture']);

        $this->assertSame(TypeSalle::Normale, $matiere->type_salle);
        $this->assertSame(60, $matiere->duree_seance);
        $this->assertSame('normale', $matiere->refresh()->getRawOriginal('type_salle'));
    }

    public function test_a_subject_can_require_a_sport_room()
    {
        $this->assertSame(TypeSalle::Sport, Matiere::factory()->sport()->create()->refresh()->type_salle);
    }

    public function test_the_weekly_volume_of_a_subject_is_stored_per_class()
    {
        $ecole = Ecole::factory()->create();
        $classe = Classe::factory()->create(['ecole_id' => $ecole->id]);
        $matiere = Matiere::factory()->create(['ecole_id' => $ecole->id]);

        $classe->matieres()->attach($matiere->id, ['volume_horaire_hebdo' => 1.5]);

        $this->assertEquals(1.5, $classe->matieres->first()->pivot->volume_horaire_hebdo);
        $this->assertTrue($matiere->classes->first()->is($classe));
    }

    public function test_a_teacher_has_subjects_and_availabilities()
    {
        $enseignant = User::factory()->enseignant()->create();
        $matiere = Matiere::factory()->create(['ecole_id' => $enseignant->ecole_id]);

        $enseignant->matieres()->attach($matiere->id);
        Disponibilite::factory()->create([
            'enseignant_id' => $enseignant->id,
            'jour' => Jour::Mardi,
            'heure_debut' => '08:00',
            'heure_fin' => '12:00',
        ]);

        $this->assertTrue($enseignant->matieres->first()->is($matiere));
        $this->assertTrue($matiere->enseignants->first()->is($enseignant));
        $this->assertSame(Jour::Mardi, $enseignant->disponibilites->first()->jour);
        $this->assertTrue($enseignant->disponibilites->first()->enseignant->is($enseignant));
    }

    public function test_a_room_has_a_type_and_a_capacity()
    {
        $salle = Salle::factory()->sport()->create();

        $this->assertSame(TypeSalle::Sport, $salle->refresh()->type);
        $this->assertSame(60, $salle->capacite);
        $this->assertCount(1, $salle->ecole->salles);
    }

    public function test_a_time_slot_belongs_to_a_school()
    {
        $horaire = Horaire::factory()->create(['jour' => Jour::Lundi, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);

        $this->assertSame(Jour::Lundi, $horaire->refresh()->jour);
        $this->assertCount(1, $horaire->ecole->horaires);
    }

    public function test_deleting_a_class_deletes_its_groups_and_volumes()
    {
        $classe = Classe::factory()->create();
        Groupe::factory()->create(['classe_id' => $classe->id]);
        $classe->matieres()->attach(Matiere::factory()->create(['ecole_id' => $classe->ecole_id])->id, ['volume_horaire_hebdo' => 2]);

        $classe->delete();

        $this->assertDatabaseCount('groupes', 0);
        $this->assertDatabaseCount('classe_matiere', 0);
        $this->assertDatabaseCount('matieres', 1); // la matière reste
    }

    public function test_the_demo_seeder_creates_the_base_data()
    {
        $this->seed();

        $ecole = Ecole::where('nom', 'École Les Jasmins')->first();
        $amira = User::where('email', 'enseignant@edt-scolaire.test')->first();

        $this->assertCount(3, $ecole->classes);
        $this->assertCount(5, $ecole->matieres);
        $this->assertSame(1, $ecole->salles()->where('type', TypeSalle::Sport)->count());
        $this->assertCount(2, $amira->matieres);
        $this->assertCount(5, $amira->disponibilites);
    }
}
