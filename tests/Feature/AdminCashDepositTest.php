<?php

use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\DailyBalance;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\OrderQueries;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 14:00:00'));
    Storage::fake((string) config('filesystems.default'));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function depositPayload(array $overrides = []): array
{
    return [
        'entry_date' => '2026-09-24',
        'entry_time' => '13:30',
        'description' => 'Setor tunai ke BCA',
        'amount' => 500000,
        'attachments' => [UploadedFile::fake()->image('slip-setoran.jpg')],
        ...$overrides,
    ];
}

/** @return array{0: CashEntry, 1: CashEntry} */
function depositPair(): array
{
    $entries = CashEntry::query()->with('attachments')->orderBy('id')->get();

    expect($entries)->toHaveCount(2);

    return [$entries->firstWhere('direction', 'out'), $entries->firstWhere('direction', 'in')];
}

test('one setor tunai writes a Tunai outflow and a Setor Tunai inflow with the same details', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.deposits.store'), depositPayload())
        ->assertRedirect(route('admin.finance.index', ['date' => '2026-09-24']))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Setor tunai berhasil dicatat.');

    [$out, $in] = depositPair();

    expect($out)
        ->category->toBe('Setor Tunai')
        ->method->toBe('Tunai')
        ->amount->toBe(500000)
        ->description->toBe('Setor tunai ke BCA')
        ->and($in)
        ->category->toBe('Setor Tunai')
        ->method->toBe('Setor Tunai')
        ->amount->toBe(500000)
        ->description->toBe('Setor tunai ke BCA')
        ->and($out->occurred_at->format('Y-m-d H:i'))->toBe('2026-09-24 13:30')
        ->and($in->occurred_at->format('Y-m-d H:i'))->toBe('2026-09-24 13:30')
        ->and($out->transfer_reference)->not->toBeNull()->toBe($in->transfer_reference)
        ->and($out->reference)->toBe('TRX-ST-260924-0001')
        ->and($in->reference)->toBe('TRX-ST-260924-0002')
        ->and($out->attachments->pluck('original_name')->all())->toBe(['slip-setoran.jpg'])
        ->and($in->attachments->pluck('original_name')->all())->toBe(['slip-setoran.jpg'])
        ->and($out->attachments->first()->path)->not->toBe($in->attachments->first()->path);

    Storage::disk((string) config('filesystems.default'))
        ->assertExists([$out->attachments->first()->path, $in->attachments->first()->path]);

    /* Cash down, non-cash up. */
    expect(DailyBalance::query()->where('date', '2026-09-24')->sole())
        ->cash_expense->toBe(500000)
        ->non_cash_income->toBe(500000)
        ->cash_income->toBe(0)
        ->non_cash_expense->toBe(0);
});

test('moving a deposit to another day gives both halves new daily numbers', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $this->actingAs($owner, 'admin')->post(route('admin.finance.deposits.store'), depositPayload());
    [$out] = depositPair();

    $this->patch(route('admin.finance.update', $out), [
        'entry_date' => '2026-09-23',
        'entry_time' => '12:00',
        'category' => 'Setor Tunai',
        'method' => 'Tunai',
        'description' => 'Setor tunai ke BCA',
        'amount' => 500000,
    ])->assertSessionHasNoErrors();

    [$out, $in] = depositPair();
    expect($out->reference)->toBe('TRX-ST-260923-0001')
        ->and($in->reference)->toBe('TRX-ST-260923-0002')
        ->and($out->attachments->first()->path)->toContain('TRX-ST-260924-0001')
        ->and($in->attachments->first()->path)->toContain('TRX-ST-260924-0002');
});

test('both halves show in the ledger and count in the totals', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')->post(route('admin.finance.deposits.store'), depositPayload());

    $this->get(route('admin.finance.index', ['date' => '2026-09-24']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('moneyOut.0.category', 'Setor Tunai')
            ->where('moneyOut.0.method', 'Tunai')
            ->where('moneyOut.0.transferReference', fn (?string $reference): bool => $reference !== null)
            ->where('moneyIn.0.method', 'Setor Tunai'));

    $this->get(route('admin.reports.index', ['from' => '2026-09-24', 'to' => '2026-09-24']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('financeSummary.moneyIn', 500000)
            ->where('financeSummary.moneyOut', 500000));
});

test('a setor tunai needs its proof and stays off the normal entry form', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);

    $this->actingAs($owner, 'admin')
        ->post(route('admin.finance.deposits.store'), depositPayload(['attachments' => []]))
        ->assertSessionHasErrors(['attachments' => 'Setor tunai wajib menyertakan bukti setoran.']);

    $this->post(route('admin.finance.store'), [
        'entry_date' => '2026-09-24', 'entry_time' => '13:00', 'direction' => 'in',
        'category' => 'Setor Tunai', 'description' => 'x', 'amount' => 1000, 'method' => 'Setor Tunai',
    ])->assertSessionHasErrors(['category', 'method']);

    expect(CashEntry::query()->count())->toBe(0)
        ->and(FinanceCategories::income())->toContain('Setor Tunai')
        ->and(FinanceCategories::expense())->toContain('Setor Tunai')
        ->and(FinanceCategories::recordable('in'))->not->toContain('Setor Tunai')
        ->and(FinanceCategories::recordable('out'))->not->toContain('Setor Tunai')
        ->and(OrderQueries::PAYMENT_METHODS)->not->toContain('Setor Tunai');
});

test('editing either half updates both, proofs included', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $this->actingAs($owner, 'admin')->post(route('admin.finance.deposits.store'), depositPayload());
    [$out, $in] = depositPair();

    $this->patch(route('admin.finance.update', $in), [
        'entry_date' => '2026-09-24',
        'entry_time' => '12:00',
        'category' => 'Setor Tunai',
        'method' => 'Setor Tunai',
        'description' => 'Setor tunai ke Mandiri',
        'amount' => 450000,
        'removed_attachment_ids' => [$in->attachments->first()->id],
        'attachments' => [UploadedFile::fake()->create('bukti-baru.pdf', 20, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    [$out, $in] = depositPair();

    foreach ([$out, $in] as $entry) {
        expect($entry)
            ->amount->toBe(450000)
            ->description->toBe('Setor tunai ke Mandiri')
            ->and($entry->occurred_at->format('H:i'))->toBe('12:00')
            ->and($entry->attachments->pluck('original_name')->all())->toBe(['bukti-baru.pdf']);
    }

    expect($out->method)->toBe('Tunai')
        ->and(DailyBalance::query()->where('date', '2026-09-24')->sole())
        ->cash_expense->toBe(450000)
        ->non_cash_income->toBe(450000);

    /* The pair keeps its category and method. */
    $this->patch(route('admin.finance.update', $out), [
        'entry_date' => '2026-09-24', 'entry_time' => '12:00', 'category' => 'Operasional',
        'method' => 'Non-Tunai', 'description' => 'x', 'amount' => 1,
    ])->assertSessionHasErrors(['category', 'method']);
});

test('deleting either half deletes the pair and restores the balance', function () {
    $owner = Admin::factory()->create(['is_owner' => true]);
    $this->actingAs($owner, 'admin')->post(route('admin.finance.deposits.store'), depositPayload());
    [$out] = depositPair();

    $this->delete(route('admin.finance.destroy', $out))->assertSessionHasNoErrors();

    expect(CashEntry::query()->count())->toBe(0)
        ->and(CashEntry::onlyTrashed()->count())->toBe(2)
        ->and(DailyBalance::query()->where('date', '2026-09-24')->value('cash_expense') ?? 0)->toBe(0)
        ->and(DailyBalance::query()->where('date', '2026-09-24')->value('non_cash_income') ?? 0)->toBe(0);
});

test('the page offers the setor tunai button and keeps a pair read-only where it must', function () {
    expect(file_get_contents(resource_path('js/pages/admin/Finance.vue')))
        ->toContain('@click="openDeposit"')
        ->toContain('storeCashDeposit.url()')
        ->toContain(':disabled="isEditingTransfer"')
        ->toContain("workflow.addMoneyOut(pair(sequence, 'Tunai'));")
        ->toContain('workflow.addMoneyIn(pair(sequence + 1, CASH_DEPOSIT));')
        // The Kanal Keuangan table lists Setor Tunai under Non-Tunai.
        ->toContain('[...props.paymentMethods, CASH_DEPOSIT].map(')
        // Both actions sit together on the right of the ledger header.
        ->toContain('class="flex flex-wrap items-center gap-2"');
});
