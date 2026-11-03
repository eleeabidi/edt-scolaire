<?php

namespace Tests\Feature;

use App\Enums\Jour;
use App\Enums\StatutDemande;
use App\Models\Affectation;
use App\Models\DemandeIndisponibilite;
use App\Models\Disponibilite;
use App\Models\EmploiDuTemps;
use App\Models\Horaire;
use App\Models\Seance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T21.7 — Signaler une indisponibilité (enseignant) et traiter les demandes (directeur).
 */
class DemandesIndisponibiliteTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    private User $karim;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-30');
        $this->seed(DatabaseSeeder::class);
        $this->directeur = User::where('email', 'directeur@edt-scolaire.test')->firstOrFail();
        $this->karim = User::where('prenom', 'Karim')->firstOrFail();
    }

    private function demande(User $enseignant, Jour $jour, string $debut, string $fin, StatutDemande $statut = StatutDemande::EnAttente, string $envoi = '2026-10-30'): DemandeIndisponibilite
    {
        return $enseignant->demandesIndisponibilite()->create([
            'jour' => $jour,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'motif' => 'Stage de formation régionale',
            'date_envoi' => $envoi,
            'statut' => $statut,
        ]);
    }

    private function emploiPublie(): EmploiDuTemps
    {
        $this->actingAs($this->directeur)
            ->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027'])
            ->assertSessionHasNoErrors();

        $emploi = EmploiDuTemps::firstOrFail();
        $emploi->publier();

        return $emploi;
    }

    // --- Signaler une indisponibilité (enseignant) ---

    public function test_a_valid_request_is_saved_as_pending()
    {
        $this->actingAs($this->karim)
            ->post(route('mes-demandes.store'), ['jour' => 3, 'heure_debut' => '08:00', 'heure_fin' => '09:55', 'motif' => 'Stage de formation régionale'])
            ->assertSessionHasNoErrors();

        $demande = DemandeIndisponibilite::sole();
        $this->assertSame($this->karim->id, $demande->enseignant_id);
        $this->assertSame(Jour::Mercredi, $demande->jour);
        $this->assertSame(StatutDemande::EnAttente, $demande->statut);
        $this->assertSame('2026-10-30', $demande->date_envoi->toDateString());

        $this->actingAs($this->karim)->get(route('mes-demandes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('mes-demandes/Index')
                ->has('demandes', 1)
                ->where('demandes.0.jour', 'Mercredi')
                ->where('demandes.0.horaire', '8 h – 9 h 55')
                ->where('demandes.0.statutLibelle', 'En attente')
                ->where('demandes.0.dateEnvoi', '30/10/2026')
                // Le samedi : seulement les créneaux de la grille des disponibilités (le matin)
                ->where('jours.5.debuts', ['08:00', '10:05', '12:00'])
                ->where('jours.5.fins', ['09:55', '12:00', '13:00']));
    }

    public function test_a_request_covering_several_slots_is_accepted()
    {
        $this->actingAs($this->karim)
            ->post(route('mes-demandes.store'), ['jour' => 4, 'heure_debut' => '08:00', 'heure_fin' => '12:00', 'motif' => 'Rendez-vous médical'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DemandeIndisponibilite::count());
    }

    public function test_empty_fields_are_refused()
    {
        $this->actingAs($this->karim)
            ->post(route('mes-demandes.store'), ['jour' => '', 'heure_debut' => '', 'heure_fin' => '', 'motif' => ''])
            ->assertSessionHasErrors(['jour', 'heure_debut', 'heure_fin', 'motif']);

        $this->assertSame(0, DemandeIndisponibilite::count());
    }

    public function test_the_end_must_be_after_the_start()
    {
        $this->actingAs($this->karim)
            ->post(route('mes-demandes.store'), ['jour' => 1, 'heure_debut' => '10:05', 'heure_fin' => '09:55', 'motif' => 'Motif'])
            ->assertSessionHasErrors(['heure_fin' => "L'heure de fin doit être après l'heure de début."]);

        $this->assertSame(0, DemandeIndisponibilite::count());
    }

    public function test_hours_must_be_slots_of_the_school()
    {
        // 8 h 30 n'est pas un créneau ; le samedi après-midi n'a pas de cours
        $this->actingAs($this->karim)
            ->post(route('mes-demandes.store'), ['jour' => 1, 'heure_debut' => '08:30', 'heure_fin' => '09:55', 'motif' => 'Motif'])
            ->assertSessionHasErrors('heure_debut');
        $this->actingAs($this->karim)
            ->post(route('mes-demandes.store'), ['jour' => 6, 'heure_debut' => '13:00', 'heure_fin' => '14:55', 'motif' => 'Motif'])
            ->assertSessionHasErrors('heure_debut');

        $this->assertSame(0, DemandeIndisponibilite::count());
    }

    public function test_a_teacher_sees_only_his_requests()
    {
        $this->demande($this->karim, Jour::Lundi, '08:00', '09:55', envoi: '2026-10-20');
        $this->demande($this->karim, Jour::Mardi, '08:00', '09:55', StatutDemande::Acceptee, '2026-10-29');
        $this->demande(User::where('prenom', 'Sonia')->firstOrFail(), Jour::Mardi, '08:00', '09:55');

        $this->actingAs($this->karim)->get(route('mes-demandes.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('demandes', 2)
                ->where('demandes.0.jour', 'Mardi') // la plus récente d'abord
                ->where('demandes.0.statutLibelle', 'Acceptée'));
    }

    // --- Traiter les demandes (directeur) ---

    public function test_the_directeur_sees_the_requests_of_his_school_pending_first()
    {
        $this->demande($this->karim, Jour::Vendredi, '15:05', '17:00', StatutDemande::Refusee, '2026-10-08');
        $this->demande($this->karim, Jour::Samedi, '10:05', '12:00', StatutDemande::Acceptee, '2026-10-29');
        $this->demande($this->karim, Jour::Jeudi, '08:00', '12:00', envoi: '2026-10-20');
        $this->demande($this->karim, Jour::Mercredi, '08:00', '09:55', envoi: '2026-10-30');
        $this->demande(User::factory()->enseignant()->create(), Jour::Lundi, '08:00', '09:55');

        $this->actingAs($this->directeur)->get(route('demandes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('demandes/Index')
                ->where('annee', '2026-2027')
                ->has('demandes', 4)
                ->where('demandes.0.jour', 'Mercredi')
                ->where('demandes.0.enseignant', 'Karim Jaziri')
                ->where('demandes.0.initiales', 'KJ')
                ->where('demandes.1.jour', 'Jeudi')
                ->where('demandes.2.statut', 'acceptee')
                ->where('demandes.3.statut', 'refusee'));
    }

    public function test_refusing_keeps_the_availabilities()
    {
        $demande = $this->demande($this->karim, Jour::Mercredi, '08:00', '09:55');

        $this->actingAs($this->directeur)->patch(route('demandes.refuser', $demande))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame(StatutDemande::Refusee, $demande->fresh()->statut);
        $this->assertSame(0, $this->karim->disponibilites()->count()); // toujours disponible partout
    }

    public function test_accepting_removes_the_slots_from_the_availabilities()
    {
        $demande = $this->demande($this->karim, Jour::Jeudi, '08:00', '12:00');

        $this->actingAs($this->directeur)->patch(route('demandes.accepter', $demande))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'La demande de Karim Jaziri est acceptée : ses disponibilités ont été mises à jour.');

        $this->assertSame(StatutDemande::Acceptee, $demande->fresh()->statut);
        $cles = Disponibilite::clesDisponibles($this->karim);
        $this->assertCount(Horaire::count() - 2, $cles);
        $this->assertNotContains('4-08:00', $cles);
        $this->assertNotContains('4-10:05', $cles);
        $this->assertContains('4-12:00', $cles);
    }

    public function test_insufficient_hours_need_a_confirmation()
    {
        // Karim garde juste assez de créneaux disponibles pour ses heures affectées
        $affectees = Affectation::minutesPourEnseignant($this->karim->id);
        $garder = [];
        $total = 0;
        foreach (Disponibilite::creneaux() as $c) {
            if ($total >= $affectees) {
                break;
            }
            $garder[] = $c['cle'];
            $total += $c['minutes'];
        }
        Disponibilite::enregistrer($this->karim, array_values(array_diff(array_column(Disponibilite::creneaux(), 'cle'), $garder)));
        $avant = Disponibilite::clesDisponibles($this->karim);

        $demande = $this->demande($this->karim, Jour::Lundi, '08:00', '09:55');

        $this->actingAs($this->directeur)->patch(route('demandes.accepter', $demande))
            ->assertSessionHasErrors([
                'confirme' => 'Si vous acceptez cette demande, les heures disponibles de Karim Jaziri deviennent inférieures à ses heures affectées.',
                'heuresAffectees' => '18 h',
                'heuresDisponibles' => '16 h',
            ]);

        // Rien ne change sans confirmation
        $this->assertSame(StatutDemande::EnAttente, $demande->fresh()->statut);
        $this->assertSame($avant, Disponibilite::clesDisponibles($this->karim));

        $this->actingAs($this->directeur)->patch(route('demandes.accepter', $demande), ['confirme' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatutDemande::Acceptee, $demande->fresh()->statut);
        $this->assertNotContains('1-08:00', Disponibilite::clesDisponibles($this->karim));
    }

    public function test_touched_sessions_are_returned_and_appear_in_the_conflicts()
    {
        $emploi = $this->emploiPublie();
        $seance = $emploi->seances()->where('enseignant_id', $this->karim->id)->with('horaire')->firstOrFail();
        $horaire = $seance->horaire;
        $demande = $this->demande($this->karim, $horaire->jour, substr($horaire->heure_debut, 0, 5), substr($horaire->heure_fin, 0, 5));

        $touchees = $emploi->seances()->where('enseignant_id', $this->karim->id)->where('horaire_id', $horaire->id)->pluck('id')->sort()->values()->all();

        $reponse = $this->actingAs($this->directeur)->patch(route('demandes.accepter', $demande))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('seancesTouchees.enseignant', 'Karim Jaziri')
            ->assertInertiaFlash('seancesTouchees.emploiId', $emploi->id);

        $renvoyees = $demande->fresh()->chercherSeancesTouchees();
        $this->assertSame($touchees, $renvoyees->pluck('id')->sort()->values()->all());
        foreach ($renvoyees->values() as $i => $s) {
            $reponse->assertInertiaFlash("seancesTouchees.seances.{$i}.id", $s->id);
        }

        // Elles apparaissent dans les conflits « enseignant non disponible »
        $indisponibles = collect($emploi->detecterConflits())->where('type', 'indisponible')->pluck('seance.id')->sort()->values()->all();
        $this->assertSame($touchees, $indisponibles);
    }

    public function test_a_draft_timetable_has_no_touched_sessions()
    {
        $this->actingAs($this->directeur)->post(route('emploi.generer'), ['annee_scolaire' => '2026-2027']);
        $seance = Seance::where('enseignant_id', $this->karim->id)->with('horaire')->firstOrFail();
        $demande = $this->demande($this->karim, $seance->horaire->jour, substr($seance->horaire->heure_debut, 0, 5), substr($seance->horaire->heure_fin, 0, 5));

        $this->actingAs($this->directeur)->patch(route('demandes.accepter', $demande))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertCount(0, $demande->fresh()->chercherSeancesTouchees());
    }

    public function test_a_processed_request_cannot_change()
    {
        $refusee = $this->demande($this->karim, Jour::Mercredi, '08:00', '09:55', StatutDemande::Refusee);

        $this->actingAs($this->directeur)->patch(route('demandes.accepter', $refusee))
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($this->directeur)->patch(route('demandes.refuser', $refusee));

        $this->assertSame(StatutDemande::Refusee, $refusee->fresh()->statut);
        $this->assertSame(0, $this->karim->disponibilites()->count());
    }

    public function test_only_the_directeur_of_the_school_processes_a_request()
    {
        $demande = $this->demande($this->karim, Jour::Mercredi, '08:00', '09:55');

        $this->actingAs(User::factory()->directeur()->create())->patch(route('demandes.accepter', $demande))->assertForbidden();
        $this->actingAs(User::factory()->directeur()->create())->patch(route('demandes.refuser', $demande))->assertForbidden();
        $this->actingAs($this->karim)->patch(route('demandes.accepter', $demande))->assertForbidden();
        $this->actingAs($this->karim)->get(route('demandes.index'))->assertForbidden();
        $this->actingAs($this->directeur)->get(route('mes-demandes.index'))->assertForbidden();

        $this->assertSame(StatutDemande::EnAttente, $demande->fresh()->statut);
    }
}
