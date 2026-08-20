<?php

use App\Support\Carwash\DateFilter;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
use Inertia\Testing\AssertableInertia;

/** The modules that focus on one day's transactions. */

/**
 * Every figure is anchored to today, so the suite stands on a fixed day and
 * keeps naming dates outright.
 */
beforeEach(function () {
    $this->travelTo('2026-08-03 09:00:00');
});

dataset('dated modules', [
    'dashboard' => ['carwash.admin.dashboard'],
    'orders' => ['carwash.admin.orders'],
    'pos' => ['carwash.admin.pos'],
    'finance' => ['carwash.admin.finance'],
]);

test('a module opens on the day it is being used', function (string $route) {
    $this->withSession(['carwash_role' => 'owner'])
        ->get(route($route))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('filters.date', Reports::todayDate())
                ->where('filters.label', 'Hari ini')
        );
})->with('dated modules');

test('picking a date narrows the module to that day', function (string $route) {
    $this->withSession(['carwash_role' => 'owner'])
        ->get(route($route, ['date' => '2026-08-02']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('filters.date', '2026-08-02')
                ->where('filters.label', '2 Agu 2026')
        );
})->with('dated modules');

test('a date that cannot be read is dropped rather than rejected', function () {
    expect(DateFilter::resolve('kemarin'))->toBe('')
        ->and(DateFilter::resolve(null))->toBe('')
        ->and(DateFilter::resolve('2026-08-05'))->toBe('2026-08-05');
});

test('the order board shows only the orders of the picked day', function () {
    $yesterday = DateFilter::apply(Operations::orders(), '2026-08-02');

    expect($yesterday)->not->toBeEmpty();

    foreach ($yesterday as $order) {
        expect($order['date'])->toBe('2026-08-02');
    }

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('carwash.admin.orders', ['date' => '2026-08-02']))
        ->assertInertia(
            fn (AssertableInertia $page) => $page->has('orders', count($yesterday))
        );
});

test('both cash ledgers follow the picked day', function () {
    $date = '2026-08-01';

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('carwash.admin.finance', ['date' => $date]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('moneyIn', count(DateFilter::apply(Finance::moneyIn(), $date)))
                ->has('moneyOut', count(DateFilter::apply(Finance::moneyOut(), $date)))
        );
});

test('an empty date brings every row back', function () {
    expect(DateFilter::apply(Operations::orders(), ''))
        ->toHaveCount(count(Operations::orders()))
        ->and(DateFilter::label(''))->toBe('Semua tanggal');
});

test('the dashboard figures follow the picked day', function () {
    $today = Reports::dashboardStats(Reports::todayDate());
    $other = Reports::dashboardStats('2026-07-29');

    expect($today[0]['label'])->toBe('Pendapatan Hari Ini')
        ->and($today[0]['value'])->toBe('Rp 2.125.000')
        ->and($today[0]['caption'])->toBe('dari 10 transaksi POS')
        ->and($today[1]['value'])->toBe('8')
        ->and($today[1]['caption'])->toBe('dari 11 order kendaraan')
        // A different day reports that day's figures, not today's.
        ->and($other[0]['label'])->toBe('Pendapatan')
        ->and($other[0]['value'])->toBe('Rp 0')
        ->and($other[1]['value'])->toBe('0');
});

test('the dashboard order caption separates served vehicles from bookings that have not arrived', function () {
    expect(Operations::orderSummary(Reports::todayDate()))->toBe([
        'total' => 11,
        'served' => 8,
        'awaitingBooking' => 3,
    ]);

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('carwash.admin.dashboard'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('orderSummary.total', 11)
                ->where('orderSummary.served', 8)
                ->where('orderSummary.awaitingBooking', 3)
        );
});

test('the dashboard date filter is shown above the hero with a clear return action', function () {
    $dashboardPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Dashboard.vue'),
    );
    $dateFilter = file_get_contents(
        resource_path('js/components/carwash/DateFilterBar.vue'),
    );

    expect(strpos($dashboardPage, '<DateFilterBar'))
        ->toBeLessThan(strpos($dashboardPage, '<!-- Hero -->'))
        ->and($dateFilter)->toContain('Kembali ke Hari Ini')
        ->and($dateFilter)->not->toContain('Semua tanggal')
        ->and($dateFilter)->not->toContain('allowAll');
});

test('booking order keeps its own boards instead of a date filter', function () {
    $bookingsPage = file_get_contents(
        resource_path('js/pages/carwash/admin/Bookings.vue'),
    );

    expect($bookingsPage)->not->toContain('DateFilterBar');

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('carwash.admin.bookings'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->missing('filters')
        );
});

test('the seeded data moves with the calendar', function () {
    $this->travelTo('2027-03-09 09:00:00');

    // Today's orders are dated today, whatever day the prototype is opened on.
    $todaysOrders = DateFilter::apply(Operations::orders(), '2027-03-09');

    expect(Reports::todayDate())->toBe('2027-03-09')
        ->and($todaysOrders)->not->toBeEmpty()
        // The numbers carry the day they belong to.
        ->and($todaysOrders[0]['orderNo'])->toContain('270309');

    // Money in and out lands on today and the two days before it.
    expect(array_column(Finance::moneyIn(), 'date'))
        ->toContain('2027-03-09')
        ->toContain('2027-03-08')
        ->toContain('2027-03-07');
});

test('the schedule keeps its bookings ahead of today', function () {
    $this->travelTo('2027-03-09 09:00:00');

    $dates = array_column(Operations::bookings(), 'date');

    // Four today, two tomorrow, one the day after, and two already behind us.
    expect(array_count_values($dates))->toBe([
        '2027-03-09' => 4,
        '2027-03-10' => 2,
        '2027-03-11' => 1,
        '2027-03-08' => 2,
    ]);
});

test('the running week of figures follows today', function () {
    $this->travelTo('2027-03-09 09:00:00');

    $trend = Reports::revenueTrend();

    expect($trend)->toHaveCount(7)
        // Oldest first, ending on today.
        ->and($trend[0]['date'])->toBe('3 Mar')
        ->and($trend[6]['date'])->toBe('9 Mar')
        ->and($trend[6]['revenue'])->toBe(4850000);
});
