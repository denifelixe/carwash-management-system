<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Models\CashEntryAttachment;
use App\Models\DailyBalance;
use App\Models\Order;
use App\Models\OrderTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/*
 * The ledger files a row on the Jakarta day it happened, so a suite running at
 * the moment that day turns over would see a payment land on either side of the
 * boundary. Every test here reads "today" from one fixed instant instead.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-30 10:00', 'Asia/Jakarta'));
});

/**
 * @param  array<string, bool>  $abilities
 */
function financeStaff(array $abilities): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'finance_'.uniqid(),
        'name' => 'Finance Staff',
        'description' => 'Role uji akses keuangan.',
        'is_active' => true,
    ]);

    $role->modules()->attach(
        AdminModule::query()->where('key', 'finance')->firstOrFail(),
        [
            'can_create' => $abilities['create'] ?? false,
            'can_read' => $abilities['read'] ?? false,
            'can_update' => $abilities['update'] ?? false,
            'can_delete' => $abilities['delete'] ?? false,
        ],
    );

    return Admin::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function cashEntryPayload(array $overrides = []): array
{
    return array_merge([
        'entry_date' => now()->toDateString(),
        'direction' => 'in',
        'category' => 'Penjualan Produk',
        'description' => 'Penjualan parfum mobil 6 botol',
        'amount' => 360000,
        'method' => 'Tunai',
    ], $overrides);
}

function financeDisk(): string
{
    return (string) config('filesystems.default');
}

test('guests cannot open the finance module', function () {
    $this->get(route('admin.finance.index'))
        ->assertRedirect(route('admin.login'));
});

test('a staff member without read access is refused the finance module', function () {
    $this->actingAs(financeStaff(['read' => false]), 'admin')
        ->get(route('admin.finance.index'))
        ->assertForbidden();
});

test('an owner sees the live finance ledger with every capability', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/Finance')
                ->where('mode', 'live')
                ->where('capabilities.create', true)
                ->where('capabilities.update', true)
                ->where('capabilities.delete', true)
                ->where('cashSummary.openingBalance', 0)
                ->where('dailyBalance.cash', 0)
                ->where('dailyBalance.nonCash', 0)
                ->has('dailyBalanceHistory', 0)
                ->has('shifts', 2)
                ->where('shifts.0.id', 'morning')
                ->where('shifts.0.time', '08.00 - 16.00')
                ->has('moneyIn', 0)
                ->has('moneyOut', 0),
        );
});

test('the balance card is handed the snapshots behind it, newest first', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    DailyBalance::factory()->create([
        'date' => '2026-08-29',
        'cash_income' => 100000,
        'cash_expense' => 0,
        'cash_balance' => 100000,
        'non_cash_income' => 0,
        'non_cash_expense' => 0,
        'non_cash_balance' => 0,
    ]);
    DailyBalance::factory()->create([
        'date' => '2026-08-30',
        'cash_income' => 0,
        'cash_expense' => 40000,
        'cash_balance' => 60000,
        'non_cash_income' => 25000,
        'non_cash_expense' => 0,
        'non_cash_balance' => 25000,
    ]);
    /* A later day never leaks into the history of the day being read. */
    DailyBalance::factory()->create(['date' => '2026-08-31']);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('dailyBalanceHistory', 2)
                ->where('dailyBalanceHistory.0.date', '2026-08-30')
                ->where('dailyBalanceHistory.0.cashExpense', 40000)
                ->where('dailyBalanceHistory.0.cashBalance', 60000)
                ->where('dailyBalanceHistory.0.nonCashIncome', 25000)
                ->where('dailyBalanceHistory.1.date', '2026-08-29')
                ->where('dailyBalanceHistory.1.cashIncome', 100000)
                ->where('dailyBalance.cash', 60000)
                ->where('dailyBalance.nonCash', 25000),
        );
});

test('a finance shift without work hours has no time caption', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    AdminShift::query()->create([
        'key' => 'flexible',
        'name' => 'Shift Fleksibel',
        'starts_at' => null,
        'ends_at' => null,
        'is_active' => true,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('shifts.0.id', 'flexible')
                ->where('shifts.0.name', 'Shift Fleksibel')
                ->where('shifts.0.time', null),
        );
});

test('a payment taken by the cashier is read back as money in', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('moneyIn', 1)
                ->where('moneyIn.0.source', 'pos')
                ->where('moneyIn.0.category', 'Pembayaran Sisa/Lunas (Order Selesai)')
                ->where('moneyIn.0.amount', 150000)
                ->where('moneyIn.0.orderNo', $order->number)
                ->where('moneyIn.0.shift', 'Shift Pagi')
                ->where('moneyIn.0.updatedBy', null)
                ->where('moneyIn.0.updatedAt', null)
                ->where('moneyIn.0.id', 'pos-'.$order->number.'-TRX-1')
                ->where('moneyIn.0.transactionId', $order->transactions()->sole()->id)
                ->where('cashSummary.todayIn', 150000)
                ->has('orders', 1),
        );
});

test('hidden admins are absent from the roster but remain visible in transaction audit data', function () {
    $viewer = Admin::factory()->create(['is_owner' => true]);
    $morningShift = AdminShift::query()->where('key', 'morning')->firstOrFail();
    $hiddenCashier = Admin::factory()->create([
        'name' => 'Hidden Debug Cashier',
        'shift_id' => $morningShift->id,
        'is_hidden' => true,
    ]);
    paidOrder($hiddenCashier);

    $this->actingAs($viewer, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('moneyIn.0.recordedBy', $hiddenCashier->name)
                ->where('shifts.0.id', 'morning')
                ->where('shifts.0.cashier', ''),
        );
});

test('an owner can correct the payment channel of a settled POS transaction', function () {
    $recorder = Admin::factory()->create(['is_owner' => true]);
    $editor = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($recorder);
    $transaction = $order->transactions()->sole();

    expect($transaction->updated_by_admin_id)->toBeNull();

    $this->actingAs($editor, 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 150000,
            'channels' => [[
                'label' => 'QRIS',
                'amount' => 150000,
                'reference' => 'QR-REV-001',
            ]],
        ])
        ->assertRedirect(route('admin.finance.index', ['date' => '2026-08-30']))
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->channel_breakdown)->toBe([[
        'label' => 'QRIS',
        'amount' => 150000,
        'reference' => 'QR-REV-001',
    ]])
        ->and($transaction->recorded_by_admin_id)->toBe($recorder->id)
        ->and($transaction->updated_by_admin_id)->toBe($editor->id)
        ->and($order->refresh()->paid_amount)->toBe(150000)
        ->and($order->payment_method)->toBe('QRIS')
        ->and($order->status)->toBe('selesai')
        ->and(DailyBalance::query()->sole()->cash_income)->toBe(0)
        ->and(DailyBalance::query()->sole()->cash_balance)->toBe(0)
        ->and(DailyBalance::query()->sole()->non_cash_income)->toBe(150000)
        ->and(DailyBalance::query()->sole()->non_cash_balance)->toBe(150000);

    $lastEditor = Admin::factory()->create(['is_owner' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-08-30 11:45', 'Asia/Jakarta'));

    $this->actingAs($lastEditor, 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 150000,
            'channels' => [[
                'label' => 'QRIS',
                'amount' => 150000,
                'reference' => 'QR-REV-002',
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->recorded_by_admin_id)->toBe($recorder->id)
        ->and($transaction->updated_by_admin_id)->toBe($lastEditor->id);

    $this->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('moneyIn.0.recordedBy', $recorder->name)
                ->where('moneyIn.0.updatedBy', $lastEditor->name)
                ->where('moneyIn.0.updatedAt.date', '2026-08-30')
                ->where('moneyIn.0.updatedAt.time', '11.45'),
        );
});

test('an owner must choose a bank when correcting a transaction to debit', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner);
    $transaction = $order->transactions()->sole();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 150000,
            'channels' => [[
                'label' => 'Debit',
                'amount' => 150000,
                'provider' => '',
                'reference' => '',
            ]],
        ])
        ->assertSessionHasErrors('channels.0.provider');

    expect($transaction->refresh()->channel_breakdown)->toBe([
        ['label' => 'Tunai', 'amount' => 150000],
    ]);
});

test('an owner can choose a bank when correcting a transaction to debit', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner);
    $transaction = $order->transactions()->sole();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 150000,
            'channels' => [[
                'label' => 'Debit',
                'amount' => 150000,
                'provider' => 'BCA',
                'reference' => 'EDC-001',
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->channel_breakdown)->toBe([[
        'label' => 'Debit · BCA',
        'amount' => 150000,
        'reference' => 'EDC-001',
    ]])->and($order->refresh()->payment_method)->toBe('Debit · BCA');
});

test('an owner can correct a partial POS transaction amount', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'total' => 200000,
        'paid_amount' => 50000,
        'status' => 'proses',
    ]);
    $transaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'recorded_by_admin_id' => $owner->id,
        'amount' => 50000,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 50000]],
    ]);

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 75000,
            'channels' => [['label' => 'Tunai', 'amount' => 75000]],
        ])
        ->assertSessionHasNoErrors();

    expect($transaction->refresh()->amount)->toBe(75000)
        ->and($order->refresh()->paid_amount)->toBe(75000)
        ->and(DailyBalance::query()->sole()->cash_income)->toBe(75000)
        ->and(DailyBalance::query()->sole()->cash_balance)->toBe(75000);
});

test('a correction cannot make a settled order unpaid', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $transaction = paidOrder($owner)->transactions()->sole();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 100000,
            'channels' => [['label' => 'Tunai', 'amount' => 100000]],
        ])
        ->assertSessionHasErrors('amount');

    expect($transaction->refresh()->amount)->toBe(150000);
});

test('a staff member without update access cannot correct a POS transaction', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $transaction = paidOrder($owner)->transactions()->sole();

    $this->actingAs(financeStaff(['read' => true]), 'admin')
        ->patch(route('admin.finance.transactions.update', $transaction), [
            'amount' => 150000,
            'channels' => [['label' => 'QRIS', 'amount' => 150000]],
        ])
        ->assertForbidden();
});

test('an evening payment is filed on the day the outlet clock says', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner);

    /* Stored as the outlet's own wall clock, so no conversion is involved. */
    $evening = CarbonImmutable::parse('2026-08-30 22:30', 'Asia/Jakarta');
    $order->transactions()->update([
        'paid_at' => $evening->format('Y-m-d H:i:s'),
        /* Taken by a cashier on no shift, which the ledger reports as such. */
        'shift_name' => null,
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index', ['date' => '2026-08-30']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('moneyIn', 1)
                ->where('moneyIn.0.date', '2026-08-30')
                ->where('moneyIn.0.time', '22.30')
                ->where('moneyIn.0.shift', null)
                ->has('orders', 1),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index', ['date' => '2026-08-29']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('moneyIn', 0));
});

test('the categories a payment is filed under cannot be written by hand', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('incomeCategories', [
                    'Penjualan Produk',
                    'Sewa Tempat',
                    'Pendapatan Lain',
                ]),
        );

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'category' => 'Pembayaran Sisa/Lunas (Order Selesai)',
        ]))
        ->assertSessionHasErrors('category');
});

test('an expense is only ever filed as cash or non-cash', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('expenseMethods', ['Tunai', 'Non-Tunai'])
                ->where('paymentMethods', ['Tunai', 'QRIS', 'Kredit', 'Debit', 'Transfer', 'E-Money']),
        );

    $expense = fn (string $method): array => cashEntryPayload([
        'direction' => 'out',
        'category' => 'Pembelian Bahan',
        'method' => $method,
        'attachments' => [UploadedFile::fake()->image('nota-supplier.jpg')],
    ]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), $expense('QRIS'))
        ->assertSessionHasErrors('method');

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), $expense('Non-Tunai'))
        ->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Pembelian Bahan',
            'method' => 'Transfer',
        ]))
        ->assertSessionHasErrors('method');

    /* Money in still names the channel it arrived on. */
    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload(['method' => 'QRIS']))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload(['method' => 'Non-Tunai']))
        ->assertSessionHasErrors('method');

    expect($entry->refresh()->method)->toBe('Non-Tunai')
        ->and(DailyBalance::query()->sole()->non_cash_expense)->toBe(360000)
        ->and(DailyBalance::query()->sole()->cash_expense)->toBe(0);
});

test('an owner can record money in', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $today = now('Asia/Jakarta')->toDateString();

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload())
        ->assertRedirect(route('admin.finance.index', ['date' => $today]))
        ->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();

    expect($entry->direction)->toBe('in')
        ->and($entry->amount)->toBe(360000)
        ->and($entry->recorded_by_admin_id)->toBe($owner->id)
        ->and($entry->updated_by_admin_id)->toBeNull()
        ->and($entry->attachments()->count())->toBe(0)
        ->and($entry->reference)->toBe('TRX-PP-'.substr(str_replace('-', '', $today), 2).'-'.str_pad((string) $entry->id, 4, '0', STR_PAD_LEFT))
        ->and(DailyBalance::query()->sole()->cash_income)->toBe(360000)
        ->and(DailyBalance::query()->sole()->cash_balance)->toBe(360000)
        ->and(DailyBalance::query()->sole()->non_cash_income)->toBe(0);
});

test('an owner can record money on the selected finance date', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'entry_date' => '2026-08-27',
        ]))
        ->assertRedirect(route('admin.finance.index', ['date' => '2026-08-27']))
        ->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();

    expect($entry->entry_date->toDateString())->toBe('2026-08-27')
        ->and($entry->occurred_at->format('Y-m-d H:i'))
        ->toBe('2026-08-27 10:00')
        ->and($entry->reference)->toContain('TRX-PP-260827-')
        ->and(DailyBalance::query()->sole()->date->toDateString())
        ->toBe('2026-08-27');
});

test('money out is refused without its supporting document', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
        ]))
        ->assertSessionHasErrors('attachments');

    expect(CashEntry::query()->count())->toBe(0);
});

test('money out stores its supporting document', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachments' => [UploadedFile::fake()->image('nota-supplier.jpg')],
        ]))
        ->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();

    $attachment = $entry->attachments()->sole();

    expect($attachment->original_name)->toBe('nota-supplier.jpg')
        ->and($attachment->disk)->toBe(financeDisk())
        ->and($attachment->path)->toStartWith($entry->reference.'/')
        ->and($attachment->path)->toEndWith('.jpg')
        ->and(DailyBalance::query()->sole()->cash_expense)->toBe(360000)
        ->and(DailyBalance::query()->sole()->cash_balance)->toBe(-360000);
    Storage::assertExists($attachment->path);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', $attachment))
        ->assertOk();
});

test('manual money in can store multiple attachments on the configured default disk', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'attachments' => [
                UploadedFile::fake()->image('bukti-transfer.png'),
                UploadedFile::fake()->create('invoice.pdf', 64, 'application/pdf'),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();
    $attachments = $entry->attachments()->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('original_name')->all())->toBe([
            'bukti-transfer.png',
            'invoice.pdf',
        ])
        ->and($attachments->pluck('disk')->unique()->all())->toBe(['local']);

    foreach ($attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
    }

    config(['filesystems.default' => 's3']);
    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', $attachments->first()))
        ->assertOk();
});

test('an image attachment is flagged and served inline for the lightbox', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachments' => [UploadedFile::fake()->image('nota-supplier.jpg')],
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('moneyOut.0.attachments.0.isImage', true)
                ->where('moneyOut.0.attachments.0.name', 'nota-supplier.jpg'),
        );

    /* Inline, not an attachment: the lightbox has to render it in the page. */
    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', CashEntryAttachment::query()->sole()))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=nota-supplier.jpg');
});

test('a document attachment is not flagged and is still handed over', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachments' => [UploadedFile::fake()->create('struk-token.pdf', 64, 'application/pdf')],
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('moneyOut.0.attachments.0.isImage', false),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', CashEntryAttachment::query()->sole()))
        ->assertOk()
        ->assertDownload('struk-token.pdf');
});

test('a video attachment is refused', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachments' => [UploadedFile::fake()->create('bukti.mp4', 64, 'video/mp4')],
        ]))
        ->assertSessionHasErrors('attachments.0');

    expect(CashEntry::query()->count())->toBe(0);
});

test('a staff member without read access cannot download an attachment', function () {
    Storage::fake(financeDisk());
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();

    $this->actingAs(financeStaff(['read' => false]), 'admin')
        ->get(route('admin.finance.attachment', $entry->attachments()->sole()))
        ->assertForbidden();
});

test('an owner can change a recorded entry and append another document', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $previousAttachment = $entry->attachments()->sole();
    Storage::put($previousAttachment->path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'description' => 'Token listrik bulanan',
            'amount' => 500000,
            'method' => 'Non-Tunai',
            'attachments' => [UploadedFile::fake()->create('struk-token.pdf', 64, 'application/pdf')],
        ]))
        ->assertSessionHasNoErrors();

    $entry->refresh();

    expect($entry->category)->toBe('Operasional')
        ->and($entry->amount)->toBe(500000)
        ->and($entry->updated_by_admin_id)->toBe($owner->id)
        ->and($entry->attachments()->count())->toBe(2)
        ->and($entry->attachments()->where('original_name', 'struk-token.pdf')->exists())->toBeTrue()
        ->and(DailyBalance::query()->sole()->cash_expense)->toBe(0)
        ->and(DailyBalance::query()->sole()->cash_balance)->toBe(0)
        ->and(DailyBalance::query()->sole()->non_cash_expense)->toBe(500000)
        ->and(DailyBalance::query()->sole()->non_cash_balance)->toBe(-500000);
    Storage::assertExists($previousAttachment->path);
    Storage::assertExists(
        $entry->attachments()->where('original_name', 'struk-token.pdf')->firstOrFail()->path,
    );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('moneyOut.0.updatedBy', $owner->name),
        );
});

test('an entry keeps the document already on file when none is uploaded', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $attachment = $entry->attachments()->sole();
    Storage::put($attachment->path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'description' => 'Token listrik bulanan',
            'amount' => 500000,
            'method' => 'Non-Tunai',
        ]))
        ->assertSessionHasNoErrors();

    expect($entry->refresh()->attachments()->sole()->original_name)->toBe('nota-supplier.jpg');
    Storage::assertExists($attachment->path);
});

test('an owner can remove one stored attachment while keeping another', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $removedAttachment = $entry->attachments()->sole();
    $keptAttachment = CashEntryAttachment::factory()->for($entry)->create([
        'path' => $entry->reference.'/invoice.pdf',
        'original_name' => 'invoice.pdf',
    ]);
    Storage::put($removedAttachment->path, 'nota lama');
    Storage::put($keptAttachment->path, 'invoice');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'removed_attachment_ids' => [$removedAttachment->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($entry->attachments()->sole()->is($keptAttachment))->toBeTrue();
    Storage::assertMissing($removedAttachment->path);
    Storage::assertExists($keptAttachment->path);
});

test('the last attachment of money out cannot be removed without a replacement', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $attachment = $entry->attachments()->sole();
    Storage::put($attachment->path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'removed_attachment_ids' => [$attachment->id],
        ]))
        ->assertSessionHasErrors('attachments');

    expect($entry->attachments()->sole()->is($attachment))->toBeTrue();
    Storage::assertExists($attachment->path);
});

test('an attachment from another entry cannot be removed', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $otherEntry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $otherAttachment = $otherEntry->attachments()->sole();

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'removed_attachment_ids' => [$otherAttachment->id],
        ]))
        ->assertSessionHasErrors('removed_attachment_ids.0');

    expect($otherEntry->attachments()->sole()->is($otherAttachment))->toBeTrue();
});

test('an owner can soft delete an entry while preserving its document', function () {
    Storage::fake(financeDisk());
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->withDailyBalance()->create();
    $attachment = $entry->attachments()->sole();
    Storage::put($attachment->path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.destroy', $entry))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($entry);
    expect(CashEntry::withTrashed()->sole()->deleted_by_admin_id)->toBe($owner->id)
        ->and(CashEntry::withTrashed()->sole()->deletedBy->is($owner))->toBeTrue()
        ->and(CashEntry::withTrashed()->count())->toBe(1)
        ->and(CashEntryAttachment::query()->count())->toBe(1)
        ->and(DailyBalance::query()->count())->toBe(0);
    Storage::assertExists($attachment->path);
    $this->get(route('admin.finance.attachment', $attachment))->assertNotFound();
});

test('deleting a POS transaction reopens its order and rebuilds later balances', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create([
        'total' => 150000,
        'paid_amount' => 150000,
        'payment_method' => 'Tunai + QRIS',
        'status' => 'selesai',
        'invoice_number' => 'ZW-KEEP-001',
    ]);
    $deletedTransaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'reference' => $order->number.'-TRX-1',
        'type' => 'Pembayaran Sebagian',
        'amount' => 50000,
        'channel_breakdown' => [['label' => 'Tunai', 'amount' => 50000]],
        'paid_at' => now()->subDay(),
    ]);
    $remainingTransaction = OrderTransaction::factory()->withDailyBalance()->create([
        'order_id' => $order->id,
        'reference' => $order->number.'-TRX-2',
        'type' => 'Pembayaran Lunas',
        'amount' => 100000,
        'channel_breakdown' => [['label' => 'QRIS', 'amount' => 100000]],
        'paid_at' => now(),
    ]);

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.transactions.destroy', $deletedTransaction))
        ->assertRedirect(route('admin.finance.index', ['date' => now()->subDay()->toDateString()]))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($deletedTransaction);
    expect(OrderTransaction::withTrashed()->findOrFail($deletedTransaction->id)->deleted_by_admin_id)->toBe($owner->id)
        ->and($order->refresh())
        ->paid_amount->toBe(100000)
        ->payment_method->toBe('QRIS')
        ->status->toBe('pelunasan')
        ->invoice_number->toBe('ZW-KEEP-001')
        ->and($remainingTransaction->refresh()->type)->toBe('Pembayaran Sebagian')
        ->and(DailyBalance::query()->count())->toBe(1)
        ->and(DailyBalance::query()->sole()->date->toDateString())->toBe(now()->toDateString())
        ->and(DailyBalance::query()->sole()->cash_income)->toBe(0)
        ->and(DailyBalance::query()->sole()->non_cash_income)->toBe(100000)
        ->and(DailyBalance::query()->sole()->non_cash_balance)->toBe(100000);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.pos.payments.store', $order), [
            'intent' => 'settlement',
            'discount' => 0,
            'amount' => 50000,
            'channels' => [['method' => 'Tunai', 'amount' => 50000]],
        ])
        ->assertSessionHasNoErrors();

    expect(OrderTransaction::withTrashed()->where('order_id', $order->id)->count())->toBe(3)
        ->and(OrderTransaction::query()->latest('id')->firstOrFail()->reference)
        ->toBe($order->number.'-TRX-3');
});

test('financial records are mutable through H-30 and locked on H-31', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = Order::factory()->create(['total' => 200000, 'paid_amount' => 50000]);
    $allowedTransaction = OrderTransaction::factory()->create([
        'order_id' => $order->id,
        'paid_at' => now()->subDays(30),
    ]);
    $lockedTransaction = OrderTransaction::factory()->create([
        'order_id' => $order->id,
        'paid_at' => now()->subDays(31),
    ]);
    $allowedEntry = CashEntry::factory()->withDailyBalance()->create([
        'entry_date' => now()->subDays(30),
        'occurred_at' => now()->subDays(30),
    ]);
    $lockedEntry = CashEntry::factory()->withDailyBalance()->create([
        'entry_date' => now()->subDays(31),
        'occurred_at' => now()->subDays(31),
    ]);

    $payload = [
        'amount' => 20000,
        'channels' => [['label' => 'Tunai', 'amount' => 20000]],
    ];

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.transactions.update', $allowedTransaction), $payload)
        ->assertSessionHasNoErrors();
    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.transactions.update', $lockedTransaction), $payload)
        ->assertUnprocessable();
    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $allowedEntry), cashEntryPayload())
        ->assertSessionHasNoErrors();
    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $lockedEntry), cashEntryPayload())
        ->assertUnprocessable();

    expect($allowedTransaction->refresh()->amount)->toBe(20000)
        ->and($lockedTransaction->refresh()->amount)->toBe(20000)
        ->and($allowedEntry->refresh()->updated_by_admin_id)->toBe($owner->id)
        ->and($lockedEntry->refresh()->updated_by_admin_id)->toBeNull();

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.destroy', $allowedEntry))
        ->assertSessionHasNoErrors();
    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.destroy', $lockedEntry))
        ->assertUnprocessable();

    $this->assertSoftDeleted($allowedEntry);
    $this->assertNotSoftDeleted($lockedEntry);
    expect(CashEntry::withTrashed()->findOrFail($allowedEntry->id)->deleted_by_admin_id)->toBe($owner->id)
        ->and($lockedEntry->refresh()->deleted_by_admin_id)->toBeNull();
});

test('a staff member without update or delete access cannot change an entry', function () {
    $entry = CashEntry::factory()->withDailyBalance()->create();
    $staff = financeStaff(['read' => true, 'create' => true]);

    $this->actingAs($staff, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload())
        ->assertForbidden();

    $this->actingAs($staff, 'admin')
        ->delete(route('admin.finance.destroy', $entry))
        ->assertForbidden();

    $this->actingAs($staff, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('capabilities.create', true)
                ->where('capabilities.update', false)
                ->where('capabilities.delete', false),
        );
});

test('the finance module links to its live page from the sidebar', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('modules.4.key', 'finance')
                ->where('modules.4.active', true)
                ->where('modules.4.enabled', true)
                ->where('modules.4.href', route('admin.finance.index', absolute: false)),
        );
});

test('a payment taken after midnight is filed on the day the outlet clock says', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner);

    /* The hour that used to vanish: 03.17 fell outside the shifted window. */
    $order->transactions()->update([
        'paid_at' => '2026-08-30 03:17:00',
    ]);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index', ['date' => '2026-08-30']))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('moneyIn', 1)
                ->where('moneyIn.0.date', '2026-08-30')
                ->where('moneyIn.0.time', '03.17')
                ->where('cashSummary.todayIn', 150000)
                ->has('orders', 1),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index', ['date' => '2026-08-29']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('moneyIn', 0)
            ->has('orders', 0));
});

/*
 * The shift is whoever wrote the row was rostered onto, stamped on it at write
 * time. A cash entry from an admin on no shift stays unattributed rather than
 * being credited to whichever shift happened to cover the hour.
 */
test('a hand-written entry takes the shift of the admin who wrote it', function () {
    $shift = AdminShift::query()->where('key', 'evening')->firstOrFail();
    $rostered = Admin::factory()->create(['is_owner' => true, 'shift_id' => $shift->id]);
    $unrostered = Admin::factory()->create(['is_owner' => true, 'shift_id' => null]);

    foreach ([$rostered, $unrostered] as $admin) {
        $this->actingAs($admin, 'admin')
            ->post(route('admin.finance.store'), [
                'direction' => 'in',
                'category' => 'Penjualan Produk',
                'description' => 'Parfum mobil',
                'amount' => 50000,
                'method' => 'Tunai',
            ])
            ->assertRedirect();
    }

    expect(CashEntry::query()->where('recorded_by_admin_id', $rostered->id)->firstOrFail())
        ->shift_name->toBe('Shift Sore')
        ->and(CashEntry::query()->where('recorded_by_admin_id', $unrostered->id)->firstOrFail())
        ->shift_name->toBeNull();
});

test('a scheduled hand-written entry requires a valid choice during overlapping shifts', function () {
    $this->travelTo('2026-08-30 14:30:00');
    $overlappingShift = AdminShift::query()->create([
        'key' => 'afternoon',
        'name' => 'Shift Siang',
        'starts_at' => '14:00',
        'ends_at' => '20:00',
        'is_active' => true,
    ]);
    $admin = Admin::factory()->create([
        'is_owner' => true,
        'shift_mode' => 'schedule',
        'shift_id' => null,
    ]);
    $payload = [
        'direction' => 'in',
        'category' => 'Penjualan Produk',
        'description' => 'Parfum mobil',
        'amount' => 50000,
        'method' => 'Tunai',
    ];

    $this->actingAs($admin, 'admin')
        ->post(route('admin.finance.store'), $payload)
        ->assertSessionHasErrors('transaction_shift_id');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.finance.store'), [
            ...$payload,
            'transaction_shift_id' => $overlappingShift->id,
        ])
        ->assertSessionHasNoErrors();

    expect(CashEntry::query()->sole()->shift_name)->toBe('Shift Siang');
});
