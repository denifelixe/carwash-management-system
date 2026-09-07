<?php

use App\Models\DailyBalance;
use App\Models\Order;
use App\Models\OrderTransaction;

function repairPaidOrderSettlementStatuses(): void
{
    $migration = require database_path('migrations/2026_09_07_163908_repair_fully_paid_orders_stuck_in_settlement.php');

    $migration->up();
}

test('paid settlement orders are repaired once without changing their payments', function (?string $invoiceNumber) {
    $order = Order::factory()->create([
        'status' => 'pelunasan',
        'total' => 135000,
        'paid_amount' => 135000,
        'invoice_number' => $invoiceNumber,
    ]);
    $transaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'type' => 'Pembayaran Lunas',
        'amount' => 135000,
        'channel_breakdown' => [['label' => 'QRIS', 'amount' => 135000]],
    ]);
    $orderAttributes = $order->refresh()->getAttributes();
    $transactionAttributes = $transaction->refresh()->getAttributes();
    $balanceAttributes = DailyBalance::query()->sole()->getAttributes();

    repairPaidOrderSettlementStatuses();

    expect($order->refresh())
        ->status->toBe('selesai')
        ->invoice_number->toBe($invoiceNumber ?? str_replace('ORD', 'ZW', $order->number))
        ->and(collect($order->getAttributes())->except(['status', 'invoice_number', 'updated_at'])->all())
        ->toBe(collect($orderAttributes)->except(['status', 'invoice_number', 'updated_at'])->all())
        ->and($transaction->refresh()->getAttributes())->toBe($transactionAttributes)
        ->and($order->transactions()->count())->toBe(1)
        ->and(DailyBalance::query()->sole()->getAttributes())->toBe($balanceAttributes);

    $repairedAttributes = $order->getAttributes();
    $this->travel(1)->minutes();

    repairPaidOrderSettlementStatuses();

    expect($order->refresh()->getAttributes())->toBe($repairedAttributes);
})->with([null, 'ZW-EXISTING']);

test('the settlement repair leaves other stages and unpaid orders untouched', function (string $status, int $paidAmount) {
    $order = Order::factory()->create([
        'status' => $status,
        'total' => 135000,
        'paid_amount' => $paidAmount,
    ]);
    OrderTransaction::factory()->create(['order_id' => $order->id, 'amount' => $paidAmount]);
    $attributes = $order->refresh()->getAttributes();

    repairPaidOrderSettlementStatuses();

    expect($order->refresh()->getAttributes())->toBe($attributes);
})->with([
    ['booking', 135000],
    ['menunggu', 135000],
    ['proses', 135000],
    ['batal', 135000],
    ['selesai', 135000],
    ['pelunasan', 40000],
    ['pelunasan', 0],
]);

test('the settlement repair skips inconsistent or deleted records and advance payments', function (string $scenario) {
    $order = Order::factory()->create([
        'status' => 'pelunasan',
        'total' => 135000,
        'paid_amount' => 135000,
    ]);

    if ($scenario !== 'missing transaction') {
        $transaction = OrderTransaction::factory()->create([
            'order_id' => $order->id,
            'type' => $scenario === 'advance payment' ? 'Pembayaran Sebagian' : 'Pembayaran Lunas',
            'amount' => $scenario === 'mismatched amount' ? 60000 : 135000,
        ]);

        if ($scenario === 'deleted transaction') {
            $transaction->delete();
        }
    }

    if ($scenario === 'deleted order') {
        $order->delete();
    }

    $attributes = $order->refresh()->getAttributes();

    repairPaidOrderSettlementStatuses();

    expect($order->refresh()->getAttributes())->toBe($attributes);
})->with(['missing transaction', 'mismatched amount', 'deleted transaction', 'deleted order', 'advance payment']);
