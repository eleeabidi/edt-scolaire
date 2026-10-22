/**
 * Durée en minutes → texte en heures, comme dans le décret.
 * Ex. : 390 → « 6 h 30 », 540 → « 9 h », 30 → « 30 min ».
 */
export function formatDuree(minutes: number): string {
    const heures = Math.floor(minutes / 60);
    const reste = minutes % 60;

    if (heures === 0) {
        return `${reste} min`;
    }

    return reste === 0
        ? `${heures} h`
        : `${heures} h ${String(reste).padStart(2, '0')}`;
}

/**
 * Heure « HH:MM » → texte. Ex. : « 09:55 » → « 9 h 55 », « 08:00 » → « 8 h ».
 */
export function formatHeure(heure: string): string {
    const [h, m] = heure.split(':').map(Number);

    return m === 0 ? `${h} h` : `${h} h ${String(m).padStart(2, '0')}`;
}

/**
 * Niveau 1 à 6 → « 1re année », « 2e année »…
 */
export function libelleNiveau(niveau: number): string {
    return niveau === 1 ? '1re année' : `${niveau}e année`;
}
