<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $orphanedRedemptions = DB::table('reward_redemptions as redemptions')
                ->join('orders', 'orders.id', '=', 'redemptions.order_id')
                ->whereNull('redemptions.deleted_at')
                ->whereNull('orders.deleted_at')
                ->whereNotExists(fn (Builder $query): Builder => $query
                    ->selectRaw('1')
                    ->from('order_transactions')
                    ->whereColumn('order_transactions.order_id', 'redemptions.order_id')
                    ->whereNull('order_transactions.deleted_at'))
                ->select('redemptions.id', 'redemptions.order_id', 'redemptions.reward_id', 'orders.subtotal', 'orders.status')
                ->get();

            foreach ($orphanedRedemptions as $redemption) {
                $voidedAt = now();

                if ($redemption->reward_id !== null) {
                    DB::table('rewards')->where('id', $redemption->reward_id)->increment('stock');
                }

                DB::table('reward_redemptions')->where('id', $redemption->id)->update([
                    'active_slot' => null,
                    'deleted_at' => $voidedAt,
                    'updated_at' => $voidedAt,
                ]);
                DB::table('orders')->where('id', $redemption->order_id)->update([
                    'paid_amount' => 0,
                    'payment_method' => null,
                    'discount' => 0,
                    'total' => $redemption->subtotal,
                    'reward_name' => null,
                    'status' => $redemption->status === 'selesai' && $redemption->subtotal > 0
                        ? 'pelunasan'
                        : $redemption->status,
                    'updated_at' => $voidedAt,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** The original payments were already deleted, so their reward choice cannot be reconstructed. */
    }
};
