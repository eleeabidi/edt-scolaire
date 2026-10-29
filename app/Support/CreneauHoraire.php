<?php

namespace App\Support;

/**
 * Calculs d'heures dans un créneau. Les minutes « du décret » (la récréation est comptée dans les
 * heures de cours) sont converties en heures réelles : dans un créneau de 2 h, la 1re heure va du
 * début au milieu (8 h → 9 h, 10 h 05 → 11 h) et la 2e du milieu à la fin (9 h → 9 h 55, 11 h → 12 h).
 */
class CreneauHoraire
{
    /**
     * « 09:55 » ou « 09:55:00 » → 595.
     */
    public static function minutes(string $heure): int
    {
        return (int) substr($heure, 0, 2) * 60 + (int) substr($heure, 3, 2);
    }

    /**
     * 595 → « 09:55 ».
     */
    public static function heure(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 780 → « 13 h », 835 → « 13 h 55 ».
     */
    public static function lisible(int $minutes): string
    {
        $reste = $minutes % 60;

        return intdiv($minutes, 60).' h'.($reste === 0 ? '' : sprintf(' %02d', $reste));
    }

    /**
     * Début de la 2e heure d'un créneau de 2 h : l'heure pleine qui suit le début (8 h → 9 h, 10 h 05 → 11 h).
     */
    public static function milieu(int $debut, bool $midi): int
    {
        return $midi ? $debut + 30 : intdiv($debut, 60) * 60 + 60;
    }

    /**
     * Heure réelle d'une position (en minutes « du décret » depuis le début du créneau).
     */
    public static function heureReelle(int $debut, int $fin, bool $midi, int $position): int
    {
        if ($midi) {
            return $debut + $position;
        }

        $milieu = self::milieu($debut, false);

        return $position <= 60
            ? $debut + (int) round($position * ($milieu - $debut) / 60)
            : $milieu + (int) round(($position - 60) * ($fin - $milieu) / 60);
    }

    /**
     * Durée comptée comme dans le décret, arrondie à 10 minutes (9 h → 9 h 55 compte 1 h).
     */
    public static function minutesDecret(string $debut, string $fin): int
    {
        return (int) round((self::minutes($fin) - self::minutes($debut)) / 10) * 10;
    }
}
