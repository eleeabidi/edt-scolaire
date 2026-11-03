<?php

namespace App\Models;

use App\Enums\Jour;
use App\Enums\StatutDemande;
use App\Enums\StatutEmploi;
use App\Http\Controllers\EmploiDuTempsController;
use App\Support\CreneauHoraire;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Demande d'indisponibilité signalée par un enseignant (lien « signale ») et traitée par le directeur
 * de son école (lien « traite »). Acceptée, elle retire les créneaux demandés des disponibilités.
 *
 * @property int $id
 * @property int $enseignant_id
 * @property Jour $jour
 * @property string $heure_debut
 * @property string $heure_fin
 * @property string $motif
 * @property CarbonImmutable $date_envoi
 * @property StatutDemande $statut
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $enseignant
 */
#[Table('demandes_indisponibilite')]
#[Fillable(['enseignant_id', 'jour', 'heure_debut', 'heure_fin', 'motif', 'date_envoi', 'statut'])]
class DemandeIndisponibilite extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'statut' => 'en_attente',
    ];

    protected function casts(): array
    {
        return [
            'jour' => Jour::class,
            'date_envoi' => 'date',
            'statut' => StatutDemande::class,
        ];
    }

    public function estEnAttente(): bool
    {
        return $this->statut === StatutDemande::EnAttente;
    }

    /**
     * Les créneaux de l'école compris entre l'heure de début et l'heure de fin, ce jour-là (clés « jour-HH:MM »).
     *
     * @return list<string>
     */
    public function creneauxDemandes(): array
    {
        $debut = CreneauHoraire::minutes($this->heure_debut);
        $fin = CreneauHoraire::minutes($this->heure_fin);

        return array_values(array_map(fn (array $c) => $c['cle'], array_filter(
            Disponibilite::creneaux(),
            fn (array $c) => $c['jour'] === $this->jour->value
                && CreneauHoraire::minutes($c['debut']) >= $debut
                && CreneauHoraire::minutes($c['fin']) <= $fin,
        )));
    }

    /**
     * Heures disponibles de l'enseignant (en minutes) une fois les créneaux demandés retirés.
     */
    public function minutesDisponiblesApres(): int
    {
        $disponibles = Disponibilite::clesDisponibles($this->enseignant);
        $retires = $this->creneauxDemandes();

        return (int) collect(Disponibilite::creneaux())
            ->filter(fn (array $c) => ($disponibles === null || in_array($c['cle'], $disponibles, true)) && ! in_array($c['cle'], $retires, true))
            ->sum('minutes');
    }

    public function minutesAffectees(): int
    {
        return Affectation::minutesPourEnseignant($this->enseignant_id);
    }

    /**
     * Les heures disponibles après acceptation restent-elles au moins égales aux heures affectées ?
     * (même calcul que l'avertissement de « Gestion des enseignants »)
     */
    public function verifierHeuresSuffisantes(): bool
    {
        return $this->minutesDisponiblesApres() >= $this->minutesAffectees();
    }

    /**
     * Retire les créneaux demandés des disponibilités de l'enseignant, puis passe la demande au statut « acceptée ».
     */
    public function accepter(): void
    {
        DB::transaction(function () {
            $tous = array_column(Disponibilite::creneaux(), 'cle');
            $disponibles = Disponibilite::clesDisponibles($this->enseignant) ?? $tous;
            $restants = array_diff($disponibles, $this->creneauxDemandes());

            Disponibilite::enregistrer($this->enseignant, array_values(array_diff($tous, $restants)));
            $this->update(['statut' => StatutDemande::Acceptee]);
        });
    }

    /**
     * Passe la demande au statut « refusée » : les disponibilités ne changent pas.
     */
    public function refuser(): void
    {
        $this->update(['statut' => StatutDemande::Refusee]);
    }

    /**
     * Les séances de l'enseignant sur les créneaux demandés, dans l'emploi du temps publié de son école
     * pour l'année scolaire en cours.
     *
     * @return Collection<int, Seance>
     */
    public function chercherSeancesTouchees(): Collection
    {
        $emploi = $this->emploiDuTempsPublie();

        if ($emploi === null) {
            return new Collection;
        }

        $creneaux = $this->creneauxDemandes();

        return $emploi->seances()
            ->where('enseignant_id', $this->enseignant_id)
            ->with(['classe', 'salle', 'horaire', 'matieres.matiere'])
            ->get()
            ->filter(fn (Seance $s) => in_array($s->horaire->jour->value.'-'.substr($s->horaire->heure_debut, 0, 5), $creneaux, true))
            ->sortBy(fn (Seance $s) => $s->matieres->min('heure_debut'))
            ->values();
    }

    public function emploiDuTempsPublie(): ?EmploiDuTemps
    {
        return $this->enseignant->ecole?->emploisDuTemps()
            ->where('annee_scolaire', EmploiDuTempsController::anneesScolaires()[0])
            ->where('statut', StatutEmploi::Publie)
            ->first();
    }

    /**
     * « 8 h – 9 h 55 »
     */
    public function horaireLisible(): string
    {
        return CreneauHoraire::lisible(CreneauHoraire::minutes($this->heure_debut)).' – '
            .CreneauHoraire::lisible(CreneauHoraire::minutes($this->heure_fin));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
