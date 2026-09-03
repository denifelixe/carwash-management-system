<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\OperationalDataWindow;
use Illuminate\Support\Facades\DB;

class DeleteOrderTransaction
{
    public function __construct(private RecalculateDailyBalances $recalculateDailyBalances) {}

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
            $paidAmount = (int) $remainingTransactions->sum('amount');
            $paymentMethod = $remainingTransactions
                ->flatMap(fn (OrderTransaction $item): array => $item->channel_breakdown)
                ->pluck('label')
                ->filter()
                ->unique()
                ->implode(' + ');

            if ($paidAmount < (int) $order->total) {
                $order->transactions()
                    ->where('type', 'Pembayaran Lunas')
                    ->update(['type' => 'Pembayaran Sebagian']);
            }

            $order->update([
                'paid_amount' => $paidAmount,
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                'status' => $paidAmount < (int) $order->total ? 'pelunasan' : $order->status,
            ]);

            $this->recalculateDailyBalances->handle($paidDate);

            return $paidDate;
        });
    }
}
