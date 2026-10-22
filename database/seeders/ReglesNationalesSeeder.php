<?php

namespace Database\Seeders;

use App\Models\Contrainte;
use App\Services\GenerateurCreneaux;
use Illuminate\Database\Seeder;

/**
 * T11.4 — Règles nationales de l'emploi du temps, puis génération des créneaux (T11.5).
 * niveau null = règle générale ; niveau rempli = règle du niveau, qui remplace la générale
 * de même type (ex. : « cours_samedi »).
 *
 * Peut être relancé : php artisan db:seed --class=ReglesNationalesSeeder
 */
class ReglesNationalesSeeder extends Seeder
{
    /**
     * [niveau, type, description, valeur]
     *
     * @var array<int, array{int|null, string, string, string}>
     */
    private const REGLES = [
        // --- Règles générales : horaire de la journée (servent à générer les créneaux) ---
        [null, 'journee', 'Du lundi au vendredi, de 8 h à 17 h : 5 créneaux par jour', '08:00-17:00'],
        [null, 'jours', 'Journées complètes du lundi au vendredi', '1,2,3,4,5'],
        [null, 'horaire_samedi', 'Le samedi : matin seulement (créneaux 1 et 2, plus le créneau 3 selon l\'école)', '08:00-13:00'],
        [null, 'recreation', 'Récréation du matin, de 9 h 55 à 10 h 05 (comptée dans les heures de cours)', '09:55-10:05'],
        [null, 'recreation', 'Récréation de l\'après-midi, de 14 h 55 à 15 h 05 (comptée dans les heures de cours)', '14:55-15:05'],
        [null, 'pause_midi', 'De 12 h à 13 h : de préférence la pause déjeuner', '12:00-13:00'],

        // --- Règles générales : organisation des séances ---
        [null, 'plusieurs_matieres_par_seance', 'Une séance de 2 h peut contenir plusieurs matières', 'oui'],
        [null, 'minimum_par_matiere', '30 min minimum par matière dans une séance', '30'],
        [null, 'midi_libere', 'Si une classe a cours de 12 h à 13 h, elle n\'a pas cours de 10 h 05 à 12 h ou de 13 h à 14 h 55', 'oui'],
        [null, 'midi_matieres', 'Seules les matières « autorisées à midi » peuvent être placées de 12 h à 13 h', 'autorisee_midi'],
        [null, 'cours_samedi', 'Cours le samedi matin', 'oui'],

        // --- 1re année ---
        [1, 'total_hebdomadaire', '20 h de cours par semaine', '1200'],
        [1, 'bloc_unique_par_jour', 'Un seul bloc de 4 heures par jour', '240'],
        [1, 'organisation_semaine', '4 matinées et 1 après-midi par semaine', '4 matinées, 1 après-midi'],
        [1, 'cours_samedi', 'Pas de cours le samedi', 'non'],

        // --- 2e année ---
        [2, 'total_hebdomadaire', '22 h de cours par semaine', '1320'],
        [2, 'organisation_semaine', 'Même organisation que la 1re année : 4 matinées et 1 après-midi par semaine', '4 matinées, 1 après-midi'],
        [2, 'matinees_jusqu_a_13h', 'Certaines matinées vont jusqu\'à 13 h pour atteindre 22 h', 'oui'],
        [2, 'cours_samedi', 'Pas de cours le samedi', 'non'],

        // --- 3e année ---
        [3, 'total_hebdomadaire', '25 h de cours par semaine', '1500'],
        [3, 'jours_de_cours', 'Du lundi au vendredi', '1,2,3,4,5'],
        [3, 'cours_samedi', 'Samedi matin seulement si la classe a « cours le samedi »', 'selon_classe'],

        // --- 4e à 6e année (samedi matin : règle générale) ---
        [4, 'total_hebdomadaire', '25 h de cours par semaine', '1500'],
        [4, 'jours_de_cours', 'Du lundi au vendredi, plus le samedi matin', '1,2,3,4,5,6'],
        [5, 'total_hebdomadaire', '28 h de cours par semaine', '1680'],
        [5, 'jours_de_cours', 'Du lundi au vendredi, plus le samedi matin', '1,2,3,4,5,6'],
        [6, 'total_hebdomadaire', '28 h de cours par semaine', '1680'],
        [6, 'jours_de_cours', 'Du lundi au vendredi, plus le samedi matin', '1,2,3,4,5,6'],
    ];

    public function run(): void
    {
        Contrainte::query()->delete();

        foreach (self::REGLES as [$niveau, $type, $description, $valeur]) {
            Contrainte::create(compact('niveau', 'type', 'description', 'valeur'));
        }

        app(GenerateurCreneaux::class)->generer();
    }
}
