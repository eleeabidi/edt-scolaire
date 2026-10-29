<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Enums\StatutEmploi;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Matiere;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\ProgrammeNationalSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 3, étape 1 — T13.1 à T13.4 (tables et modèles), T13.9 et T13.10 (reportés du sprint 2).
 */
class EmploiDuTempsDonneesTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProgrammeNationalSeeder::class);
        $this->directeur = User::factory()->directeur()->create();
    }

    /**
     * Une séance de l'école du directeur, avec une matière.
     */
    private function seance(array $attributs = []): Seance
    {
        $ecole = $this->directeur->ecole;
        $emploi = EmploiDuTemps::factory()->create(['ecole_id' => $ecole->id]);

        $seance = Seance::factory()->create([
            'emploi_du_temps_id' => $emploi->id,
            'classe_id' => Classe::factory()->niveau(3)->create(['ecole_id' => $ecole->id])->id,
            'enseignant_id' => User::factory()->enseignant($ecole)->create()->id,
            'salle_id' => $ecole->salles()->create(['nom' => 'Salle 1', 'capacite' => 30])->id,
            ...$attributs,
        ]);

        $seance->matieres()->create([
            'matiere_id' => Matiere::where('nom', 'Langue arabe')->value('id'),
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);

        return $seance;
    }

    public function test_a_new_timetable_is_a_draft()
    {
        $emploi = EmploiDuTemps::create(['ecole_id' => $this->directeur->ecole_id, 'annee_scolaire' => '2026-2027']);

        $this->assertSame(StatutEmploi::Brouillon, $emploi->refresh()->statut);
        $this->assertFalse($emploi->estPublie());
        $this->assertSame(['brouillon', 'publie'], array_column(StatutEmploi::cases(), 'value'));
    }

    public function test_a_school_has_only_one_timetable_per_school_year()
    {
        EmploiDuTemps::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'annee_scolaire' => '2026-2027']);
        EmploiDuTemps::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'annee_scolaire' => '2027-2028']);

        $this->expectException(QueryException::class);
        EmploiDuTemps::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'annee_scolaire' => '2026-2027']);
    }

    public function test_a_session_links_a_class_a_teacher_a_room_a_slot_and_its_subjects()
    {
        $seance = $this->seance();

        $this->assertSame($this->directeur->ecole_id, $seance->emploiDuTemps->ecole_id);
        $this->assertSame('08:00:00', $seance->horaire->heure_debut);
        $this->assertSame('Langue arabe', $seance->matieres->first()->matiere->nom);
        $this->assertSame(1, $seance->classe->seances()->count());
        $this->assertSame(1, $seance->enseignant->seances()->count());
        $this->assertSame(1, $seance->salle->seances()->count());
        $this->assertSame(1, $this->directeur->ecole->emploisDuTemps()->count());
    }

    public function test_deleting_a_timetable_deletes_its_sessions_and_their_subjects()
    {
        $seance = $this->seance();

        $seance->emploiDuTemps->delete();

        $this->assertModelMissing($seance);
        $this->assertDatabaseCount('seance_matiere', 0);
    }

    public function test_a_class_used_in_a_timetable_cannot_be_deleted()
    {
        $classe = $this->seance()->classe;

        $this->actingAs($this->directeur)
            ->delete(route('classes.destroy', $classe))
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', "Impossible de supprimer la classe {$classe->nom} : elle est utilisée dans l'emploi du temps 2026-2027.");

        $this->assertModelExists($classe);
    }

    public function test_a_class_without_session_can_still_be_deleted()
    {
        $this->seance();
        $libre = Classe::factory()->create(['ecole_id' => $this->directeur->ecole_id]);

        $this->actingAs($this->directeur)->delete(route('classes.destroy', $libre));

        $this->assertModelMissing($libre);
    }

    public function test_a_teacher_planned_in_a_timetable_cannot_be_deactivated()
    {
        $enseignant = $this->seance()->enseignant;

        foreach ([$this->directeur, User::factory()->admin()->create()] as $utilisateur) {
            $this->actingAs($utilisateur)
                ->delete(route('enseignants.destroy', $enseignant))
                ->assertInertiaFlash('toast.type', 'error')
                ->assertInertiaFlash('toast.message', "Impossible de supprimer {$enseignant->name} : cet enseignant assure des séances dans l'emploi du temps 2026-2027. Modifiez d'abord l'emploi du temps.");
        }

        $this->assertSame(StatutCompte::Actif, $enseignant->refresh()->statut);
    }

    public function test_a_teacher_without_session_can_still_be_deactivated()
    {
        $this->seance();
        $enseignant = User::factory()->enseignant($this->directeur->ecole)->create();

        $this->actingAs($this->directeur)->delete(route('enseignants.destroy', $enseignant));

        $this->assertSame(StatutCompte::Desactive, $enseignant->refresh()->statut);
    }
}
