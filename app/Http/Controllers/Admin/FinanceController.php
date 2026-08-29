<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCashEntryRequest;
use App\Http\Requests\Admin\UpdateCashEntryRequest;
use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\Order;
use App\Support\Admin\AdminShell;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\FinancePresenter;
use App\Support\Admin\FinanceQueries;
use App\Support\Admin\FinanceReference;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Money in and money out for daily operations (BR-10).
 *
 * Payments the cashier accepted are read straight from their order transaction
 * rather than copied here, so the ledger cannot drift from the till. Everything
 * else — a product sale, a supplier bill — is written by hand, and outgoing
 * money only saves once its supporting document is attached.
 */
class FinanceController extends Controller
{
    private const ATTACHMENT_DISK = 'local';

    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.finance.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $today = CarbonImmutable::now()->startOfDay();
        $selectedDate = DateFilter::resolve($request->query('date')) ?: $today->toDateString();

        ['moneyIn' => $moneyIn, 'moneyOut' => $moneyOut] = FinanceQueries::ledgerForDate($selectedDate);

        return Inertia::render('admin/Finance', [
            ...$adminShell->props($admin, 'Keuangan', 'finance'),
            'moneyIn' => $moneyIn,
            'moneyOut' => $moneyOut,
            'filters' => OrderQueries::filters($selectedDate, $today),
            /* Only what may be written by hand: POS categories are derived. */
            'incomeCategories' => FinanceCategories::recordable('in'),
            'expenseCategories' => FinanceCategories::recordable('out'),
            'cashSummary' => FinanceQueries::cashSummary($moneyIn, $moneyOut),
            'paymentMethods' => OrderQueries::PAYMENT_METHODS,
            'shifts' => FinanceQueries::shiftSummary($moneyIn, $moneyOut, $selectedDate),
            'orders' => FinanceQueries::ordersForDate($selectedDate)
                ->map(fn (Order $order): array => OrderPresenter::order($order))
                ->all(),
            'capabilities' => [
                'create' => Gate::allows('admin.finance.create'),
                'update' => Gate::allows('admin.finance.update'),
                'delete' => Gate::allows('admin.finance.delete'),
            ],
        ]);
    }

    public function store(StoreCashEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $admin->loadMissing('workShift');
        /* Both read off the outlet clock, so the entry_date is the day the
         * till was actually open. */
        $occurredAt = CarbonImmutable::now();
        $entryDate = $occurredAt->toDateString();

        $entry = new CashEntry([
            'direction' => $data['direction'],
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'recorded_by_admin_id' => $admin->getKey(),
            'shift_name' => $admin->workShift?->name,
            'entry_date' => $entryDate,
            'occurred_at' => $occurredAt,
            /* Placeholder: the reference is only stable once the row has an ID. */
            'reference' => 'TRX-'.$occurredAt->format('YmdHisu'),
        ]);

        $entry->fill($this->attachmentAttributes($request->file('attachment')))->save();

        $entry->update([
            'reference' => FinanceReference::make($data['category'], $entryDate, $entry->id),
        ]);

        return to_route('admin.finance.index', ['date' => $entryDate])
            ->with('success', 'Catatan keuangan berhasil disimpan.');
    }

    public function update(UpdateCashEntryRequest $request, CashEntry $cashEntry): RedirectResponse
    {
        $data = $request->validated();
        $attachment = $request->file('attachment');
        $previousPath = $cashEntry->attachment_path;

        $cashEntry->fill([
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => FinanceReference::make(
                $data['category'],
                $cashEntry->entry_date->toDateString(),
                $cashEntry->id,
            ),
        ]);

        if ($attachment instanceof UploadedFile) {
            $cashEntry->fill($this->attachmentAttributes($attachment));
        }

        $cashEntry->save();

        /* Only drop the old document once its replacement is on the row. */
        if ($attachment instanceof UploadedFile && $previousPath !== null) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($previousPath);
        }

        return to_route('admin.finance.index', ['date' => $cashEntry->entry_date->toDateString()])
            ->with('success', 'Catatan keuangan berhasil diperbarui.');
    }

    public function destroy(CashEntry $cashEntry): RedirectResponse
    {
        Gate::authorize('admin.finance.delete');

        $entryDate = $cashEntry->entry_date->toDateString();

        if ($cashEntry->attachment_path !== null) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($cashEntry->attachment_path);
        }

        $cashEntry->delete();

        return to_route('admin.finance.index', ['date' => $entryDate])
            ->with('success', 'Catatan keuangan berhasil dihapus.');
    }

    public function attachment(CashEntry $cashEntry): StreamedResponse
    {
        Gate::authorize('admin.finance.read');

        abort_if($cashEntry->attachment_path === null, 404);

        $disk = Storage::disk(self::ATTACHMENT_DISK);
        $name = $cashEntry->attachment_name ?? 'lampiran';

        /*
         * An image is served inline so the ledger can show it in place; a
         * document has nothing to show and is handed over to be opened.
         */
        return FinancePresenter::isImage($cashEntry->attachment_path)
            ? $disk->response($cashEntry->attachment_path, $name)
            : $disk->download($cashEntry->attachment_path, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentAttributes(?UploadedFile $attachment): array
    {
        if (! $attachment instanceof UploadedFile) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store(
                'finance-attachments/'.CarbonImmutable::now()->format('Y/m'),
                self::ATTACHMENT_DISK,
            ),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_size' => $attachment->getSize(),
        ];
    }
}
