<?php

namespace Database\Seeders;

use App\Enums\TypeSalle;
use App\Models\Matiere;
use App\Models\NiveauMatiere;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * T08.5 — Programme national : matières et volumes hebdomadaires par niveau.
 *
 * Source : annexe n° 1 du décret n° 2019-1085 (JORT n° 95 du 26 novembre 2019, pages 4042 à 4047),
 * modifié par le décret n° 2021-143. En 5e année, on applique l'observation de l'annexe :
 * à partir de 2020/2021, 1 h d'éveil scientifique passe à l'anglais.
 * La langue arabe est une seule matière (on ne gère pas ses activités : lecture, écriture…).
 *
 * Peut être relancé sans créer de doublons : php artisan db:seed --class=ProgrammeNationalSeeder
 */
class ProgrammeNationalSeeder extends Seeder
{
    /**
     * Tableau « Programme par niveau » : [type de salle, autorisée à midi, minutes par semaine du niveau 1 au niveau 6].
     * null = la matière n'est pas enseignée à ce niveau (pas de ligne dans niveau_matiere).
     *
     * @var array<string, array{TypeSalle, bool, array<int, int|null>}>
     */
    public const PROGRAMME = [
        'Langue arabe' => [TypeSalle::Normale, false, [1 => 540, 2 => 540, 3 => 360, 4 => 360, 5 => 360, 6 => 360]],
        'Mathématiques' => [TypeSalle::Normale, false, [1 => 300, 2 => 300, 3 => 300, 4 => 300, 5 => 300, 6 => 300]],
        'Français' => [TypeSalle::Normale, false, [1 => null, 2 => 120, 3 => 480, 4 => 480, 5 => 480, 6 => 480]],
        'Anglais' => [TypeSalle::Normale, false, [1 => null, 2 => null, 3 => null, 4 => 60, 5 => 60, 6 => 120]],
        'Éveil scientifique' => [TypeSalle::Normale, false, [1 => 60, 2 => 60, 3 => 60, 4 => 60, 5 => 60, 6 => 60]],
        'Éducation islamique' => [TypeSalle::Normale, false, [1 => 60, 2 => 60, 3 => 60, 4 => 60, 5 => 60, 6 => 60]],
        'Éducation technologique' => [TypeSalle::Normale, true, [1 => 60, 2 => 60, 3 => 60, 4 => 60, 5 => 60, 6 => 60]],
        'Éducation musicale' => [TypeSalle::Normale, true, [1 => 60, 2 => 60, 3 => 60, 4 => 30, 5 => 30, 6 => 30]],
        'Éducation artistique' => [TypeSalle::Normale, true, [1 => 60, 2 => 60, 3 => 60, 4 => 30, 5 => 30, 6 => 30]],
        'Éducation physique' => [TypeSalle::Sport, true, [1 => 60, 2 => 60, 3 => 60, 4 => 60, 5 => 60, 6 => 60]],
        'Histoire' => [TypeSalle::Normale, false, [1 => null, 2 => null, 3 => null, 4 => null, 5 => 60, 6 => 40]],
        'Géographie' => [TypeSalle::Normale, false, [1 => null, 2 => null, 3 => null, 4 => null, 5 => 60, 6 => 40]],
        'Éducation civique' => [TypeSalle::Normale, false, [1 => null, 2 => null, 3 => null, 4 => null, 5 => 60, 6 => 40]],
    ];

    /**
     * Dernière ligne du tableau : totaux officiels par semaine, en minutes.
     *
     * @var array<int, int>
     */
    public const TOTAUX_OFFICIELS = [1 => 1200, 2 => 1320, 3 => 1500, 4 => 1500, 5 => 1680, 6 => 1680];

    public function run(): void
    {
        $this->verifierTotaux();

        DB::transaction(function () {
            // Les matières qui ne sont plus dans le programme (ex. : anciennes données d'exemple) sont retirées,
            // avec leurs volumes (suppression en cascade dans niveau_matiere)
            Matiere::whereNotIn('nom', array_keys(self::PROGRAMME))->delete();

            foreach (self::PROGRAMME as $nom => [$typeSalle, $autoriseeMidi, $volumes]) {
                $matiere = Matiere::updateOrCreate(
                    ['nom' => $nom],
                    ['type_salle' => $typeSalle, 'autorisee_midi' => $autoriseeMidi],
                );

                foreach ($volumes as $niveau => $minutes) {
                    if ($minutes === null) {
                        // « — » : pas enseignée à ce niveau
                        NiveauMatiere::where(['niveau' => $niveau, 'matiere_id' => $matiere->id])->delete();

                        continue;
                    }

                    NiveauMatiere::updateOrCreate(
                        ['niveau' => $niveau, 'matiere_id' => $matiere->id],
                        ['minutes_par_semaine' => $minutes],
                    );
                }
            }
        });
    }

    /**
     * Le total de chaque niveau doit être celui de la dernière ligne du tableau.
     */
    private function verifierTotaux(): void
    {
        foreach (self::TOTAUX_OFFICIELS as $niveau => $attendu) {
            $total = array_sum(array_map(fn (array $ligne) => $ligne[2][$niveau] ?? 0, self::PROGRAMME));

            if ($total !== $attendu) {
                throw new RuntimeException("Programme national : le total du niveau {$niveau} est de {$total} min au lieu de {$attendu} min.");
            }
        }
    }
}
