<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeleteCashEntry;
use App\Actions\Admin\DeleteOrderTransaction;
use App\Actions\Admin\RecalculateDailyBalances;
use App\Actions\Admin\UpdateDailyBalance;
use App\Actions\Admin\UpdateOrderTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCashEntryRequest;
use App\Http\Requests\Admin\UpdateCashEntryRequest;
use App\Http\Requests\Admin\UpdateOrderTransactionRequest;
use App\Models\Admin;
use App\Models\CashEntry;
use App\Models\CashEntryAttachment;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\AdminModuleActions;
use App\Support\Admin\AdminShell;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\FinancePresenter;
use App\Support\Admin\FinanceQueries;
use App\Support\Admin\FinanceReference;
use App\Support\Admin\OperationalDataWindow;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use App\Support\Admin\TransactionShiftResolver;
use App\Support\Demo\DateFilter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.finance.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $today = CarbonImmutable::now()->startOfDay();
        $selectedDate = DateFilter::resolve($request->query('date')) ?: $today->toDateString();

        ['moneyIn' => $moneyIn, 'moneyOut' => $moneyOut] = FinanceQueries::ledgerForDate($selectedDate);
        $canViewNonCashBalance = Gate::allows(
            'admin.finance.'.AdminModuleActions::VIEW_NON_CASH_BALANCE,
        );
        $dailyBalance = FinanceQueries::dailyBalance($selectedDate);
        $dailyBalanceHistory = FinanceQueries::dailyBalanceHistory($selectedDate);

        if (! $canViewNonCashBalance) {
            $dailyBalance['nonCash'] = 0;
            $dailyBalance['previous']['nonCash'] = 0;
            $dailyBalanceHistory = array_map(
                fn (array $balance): array => [
                    ...$balance,
                    'nonCashIncome' => 0,
                    'nonCashExpense' => 0,
                    'nonCashBalance' => 0,
                ],
                $dailyBalanceHistory,
            );
        }

        return Inertia::render('admin/Finance', [
            ...$adminShell->props($admin, 'Keuangan', 'finance'),
            'moneyIn' => $moneyIn,
            'moneyOut' => $moneyOut,
            'filters' => OrderQueries::filters($selectedDate, $today),
            /* Only what may be written by hand: POS categories are derived. */
            'incomeCategories' => FinanceCategories::recordable('in'),
            'expenseCategories' => FinanceCategories::recordable('out'),
            'cashSummary' => FinanceQueries::cashSummary($moneyIn, $moneyOut),
            'dailyBalance' => $dailyBalance,
            'dailyBalanceHistory' => $dailyBalanceHistory,
            'paymentMethods' => OrderQueries::PAYMENT_METHODS,
            'expenseMethods' => OrderQueries::EXPENSE_METHODS,
            'shifts' => FinanceQueries::shiftSummary($moneyIn, $moneyOut, $selectedDate),
            'orders' => FinanceQueries::ordersForDate($selectedDate)
                ->map(fn (Order $order): array => OrderPresenter::order($order))
                ->all(),
            'capabilities' => [
                'create' => Gate::allows('admin.finance.create'),
                'update' => Gate::allows('admin.finance.update'),
                'delete' => Gate::allows('admin.finance.delete'),
                'edit_cash_entry_backdate' => Gate::allows(
                    'admin.finance.'.AdminModuleActions::EDIT_CASH_ENTRY_BACKDATE,
                ),
                'view_non_cash_balance' => $canViewNonCashBalance,
            ],
        ]);
    }

    public function store(
        StoreCashEntryRequest $request,
        TransactionShiftResolver $transactionShiftResolver,
        UpdateDailyBalance $updateDailyBalance,
    ): RedirectResponse {
        $data = $request->validated();

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $now = CarbonImmutable::now();
        $entryDate = $data['entry_date'] ?? $now->toDateString();
        $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $entryDate);
        $occurredAt = $selectedDate->setTime(
            $now->hour,
            $now->minute,
            $now->second,
            $now->micro,
        );
        $shift = $transactionShiftResolver->resolve(
            $admin,
            $request->integer('transaction_shift_id') ?: null,
            $occurredAt,
        );

        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);
        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $data,
                $admin,
                $entryDate,
                $occurredAt,
                $shift,
                $attachments,
                $updateDailyBalance,
                &$storedFiles,
            ): void {
                $entry = new CashEntry([
                    'direction' => $data['direction'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'method' => $data['method'],
                    'recorded_by_admin_id' => $admin->getKey(),
                    'shift_name' => $shift?->name,
                    'entry_date' => $entryDate,
                    'occurred_at' => $occurredAt,
                    /* Placeholder: the reference is only stable once the row has an ID. */
                    'reference' => 'TRX-'.$occurredAt->format('YmdHisu'),
                ]);

                $entry->save();

                $entry->reference = FinanceReference::make($data['category'], $entryDate, $entry->id);
                $entry->save();
                $amounts = UpdateDailyBalance::methodAmounts($data['method'], $data['amount']);
                $updateDailyBalance->handle(
                    $entryDate,
                    cashIncomeDelta: $data['direction'] === 'in'
                        ? $amounts['cash']
                        : 0,
                    cashExpenseDelta: $data['direction'] === 'out'
                        ? $amounts['cash']
                        : 0,
                    nonCashIncomeDelta: $data['direction'] === 'in'
                        ? $amounts['nonCash']
                        : 0,
                    nonCashExpenseDelta: $data['direction'] === 'out'
                        ? $amounts['nonCash']
                        : 0,
                );
                $this->storeAttachments($entry, $attachments, $storedFiles);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);

            throw $exception;
        }

        return to_route('admin.finance.index', ['date' => $entryDate])
            ->with('success', 'Catatan keuangan berhasil disimpan.');
    }

    public function update(
        UpdateCashEntryRequest $request,
        CashEntry $cashEntry,
        RecalculateDailyBalances $recalculateDailyBalances,
        UpdateDailyBalance $updateDailyBalance,
    ): RedirectResponse {
        OperationalDataWindow::ensureAllows($cashEntry->entry_date);
        $data = $request->validated();
        /** @var Admin $admin */
        $admin = $request->user('admin');
        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);
        /** @var list<int> $removedAttachmentIds */
        $removedAttachmentIds = $data['removed_attachment_ids'] ?? [];
        $storedFiles = [];
        $removedFiles = $cashEntry->attachments()
            ->whereKey($removedAttachmentIds)
            ->get()
            ->map(fn (CashEntryAttachment $attachment): array => [
                'disk' => $attachment->disk,
                'path' => $attachment->path,
            ])
            ->all();

        try {
            DB::transaction(function () use (
                $data,
                $admin,
                $cashEntry,
                $attachments,
                $removedAttachmentIds,
                $recalculateDailyBalances,
                $updateDailyBalance,
                &$storedFiles,
            ): void {
                $previousEntryDate = $cashEntry->entry_date->toDateString();
                $entryDate = $data['entry_date'];
                $dateChanged = $entryDate !== $previousEntryDate;
                $previousAmounts = UpdateDailyBalance::methodAmounts(
                    $cashEntry->method,
                    (int) $cashEntry->amount,
                );
                $correctedAmounts = UpdateDailyBalance::methodAmounts(
                    $data['method'],
                    (int) $data['amount'],
                );
                $occurredAt = $cashEntry->occurred_at;

                if ($dateChanged) {
                    $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $entryDate);
                    $occurredAt = $selectedDate->setTime(
                        $occurredAt->hour,
                        $occurredAt->minute,
                        $occurredAt->second,
                        $occurredAt->micro,
                    );
                }

                $cashEntry->fill([
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'amount' => $data['amount'],
                    'method' => $data['method'],
                    'entry_date' => $entryDate,
                    'occurred_at' => $occurredAt,
                    'updated_by_admin_id' => $admin->getKey(),
                    'reference' => FinanceReference::make(
                        $data['category'],
                        $entryDate,
                        $cashEntry->id,
                    ),
                ])->save();

                if ($dateChanged) {
                    $recalculationDate = $previousEntryDate < $entryDate
                        ? $previousEntryDate
                        : $entryDate;
                    $recalculateDailyBalances->handle($recalculationDate);
                } else {
                    $updateDailyBalance->handle(
                        $entryDate,
                        cashIncomeDelta: $cashEntry->direction === 'in'
                            ? $correctedAmounts['cash'] - $previousAmounts['cash']
                            : 0,
                        cashExpenseDelta: $cashEntry->direction === 'out'
                            ? $correctedAmounts['cash'] - $previousAmounts['cash']
                            : 0,
                        nonCashIncomeDelta: $cashEntry->direction === 'in'
                            ? $correctedAmounts['nonCash'] - $previousAmounts['nonCash']
                            : 0,
                        nonCashExpenseDelta: $cashEntry->direction === 'out'
                            ? $correctedAmounts['nonCash'] - $previousAmounts['nonCash']
                            : 0,
                    );
                }

                $cashEntry->attachments()->whereKey($removedAttachmentIds)->delete();
                $this->storeAttachments($cashEntry, $attachments, $storedFiles);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($storedFiles);

            throw $exception;
        }

        $this->deleteStoredFiles($removedFiles);

        return to_route('admin.finance.index', ['date' => $cashEntry->entry_date->toDateString()])
            ->with('success', 'Catatan keuangan berhasil diperbarui.');
    }

    public function updateTransaction(
        UpdateOrderTransactionRequest $request,
        OrderTransaction $orderTransaction,
        UpdateOrderTransaction $updateOrderTransaction,
    ): RedirectResponse {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $updateOrderTransaction->handle($orderTransaction, $admin, [
            'amount' => $request->integer('amount'),
            'channels' => $request->channels(),
        ]);

        return to_route('admin.finance.index', ['date' => $orderTransaction->paid_at->toDateString()])
            ->with('success', 'Transaksi pembayaran berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        CashEntry $cashEntry,
        DeleteCashEntry $deleteCashEntry,
    ): RedirectResponse {
        Gate::authorize('admin.finance.delete');
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $entryDate = $deleteCashEntry->handle($cashEntry, $admin);

        return to_route('admin.finance.index', ['date' => $entryDate])
            ->with('success', 'Catatan keuangan berhasil dihapus.');
    }

    public function destroyTransaction(
        Request $request,
        OrderTransaction $orderTransaction,
        DeleteOrderTransaction $deleteOrderTransaction,
    ): RedirectResponse {
        Gate::authorize('admin.finance.delete');
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $paidDate = $deleteOrderTransaction->handle($orderTransaction, $admin);

        return to_route('admin.finance.index', ['date' => $paidDate])
            ->with('success', 'Transaksi pembayaran berhasil dihapus.');
    }

    public function attachment(CashEntryAttachment $cashEntryAttachment): StreamedResponse
    {
        Gate::authorize('admin.finance.read');
        abort_unless($cashEntryAttachment->cashEntry()->exists(), 404);

        $disk = Storage::disk($cashEntryAttachment->disk);

        /*
         * An image is served inline so the ledger can show it in place; a
         * document has nothing to show and is handed over to be opened.
         */
        return FinancePresenter::isImage($cashEntryAttachment->path)
            ? $disk->response($cashEntryAttachment->path, $cashEntryAttachment->original_name)
            : $disk->download($cashEntryAttachment->path, $cashEntryAttachment->original_name);
    }

    /**
     * @param  list<UploadedFile>  $attachments
     * @param  list<array{disk: string, path: string}>  $storedFiles
     */
    private function storeAttachments(CashEntry $entry, array $attachments, array &$storedFiles): void
    {
        $disk = (string) config('filesystems.default');

        foreach ($attachments as $attachment) {
            $path = $attachment->store($entry->reference, $disk);

            if ($path === false) {
                throw new RuntimeException('Lampiran keuangan gagal disimpan.');
            }

            $storedFiles[] = ['disk' => $disk, 'path' => $path];
            $entry->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $attachment->getClientOriginalName(),
                'size' => $attachment->getSize(),
            ]);
        }
    }

    /** @param list<array{disk: string, path: string}> $storedFiles */
    private function deleteStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $storedFile) {
            Storage::disk($storedFile['disk'])->delete($storedFile['path']);
        }
    }
}
