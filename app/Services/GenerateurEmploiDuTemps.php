<?php

namespace App\Services;

use App\Enums\Jour;
use App\Enums\TypeSalle;
use App\Exceptions\GenerationImpossible;
use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Contrainte;
use App\Models\Disponibilite;
use App\Models\Ecole;
use App\Models\Horaire;
use App\Models\NiveauMatiere;
use App\Models\Salle;
use App\Models\User;
use App\Support\Duree;
use Generator;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * T13.6 / T13.7 — Génération automatique de l'emploi du temps d'une école (genererAuto).
 *
 * 1. Vérifie les données (4a) : chaque matière de chaque classe a un enseignant actif,
 *    l'école a une salle de technologie et une salle de sport en service.
 * 2. Prépare chaque classe : les heures de chaque enseignant sont découpées en séances de 2 h
 *    (un créneau entier) ou d'1 h. L'éducation physique et l'éducation technologique (matières
 *    qui demandent une salle de sport ou de technologie) forment chacune une séance d'1 h à part :
 *    elle partage un créneau de 2 h avec 1 h d'un enseignant de la classe, ou occupe le créneau de midi.
 *    Une matière dure au moins 30 min dans une séance (règle minimum_par_matiere).
 * 3. Place les séances dans les créneaux, en respectant les règles de gestion du niveau
 *    (1re et 2e année : un bloc de 4 h par jour, 4 matinées et 1 après-midi, matinées jusqu'à 13 h
 *    en 2e année ; samedi matin selon la classe ; si la classe a cours de 12 h à 13 h, elle est libre
 *    de 10 h 05 à 12 h ou de 13 h à 14 h 55 ; seules les matières autorisées à midi vont de 12 h à 13 h),
 *    les disponibilités des enseignants et le type et la capacité des salles en service.
 *    verifierDisponibilite() contrôle la classe, l'enseignant et la salle de chaque séance.
 * 4. En cas d'échec, recommence dans un autre ordre ; après plusieurs tentatives, explique la cause (5a).
 *
 * Le créneau du samedi de 12 h à 13 h n'est jamais utilisé (décision du 29 octobre).
 *
 * @phpstan-type Creneau array{id: int, jour: int, debut: int, fin: int, milieu: int, cle: string, midi: bool, avantMidi: bool, apresMidi: bool, ordre: int}
 * @phpstan-type Partie array{enseignant: int, type: TypeSalle, duree: int, matieres: list<array{0: int, 1: int}>}
 * @phpstan-type Travail array{type: 'plein'|'mixte'|'midi', parties: list<Partie>}
 * @phpstan-type Motif array{bloc: bool, matinees: int, apresMidi: int, midi: int, midiLibere: bool}
 * @phpstan-type Plan array{classe: Classe, jours: list<int>, motif: Motif, compositions: list<list<Travail>>}
 * @phpstan-type SeanceGeneree array{classe_id: int, horaire_id: int, enseignant_id: int, salle_id: int, matieres: list<array{matiere_id: int, heure_debut: string, heure_fin: string}>}
 */
class GenerateurEmploiDuTemps
{
    /** Nombre de tentatives (ordres différents) avant d'abandonner. */
    private const TENTATIVES = 15;

    /** Nombre maximum d'essais de placement pour une classe, à chaque tentative. */
    private const ESSAIS_MAX = 8000;

    /** Durée maximale de la recherche, en secondes. */
    private const DUREE_MAX = 10;

    /** @var array<int, Classe> */
    private array $classes = [];

    /** @var array<int, Salle> salles en service */
    private array $salles = [];

    /** @var array<int, string> */
    private array $nomsEnseignants = [];

    /** @var array<int, array<string, true>|null> créneaux disponibles (« jour-HH:MM »), null = disponible partout */
    private array $disponibles = [];

    /** @var list<Creneau> */
    private array $creneaux = [];

    /** @var array<int, int> salle de cours habituelle de chaque classe */
    private array $salleHabituelle = [];

    /** @var array<string, array<int, array{0: int, 1: int}>> occupations : « c12 », « e5 », « s3 » → intervalles */
    private array $occupations = [];

    private int $jeton = 0;

    private int $essais = 0;

    /** @var array<string, int> causes de refus rencontrées pendant le placement d'une classe */
    private array $raisons = [];

    private Randomizer $hasard;

    /** Enseignant dont les disponibilités rendent la classe impossible à placer, quel que soit l'ordre. */
    private ?int $bloqueParDisponibilites = null;

    private int $minimumParMatiere = 30;

    private int $midiDebut = 720;

    private int $midiFin = 780;

    /**
     * Génère les séances de toutes les classes de l'école, sans rien enregistrer.
     *
     * @return list<SeanceGeneree>
     *
     * @throws GenerationImpossible
     */
    public function genererAuto(Ecole $ecole): array
    {
        $this->charger($ecole);
        $plans = $this->preparer();

        $ordre = array_keys($plans);
        usort($ordre, fn (int $a, int $b) => [$plans[$b]['motif']['bloc'], $plans[$b]['classe']->niveau] <=> [$plans[$a]['motif']['bloc'], $plans[$a]['classe']->niveau]);

        $bloquante = $ordre[0];
        $limite = microtime(true) + self::DUREE_MAX;

        for ($tentative = 1; $tentative <= self::TENTATIVES && microtime(true) < $limite; $tentative++) {
            $this->hasard = new Randomizer(new Mt19937($tentative));
            $this->occupations = [];
            $resultat = [];
            $echec = null;

            foreach ($ordre as $classeId) {
                $seances = $this->placerClasse($plans[$classeId]);

                if ($seances === null) {
                    $echec = $classeId;

                    // Les disponibilités suffisent à rendre la classe impossible : inutile de recommencer
                    if ($this->bloqueParDisponibilites !== null) {
                        throw new GenerationImpossible([$this->causeEchec($this->classes[$classeId])]);
                    }

                    break;
                }

                array_push($resultat, ...$seances);
            }

            if ($echec === null) {
                return $resultat;
            }

            $bloquante = $echec;

            // La classe qui bloque passe en premier à la tentative suivante
            $ordre = [$echec, ...array_values(array_diff($ordre, [$echec]))];
        }

        throw new GenerationImpossible([$this->causeEchec($this->classes[$bloquante])]);
    }

    /**
     * T13.6 — La séance (classe, enseignant, salle, de $debut à $fin dans le créneau) est-elle possible ?
     * Renvoie null si oui, sinon la cause du conflit.
     *
     * @param  Creneau  $creneau
     */
    public function verifierDisponibilite(int $classeId, int $enseignantId, int $salleId, array $creneau, int $debut, int $fin): ?string
    {
        return $this->probleme($classeId, $enseignantId, $salleId, $creneau, $debut, $fin)['message'] ?? null;
    }

    // ---------------------------------------------------------------------------------------------
    // 1. Chargement et vérification des données
    // ---------------------------------------------------------------------------------------------

    private function charger(Ecole $ecole): void
    {
        $this->classes = $ecole->classes()->orderBy('niveau')->orderBy('nom')->get()->keyBy('id')->all();
        $this->salles = $ecole->salles()->where('en_service', true)->orderBy('nom')->get()->keyBy('id')->all();

        $minimum = (int) Contrainte::generales('minimum_par_matiere')->first()?->valeur;
        $this->minimumParMatiere = $minimum > 0 ? $minimum : 30;

        $midi = Contrainte::generales('pause_midi')->first()->valeur ?? '12:00-13:00';
        [$debut, $fin] = explode('-', $midi);
        $this->midiDebut = $this->minutes($debut);
        $this->midiFin = $this->minutes($fin);

        $this->creneaux = [];
        foreach (Horaire::orderBy('jour')->orderBy('heure_debut')->get() as $horaire) {
            $debut = $this->minutes($horaire->heure_debut);
            $fin = $this->minutes($horaire->heure_fin);
            $midiCreneau = $debut === $this->midiDebut && $fin === $this->midiFin;

            // Le créneau du samedi de 12 h à 13 h n'est jamais utilisé
            if ($midiCreneau && $horaire->jour === Jour::Samedi) {
                continue;
            }

            $this->creneaux[] = [
                'id' => $horaire->id,
                'jour' => $horaire->jour->value,
                'debut' => $debut,
                'fin' => $fin,
                // Les matières d'un créneau de 2 h se partagent en heures : 8 h → 9 h → 9 h 55, 10 h 05 → 11 h → 12 h
                'milieu' => $midiCreneau ? $debut + 30 : intdiv($debut, 60) * 60 + 60,
                'cle' => $horaire->jour->value.'-'.substr($horaire->heure_debut, 0, 5),
                'midi' => $midiCreneau,
                'avantMidi' => $fin === $this->midiDebut,
                'apresMidi' => $debut === $this->midiFin,
                'ordre' => $midiCreneau ? 99 : intdiv($debut, 60), // le matin d'abord, midi en dernier
            ];
        }
    }

    /**
     * Vérifie les données (4a et 5a) et prépare les compositions de séances de chaque classe.
     *
     * @return array<int, Plan>
     *
     * @throws GenerationImpossible
     */
    private function preparer(): array
    {
        $causes = [];

        if ($this->classes === []) {
            throw new GenerationImpossible(["Aucune classe n'existe dans votre école."]);
        }

        // Programme de chaque niveau : niveau => volumes (les plus grandes matières d'abord)
        $programme = [];
        foreach (NiveauMatiere::with('matiere')->orderByDesc('minutes_par_semaine')->orderBy('id')->get() as $volume) {
            $programme[$volume->niveau][] = $volume;
        }

        /** @var Collection<string, Affectation> $affectations */
        $affectations = Affectation::whereIn('classe_id', array_keys($this->classes))
            ->with('enseignant')
            ->get()
            ->keyBy(fn (Affectation $a) => "{$a->classe_id}-{$a->matiere_id}");

        // 4a : chaque matière de chaque classe a un enseignant (actif)
        foreach ($this->classes as $classe) {
            foreach ($programme[$classe->niveau] ?? [] as $volume) {
                $enseignant = $affectations->get("{$classe->id}-{$volume->matiere_id}")?->enseignant;

                if ($enseignant === null || ! $enseignant->isActif()) {
                    $causes[] = "La matière {$volume->matiere->nom} de la classe {$classe->nom} n'a pas d'enseignant.";
                }
            }
        }

        // 4a : au moins une salle de technologie et une salle de sport en service
        $types = collect($this->salles)->map(fn (Salle $s) => $s->type);
        if (! $types->contains(TypeSalle::Technologie)) {
            $causes[] = "Aucune salle de technologie n'est en service.";
        }
        if (! $types->contains(TypeSalle::Sport)) {
            $causes[] = "Aucune salle de sport n'est en service.";
        }
        if (! $types->contains(TypeSalle::Cours)) {
            $causes[] = "Aucune salle de cours n'est en service.";
        }

        if ($causes !== []) {
            throw new GenerationImpossible($causes);
        }

        $this->chargerEnseignants($affectations);
        $this->choisirSallesHabituelles();

        // 5a : causes connues d'avance (heures disponibles, salles trop petites, heures impossibles à répartir)
        $causes = $this->verifierHeuresDisponibles($affectations, $programme);
        $plans = [];

        foreach ($this->classes as $classe) {
            $plan = $this->preparerClasse($classe, $programme[$classe->niveau] ?? [], $affectations, $causes);

            if ($plan !== null) {
                $plans[$classe->id] = $plan;
            }
        }

        if ($causes !== []) {
            throw new GenerationImpossible(array_values(array_unique($causes)));
        }

        return $plans;
    }

    /**
     * @param  Collection<string, Affectation>  $affectations
     */
    private function chargerEnseignants(Collection $affectations): void
    {
        $this->nomsEnseignants = [];
        $this->disponibles = [];

        foreach ($affectations as $affectation) {
            $this->nomsEnseignants[$affectation->enseignant_id] = $affectation->enseignant->name;
            $this->disponibles[$affectation->enseignant_id] = null;
        }

        foreach (Disponibilite::whereIn('enseignant_id', array_keys($this->nomsEnseignants))->get() as $disponibilite) {
            $this->disponibles[$disponibilite->enseignant_id] ??= [];
            $this->disponibles[$disponibilite->enseignant_id][$disponibilite->jour->value.'-'.substr($disponibilite->heure_debut, 0, 5)] = true;
        }
    }

    /**
     * Chaque classe a, si possible, sa salle de cours (les plus grandes classes d'abord).
     */
    private function choisirSallesHabituelles(): void
    {
        $this->salleHabituelle = [];
        $libres = collect($this->salles)->filter(fn (Salle $s) => $s->type === TypeSalle::Cours)->sortBy('capacite')->all();

        foreach (collect($this->classes)->sortByDesc('effectif') as $classe) {
            foreach ($libres as $id => $salle) {
                if ($salle->capacite >= $classe->effectif) {
                    $this->salleHabituelle[$classe->id] = $id;
                    unset($libres[$id]);
                    break;
                }
            }
        }
    }

    /**
     * 5a : un enseignant n'a pas assez de créneaux disponibles pour ses heures affectées.
     *
     * @param  Collection<string, Affectation>  $affectations
     * @param  array<int, list<NiveauMatiere>>  $programme
     * @return list<string>
     */
    private function verifierHeuresDisponibles(Collection $affectations, array $programme): array
    {
        $affectees = [];

        foreach ($affectations as $affectation) {
            $classe = $this->classes[$affectation->classe_id];
            $volume = collect($programme[$classe->niveau] ?? [])->firstWhere('matiere_id', $affectation->matiere_id);
            $affectees[$affectation->enseignant_id] = ($affectees[$affectation->enseignant_id] ?? 0) + ($volume->minutes_par_semaine ?? 0);
        }

        $causes = [];

        foreach ($affectees as $enseignantId => $minutes) {
            $disponibles = 0;

            foreach ($this->creneaux as $creneau) {
                if ($this->estDisponible($enseignantId, $creneau)) {
                    $disponibles += $creneau['midi'] ? 60 : 120;
                }
            }

            if ($disponibles < $minutes) {
                $causes[] = "{$this->nomsEnseignants[$enseignantId]} n'a que ".Duree::format($disponibles).' disponibles pour '.Duree::format($minutes).' affectées.';
            }
        }

        return $causes;
    }

    // ---------------------------------------------------------------------------------------------
    // 2. Préparation d'une classe : découpage en séances
    // ---------------------------------------------------------------------------------------------

    /**
     * @param  list<NiveauMatiere>  $programme
     * @param  Collection<string, Affectation>  $affectations
     * @param  list<string>  $causes
     * @return Plan|null
     */
    private function preparerClasse(Classe $classe, array $programme, Collection $affectations, array &$causes): ?array
    {
        $enseignants = []; // id => [matières => minutes, midi => [matière => autorisée à midi]]
        $speciaux = [];    // séances d'1 h à part (éducation physique, éducation technologique)
        $typesDemandes = [TypeSalle::Cours->value => TypeSalle::Cours];

        foreach ($programme as $volume) {
            $enseignantId = $affectations->get("{$classe->id}-{$volume->matiere_id}")?->enseignant_id;
            $type = $volume->matiere->type_salle;

            if ($enseignantId === null) {
                continue;
            }

            if ($type !== TypeSalle::Cours) {
                $typesDemandes[$type->value] = $type;

                if ($volume->minutes_par_semaine % 60 !== 0) {
                    $causes[] = "La matière {$volume->matiere->nom} de la classe {$classe->nom} ne remplit pas des séances d'1 h.";

                    continue;
                }

                for ($i = 0; $i < intdiv($volume->minutes_par_semaine, 60); $i++) {
                    $speciaux[] = ['enseignant' => $enseignantId, 'type' => $type, 'duree' => 60, 'matieres' => [[$volume->matiere_id, 60]]];
                }

                continue;
            }

            $enseignants[$enseignantId]['matieres'][$volume->matiere_id] = $volume->minutes_par_semaine;
            $enseignants[$enseignantId]['midi'][$volume->matiere_id] = $volume->matiere->autorisee_midi;
        }

        // Chaque salle demandée doit exister en service et être assez grande pour la classe
        foreach ($typesDemandes as $type) {
            $assezGrande = collect($this->salles)->contains(fn (Salle $s) => $s->type === $type && $s->capacite >= $classe->effectif);

            if (! $assezGrande) {
                $causes[] = 'Aucune salle '.$this->libelleType($type)." en service n'est assez grande pour la classe {$classe->nom} ({$classe->effectif} élèves).";
            }
        }

        foreach ($enseignants as $id => $donnees) {
            $minutes = array_sum($donnees['matieres']);

            if ($minutes % 60 !== 0) {
                $causes[] = "Les heures de {$this->nomsEnseignants[$id]} en {$classe->nom} (".Duree::format($minutes).') ne remplissent pas des séances d\'1 h ou de 2 h.';
            }
        }

        $jours = $classe->cours_samedi ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5];
        $motif = $this->motif($classe);
        $compositions = $this->compositions($enseignants, $speciaux, $motif, $jours);

        if ($compositions === []) {
            $causes[] = "Les heures de la classe {$classe->nom} ne peuvent pas être réparties en séances qui respectent les règles de gestion des emplois du temps.";

            return null;
        }

        return ['classe' => $classe, 'jours' => $jours, 'motif' => $motif, 'compositions' => $compositions];
    }

    /**
     * Règles d'organisation du niveau (table contraintes).
     *
     * @return Motif
     */
    private function motif(Classe $classe): array
    {
        $regles = Contrainte::pourNiveau($classe->niveau)->keyBy('type');
        $organisation = $regles->get('organisation_semaine')?->valeur;
        $total = (int) $regles->get('total_hebdomadaire')?->valeur;
        $midiLibere = ($regles->get('midi_libere')->valeur ?? 'oui') === 'oui';

        if ($organisation === null) {
            return ['bloc' => false, 'matinees' => 0, 'apresMidi' => 0, 'midi' => 0, 'midiLibere' => $midiLibere];
        }

        // « 4 matinées, 1 après-midi » : un bloc de 4 h par jour (2 créneaux de 2 h)
        preg_match_all('/\d+/', $organisation, $nombres);
        [$matinees, $apresMidi] = array_map('intval', $nombres[0]) + [0, 0];
        $bloc = (int) ($regles->get('bloc_unique_par_jour')->valeur ?? 240);

        // 2e année : certaines matinées vont jusqu'à 13 h (créneau de midi) pour atteindre le total
        $midi = $regles->has('matinees_jusqu_a_13h') ? max(0, intdiv($total - ($matinees + $apresMidi) * $bloc, 60)) : 0;

        return ['bloc' => true, 'matinees' => $matinees, 'apresMidi' => $apresMidi, 'midi' => $midi, 'midiLibere' => $midiLibere];
    }

    /**
     * Les façons de répartir les heures de la classe en séances, de la plus simple à la plus compliquée.
     *
     * @param  array<int, array{matieres: array<int, int>, midi: array<int, bool>}>  $enseignants
     * @param  list<Partie>  $speciaux
     * @param  Motif  $motif
     * @param  list<int>  $jours
     * @return list<list<Travail>>
     */
    private function compositions(array $enseignants, array $speciaux, array $motif, array $jours): array
    {
        $unites = array_map(fn (array $e) => intdiv(array_sum($e['matieres']), 60), $enseignants);
        $midiMax = $motif['bloc'] ? $motif['midi'] : count(array_diff($jours, [6]));
        $resultats = [];
        $n = count($speciaux);

        for ($masque = 0; $masque < (1 << $n); $masque++) {
            $speciauxMidi = [];
            $speciauxAssocies = [];

            foreach ($speciaux as $i => $special) {
                if ($masque & (1 << $i)) {
                    $speciauxMidi[] = $special;
                } else {
                    $speciauxAssocies[] = $special;
                }
            }

            for ($midiEnseignants = 0; $midiEnseignants <= $midiMax; $midiEnseignants++) {
                $midi = count($speciauxMidi) + $midiEnseignants;

                if ($motif['bloc'] ? $midi !== $motif['midi'] : $midi > $midiMax) {
                    continue;
                }

                $seancesUneHeure = $this->repartirUnites($unites, count($speciauxAssocies) + $midiEnseignants);
                $seancesMidi = $seancesUneHeure === null ? null : $this->repartirMidi($enseignants, $seancesUneHeure, $midiEnseignants);

                if ($seancesUneHeure === null || $seancesMidi === null) {
                    continue;
                }

                $travaux = $this->construireTravaux($enseignants, $unites, $seancesUneHeure, $seancesMidi, $speciauxMidi, $speciauxAssocies);

                if ($travaux !== null) {
                    $resultats[] = ['cle' => [$midi, count($speciauxMidi), $masque], 'travaux' => $travaux];
                }
            }
        }

        usort($resultats, fn (array $a, array $b) => $a['cle'] <=> $b['cle']);

        return array_slice(array_column($resultats, 'travaux'), 0, 6);
    }

    /**
     * Nombre de séances d'1 h de chaque enseignant (le reste en séances de 2 h).
     * Un enseignant qui a un nombre impair d'heures a au moins une séance d'1 h.
     *
     * @param  array<int, int>  $unites  heures de chaque enseignant
     * @return array<int, int>|null
     */
    private function repartirUnites(array $unites, int $total): ?array
    {
        $uneHeure = array_map(fn (int $u) => $u % 2, $unites);
        $reste = $total - array_sum($uneHeure);

        if ($reste < 0 || $reste % 2 !== 0) {
            return null;
        }

        while ($reste > 0) {
            $choix = null;

            foreach ($unites as $id => $u) {
                if ($u - $uneHeure[$id] >= 2 && ($choix === null || $u - $uneHeure[$id] > $unites[$choix] - $uneHeure[$choix])) {
                    $choix = $id;
                }
            }

            if ($choix === null) {
                return null;
            }

            $uneHeure[$choix] += 2;
            $reste -= 2;
        }

        return $uneHeure;
    }

    /**
     * Parmi les séances d'1 h, celles qui vont de 12 h à 13 h (matières autorisées à midi seulement).
     *
     * @param  array<int, array{matieres: array<int, int>, midi: array<int, bool>}>  $enseignants
     * @param  array<int, int>  $uneHeure
     * @return array<int, int>|null
     */
    private function repartirMidi(array $enseignants, array $uneHeure, int $nombre): ?array
    {
        $midi = array_map(fn () => 0, $uneHeure);
        $minutesMidi = array_map(
            fn (array $e) => array_sum(array_filter($e['matieres'], fn (int $m, int $id) => $e['midi'][$id], ARRAY_FILTER_USE_BOTH)),
            $enseignants,
        );

        for ($i = 0; $i < $nombre; $i++) {
            $choix = null;

            foreach ($uneHeure as $id => $u) {
                $restant = $minutesMidi[$id] - 60 * $midi[$id];

                if ($u - $midi[$id] >= 1 && $restant >= 60 && ($choix === null || $restant > $minutesMidi[$choix] - 60 * $midi[$choix])) {
                    $choix = $id;
                }
            }

            if ($choix === null) {
                return null;
            }

            $midi[$choix]++;
        }

        return $midi;
    }

    /**
     * Construit les séances (« travaux ») à placer : créneaux pleins, créneaux mixtes et créneaux de midi.
     *
     * @param  array<int, array{matieres: array<int, int>, midi: array<int, bool>}>  $enseignants
     * @param  array<int, int>  $unites
     * @param  array<int, int>  $uneHeure
     * @param  array<int, int>  $midi
     * @param  list<Partie>  $speciauxMidi
     * @param  list<Partie>  $speciauxAssocies
     * @return list<Travail>|null
     */
    private function construireTravaux(array $enseignants, array $unites, array $uneHeure, array $midi, array $speciauxMidi, array $speciauxAssocies): ?array
    {
        $travaux = [];
        $heuresAssociees = [];

        foreach ($enseignants as $id => $donnees) {
            $tailles = [
                ...array_fill(0, $midi[$id], [60, true]),
                ...array_fill(0, intdiv($unites[$id] - $uneHeure[$id], 2), [120, false]),
                ...array_fill(0, $uneHeure[$id] - $midi[$id], [60, false]),
            ];

            $contenus = $this->repartir($donnees['matieres'], $donnees['midi'], $tailles);

            if ($contenus === null) {
                return null;
            }

            foreach ($contenus as $i => $contenu) {
                $partie = ['enseignant' => $id, 'type' => TypeSalle::Cours, 'duree' => $tailles[$i][0], 'matieres' => $contenu];

                if ($tailles[$i][1]) {
                    $travaux[] = ['type' => 'midi', 'parties' => [$partie]];
                } elseif ($tailles[$i][0] === 120) {
                    $travaux[] = ['type' => 'plein', 'parties' => [$partie]];
                } else {
                    $heuresAssociees[] = $partie;
                }
            }
        }

        foreach ($speciauxMidi as $special) {
            $travaux[] = ['type' => 'midi', 'parties' => [$special]];
        }

        foreach ($speciauxAssocies as $i => $special) {
            $travaux[] = ['type' => 'mixte', 'parties' => [$special, $heuresAssociees[$i]]];
        }

        return $travaux;
    }

    /**
     * Répartit les minutes des matières d'un enseignant dans des séances de tailles données
     * (au moins 30 min par matière dans une séance ; séances de midi : matières autorisées à midi).
     *
     * @param  array<int, int>  $restes  matière => minutes à placer
     * @param  array<int, bool>  $midiOk
     * @param  list<array{0: int, 1: bool}>  $tailles  [minutes, midi seulement]
     * @return list<list<array{0: int, 1: int}>>|null
     */
    private function repartir(array $restes, array $midiOk, array $tailles): ?array
    {
        if ($tailles === []) {
            return array_sum($restes) === 0 ? [] : null;
        }

        [$taille, $midi] = $tailles[0];

        foreach ($this->remplirSeance($restes, $midiOk, $taille, $midi, [], 0) as [$contenu, $nouveauxRestes]) {
            $suite = $this->repartir($nouveauxRestes, $midiOk, array_slice($tailles, 1));

            if ($suite !== null) {
                return [$contenu, ...$suite];
            }
        }

        return null;
    }

    /**
     * Toutes les façons de remplir une séance de $place minutes.
     *
     * @param  array<int, int>  $restes
     * @param  array<int, bool>  $midiOk
     * @param  list<array{0: int, 1: int}>  $contenu
     * @return Generator<int, array{0: list<array{0: int, 1: int}>, 1: array<int, int>}>
     */
    private function remplirSeance(array $restes, array $midiOk, int $place, bool $midi, array $contenu, int $depuis): Generator
    {
        if ($place === 0) {
            yield [$contenu, $restes];

            return;
        }

        $ids = array_keys($restes);
        $minimum = $this->minimumParMatiere;

        for ($i = $depuis; $i < count($ids); $i++) {
            $id = $ids[$i];
            $reste = $restes[$id];

            if ($reste === 0 || ($midi && ! $midiOk[$id])) {
                continue;
            }

            for ($x = min($reste, $place); $x >= $minimum; $x -= 10) {
                if (($reste - $x !== 0 && $reste - $x < $minimum) || ($place - $x !== 0 && $place - $x < $minimum)) {
                    continue;
                }

                $nouveaux = $restes;
                $nouveaux[$id] -= $x;

                yield from $this->remplirSeance($nouveaux, $midiOk, $place - $x, $midi, [...$contenu, [$id, $x]], $i + 1);
            }
        }
    }

    // ---------------------------------------------------------------------------------------------
    // 3. Placement des séances dans les créneaux
    // ---------------------------------------------------------------------------------------------

    /**
     * @param  Plan  $plan
     * @return list<SeanceGeneree>|null
     */
    private function placerClasse(array $plan): ?array
    {
        $this->raisons = [];
        $this->essais = 0;
        $this->bloqueParDisponibilites = null;
        $possibleSansAutresClasses = false;

        foreach ($plan['compositions'] as $travaux) {
            // Les séances partagées (salle de sport, de technologie) d'abord, puis midi, puis le reste
            $rang = ['mixte' => 0, 'midi' => 1, 'plein' => 2];
            usort($travaux, fn (array $a, array $b) => $rang[$a['type']] <=> $rang[$b['type']]);

            foreach ($this->ensemblesDeCreneaux($plan) as $ensemble) {
                // Vérification rapide : chaque séance peut-elle avoir son créneau, d'après les seules disponibilités ?
                if (! $this->compatible($travaux, $ensemble, $plan['motif']['bloc']) || ! $this->couplagePossible($travaux, $ensemble)) {
                    continue;
                }

                $possibleSansAutresClasses = true;

                $utilises = [];
                $seances = $this->placerTravaux($plan, $travaux, 0, $ensemble, $utilises);

                if ($seances !== null) {
                    return $seances;
                }
            }
        }

        if (! $possibleSansAutresClasses) {
            $this->bloqueParDisponibilites = $this->enseignantLePlusContraint($plan);
            $this->raisons = ["indisponible:{$this->bloqueParDisponibilites}" => 1];
        }

        return null;
    }

    /**
     * Couplage séances ↔ créneaux (algorithme des chemins augmentants) : chaque séance a-t-elle un créneau
     * différent où tous ses enseignants sont disponibles ?
     *
     * @param  list<Travail>  $travaux
     * @param  list<Creneau>  $ensemble
     */
    private function couplagePossible(array $travaux, array $ensemble): bool
    {
        $compatibles = [];

        foreach ($travaux as $i => $travail) {
            $compatibles[$i] = [];

            foreach ($ensemble as $j => $creneau) {
                $disponibles = collect($travail['parties'])->every(fn (array $partie) => $this->estDisponible($partie['enseignant'], $creneau));

                if ($creneau['midi'] === ($travail['type'] === 'midi') && $disponibles) {
                    $compatibles[$i][] = $j;
                }
            }

            if ($compatibles[$i] === []) {
                return false;
            }
        }

        $occupants = [];

        foreach (array_keys($travaux) as $i) {
            $vus = [];

            if (! $this->augmenter($i, $compatibles, $occupants, $vus)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, list<int>>  $compatibles
     * @param  array<int, int>  $occupants  créneau => séance
     * @param  array<int, true>  $vus
     */
    private function augmenter(int $i, array $compatibles, array &$occupants, array &$vus): bool
    {
        foreach ($compatibles[$i] as $j) {
            if (isset($vus[$j])) {
                continue;
            }

            $vus[$j] = true;

            if (! isset($occupants[$j]) || $this->augmenter($occupants[$j], $compatibles, $occupants, $vus)) {
                $occupants[$j] = $i;

                return true;
            }
        }

        return false;
    }

    /**
     * L'enseignant de la classe dont les disponibilités sont limitées et qui a le plus d'heures dans la classe.
     *
     * @param  Plan  $plan
     */
    private function enseignantLePlusContraint(array $plan): int
    {
        $heures = [];

        foreach ($plan['compositions'][0] as $travail) {
            foreach ($travail['parties'] as $partie) {
                if ($this->disponibles[$partie['enseignant']] !== null) {
                    $heures[$partie['enseignant']] = ($heures[$partie['enseignant']] ?? 0) + $partie['duree'];
                }
            }
        }

        arsort($heures);

        return (int) (array_key_first($heures) ?? $plan['compositions'][0][0]['parties'][0]['enseignant']);
    }

    /**
     * Les créneaux possibles pour la classe. En 1re et 2e année : un ensemble par organisation
     * de la semaine (quel jour l'après-midi, quelles matinées vont jusqu'à 13 h).
     *
     * @param  Plan  $plan
     * @return list<list<Creneau>>
     */
    private function ensemblesDeCreneaux(array $plan): array
    {
        $creneaux = array_values(array_filter($this->creneaux, fn (array $c) => in_array($c['jour'], $plan['jours'], true)));
        $motif = $plan['motif'];

        if (! $motif['bloc']) {
            return [$creneaux];
        }

        $jours = array_values(array_diff($plan['jours'], [6]));
        $ensembles = [];

        foreach ($this->combinaisons($jours, $motif['apresMidi']) as $apresMidi) {
            $matins = array_values(array_diff($jours, $apresMidi));

            if (count($matins) !== $motif['matinees']) {
                continue;
            }

            foreach ($this->combinaisons($matins, $motif['midi']) as $joursMidi) {
                $ensembles[] = array_values(array_filter($creneaux, fn (array $c) => match (true) {
                    $c['midi'] => in_array($c['jour'], $joursMidi, true),
                    in_array($c['jour'], $apresMidi, true) => $c['debut'] >= $this->midiFin,
                    default => $c['fin'] <= $this->midiDebut,
                }));
            }
        }

        return $this->hasard->shuffleArray($ensembles);
    }

    /**
     * @param  list<Travail>  $travaux
     * @param  list<Creneau>  $ensemble
     */
    private function compatible(array $travaux, array $ensemble, bool $exact): bool
    {
        $midiTravaux = count(array_filter($travaux, fn (array $t) => $t['type'] === 'midi'));
        $midiCreneaux = count(array_filter($ensemble, fn (array $c) => $c['midi']));
        $autresTravaux = count($travaux) - $midiTravaux;
        $autresCreneaux = count($ensemble) - $midiCreneaux;

        return $exact
            ? $midiTravaux === $midiCreneaux && $autresTravaux === $autresCreneaux
            : $midiTravaux <= $midiCreneaux && $autresTravaux <= $autresCreneaux;
    }

    /**
     * Placement par essais et retours en arrière.
     *
     * @param  Plan  $plan
     * @param  list<Travail>  $travaux
     * @param  list<Creneau>  $ensemble
     * @param  array<int, Creneau>  $utilises  créneaux déjà pris par la classe
     * @return list<SeanceGeneree>|null
     */
    private function placerTravaux(array $plan, array $travaux, int $i, array $ensemble, array &$utilises): ?array
    {
        if ($i === count($travaux)) {
            return [];
        }

        foreach ($this->candidats($plan, $travaux[$i], $ensemble, $utilises) as $creneau) {
            foreach ($this->variantes($travaux[$i]) as $parties) {
                if (++$this->essais > self::ESSAIS_MAX) {
                    return null;
                }

                $reservation = $this->reserver($plan['classe'], $creneau, $parties);

                if ($reservation === null) {
                    continue;
                }

                [$jetons, $seances] = $reservation;
                $utilises[$creneau['id']] = $creneau;

                $suite = $this->placerTravaux($plan, $travaux, $i + 1, $ensemble, $utilises);

                if ($suite !== null) {
                    return [...$seances, ...$suite];
                }

                unset($utilises[$creneau['id']]);
                $this->liberer($jetons);
            }
        }

        return null;
    }

    /**
     * Les créneaux où l'on peut essayer de placer la séance, du meilleur au moins bon
     * (jour le moins chargé pour la classe, le matin d'abord).
     *
     * @param  Plan  $plan
     * @param  Travail  $travail
     * @param  list<Creneau>  $ensemble
     * @param  array<int, Creneau>  $utilises
     * @return list<Creneau>
     */
    private function candidats(array $plan, array $travail, array $ensemble, array $utilises): array
    {
        $charge = [];
        foreach ($utilises as $c) {
            $charge[$c['jour']] = ($charge[$c['jour']] ?? 0) + 1;
        }

        $candidats = [];

        foreach ($ensemble as $creneau) {
            if (isset($utilises[$creneau['id']]) || $creneau['midi'] !== ($travail['type'] === 'midi')) {
                continue;
            }

            if ($plan['motif']['midiLibere'] && ! $this->respecteMidi($creneau, $utilises)) {
                continue;
            }

            $candidats[] = ['creneau' => $creneau, 'cle' => [$charge[$creneau['jour']] ?? 0, $creneau['ordre'], $this->hasard->getInt(0, 1000)]];
        }

        usort($candidats, fn (array $a, array $b) => $a['cle'] <=> $b['cle']);

        return array_column($candidats, 'creneau');
    }

    /**
     * Si la classe a cours de 12 h à 13 h, elle est libre de 10 h 05 à 12 h ou de 13 h à 14 h 55.
     *
     * @param  Creneau  $creneau
     * @param  array<int, Creneau>  $utilises
     */
    private function respecteMidi(array $creneau, array $utilises): bool
    {
        $duJour = array_filter($utilises, fn (array $c) => $c['jour'] === $creneau['jour']);
        $avant = collect($duJour)->contains('avantMidi', true) || $creneau['avantMidi'];
        $apres = collect($duJour)->contains('apresMidi', true) || $creneau['apresMidi'];
        $midi = collect($duJour)->contains('midi', true) || $creneau['midi'];

        return ! ($midi && $avant && $apres);
    }

    /**
     * Ordres possibles des parties d'une séance (créneau mixte : éducation physique avant ou après).
     *
     * @param  Travail  $travail
     * @return list<list<array{0: Partie, 1: int}>>
     */
    private function variantes(array $travail): array
    {
        if ($travail['type'] !== 'mixte') {
            return [[[$travail['parties'][0], 0]]];
        }

        [$special, $autre] = $travail['parties'];
        $variantes = [[[$special, 0], [$autre, 60]], [[$autre, 0], [$special, 60]]];

        return $this->hasard->shuffleArray($variantes);
    }

    /**
     * Réserve la classe, l'enseignant et une salle pour chaque partie de la séance.
     *
     * @param  Creneau  $creneau
     * @param  list<array{0: Partie, 1: int}>  $parties  [partie, début en minutes depuis le début du créneau]
     * @return array{0: list<array{0: string, 1: int}>, 1: list<SeanceGeneree>}|null
     */
    private function reserver(Classe $classe, array $creneau, array $parties): ?array
    {
        $jetons = [];
        $seances = [];

        foreach ($parties as [$partie, $decalage]) {
            $debut = $this->heureReelle($creneau, $decalage);
            $fin = $this->heureReelle($creneau, $decalage + $partie['duree']);
            $salleChoisie = null;

            foreach ($this->sallesPossibles($classe, $partie['type']) as $salle) {
                $probleme = $this->probleme($classe->id, $partie['enseignant'], $salle->id, $creneau, $debut, $fin);

                if ($probleme === null) {
                    $salleChoisie = $salle;
                    break;
                }

                $this->raisons[$probleme['code']] = ($this->raisons[$probleme['code']] ?? 0) + 1;

                if (! str_starts_with($probleme['code'], 'salle')) {
                    break; // le problème ne vient pas de la salle : inutile d'en essayer une autre
                }
            }

            if ($salleChoisie === null) {
                $this->liberer($jetons);

                return null;
            }

            foreach (['c'.$classe->id, 'e'.$partie['enseignant'], 's'.$salleChoisie->id] as $cle) {
                $jetons[] = [$cle, $this->occuper($cle, $creneau['jour'], $debut, $fin)];
            }

            $matieres = [];
            $position = $decalage;

            foreach ($partie['matieres'] as [$matiereId, $minutes]) {
                $matieres[] = [
                    'matiere_id' => $matiereId,
                    'heure_debut' => $this->heure($this->heureReelle($creneau, $position)),
                    'heure_fin' => $this->heure($this->heureReelle($creneau, $position + $minutes)),
                ];
                $position += $minutes;
            }

            $seances[] = [
                'classe_id' => $classe->id,
                'horaire_id' => $creneau['id'],
                'enseignant_id' => $partie['enseignant'],
                'salle_id' => $salleChoisie->id,
                'matieres' => $matieres,
            ];
        }

        return [$jetons, $seances];
    }

    /**
     * Salles du bon type, en service et assez grandes ; la salle habituelle de la classe d'abord.
     *
     * @return list<Salle>
     */
    private function sallesPossibles(Classe $classe, TypeSalle $type): array
    {
        $habituelle = $this->salleHabituelle[$classe->id] ?? null;

        return array_values(collect($this->salles)
            ->filter(fn (Salle $s) => $s->type === $type && $s->capacite >= $classe->effectif)
            ->sortBy(fn (Salle $s) => [$s->id === $habituelle ? 0 : 1, $s->capacite])
            ->all());
    }

    /**
     * Le conflit éventuel d'une séance : classe, enseignant (disponible et libre) ou salle (en service, assez grande, libre).
     *
     * @param  Creneau  $creneau
     * @return array{code: string, message: string}|null
     */
    private function probleme(int $classeId, int $enseignantId, int $salleId, array $creneau, int $debut, int $fin): ?array
    {
        $jour = mb_strtolower(Jour::from($creneau['jour'])->label());
        $moment = "le {$jour} de {$this->heureLisible($debut)} à {$this->heureLisible($fin)}";
        $classe = $this->classes[$classeId] ?? null;
        $salle = $this->salles[$salleId] ?? Salle::find($salleId);
        $nom = $this->nomsEnseignants[$enseignantId] ?? User::find($enseignantId)->name ?? '';

        return match (true) {
            $this->occupe('c'.$classeId, $creneau['jour'], $debut, $fin) => ['code' => 'classe', 'message' => "La classe {$classe?->nom} a déjà une séance {$moment}."],
            ! $this->estDisponible($enseignantId, $creneau) => ['code' => "indisponible:{$enseignantId}", 'message' => "{$nom} n'est pas disponible le {$jour} de {$this->heureLisible($creneau['debut'])} à {$this->heureLisible($creneau['fin'])}."],
            $this->occupe('e'.$enseignantId, $creneau['jour'], $debut, $fin) => ['code' => "occupe:{$enseignantId}", 'message' => "{$nom} a déjà une séance {$moment}."],
            $salle === null || ! $salle->en_service => ['code' => 'salle:hors_service', 'message' => "La salle {$salle?->nom} est hors service."],
            $classe !== null && $salle->capacite < $classe->effectif => ['code' => 'salle:capacite', 'message' => "La salle {$salle->nom} est trop petite pour la classe {$classe->nom} ({$classe->effectif} élèves)."],
            $this->occupe('s'.$salleId, $creneau['jour'], $debut, $fin) => ['code' => "salle:{$salle->type->value}", 'message' => "La salle {$salle->nom} est déjà occupée {$moment}."],
            default => null,
        };
    }

    /**
     * @param  Creneau  $creneau
     */
    private function estDisponible(int $enseignantId, array $creneau): bool
    {
        $disponibles = $this->disponibles[$enseignantId] ?? null;

        return $disponibles === null || isset($disponibles[$creneau['cle']]);
    }

    private function occupe(string $cle, int $jour, int $debut, int $fin): bool
    {
        $a = $jour * 1440 + $debut;
        $b = $jour * 1440 + $fin;

        foreach ($this->occupations[$cle] ?? [] as [$debutPris, $finPris]) {
            if ($a < $finPris && $debutPris < $b) {
                return true;
            }
        }

        return false;
    }

    private function occuper(string $cle, int $jour, int $debut, int $fin): int
    {
        $this->occupations[$cle][++$this->jeton] = [$jour * 1440 + $debut, $jour * 1440 + $fin];

        return $this->jeton;
    }

    /**
     * @param  list<array{0: string, 1: int}>  $jetons
     */
    private function liberer(array $jetons): void
    {
        foreach ($jetons as [$cle, $jeton]) {
            unset($this->occupations[$cle][$jeton]);
        }
    }

    /**
     * Heure réelle d'une position (en minutes « du décret ») dans le créneau :
     * la 1re heure va du début au milieu (8 h → 9 h), la 2e du milieu à la fin (9 h → 9 h 55).
     *
     * @param  Creneau  $creneau
     */
    private function heureReelle(array $creneau, int $position): int
    {
        if ($creneau['midi']) {
            return $creneau['debut'] + $position;
        }

        return $position <= 60
            ? $creneau['debut'] + (int) round($position * ($creneau['milieu'] - $creneau['debut']) / 60)
            : $creneau['milieu'] + (int) round(($position - 60) * ($creneau['fin'] - $creneau['milieu']) / 60);
    }

    // ---------------------------------------------------------------------------------------------
    // 4. Cause d'un échec (5a)
    // ---------------------------------------------------------------------------------------------

    private function causeEchec(Classe $classe): string
    {
        arsort($this->raisons);
        $code = (string) array_key_first($this->raisons);
        [$type, $detail] = explode(':', $code) + ['', ''];
        $nom = $this->nomsEnseignants[(int) $detail] ?? '';
        $debut = 'Aucune solution ne respecte toutes les règles';

        return match ($type) {
            'indisponible' => "{$debut} : les disponibilités de {$nom} ne laissent pas assez de créneaux pour la classe {$classe->nom}.",
            'occupe' => "{$debut} : {$nom} n'a pas assez de créneaux libres pour la classe {$classe->nom} (séances dans d'autres classes).",
            'salle' => "{$debut} : il n'y a pas assez de salles ".$this->libelleType(TypeSalle::tryFrom($detail) ?? TypeSalle::Cours)." libres pour la classe {$classe->nom}.",
            default => "{$debut} pour la classe {$classe->nom}. Vérifiez les affectations, les disponibilités et les salles.",
        };
    }

    // ---------------------------------------------------------------------------------------------
    // Outils
    // ---------------------------------------------------------------------------------------------

    /**
     * Toutes les combinaisons de $k éléments.
     *
     * @param  list<int>  $elements
     * @return list<list<int>>
     */
    private function combinaisons(array $elements, int $k): array
    {
        if ($k === 0) {
            return [[]];
        }

        $resultats = [];

        foreach ($elements as $i => $element) {
            foreach ($this->combinaisons(array_slice($elements, $i + 1), $k - 1) as $suite) {
                $resultats[] = [$element, ...$suite];
            }
        }

        return $resultats;
    }

    private function libelleType(TypeSalle $type): string
    {
        return match ($type) {
            TypeSalle::Cours => 'de cours',
            TypeSalle::Sport => 'de sport',
            TypeSalle::Technologie => 'de technologie',
        };
    }

    private function minutes(string $heure): int
    {
        [$h, $m] = array_map('intval', explode(':', $heure));

        return $h * 60 + $m;
    }

    private function heure(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 780 → « 13 h », 835 → « 13 h 55 ».
     */
    private function heureLisible(int $minutes): string
    {
        $reste = $minutes % 60;

        return intdiv($minutes, 60).' h'.($reste === 0 ? '' : sprintf(' %02d', $reste));
    }
}
