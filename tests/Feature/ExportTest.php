<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Salle;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T18.5 — Exporter l'emploi du temps publié en PDF ou en Excel.
 */
class ExportTest extends TestCase
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

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function filtres(): array
    {
        return [
            'pdf par classe' => ['pdf', 'classe'],
            'pdf par enseignant' => ['pdf', 'enseignant'],
            'pdf par salle' => ['pdf', 'salle'],
            'excel par classe' => ['excel', 'classe'],
            'excel par enseignant' => ['excel', 'enseignant'],
            'excel par salle' => ['excel', 'salle'],
        ];
    }

    private function choix(string $par): array
    {
        return match ($par) {
            'classe' => [Classe::where('nom', '3e année A')->value('id'), '3e-annee-a'],
            'enseignant' => [User::where('prenom', 'Karim')->value('id'), 'karim-jaziri'],
            'salle' => [Salle::where('nom', 'Salle de sport')->value('id'), 'salle-de-sport'],
        };
    }

    private function exporter(string $format, string $par, ?int $id = null, ?User $qui = null)
    {
        return $this->actingAs($qui ?? $this->directeur)->get(route('emploi.exporter', [
            'emploi' => $this->emploi,
            'format' => $format,
            'par' => $par,
            'id' => $id ?? $this->choix($par)[0],
        ]));
    }

    #[DataProvider('filtres')]
    public function test_a_published_timetable_is_exported(string $format, string $par)
    {
        $this->emploi->publier();
        $extension = $format === 'excel' ? 'xlsx' : 'pdf';

        $reponse = $this->exporter($format, $par)
            ->assertOk()
            ->assertDownload("emploi-du-temps-2026-2027-{$this->choix($par)[1]}.{$extension}");

        if ($format === 'pdf') {
            $this->assertStringStartsWith('%PDF', $reponse->baseResponse->getContent());
        }
    }

    public function test_the_excel_file_follows_the_grid()
    {
        $this->emploi->publier();
        $reponse = $this->exporter('excel', 'classe');

        $reader = new Reader;
        $reader->open($reponse->baseResponse->getFile()->getPathname());
        $lignes = [];
        foreach ($reader->getSheetIterator() as $feuille) {
            foreach ($feuille->getRowIterator() as $ligne) {
                $lignes[] = $ligne->toArray();
            }
        }
        $reader->close();

        // En-tête : l'école (le lecteur saute la ligne vide), l'année scolaire et la classe choisie
        $this->assertSame('École Les Jasmins · année scolaire 2026-2027', $lignes[1][0]);
        $this->assertSame('Classe : 3e année A', $lignes[2][0]);
        $this->assertSame(['Créneau', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'], $lignes[3]);

        // Les créneaux en lignes, avec les récréations et midi
        $premiereColonne = array_column(array_slice($lignes, 4), 0);
        $this->assertSame([
            '8 h – 9 h 55',
            'Récréation · 9 h 55 – 10 h 05',
            '10 h 05 – 12 h',
            "12 h – 13 h\nMidi",
            '13 h – 14 h 55',
            'Récréation · 14 h 55 – 15 h 05',
            '15 h 05 – 17 h',
        ], $premiereColonne);

        // Samedi après-midi (et samedi midi) : « Pas de cours »
        $this->assertSame('Pas de cours', $lignes[7][6]);
        $this->assertSame('Pas de cours', $lignes[8][6]);
        $this->assertSame('Pas de cours', $lignes[10][6]);

        // Chaque case : le même contenu que la grille (« enseignant · salle », puis les matières avec leurs horaires)
        $lundi = $lignes[4][1];
        $this->assertMatchesRegularExpression('/^.+ · .+\n.+ · 8 h – /u', $lundi);
    }

    public function test_a_draft_is_not_exported()
    {
        $this->exporter('pdf', 'classe')->assertForbidden();
        $this->exporter('excel', 'classe')->assertForbidden();
    }

    public function test_another_directeur_and_a_teacher_cannot_export()
    {
        $this->emploi->publier();

        $this->exporter('pdf', 'classe', qui: User::factory()->directeur()->create())->assertForbidden();
        $this->exporter('pdf', 'classe', qui: User::where('prenom', 'Karim')->firstOrFail())->assertForbidden();
    }

    public function test_a_class_of_another_school_is_not_exported()
    {
        $this->emploi->publier();
        $autre = Classe::factory()->create();

        $this->exporter('pdf', 'classe', $autre->id)->assertNotFound();
    }

    public function test_an_unknown_format_is_refused()
    {
        $this->emploi->publier();

        $this->getJson(route('emploi.exporter', [
            'emploi' => $this->emploi, 'format' => 'docx', 'par' => 'classe', 'id' => $this->choix('classe')[0],
        ]))->assertUnprocessable();
    }
}
