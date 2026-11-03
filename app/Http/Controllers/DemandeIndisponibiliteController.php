<?php

namespace App\Http\Controllers;

use App\Enums\Jour;
use App\Enums\StatutDemande;
use App\Models\DemandeIndisponibilite;
use App\Models\Disponibilite;
use App\Models\Seance;
use App\Support\CreneauHoraire;
use App\Support\Duree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US21 — Demandes d'indisponibilité : l'enseignant les signale (T21.3, T21.6),
 * le directeur de son école les accepte ou les refuse (T21.4, T21.5).
 */
class DemandeIndisponibiliteController extends Controller
{
    /**
     * Enseignant — « Mes demandes d'indisponibilité » : le formulaire et ses demandes, de la plus récente à la plus ancienne.
     */
    public function mesDemandes(Request $request): Response
    {
        $demandes = $request->user()->demandesIndisponibilite()
            ->orderByDesc('date_envoi')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('mes-demandes/Index', [
            'demandes' => $demandes->map(fn (DemandeIndisponibilite $d) => $this->pourAffichage($d))->values(),
            'jours' => $this->creneauxParJour(),
        ]);
    }

    /**
     * Enseignant — Signaler une indisponibilité : enregistrée au statut « en attente », avec la date du jour.
     */
    public function signalerIndisponibilite(Request $request): RedirectResponse
    {
        $request->validate([
            'jour' => ['required', Rule::enum(Jour::class)],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'jour.required' => 'Choisissez un jour.',
            'jour.*' => 'Choisissez un jour du lundi au samedi.',
            'heure_debut.required' => "Choisissez l'heure de début.",
            'heure_fin.required' => "Choisissez l'heure de fin.",
            'motif.required' => 'Saisissez le motif.',
            'motif.max' => 'Le motif ne doit pas dépasser 255 caractères.',
            '*.date_format' => 'Choisissez une heure parmi les créneaux de l\'école.',
        ]);

        // Les heures se choisissent parmi les créneaux de l'école, ce jour-là
        $jour = $request->integer('jour');
        $creneaux = collect(Disponibilite::creneaux())->where('jour', $jour);
        $debut = $request->string('heure_debut')->value();
        $fin = $request->string('heure_fin')->value();

        if (! $creneaux->contains('debut', $debut)) {
            throw ValidationException::withMessages(['heure_debut' => "Choisissez une heure parmi les créneaux de l'école."]);
        }
        if (! $creneaux->contains('fin', $fin)) {
            throw ValidationException::withMessages(['heure_fin' => "Choisissez une heure parmi les créneaux de l'école."]);
        }
        if (CreneauHoraire::minutes($fin) <= CreneauHoraire::minutes($debut)) {
            throw ValidationException::withMessages(['heure_fin' => "L'heure de fin doit être après l'heure de début."]);
        }

        $request->user()->demandesIndisponibilite()->create([
            'jour' => $jour,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'motif' => $request->string('motif')->trim()->value(),
            'date_envoi' => today(),
            'statut' => StatutDemande::EnAttente,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Votre demande a été envoyée au directeur.']);

        return back();
    }

    /**
     * Directeur — Les demandes des enseignants de son école : en attente d'abord, puis de la plus récente à la plus ancienne.
     */
    public function consulterDemandes(Request $request): Response
    {
        $ecole = $request->user()->ecole;

        $demandes = DemandeIndisponibilite::query()
            ->whereHas('enseignant', fn ($q) => $q->where('ecole_id', $ecole->id))
            ->with('enseignant')
            ->orderByRaw('statut = ? desc', [StatutDemande::EnAttente->value])
            ->orderByDesc('date_envoi')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('demandes/Index', [
            'ecoleNom' => $ecole->nom,
            'annee' => EmploiDuTempsController::anneesScolaires()[0],
            'demandes' => $demandes->map(fn (DemandeIndisponibilite $d) => [
                ...$this->pourAffichage($d),
                'enseignant' => $d->enseignant->name,
                'initiales' => mb_strtoupper(mb_substr($d->enseignant->prenom, 0, 1).mb_substr($d->enseignant->nom, 0, 1)),
            ])->values(),
        ]);
    }

    /**
     * Directeur — Accepter une demande : vérifier les heures (4a : confirmation si elles deviennent insuffisantes),
     * retirer les créneaux des disponibilités, puis signaler les séances touchées de l'emploi du temps publié (5a).
     */
    public function accepterDemande(Request $request, DemandeIndisponibilite $demande): RedirectResponse
    {
        if (! $this->peutTraiter($request, $demande)) {
            return back();
        }

        if (! $demande->verifierHeuresSuffisantes() && ! $request->boolean('confirme')) {
            throw ValidationException::withMessages([
                'confirme' => "Si vous acceptez cette demande, les heures disponibles de {$demande->enseignant->name} deviennent inférieures à ses heures affectées.",
                'heuresAffectees' => Duree::format($demande->minutesAffectees()),
                'heuresDisponibles' => Duree::format($demande->minutesDisponiblesApres()),
            ]);
        }

        $demande->accepter();
        $seances = $demande->chercherSeancesTouchees();

        if ($seances->isNotEmpty()) {
            Inertia::flash('seancesTouchees', [
                'enseignant' => $demande->enseignant->name,
                'emploiId' => $seances->first()->emploi_du_temps_id,
                'seances' => $seances->map(fn (Seance $s) => [
                    'id' => $s->id,
                    'quand' => $s->horaire->jour->label().' · '
                        .CreneauHoraire::lisible(CreneauHoraire::minutes((string) $s->matieres->min('heure_debut'))).' – '
                        .CreneauHoraire::lisible(CreneauHoraire::minutes((string) $s->matieres->max('heure_fin'))),
                    'detail' => $s->classe->nom.' · '.$s->matieres->sortBy('heure_debut')->map(fn ($m) => $m->matiere->nom)->join(', ').' · '.$s->salle->nom,
                    'typeSalle' => $s->salle->type->value,
                ])->values()->all(),
            ]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => "La demande de {$demande->enseignant->name} est acceptée : ses disponibilités ont été mises à jour."]);
        }

        return back();
    }

    /**
     * Directeur — Refuser une demande : les disponibilités de l'enseignant ne changent pas.
     */
    public function refuserDemande(Request $request, DemandeIndisponibilite $demande): RedirectResponse
    {
        if (! $this->peutTraiter($request, $demande)) {
            return back();
        }

        $demande->refuser();
        Inertia::flash('toast', ['type' => 'success', 'message' => "La demande de {$demande->enseignant->name} est refusée."]);

        return back();
    }

    /**
     * Seul le directeur de l'école de l'enseignant traite la demande, et seulement si elle est encore en attente.
     */
    private function peutTraiter(Request $request, DemandeIndisponibilite $demande): bool
    {
        abort_unless($demande->enseignant->ecole_id === $request->user()->ecole_id, 403);

        if (! $demande->estEnAttente()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cette demande a déjà été traitée : elle ne peut plus changer.']);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function pourAffichage(DemandeIndisponibilite $d): array
    {
        return [
            'id' => $d->id,
            'jour' => $d->jour->label(),
            'horaire' => $d->horaireLisible(),
            'motif' => $d->motif,
            'dateEnvoi' => $d->date_envoi->format('d/m/Y'),
            'statut' => $d->statut->value,
            'statutLibelle' => $d->statut->label(),
        ];
    }

    /**
     * Les heures possibles pour chaque jour : les débuts et les fins des créneaux de l'école
     * (ceux de la grille des disponibilités ; le samedi, seulement le matin).
     *
     * @return list<array{valeur: int, nom: string, debuts: list<string>, fins: list<string>}>
     */
    private function creneauxParJour(): array
    {
        $creneaux = collect(Disponibilite::creneaux());

        return array_map(fn (Jour $jour) => [
            'valeur' => $jour->value,
            'nom' => $jour->label(),
            'debuts' => array_values($creneaux->where('jour', $jour->value)->pluck('debut')->all()),
            'fins' => array_values($creneaux->where('jour', $jour->value)->pluck('fin')->all()),
        ], Jour::cases());
    }
}
