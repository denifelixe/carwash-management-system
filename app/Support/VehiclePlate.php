<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * One canonical form for a number plate.
 *
 * A plate is written differently by every hand that types it — "B 8120 DS",
 * "b8120ds", "B  8120  DS" — and each of those would otherwise be its own row.
 * Stripping the whitespace and upper-casing makes the stored value the identity
 * of the car, so a member's vehicle can be recognised whichever way it is typed.
 */
class VehiclePlate
{
    public const FORMAT_PATTERN = '/\A[A-Z]{1,2}[0-9]{1,4}[A-Z]{0,3}\z/';

    public const FORMAT_RULE = 'regex:'.self::FORMAT_PATTERN;

    public const FORMAT_MESSAGE = 'Format plat nomor harus 1–2 huruf wilayah, 1–4 angka, dan maksimal 3 huruf belakang. Contoh: B 1234 CDE.';

    public static function normalize(?string $plate): string
    {
        return Str::upper((string) preg_replace('/\s+/u', '', trim((string) $plate)));
    }

    /**
     * The stored plate rendered for people: "B 8120 DS".
     *
     * The server-side twin of formatPlate() in resources/js/lib/vehiclePlate.ts,
     * for documents rendered without a browser — the CSV export. Every on-screen
     * plate still goes through the JS one; keep the two producing the same
     * string. A plate that does not fit the national pattern is passed through
     * untouched rather than guessed at.
     */
    public static function format(?string $plate): string
    {
        $normalized = self::normalize($plate);

        if (preg_match('/\A([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})\z/', $normalized, $segments) !== 1) {
            return $normalized;
        }

        return implode(' ', array_filter([$segments[1], $segments[2], $segments[3]]));
    }
}
