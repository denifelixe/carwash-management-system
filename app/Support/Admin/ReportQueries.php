<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\Lead;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * The reads behind the live reporting module (BR-12).
 *
 * Every figure here is derived from the module that owns it, the same contract
 * the dashboard follows: money comes off the order transactions the Keuangan
 * ledger reads, so a range total can never disagree with the days it covers.
 * Revenue is attributed to the day it was *paid*, never the day it was served —
 * a deposit taken today on next week's booking is today's takings.
 */
class ReportQueries
{
    /** Widest range still charted day by day; anything longer rolls up to months. */
    public const DAILY_RANGE_LIMIT = 62;

    /** Default span when no range is supplied, in days. */
    private const DEFAULT_DAYS = 7;

    /** Days without a visit before a member counts as at risk of not returning. */
    private const CHURN_DAYS = 60;

    /** How many services the contribution chart ranks. */
    private const TOP_SERVICES = 5;

    /** Rows per page in the order log behind the contribution card. */
    public const ORDERS_PER_PAGE = 25;

    /**
     * The stock card. Deliberately takes no range: on hand is a figure for right
     * now and the movement count is a rolling week, which is why the Reports
     * page leaves this prop out of its range reload.
     *
     * @return array{totalItems: int, lowStock: int, stockValue: int, movementsThisWeek: int, topConsumed: string}
     */
    public static function inventorySummary(): array
    {
        return StockQueries::summary();
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
        $today = CarbonImmutable::now()->startOfDay();
        $earliest = CarbonImmutable::parse(self::earliest())->startOfDay();

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
     * Oldest day the filter may reach back to: the first service day on record,
     * but never so recent that the default week would not fit inside it.
     */
    public static function earliest(): string
    {
        $floor = CarbonImmutable::now()->startOfDay()->subDays(self::DEFAULT_DAYS - 1);
        $firstServiceDay = Order::query()->min('service_date');
        $earliest = is_string($firstServiceDay) && $firstServiceDay !== ''
            ? CarbonImmutable::parse($firstServiceDay)->startOfDay()
            : $floor;

        return $earliest->greaterThan($floor)
            ? $floor->toDateString()
            : $earliest->toDateString();
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
            'today' => CarbonImmutable::now()->toDateString(),
            'earliest' => self::earliest(),
        ];
    }

    /** Whole days covered by the range, both ends inclusive. */
    public static function rangeDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->diffInDays($to) + 1;
    }

    /**
     * Revenue vs expense across the range, by day for short ranges and rolled
     * up by month once the range would produce too many bars to read. A quiet
     * day is charted as a zero rather than dropped, so the axis stays even.
     *
     * @return list<array{label: string, caption: string, revenue: int, expense: int, transactions: int}>
     */
    public static function trend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $income = OrderTransaction::query()
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as revenue, COUNT(*) as transactions')
            ->where('paid_at', '>=', $from->startOfDay())
            ->where('paid_at', '<', $to->startOfDay()->addDay())
            ->where('amount', '>', 0)
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $expenses = CashEntry::query()
            ->selectRaw('entry_date as day, SUM(amount) as expense')
            ->where('direction', 'out')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('entry_date')
            ->get()
            ->keyBy(fn (CashEntry $entry): string => CarbonImmutable::parse($entry->getAttribute('day'))->toDateString());

        $days = [];

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $key = $date->toDateString();
            $takings = $income->get($key);

            $days[] = [
                'date' => $date,
                'revenue' => (int) ($takings?->getAttribute('revenue') ?? 0),
                'transactions' => (int) ($takings?->getAttribute('transactions') ?? 0),
                'expense' => (int) ($expenses->get($key)?->getAttribute('expense') ?? 0),
            ];
        }

        return count($days) <= self::DAILY_RANGE_LIMIT
            ? self::dailyTrend($days)
            : self::monthlyTrend($days);
    }

    /**
     * The services that earned the most over the range. Read off the order
     * snapshot columns, so a service renamed or retired since still reports
     * under the name it was sold as.
     *
     * This is the value of what was sold on the orders paid in the range, not
     * the cash those orders brought in — a half-paid order contributes its
     * whole service value here and only its payment to the trend. The page says
     * as much, so the two figures are not read as the same number.
     *
     * @return list<array{name: string, orders: int, revenue: int}>
     */
    public static function topServices(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('order_services')
            ->join('orders', 'orders.id', '=', 'order_services.order_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('order_services.order_id', self::ordersPaidWithin($from, $to))
            ->selectRaw('service_name, COUNT(DISTINCT order_services.order_id) as order_count, SUM(total_price) as revenue')
            ->groupBy('service_name')
            ->orderByDesc('revenue')
            ->limit(self::TOP_SERVICES)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->service_name,
                'orders' => (int) $row->order_count,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * The orders behind the contribution card, newest first.
     *
     * Reads the same set topServices counts — orders a payment landed on inside
     * the range — so opening a service's row shows exactly the orders that
     * produced its figure. `$serviceName` narrows it to one of those services,
     * matched against the name the order was sold under.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}}
     */
    public static function orderLog(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $serviceName = null,
        int $page = 1,
    ): array {
        return Paginated::fromPaginator(
            self::orderLogQuery($from, $to, $serviceName)->paginate(
                perPage: self::ORDERS_PER_PAGE,
                page: max($page, 1),
            ),
            fn (Order $order): array => self::orderRow($order),
        );
    }

    /**
     * Every row of that same log, for the spreadsheet export. Lazy because a
     * download covers the whole range rather than one page, and a year of
     * orders must not be held in memory to write a file that is streamed.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public static function orderLogRows(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $serviceName = null,
    ): LazyCollection {
        return self::orderLogQuery($from, $to, $serviceName)
            ->lazy()
            ->map(fn (Order $order): array => self::orderRow($order));
    }

    /**
     * @return Builder<Order>
     */
    private static function orderLogQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $serviceName,
    ): Builder {
        $query = Order::query()
            ->with('serviceVariations:id,service_id')
            ->whereIn('id', self::ordersPaidWithin($from, $to));

        if ($serviceName !== null && $serviceName !== '') {
            $query->whereHas(
                'serviceVariations',
                fn (Builder $services) => $services->where('order_services.service_name', $serviceName),
            );
        }

        return $query->orderByDesc('id');
    }

    /**
     * How the customer base moved over the range: members on one side, the
     * leads feeding them on the other.
     *
     * Loyalty deliberately has no place here. Nothing records a stamp
     * redemption until the rewards module ships, and a card of zeroes says
     * less than no card at all.
     *
     * @return array{newMembers: int, returningMembers: int, membersServed: int, newLeads: int, convertedLeads: int, openLeads: int, churnRisk: int, averageVisitsPerMember: float}
     */
    public static function customerBase(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rangeStart = $from->startOfDay();
        $rangeEnd = $to->startOfDay()->addDay();

        $servedOrders = Order::query()
            ->whereIn('id', self::ordersPaidWithin($from, $to))
            ->whereNotNull('member_id');

        $visits = (clone $servedOrders)->count();
        $membersServed = (clone $servedOrders)->distinct()->count('member_id');

        return [
            'newMembers' => Member::query()
                ->where('created_at', '>=', $rangeStart)
                ->where('created_at', '<', $rangeEnd)
                ->count(),
            'returningMembers' => (clone $servedOrders)
                ->whereIn('member_id', Order::query()
                    ->select('member_id')
                    ->whereNotNull('member_id')
                    ->where('service_date', '<', $from->toDateString()))
                ->distinct()
                ->count('member_id'),
            'membersServed' => $membersServed,
            'newLeads' => Lead::query()
                ->where('created_at', '>=', $rangeStart)
                ->where('created_at', '<', $rangeEnd)
                ->count(),
            'convertedLeads' => Lead::query()
                ->whereNotNull('converted_member_id')
                ->where('converted_at', '>=', $rangeStart)
                ->where('converted_at', '<', $rangeEnd)
                ->count(),
            /* Current state rather than a range figure: how many leads are
             * still waiting to be converted, whatever period is on screen. */
            'openLeads' => Lead::query()
                ->whereNull('converted_member_id')
                ->where('is_active', true)
                ->count(),
            'churnRisk' => self::churnRisk($to),
            'averageVisitsPerMember' => $membersServed === 0
                ? 0.0
                : round($visits / $membersServed, 1),
        ];
    }

    /**
     * Booking performance over the range. Counted on the service day, because
     * a booking is an operational commitment rather than a payment.
     *
     * @return array{total: int, scheduled: int, completed: int, cancelled: int, showRate: float}
     */
    public static function bookingSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $statuses = self::bookingsWithin($from, $to)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $statuses->sum();
        $scheduled = (int) $statuses->get('booking', 0) + (int) $statuses->get('menunggu', 0);
        $cancelled = (int) $statuses->get('batal', 0);
        /* Bookings that reached a conclusion — the only ones attendance can be judged on. */
        $settled = $total - $scheduled;

        return [
            'total' => $total,
            'scheduled' => $scheduled,
            'completed' => (int) $statuses->get('selesai', 0),
            'cancelled' => $cancelled,
            'showRate' => $settled === 0
                ? 0.0
                : round((($settled - $cancelled) / $settled) * 100, 1),
        ];
    }

    /**
     * Shift performance across the range.
     *
     * Rows are filed by the shift stamped on them when they were written, never
     * inferred from the clock. A name matching no active shift — and a row
     * written with none at all — falls into the Tanpa Shift bucket, so the rows
     * still add up to the range's ledger.
     *
     * @return list<array<string, mixed>>
     */
    public static function shiftSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shifts = self::activeShifts();
        $shiftNames = $shifts->pluck('name')->all();
        $income = self::posTotalsByShift($from, $to);
        $cash = self::cashTotalsByShift($from, $to);

        $summary = $shifts->map(function (AdminShift $shift) use ($income, $cash): array {
            $cashier = $shift->admins->first();

            return [
                'id' => $shift->key,
                'name' => $shift->name,
                'time' => $shift->starts_at !== null && $shift->ends_at !== null
                    ? OrderPresenter::clock($shift->starts_at).' - '.OrderPresenter::clock($shift->ends_at)
                    : null,
                'cashier' => $cashier instanceof Admin ? $cashier->name : '',
                'initials' => $cashier instanceof Admin ? OrderPresenter::initials($cashier->name) : '',
                ...self::shiftTotals(
                    $income[$shift->name] ?? null,
                    $cash[$shift->name] ?? null,
                ),
            ];
        })->all();

        $summary[] = [
            'id' => FinanceQueries::UNASSIGNED_SHIFT_KEY,
            'name' => 'Tanpa Shift',
            'time' => null,
            'cashier' => '',
            'initials' => '',
            ...self::shiftTotals(
                self::unassignedPosTotals($from, $to, $shiftNames),
                self::unassignedCashTotals($cash, $shiftNames),
            ),
        ];

        return $summary;
    }

    /**
     * @param  list<array{date: CarbonImmutable, revenue: int, expense: int, transactions: int}>  $days
     * @return list<array{label: string, caption: string, revenue: int, expense: int, transactions: int}>
     */
    private static function dailyTrend(array $days): array
    {
        return array_map(static fn (array $day): array => [
            'label' => self::shortDate($day['date']),
            'caption' => self::longDate($day['date']),
            'revenue' => $day['revenue'],
            'expense' => $day['expense'],
            'transactions' => $day['transactions'],
        ], $days);
    }

    /**
     * Same keys as the daily branch so the chart never has to ask which one it
     * is drawing. A partial month is summed as it stands, not prorated.
     *
     * @param  list<array{date: CarbonImmutable, revenue: int, expense: int, transactions: int}>  $days
     * @return list<array{label: string, caption: string, revenue: int, expense: int, transactions: int}>
     */
    private static function monthlyTrend(array $days): array
    {
        $months = [];

        foreach ($days as $day) {
            $key = $day['date']->format('Y-m');
            $month = DateFilter::MONTHS[(int) $day['date']->format('n')];

            $months[$key] ??= [
                'label' => $month.' '.$day['date']->format('y'),
                'caption' => $month.' '.$day['date']->format('Y'),
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
     * One line of the order log. The service names come off the order snapshot,
     * so the log reads the way the floor wrote it rather than the way the
     * service master reads today.
     *
     * @return array{id: int, orderNo: string, date: string, time: string, vehicle: string, plate: string, customer: string, phone: string, services: string, status: string, total: int}
     */
    private static function orderRow(Order $order): array
    {
        $stamped = $order->arrived_at ?? $order->created_at;

        return [
            'id' => $order->id,
            'orderNo' => $order->number,
            'date' => $order->service_date->format('d/m/Y'),
            /* Colon-separated, unlike the dotted clock the operational modules
             * print: the log is read as a list of timestamps and exported to a
             * spreadsheet, where 'H.i' reads as a decimal number. */
            'time' => $stamped?->format('H:i') ?? '—',
            'vehicle' => $order->vehicle_name,
            'plate' => $order->vehicle_plate,
            'customer' => $order->customer_name,
            'phone' => $order->customer_phone,
            'services' => $order->serviceVariations->pluck('pivot.service_name')->join(', '),
            'status' => $order->status,
            'total' => (int) $order->total,
        ];
    }

    /**
     * The orders a payment landed on inside the range, as a subquery so a long
     * range never drags every id through PHP.
     *
     * @return Builder<OrderTransaction>
     */
    private static function ordersPaidWithin(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return OrderTransaction::query()
            ->select('order_id')
            ->where('paid_at', '>=', $from->startOfDay())
            ->where('paid_at', '<', $to->startOfDay()->addDay())
            ->where('amount', '>', 0);
    }

    /**
     * @return Builder<Order>
     */
    private static function bookingsWithin(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->where('source', 'booking')
            ->whereBetween('service_date', [$from->toDateString(), $to->toDateString()]);
    }

    /** Members who used to come and have not been back within the churn window. */
    private static function churnRisk(CarbonImmutable $to): int
    {
        $cutoff = $to->subDays(self::CHURN_DAYS)->toDateString();

        return Member::query()
            ->where('is_active', true)
            ->whereHas('orders', fn (Builder $query) => $query->where('status', '!=', 'batal'))
            ->whereDoesntHave('orders', fn (Builder $query) => $query
                ->where('status', '!=', 'batal')
                ->where('service_date', '>=', $cutoff))
            ->count();
    }

    /**
     * @return Collection<int, AdminShift>
     */
    private static function activeShifts(): Collection
    {
        return AdminShift::query()
            ->with(['admins' => fn ($query) => $query
                ->visibleInOperations()
                ->where('is_active', true)
                ->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return array<string, array{revenue: int, transactions: int, vehiclesServed: int}>
     */
    private static function posTotalsByShift(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return OrderTransaction::query()
            ->selectRaw('shift_name, SUM(amount) as revenue, COUNT(*) as transactions, COUNT(DISTINCT order_id) as vehicles_served')
            ->where('paid_at', '>=', $from->startOfDay())
            ->where('paid_at', '<', $to->startOfDay()->addDay())
            ->where('amount', '>', 0)
            ->groupBy('shift_name')
            ->get()
            ->mapWithKeys(fn (OrderTransaction $row): array => [
                (string) $row->shift_name => [
                    'revenue' => (int) $row->getAttribute('revenue'),
                    'transactions' => (int) $row->getAttribute('transactions'),
                    'vehiclesServed' => (int) $row->getAttribute('vehicles_served'),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{in: int, out: int}>
     */
    private static function cashTotalsByShift(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = [];

        $rows = CashEntry::query()
            ->selectRaw('shift_name, direction, SUM(amount) as total')
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('shift_name', 'direction')
            ->get();

        foreach ($rows as $row) {
            $name = (string) $row->shift_name;
            $totals[$name] ??= ['in' => 0, 'out' => 0];
            $totals[$name][$row->direction === 'out' ? 'out' : 'in'] += (int) $row->getAttribute('total');
        }

        return $totals;
    }

    /**
     * Read on its own rather than summed from the grouped rows: one order paid
     * twice under two retired shift names must still count as one vehicle.
     *
     * @param  list<string>  $shiftNames
     * @return array{revenue: int, transactions: int, vehiclesServed: int}
     */
    private static function unassignedPosTotals(CarbonImmutable $from, CarbonImmutable $to, array $shiftNames): array
    {
        $row = OrderTransaction::query()
            ->selectRaw('SUM(amount) as revenue, COUNT(*) as transactions, COUNT(DISTINCT order_id) as vehicles_served')
            ->where('paid_at', '>=', $from->startOfDay())
            ->where('paid_at', '<', $to->startOfDay()->addDay())
            ->where('amount', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereNull('shift_name')
                ->orWhereNotIn('shift_name', $shiftNames))
            ->first();

        return [
            'revenue' => (int) ($row?->getAttribute('revenue') ?? 0),
            'transactions' => (int) ($row?->getAttribute('transactions') ?? 0),
            'vehiclesServed' => (int) ($row?->getAttribute('vehicles_served') ?? 0),
        ];
    }

    /**
     * @param  array<string, array{in: int, out: int}>  $cash
     * @param  list<string>  $shiftNames
     * @return array{in: int, out: int}
     */
    private static function unassignedCashTotals(array $cash, array $shiftNames): array
    {
        $totals = ['in' => 0, 'out' => 0];

        foreach ($cash as $name => $amounts) {
            if (in_array($name, $shiftNames, strict: true)) {
                continue;
            }

            $totals['in'] += $amounts['in'];
            $totals['out'] += $amounts['out'];
        }

        return $totals;
    }

    /**
     * Money in carries the hand-written entries too, so the row matches what
     * the finance ledger reports for the same shift.
     *
     * @param  array{revenue: int, transactions: int, vehiclesServed: int}|null  $income
     * @param  array{in: int, out: int}|null  $cash
     * @return array{revenue: int, transactions: int, vehiclesServed: int, moneyIn: int, moneyOut: int}
     */
    private static function shiftTotals(?array $income, ?array $cash): array
    {
        $revenue = $income['revenue'] ?? 0;

        return [
            'revenue' => $revenue,
            'transactions' => $income['transactions'] ?? 0,
            'vehiclesServed' => $income['vehiclesServed'] ?? 0,
            'moneyIn' => $revenue + ($cash['in'] ?? 0),
            'moneyOut' => $cash['out'] ?? 0,
        ];
    }

    /** "3 Agu" — compact enough for a chart axis. */
    private static function shortDate(CarbonImmutable $date): string
    {
        return $date->format('j').' '.DateFilter::MONTHS[(int) $date->format('n')];
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
