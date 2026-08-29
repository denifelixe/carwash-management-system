<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The zones an Indonesian outlet may run on. This is the only place a zone
 * identifier is written down: everywhere else asks AppSettings which one is
 * configured.
 */
class Timezones
{
    /** @var array<string, array{code: string, name: string, offset: string, cities: string}> */
    public const ZONES = [
        'Asia/Jakarta' => [
            'code' => 'WIB',
            'name' => 'Waktu Indonesia Barat',
            'offset' => 'GMT+7',
            'cities' => 'Jakarta, Bandung, Medan, Bogor, Palembang',
        ],
        'Asia/Makassar' => [
            'code' => 'WITA',
            'name' => 'Waktu Indonesia Tengah',
            'offset' => 'GMT+8',
            'cities' => 'Makassar, Denpasar, Balikpapan, Manado',
        ],
        'Asia/Jayapura' => [
            'code' => 'WIT',
            'name' => 'Waktu Indonesia Timur',
            'offset' => 'GMT+9',
            'cities' => 'Jayapura, Ambon, Ternate, Sorong',
        ],
    ];

    public const FALLBACK = 'Asia/Jakarta';

    /**
     * Every zone as the console shows it, each carrying the clock it is on
     * right now so the owner can recognise their own time before choosing.
     *
     * @return list<array{id: string, code: string, name: string, offset: string, cities: string, clock: string}>
     */
    public static function options(): array
    {
        $now = CarbonImmutable::now();

        return array_map(
            fn (string $id): array => [
                'id' => $id,
                ...self::ZONES[$id],
                'clock' => $now->timezone($id)->format('H.i'),
            ],
            array_keys(self::ZONES),
        );
    }

    public static function has(string $timezone): bool
    {
        return array_key_exists($timezone, self::ZONES);
    }

    /** The short Indonesian code — WIB, WITA, WIT — for a zone. */
    public static function code(string $timezone): string
    {
        return self::ZONES[$timezone]['code'] ?? $timezone;
    }
}
