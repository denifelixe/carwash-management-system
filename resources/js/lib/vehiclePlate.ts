/**
 * The canonical form of a number plate, matching what the server stores.
 *
 * A plate typed as "B 8120 DS" and one typed as "b8120ds" are the same car, so
 * both sides compare the stripped, upper-cased form rather than the keystrokes.
 */
export function normalizePlate(value: string): string {
    return value.replace(/\s+/g, '').toUpperCase();
}

/** A canonical Indonesian plate rendered for people: "B 8120 DS". */
export function formatPlate(value: string | null | undefined): string {
    if (typeof value !== 'string') {
        return '';
    }

    const normalized = normalizePlate(value);
    const segments = normalized.match(/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/);

    if (segments === null) {
        return value;
    }

    return [segments[1], segments[2], segments[3]].filter(Boolean).join(' ');
}
