<?php

namespace Database\Seeders;

use App\Enums\TypeSalle;
use App\Models\Matiere;
use App\Models\NiveauMatiere;
use Illuminate\Database\Seeder;

/**
 * T08.5 — Programme national : matières et volumes hebdomadaires par niveau
 * (décret n° 2019-1085, modifié par le décret n° 2021-143).
 *
 * ⚠️ DONNÉES PROVISOIRES : les vrais volumes du décret seront fournis plus tard.
 * Ces valeurs d'exemple respectent seulement les totaux officiels par semaine :
 * 1re 20 h, 2e 22 h, 3e 25 h, 4e 25 h, 5e 28 h, 6e 28 h.
 *
 * Peut être relancé sans créer de doublons : php artisan db:seed --class=ProgrammeNationalSeeder
 */
class ProgrammeNationalSeeder extends Seeder
{
    /**
     * Matières : [type de salle, autorisée de 12 h à 13 h].
     *
     * @var array<string, array{TypeSalle, bool}>
     */
    private const MATIERES = [
        'Langue arabe' => [TypeSalle::Normale, false],
        'Langue française' => [TypeSalle::Normale, false],
        'Langue anglaise' => [TypeSalle::Normale, false],
        'Mathématiques' => [TypeSalle::Normale, false],
        'Éveil scientifique' => [TypeSalle::Normale, false],
        'Éducation islamique' => [TypeSalle::Normale, false],
        'Éducation civique' => [TypeSalle::Normale, false],
        'Histoire-géographie' => [TypeSalle::Normale, false],
        'Informatique' => [TypeSalle::Normale, true],
        'Éducation musicale' => [TypeSalle::Normale, true],
        'Éducation artistique' => [TypeSalle::Normale, true],
        'Éducation physique' => [TypeSalle::Sport, true],
    ];

    /**
     * ⚠️ PROVISOIRE — volumes en minutes par semaine, niveau par niveau.
     *
     * @var array<int, array<string, int>>
     */
    public const VOLUMES_PROVISOIRES = [
        1 => [ // total 1 200 min = 20 h (maquette 19)
            'Langue arabe' => 540, 'Mathématiques' => 300, 'Éveil scientifique' => 120,
            'Éducation islamique' => 60, 'Éducation artistique' => 60, 'Éducation physique' => 120,
        ],
        2 => [ // total 1 320 min = 22 h
            'Langue arabe' => 480, 'Langue française' => 180, 'Mathématiques' => 300,
            'Éveil scientifique' => 120, 'Éducation islamique' => 60, 'Éducation artistique' => 60,
            'Éducation physique' => 120,
        ],
        3 => [ // total 1 500 min = 25 h
            'Langue arabe' => 420, 'Langue française' => 270, 'Mathématiques' => 300,
            'Éveil scientifique' => 150, 'Éducation islamique' => 60, 'Éducation civique' => 60,
            'Éducation musicale' => 60, 'Éducation artistique' => 60, 'Éducation physique' => 120,
        ],
        4 => [ // total 1 500 min = 25 h
            'Langue arabe' => 390, 'Langue française' => 300, 'Mathématiques' => 300,
            'Éveil scientifique' => 150, 'Éducation islamique' => 60, 'Éducation civique' => 60,
            'Informatique' => 60, 'Éducation artistique' => 60, 'Éducation physique' => 120,
        ],
        5 => [ // total 1 680 min = 28 h
            'Langue arabe' => 390, 'Langue française' => 330, 'Langue anglaise' => 90,
            'Mathématiques' => 300, 'Éveil scientifique' => 180, 'Éducation islamique' => 60,
            'Histoire-géographie' => 90, 'Éducation civique' => 30, 'Informatique' => 60,
            'Éducation musicale' => 30, 'Éducation physique' => 120,
        ],
        6 => [ // total 1 680 min = 28 h
            'Langue arabe' => 390, 'Langue française' => 330, 'Langue anglaise' => 90,
            'Mathématiques' => 300, 'Éveil scientifique' => 180, 'Éducation islamique' => 60,
            'Histoire-géographie' => 90, 'Éducation civique' => 30, 'Informatique' => 60,
            'Éducation musicale' => 30, 'Éducation physique' => 120,
        ],
    ];

    /**
     * Totaux officiels par semaine, en minutes.
     *
     * @var array<int, int>
     */
    public const TOTAUX_OFFICIELS = [1 => 1200, 2 => 1320, 3 => 1500, 4 => 1500, 5 => 1680, 6 => 1680];

    public function run(): void
    {
        foreach (self::MATIERES as $nom => [$typeSalle, $autoriseeMidi]) {
            Matiere::updateOrCreate(
                ['nom' => $nom],
                ['type_salle' => $typeSalle, 'autorisee_midi' => $autoriseeMidi],
            );
        }

        $idsMatieres = Matiere::query()->pluck('id', 'nom');

        foreach (self::VOLUMES_PROVISOIRES as $niveau => $volumes) {
            foreach ($volumes as $nom => $minutes) {
                NiveauMatiere::updateOrCreate(
                    ['niveau' => $niveau, 'matiere_id' => $idsMatieres[$nom]],
                    ['minutes_par_semaine' => $minutes],
                );
            }
        }
    }
}
