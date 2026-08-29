<?php

namespace App\Support\Admin;

use App\Models\Member;
use App\Models\Order;
use App\Models\Service;
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
     * The catalog behind an order list: everything sellable today plus the
     * services those orders were billed with, even once retired.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Service>
     */
    public static function servicesFor(Collection $orders): Collection
    {
        return Service::query()
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereHas('orders', fn ($orderQuery) => $orderQuery->whereKey($orders->modelKeys())))
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Member>
     */
    public static function customers(): Collection
    {
        return Member::query()
            ->where('is_active', true)
            ->with(['vehicles' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id')])
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{date: string, today: string, earliest: string, latest: string, label: string}
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
        ];
    }

    /**
     * @return Builder<Order>
     */
    private static function baseQuery(): Builder
    {
        return Order::query()->with([
            'services:id,name',
            'transactions.recordedBy:id,name',
            'crew:id,name',
        ]);
    }
}
