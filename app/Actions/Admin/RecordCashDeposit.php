<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\CashEntry;
use App\Support\Admin\CashEntryAttachments;
use App\Support\Admin\DocumentNumbers;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\OrderQueries;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Setor Tunai (MoM 17 Sep 2026): cash leaves the drawer for a non-cash
 * account. It is written as two entries with the same amount, description,
 * time, shift and proofs — a Tunai outflow and a Setor Tunai inflow — tied by
 * a transfer reference so they are always edited and deleted together.
 */
class RecordCashDeposit
{
    public function __construct(private UpdateDailyBalance $updateDailyBalance) {}

    /**
     * @param  array{description: string, amount: int}  $data
     * @param  list<UploadedFile>  $attachments
     * @return array{out: CashEntry, in: CashEntry}
     */
    public function handle(
        array $data,
        Admin $admin,
        CarbonImmutable $occurredAt,
        ?AdminShift $shift,
        array $attachments,
    ): array {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($data, $admin, $occurredAt, $shift, $attachments, &$storedFiles): array {
                $entryDate = $occurredAt->toDateString();
                $entries = [];

                foreach (['out' => 'Tunai', 'in' => OrderQueries::CASH_DEPOSIT_METHOD] as $direction => $method) {
                    $entry = CashEntry::query()->create([
                        'direction' => $direction,
                        'category' => FinanceCategories::CASH_DEPOSIT,
                        'description' => $data['description'],
                        'amount' => $data['amount'],
                        'method' => $method,
                        'recorded_by_admin_id' => $admin->getKey(),
                        'shift_name' => $shift?->name,
                        'entry_date' => $entryDate,
                        'occurred_at' => $occurredAt,
                        'reference' => DocumentNumbers::cashEntry(FinanceCategories::CASH_DEPOSIT, $entryDate),
                    ]);

                    $entries[$direction] = $entry;
                }

                $transferReference = 'SETOR-'.$entries['out']->id;

                foreach ($entries as $entry) {
                    $entry->update(['transfer_reference' => $transferReference]);
                    CashEntryAttachments::store($entry, $attachments, $storedFiles);
                }

                /* The day's money only changes pocket: cash down, non-cash up. */
                $this->updateDailyBalance->handle(
                    $entryDate,
                    cashExpenseDelta: (int) $data['amount'],
                    nonCashIncomeDelta: (int) $data['amount'],
                );

                return $entries;
            });
        } catch (Throwable $exception) {
            CashEntryAttachments::delete($storedFiles);

            throw $exception;
        }
    }
}
