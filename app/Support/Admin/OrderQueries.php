<?php

namespace App\Support\Admin;

use App\Models\AdminShift;
use App\Models\Member;
use App\Models\Order;
use App\Models\Service;
use App\Support\AppSettings;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the live order modules share, so the order floor and the cashier see
 * the same rows for the same day.
 */
class OrderQueries
{
    /** @var list<string> */
    public const PAYMENT_METHODS = ['Tunai', 'QRIS', 'Kredit', 'Debit', 'Transfer', 'E-Money'];

    /**
     * What a hand-written expense may be paid with. Outgoing money is only ever
     * asked whether it left the till or not — the same split the daily balance
     * keeps — so naming a card network on the way out buys nothing.
     *
     * @var list<string>
     */
    public const EXPENSE_METHODS = ['Tunai', 'Non-Tunai'];

    /**
     * The methods a hand-written entry of this direction may name, the way
     * FinanceCategories::recordable answers for its categories.
     *
     * @return list<string>
     */
    public static function recordableMethods(mixed $direction): array
    {
        if ($direction === 'out') {
            return self::EXPENSE_METHODS;
        }

        return self::PAYMENT_METHODS;
    }

    /**
     * Every order of one service day, with the relations the payload reads.
     *
     * @return Collection<int, Order>
     */
    public static function forDate(string $date): Collection
    {
        return self::baseQuery()
            ->whereDate('service_date', $date)
            ->latest('arrived_at')
            ->latest('id')
            ->get();
    }

    /**
     * Bookings whose car has not arrived yet, from today onwards. The cashier
     * takes deposits on these before the visit, so they stay visible whichever
     * day is filtered.
     *
     * @return Collection<int, Order>
     */
    public static function upcomingBookings(string $today): Collection
    {
        return self::baseQuery()
            ->where('source', 'booking')
            ->where('status', 'booking')
            ->whereDate('service_date', '>=', $today)
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Every scheduled order, including completed and cancelled history.
     *
     * @return Collection<int, Order>
     */
    public static function bookings(): Collection
    {
        return self::baseQuery()
            ->where('source', 'booking')
            ->orderByDesc('service_date')
            ->latest('id')
            ->get();
    }

    /**
     * The catalog behind an order list: everything sellable today plus the
     * services those orders were billed with, even once retired.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Service>
     */
    public static function servicesFor(Collection $orders): Collection
    {
        return Service::query()
            ->with(['serviceVariations' => fn ($query) => $query->orderBy('id')])
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereHas(
                    'serviceVariations.orders',
                    fn ($orderQuery) => $orderQuery->whereKey($orders->modelKeys()),
                ))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Vehicle totals for a business day, counted off the same rows the order
     * board shows. A cancelled order is neither served nor still scheduled, so
     * it drops out of all three figures.
     *
     * @return array{total: int, served: int, awaitingBooking: int}
     */
    public static function summaryForDate(string $date): array
    {
        $statuses = Order::query()
            ->whereDate('service_date', $date)
            ->where('status', '!=', 'batal')
            ->pluck('status');

        return [
            'total' => $statuses->count(),
            'served' => $statuses->reject(fn (string $status): bool => $status === 'booking')->count(),
            'awaitingBooking' => $statuses->filter(fn (string $status): bool => $status === 'booking')->count(),
        ];
    }

    /**
     * The shifts a payment can be booked under, in the order the day runs. A
     * retired shift is left out: its old payments read as unassigned rather
     * than keeping a tab alive that nobody can be rostered onto.
     *
     * @return Collection<int, AdminShift>
     */
    public static function workShifts(): Collection
    {
        return AdminShift::query()
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Member>
     */
    public static function customers(): Collection
    {
        return self::withMemberAggregates(Member::query())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public static function withMemberAggregates(Builder $query): Builder
    {
        return $query
            ->with(['vehicles' => fn ($vehicleQuery) => $vehicleQuery->orderByDesc('is_primary')->orderBy('id')])
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->withSum([
                'orders as stamps_earned_total' => fn ($orderQuery) => $orderQuery->where('status', '!=', 'batal'),
            ], 'stamps_earned')
            ->withMax([
                'orders as last_order_date' => fn ($orderQuery) => $orderQuery->where('status', '!=', 'batal'),
            ], 'service_date');
    }

    /**
     * @return array{date: string, today: string, earliest: string, latest: string, label: string, timezone: string}
     */
    public static function filters(string $selectedDate, CarbonImmutable $today): array
    {
        $earliest = Order::query()->min('service_date');

        return [
            'date' => $selectedDate,
            'today' => $today->toDateString(),
            'earliest' => is_string($earliest) ? $earliest : $today->toDateString(),
            'latest' => $today->addYear()->toDateString(),
            'label' => $selectedDate === $today->toDateString() ? 'Hari ini' : DateFilter::format($selectedDate),
            'timezone' => AppSettings::timezone(),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private static function baseQuery(): Builder
    {
        return Order::query()->with([
            'serviceVariations:id,service_id',
            'serviceVariations.service:id,name',
            'transactions.recordedBy:id,name',
            'createdBy:id,name',
            'handledByAdmin:id,name',
            'crew:id,name',
        ]);
    }
}
