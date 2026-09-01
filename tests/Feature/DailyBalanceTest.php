<?php

use App\Actions\Admin\UpdateDailyBalance;
use App\Models\DailyBalance;
use App\Models\Order;
use App\Support\Admin\FinanceQueries;
use Illuminate\Support\Facades\DB;

test('cash and non cash balances are rebuilt after a historical movement', function () {
    $updateDailyBalance = app(UpdateDailyBalance::class);

    $updateDailyBalance->handle(
        '2026-08-30',
        cashIncomeDelta: 100000,
        nonCashIncomeDelta: 50000,
    );
    $updateDailyBalance->handle(
        '2026-09-01',
        cashExpenseDelta: 20000,
        nonCashIncomeDelta: 30000,
    );
    $updateDailyBalance->handle(
        '2026-08-31',
        cashIncomeDelta: 10000,
        nonCashExpenseDelta: 5000,
    );

    $balances = DailyBalance::query()->oldest('date')->get();

    expect($balances)->toHaveCount(3)
        ->and($balances[0]->date->toDateString())->toBe('2026-08-30')
        ->and($balances[0]->cash_balance)->toBe(100000)
        ->and($balances[0]->non_cash_balance)->toBe(50000)
        ->and($balances[1]->date->toDateString())->toBe('2026-08-31')
        ->and($balances[1]->cash_income)->toBe(10000)
        ->and($balances[1]->non_cash_expense)->toBe(5000)
        ->and($balances[1]->cash_balance)->toBe(110000)
        ->and($balances[1]->non_cash_balance)->toBe(45000)
        ->and($balances[2]->date->toDateString())->toBe('2026-09-01')
        ->and($balances[2]->cash_balance)->toBe(90000)
        ->and($balances[2]->non_cash_balance)->toBe(75000);
});

test('finance balance query uses the latest snapshot through the selected date', function () {
    $updateDailyBalance = app(UpdateDailyBalance::class);
    $updateDailyBalance->handle('2026-08-30', cashIncomeDelta: 100000);
    $updateDailyBalance->handle('2026-09-01', nonCashIncomeDelta: 75000);

    expect(FinanceQueries::dailyBalance('2026-08-29'))->toBe(['cash' => 0, 'nonCash' => 0])
        ->and(FinanceQueries::dailyBalance('2026-08-30'))->toBe(['cash' => 100000, 'nonCash' => 0])
        ->and(FinanceQueries::dailyBalance('2026-08-31'))->toBe(['cash' => 100000, 'nonCash' => 0])
        ->and(FinanceQueries::dailyBalance('2026-09-01'))->toBe(['cash' => 100000, 'nonCash' => 75000])
        ->and(FinanceQueries::dailyBalance('2026-09-05'))->toBe(['cash' => 100000, 'nonCash' => 75000]);
});

test('the balance history lists the days that moved money, newest first', function () {
    $updateDailyBalance = app(UpdateDailyBalance::class);
    $updateDailyBalance->handle('2026-08-30', cashIncomeDelta: 100000);
    $updateDailyBalance->handle('2026-08-31', cashExpenseDelta: 40000, nonCashIncomeDelta: 25000);
    $updateDailyBalance->handle('2026-09-02', nonCashIncomeDelta: 75000);

    expect(FinanceQueries::dailyBalanceHistory('2026-09-01'))->toBe([
        [
            'date' => '2026-08-31',
            'cashIncome' => 0,
            'cashExpense' => 40000,
            'cashBalance' => 60000,
            'nonCashIncome' => 25000,
            'nonCashExpense' => 0,
            'nonCashBalance' => 25000,
        ],
        [
            'date' => '2026-08-30',
            'cashIncome' => 100000,
            'cashExpense' => 0,
            'cashBalance' => 100000,
            'nonCashIncome' => 0,
            'nonCashExpense' => 0,
            'nonCashBalance' => 0,
        ],
    ]);
});

test('the balance history stops at the day limit it is asked for', function () {
    $updateDailyBalance = app(UpdateDailyBalance::class);
    $updateDailyBalance->handle('2026-08-30', cashIncomeDelta: 10000);
    $updateDailyBalance->handle('2026-08-31', cashIncomeDelta: 10000);
    $updateDailyBalance->handle('2026-09-01', cashIncomeDelta: 10000);

    $history = FinanceQueries::dailyBalanceHistory('2026-09-01', 2);

    expect($history)->toHaveCount(2)
        ->and(array_column($history, 'date'))->toBe(['2026-09-01', '2026-08-31'])
        ->and($history[0]['cashBalance'])->toBe(30000);
});

test('a day without movement has no balance history row', function () {
    expect(FinanceQueries::dailyBalanceHistory('2026-09-01'))->toBe([]);
});

test('only the Tunai base channel is classified as cash', function () {
    expect(UpdateDailyBalance::channelAmounts([
        ['label' => 'Tunai', 'amount' => 10000],
        ['label' => 'Tunai · Kas Utama', 'amount' => 5000],
        ['label' => 'QRIS', 'amount' => 20000],
        ['label' => 'Debit · BCA', 'amount' => 30000],
    ]))->toBe(['cash' => 15000, 'nonCash' => 50000])
        ->and(UpdateDailyBalance::methodAmounts('Tunai', 10000))->toBe([
            'cash' => 10000,
            'nonCash' => 0,
        ])
        ->and(UpdateDailyBalance::methodAmounts('Transfer', 10000))->toBe([
            'cash' => 0,
            'nonCash' => 10000,
        ]);
});

test('the backfill builds daily totals from source rows without factory callbacks', function () {
    $order = Order::factory()->create();
    $now = now();

    DB::table('order_transactions')->insert([
        'order_id' => $order->id,
        'recorded_by_admin_id' => null,
        'reference' => 'BACKFILL-POS-001',
        'type' => 'Pembayaran Sebagian',
        'amount' => 100000,
        'channel_breakdown' => json_encode([
            ['label' => 'Tunai · Kas Utama', 'amount' => 25000],
            ['label' => 'QRIS', 'amount' => 75000],
        ], JSON_THROW_ON_ERROR),
        'paid_at' => '2026-08-30 10:00:00',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('cash_entries')->insert([
        [
            'direction' => 'in',
            'reference' => 'BACKFILL-MANUAL-IN',
            'category' => 'Pendapatan Lain',
            'description' => 'Pemasukan tunai lama',
            'amount' => 20000,
            'method' => 'Tunai',
            'entry_date' => '2026-08-31',
            'occurred_at' => '2026-08-31 09:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'direction' => 'out',
            'reference' => 'BACKFILL-MANUAL-OUT',
            'category' => 'Operasional',
            'description' => 'Pengeluaran transfer lama',
            'amount' => 30000,
            'method' => 'Transfer',
            'entry_date' => '2026-08-31',
            'occurred_at' => '2026-08-31 11:00:00',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    $migration = require database_path('migrations/2026_09_01_113808_backfill_daily_balance_table.php');
    $migration->up();

    $balances = DailyBalance::query()->oldest('date')->get();

    expect($balances)->toHaveCount(2)
        ->and($balances[0]->cash_income)->toBe(25000)
        ->and($balances[0]->non_cash_income)->toBe(75000)
        ->and($balances[0]->cash_balance)->toBe(25000)
        ->and($balances[0]->non_cash_balance)->toBe(75000)
        ->and($balances[1]->cash_income)->toBe(20000)
        ->and($balances[1]->non_cash_expense)->toBe(30000)
        ->and($balances[1]->cash_balance)->toBe(45000)
        ->and($balances[1]->non_cash_balance)->toBe(45000);
});
