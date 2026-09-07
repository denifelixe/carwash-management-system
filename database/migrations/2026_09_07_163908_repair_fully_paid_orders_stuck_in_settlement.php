<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restore settled orders whose status was moved back to the cashier queue.
     */
    public function up(): void
    {
        $this->candidates()->select('id')->chunkById(100, function (Collection $orders): void {
            foreach ($orders as $candidate) {
                DB::transaction(function () use ($candidate): void {
                    $order = $this->candidates()->where('id', $candidate->id)->lockForUpdate()->first();

                    if ($order === null) {
                        return;
                    }

                    $transactions = DB::table('order_transactions')
                        ->where('order_id', $order->id)
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->get();

                    if (! $transactions->contains('type', 'Pembayaran Lunas')
                        || (int) $transactions->sum('amount') !== (int) $order->paid_amount) {
                        return;
                    }

                    DB::table('orders')->where('id', $order->id)->update([
                        'status' => 'selesai',
                        'invoice_number' => $order->invoice_number ?? str_replace('ORD', 'ZW', $order->number),
                        'updated_at' => now(),
                    ]);
                });
            }
        });
    }

    private function candidates(): Builder
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->where('status', 'pelunasan')
            ->whereColumn('paid_amount', '>=', 'total');
    }

    /**
     * Keep repaired statuses on rollback; reopening settled orders restores the bug.
     */
    public function down(): void {}
};
