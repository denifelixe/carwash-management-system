<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Recover only unarrived bookings whose last update matches an audited DP
     * deletion. Later edits and settlement history need individual review.
     */
    public function up(): void
    {
        $today = today()->toDateString();

        $this->candidates($today)->select('id')->chunkById(100, function (Collection $orders) use ($today): void {
            foreach ($orders as $candidate) {
                DB::transaction(function () use ($candidate, $today): void {
                    $order = $this->candidates($today)->where('id', $candidate->id)->lockForUpdate()->first();

                    if ($order === null) {
                        return;
                    }

                    $transactions = DB::table('order_transactions')
                        ->where('order_id', $order->id)
                        ->lockForUpdate()
                        ->get();
                    $activeTransactions = $transactions->whereNull('deleted_at');
                    $hasMatchingDeletion = $transactions->contains(fn (object $transaction): bool => $transaction->deleted_at !== null
                        && $transaction->deleted_by_admin_id !== null
                        && $transaction->type === 'Pembayaran Sebagian'
                        && $transaction->deleted_at === $order->updated_at);

                    if (! $hasMatchingDeletion
                        || $transactions->contains('type', 'Pembayaran Lunas')
                        || (int) $activeTransactions->sum('amount') !== (int) $order->paid_amount) {
                        return;
                    }

                    DB::table('orders')->where('id', $order->id)->update([
                        'status' => 'booking',
                        'updated_at' => now(),
                    ]);
                });
            }
        });
    }

    private function candidates(string $today): Builder
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->where('source', 'booking')
            ->where('status', 'pelunasan')
            ->whereNull('arrived_at')
            ->whereNull('invoice_number')
            ->where('service_date', '>=', $today)
            ->whereColumn('paid_amount', '<', 'total');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /* Restoring the incorrect status could hide bookings again or overwrite
         * progress made after deployment, so the recovered data is retained. */
    }
};
