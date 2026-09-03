<?php

namespace App\Actions\Admin;

use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\PaymentChannelBreakdown;
use Illuminate\Support\Facades\DB;

class UpdateOrderTransaction
{
    public function __construct(private UpdateDailyBalance $updateDailyBalance) {}

    /**
     * @param  array{amount: int, channels: list<array{label: string, amount: int, provider: string, reference: string}>}  $payment
     */
    public function handle(OrderTransaction $orderTransaction, array $payment): void
    {
        DB::transaction(function () use ($orderTransaction, $payment): void {
            $order = Order::query()
                ->whereKey($orderTransaction->order_id)
                ->lockForUpdate()
                ->firstOrFail();
            $transaction = OrderTransaction::query()
                ->whereBelongsTo($order)
                ->whereKey($orderTransaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $paidAmount = (int) OrderTransaction::query()
                ->whereBelongsTo($order)
                ->where('id', '!=', $transaction->id)
                ->sum('amount') + $payment['amount'];

            abort_if(
                $paidAmount > (int) $order->total,
                422,
                'Total pembayaran tidak boleh melebihi total order.',
            );
            abort_if(
                $order->status === 'selesai' && $paidAmount !== (int) $order->total,
                422,
                'Order selesai harus tetap berstatus lunas setelah koreksi.',
            );

            $channels = array_map(
                fn (array $channel): array => $channel['reference'] === ''
                    ? ['label' => $channel['label'], 'amount' => $channel['amount']]
                    : [
                        'label' => $channel['label'],
                        'amount' => $channel['amount'],
                        'reference' => $channel['reference'],
                    ],
                $payment['channels'],
            );
            $previousAmounts = UpdateDailyBalance::channelAmounts(
                PaymentChannelBreakdown::financial(
                    $transaction->channel_breakdown,
                    (int) $transaction->amount,
                ),
            );
            $correctedAmounts = UpdateDailyBalance::channelAmounts($channels);

            $transaction->update([
                'amount' => $payment['amount'],
                'channel_breakdown' => $channels,
            ]);

            $this->updateDailyBalance->handle(
                $transaction->paid_at->toDateString(),
                cashIncomeDelta: $correctedAmounts['cash'] - $previousAmounts['cash'],
                nonCashIncomeDelta: $correctedAmounts['nonCash'] - $previousAmounts['nonCash'],
            );

            $paymentMethod = $order->transactions()
                ->get(['channel_breakdown'])
                ->flatMap(fn (OrderTransaction $item) => $item->channel_breakdown)
                ->pluck('label')
                ->filter()
                ->unique()
                ->implode(' + ');

            $order->update([
                'paid_amount' => $paidAmount,
                'payment_method' => $paymentMethod,
            ]);
        });
    }
}
