<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Salle;
use App\Models\User;
use App\Support\CreneauHoraire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * US18 — Export de l'emploi du temps publié en PDF (DomPDF) ou en Excel (OpenSpout), fidèle à la grille
 * affichée : les jours du lundi au samedi en colonnes, les créneaux en lignes avec les récréations et midi,
 * le samedi après-midi « Pas de cours », et dans chaque case le même contenu que la grille.
 */
class ExportEmploiDuTemps
{
    public const FORMATS = ['pdf', 'excel'];

    public const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    /** Couleurs des séances selon le type de salle (comme dans la grille). */
    public const COULEURS = ['cours' => 'EEEDFF', 'sport' => 'DDF6EF', 'technologie' => 'FFF1CC'];

    /**
     * @param  key-of<GrilleEmploiDuTemps::FILTRES>  $filtre
     */
    public function __construct(private EmploiDuTemps $emploi, private string $filtre, private int $id) {}

    /**
     * Le fichier à télécharger.
     */
    public function telecharger(string $format): Response
    {
        return $format === 'excel' ? $this->excel() : $this->pdf();
    }

    /**
     * « Classe », « Enseignant » ou « Salle » et le nom choisi, ex. [« Classe », « 3e année A »].
     *
     * @return array{0: string, 1: string}
     */
    public function sujet(): array
    {
        return match ($this->filtre) {
            'classe' => ['Classe', Classe::findOrFail($this->id)->nom],
            'enseignant' => ['Enseignant', User::findOrFail($this->id)->name],
            'salle' => ['Salle', Salle::findOrFail($this->id)->nom],
        };
    }

    /**
     * Nom du fichier, ex. « emploi-du-temps-2026-2027-3e-annee-a.pdf ».
     */
    public function nomFichier(string $format): string
    {
        return 'emploi-du-temps-'.$this->emploi->annee_scolaire.'-'.Str::slug($this->sujet()[1])
            .($format === 'excel' ? '.xlsx' : '.pdf');
    }

    /**
     * Les lignes du tableau : un créneau (avec ses 6 cases) ou une récréation.
     * Une case vaut null quand il n'y a pas de cours (samedi après-midi et samedi midi),
     * sinon la liste de ses séances (titre, matières avec leurs horaires, type de salle).
     *
     * @return list<array{recreation: bool, libelle: string, midi: bool, cases: list<list<array{titre: string, matieres: list<string>, type: string}>|null>}>
     */
    public function lignes(): array
    {
        $structure = GrilleEmploiDuTemps::structure();
        $seances = GrilleEmploiDuTemps::seances($this->emploi, $this->filtre, $this->id);
        $lignes = [];

        foreach ($structure['lignes'] as $position => $ligne) {
            $cases = [];

            foreach (array_keys(self::JOURS) as $j) {
                $cle = ($j + 1).'-'.$ligne['debut'];
                $cases[] = in_array($cle, $structure['cases'], true)
                    ? array_values(array_map(fn (array $s) => $this->seance($s), array_filter($seances, fn (array $s) => $s['case'] === $cle)))
                    : null;
            }

            $lignes[] = [
                'recreation' => false,
                'libelle' => $this->heures($ligne['debut'], $ligne['fin']),
                'midi' => $ligne['midi'],
                'cases' => $cases,
            ];

            // Récréation entre deux créneaux qui ne se suivent pas (9 h 55 → 10 h 05)
            $suivante = $structure['lignes'][$position + 1] ?? null;
            if ($suivante && $ligne['fin'] !== $suivante['debut']) {
                $lignes[] = ['recreation' => true, 'libelle' => 'Récréation · '.$this->heures($ligne['fin'], $suivante['debut']), 'midi' => false, 'cases' => []];
            }
        }

        return $lignes;
    }

    private function pdf(): Response
    {
        [$libelle, $nom] = $this->sujet();

        return Pdf::loadView('exports.emploi-du-temps', [
            'ecole' => $this->emploi->ecole->nom,
            'annee' => $this->emploi->annee_scolaire,
            'libelle' => $libelle,
            'nom' => $nom,
            'jours' => self::JOURS,
            'lignes' => $this->lignes(),
            'couleurs' => self::COULEURS,
        ])->setOption('isFontSubsettingEnabled', true) // seulement les caractères utilisés : fichier plus léger
            ->setPaper('a4', 'landscape')->download($this->nomFichier('pdf'));
    }

    private function excel(): Response
    {
        [$libelle, $nom] = $this->sujet();
        $chemin = tempnam(sys_get_temp_dir(), 'edt');

        $options = new Options;
        $options->setColumnWidth(16, 1);
        $options->setColumnWidth(30, 2, 3, 4, 5, 6, 7);
        $options->mergeCells(0, 1, 6, 1);
        $options->mergeCells(0, 2, 6, 2);
        $options->mergeCells(0, 3, 6, 3);

        $haut = new Style(shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::TOP);
        $gras = new Style(fontBold: true);

        $writer = new Writer($options);
        $writer->openToFile($chemin);
        $writer->addRow(Row::fromValuesWithStyle(['Emploi du temps'], new Style(fontBold: true, fontSize: 16)));
        $writer->addRow(Row::fromValues([$this->emploi->ecole->nom.' · année scolaire '.$this->emploi->annee_scolaire]));
        $writer->addRow(Row::fromValues([$libelle.' : '.$nom]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle(['Créneau', ...self::JOURS], $gras));

        $numero = 5;
        foreach ($this->lignes() as $ligne) {
            $numero++;

            if ($ligne['recreation']) {
                $options->mergeCells(0, $numero, 6, $numero);
                $writer->addRow(Row::fromValuesWithStyle([$ligne['libelle']], new Style(fontItalic: true, backgroundColor: 'F7F7FB')));

                continue;
            }

            $valeurs = [$ligne['libelle'].($ligne['midi'] ? "\nMidi" : '')];
            $styles = [$haut->withFontBold(true)];

            foreach ($ligne['cases'] as $case) {
                if ($case === null) {
                    $valeurs[] = 'Pas de cours';
                    $styles[] = $haut->withBackgroundColor('EEEEF3');

                    continue;
                }

                $valeurs[] = implode("\n\n", array_map(fn (array $s) => implode("\n", [$s['titre'], ...$s['matieres']]), $case));
                // Une seule séance : la couleur de sa salle ; deux séances de types différents : pas de couleur
                $types = array_unique(array_column($case, 'type'));
                $styles[] = count($types) === 1 ? $haut->withBackgroundColor(self::COULEURS[$types[0]]) : $haut;
            }

            $writer->addRow(Row::fromValuesWithStyles($valeurs, $styles));
        }

        $writer->close();

        return response()->download($chemin, $this->nomFichier('excel'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Une séance comme dans la grille : le titre montre ce qui n'est pas déjà choisi
     * (par classe : « enseignant · salle »), puis les matières avec leurs horaires.
     *
     * @param  array<string, mixed>  $s
     * @return array{titre: string, matieres: list<string>, type: string}
     */
    private function seance(array $s): array
    {
        $titre = match ($this->filtre) {
            'classe' => "{$s['enseignant']} · {$s['salle']}",
            'enseignant' => "{$s['classe']} · {$s['salle']}",
            'salle' => "{$s['classe']} · {$s['enseignant']}",
        };

        /** @var list<array{nom: string, debut: string, fin: string}> $matieres */
        $matieres = $s['matieres'];

        return [
            'titre' => $titre,
            'matieres' => array_map(fn (array $m) => $m['nom'].' · '.$this->heures($m['debut'], $m['fin']), $matieres),
            'type' => (string) $s['typeSalle'],
        ];
    }

    /**
     * « 08:00 », « 09:55 » → « 8 h – 9 h 55 ».
     */
    private function heures(string $debut, string $fin): string
    {
        return CreneauHoraire::lisible(CreneauHoraire::minutes($debut)).' – '.CreneauHoraire::lisible(CreneauHoraire::minutes($fin));
    }
}
