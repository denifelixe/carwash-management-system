<?php

namespace App\Support\Carwash;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Aggregated figures for the dashboard (BR-12) and reports module.
 *
 * The prototype has no transaction tables, so the running week is hand-tuned in
 * {@see self::CURATED_DAYS} — relative to today, so the figures move with the
 * calendar — and every other date is synthesised from a hash of the date
 * itself. That keeps a report deterministic: the same range always reports the
 * same figures, so a shared report URL stays stable between visits.
 */
class Reports
{
    /** The shop's timezone, which decides when a business day turns over. */
    public const TIMEZONE = 'Asia/Jakarta';

    /** Days of history a report may reach back over. */
    private const HISTORY_DAYS = 730;

    /** Default span when no range is supplied, in days. */
    private const DEFAULT_DAYS = 7;

    /** Widest range still charted day by day; anything longer rolls up to months. */
    private const DAILY_RANGE_LIMIT = 62;

    /** Hand-tuned figures for the running week, keyed by days back from today. */
    private const CURATED_DAYS = [
        6 => ['revenue' => 3250000, 'transactions' => 27, 'expense' => 1340000],
        5 => ['revenue' => 2980000, 'transactions' => 24, 'expense' => 1210000],
        4 => ['revenue' => 3760000, 'transactions' => 31, 'expense' => 1520000],
        3 => ['revenue' => 4120000, 'transactions' => 33, 'expense' => 1680000],
        2 => ['revenue' => 5240000, 'transactions' => 41, 'expense' => 2100000],
        1 => ['revenue' => 6480000, 'transactions' => 52, 'expense' => 2610000],
        0 => ['revenue' => 4850000, 'transactions' => 38, 'expense' => 1940000],
    ];

    /** @var array<int, string> */
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * @return list<array{label: string, value: string, caption: string, delta: float, trend: string, icon: string}>
     */
    public static function todayStats(): array
    {
        return self::periodStats(self::todayDate(), self::todayDate());
    }

    /**
     * Headline figures for the dashboard's selected business day. Revenue is
     * collected POS money, while vehicle counts follow the order board.
     *
     * @return list<array{label: string, value: string, caption: string, delta: float, trend: string, icon: string}>
     */
    public static function dashboardStats(string $date): array
    {
        $stats = self::periodStats($date, $date);
        $posPayments = array_values(array_filter(
            DateFilter::apply(Finance::moneyIn(), $date),
            fn (array $entry): bool => $entry['source'] === 'pos',
        ));
        $orderSummary = Operations::orderSummary($date);
        $isToday = $date === self::todayDate();

        $stats[0] = [
            ...$stats[0],
            'label' => $isToday ? 'Pendapatan Hari Ini' : 'Pendapatan',
            'value' => 'Rp '.number_format(array_sum(array_column($posPayments, 'amount')), 0, ',', '.'),
            'caption' => 'dari '.number_format(count($posPayments), 0, ',', '.').' transaksi POS',
        ];
        $stats[1] = [
            ...$stats[1],
            'value' => number_format($orderSummary['served'], 0, ',', '.'),
            'caption' => 'dari '.number_format($orderSummary['total'], 0, ',', '.').' order kendaraan',
        ];

        return $stats;
    }

    /**
     * The four headline numbers for a range. Money and cars are flows, so they
     * add up over the days picked; the loyalty totals describe the customer
     * base as a whole and stay put whatever the range.
     *
     * @return list<array{label: string, value: string, caption: string, delta: float, trend: string, icon: string}>
     */
    public static function periodStats(string $from, string $to): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
        $isToday = $from === self::todayDate() && $to === self::todayDate();

        $revenue = 0;
        $transactions = 0;

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $figures = self::dayFigures($date);
            $revenue += $figures['revenue'];
            $transactions += $figures['transactions'];
        }

        return [
            ['label' => $isToday ? 'Pendapatan Hari Ini' : 'Pendapatan', 'value' => 'Rp '.number_format($revenue, 0, ',', '.'), 'caption' => 'dari '.number_format($transactions, 0, ',', '.').' transaksi', 'delta' => 12.4, 'trend' => 'up', 'icon' => 'wallet'],
            ['label' => 'Kendaraan Dilayani', 'value' => number_format($transactions, 0, ',', '.'), 'caption' => $isToday ? '6 unit masih antre' : 'sepanjang '.self::rangeDays($start, $end).' hari', 'delta' => 8.6, 'trend' => 'up', 'icon' => 'car'],
            ['label' => 'Customer Aktif', 'value' => '1.284', 'caption' => '+24 member bulan ini', 'delta' => 3.1, 'trend' => 'up', 'icon' => 'users'],
            ['label' => 'Stempel Ditukar', 'value' => '142', 'caption' => '19 reward diklaim', 'delta' => -2.8, 'trend' => 'down', 'icon' => 'gift'],
        ];
    }

    /** The date the whole prototype treats as today. */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    /** Today as an ISO date, the shape every module filters by. */
    public static function todayDate(): string
    {
        return self::today()->toDateString();
    }

    /**
     * Whole days between a date and today, counting backwards: 0 is today, 1 is
     * yesterday. Both sides are read as bare dates, so a timezone can never
     * shift the answer by a day.
     */
    public static function daysBack(CarbonImmutable $date): int
    {
        $today = CarbonImmutable::createFromFormat('!Y-m-d', self::todayDate());
        $then = CarbonImmutable::createFromFormat('!Y-m-d', $date->toDateString());

        return intdiv($today->getTimestamp() - $then->getTimestamp(), 86400);
    }

    /**
     * Daily revenue vs expense for the running week, used by the dashboard chart.
     *
     * @return list<array{day: string, date: string, revenue: int, transactions: int, expense: int}>
     */
    public static function revenueTrend(): array
    {
        $points = [];

        foreach (self::CURATED_DAYS as $back => $figures) {
            $date = self::today()->subDays($back);

            $points[] = [
                'day' => $date->locale('id')->isoFormat('ddd'),
                'date' => self::shortDate($date),
                'revenue' => $figures['revenue'],
                'transactions' => $figures['transactions'],
                'expense' => $figures['expense'],
            ];
        }

        return $points;
    }

    /**
     * Turn the raw query string into a usable range, falling back to the last
     * week. Filters arrive on a GET, so bad input is clamped rather than
     * rejected — a report URL should always render something.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function resolveRange(?string $from, ?string $to): array
    {
        $today = self::today();
        $earliest = $today->subDays(self::HISTORY_DAYS);

        $start = self::parseDate($from);
        $end = self::parseDate($to);

        // A single open end anchors the other side to the default span.
        $start ??= $end?->subDays(self::DEFAULT_DAYS - 1) ?? $today->subDays(self::DEFAULT_DAYS - 1);
        $end ??= $start->addDays(self::DEFAULT_DAYS - 1);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $start = $start->lessThan($earliest) ? $earliest : $start;
        $end = $end->greaterThan($today) ? $today : $end;

        return [
            'from' => $start->greaterThan($end) ? $end : $start,
            'to' => $end,
        ];
    }

    /**
     * Everything the filter bar needs to describe and re-select the range.
     *
     * @return array{from: string, to: string, label: string, granularity: string, days: int, today: string, earliest: string}
     */
    public static function rangeMeta(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = self::rangeDays($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'label' => self::rangeLabel($from, $to),
            'granularity' => $days <= self::DAILY_RANGE_LIMIT ? 'harian' : 'bulanan',
            'days' => $days,
            'today' => self::todayDate(),
            'earliest' => self::today()->subDays(self::HISTORY_DAYS)->toDateString(),
        ];
    }

    /** Oldest day any filter may reach back to. */
    public static function earliest(): string
    {
        return self::today()->subDays(self::HISTORY_DAYS)->toDateString();
    }

    /** Whole days covered by the range, both ends inclusive. */
    public static function rangeDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->diffInDays($to) + 1;
    }

    /**
     * How much larger the range is than the curated week. Count-style figures
     * elsewhere on the report are stretched by this so a six-month range does
     * not sit next to a single week's booking total.
     */
    public static function rangeScale(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return self::rangeDays($from, $to) / count(self::CURATED_DAYS);
    }

    /**
     * Revenue vs expense across the range, by day for short ranges and rolled
     * up by month once the range would produce too many bars to read.
     *
     * @return list<array{label: string, caption: string, revenue: int, expense: int, transactions: int}>
     */
    public static function trend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $days[] = ['date' => $date, ...self::dayFigures($date)];
        }

        if (count($days) <= self::DAILY_RANGE_LIMIT) {
            return array_map(static fn (array $day): array => [
                'label' => self::shortDate($day['date']),
                'caption' => self::longDate($day['date']),
                'revenue' => $day['revenue'],
                'expense' => $day['expense'],
                'transactions' => $day['transactions'],
            ], $days);
        }

        $months = [];

        foreach ($days as $day) {
            $key = $day['date']->format('Y-m');

            $months[$key] ??= [
                'label' => self::MONTH_LABELS[(int) $day['date']->format('n')].' '.$day['date']->format('y'),
                'caption' => self::MONTH_LABELS[(int) $day['date']->format('n')].' '.$day['date']->format('Y'),
                'revenue' => 0,
                'expense' => 0,
                'transactions' => 0,
            ];

            $months[$key]['revenue'] += $day['revenue'];
            $months[$key]['expense'] += $day['expense'];
            $months[$key]['transactions'] += $day['transactions'];
        }

        return array_values($months);
    }

    /**
     * @return list<array{name: string, orders: int, revenue: int}>
     */
    public static function topServices(float $scale = 1.0): array
    {
        $services = [
            ['name' => 'Cuci Mobil Reguler', 'orders' => 412, 'revenue' => 18540000],
            ['name' => 'Snow Wash Premium', 'orders' => 236, 'revenue' => 28320000],
            ['name' => 'Cuci Mobil + Wax', 'orders' => 198, 'revenue' => 16830000],
            ['name' => 'Deep Clean Interior', 'orders' => 124, 'revenue' => 18600000],
            ['name' => 'Cuci Motor Reguler', 'orders' => 96, 'revenue' => 1920000],
        ];

        return array_map(static fn (array $service): array => [
            'name' => $service['name'],
            'orders' => self::scaleFlow($service['orders'], $scale),
            'revenue' => self::scaleFlow($service['revenue'], $scale),
        ], $services);
    }

    /**
     * Customer activity rollup for the reports module. Flow figures grow with
     * the range; distinct-people and current-state figures do not scale
     * linearly, so they are damped or left alone.
     *
     * @return array{newCustomers: int, returningCustomers: int, churnRisk: int, stampsIssued: int, stampsRedeemed: int, rewardsClaimed: int, averageVisitsPerCustomer: float}
     */
    public static function customerActivity(float $scale = 1.0): array
    {
        return [
            'newCustomers' => self::scaleFlow(24, $scale),
            'returningCustomers' => self::scaleDistinct(186, $scale),
            'churnRisk' => 12,
            'stampsIssued' => self::scaleFlow(418, $scale),
            'stampsRedeemed' => self::scaleFlow(142, $scale),
            'rewardsClaimed' => self::scaleFlow(19, $scale),
            'averageVisitsPerCustomer' => 3.4,
        ];
    }

    /**
     * @return array{total: int, scheduled: int, completed: int, cancelled: int, showRate: float, peakSlot: string}
     */
    public static function bookingSummary(float $scale = 1.0): array
    {
        return [
            'total' => self::scaleFlow(64, $scale),
            'scheduled' => self::scaleFlow(21, $scale),
            'completed' => self::scaleFlow(38, $scale),
            'cancelled' => self::scaleFlow(5, $scale),
            'showRate' => 92.2,
            'peakSlot' => '09.00 - 11.00',
        ];
    }

    /**
     * @return array{totalItems: int, lowStock: int, stockValue: int, movementsThisWeek: int, topConsumed: string}
     */
    public static function inventorySummary(): array
    {
        return [
            'totalItems' => 12,
            'lowStock' => 3,
            'stockValue' => 14_286_000,
            'movementsThisWeek' => 28,
            'topConsumed' => 'Snow Foam pH Netral',
        ];
    }

    /**
     * Revenue, expense and transactions for a single date. Curated where the
     * prototype has hand-tuned figures, hashed from the date otherwise.
     *
     * @return array{revenue: int, expense: int, transactions: int}
     */
    private static function dayFigures(CarbonImmutable $date): array
    {
        $back = self::daysBack($date);

        if (isset(self::CURATED_DAYS[$back])) {
            return self::CURATED_DAYS[$back];
        }

        $seed = crc32($date->toDateString());
        $wobble = ($seed % 1000) / 1000;
        $weekendLift = $date->isWeekend() ? 1.45 : 1.0;

        $revenue = self::toNearest(3_150_000 * $weekendLift * (0.85 + 0.3 * $wobble));
        $expenseRatio = 0.39 + 0.05 * ((intdiv($seed, 1000) % 100) / 100);

        return [
            'revenue' => $revenue,
            'expense' => self::toNearest($revenue * $expenseRatio),
            'transactions' => (int) max(1, round($revenue / 126_000)),
        ];
    }

    /** Figures that accumulate over time, so they track the range directly. */
    private static function scaleFlow(int $base, float $scale): int
    {
        return (int) max(1, round($base * $scale));
    }

    /** Head counts saturate as the range widens rather than growing linearly. */
    private static function scaleDistinct(int $base, float $scale): int
    {
        return (int) max(1, round($base * $scale ** 0.65));
    }

    private static function toNearest(float $value, int $step = 10_000): int
    {
        return (int) (round($value / $step) * $step);
    }

    /** "3 Agu" — compact enough for a chart axis. */
    private static function shortDate(CarbonImmutable $date): string
    {
        return $date->format('j').' '.self::MONTH_LABELS[(int) $date->format('n')];
    }

    /** "3 Agu 2026" — used where the year matters, such as tooltips. */
    private static function longDate(CarbonImmutable $date): string
    {
        return self::shortDate($date).' '.$date->format('Y');
    }

    private static function rangeLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->isSameDay($to)) {
            return self::longDate($from);
        }

        $start = $from->format('Y') === $to->format('Y')
            ? self::shortDate($from)
            : self::longDate($from);

        return $start.' – '.self::longDate($to);
    }

    private static function parseDate(?string $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }

        // Rejects rolled-over dates such as 2026-02-31.
        return $date->toDateString() === $value ? $date : null;
    }
}
