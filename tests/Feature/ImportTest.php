<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Enums\TypeSalle;
use App\Models\Classe;
use App\Models\Salle;
use App\Models\User;
use App\Notifications\CompteCreeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * T12.6 — Import des enseignants, des classes et des salles.
 */
class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directeur = User::factory()->directeur()->create();
    }

    private function csv(string $nom, string $contenu): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, $contenu);
    }

    /**
     * @param  array<int, array<int, string|int>>  $lignes
     */
    private function xlsx(string $nom, array $lignes): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'test').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($chemin);
        foreach ($lignes as $ligne) {
            $writer->addRow(Row::fromValues($ligne));
        }
        $writer->close();

        return new UploadedFile($chemin, $nom, null, null, true);
    }

    private function importer(string $type, UploadedFile $fichier)
    {
        return $this->actingAs($this->directeur)->post(route('import.store'), ['type' => $type, 'fichier' => $fichier]);
    }

    public function test_only_the_directeur_can_import()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('import.index'))->assertForbidden();
        $this->actingAs(User::factory()->enseignant()->create())->get(route('import.index'))->assertForbidden();
        $this->actingAs($this->directeur)->get(route('import.index'))->assertOk();
    }

    public function test_templates_can_be_downloaded()
    {
        foreach (['enseignants' => 'email', 'classes' => 'cours le samedi', 'salles' => 'capacite'] as $type => $colonne) {
            $reponse = $this->actingAs($this->directeur)->get(route('import.modele', $type));
            $reponse->assertOk()->assertDownload("modele-{$type}.xlsx");

            $reader = new Reader;
            $reader->open($reponse->getFile()->getPathname());
            foreach ($reader->getSheetIterator() as $feuille) {
                foreach ($feuille->getRowIterator() as $ligne) {
                    $this->assertContains($colonne, $ligne->toArray());
                    break;
                }
            }
            $reader->close();
        }

        $this->actingAs($this->directeur)->get(route('import.modele', 'directeurs'))->assertNotFound();
    }

    public function test_teachers_are_imported_as_active_accounts_of_the_school_with_a_temporary_password()
    {
        Notification::fake();

        $this->importer('enseignants', $this->csv('enseignants.csv', "nom;prénom;email;téléphone\nHaddad;Leila;leila@example.com;22 111 222\nBouzid;Mehdi;mehdi@example.com;\n"))
            ->assertRedirect(route('import.rapport'));

        $leila = User::where('email', 'leila@example.com')->first();
        $this->assertSame(Role::Enseignant, $leila->role);
        $this->assertSame(StatutCompte::Actif, $leila->statut);
        $this->assertSame($this->directeur->ecole_id, $leila->ecole_id);
        $this->assertSame('22 111 222', $leila->telephone);
        $this->assertNull(User::where('email', 'mehdi@example.com')->value('telephone'));

        Notification::assertSentTo($leila, fn (CompteCreeNotification $n) => Hash::check($n->motDePasseTemporaire, $leila->password));
        Notification::assertCount(2);
    }

    public function test_the_report_lists_the_lines_in_error()
    {
        Notification::fake();
        User::factory()->enseignant()->statut(StatutCompte::Desactive)->create(['email' => 'karim@example.com']);

        $contenu = "nom;prenom;email;telephone\n"
            ."Haddad;Leila;leila@example.com;\n"   // ligne 2 : importée
            ."Jlassi;Karim;karim@example.com;\n"   // ligne 3 : email déjà utilisé (compte désactivé)
            .";;;\n"                                // ligne 4 : vide, ignorée
            ."Ayari;Nour;;\n"                       // ligne 5 : email vide
            ."Saidi;Rim;leila@example.com;\n";     // ligne 6 : email en double dans le fichier

        $this->importer('enseignants', $this->csv('enseignants.csv', $contenu))
            ->assertSessionHas('rapport', fn (array $rapport) => $rapport['lignesLues'] === 4
                && $rapport['importees'] === 1
                && $rapport['erreurs'] === [
                    ['ligne' => 3, 'donnee' => 'karim@example.com', 'erreur' => 'Email déjà utilisé par un autre compte'],
                    ['ligne' => 5, 'donnee' => '(email vide)', 'erreur' => 'Champ obligatoire vide : email'],
                    ['ligne' => 6, 'donnee' => 'leila@example.com', 'erreur' => 'Email déjà utilisé par un autre compte'],
                ]);

        $this->actingAs($this->directeur)
            ->withSession(['rapport' => ['type' => 'enseignants', 'fichier' => 'enseignants.csv', 'lignesLues' => 4, 'importees' => 1, 'emailsNonEnvoyes' => 0, 'erreurs' => []]])
            ->get(route('import.rapport'))
            ->assertInertia(fn (Assert $page) => $page->component('import/Rapport')->where('rapport.importees', 1));
    }

    public function test_the_report_page_without_import_goes_back_to_the_import_page()
    {
        $this->actingAs($this->directeur)->get(route('import.rapport'))->assertRedirect(route('import.index'));
    }

    public function test_classes_are_imported_with_the_saturday_rule()
    {
        $this->importer('classes', $this->xlsx('classes.xlsx', [
            ['nom', 'niveau', 'effectif', 'cours_samedi'],
            ['1re année A', 1, 28, 'oui'],      // toujours non en 1re année
            ['3e année A', '3e année', 30, 'oui'], // choix en 3e année
            ['3e année B', 3, 31, 'non'],
            ['5e année A', 5, 29, 'non'],       // toujours oui en 5e année
            ['7e année', 7, 30, ''],            // niveau invalide
        ]))->assertSessionHas('rapport', fn (array $r) => $r['importees'] === 4
            && $r['erreurs'][0]['ligne'] === 6
            && $r['erreurs'][0]['erreur'] === 'Le niveau doit être compris entre 1 et 6');

        $samedi = fn (string $nom) => (bool) Classe::where('nom', $nom)->value('cours_samedi');
        $this->assertFalse($samedi('1re année A'));
        $this->assertTrue($samedi('3e année A'));
        $this->assertFalse($samedi('3e année B'));
        $this->assertTrue($samedi('5e année A'));
        $this->assertSame($this->directeur->ecole_id, Classe::where('nom', '3e année A')->value('ecole_id'));
    }

    public function test_rooms_are_imported_from_a_comma_separated_file()
    {
        Salle::factory()->create(['ecole_id' => $this->directeur->ecole_id, 'nom' => 'Salle 1']);

        $this->importer('salles', $this->csv('salles.csv', "nom,type,capacité\nSalle 2,Cours,30\nGymnase,sport,60\nSalle info,technologie,30\nLabo,laboratoire,20\nSalle 1,cours,30\n"))
            ->assertSessionHas('rapport', fn (array $r) => $r['importees'] === 3
                && $r['erreurs'][0]['erreur'] === 'Le type doit être « cours », « sport » ou « technologie »'
                && $r['erreurs'][1]['erreur'] === 'Une salle de ce nom existe déjà dans votre école');

        $this->assertSame(TypeSalle::Cours, Salle::where('nom', 'Salle 2')->first()->type);
        $this->assertSame(TypeSalle::Sport, Salle::where('nom', 'Gymnase')->first()->type);
        $this->assertSame(TypeSalle::Technologie, Salle::where('nom', 'Salle info')->first()->type);
        $this->assertTrue(Salle::where('nom', 'Salle info')->first()->en_service); // en service
    }

    public function test_the_classes_template_header_cours_le_samedi_is_accepted()
    {
        $this->importer('classes', $this->csv('classes.csv', "Nom;niveau;effectif;cours le samedi\n3e année A;3;30;oui\n"))
            ->assertSessionHas('rapport', fn (array $r) => $r['importees'] === 1);

        $this->assertTrue((bool) Classe::where('nom', '3e année A')->value('cours_samedi'));
    }

    public function test_an_empty_file_is_refused()
    {
        $this->importer('salles', $this->csv('salles.csv', ''))
            ->assertSessionHasErrors(['fichier' => 'Le fichier est vide : remplissez le modèle, puis envoyez-le à nouveau.']);

        // Seulement la ligne de titres
        $this->importer('salles', $this->csv('salles.csv', "nom,type,capacite\n"))
            ->assertSessionHasErrors('fichier');
    }

    public function test_a_file_that_is_not_really_excel_is_refused()
    {
        $this->importer('salles', $this->csv('salles.xlsx', 'ceci n\'est pas un fichier Excel'))
            ->assertSessionHasErrors(['fichier' => "Ce fichier n'est ni un fichier Excel ni un fichier CSV lisible."]);
    }

    public function test_a_file_whose_columns_differ_from_the_template_is_refused_and_nothing_is_imported()
    {
        // Colonne « capacite » manquante
        $this->importer('salles', $this->csv('salles.csv', "nom,type\nSalle 2,cours\n"))
            ->assertSessionHasErrors(['fichier' => "Les colonnes du fichier ne correspondent pas au modèle (attendues : nom, type, capacite). Rien n'a été importé."]);

        // Modèle des enseignants envoyé comme salles
        $this->importer('salles', $this->csv('salles.csv', "nom;prenom;email;telephone\nHaddad;Leila;leila@example.com;\n"))
            ->assertSessionHasErrors('fichier');

        $this->assertSame(0, Salle::count());
        $this->assertSame(0, User::where('email', 'leila@example.com')->count());
    }

    public function test_nothing_is_imported_when_no_line_is_valid()
    {
        // 4c : le rapport liste toutes les erreurs
        $this->importer('salles', $this->csv('salles.csv', "nom,type,capacite\n,cours,30\nSalle 3,piscine,20\n"))
            ->assertSessionHas('rapport', fn (array $r) => $r['importees'] === 0 && count($r['erreurs']) === 2);

        $this->assertSame(0, Salle::count());
    }

    public function test_only_xlsx_or_csv_files_are_accepted()
    {
        $this->importer('salles', UploadedFile::fake()->create('salles.pdf', 10))
            ->assertSessionHasErrors(['fichier' => 'Le fichier doit être au format .xlsx ou .csv.']);
    }
}
