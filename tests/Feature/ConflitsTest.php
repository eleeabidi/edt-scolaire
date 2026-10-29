<?php

namespace Tests\Feature;

use App\Enums\Jour;
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
 * T14.4 — Détection des conflits, cause et solution proposée ; vérification d'un déplacement.
 */
class ConflitsTest extends TestCase
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

    private function conflits(): array
    {
        return $this->emploi->detecterConflits();
    }

    private function enseignant(string $prenom): User
    {
        return User::where('prenom', $prenom)->firstOrFail();
    }

    /**
     * Une séance de 2 h (créneau entier) d'un enseignant dans une classe.
     */
    private function seanceDeDeuxHeures(string $prenom, string $classe): Seance
    {
        return $this->emploi->seances()
            ->with(['matieres', 'horaire'])
            ->where('enseignant_id', $this->enseignant($prenom)->id)
            ->where('classe_id', Classe::where('nom', $classe)->value('id'))
            ->get()
            ->first(fn (Seance $s) => $s->matieres->sum(fn ($m) => CreneauHoraire::minutesDecret($m->heure_debut, $m->heure_fin)) === 120);
    }

    /**
     * Place la séance B exactement au même moment que la séance A (modification « à la main »).
     */
    private function placerAuMemeMoment(Seance $b, Seance $a): void
    {
        $b->update(['horaire_id' => $a->horaire_id]);
        $matiere = $b->matieres()->first()->matiere_id;
        $b->matieres()->delete();
        $b->matieres()->create(['matiere_id' => $matiere, 'heure_debut' => $a->matieres->min('heure_debut'), 'heure_fin' => $a->matieres->max('heure_fin')]);
    }

    public function test_the_generated_timetable_has_no_conflict()
    {
        $this->assertSame([], $this->conflits());

        $this->actingAs($this->directeur)
            ->get(route('emploi.conflits', $this->emploi))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('emploi-du-temps/Conflits')
                ->where('annee', '2026-2027')
                ->where('conflits', []));

        $this->actingAs($this->directeur)
            ->get(route('emploi.index', ['annee' => '2026-2027']))
            ->assertInertia(fn (Assert $page) => $page->where('nbConflits', 0));
    }

    public function test_a_teacher_who_is_no_longer_available_is_a_conflict_with_a_free_slot_proposed()
    {
        $seance = $this->seanceDeDeuxHeures('Karim', '3e année A');
        $karim = $this->enseignant('Karim');

        // Karim Jaziri n'est plus disponible sur le créneau de cette séance
        foreach (Horaire::where('id', '!=', $seance->horaire_id)->get() as $horaire) {
            $karim->disponibilites()->create(['jour' => $horaire->jour, 'heure_debut' => $horaire->heure_debut, 'heure_fin' => $horaire->heure_fin]);
        }

        $conflits = collect($this->conflits());
        $this->assertCount(1, $conflits);

        $conflit = $conflits->first();
        $jour = mb_strtolower($seance->horaire->jour->label());
        $this->assertSame('indisponible', $conflit['type']);
        $this->assertSame('Enseignant non disponible', $conflit['libelle']);
        $this->assertSame($seance->id, $conflit['seance']['id']);
        $this->assertStringStartsWith("Karim Jaziri n'est pas disponible le {$jour} de ", $conflit['explication']);
        $this->assertStringStartsWith('déplacer la séance au ', $conflit['solution']);

        // La solution proposée est vraiment possible
        $planning = new PlanningEmploiDuTemps($this->emploi);
        [$horaire, $salle] = $planning->creneauLibre($seance->fresh());
        $this->assertNull($planning->verifierDeplacement($seance->fresh(), $horaire, $salle)['cause']);

        $this->actingAs($this->directeur)
            ->get(route('emploi.index', ['annee' => '2026-2027']))
            ->assertInertia(fn (Assert $page) => $page->where('nbConflits', 1));
    }

    public function test_a_room_out_of_service_is_a_conflict_with_a_free_room_proposed()
    {
        $seance = $this->seanceDeDeuxHeures('Karim', '3e année A');
        $seance->salle->update(['en_service' => false]);

        $conflits = collect($this->conflits())->where('type', 'hors_service');
        $this->assertSame($seance->salle->seances()->count(), $conflits->count());

        $conflit = $conflits->firstWhere('seance.id', $seance->id);
        $this->assertSame('Salle hors service', $conflit['libelle']);
        $this->assertSame("La {$seance->salle->nom} a été mise hors service.", $conflit['explication']);

        // Une salle libre sur le même créneau si possible, sinon un autre créneau dans une salle libre
        $planning = new PlanningEmploiDuTemps($this->emploi);
        foreach ($conflits as $c) {
            $libres = $planning->sallesLibres(Seance::findOrFail($c['seance']['id']));

            if ($libres !== []) {
                $this->assertSame("changer la salle de la séance pour la {$libres[0]->nom}, libre sur ce créneau.", $c['solution']);
            } else {
                $this->assertStringStartsWith('déplacer la séance au ', $c['solution']);
            }
        }
        $this->assertTrue($conflits->contains(fn ($c) => str_starts_with($c['solution'], 'changer la salle')));
    }

    public function test_a_conflict_without_solution_says_so()
    {
        // Plus de salle de sport en service : aucune autre salle possible pour l'éducation physique
        Salle::where('nom', 'Salle de sport')->update(['en_service' => false]);

        $conflits = collect($this->conflits())->where('type', 'hors_service');
        $this->assertCount(8, $conflits);
        $this->assertTrue($conflits->every(fn ($c) => $c['solution'] === null));
    }

    public function test_the_same_teacher_at_the_same_time_is_a_conflict()
    {
        $a = $this->seanceDeDeuxHeures('Karim', '3e année A');
        $b = $this->seanceDeDeuxHeures('Karim', '3e année B');
        $this->placerAuMemeMoment($b, $a);

        $conflit = collect($this->conflits())->firstWhere('type', 'enseignant');
        $this->assertSame('Même enseignant au même moment', $conflit['libelle']);
        $this->assertSame($b->id, $conflit['seance']['id']);
        $this->assertStringStartsWith('Karim Jaziri a aussi une séance en 3e année A le ', $conflit['explication']);
    }

    public function test_the_same_room_or_the_same_class_at_the_same_time_is_a_conflict()
    {
        $a = $this->seanceDeDeuxHeures('Karim', '3e année A');

        // Une autre classe dans la même salle au même moment
        $b = $this->seanceDeDeuxHeures('Rim', '4e année A');
        $this->placerAuMemeMoment($b, $a);
        $b->update(['salle_id' => $a->salle_id]);

        // Une deuxième séance pour la même classe au même moment
        $doublon = Seance::factory()->create([
            'emploi_du_temps_id' => $this->emploi->id,
            'classe_id' => $a->classe_id,
            'horaire_id' => $a->horaire_id,
            'enseignant_id' => $this->enseignant('Nour')->id,
            'salle_id' => Salle::where('nom', 'Salle 8')->value('id'),
        ]);
        $doublon->matieres()->create(['matiere_id' => $a->matieres->first()->matiere_id, 'heure_debut' => $a->matieres->min('heure_debut'), 'heure_fin' => $a->matieres->max('heure_fin')]);

        $conflits = collect($this->conflits());

        $salle = $conflits->firstWhere('type', 'salle');
        $this->assertSame('Même salle au même moment', $salle['libelle']);
        $this->assertContains($salle['seance']['id'], [$a->id, $b->id]);
        $autreClasse = $salle['seance']['id'] === $a->id ? '4e année A' : '3e année A';
        $this->assertStringContainsString("accueille aussi la classe {$autreClasse} le ", $salle['explication']);

        $classe = $conflits->where('type', 'classe')->firstWhere('seance.id', $doublon->id);
        $this->assertSame('Même classe au même moment', $classe['libelle']);
        $this->assertSame($doublon->id, $classe['seance']['id']);
        $this->assertStringStartsWith('La classe 3e année A a aussi une séance avec Karim Jaziri le ', $classe['explication']);
    }

    public function test_moves_are_checked_against_the_rules()
    {
        $planning = new PlanningEmploiDuTemps($this->emploi);
        $samedi = Horaire::where('jour', Jour::Samedi)->where('heure_debut', 'like', '08:00%')->firstOrFail();

        // 3e année B : pas de cours le samedi
        $seance = $this->seanceDeDeuxHeures('Karim', '3e année B');
        $this->assertSame("La classe 3e année B n'a pas cours le samedi.", $planning->verifierDeplacement($seance, $samedi)['cause']);

        // 1re année : un seul bloc de 4 h par jour
        $seance = $this->seanceDeDeuxHeures('Amira', '1re année A');
        $autre = Horaire::all()->first(fn (Horaire $h) => $h->id !== $seance->horaire_id
            && $h->jour !== Jour::Samedi
            && ! str_starts_with($h->heure_debut, '12:00')
            && $planning->verifierDeplacement($seance, $h)['code'] === 'regle');
        $this->assertStringStartsWith('En 1re année, chaque jour a un seul bloc de 4 heures', $planning->verifierDeplacement($seance, $autre)['cause']);

        // Une séance de 2 h ne tient pas de 12 h à 13 h
        $midi = Horaire::where('jour', Jour::Lundi)->where('heure_debut', 'like', '12:00%')->firstOrFail();
        $seance = $this->seanceDeDeuxHeures('Nour', '3e année A');
        $this->assertSame('Une séance de 2 h ne tient pas dans le créneau de 12 h à 13 h.', $planning->verifierDeplacement($seance, $midi)['cause']);

        // Nour Ayari n'est pas disponible le mercredi après-midi (créneau où la classe est libre)
        $seance = $this->seanceDeDeuxHeures('Nour', '3e année B');
        $mercredi = Horaire::where('jour', Jour::Mercredi)->where('heure_debut', '>=', '13:00')->get()
            ->first(fn (Horaire $h) => ! $this->emploi->seances()->where('classe_id', $seance->classe_id)->where('horaire_id', $h->id)->exists());
        $this->assertNotNull($mercredi);
        $fin = CreneauHoraire::lisible(CreneauHoraire::minutes($mercredi->heure_fin));
        $debut = CreneauHoraire::lisible(CreneauHoraire::minutes($mercredi->heure_debut));
        $this->assertSame("Nour Ayari n'est pas disponible le mercredi de {$debut} à {$fin}.", $this->emploi->verifierDisponibilite($seance, $mercredi));
    }

    public function test_only_the_directeur_of_the_school_sees_the_conflicts()
    {
        $autre = User::factory()->directeur()->create();

        $this->actingAs($autre)->get(route('emploi.conflits', $this->emploi))->assertForbidden();
        $this->actingAs($this->enseignant('Karim'))->get(route('emploi.conflits', $this->emploi))->assertForbidden();
    }

    public function test_see_in_the_grid_highlights_the_session()
    {
        $seance = $this->seanceDeDeuxHeures('Karim', '3e année A');

        $this->actingAs($this->directeur)
            ->get(route('emploi.index', ['annee' => '2026-2027', 'par' => 'classe', 'id' => $seance->classe_id, 'seance' => $seance->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('choix', $seance->classe_id)
                ->where('surlignee', $seance->id));
    }
}
