<?php

use App\Support\Carwash\Catalog;
use App\Support\Carwash\Finance;
use App\Support\Carwash\Operations;
use App\Support\Carwash\RoleAccess;
use Inertia\Testing\AssertableInertia;

/**
 * The cashier settles orders that already exist, so every order has to carry a
 * `paidAmount` the POS can subtract from (BR-06).
 */
test('the cashier lands on the POS with the order list attached', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('carwash.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/admin/Pos')
                ->where('role.key', 'cashier')
                ->has('orders', count(Operations::orders()))
                ->has('orders.0.paidAmount')
                ->has('orders.0.paymentStatus')
        );
});

test('every order carries a paid amount within its total', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'owner'])
        ->get(route('carwash.admin.pos'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            /** @var list<array{paidAmount: int, total: int}> $orders */
            $orders = $page->toArray()['props']['orders'];

            expect($orders)->not->toBeEmpty();

            foreach ($orders as $order) {
                expect($order['paidAmount'])
                    ->toBeGreaterThanOrEqual(0)
                    ->toBeLessThanOrEqual($order['total']);
            }
        });
});

test('the payment status always agrees with how much was collected', function () {
    foreach (Operations::orders() as $order) {
        expect($order['paymentStatus'])->toBeIn(['belum bayar', 'dp', 'lunas']);

        $expected = match (true) {
            $order['paidAmount'] === 0 => 'belum bayar',
            $order['paidAmount'] >= $order['total'] => 'lunas',
            default => 'dp',
        };

        expect($order['paymentStatus'])->toBe(
            $expected,
            "Order {$order['orderNo']} is marked {$order['paymentStatus']} but has paid {$order['paidAmount']} of {$order['total']}.",
        );
    }
});

test('an unpaid order and a deposit order are both on show in the demo', function () {
    $statuses = array_column(Operations::orders(), 'paymentStatus');

    expect($statuses)->toContain('belum bayar')
        ->and($statuses)->toContain('dp')
        ->and($statuses)->toContain('lunas');
});

test('an order that is only half paid still owes the rest', function () {
    $deposits = array_values(array_filter(
        Operations::orders(),
        fn (array $order): bool => $order['paymentStatus'] === 'dp',
    ));

    expect($deposits)->not->toBeEmpty();

    foreach ($deposits as $order) {
        expect($order['total'] - $order['paidAmount'])->toBeGreaterThan(0)
            ->and($order['payment'])->not->toBe('—');
    }
});

test('a redeemed reward is priced into the order before it reaches the till', function () {
    foreach (Operations::orders() as $order) {
        expect($order['discount'])->toBeGreaterThanOrEqual(0);

        if ($order['discount'] > 0) {
            expect($order['reward'])->not->toBe(
                '—',
                "Order {$order['orderNo']} has a discount but names no reward.",
            );
        }
    }
});

test('the cashier is never offered rewards to redeem', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cashier'])
        ->get(route('carwash.admin.pos'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->missing('rewards')
        );
});

test('the front office gets the reward catalog on the order screen', function () {
    $this->withSession([RoleAccess::SESSION_KEY => 'cs'])
        ->get(route('carwash.admin.orders'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('carwash/admin/Orders')
                ->has('rewards', count(Catalog::rewards()))
        );
});

test('the cash summary reports the real outstanding balance', function () {
    $outstanding = array_sum(array_map(
        fn (array $order): int => $order['total'] - $order['paidAmount'],
        Operations::orders(),
    ));

    expect(Operations::outstandingTotal())->toBe($outstanding)
        ->and(Finance::summary()['pendingPayments'])->toBe($outstanding);
});
