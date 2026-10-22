<?php

namespace Tests\Feature;

use App\Enums\Jour;
use App\Models\Contrainte;
use App\Models\Horaire;
use App\Models\User;
use App\Services\GenerateurCreneaux;
use Database\Seeders\ProgrammeNationalSeeder;
use Database\Seeders\ReglesNationalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T11.7 — Règles nationales et créneaux générés.
 */
class ReglesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ProgrammeNationalSeeder::class, ReglesNationalesSeeder::class]);
    }

    /**
     * @return array<int, array{string, string}>
     */
    private function creneauxDu(Jour $jour): array
    {
        return Horaire::where('jour', $jour)
            ->orderBy('heure_debut')
            ->get()
            ->map(fn (Horaire $h) => [substr($h->heure_debut, 0, 5), substr($h->heure_fin, 0, 5)])
            ->all();
    }

    public function test_the_national_tables_have_no_school()
    {
        $this->assertFalse(Schema::hasColumn('contraintes', 'ecole_id'));
        $this->assertFalse(Schema::hasColumn('horaires', 'ecole_id'));
    }

    public function test_each_weekday_has_the_five_national_time_slots()
    {
        $attendus = [
            ['08:00', '09:55'],
            ['10:05', '12:00'],
            ['12:00', '13:00'],
            ['13:00', '14:55'],
            ['15:05', '17:00'],
        ];

        foreach ([Jour::Lundi, Jour::Mardi, Jour::Mercredi, Jour::Jeudi, Jour::Vendredi] as $jour) {
            $this->assertSame($attendus, $this->creneauxDu($jour), $jour->label());
        }
    }

    public function test_saturday_is_morning_only()
    {
        // Créneaux 1 et 2, plus le créneau 3 (selon l'école, décidé à la génération du sprint 3)
        $this->assertSame([['08:00', '09:55'], ['10:05', '12:00'], ['12:00', '13:00']], $this->creneauxDu(Jour::Samedi));
        $this->assertSame(28, Horaire::count());
    }

    public function test_the_slots_follow_the_rules()
    {
        // Si la règle change (ex. : journée jusqu'à 16 h), les créneaux générés suivent
        Contrainte::where('type', 'journee')->update(['valeur' => '08:00-16:00']);

        app(GenerateurCreneaux::class)->generer();

        $this->assertSame(['15:05', '16:00'], $this->creneauxDu(Jour::Lundi)[4]);
    }

    public function test_generating_twice_does_not_duplicate_the_slots()
    {
        app(GenerateurCreneaux::class)->generer();

        $this->assertSame(28, Horaire::count());
    }

    public function test_a_level_rule_replaces_the_general_rule()
    {
        // Samedi : règle générale « oui » ; remplacée par « non » en 1re et 2e, « selon la classe » en 3e
        $samedi = fn (int $niveau) => Contrainte::pourNiveau($niveau)->firstWhere('type', 'cours_samedi')->valeur;

        $this->assertSame('non', $samedi(1));
        $this->assertSame('non', $samedi(2));
        $this->assertSame('selon_classe', $samedi(3));
        $this->assertSame('oui', $samedi(4));
        $this->assertSame('oui', $samedi(6));

        // Une seule règle « cours_samedi » par niveau
        $this->assertCount(1, Contrainte::pourNiveau(1)->where('type', 'cours_samedi'));
    }

    public function test_the_official_weekly_totals_are_stored_as_rules()
    {
        foreach ([1 => '1200', 2 => '1320', 3 => '1500', 4 => '1500', 5 => '1680', 6 => '1680'] as $niveau => $minutes) {
            $this->assertSame($minutes, Contrainte::pourNiveau($niveau)->firstWhere('type', 'total_hebdomadaire')->valeur);
        }
    }

    public function test_the_directeur_can_consult_the_rules()
    {
        $this->actingAs(User::factory()->directeur()->create())
            ->get(route('regles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('regles/Index')
                ->where('generales.journee', '08:00-17:00')
                ->where('generales.minimumParMatiere', 30)
                ->has('creneaux', 5)
                ->has('creneauxSamedi', 3)
                ->has('niveaux', 6)
                ->where('niveaux.0.coursSamedi', 'non')
                ->where('niveaux.0.totalMinutes', 1200)
                ->where('niveaux.2.coursSamedi', 'selon_classe')
                ->has('matieresMidi', 4));
    }

    public function test_only_the_directeur_can_consult_the_rules()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('regles.index'))->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create())->get(route('regles.index'))->assertForbidden();
    }
}
