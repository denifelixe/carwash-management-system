<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\CashEntry;
use App\Support\Admin\AdminModuleActions;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 10:00'));
});

function sensitiveFinanceStaff(bool $sensitive = false): Admin
{
    $role = AdminRole::query()->create([
        'key' => 'sensitive_finance_'.uniqid(),
        'name' => 'Sensitive finance test',
        'is_active' => true,
    ]);
    foreach (['finance', 'reports'] as $module) {
        $role->modules()->attach(AdminModule::query()->where('key', $module)->firstOrFail(), [
            'can_read' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
            'additional_actions' => json_encode($module === 'finance' && $sensitive ? [AdminModuleActions::VIEW_SENSITIVE_DATA] : []),
        ]);
    }

    return Admin::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function sensitiveEntryPayload(array $overrides = []): array
{
    return array_merge([
        'direction' => 'in', 'category' => 'Penjualan Produk', 'description' => 'Rahasia supplier',
        'amount' => 75000, 'method' => 'Tunai', 'is_sensitive' => true,
    ], $overrides);
}

test('sensitive entries are saved by permitted staff in both directions', function (string $direction): void {
    Storage::fake('local');
    $payload = sensitiveEntryPayload($direction === 'out' ? [
        'direction' => 'out', 'category' => 'Pembelian Bahan',
        'attachments' => [UploadedFile::fake()->image('nota.jpg')],
    ] : []);

    $this->actingAs(sensitiveFinanceStaff(true), 'admin')->post(route('admin.finance.store'), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    $entry = CashEntry::query()->sole();
    expect($entry->is_sensitive)->toBeTrue()->and($entry->direction)->toBe($direction);
    if ($direction === 'out') {
        $attachment = $entry->attachments()->sole();
        Storage::disk($attachment->disk)->assertExists($attachment->path);
    }
})->with(['income' => 'in', 'expense' => 'out']);

test('staff cannot enable sensitivity through a forged create or update request', function (): void {
    $staff = sensitiveFinanceStaff();
    $ordinary = CashEntry::factory()->create();
    $this->actingAs($staff, 'admin')->post(route('admin.finance.store'), sensitiveEntryPayload())->assertForbidden();
    $this->patch(route('admin.finance.update', $ordinary), sensitiveEntryPayload())->assertForbidden();
    expect(CashEntry::query()->count())->toBe(1)->and($ordinary->fresh()->is_sensitive)->toBeFalse();
});

test('invalid sensitivity flags are rejected without writing a record', function (): void {
    $this->actingAs(sensitiveFinanceStaff(true), 'admin')
        ->post(route('admin.finance.store'), sensitiveEntryPayload(['is_sensitive' => 'invalid']))
        ->assertSessionHasErrors('is_sensitive');
    expect(CashEntry::query()->count())->toBe(0);
});

test('sensitive history is redacted while balances and every shift aggregate include it', function (): void {
    CashEntry::factory()->withDailyBalance()->create(['amount' => 100000]);
    CashEntry::factory()->sensitive()->withDailyBalance()->create(['amount' => 75000, 'description' => 'Secret income', 'reference' => 'SECRET-IN']);
    CashEntry::factory()->sensitive()->moneyOut()->withDailyBalance()->create(['amount' => 25000, 'shift_name' => null, 'description' => 'Secret expense', 'reference' => 'SECRET-OUT']);
    CashEntry::factory()->sensitive()->withDailyBalance()->create(['amount' => 5000, 'shift_name' => 'Retired']);

    $this->actingAs(sensitiveFinanceStaff(), 'admin')->get(route('admin.finance.index'))->assertOk()
        ->assertDontSee('Secret income')->assertDontSee('Secret expense')
        ->assertDontSee('SECRET-IN')->assertDontSee('SECRET-OUT')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('moneyIn', 3)->has('moneyOut', 1)
            ->where('moneyIn', fn ($rows): bool => collect($rows)->where('isRedacted', true)->count() === 2)
            ->where('moneyOut.0.isRedacted', true)
            ->where('moneyOut.0.ref', '')
            ->where('moneyOut.0.description', '')
            ->where('moneyOut.0.category', '')
            ->where('moneyOut.0.amount', 0)
            ->where('moneyOut.0.recordedBy', '')
            ->has('moneyOut.0.attachments', 0)
            ->has('moneyOut.0.channelBreakdown', 0)
            ->missing('moneyOut.0.updatedBy')
            ->missing('moneyOut.0.transferReference')
            ->where('capabilities.view_sensitive_data', false)
            ->where('dailyBalance.cash', 155000)
            ->where('ledgerSummary.all.moneyIn', 180000)
            ->where('ledgerSummary.all.moneyOut', 25000)
            ->where('ledgerSummary.all.incomeCount', 3)
            ->where('ledgerSummary.all.expenseCount', 1)
            ->where('ledgerSummary.all.channels.Tunai.income', 180000)
            ->where('ledgerSummary.all.channels.Tunai.expense', 25000)
            ->where('ledgerSummary.morning.moneyIn', 175000)
            ->where('ledgerSummary.tanpa-shift.moneyIn', 5000)
            ->where('ledgerSummary.tanpa-shift.moneyOut', 25000));
});

test('sensitive permission and owner access reveal entries but still require CRUD permissions', function (): void {
    $entry = CashEntry::factory()->sensitive()->create();
    $staff = sensitiveFinanceStaff(true);
    $this->actingAs($staff, 'admin')->get(route('admin.finance.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('moneyIn', 1)->where('moneyIn.0.isSensitive', true)->where('moneyIn.0.ref', $entry->reference)->missing('moneyIn.0.isRedacted'));
    $staff->role->modules()->updateExistingPivot(AdminModule::query()->where('key', 'finance')->firstOrFail()->id, ['can_update' => false]);
    $this->patch(route('admin.finance.update', $entry), sensitiveEntryPayload(['is_sensitive' => false]))->assertForbidden();

    $this->actingAs(Admin::factory()->create(['is_owner' => true]), 'admin')->get(route('admin.finance.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('moneyIn', 1)->where('capabilities.view_sensitive_data', true));
});

test('permitted staff can change the sensitive flag through editing', function (bool $sensitive): void {
    $entry = CashEntry::factory()->withDailyBalance()->create(['is_sensitive' => ! $sensitive]);
    $this->actingAs(sensitiveFinanceStaff(true), 'admin')->patch(route('admin.finance.update', $entry), sensitiveEntryPayload(['is_sensitive' => $sensitive]))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($entry->fresh()->is_sensitive)->toBe($sensitive);
})->with(['make sensitive' => true, 'make ordinary' => false]);

test('permitted staff can read sensitive attachments and delete sensitive entries', function (): void {
    Storage::fake('local');
    $entry = CashEntry::factory()->sensitive()->moneyOut()->withDailyBalance()->create();
    $attachment = $entry->attachments()->sole();
    Storage::disk($attachment->disk)->put($attachment->path, 'supporting document');

    $this->actingAs(sensitiveFinanceStaff(true), 'admin')->get(route('admin.finance.attachment', $attachment))
        ->assertOk();
    $this->delete(route('admin.finance.destroy', $entry))->assertRedirect();
    $this->assertSoftDeleted($entry);
});

test('sensitive attachments edits and deletion reject direct requests without permission', function (): void {
    $entry = CashEntry::factory()->sensitive()->moneyOut()->create();
    $attachment = $entry->attachments()->sole();
    $this->actingAs(sensitiveFinanceStaff(), 'admin')->get(route('admin.finance.attachment', $attachment))->assertForbidden();
    $this->patch(route('admin.finance.update', $entry), sensitiveEntryPayload(['is_sensitive' => false]))->assertForbidden();
    $this->delete(route('admin.finance.destroy', $entry))->assertForbidden();
    expect($entry->fresh()->deleted_at)->toBeNull()->and($entry->fresh()->is_sensitive)->toBeTrue();
});

test('hidden sensitive duplicates do not appear in duplicate warnings', function (): void {
    CashEntry::factory()->sensitive()->create(['amount' => 75000]);
    $this->actingAs(sensitiveFinanceStaff(), 'admin')->post(route('admin.finance.store'), sensitiveEntryPayload(['is_sensitive' => false]))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(CashEntry::query()->count())->toBe(2);
});

test('report history pagination and exports hide sensitive rows while report totals include them', function (bool $canView): void {
    CashEntry::factory()->create(['amount' => 100000, 'description' => 'Public entry']);
    CashEntry::factory()->sensitive()->count(26)->create(['amount' => 1000, 'description' => 'Hidden supplier']);
    $staff = sensitiveFinanceStaff($canView);
    $query = ['from' => '2026-10-11', 'to' => '2026-10-11'];
    $this->actingAs($staff, 'admin')->get(route('admin.reports.index', $query))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('financeSummary.moneyIn', 126000));
    $this->get(route('admin.reports.index', $query), [
        'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'admin/Reports', 'X-Inertia-Partial-Data' => 'financeLog',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
    ])->assertOk()->assertJsonCount($canView ? 25 : 1, 'props.financeLog.data')
        ->assertJsonPath('props.financeLog.meta.total', $canView ? 27 : 1);
    $csv = $this->get(route('admin.reports.finance.export', $query))->assertOk()->streamedContent();
    expect($csv)->toContain('Public entry');
    if ($canView) {
        expect($csv)->toContain('Hidden supplier');
    } else {
        expect($csv)->not->toContain('Hidden supplier');
    }
})->with(['restricted' => false, 'permitted' => true]);
