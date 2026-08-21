<?php

namespace App\Support\Carwash;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;

/**
 * The single-day filter the operational modules share (BR-12).
 *
 * Rows carry an ISO `date` and the filter picks one of those days. An empty
 * pick is no filter at all, so a module opens on its full list and narrows only
 * once someone chooses a date. Ranges are the reporting module's job.
 */
class DateFilter
{
    /** Indonesian short month names, indexed the way a date string numbers them. */
    private const MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Days ahead a schedule may be picked from. */
    private const FUTURE_DAYS = 365;

    /** The picked day, or '' when the module should show everything. */
    public static function fromRequest(Request $request): string
    {
        return self::resolve($request->query('date'));
    }

    /**
     * Clamp raw query input into a usable day. Filters arrive on a GET, so
     * unusable input is dropped rather than rejected — a filtered URL should
     * always render something.
     */
    public static function resolve(mixed $date): string
    {
        if (! is_string($date) || $date === '') {
            return '';
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $date)->toDateString();
        } catch (InvalidFormatException) {
            return '';
        }
    }

    /**
     * Everything the filter needs to describe and re-select the day.
     *
     * @return array{date: string, today: string, earliest: string, latest: string, label: string}
     */
    public static function meta(string $date): array
    {
        return [
            'date' => $date,
            'today' => Reports::todayDate(),
            'earliest' => Reports::earliest(),
            'latest' => Reports::today()->addDays(self::FUTURE_DAYS)->toDateString(),
            'label' => self::label($date),
        ];
    }

    /**
     * @template TRow of array<string, mixed>
     *
     * @param  list<TRow>  $rows
     * @return list<TRow>
     */
    public static function apply(array $rows, string $date, string $key = 'date'): array
    {
        if ($date === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => (string) $row[$key] === $date,
        ));
    }

    /** "2026-08-05" the way the modules spell a date: "5 Agu 2026". */
    public static function format(string $date): string
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return $day.' '.self::MONTHS[$month].' '.$year;
    }

    /** How the filter names the day it is showing. */
    public static function label(string $date): string
    {
        if ($date === '') {
            return 'Semua tanggal';
        }

        return $date === Reports::todayDate() ? 'Hari ini' : self::format($date);
    }
}
