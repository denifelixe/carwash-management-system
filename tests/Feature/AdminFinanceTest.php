<?php

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminWorkShift;
use App\Models\CashEntry;
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
        'direction' => 'in',
        'category' => 'Penjualan Produk',
        'description' => 'Penjualan parfum mobil 6 botol',
        'amount' => 360000,
        'method' => 'Tunai',
    ], $overrides);
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
                ->has('shifts', 2)
                ->where('shifts.0.id', 'morning')
                ->where('shifts.0.time', '08.00 - 16.00')
                ->has('moneyIn', 0)
                ->has('moneyOut', 0),
        );
});

test('a finance shift without work hours has no time caption', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    AdminWorkShift::query()->create([
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
                ->where('moneyIn.0.id', 'pos-'.$order->number.'-TRX-1')
                ->where('cashSummary.todayIn', 150000)
                ->has('orders', 1),
        );
});

test('an evening payment is filed on the day the outlet clock says', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $order = paidOrder($owner);

    /* Stored as the outlet's own wall clock, so no conversion is involved. */
    $evening = CarbonImmutable::parse('2026-08-30 22:30', 'Asia/Jakarta');
    $order->transactions()->update([
        'paid_at' => $evening->format('Y-m-d H:i:s'),
        /* Left unrecorded, so the shift is worked out from the clock instead. */
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
                ->where('moneyIn.0.shift', 'Shift Sore')
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
        ->and($entry->attachment_path)->toBeNull()
        ->and($entry->reference)->toBe('TRX-PP-'.substr(str_replace('-', '', $today), 2).'-'.str_pad((string) $entry->id, 4, '0', STR_PAD_LEFT));
});

test('money out is refused without its supporting document', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
        ]))
        ->assertSessionHasErrors('attachment');

    expect(CashEntry::query()->count())->toBe(0);
});

test('money out stores its supporting document', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachment' => UploadedFile::fake()->image('nota-supplier.jpg'),
        ]))
        ->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();

    expect($entry->attachment_name)->toBe('nota-supplier.jpg');
    Storage::disk('local')->assertExists($entry->attachment_path);

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', $entry))
        ->assertOk();
});

test('an image attachment is flagged and served inline for the lightbox', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachment' => UploadedFile::fake()->image('nota-supplier.jpg'),
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('moneyOut.0.attachmentIsImage', true)
                ->where('moneyOut.0.attachment.name', 'nota-supplier.jpg'),
        );

    /* Inline, not an attachment: the lightbox has to render it in the page. */
    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', CashEntry::query()->sole()))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=nota-supplier.jpg');
});

test('a document attachment is not flagged and is still handed over', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachment' => UploadedFile::fake()->create('struk-token.pdf', 64, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('moneyOut.0.attachmentIsImage', false),
        );

    $this->actingAs($owner, 'admin')
        ->get(route('admin.finance.attachment', CashEntry::query()->sole()))
        ->assertOk()
        ->assertDownload('struk-token.pdf');
});

test('a video attachment is refused', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.store'), cashEntryPayload([
            'direction' => 'out',
            'category' => 'Pembelian Bahan',
            'attachment' => UploadedFile::fake()->create('bukti.mp4', 64, 'video/mp4'),
        ]))
        ->assertSessionHasErrors('attachment');

    expect(CashEntry::query()->count())->toBe(0);
});

test('a staff member without read access cannot download an attachment', function () {
    Storage::fake('local');
    $entry = CashEntry::factory()->moneyOut()->create();

    $this->actingAs(financeStaff(['read' => false]), 'admin')
        ->get(route('admin.finance.attachment', $entry))
        ->assertForbidden();
});

test('an owner can change a recorded entry and replace its document', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->create();
    Storage::disk('local')->put($entry->attachment_path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'description' => 'Token listrik bulanan',
            'amount' => 500000,
            'method' => 'QRIS',
            'attachment' => UploadedFile::fake()->create('struk-token.pdf', 64, 'application/pdf'),
        ]))
        ->assertSessionHasNoErrors();

    $entry->refresh();

    expect($entry->category)->toBe('Operasional')
        ->and($entry->amount)->toBe(500000)
        ->and($entry->attachment_name)->toBe('struk-token.pdf');
    Storage::disk('local')->assertMissing('finance-attachments/2026/08/nota-supplier.jpg');
    Storage::disk('local')->assertExists($entry->attachment_path);
});

test('an entry keeps the document already on file when none is uploaded', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->create();
    Storage::disk('local')->put($entry->attachment_path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->patch(route('admin.finance.update', $entry), cashEntryPayload([
            'category' => 'Operasional',
            'description' => 'Token listrik bulanan',
            'amount' => 500000,
            'method' => 'QRIS',
        ]))
        ->assertSessionHasNoErrors();

    expect($entry->refresh()->attachment_name)->toBe('nota-supplier.jpg');
    Storage::disk('local')->assertExists($entry->attachment_path);
});

test('an owner can delete an entry along with its document', function () {
    Storage::fake('local');
    $owner = Admin::factory()->create(['is_owner' => true]);
    $entry = CashEntry::factory()->moneyOut()->create();
    Storage::disk('local')->put($entry->attachment_path, 'nota lama');

    $this->actingAs($owner, 'admin')
        ->delete(route('admin.finance.destroy', $entry))
        ->assertSessionHasNoErrors();

    expect(CashEntry::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing('finance-attachments/2026/08/nota-supplier.jpg');
});

test('a staff member without update or delete access cannot change an entry', function () {
    $entry = CashEntry::factory()->create();
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
