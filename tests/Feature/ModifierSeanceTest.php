<?php

namespace Tests\Feature;

use App\Enums\Jour;
use App\Enums\StatutEmploi;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Horaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\User;
use App\Services\PlanningEmploiDuTemps;
use App\Support\CreneauHoraire;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T16.5 — Modifier une séance : la déplacer (glisser-déposer) ou changer sa salle.
 */
class ModifierSeanceTest extends TestCase
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

    private function seance(string $prenom, string $classe, int $minutes = 120): Seance
    {
        return $this->emploi->seances()
            ->with(['matieres', 'horaire'])
            ->where('enseignant_id', User::where('prenom', $prenom)->value('id'))
            ->where('classe_id', Classe::where('nom', $classe)->value('id'))
            ->get()
            ->first(fn (Seance $s) => $s->matieres->sum(fn ($m) => CreneauHoraire::minutesDecret($m->heure_debut, $m->heure_fin)) === $minutes);
    }

    private function deplacer(Seance $seance, Horaire $horaire)
    {
        return $this->actingAs($this->directeur)->patch(route('seances.deplacer', $seance), ['horaire_id' => $horaire->id]);
    }

    /**
     * @return array<int, array{0: int, 1: string, 2: string}> matière, début, fin
     */
    private function matieres(Seance $seance): array
    {
        return $seance->matieres()->orderBy('heure_debut')->get()
            ->map(fn ($m) => [$m->matiere_id, substr($m->heure_debut, 0, 5), substr($m->heure_fin, 0, 5)])
            ->all();
    }

    public function test_a_session_is_moved_to_a_free_slot()
    {
        $seance = $this->seance('Karim', '3e année A');
        $avant = collect($this->matieres($seance));
        [$horaire] = (new PlanningEmploiDuTemps($this->emploi))->creneauLibre($seance);

        $this->deplacer($seance, $horaire)
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'La séance a été déplacée.');

        $seance->refresh();
        $this->assertSame($horaire->id, $seance->horaire_id);

        // Les matières gardent leur ordre et leur durée, dans le nouveau créneau
        $apres = collect($this->matieres($seance));
        $this->assertSame($avant->pluck(0)->all(), $apres->pluck(0)->all());
        $duree = fn ($m) => CreneauHoraire::minutesDecret($m[1], $m[2]);
        $this->assertSame($avant->map($duree)->all(), $apres->map($duree)->all());
        $this->assertSame(substr($horaire->heure_debut, 0, 5), $apres->first()[1]);

        $this->assertSame([], $this->emploi->detecterConflits());
    }

    public function test_a_move_creating_a_conflict_is_refused_and_the_session_stays_in_place()
    {
        $seance = $this->seance('Karim', '3e année A');
        $avant = $this->matieres($seance);

        // Un créneau où la classe a déjà une séance
        $autre = $this->emploi->seances()->where('classe_id', $seance->classe_id)->where('horaire_id', '!=', $seance->horaire_id)->first();

        $this->deplacer($seance, $autre->horaire)->assertSessionHasErrors('deplacement');

        $erreur = session('errors')->get('deplacement')[0];
        $this->assertStringStartsWith('La classe 3e année A a déjà une séance le ', $erreur);
        $this->assertStringEndsWith('La séance reste à sa place.', $erreur);
        $this->assertSame($avant, $this->matieres($seance->fresh()));
    }

    public function test_a_move_to_a_slot_where_the_teacher_is_not_available_is_refused()
    {
        $seance = $this->seance('Nour', '3e année B');
        $mercredi = Horaire::where('jour', Jour::Mercredi)->where('heure_debut', '>=', '13:00')->get()
            ->first(fn (Horaire $h) => ! $this->emploi->seances()->where('classe_id', $seance->classe_id)->where('horaire_id', $h->id)->exists());
        $debut = CreneauHoraire::lisible(CreneauHoraire::minutes($mercredi->heure_debut));
        $fin = CreneauHoraire::lisible(CreneauHoraire::minutes($mercredi->heure_fin));

        $this->deplacer($seance, $mercredi)->assertSessionHasErrors([
            'deplacement' => "Nour Ayari n'est pas disponible le mercredi de {$debut} à {$fin}. La séance reste à sa place.",
        ]);
    }

    public function test_a_move_breaking_the_rules_of_the_level_is_refused()
    {
        // 3e année B : pas de cours le samedi
        $samedi = Horaire::where('jour', Jour::Samedi)->where('heure_debut', 'like', '08:00%')->firstOrFail();
        $this->deplacer($this->seance('Karim', '3e année B'), $samedi)->assertSessionHasErrors([
            'deplacement' => "La classe 3e année B n'a pas cours le samedi. La séance reste à sa place.",
        ]);

        // 2 h de mathématiques ne vont pas de 12 h à 13 h
        $midi = Horaire::where('jour', Jour::Lundi)->where('heure_debut', 'like', '12:00%')->firstOrFail();
        $this->deplacer($this->seance('Mehdi', '3e année A'), $midi)->assertSessionHasErrors([
            'deplacement' => 'Une séance de 2 h ne tient pas dans le créneau de 12 h à 13 h. La séance reste à sa place.',
        ]);
    }

    public function test_the_room_of_a_session_is_changed()
    {
        // Une séance de salle de cours qui a au moins une autre salle libre sur son créneau
        $planning = new PlanningEmploiDuTemps($this->emploi);
        $seance = $this->emploi->seances()->whereHas('salle', fn ($q) => $q->where('type', 'cours'))->get()
            ->first(fn (Seance $s) => $planning->sallesLibres($s) !== []);
        $this->assertNotNull($seance);
        $libres = $planning->sallesLibres($seance);

        // La liste proposée : la salle actuelle, puis les salles libres
        $this->actingAs($this->directeur)
            ->get(route('emploi.index', ['annee' => '2026-2027', 'salles_pour' => $seance->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('sallesPossibles') // chargées seulement à l'ouverture de la fenêtre
                ->reloadOnly('sallesPossibles', fn (Assert $rechargement) => $rechargement
                    ->where('sallesPossibles.0.id', $seance->salle_id)
                    ->where('sallesPossibles.1.id', $libres[0]->id)));

        $this->actingAs($this->directeur)
            ->patch(route('seances.salle', $seance), ['salle_id' => $libres[0]->id])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'La salle de la séance a été changée.');

        $this->assertSame($libres[0]->id, $seance->fresh()->salle_id);
        $this->assertSame([], $this->emploi->detecterConflits());
    }

    public function test_a_wrong_room_is_refused()
    {
        $seance = $this->seance('Karim', '3e année A');

        // Une salle de sport pour de l'arabe
        $this->actingAs($this->directeur)
            ->patch(route('seances.salle', $seance), ['salle_id' => Salle::where('nom', 'Salle de sport')->value('id')])
            ->assertSessionHasErrors(['salle_id' => "La Salle de sport n'est pas une salle de cours."]);

        // Une salle déjà occupée par une autre classe au même moment
        $occupee = $this->emploi->seances()->where('horaire_id', $seance->horaire_id)->where('classe_id', '!=', $seance->classe_id)
            ->whereHas('salle', fn ($q) => $q->where('type', 'cours')->where('capacite', '>=', 32))->first();
        if ($occupee !== null) {
            $this->actingAs($this->directeur)
                ->patch(route('seances.salle', $seance), ['salle_id' => $occupee->salle_id])
                ->assertSessionHasErrors('salle_id');
        }

        // Une salle d'une autre école
        $autre = Salle::factory()->create();
        $this->actingAs($this->directeur)
            ->patch(route('seances.salle', $seance), ['salle_id' => $autre->id])
            ->assertSessionHasErrors(['salle_id' => "Cette salle n'existe pas dans votre école."]);

        $this->assertSame($seance->salle_id, $seance->fresh()->salle_id);
    }

    public function test_a_published_timetable_can_still_be_modified()
    {
        $this->emploi->update(['statut' => StatutEmploi::Publie]);
        $seance = $this->seance('Karim', '3e année A');
        [$horaire] = (new PlanningEmploiDuTemps($this->emploi))->creneauLibre($seance);

        $this->deplacer($seance, $horaire)->assertSessionHasNoErrors();
        $this->assertSame($horaire->id, $seance->fresh()->horaire_id);
    }

    public function test_only_the_directeur_of_the_school_modifies_a_session()
    {
        $seance = $this->seance('Karim', '3e année A');
        $horaire = Horaire::firstOrFail();

        $this->actingAs(User::factory()->directeur()->create())
            ->patch(route('seances.deplacer', $seance), ['horaire_id' => $horaire->id])
            ->assertForbidden();
        $this->actingAs(User::where('prenom', 'Karim')->firstOrFail())
            ->patch(route('seances.salle', $seance), ['salle_id' => $seance->salle_id])
            ->assertForbidden();
    }
}
