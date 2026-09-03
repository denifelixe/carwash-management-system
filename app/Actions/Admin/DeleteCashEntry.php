<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\CashEntry;
use App\Support\Admin\OperationalDataWindow;
use Illuminate\Support\Facades\DB;

class DeleteCashEntry
{
    public function __construct(private RecalculateDailyBalances $recalculateDailyBalances) {}

    public function handle(CashEntry $cashEntry, Admin $admin): string
    {
        return DB::transaction(function () use ($cashEntry, $admin): string {
            $entry = CashEntry::query()->whereKey($cashEntry->getKey())->lockForUpdate()->firstOrFail();
            OperationalDataWindow::ensureAllows($entry->entry_date);
            $entryDate = $entry->entry_date->toDateString();

            $entry->update(['deleted_by_admin_id' => $admin->getKey()]);
            $entry->delete();
            $this->recalculateDailyBalances->handle($entryDate);

            return $entryDate;
        });
    }
}
