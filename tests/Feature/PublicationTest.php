<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Enums\StatutEmploi;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\EmploiDuTempsPublieNotification;
use App\Notifications\SeanceModifieeNotification;
use App\Services\PlanningEmploiDuTemps;
use App\Support\CreneauHoraire;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T17.5 — Publier l'emploi du temps, le montrer aux enseignants et les notifier.
 */
class PublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    private EmploiDuTemps $emploi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29');
        $this->seed(DatabaseSeeder::class);
        $this->directeur = User::where('email', 'directeur@edt-scolaire.test')->firstOrFail();

        $this->actingAs($this->directeur)
            ->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027'])
            ->assertSessionHasNoErrors();

        $this->emploi = EmploiDuTemps::firstOrFail();
    }

    private function publier()
    {
        return $this->actingAs($this->directeur)->post(route('emploi.publier', $this->emploi));
    }

    private function enseignant(string $prenom): User
    {
        return User::where('prenom', $prenom)->firstOrFail();
    }

    private function seanceDeDeuxHeures(string $prenom, string $classe): Seance
    {
        return $this->emploi->seances()
            ->with('matieres')
            ->where('enseignant_id', $this->enseignant($prenom)->id)
            ->where('classe_id', Classe::where('nom', $classe)->value('id'))
            ->get()
            ->first(fn (Seance $s) => $s->matieres->sum(fn ($m) => CreneauHoraire::minutesDecret($m->heure_debut, $m->heure_fin)) === 120);
    }

    public function test_publishing_makes_the_timetable_visible_and_notifies_every_teacher()
    {
        Notification::fake();
        $desactive = User::factory()->enseignant($this->directeur->ecole)->statut(StatutCompte::Desactive)->create();

        $this->publier()
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', "L'emploi du temps 2026-2027 est publié : chaque enseignant a reçu une notification.");

        $this->assertSame(StatutEmploi::Publie, $this->emploi->fresh()->statut);

        $actifs = $this->directeur->ecole->enseignants()->where('statut', StatutCompte::Actif)->get();
        $this->assertCount(12, $actifs);
        Notification::assertSentTo($actifs, EmploiDuTempsPublieNotification::class);
        Notification::assertNotSentTo($desactive, EmploiDuTempsPublieNotification::class);
        Notification::assertCount(12);
    }

    public function test_the_publication_email_links_to_my_timetable()
    {
        $mail = (new EmploiDuTempsPublieNotification($this->emploi))->toMail($this->enseignant('Karim'));

        $this->assertSame("L'emploi du temps 2026-2027 est publié", $mail->subject);
        $this->assertSame(route('mon-emploi.index'), $mail->actionUrl);
    }

    public function test_a_timetable_with_conflicts_cannot_be_published()
    {
        Notification::fake();
        Salle::where('nom', 'Salle de sport')->update(['en_service' => false]); // 8 conflits

        $this->publier()->assertSessionHasErrors([
            'publication' => "L'emploi du temps contient 8 conflits. Corrigez-les avant de le publier.",
        ]);

        $this->assertSame(StatutEmploi::Brouillon, $this->emploi->fresh()->statut);
        Notification::assertNothingSent();
    }

    public function test_a_published_timetable_is_not_published_twice()
    {
        Notification::fake();
        $this->emploi->publier();

        $this->publier()->assertInertiaFlash('toast.type', 'info');
        Notification::assertNothingSent();
    }

    public function test_only_the_directeur_of_the_school_publishes()
    {
        $this->actingAs(User::factory()->directeur()->create())->post(route('emploi.publier', $this->emploi))->assertForbidden();
        $this->actingAs($this->enseignant('Karim'))->post(route('emploi.publier', $this->emploi))->assertForbidden();

        $this->assertSame(StatutEmploi::Brouillon, $this->emploi->fresh()->statut);
    }

    public function test_teachers_do_not_see_a_draft_timetable()
    {
        $this->actingAs($this->enseignant('Karim'))
            ->get(route('mon-emploi.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('mon-emploi-du-temps/Index')
                ->where('emploi', null)
                ->missing('seances'));
    }

    public function test_teachers_see_their_own_sessions_once_published()
    {
        $this->emploi->publier();
        $karim = $this->enseignant('Karim');

        $this->actingAs($karim)
            ->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('emploi.annee', '2026-2027')
                ->where('emploi.ecole', 'École Les Jasmins')
                ->where('totalMinutes', 1080) // 18 h
                ->where('seances', fn ($seances) => collect($seances)->every(fn ($s) => $s['enseignant'] === 'Karim Jaziri')));

        // Un enseignant d'une autre école ne voit rien
        $this->actingAs(User::factory()->enseignant()->create())
            ->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page->where('emploi', null));
    }

    public function test_only_teachers_have_my_timetable()
    {
        $this->actingAs($this->directeur)->get(route('mon-emploi.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('mon-emploi.index'))->assertForbidden();
    }

    public function test_the_teacher_of_a_moved_session_is_notified_when_the_timetable_is_published()
    {
        Notification::fake();
        $this->emploi->publier();
        $seance = $this->seanceDeDeuxHeures('Karim', '3e année A');
        [$horaire] = (new PlanningEmploiDuTemps($this->emploi))->creneauLibre($seance);

        $this->actingAs($this->directeur)
            ->patch(route('seances.deplacer', $seance), ['horaire_id' => $horaire->id])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'La séance a été déplacée. Karim Jaziri a été prévenu(e) par email.');

        Notification::assertSentTo($this->enseignant('Karim'), SeanceModifieeNotification::class, function (SeanceModifieeNotification $n) {
            return str_starts_with($n->modification, 'Votre séance de ') && str_contains($n->modification, ' en 3e année A a été déplacée du ');
        });
        Notification::assertCount(1);
    }

    public function test_the_teacher_of_a_session_whose_room_changes_is_notified()
    {
        Notification::fake();
        $this->emploi->publier();
        $planning = new PlanningEmploiDuTemps($this->emploi);
        $seance = $this->emploi->seances()->whereHas('salle', fn ($q) => $q->where('type', 'cours'))->get()
            ->first(fn (Seance $s) => $planning->sallesLibres($s) !== []);
        $salle = $planning->sallesLibres($seance)[0];

        $this->actingAs($this->directeur)
            ->patch(route('seances.salle', $seance), ['salle_id' => $salle->id])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($seance->enseignant, SeanceModifieeNotification::class, fn ($n) => str_ends_with($n->modification, "a lieu désormais dans la salle {$salle->nom}."));
    }

    public function test_no_notification_while_the_timetable_is_a_draft()
    {
        Notification::fake();
        $seance = $this->seanceDeDeuxHeures('Karim', '3e année A');
        [$horaire] = (new PlanningEmploiDuTemps($this->emploi))->creneauLibre($seance);

        $this->actingAs($this->directeur)
            ->patch(route('seances.deplacer', $seance), ['horaire_id' => $horaire->id])
            ->assertInertiaFlash('toast.message', 'La séance a été déplacée.');

        Notification::assertNothingSent();
    }
}
