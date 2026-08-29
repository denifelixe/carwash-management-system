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
    public static function normalize(?string $plate): string
    {
        return Str::upper((string) preg_replace('/\s+/u', '', trim((string) $plate)));
    }
}
