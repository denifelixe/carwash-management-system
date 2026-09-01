<?php

namespace App\Support\Admin;

use App\Support\Demo\DateFilter;

/**
 * The address a printed shift recap points back at.
 *
 * A recap is not a public document the way a receipt is — there is no signed
 * guest page behind it, and its figures are the outlet's takings. So the link
 * on a printed sheet is a deep link into the console instead: whoever scans it
 * lands on the same day and shift, once they are logged in.
 *
 * The URL is only ever built here, from a page key and the two filters that
 * choose a recap, so the QR endpoint can never be talked into pointing
 * somewhere else.
 */
class RecapLink
{
    /** The console pages a recap can be taken from. */
    public const PAGES = [
        'finance' => 'admin.finance.index',
        'pos' => 'admin.pos.index',
    ];

    public static function isPage(?string $page): bool
    {
        return $page !== null && array_key_exists($page, self::PAGES);
    }

    /**
     * The shift tab, as the page that owns it spells it. The two consoles do
     * not share an alphabet — Finance keys its tabs by work shift id, the POS
     * by the master list's slug — so this only bounds the shape rather than
     * checking it against a list: enough that nothing but a tab key can be
     * smuggled into the address the QR encodes.
     */
    public static function shift(mixed $shift): ?string
    {
        if (! is_string($shift) || ! preg_match('/^[a-z0-9-]{1,64}$/', $shift)) {
            return null;
        }

        return $shift;
    }

    public static function url(string $page, mixed $date, mixed $shift): string
    {
        $parameters = [];
        $resolvedDate = DateFilter::resolve($date);

        if ($resolvedDate !== '') {
            $parameters['date'] = $resolvedDate;
        }

        $resolvedShift = self::shift($shift);

        if ($resolvedShift !== null) {
            $parameters['shift'] = $resolvedShift;
        }

        return route(self::PAGES[$page], $parameters);
    }
}
