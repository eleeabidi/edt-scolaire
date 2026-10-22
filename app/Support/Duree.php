<?php

namespace App\Support;

/**
 * Affichage des durées comme dans le décret : 390 → « 6 h 30 », 1140 → « 19 h », 30 → « 30 min ».
 */
class Duree
{
    public static function format(int $minutes): string
    {
        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        if ($heures === 0) {
            return "{$reste} min";
        }

        return $reste === 0 ? "{$heures} h" : sprintf('%d h %02d', $heures, $reste);
    }
}
