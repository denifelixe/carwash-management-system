<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\OperationalDataWindow;
use Illuminate\Support\Facades\DB;

class DeleteOrderTransaction
{
    public function __construct(
        private RecalculateDailyBalances $recalculateDailyBalances,
        private VoidRewardRedemption $voidRewardRedemption,
    ) {}

    public function handle(OrderTransaction $orderTransaction, Admin $admin): string
    {
        return DB::transaction(function () use ($orderTransaction, $admin): string {
            $order = Order::query()
                ->whereKey($orderTransaction->order_id)
                ->lockForUpdate()
                ->firstOrFail();
            $transaction = OrderTransaction::query()
                ->whereBelongsTo($order)
                ->whereKey($orderTransaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            OperationalDataWindow::ensureAllows($transaction->paid_at);
            $paidDate = $transaction->paid_at->toDateString();

            $transaction->update(['deleted_by_admin_id' => $admin->getKey()]);
            $transaction->delete();

            $remainingTransactions = $order->transactions()->lockForUpdate()->get();
            $hasNoPayments = $remainingTransactions->isEmpty();
            $paidAmount = (int) $remainingTransactions->sum('amount');
            $paymentMethod = $remainingTransactions
                ->flatMap(fn (OrderTransaction $item): array => $item->channel_breakdown)
                ->pluck('label')
                ->filter()
                ->unique()
                ->implode(' + ');

            if ($hasNoPayments) {
                $this->voidRewardRedemption->handle($order);
            }

            $total = $hasNoPayments && $order->discount > 0
                ? (int) $order->subtotal
                : (int) $order->total;

            if ($paidAmount < $total) {
                $order->transactions()
                    ->where('type', 'Pembayaran Lunas')
                    ->update(['type' => 'Pembayaran Sebagian']);
            }

            $order->update([
                'paid_amount' => $paidAmount,
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                'discount' => $hasNoPayments ? 0 : (int) $order->discount,
                'total' => $total,
                'reward_name' => $hasNoPayments ? null : $order->reward_name,
                'status' => $order->status === 'selesai' && $paidAmount < $total
                    ? 'pelunasan'
                    : $order->status,
            ]);

            $this->recalculateDailyBalances->handle($paidDate);

            return $paidDate;
        });
    }
}
