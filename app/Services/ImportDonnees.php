<?php

namespace App\Services;

use App\Concerns\EnvoieIdentifiants;
use App\Enums\Role;
use App\Enums\StatutCompte;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Options as OptionsCsv;
use OpenSpout\Reader\CSV\Reader as LecteurCsv;
use OpenSpout\Reader\XLSX\Reader as LecteurXlsx;

/**
 * T12.4 — Lit, valide et enregistre les lignes d'un fichier Excel (.xlsx) ou CSV.
 * La première ligne contient les titres des colonnes (voir les modèles à télécharger).
 */
class ImportDonnees
{
    use EnvoieIdentifiants;

    /**
     * Nombre d'enseignants importés dont l'email n'a pas pu être envoyé (import en cours).
     */
    private int $emailsNonEnvoyes = 0;

    /**
     * Colonnes de chaque modèle de fichier (T12.1).
     *
     * @var array<string, array<int, string>>
     */
    public const COLONNES = [
        'enseignants' => ['nom', 'prenom', 'email', 'telephone'],
        'classes' => ['nom', 'niveau', 'effectif', 'cours_samedi'],
        'salles' => ['nom', 'type', 'capacite'],
    ];

    /**
     * Exemple de ligne de chaque modèle.
     *
     * @var array<string, array<int, string|int>>
     */
    public const EXEMPLES = [
        'enseignants' => ['Trabelsi', 'Leila', 'leila.trabelsi@ecole.tn', '+216 98 765 432'],
        'classes' => ['3e année A', 3, 30, 'oui'],
        'salles' => ['Salle 1', 'normale', 30],
    ];

    /**
     * Importe le fichier et renvoie le rapport (T12.5).
     *
     * @return array{type: string, fichier: string, lignesLues: int, importees: int, emailsNonEnvoyes: int, erreurs: array<int, array{ligne: int, donnee: string, erreur: string}>}
     */
    public function importer(string $type, string $chemin, string $extension, string $nomFichier, User $directeur): array
    {
        $this->emailsNonEnvoyes = 0;
        $lignesLues = 0;
        $importees = 0;
        $erreurs = [];

        foreach ($this->lireLignes($chemin, $extension) as $numero => $ligne) {
            $lignesLues++;

            $erreur = match ($type) {
                'enseignants' => $this->importerEnseignant($ligne, $directeur),
                'classes' => $this->importerClasse($ligne, $directeur),
                'salles' => $this->importerSalle($ligne, $directeur),
                default => throw new InvalidArgumentException("Type d'import inconnu : {$type}"),
            };

            if ($erreur === null) {
                $importees++;
            } else {
                $colonne = $type === 'enseignants' ? 'email' : 'nom';
                $donnee = $ligne[$colonne] ?? '';

                $erreurs[] = [
                    'ligne' => $numero,
                    'donnee' => $donnee !== '' ? $donnee : "({$colonne} vide)",
                    'erreur' => $erreur,
                ];
            }
        }

        return [
            'type' => $type,
            'fichier' => $nomFichier,
            'lignesLues' => $lignesLues,
            'importees' => $importees,
            'emailsNonEnvoyes' => $this->emailsNonEnvoyes,
            'erreurs' => $erreurs,
        ];
    }

    /**
     * Lit le fichier : renvoie les lignes non vides, indexées par leur numéro dans le fichier
     * (la ligne 1 contient les titres), avec les colonnes nommées d'après la ligne de titres.
     *
     * @return array<int, array<string, string>>
     */
    public function lireLignes(string $chemin, string $extension): array
    {
        $lecteur = $extension === 'csv' ? $this->lecteurCsv($chemin) : new LecteurXlsx;
        $lecteur->open($chemin);

        $titres = null;
        $lignes = [];

        foreach ($lecteur->getSheetIterator() as $feuille) {
            foreach ($feuille->getRowIterator() as $numero => $ligne) {
                $valeurs = array_map(fn (mixed $valeur) => $this->texte($valeur), $ligne->toArray());

                if ($titres === null) {
                    // « Prénom » → « prenom », « Cours samedi » → « cours_samedi »
                    $titres = array_map(fn (string $t) => Str::of($t)->ascii()->lower()->trim()->replace(' ', '_')->value(), $valeurs);

                    continue;
                }

                if (implode('', $valeurs) === '') {
                    continue; // ligne vide
                }

                $lignes[$numero] = [];
                foreach ($titres as $position => $titre) {
                    $lignes[$numero][$titre] = $valeurs[$position] ?? '';
                }
            }

            break; // seulement la première feuille
        }

        $lecteur->close();

        return $lignes;
    }

    /**
     * Enseignant : compte ACTIF de l'école du directeur, mot de passe temporaire par email.
     *
     * @param  array<string, string>  $ligne
     */
    private function importerEnseignant(array $ligne, User $directeur): ?string
    {
        $erreur = $this->valider($ligne, [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'telephone' => ['nullable', 'string', 'max:20'],
        ], [
            'email.unique' => 'Email déjà utilisé par un autre compte',
        ]);

        if ($erreur !== null) {
            return $erreur;
        }

        $motDePasse = $this->genererMotDePasseTemporaire();

        $enseignant = User::create([
            'nom' => $ligne['nom'],
            'prenom' => $ligne['prenom'],
            'email' => $ligne['email'],
            'telephone' => ($ligne['telephone'] ?? '') !== '' ? $ligne['telephone'] : null,
            'role' => Role::Enseignant,
            'statut' => StatutCompte::Actif,
            'ecole_id' => $directeur->ecole_id,
            'password' => $motDePasse,
        ]);

        if (! $this->envoyerIdentifiants($enseignant, $motDePasse)) {
            $this->emailsNonEnvoyes++;
        }

        return null;
    }

    /**
     * Classe de l'école du directeur ; cours_samedi n'est lu qu'en 3e année (règle du modèle Classe).
     *
     * @param  array<string, string>  $ligne
     */
    private function importerClasse(array $ligne, User $directeur): ?string
    {
        // « 3e année » ou « 3 » → 3
        $ligne['niveau'] = preg_match('/\d+/', $ligne['niveau'] ?? '', $m) ? $m[0] : ($ligne['niveau'] ?? '');

        $erreur = $this->valider($ligne, [
            'nom' => ['required', 'string', 'max:255', Rule::unique('classes', 'nom')->where('ecole_id', $directeur->ecole_id)],
            'niveau' => ['required', 'integer', 'between:1,6'],
            'effectif' => ['required', 'integer', 'min:1', 'max:99'],
        ], [
            'nom.unique' => 'Une classe de ce nom existe déjà dans votre école',
            'niveau.between' => 'Le niveau doit être compris entre 1 et 6',
        ]);

        if ($erreur !== null) {
            return $erreur;
        }

        $directeur->ecole->classes()->create([
            'nom' => $ligne['nom'],
            'niveau' => (int) $ligne['niveau'],
            'effectif' => (int) $ligne['effectif'],
            'cours_samedi' => in_array(Str::lower(Str::ascii($ligne['cours_samedi'] ?? '')), ['oui', 'o', '1', 'vrai', 'yes'], true),
        ]);

        return null;
    }

    /**
     * Salle de l'école du directeur : type normale ou sport.
     *
     * @param  array<string, string>  $ligne
     */
    private function importerSalle(array $ligne, User $directeur): ?string
    {
        $ligne['type'] = Str::lower($ligne['type'] ?? '');

        $erreur = $this->valider($ligne, [
            'nom' => ['required', 'string', 'max:255', Rule::unique('salles', 'nom')->where('ecole_id', $directeur->ecole_id)],
            'type' => ['required', Rule::in(['normale', 'sport'])],
            'capacite' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'nom.unique' => 'Une salle de ce nom existe déjà dans votre école',
            'type.in' => 'Le type doit être « normale » ou « sport »',
        ]);

        if ($erreur !== null) {
            return $erreur;
        }

        $directeur->ecole->salles()->create([
            'nom' => $ligne['nom'],
            'type' => $ligne['type'],
            'capacite' => (int) $ligne['capacite'],
        ]);

        return null;
    }

    /**
     * Valide une ligne ; renvoie le premier message d'erreur, ou null si elle est valide.
     *
     * @param  array<string, string>  $ligne
     * @param  array<string, mixed>  $regles
     * @param  array<string, string>  $messages
     */
    private function valider(array $ligne, array $regles, array $messages): ?string
    {
        $validateur = Validator::make($ligne, $regles, [
            'required' => 'Champ obligatoire vide : :attribute',
            'integer' => 'Le champ :attribute doit être un nombre',
            'email' => 'Email invalide',
            ...$messages,
        ]);

        return $validateur->fails() ? $validateur->errors()->first() : null;
    }

    /**
     * Lecteur CSV : séparateur « ; » (Excel en français) ou « , », encodage UTF-8 ou Windows-1252.
     */
    private function lecteurCsv(string $chemin): LecteurCsv
    {
        $debut = (string) file_get_contents($chemin, length: 4096);
        $premiereLigne = strtok($debut, "\n") ?: '';

        return new LecteurCsv(new OptionsCsv(
            FIELD_DELIMITER: substr_count($premiereLigne, ';') > substr_count($premiereLigne, ',') ? ';' : ',',
            ENCODING: mb_check_encoding($debut, 'UTF-8') ? 'UTF-8' : 'WINDOWS-1252',
        ));
    }

    /**
     * Valeur d'une cellule → texte (les nombres d'Excel deviennent « 30 », pas « 30.0 »).
     */
    private function texte(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => '',
            is_float($valeur) && floor($valeur) === $valeur => (string) (int) $valeur,
            $valeur instanceof DateTimeInterface => $valeur->format('d/m/Y'),
            is_bool($valeur) => $valeur ? 'oui' : 'non',
            is_scalar($valeur) => trim((string) $valeur),
            default => '',
        };
    }
}
