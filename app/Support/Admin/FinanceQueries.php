<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminWorkShift;
use App\Models\CashEntry;
use App\Models\Order;
use App\Models\OrderTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The reads behind the live finance ledger (BR-10). Money the cashier took is
 * never copied into a cash entry: it is read back from the order transaction it
 * was booked on, so the two modules can never disagree about a day's takings.
 */
class FinanceQueries
{
    /**
     * One day of the ledger, ready to hand to a page. Both the finance module
     * and the dashboard read a day through here, so a figure shown on one
     * cannot disagree with the same figure shown on the other.
     *
     * @return array{moneyIn: list<array<string, mixed>>, moneyOut: list<array<string, mixed>>}
     */
    public static function ledgerForDate(string $date): array
    {
        $moneyIn = [
            ...self::posTransactionsForDate($date)
                ->map(fn (OrderTransaction $transaction): array => FinancePresenter::posMoneyIn($transaction))
                ->all(),
            ...self::cashEntriesForDate($date, 'in')
                ->map(fn (CashEntry $entry): array => FinancePresenter::cashEntry($entry))
                ->all(),
        ];

        usort(
            $moneyIn,
            fn (array $first, array $second): int => [$second['date'], $second['time'], $second['ref']]
                <=> [$first['date'], $first['time'], $first['ref']],
        );

        return [
            'moneyIn' => $moneyIn,
            'moneyOut' => self::cashEntriesForDate($date, 'out')
                ->map(fn (CashEntry $entry): array => FinancePresenter::cashEntry($entry))
                ->all(),
        ];
    }

    /**
     * Every payment accepted on one day, whatever service day its order sits on.
     *
     * paid_at holds the outlet's own wall clock, so the day is just the span
     * between one midnight and the next. Kept as a range rather than whereDate
     * so the ['order_id', 'paid_at'] index still carries the scan.
     *
     * @return Collection<int, OrderTransaction>
     */
    public static function posTransactionsForDate(string $date): Collection
    {
        $dayStart = CarbonImmutable::parse($date)->startOfDay();

        return OrderTransaction::query()
            ->with(['order.services:id,name', 'recordedBy:id,name'])
            ->where('paid_at', '>=', $dayStart)
            ->where('paid_at', '<', $dayStart->addDay())
            ->where('amount', '>', 0)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, CashEntry>
     */
    public static function cashEntriesForDate(string $date, string $direction): Collection
    {
        return CashEntry::query()
            ->with('recordedBy:id,name')
            ->whereDate('entry_date', $date)
            ->where('direction', $direction)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The orders the day's payments belong to. A deposit taken today may sit on
     * a booking served next week, so this cannot filter on the service day.
     *
     * @return Collection<int, Order>
     */
    public static function ordersForDate(string $date): Collection
    {
        $dayStart = CarbonImmutable::parse($date)->startOfDay();

        return Order::query()
            ->with(['services:id,name', 'transactions.recordedBy:id,name', 'crew:id,name'])
            ->whereHas('transactions', fn ($query) => $query
                ->where('paid_at', '>=', $dayStart)
                ->where('paid_at', '<', $dayStart->addDay()))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Shift performance for one day, read from the same payments and cash
     * entries the ledger below shows.
     *
     * @param  list<array<string, mixed>>  $moneyIn
     * @param  list<array<string, mixed>>  $moneyOut
     * @return list<array<string, mixed>>
     */
    public static function shiftSummary(array $moneyIn, array $moneyOut, string $date): array
    {
        $now = CarbonImmutable::now();

        return self::shifts()->map(function (AdminWorkShift $shift) use ($moneyIn, $moneyOut, $date, $now): array {
            $shiftIncome = self::entriesOfShift($moneyIn, $shift);
            $shiftExpenses = self::entriesOfShift($moneyOut, $shift);
            $posIncome = array_values(array_filter(
                $shiftIncome,
                fn (array $entry): bool => $entry['source'] === 'pos',
            ));
            $cashier = $shift->admins->first();
            /* A shift with no closing time never reads as finished. */
            $isRunning = $date === $now->toDateString()
                && ($shift->ends_at === null || $now->format('H:i:s') < $shift->ends_at);

            return [
                'id' => $shift->key,
                'name' => $shift->name,
                'time' => $shift->starts_at !== null && $shift->ends_at !== null
                    ? self::clock($shift->starts_at).' - '.self::clock($shift->ends_at)
                    : null,
                'cashier' => $cashier instanceof Admin ? $cashier->name : '',
                'initials' => $cashier instanceof Admin ? OrderPresenter::initials($cashier->name) : '',
                'revenue' => array_sum(array_column($posIncome, 'amount')),
                'transactions' => count($posIncome),
                'vehiclesServed' => count(array_unique(array_filter(
                    array_column($posIncome, 'orderId'),
                    fn (mixed $orderId): bool => $orderId !== null,
                ))),
                'moneyIn' => array_sum(array_column($shiftIncome, 'amount')),
                'moneyOut' => array_sum(array_column($shiftExpenses, 'amount')),
                'status' => $isRunning ? 'berjalan' : 'selesai',
            ];
        })->all();
    }

    /**
     * The cash position of one day. The opening balance is deliberately zero:
     * these cards report the day's movement, not a carried-over till.
     *
     * @param  list<array<string, mixed>>  $moneyIn
     * @param  list<array<string, mixed>>  $moneyOut
     * @return array{openingBalance: int, todayIn: int, todayOut: int, remainingBalance: int, closingBalance: int, pendingPayments: int}
     */
    public static function cashSummary(array $moneyIn, array $moneyOut): array
    {
        $todayIn = (int) array_sum(array_column($moneyIn, 'amount'));
        $todayOut = (int) array_sum(array_column($moneyOut, 'amount'));

        return [
            'openingBalance' => 0,
            'todayIn' => $todayIn,
            'todayOut' => $todayOut,
            'remainingBalance' => $todayIn - $todayOut,
            'closingBalance' => $todayIn - $todayOut,
            'pendingPayments' => self::outstandingTotal(),
        ];
    }

    /** Money still owed on every order that is neither settled nor cancelled. */
    public static function outstandingTotal(): int
    {
        return (int) Order::query()
            ->whereNotIn('status', ['selesai', 'batal'])
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')
            ->value('due');
    }

    /**
     * The shift a payment fell in, for rows booked before the cashier's shift
     * was recorded alongside them.
     */
    public static function shiftNameFor(string $time): ?string
    {
        $clock = str_replace('.', ':', $time).':00';

        foreach (self::shifts() as $shift) {
            /* A shift without both ends describes no window to fall inside. */
            if ($shift->starts_at === null || $shift->ends_at === null) {
                continue;
            }

            $isOvernight = $shift->ends_at <= $shift->starts_at;
            $withinShift = $isOvernight
                ? $clock >= $shift->starts_at || $clock < $shift->ends_at
                : $clock >= $shift->starts_at && $clock < $shift->ends_at;

            if ($withinShift) {
                return $shift->name;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, AdminWorkShift>
     */
    private static function shifts(): Collection
    {
        return AdminWorkShift::query()
            ->with(['admins' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private static function entriesOfShift(array $entries, AdminWorkShift $shift): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['shift'] === $shift->name,
        ));
    }

    /** Shift windows are stored as times but read as 07.00 on the console. */
    private static function clock(string $time): string
    {
        return Str::of($time)->substr(0, 5)->replace(':', '.')->value();
    }
}
