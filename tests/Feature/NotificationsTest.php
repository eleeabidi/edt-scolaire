<?php

namespace Tests\Feature;

use App\Enums\StatutCompte;
use App\Enums\TypeNotification;
use App\Events\NotificationEnvoyee;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Notification;
use App\Models\Seance;
use App\Models\User;
use App\Services\PlanningEmploiDuTemps;
use App\Support\CreneauHoraire;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as Emails;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T20.5 — Notifications enregistrées et diffusées en temps réel sur le canal privé de chaque enseignant.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    private EmploiDuTemps $emploi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-29 09:40');
        $this->seed(DatabaseSeeder::class);
        $this->directeur = User::where('email', 'directeur@edt-scolaire.test')->firstOrFail();

        $this->actingAs($this->directeur)
            ->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027'])
            ->assertSessionHasNoErrors();

        $this->emploi = EmploiDuTemps::firstOrFail();
        Emails::fake();
    }

    private function karim(): User
    {
        return User::where('prenom', 'Karim')->firstOrFail();
    }

    private function seanceDeDeuxHeures(): Seance
    {
        return $this->emploi->seances()
            ->with('matieres')
            ->where('enseignant_id', $this->karim()->id)
            ->where('classe_id', Classe::where('nom', '3e année A')->value('id'))
            ->get()
            ->first(fn (Seance $s) => $s->matieres->sum(fn ($m) => CreneauHoraire::minutesDecret($m->heure_debut, $m->heure_fin)) === 120);
    }

    public function test_publishing_saves_and_broadcasts_a_notification_to_each_teacher()
    {
        Event::fake([NotificationEnvoyee::class]);
        $desactive = User::factory()->enseignant($this->directeur->ecole)->statut(StatutCompte::Desactive)->create();

        $this->actingAs($this->directeur)->post(route('emploi.publier', $this->emploi))->assertSessionHasNoErrors();

        $actifs = $this->directeur->ecole->enseignants()->where('statut', StatutCompte::Actif)->pluck('id');
        $this->assertCount(12, $actifs);
        $this->assertSame(12, Notification::count());

        foreach ($actifs as $id) {
            $notification = Notification::where('enseignant_id', $id)->sole();
            $this->assertSame(TypeNotification::Publication, $notification->type);
            $this->assertSame("L'emploi du temps 2026-2027 de l'école Les Jasmins est publié. Vous pouvez consulter vos séances.", $notification->message);
            $this->assertFalse($notification->lue);
            $this->assertTrue($notification->date_envoi->equalTo(now()));

            Event::assertDispatched(NotificationEnvoyee::class, fn (NotificationEnvoyee $e) => $e->notification->is($notification)
                && $e->broadcastOn()->name === "private-enseignant.{$id}");
        }

        Event::assertDispatchedTimes(NotificationEnvoyee::class, 12);
        $this->assertSame(0, Notification::where('enseignant_id', $desactive->id)->count());
    }

    public function test_only_the_teacher_of_a_modified_session_is_notified()
    {
        $this->emploi->publier();
        Event::fake([NotificationEnvoyee::class]);
        $seance = $this->seanceDeDeuxHeures();
        [$horaire] = (new PlanningEmploiDuTemps($this->emploi))->creneauLibre($seance);

        $this->actingAs($this->directeur)
            ->patch(route('seances.deplacer', $seance), ['horaire_id' => $horaire->id])
            ->assertSessionHasNoErrors();

        $notification = Notification::sole();
        $this->assertSame($this->karim()->id, $notification->enseignant_id);
        $this->assertSame(TypeNotification::Modification, $notification->type);
        $this->assertMatchesRegularExpression('/^Votre séance avec la 3e année A passe du \w+ .+ au \w+ .+\.$/u', $notification->message);

        Event::assertDispatchedTimes(NotificationEnvoyee::class, 1);
        Event::assertDispatched(NotificationEnvoyee::class, fn ($e) => $e->broadcastOn()->name === "private-enseignant.{$this->karim()->id}");
    }

    public function test_changing_the_room_notifies_the_teacher_of_the_session()
    {
        $this->emploi->publier();
        $planning = new PlanningEmploiDuTemps($this->emploi);
        $seance = $this->emploi->seances()->whereHas('salle', fn ($q) => $q->where('type', 'cours'))->get()
            ->first(fn (Seance $s) => $planning->sallesLibres($s) !== []);
        $salle = $planning->sallesLibres($seance)[0];

        $this->actingAs($this->directeur)->patch(route('seances.salle', $seance), ['salle_id' => $salle->id]);

        $notification = Notification::sole();
        $this->assertSame($seance->enseignant_id, $notification->enseignant_id);
        $this->assertStringStartsWith("Votre séance avec la {$seance->classe->nom} du ", $notification->message);
        $this->assertStringEndsWith("a lieu désormais dans la salle {$salle->nom}.", $notification->message);
    }

    public function test_no_notification_while_the_timetable_is_a_draft()
    {
        $seance = $this->seanceDeDeuxHeures();
        [$horaire] = (new PlanningEmploiDuTemps($this->emploi))->creneauLibre($seance);

        $this->actingAs($this->directeur)->patch(route('seances.deplacer', $seance), ['horaire_id' => $horaire->id]);

        $this->assertSame(0, Notification::count());
    }

    public function test_only_the_teacher_can_listen_to_his_channel()
    {
        $autorise = Broadcast::driver()->getChannels()->get('enseignant.{id}');
        $karim = $this->karim();

        $this->assertTrue($autorise($karim, $karim->id));
        $this->assertFalse($autorise(User::where('prenom', 'Sonia')->firstOrFail(), $karim->id));
        $this->assertFalse($autorise($this->directeur, $karim->id));
    }

    public function test_the_unread_count_is_shared_and_opening_the_page_marks_them_read()
    {
        $karim = $this->karim();
        Notification::create(['enseignant_id' => $karim->id, 'type' => TypeNotification::Publication, 'message' => 'Ancienne', 'date_envoi' => now()->subDays(10), 'lue' => true]);
        Notification::create(['enseignant_id' => $karim->id, 'type' => TypeNotification::Modification, 'message' => 'Hier', 'date_envoi' => now()->subDay()->setTime(16, 15)]);
        Notification::create(['enseignant_id' => $karim->id, 'type' => TypeNotification::Modification, 'message' => "Aujourd'hui", 'date_envoi' => now()]);
        Notification::create(['enseignant_id' => User::where('prenom', 'Sonia')->value('id'), 'type' => TypeNotification::Modification, 'message' => 'Autre', 'date_envoi' => now()]);

        $this->actingAs($karim)->get(route('mon-emploi.index'))
            ->assertInertia(fn (Assert $page) => $page->where('notificationsNonLues', 2));

        // De la plus récente à la plus ancienne ; les non lues sont encore « nouvelles » pendant cette consultation
        $this->actingAs($karim)->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/Index')
                ->where('notificationsNonLues', 0)
                ->has('notifications', 3)
                ->where('notifications.0.message', "Aujourd'hui")
                ->where('notifications.0.titre', 'Séance modifiée')
                ->where('notifications.0.date', "Aujourd'hui, 09:40")
                ->where('notifications.0.lue', false)
                ->where('notifications.1.date', 'Hier, 16:15')
                ->where('notifications.1.lue', false)
                ->where('notifications.2.titre', 'Emploi du temps publié')
                ->where('notifications.2.date', '19 oct. 2026')
                ->where('notifications.2.lue', true));

        $this->assertSame(0, $karim->notificationsRecues()->where('lue', false)->count());
        $this->assertSame(1, Notification::where('lue', false)->count()); // celle de Sonia
    }

    public function test_no_notification_yet()
    {
        $this->actingAs($this->karim())->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->has('notifications', 0));
    }

    public function test_only_teachers_have_notifications()
    {
        $this->actingAs($this->directeur)->get(route('notifications.index'))->assertForbidden();
    }

    public function test_the_broadcast_carries_the_notification_and_the_unread_count()
    {
        $karim = $this->karim();
        Event::fake([NotificationEnvoyee::class]);
        Notification::envoyer($karim, TypeNotification::Modification, 'Premier');
        $notification = Notification::envoyer($karim, TypeNotification::Modification, 'Second');

        $donnees = (new NotificationEnvoyee($notification))->broadcastWith();

        $this->assertSame('Second', $donnees['notification']['message']);
        $this->assertSame(2, $donnees['nonLues']);
        $this->assertSame('notification.envoyee', (new NotificationEnvoyee($notification))->broadcastAs());
    }
}
