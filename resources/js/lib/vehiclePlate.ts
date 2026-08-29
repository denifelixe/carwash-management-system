/**
 * The canonical form of a number plate, matching what the server stores.
 *
 * A plate typed as "B 8120 DS" and one typed as "b8120ds" are the same car, so
 * both sides compare the stripped, upper-cased form rather than the keystrokes.
 */
export function normalizePlate(value: string): string {
    return value.replace(/\s+/g, '').toUpperCase();
}
