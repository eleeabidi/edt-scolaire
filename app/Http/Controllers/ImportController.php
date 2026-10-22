<?php

namespace App\Http\Controllers;

use App\Services\ImportDonnees;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * US12 — Importer les enseignants, les classes et les salles depuis un fichier Excel/CSV.
 * Écrans 21 (import) et 22 (rapport). Directeur, pour son école.
 */
class ImportController extends Controller
{
    /**
     * Écran 21 — Choisir le type de données et envoyer le fichier (T12.3).
     */
    public function index(): Response
    {
        return Inertia::render('import/Index');
    }

    /**
     * T12.1 — Modèle de fichier Excel à télécharger : ligne de titres + une ligne d'exemple.
     */
    public function modele(string $type): BinaryFileResponse
    {
        abort_unless(array_key_exists($type, ImportDonnees::COLONNES), 404);

        $chemin = tempnam(sys_get_temp_dir(), 'modele').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($chemin);
        $writer->addRow(Row::fromValues(ImportDonnees::COLONNES[$type]));
        $writer->addRow(Row::fromValues(ImportDonnees::EXEMPLES[$type]));
        $writer->close();

        return response()->download($chemin, "modele-{$type}.xlsx")->deleteFileAfterSend();
    }

    /**
     * T12.4 — Lit, valide et enregistre les lignes, puis affiche le rapport.
     */
    public function store(Request $request, ImportDonnees $import): RedirectResponse
    {
        $request->validate([
            'type' => ['required', Rule::in(array_keys(ImportDonnees::COLONNES))],
            'fichier' => ['required', 'file', 'max:2048', 'extensions:xlsx,csv'],
        ], [
            'fichier.extensions' => 'Le fichier doit être au format .xlsx ou .csv.',
        ], [
            'fichier' => 'fichier',
        ]);

        $fichier = $request->file('fichier');

        $rapport = $import->importer(
            $request->string('type')->value(),
            $fichier->getRealPath(),
            strtolower($fichier->getClientOriginalExtension()),
            $fichier->getClientOriginalName(),
            $request->user(),
        );

        return to_route('import.rapport')->with('rapport', $rapport);
    }

    /**
     * Écran 22 — Rapport d'import, avec les lignes en erreur (T12.5).
     */
    public function rapport(Request $request): Response|RedirectResponse
    {
        $rapport = $request->session()->get('rapport');

        if ($rapport === null) {
            return to_route('import.index');
        }

        return Inertia::render('import/Rapport', ['rapport' => $rapport]);
    }
}
