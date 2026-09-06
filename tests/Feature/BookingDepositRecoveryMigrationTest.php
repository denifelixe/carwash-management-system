<?php

use App\Models\Admin;
use App\Models\DailyBalance;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\OrderQueries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-06 15:00:00'));
});

function bookingDepositRecoveryMigration(): Migration
{
    return require database_path('migrations/2026_09_06_224548_repair_booking_statuses_after_deleted_deposits.php');
}

/** @param array<string, mixed> $overrides */
function affectedDepositBooking(array $overrides = []): Order
{
    $order = Order::factory()->create(array_merge([
        'source' => 'booking',
        'status' => 'pelunasan',
        'service_date' => today()->addDays(481),
        'arrived_at' => null,
        'total' => 45000,
        'paid_amount' => 0,
        'updated_at' => now()->subHour(),
    ], $overrides));
    OrderTransaction::factory()->for($order)->create([
        'deleted_at' => now()->subHour(),
        'deleted_by_admin_id' => Admin::factory(),
    ]);

    return $order;
}

test('the migration recovers affected bookings without changing payment data', function (int $remainingAmount, int $daysAhead) {
    $order = affectedDepositBooking([
        'service_date' => today()->addDays($daysAhead),
        'paid_amount' => $remainingAmount,
        'payment_method' => $remainingAmount > 0 ? 'Tunai' : null,
    ]);

    if ($remainingAmount > 0) {
        OrderTransaction::factory()->withDailyBalance()->for($order)->create(['amount' => $remainingAmount]);
    }

    $originalOrder = $order->refresh()->getRawOriginal();
    $originalTransactions = $order->transactions()->withTrashed()->get()->toArray();
    $originalBalances = DailyBalance::all()->toArray();
    $migration = bookingDepositRecoveryMigration();
    $migration->up();
    $migration->up();
    $migration->down();

    expect($order->refresh()->status)->toBe('booking')
        ->and(collect($order->getRawOriginal())->except(['status', 'updated_at'])->all())
        ->toBe(collect($originalOrder)->except(['status', 'updated_at'])->all())
        ->and($order->transactions()->withTrashed()->get()->toArray())->toBe($originalTransactions)
        ->and(DailyBalance::all()->toArray())->toBe($originalBalances)
        ->and(OrderQueries::upcomingBookings(today()->toDateString())->modelKeys())->toContain($order->id)
        ->and(OrderQueries::bookings()->modelKeys())->toContain($order->id);
})->with([0, 10000])->with([0, 481]);

test('the migration leaves bookings with ambiguous or unrelated histories untouched', function (string $scenario) {
    $order = affectedDepositBooking();
    $transaction = $order->transactions()->withTrashed()->sole();

    match ($scenario) {
        'walk-in' => $order->update(['source' => 'walk-in']),
        'arrived' => $order->update(['arrived_at' => now()]),
        'past' => $order->update(['service_date' => today()->subDay()]),
        'invoice' => $order->update(['invoice_number' => 'ZW-SETTLED']),
        'fully paid' => $order->update(['paid_amount' => 45000]),
        'inconsistent payment' => $order->update(['paid_amount' => 10000]),
        'order deleted' => $order->delete(),
        'later edit' => $order->update(['notes' => 'Updated after deletion']),
        'no deleted deposit' => $transaction->restore(),
        'no audit' => $transaction->update(['deleted_by_admin_id' => null]),
        'settlement history' => $transaction->update(['type' => 'Pembayaran Lunas']),
        default => $order->update(['status' => $scenario]),
    };

    if ($scenario !== 'later edit') {
        $order->update(['updated_at' => now()->subHour()]);
    }

    $before = $order->refresh()->getRawOriginal();
    bookingDepositRecoveryMigration()->up();

    expect($order->refresh()->getRawOriginal())->toBe($before);
})->with([
    'walk-in', 'arrived', 'past', 'invoice', 'fully paid', 'inconsistent payment',
    'order deleted', 'later edit', 'no deleted deposit', 'no audit', 'settlement history',
    'booking', 'menunggu', 'proses', 'selesai', 'batal',
]);
