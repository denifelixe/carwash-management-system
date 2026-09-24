<?php

use App\Models\CashEntry;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\DocumentNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('order and cash sequences share their period and reset in the next period', function () {
    DB::transaction(function (): void {
        expect(DocumentNumbers::order(false, '2026-08-31 23:59:00'))->toBe('00000001/ORD/0826')
            ->and(DocumentNumbers::order(true, '2026-08-31 23:59:00'))->toBe('00000002/ORD/BK/0826')
            ->and(DocumentNumbers::order(false, '2026-09-01 00:00:00'))->toBe('00000001/ORD/0926')
            ->and(DocumentNumbers::cashEntry('Pembelian Bahan', '2026-09-24'))->toBe('TRX-PB-260924-0001')
            ->and(DocumentNumbers::cashEntry('Penjualan Produk', '2026-09-24'))->toBe('TRX-PP-260924-0002')
            ->and(DocumentNumbers::cashEntry('Pembelian Bahan', '2026-09-25'))->toBe('TRX-PB-260925-0001');
    });
});

test('existing records including soft deleted rows receive ordered numbers', function () {
    $first = Order::factory()->create([
        'number' => 'ORD-OLD-A',
        'invoice_number' => 'ZW-OLD-A',
        'created_at' => '2026-08-01 08:00:00',
    ]);
    $booking = Order::factory()->create([
        'number' => 'ORD-BK-OLD-B',
        'source' => 'booking',
        'created_at' => '2026-08-02 08:00:00',
    ]);
    $booking->delete();
    $nextMonth = Order::factory()->create([
        'number' => 'ORD-OLD-C',
        'created_at' => '2026-09-01 08:00:00',
    ]);
    $paymentOne = OrderTransaction::factory()->for($first)->create([
        'reference' => 'PAY-OLD-A',
        'created_at' => '2026-08-01 09:00:00',
    ]);
    $paymentOne->delete();
    $paymentTwo = OrderTransaction::factory()->for($first)->create([
        'reference' => 'PAY-OLD-B',
        'created_at' => '2026-08-01 10:00:00',
    ]);
    $cashOne = CashEntry::factory()->create([
        'reference' => 'CASH-OLD-A',
        'category' => 'Pembelian Bahan',
        'entry_date' => '2026-09-24',
        'occurred_at' => '2026-09-24 08:00:00',
    ]);
    $cashOne->delete();
    $cashTwo = CashEntry::factory()->create([
        'reference' => 'CASH-OLD-B',
        'category' => 'Penjualan Produk',
        'entry_date' => '2026-09-24',
        'occurred_at' => '2026-09-24 09:00:00',
    ]);
    $cashNextDay = CashEntry::factory()->create([
        'reference' => 'CASH-OLD-C',
        'category' => 'Pembelian Bahan',
        'entry_date' => '2026-09-25',
        'occurred_at' => '2026-09-25 08:00:00',
    ]);

    Schema::drop('number_sequences');
    $migration = require database_path('migrations/2026_09_24_214409_create_number_sequences_and_backfill_document_numbers.php');
    $migration->up();

    expect($first->refresh()->number)->toBe('00000001/ORD/0826')
        ->and($first->invoice_number)->toBe($first->number)
        ->and($booking->refresh()->number)->toBe('00000002/ORD/BK/0826')
        ->and($booking->invoice_number)->toBe($booking->number)
        ->and($nextMonth->refresh()->number)->toBe('00000001/ORD/0926')
        ->and($paymentOne->refresh()->reference)->toBe('00000001/ORD/0826/TRX1')
        ->and($paymentTwo->refresh()->reference)->toBe('00000001/ORD/0826/TRX2')
        ->and($cashOne->refresh()->reference)->toBe('TRX-PB-260924-0001')
        ->and($cashTwo->refresh()->reference)->toBe('TRX-PP-260924-0002')
        ->and($cashNextDay->refresh()->reference)->toBe('TRX-PB-260925-0001');

    DB::transaction(function (): void {
        expect(DocumentNumbers::order(false, '2026-08-03 08:00:00'))->toBe('00000003/ORD/0826')
            ->and(DocumentNumbers::cashEntry('Operasional', '2026-09-24'))->toBe('TRX-O-260924-0003');
    });
});
