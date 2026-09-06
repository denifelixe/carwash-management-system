/**
 * The canonical form of a number plate, matching what the server stores.
 *
 * A plate typed as "B 8120 DS" and one typed as "b8120ds" are the same car, so
 * both sides compare the stripped, upper-cased form rather than the keystrokes.
 */
export function normalizePlate(value: string): string {
    return value.replace(/\s+/g, '').toUpperCase();
}

export function isSpecialPlate(value: string): boolean {
    const normalized = normalizePlate(value);

    return (
        normalized !== '' &&
        !/^[A-Z]{1,2}[0-9]{1,4}[A-Z]{0,3}$/.test(normalized)
    );
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

/** The three columns a plate is typed in: "B" — "8120" — "DS". */
export type PlateSegments = {
    prefix: string;
    digits: string;
    suffix: string;
};

/** How long each column may get, in the order they are typed. */
export const plateSegmentLengths: Record<keyof PlateSegments, number> = {
    prefix: 2,
    digits: 4,
    suffix: 3,
};

/**
 * A plate — whole or half-typed — split into the columns of the entry field.
 *
 * The match is greedy per column and never re-orders, so "B8120" fills the
 * first two columns and leaves the third empty, and anything that cannot
 * belong to a column (a stray symbol, a fifth digit) is dropped rather than
 * shifted into the next one.
 */
export function splitPlate(value: string | null | undefined): PlateSegments {
    let rest = typeof value === 'string' ? normalizePlate(value) : '';

    const take = (pattern: RegExp): string => {
        const matched = rest.match(pattern)?.[0] ?? '';
        rest = rest.slice(matched.length);

        return matched;
    };

    return {
        prefix: take(/^[A-Z]{1,2}/),
        digits: take(/^\d{1,4}/),
        suffix: take(/^[A-Z]{1,3}/),
    };
}
