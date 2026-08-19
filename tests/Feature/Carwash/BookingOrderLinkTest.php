<?php

use App\Support\Carwash\Operations;
use App\Support\Carwash\Reports;
use Inertia\Testing\AssertableInertia;

/** Bookings whose car has not arrived, so the order board derives their row. */
function awaitingBookings(): array
{
    $started = array_column(Operations::orders(), 'status', 'orderNo');

    return array_values(array_filter(
        Operations::bookings(),
        fn (array $booking): bool => ($started[$booking['code']] ?? null) === 'booking',
    ));
}

test('a booking that has not arrived also sits on the order board', function () {
    $orderNumbers = array_column(Operations::orders(), 'orderNo');

    foreach (awaitingBookings() as $booking) {
        expect($orderNumbers)->toContain($booking['code']);
    }
});

test('a booking order carries the booking it came from', function () {
    $ordersByNumber = array_column(Operations::orders(), null, 'orderNo');

    foreach (awaitingBookings() as $booking) {
        $order = $ordersByNumber[$booking['code']];

        expect($order['status'])->toBe('booking')
            ->and($order['source'])->toBe('booking')
            ->and($order['customer'])->toBe($booking['customer'])
            ->and($order['plate'])->toBe($booking['plate'])
            ->and($order['total'])->toBe($booking['estimate'])
            ->and($order['serviceIds'])->toBe($booking['serviceIds'])
            // Nothing is collected before the car shows up.
            ->and($order['paidAmount'])->toBe(0)
            ->and($order['transactions'])->toBe([]);
    }
});

test('every booking is on the order board under its own number', function () {
    $ordersByNumber = array_column(Operations::orders(), null, 'orderNo');

    foreach (Operations::bookings() as $booking) {
        expect($ordersByNumber)->toHaveKey($booking['code']);
    }
});

test('the booking category contains exactly the booking orders for the selected day', function () {
    $today = Reports::todayDate();
    $bookingNumbers = array_column(array_filter(
        Operations::bookings(),
        fn (array $booking): bool => $booking['date'] === $today,
    ), 'code');
    $bookingOrderNumbers = array_column(array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['date'] === $today && $order['source'] === 'booking',
    ), 'orderNo');

    sort($bookingNumbers);
    sort($bookingOrderNumbers);

    expect($bookingOrderNumbers)
        ->toHaveCount(4)
        ->toBe($bookingNumbers);
});

test('the order module is what says where a booking stands', function () {
    $statuses = array_column(Operations::orders(), 'status', 'orderNo');

    foreach (Operations::scheduledBookings() as $booking) {
        expect($booking['orderStatus'])->toBe($statuses[$booking['code']]);
    }

    // A booking already worked on is no longer parked on the booking stage.
    expect(array_column(Operations::scheduledBookings(), 'orderStatus'))
        ->toContain('proses')
        ->toContain('selesai')
        ->toContain('batal');
});

test('every order stays on one of the lifecycle stages', function () {
    $stages = Operations::orderStatuses();

    foreach (Operations::orders() as $order) {
        expect($stages)->toContain($order['status']);
    }
});

test('the cashier is not offered a car that has not arrived or was cancelled', function () {
    foreach (Operations::billableOrders() as $order) {
        expect($order['status'])->not->toBe('booking')
            ->and($order['status'])->not->toBe('batal');
    }

    $this->withSession(['carwash_role' => 'owner'])
        ->get(route('carwash.admin.pos'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/admin/Pos')
                ->where('orders', fn ($orders) => $orders
                    ->every(fn (array $order): bool => $order['status'] !== 'booking'))
        );
});
