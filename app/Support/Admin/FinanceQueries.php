<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\Order;
use App\Models\OrderTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The reads behind the live finance ledger (BR-10). Money the cashier took is
 * never copied into a cash entry: it is read back from the order transaction it
 * was booked on, so the two modules can never disagree about a day's takings.
 */
class FinanceQueries
{
    /** The bucket for ledger rows that match no active shift, as Finance.vue and Pos.vue key it. */
    public const UNASSIGNED_SHIFT_KEY = 'tanpa-shift';

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
            ->with(['recordedBy:id,name', 'attachments'])
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
     * entries the ledger below shows. `$withUnassigned` appends the Tanpa
     * Shift bucket so a caller showing every shift side by side still adds up
     * to the day's ledger; Finance.vue leaves it off because its tab strip
     * already carries a fixed one.
     *
     * @param  list<array<string, mixed>>  $moneyIn
     * @param  list<array<string, mixed>>  $moneyOut
     * @return list<array<string, mixed>>
     */
    public static function shiftSummary(array $moneyIn, array $moneyOut, string $date, bool $withUnassigned = false): array
    {
        $now = CarbonImmutable::now();
        $shifts = self::shifts();

        $summary = $shifts->map(function (AdminShift $shift) use ($moneyIn, $moneyOut, $date, $now): array {
            $cashier = $shift->admins->first();
            /* A shift with no closing time never reads as finished. */
            $isRunning = $date === $now->toDateString()
                && ($shift->ends_at === null || $now->format('H:i:s') < $shift->ends_at);

            return [
                'id' => $shift->key,
                'name' => $shift->name,
                'time' => $shift->starts_at !== null && $shift->ends_at !== null
                    ? OrderPresenter::clock($shift->starts_at).' - '.OrderPresenter::clock($shift->ends_at)
                    : null,
                'cashier' => $cashier instanceof Admin ? $cashier->name : '',
                'initials' => $cashier instanceof Admin ? OrderPresenter::initials($cashier->name) : '',
                ...self::shiftTotals(
                    self::entriesOfShift($moneyIn, $shift->name),
                    self::entriesOfShift($moneyOut, $shift->name),
                ),
                'status' => $isRunning ? 'berjalan' : 'selesai',
            ];
        })->all();

        if ($withUnassigned) {
            $summary[] = self::unassignedShiftSummary($moneyIn, $moneyOut, $shifts->pluck('name')->all());
        }

        return $summary;
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
     * @return Collection<int, AdminShift>
     */
    private static function shifts(): Collection
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
     * Everything the day's ledger booked outside the active roster: rows written
     * with no shift, and rows stamped with a shift since renamed or retired.
     *
     * @param  list<array<string, mixed>>  $moneyIn
     * @param  list<array<string, mixed>>  $moneyOut
     * @param  list<string>  $shiftNames
     * @return array<string, mixed>
     */
    private static function unassignedShiftSummary(array $moneyIn, array $moneyOut, array $shiftNames): array
    {
        return [
            'id' => self::UNASSIGNED_SHIFT_KEY,
            'name' => 'Tanpa Shift',
            'time' => null,
            'cashier' => '',
            'initials' => '',
            ...self::shiftTotals(
                self::entriesOutsideShifts($moneyIn, $shiftNames),
                self::entriesOutsideShifts($moneyOut, $shiftNames),
            ),
            'status' => '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $income
     * @param  list<array<string, mixed>>  $expenses
     * @return array{revenue: int, transactions: int, vehiclesServed: int, moneyIn: int, moneyOut: int}
     */
    private static function shiftTotals(array $income, array $expenses): array
    {
        $posIncome = array_values(array_filter(
            $income,
            fn (array $entry): bool => $entry['source'] === 'pos',
        ));

        return [
            'revenue' => (int) array_sum(array_column($posIncome, 'amount')),
            'transactions' => count($posIncome),
            'vehiclesServed' => count(array_unique(array_filter(
                array_column($posIncome, 'orderId'),
                fn (mixed $orderId): bool => $orderId !== null,
            ))),
            'moneyIn' => (int) array_sum(array_column($income, 'amount')),
            'moneyOut' => (int) array_sum(array_column($expenses, 'amount')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private static function entriesOfShift(array $entries, string $shiftName): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['shift'] === $shiftName,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $shiftNames
     * @return list<array<string, mixed>>
     */
    private static function entriesOutsideShifts(array $entries, array $shiftNames): array
    {
        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => ! in_array($entry['shift'], $shiftNames, strict: true),
        ));
    }
}
