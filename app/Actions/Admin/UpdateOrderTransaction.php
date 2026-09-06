<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Support\Admin\AdminModuleActions;
use App\Support\Admin\OperationalDataWindow;
use App\Support\Admin\PaymentChannelBreakdown;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UpdateOrderTransaction
{
    public function __construct(
        private UpdateDailyBalance $updateDailyBalance,
        private RecalculateDailyBalances $recalculateDailyBalances,
    ) {}

    /**
     * @param  array{amount: int, channels: list<array{label: string, amount: int, provider: string, reference: string}>, transaction_shift_id?: int|null, paid_at?: CarbonImmutable}  $payment
     */
    public function handle(OrderTransaction $orderTransaction, Admin $admin, array $payment): void
    {
        DB::transaction(function () use ($orderTransaction, $admin, $payment): void {
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
            $previousDate = $transaction->paid_at->toDateString();
            $paidAt = isset($payment['paid_at']) && $admin->can('admin.finance.'.AdminModuleActions::EDIT_CASH_ENTRY_BACKDATE)
                ? $payment['paid_at']
                : $transaction->paid_at;

            $transaction->update([
                ...(array_key_exists('transaction_shift_id', $payment) ? [
                    'shift_name' => $payment['transaction_shift_id'] === null
                        ? null
                        : AdminShift::query()->where('is_active', true)->findOrFail($payment['transaction_shift_id'])->name,
                ] : []),
                'amount' => $payment['amount'],
                'paid_at' => $paidAt,
                'channel_breakdown' => $channels,
                'updated_by_admin_id' => $admin->getKey(),
            ]);

            $paidDate = $transaction->paid_at->toDateString();
            if ($paidDate !== $previousDate) {
                $this->recalculateDailyBalances->handle(min($previousDate, $paidDate));
            } else {
                $this->updateDailyBalance->handle(
                    $paidDate,
                    cashIncomeDelta: $correctedAmounts['cash'] - $previousAmounts['cash'],
                    nonCashIncomeDelta: $correctedAmounts['nonCash'] - $previousAmounts['nonCash'],
                );
            }

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
