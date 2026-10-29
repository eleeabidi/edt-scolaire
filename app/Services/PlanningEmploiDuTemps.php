<?php

namespace App\Services;

use App\Enums\Jour;
use App\Enums\TypeSalle;
use App\Models\Contrainte;
use App\Models\Disponibilite;
use App\Models\EmploiDuTemps;
use App\Models\Horaire;
use App\Models\Salle;
use App\Models\Seance;
use App\Models\SeanceMatiere;
use App\Support\CreneauHoraire;
use Illuminate\Support\Collection;

/**
 * Vérifications sur un emploi du temps déjà enregistré :
 * - T14.1 detecterConflits() : même enseignant, même salle ou même classe au même moment,
 *   enseignant non disponible, salle hors service ;
 * - T14.3 une solution pour chaque conflit (une salle libre ou un créneau libre) ;
 * - T13.6 / T16.2 verifierDeplacement() : une séance peut-elle aller sur un autre créneau ou dans une
 *   autre salle ? (classe, enseignant et salle libres, enseignant disponible, salle en service, du bon type
 *   et assez grande, règles de gestion des emplois du temps).
 *
 * @phpstan-type Verification array{code: string|null, cause: string|null, debut: int, fin: int, decalage: int}
 * @phpstan-type Conflit array{cle: string, type: string, libelle: string, seance: array{id: int, classeId: int, classe: string, matieres: string, enseignant: string, salle: string, quand: string}, explication: string, solution: string|null}
 */
class PlanningEmploiDuTemps
{
    /** Libellés des types de conflit (badges de la page « Conflits »), dans l'ordre d'affichage. */
    public const TYPES = [
        'indisponible' => 'Enseignant non disponible',
        'hors_service' => 'Salle hors service',
        'enseignant' => 'Même enseignant au même moment',
        'salle' => 'Même salle au même moment',
        'classe' => 'Même classe au même moment',
    ];

    /** @var Collection<int, Seance> */
    private Collection $seances;

    /** @var Collection<int, Horaire> */
    private Collection $horaires;

    /** @var Collection<int, Salle> */
    private Collection $salles;

    /** @var array<int, array<string, true>|null> créneaux disponibles (« jour-HH:MM »), null = disponible partout */
    private array $disponibles = [];

    /** @var array<int, array{bloc: bool, matinees: int, apresMidi: int, midi: int, midiLibere: bool}> */
    private array $motifs = [];

    private int $midiDebut = 720;

    private int $midiFin = 780;

    public function __construct(private readonly EmploiDuTemps $emploi)
    {
        $this->seances = $emploi->seances()->with(['classe', 'enseignant', 'salle', 'horaire', 'matieres.matiere'])->get()->keyBy('id');
        $this->horaires = Horaire::orderBy('jour')->orderBy('heure_debut')->get()->keyBy('id');
        $this->salles = $emploi->ecole->salles()->orderBy('nom')->get()->keyBy('id');

        $midi = Contrainte::generales('pause_midi')->first()->valeur ?? '12:00-13:00';
        [$debut, $fin] = explode('-', $midi);
        $this->midiDebut = CreneauHoraire::minutes($debut);
        $this->midiFin = CreneauHoraire::minutes($fin);

        foreach ($this->seances as $seance) {
            $this->disponibles[$seance->enseignant_id] = null;
        }

        foreach (Disponibilite::whereIn('enseignant_id', array_keys($this->disponibles))->get() as $d) {
            $this->disponibles[$d->enseignant_id] ??= [];
            $this->disponibles[$d->enseignant_id][$d->jour->value.'-'.substr($d->heure_debut, 0, 5)] = true;
        }
    }

    // ---------------------------------------------------------------------------------------------
    // T14.1 — Conflits
    // ---------------------------------------------------------------------------------------------

    /**
     * @return list<Conflit>
     */
    public function detecterConflits(): array
    {
        $conflits = [];

        // Deux séances au même moment : même classe, même enseignant ou même salle
        foreach ($this->seances->groupBy(fn (Seance $s) => $s->horaire->jour->value) as $duJour) {
            $liste = $duJour->sortBy('id')->values();

            foreach ($liste as $i => $a) {
                for ($j = $i + 1; $j < $liste->count(); $j++) {
                    $b = $liste[$j];

                    if (! $this->chevauchent($a, $b)) {
                        continue;
                    }

                    foreach (['classe' => 'classe_id', 'enseignant' => 'enseignant_id', 'salle' => 'salle_id'] as $type => $colonne) {
                        if ($a->{$colonne} === $b->{$colonne}) {
                            $conflits[] = $this->conflit($type, $b, $a);
                        }
                    }
                }
            }
        }

        // Une séance seule : enseignant non disponible, salle hors service
        foreach ($this->seances->sortBy('id') as $seance) {
            if (! $this->estDisponible($seance->enseignant_id, $seance->horaire)) {
                $conflits[] = $this->conflit('indisponible', $seance);
            }

            if (! $seance->salle->en_service) {
                $conflits[] = $this->conflit('hors_service', $seance);
            }
        }

        $ordre = array_flip(array_keys(self::TYPES));
        usort($conflits, fn (array $x, array $y) => [$ordre[$x['type']], $x['seance']['id']] <=> [$ordre[$y['type']], $y['seance']['id']]);

        return $conflits;
    }

    /**
     * @return Conflit
     */
    private function conflit(string $type, Seance $seance, ?Seance $autre = null): array
    {
        [$debut, $fin] = $this->intervalle($seance);
        $jour = $this->jour($seance->horaire);
        $moment = $autre ? $this->moment($autre) : '';

        $explication = match ($type) {
            'indisponible' => "{$seance->enseignant->name} n'est pas disponible le {$jour} de ".CreneauHoraire::lisible(CreneauHoraire::minutes($seance->horaire->heure_debut)).' à '.CreneauHoraire::lisible(CreneauHoraire::minutes($seance->horaire->heure_fin)).' (disponibilités modifiées après la génération).',
            'hors_service' => ucfirst($this->nomSalle($seance->salle)).' a été mise hors service.',
            'enseignant' => "{$seance->enseignant->name} a aussi une séance en {$autre?->classe->nom} {$moment}.",
            'salle' => ucfirst($this->nomSalle($seance->salle))." accueille aussi la classe {$autre?->classe->nom} {$moment}.",
            default => "La classe {$seance->classe->nom} a aussi une séance avec {$autre?->enseignant->name} {$moment}.",
        };

        return [
            'cle' => "{$type}-{$seance->id}-".($autre->id ?? 0),
            'type' => $type,
            'libelle' => self::TYPES[$type],
            'seance' => [
                'id' => $seance->id,
                'classeId' => $seance->classe_id,
                'classe' => $seance->classe->nom,
                'matieres' => $seance->matieres->sortBy('heure_debut')->map(fn (SeanceMatiere $m) => $m->matiere->nom)->join(', '),
                'enseignant' => $seance->enseignant->name,
                'salle' => $seance->salle->nom,
                'quand' => "{$jour} ".CreneauHoraire::lisible($debut).' – '.CreneauHoraire::lisible($fin),
            ],
            'explication' => $explication,
            'solution' => $this->solution($type, $seance),
        ];
    }

    /**
     * T14.3 — Une salle libre (salle hors service ou déjà occupée), sinon un créneau libre.
     */
    private function solution(string $type, Seance $seance): ?string
    {
        if (in_array($type, ['hors_service', 'salle'], true)) {
            $salle = $this->sallesLibres($seance)[0] ?? null;

            if ($salle !== null) {
                return 'changer la salle de la séance pour '.$this->nomSalle($salle).', libre sur ce créneau.';
            }
        }

        $libre = $this->creneauLibre($seance);

        if ($libre === null) {
            return null;
        }

        [$horaire, $salle] = $libre;
        $quand = $this->jour($horaire).' '.CreneauHoraire::lisible(CreneauHoraire::minutes($horaire->heure_debut)).' – '.CreneauHoraire::lisible(CreneauHoraire::minutes($horaire->heure_fin));

        return $salle->id === $seance->salle_id
            ? "déplacer la séance au {$quand} (la classe, l'enseignant et la salle sont libres)."
            : "déplacer la séance au {$quand}, dans ".$this->nomSalle($salle).' (la classe, l\'enseignant et la salle sont libres).';
    }

    /**
     * Le premier créneau où la séance peut aller (avec sa salle, ou une autre salle libre).
     *
     * @return array{0: Horaire, 1: Salle}|null
     */
    public function creneauLibre(Seance $seance): ?array
    {
        foreach ($this->horaires as $horaire) {
            if ($horaire->id === $seance->horaire_id) {
                continue;
            }

            if ($this->verifierDeplacement($seance, $horaire)['cause'] === null) {
                return [$horaire, $seance->salle];
            }

            foreach ($this->sallesLibres($seance, $horaire) as $salle) {
                if ($this->verifierDeplacement($seance, $horaire, $salle)['cause'] === null) {
                    return [$horaire, $salle];
                }
            }
        }

        return null;
    }

    /**
     * Les autres salles possibles pour la séance (sur son créneau ou sur un autre) : en service,
     * du type demandé par ses matières, assez grandes pour la classe et libres.
     *
     * @return list<Salle>
     */
    public function sallesLibres(Seance $seance, ?Horaire $horaire = null): array
    {
        $horaire ??= $seance->horaire;
        $libres = [];

        foreach ($this->salles as $salle) {
            if ($salle->id === $seance->salle_id && $horaire->id === $seance->horaire_id) {
                continue;
            }

            $verification = $this->verifierDeplacement($seance, $horaire, $salle, $horaire->id === $seance->horaire_id);

            if ($verification['cause'] === null) {
                $libres[] = $salle;
            }
        }

        return $libres;
    }

    // ---------------------------------------------------------------------------------------------
    // T13.6 / T16.2 — Vérifier un déplacement ou un changement de salle
    // ---------------------------------------------------------------------------------------------

    /**
     * La séance peut-elle aller sur ce créneau (et dans cette salle) ? Renvoie la cause du refus, ou null.
     *
     * @return Verification
     */
    public function verifierDeplacement(Seance $seance, Horaire $horaire, ?Salle $salle = null, bool $memeCreneau = false): array
    {
        $salle ??= $seance->salle;
        $classe = $seance->classe;
        $debutCreneau = CreneauHoraire::minutes($horaire->heure_debut);
        $finCreneau = CreneauHoraire::minutes($horaire->heure_fin);
        $midi = $debutCreneau === $this->midiDebut && $finCreneau === $this->midiFin;
        $duree = $this->duree($seance);
        $jour = $this->jour($horaire);
        $refus = fn (string $code, string $cause) => ['code' => $code, 'cause' => $cause, 'debut' => 0, 'fin' => 0, 'decalage' => 0];

        if ($horaire->id === $seance->horaire_id && ! $memeCreneau && $salle->id === $seance->salle_id) {
            return $refus('meme', 'La séance est déjà sur ce créneau.');
        }

        // Règles de gestion : samedi, midi
        if ($horaire->jour === Jour::Samedi && $midi) {
            return $refus('regle', "Le samedi, il n'y a pas cours de 12 h à 13 h.");
        }
        if ($horaire->jour === Jour::Samedi && ! $classe->cours_samedi) {
            return $refus('regle', "La classe {$classe->nom} n'a pas cours le samedi.");
        }
        if ($midi && $duree > $finCreneau - $debutCreneau) {
            return $refus('regle', 'Une séance de '.CreneauHoraire::lisible($duree).' ne tient pas dans le créneau de 12 h à 13 h.');
        }
        if ($midi && $seance->matieres->contains(fn (SeanceMatiere $m) => ! $m->matiere->autorisee_midi)) {
            return $refus('regle', 'Seules les matières autorisées à midi peuvent être placées de 12 h à 13 h.');
        }

        // La salle : en service, du type demandé, assez grande
        $type = $this->typeDemande($seance);
        if (! $salle->en_service) {
            return $refus('salle', ucfirst($this->nomSalle($salle)).' est hors service.');
        }
        if ($salle->type !== $type) {
            return $refus('salle', ucfirst($this->nomSalle($salle)).' n\'est pas une salle '.$this->libelleType($type).'.');
        }
        if ($salle->capacite < $classe->effectif) {
            return $refus('salle', ucfirst($this->nomSalle($salle))." est trop petite pour la classe {$classe->nom} ({$classe->effectif} élèves).");
        }

        // Une séance d'1 h peut aller dans la 1re ou la 2e heure d'un créneau de 2 h
        $decalages = $midi || $duree >= 120 ? [0] : array_values(array_unique([$this->decalage($seance), 0, 60]));
        $premiereCause = null;

        foreach ($decalages as $decalage) {
            $debut = CreneauHoraire::heureReelle($debutCreneau, $finCreneau, $midi, $decalage);
            $fin = CreneauHoraire::heureReelle($debutCreneau, $finCreneau, $midi, $decalage + $duree);
            $moment = "le {$jour} de ".CreneauHoraire::lisible($debut).' à '.CreneauHoraire::lisible($fin);

            $probleme = match (true) {
                ($autre = $this->occupant('classe_id', $classe->id, $seance, $horaire, $debut, $fin)) !== null => ['classe', "La classe {$classe->nom} a déjà une séance {$moment} (avec {$autre->enseignant->name})."],
                ! $this->estDisponible($seance->enseignant_id, $horaire) => ['indisponible', "{$seance->enseignant->name} n'est pas disponible le {$jour} de ".CreneauHoraire::lisible($debutCreneau).' à '.CreneauHoraire::lisible($finCreneau).'.'],
                ($autre = $this->occupant('enseignant_id', $seance->enseignant_id, $seance, $horaire, $debut, $fin)) !== null => ['enseignant', "{$seance->enseignant->name} a déjà une séance {$moment} (en {$autre->classe->nom})."],
                ($autre = $this->occupant('salle_id', $salle->id, $seance, $horaire, $debut, $fin)) !== null => ['salle', ucfirst($this->nomSalle($salle))." est déjà occupée {$moment} (par la classe {$autre->classe->nom})."],
                default => null,
            };

            if ($probleme === null) {
                $regle = $this->verifierRegles($seance, $horaire);

                return $regle === null
                    ? ['code' => null, 'cause' => null, 'debut' => $debut, 'fin' => $fin, 'decalage' => $decalage]
                    : $refus('regle', $regle);
            }

            $premiereCause ??= $probleme;
        }

        return $refus($premiereCause[0], $premiereCause[1]);
    }

    /**
     * Règles de gestion de la classe après le déplacement : règle de midi, et en 1re et 2e année
     * un seul bloc de 4 h par jour (4 matinées et 1 après-midi, matinées jusqu'à 13 h en 2e année).
     */
    private function verifierRegles(Seance $seance, Horaire $horaire): ?string
    {
        $classe = $seance->classe;
        $motif = $this->motifs[$classe->id] ??= app(GenerateurEmploiDuTemps::class)->motif($classe);

        // Créneaux de la classe après le déplacement
        $horaires = $this->horaires->all();
        $ids = $this->seances
            ->filter(fn (Seance $s) => $s->classe_id === $classe->id && $s->id !== $seance->id)
            ->map(fn (Seance $s) => $s->horaire_id)
            ->push($horaire->id)
            ->unique()
            ->all();
        $creneaux = collect(array_map(fn (int $id) => $horaires[$id], $ids));

        foreach ($creneaux->groupBy(fn (Horaire $h) => $h->jour->value) as $duJour) {
            $debuts = $duJour->map(fn (Horaire $h) => CreneauHoraire::minutes($h->heure_debut))->all();
            $fins = $duJour->map(fn (Horaire $h) => CreneauHoraire::minutes($h->heure_fin))->all();
            $aMidi = in_array($this->midiDebut, $debuts, true);

            if ($motif['midiLibere'] && $aMidi && in_array($this->midiDebut, $fins, true) && in_array($this->midiFin, $debuts, true)) {
                return 'Si une classe a cours de 12 h à 13 h, elle doit être libre de 10 h 05 à 12 h ou de 13 h à 14 h 55.';
            }
        }

        if (! $motif['bloc']) {
            return null;
        }

        $apresMidi = 0;
        $midis = 0;

        foreach ($creneaux->groupBy(fn (Horaire $h) => $h->jour->value) as $duJour) {
            $matin = $duJour->filter(fn (Horaire $h) => CreneauHoraire::minutes($h->heure_fin) <= $this->midiDebut)->count();
            $soir = $duJour->filter(fn (Horaire $h) => CreneauHoraire::minutes($h->heure_debut) >= $this->midiFin)->count();
            $midi = $duJour->count() - $matin - $soir;

            $blocValide = ($matin === 2 && $soir === 0) || ($matin === 0 && $soir === 2 && $midi === 0);
            if (! $blocValide) {
                return $this->messageBloc($classe->niveau, $motif);
            }

            $apresMidi += $soir === 2 ? 1 : 0;
            $midis += $midi;
        }

        return $apresMidi === $motif['apresMidi'] && $midis === $motif['midi'] ? null : $this->messageBloc($classe->niveau, $motif);
    }

    /**
     * @param  array{bloc: bool, matinees: int, apresMidi: int, midi: int, midiLibere: bool}  $motif
     */
    private function messageBloc(int $niveau, array $motif): string
    {
        $libelle = $niveau === 1 ? '1re année' : "{$niveau}e année";
        $midi = $motif['midi'] > 0 ? ", dont {$motif['midi']} matinées jusqu'à 13 h" : '';

        return "En {$libelle}, chaque jour a un seul bloc de 4 heures ({$motif['matinees']} matinées et {$motif['apresMidi']} après-midi par semaine{$midi}) : ce déplacement ne respecte pas cette organisation.";
    }

    // ---------------------------------------------------------------------------------------------
    // Outils
    // ---------------------------------------------------------------------------------------------

    /**
     * Une autre séance (même classe, même enseignant ou même salle) sur ce créneau, au même moment.
     */
    private function occupant(string $colonne, int $valeur, Seance $deplacee, Horaire $horaire, int $debut, int $fin): ?Seance
    {
        foreach ($this->seances as $s) {
            if ($s->id === $deplacee->id || $s->{$colonne} !== $valeur || $s->horaire->jour !== $horaire->jour) {
                continue;
            }

            [$a, $b] = $this->intervalle($s);

            if ($debut < $b && $a < $fin) {
                return $s;
            }
        }

        return null;
    }

    private function chevauchent(Seance $a, Seance $b): bool
    {
        [$debutA, $finA] = $this->intervalle($a);
        [$debutB, $finB] = $this->intervalle($b);

        return $debutA < $finB && $debutB < $finA;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function intervalle(Seance $seance): array
    {
        $matieres = $seance->matieres;

        return [
            CreneauHoraire::minutes((string) $matieres->min('heure_debut')),
            CreneauHoraire::minutes((string) $matieres->max('heure_fin')),
        ];
    }

    /**
     * Durée de la séance en minutes « du décret » (1 h ou 2 h).
     */
    private function duree(Seance $seance): int
    {
        return (int) $seance->matieres->sum(fn (SeanceMatiere $m) => CreneauHoraire::minutesDecret($m->heure_debut, $m->heure_fin));
    }

    /**
     * Position de la séance dans son créneau : 0 (1re heure) ou 60 (2e heure).
     */
    private function decalage(Seance $seance): int
    {
        [$debut] = $this->intervalle($seance);

        return $debut > CreneauHoraire::minutes($seance->horaire->heure_debut) ? 60 : 0;
    }

    private function typeDemande(Seance $seance): TypeSalle
    {
        $type = $seance->matieres->first()?->matiere->type_salle;

        return $type ?? TypeSalle::Cours;
    }

    private function estDisponible(int $enseignantId, Horaire $horaire): bool
    {
        $disponibles = $this->disponibles[$enseignantId] ?? null;

        return $disponibles === null || isset($disponibles[$horaire->jour->value.'-'.substr($horaire->heure_debut, 0, 5)]);
    }

    private function jour(Horaire $horaire): string
    {
        return mb_strtolower($horaire->jour->label());
    }

    private function moment(Seance $seance): string
    {
        [$debut, $fin] = $this->intervalle($seance);

        return "le {$this->jour($seance->horaire)} de ".CreneauHoraire::lisible($debut).' à '.CreneauHoraire::lisible($fin);
    }

    /**
     * « la Salle 4 », « la Salle de sport », mais « la salle Gymnase ».
     */
    private function nomSalle(Salle $salle): string
    {
        return str_starts_with(mb_strtolower($salle->nom), 'salle') ? "la {$salle->nom}" : "la salle {$salle->nom}";
    }

    private function libelleType(TypeSalle $type): string
    {
        return match ($type) {
            TypeSalle::Cours => 'de cours',
            TypeSalle::Sport => 'de sport',
            TypeSalle::Technologie => 'de technologie',
        };
    }
}
