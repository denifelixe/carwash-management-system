<?php

namespace App\Support\Demo;

use App\Support\Admin\FinanceReportQueries;
use App\Support\Admin\Paginated;
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
    /** @return list<array<string, mixed>> */
    public static function financeLogRows(CarbonImmutable $from, CarbonImmutable $to, string $direction = 'all'): array
    {
        $direction = FinanceReportQueries::direction($direction);

        return array_values(collect(Finance::moneyIn())
            ->map(fn (array $row): array => [...$row, 'direction' => 'in'])
            ->concat(collect(Finance::moneyOut())->map(fn (array $row): array => [...$row, 'source' => 'manual', 'direction' => 'out']))
            ->filter(fn (array $row): bool => $row['date'] >= $from->toDateString()
                && $row['date'] <= $to->toDateString()
                && ($direction === 'all' || $row['direction'] === $direction))
            ->sortByDesc(fn (array $row): string => $row['date'].' '.$row['time'].' '.$row['ref'])
            ->all());
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, int|null>} */
    public static function financeLog(CarbonImmutable $from, CarbonImmutable $to, string $direction = 'all', int $page = 1): array
    {
        return Paginated::fromArray(self::financeLogRows($from, $to, $direction), $page, FinanceReportQueries::PER_PAGE);
    }

    /** @return array{moneyIn: int, moneyOut: int, net: int, transactions: int} */
    public static function financeSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = collect(self::financeLogRows($from, $to));
        $moneyIn = (int) $rows->where('direction', 'in')->sum('amount');
        $moneyOut = (int) $rows->where('direction', 'out')->sum('amount');

        return ['moneyIn' => $moneyIn, 'moneyOut' => $moneyOut, 'net' => $moneyIn - $moneyOut, 'transactions' => $rows->count()];
    }

    /** Days of history a report may reach back over. */
    private const HISTORY_DAYS = 730;

    /** Default span when no range is supplied, in days. */
    private const DEFAULT_DAYS = 7;

    /** Widest range still charted day by day; anything longer rolls up to months. */
    private const DAILY_RANGE_LIMIT = 62;

    /** Longest range the report accepts (MoM follow-up: at most 95 days). */
    public const MAX_RANGE_DAYS = 95;

    /** Rows per page in the order log, matching the live reader. */
    private const ORDERS_PER_PAGE = 25;

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
     * Headline figures for the dashboard's selected business day. Revenue and
     * cash follow the finance ledger, vehicles follow the order board, and
     * loyalty figures follow the member module.
     *
     * @return list<array{label: string, value: string, caption: string, delta: float|null, trend: string, icon: string}>
     */
    public static function dashboardStats(string $date): array
    {
        $stats = self::periodStats($date, $date);
        $income = DateFilter::apply(Finance::moneyIn(), $date);
        $orderSummary = Operations::orderSummary($date);
        $memberSummary = Customers::summary();
        $isToday = $date === self::todayDate();
        $previousDate = CarbonImmutable::createFromFormat('!Y-m-d', $date)
            ->subDay()
            ->toDateString();
        $previousIncome = DateFilter::apply(Finance::moneyIn(), $previousDate);
        $previousOrderSummary = Operations::orderSummary($previousDate);
        $revenue = array_sum(array_column($income, 'amount'));

        $stats[0] = [
            ...$stats[0],
            ...self::dailyComparison(
                $revenue,
                array_sum(array_column($previousIncome, 'amount')),
            ),
            'label' => $isToday ? 'Pendapatan Hari Ini' : 'Pendapatan',
            'value' => 'Rp '.number_format($revenue, 0, ',', '.'),
            'caption' => 'dari '.number_format(count($income), 0, ',', '.').' transaksi keuangan',
        ];
        $stats[1] = [
            ...$stats[1],
            ...self::dailyComparison(
                $orderSummary['served'],
                $previousOrderSummary['served'],
            ),
            'value' => number_format($orderSummary['served'], 0, ',', '.'),
            'caption' => 'dari '.number_format($orderSummary['total'], 0, ',', '.').' order kendaraan',
        ];
        $stats[2] = [
            ...$stats[2],
            ...self::dailyComparison($memberSummary['active'], $memberSummary['active']),
            'label' => 'Member Aktif',
            'value' => number_format($memberSummary['active'], 0, ',', '.'),
            'caption' => 'dari '.number_format($memberSummary['total'], 0, ',', '.').' member terdaftar',
        ];
        $stats[3] = [
            ...$stats[3],
            ...self::dailyComparison($memberSummary['redeemedStamps'], $memberSummary['redeemedStamps']),
            'value' => number_format($memberSummary['redeemedStamps'], 0, ',', '.'),
            'caption' => number_format($memberSummary['rewardsClaimed'], 0, ',', '.').' reward diklaim',
        ];

        return $stats;
    }

    /**
     * @return array{delta: float|null, trend: string}
     */
    private static function dailyComparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'delta' => $current === 0 ? 0.0 : null,
                'trend' => 'flat',
            ];
        }

        $delta = round((($current - $previous) / $previous) * 100, 1);

        return [
            'delta' => $delta,
            'trend' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
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
        return CarbonImmutable::now()->startOfDay();
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

        /* A report never spans more than MAX_RANGE_DAYS; the start gives way. */
        if ($start->lessThan($end->subDays(self::MAX_RANGE_DAYS - 1))) {
            $start = $end->subDays(self::MAX_RANGE_DAYS - 1);
        }

        return [
            'from' => $start->greaterThan($end) ? $end : $start,
            'to' => $end,
        ];
    }

    /**
     * Everything the filter bar needs to describe and re-select the range.
     *
     * @return array{from: string, to: string, label: string, granularity: string, days: int, today: string, earliest: string, maxDays: int}
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
            'maxDays' => self::MAX_RANGE_DAYS,
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
     * The demo's Laporan Penjualan Harian: the same day figures trend() charts,
     * split across the payment methods in a fixed mix. The last method takes
     * the rounding, so every row still foots to its total like the live one.
     *
     * @return array{methods: list<string>, rows: list<array{date: string, transactions: int, total: int, methods: array<string, int>}>, total: array{transactions: int, total: int, methods: array<string, int>}}
     */
    public static function dailySales(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shares = ['Tunai' => 0.22, 'QRIS' => 0.28, 'Kredit' => 0.05, 'Debit' => 0.3, 'Transfer' => 0.12, 'E-Money' => 0.03];
        $methods = array_keys($shares);
        $rows = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $figures = self::dayFigures($date);
            $split = [];

            foreach ($shares as $method => $share) {
                $split[$method] = self::toNearest($figures['revenue'] * $share);
            }

            $split['E-Money'] = $figures['revenue'] - array_sum(array_slice($split, 0, -1));

            $rows[] = [
                'date' => $date->toDateString(),
                'transactions' => $figures['transactions'],
                'total' => $figures['revenue'],
                'methods' => $split,
            ];
        }

        return [
            'methods' => $methods,
            'rows' => $rows,
            'total' => [
                'transactions' => array_sum(array_column($rows, 'transactions')),
                'total' => array_sum(array_column($rows, 'total')),
                'methods' => array_combine($methods, array_map(
                    static fn (string $method): int => array_sum(array_map(static fn (array $row): int => $row['methods'][$method], $rows)),
                    $methods,
                )),
            ],
        ];
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
     * The demo's Penjualan per Layanan: the catalog under its category groups,
     * each service sold a fixed number of times per 30 days at its lowest
     * price, scaled to the range like the other flow figures.
     *
     * @return array{groups: list<array{group: string, items: list<array{name: string, category: string, quantity: int, total: int}>, quantity: int, total: int}>, quantity: int, total: int}
     */
    public static function itemSales(float $scale = 1.0): array
    {
        $monthlyQuantities = [1 => 294, 2 => 74, 3 => 56, 4 => 25, 5 => 22, 6 => 10, 7 => 4, 8 => 9, 9 => 18, 10 => 6, 11 => 20, 12 => 31, 13 => 5];
        $groups = [];

        foreach (Catalog::services() as $service) {
            $quantity = self::scaleFlow($monthlyQuantities[$service['id']] ?? 3, $scale);
            $price = min(array_column($service['serviceVariations'], 'price'));
            $group = $service['categoryGroup'];

            $groups[$group] ??= ['group' => $group, 'items' => [], 'quantity' => 0, 'total' => 0];
            $groups[$group]['items'][] = [
                'name' => $service['name'],
                'category' => $service['category'],
                'quantity' => $quantity,
                'total' => $quantity * $price,
            ];
            $groups[$group]['quantity'] += $quantity;
            $groups[$group]['total'] += $quantity * $price;
        }

        $groups = array_values(array_map(static function (array $group): array {
            usort($group['items'], static fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']) ?: strcmp($first['name'], $second['name']));

            return $group;
        }, $groups));

        return [
            'groups' => $groups,
            'quantity' => array_sum(array_column($groups, 'quantity')),
            'total' => array_sum(array_column($groups, 'total')),
        ];
    }

    /**
     * How the customer base moved over the range. Flow figures grow with the
     * range; distinct-people and current-state figures do not scale linearly,
     * so they are damped or left alone.
     *
     * @return array{newMembers: int, returningMembers: int, membersServed: int, newLeads: int, convertedLeads: int, openLeads: int, churnRisk: int, averageVisitsPerMember: float}
     */
    public static function customerBase(float $scale = 1.0): array
    {
        return [
            'newMembers' => self::scaleFlow(24, $scale),
            'returningMembers' => self::scaleDistinct(186, $scale),
            'membersServed' => self::scaleDistinct(214, $scale),
            'newLeads' => self::scaleFlow(38, $scale),
            'convertedLeads' => self::scaleFlow(11, $scale),
            'openLeads' => 42,
            'churnRisk' => 12,
            'averageVisitsPerMember' => 3.4,
        ];
    }

    /**
     * @return array{total: int, scheduled: int, completed: int, cancelled: int, showRate: float}
     */
    public static function bookingSummary(float $scale = 1.0): array
    {
        return [
            'total' => self::scaleFlow(64, $scale),
            'scheduled' => self::scaleFlow(21, $scale),
            'completed' => self::scaleFlow(38, $scale),
            'cancelled' => self::scaleFlow(5, $scale),
            'showRate' => 92.2,
        ];
    }

    /**
     * The orders behind the contribution card, mirroring the shape live
     * ReportQueries::orderLog hands the same page.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}}
     */
    public static function orderLog(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $serviceName = null,
        int $page = 1,
    ): array {
        return Paginated::fromArray(
            self::orderLogRows($from, $to, $serviceName),
            max($page, 1),
            self::ORDERS_PER_PAGE,
        );
    }

    /**
     * Every row of that log, the way the spreadsheet export reads it.
     *
     * @return list<array<string, mixed>>
     */
    public static function orderLogRows(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $serviceName = null,
    ): array {
        $rows = [];

        foreach (Operations::orders() as $order) {
            $date = $order['date'];

            if ($date < $from->toDateString() || $date > $to->toDateString()) {
                continue;
            }

            $services = array_map('trim', explode(',', (string) $order['items']));

            if ($serviceName !== null && $serviceName !== '' && ! in_array($serviceName, $services, strict: true)) {
                continue;
            }

            $rows[] = [
                'id' => $order['id'],
                'orderNo' => $order['orderNo'],
                'date' => CarbonImmutable::createFromFormat('!Y-m-d', $date)->format('d/m/Y'),
                /* The fixtures carry the dotted clock the operational modules
                 * print; the log spells a timestamp with a colon. */
                'time' => str_replace('.', ':', (string) $order['time']),
                'vehicle' => $order['vehicle'],
                'plate' => $order['plate'],
                'customer' => $order['customer'],
                'phone' => $order['phone'],
                'services' => $order['items'],
                'status' => $order['status'],
                'total' => $order['total'],
            ];
        }

        return $rows;
    }

    /**
     * Shift performance across the range, mirroring the shape live
     * ReportQueries::shiftSummary hands the same page. `status` is deliberately
     * absent: whether a shift is running is a today-only fact and says nothing
     * about a range.
     *
     * @return list<array{id: string, name: string, time: string|null, cashier: string, initials: string, revenue: int, transactions: int, vehiclesServed: int, moneyIn: int, moneyOut: int}>
     */
    public static function shiftSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $scale = self::rangeScale($from, $to);

        return array_map(static fn (array $shift): array => [
            'id' => $shift['id'],
            'name' => $shift['name'],
            'time' => $shift['time'],
            'cashier' => $shift['cashier'],
            'initials' => $shift['initials'],
            'revenue' => self::scaleFlow($shift['revenue'], $scale),
            'transactions' => self::scaleFlow($shift['transactions'], $scale),
            'vehiclesServed' => self::scaleFlow($shift['vehiclesServed'], $scale),
            'moneyIn' => self::scaleFlow($shift['moneyIn'], $scale),
            'moneyOut' => self::scaleFlow($shift['moneyOut'], $scale),
        ], Brand::shifts());
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
