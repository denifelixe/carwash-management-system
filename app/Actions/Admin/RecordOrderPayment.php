<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Support\Admin\MemberStamps;
use App\Support\Admin\PaymentChannelBreakdown;
use App\Support\Admin\RewardRedemptionRules;
use App\Support\Admin\TransactionShiftResolver;
use Illuminate\Support\Facades\DB;

/**
 * Books one cashier payment against an order (BR-06).
 *
 * The cashier may take the bill in instalments, so the order keeps a running
 * `paid_amount` and every hand-over is written as its own transaction. Only a
 * payment that clears the bill on a settlement intent closes the order and
 * issues its invoice; a deposit on a booking leaves the car still to arrive.
 */
class RecordOrderPayment
{
    public function __construct(
        private TransactionShiftResolver $transactionShiftResolver,
        private UpdateDailyBalance $updateDailyBalance,
    ) {}

    /**
     * @param  array{intent: string, discount: int, reward_id?: int|null, amount: int, channels: list<array{method: string, amount: int, provider: string, reference: string}>, transaction_shift_id: int|null, note?: string|null}  $payment
     */
    public function handle(Order $order, Admin $cashier, array $payment): OrderTransaction
    {
        return DB::transaction(function () use ($order, $cashier, $payment): OrderTransaction {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $discount = $payment['discount'];
            $amount = $payment['amount'];

            /*
             * The form was validated against an unlocked read, so the balance
             * is checked again here: two tills settling the same order at once
             * would otherwise take more money than the bill is worth.
             */
            $due = max((int) $order->total - (int) $order->paid_amount, 0);

            abort_if(
                in_array($order->status, ['selesai', 'batal'], true),
                422,
                'Order ini sudah ditutup dan tidak bisa dibayar lagi.',
            );
            $redemption = $this->redeemReward($order, $cashier, $payment['reward_id'] ?? null, $due);
            $discount += $redemption?->discount ?? 0;

            abort_if(
                $discount > $due || $amount > $due - min($discount, $due),
                422,
                'Pembayaran melebihi sisa tagihan order.',
            );

            $total = (int) $order->total - $discount;
            $paidAmount = (int) $order->paid_amount + $amount;
            $isFullyPaid = $paidAmount >= $total;
            $completesOrder = $isFullyPaid && $payment['intent'] === 'settlement';
            $channels = self::channelBreakdown($payment['channels'], $amount, $redemption !== null);

            $paidAt = now();
            $shift = $this->transactionShiftResolver->resolve(
                $cashier,
                $payment['transaction_shift_id'],
                $paidAt,
            );

            $transaction = $order->transactions()->create([
                'recorded_by_admin_id' => $cashier->getKey(),
                'reference' => $order->number.'-TRX-'.(
                    OrderTransaction::withTrashed()->whereBelongsTo($order)->count() + 1
                ),
                'type' => $completesOrder ? 'Pembayaran Lunas' : 'Pembayaran Sebagian',
                'shift_name' => $shift?->name,
                'amount' => $amount,
                'channel_breakdown' => $channels,
                'note' => $payment['note'] ?? null,
                /* The outlet's own clock, which is what the column holds. */
                'paid_at' => $paidAt,
            ]);

            $financialChannels = PaymentChannelBreakdown::financial($channels, $amount);
            $channelAmounts = UpdateDailyBalance::channelAmounts($financialChannels);
            $this->updateDailyBalance->handle(
                $paidAt->toDateString(),
                cashIncomeDelta: $channelAmounts['cash'],
                nonCashIncomeDelta: $channelAmounts['nonCash'],
            );

            $order->update([
                'discount' => (int) $order->discount + $discount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'payment_method' => self::paymentMethodLabel($order, $channels),
                'reward_name' => $redemption?->reward_name ?? $order->reward_name,
                'status' => $completesOrder ? 'selesai' : $order->status,
                'invoice_number' => $completesOrder
                    ? ($order->invoice_number ?? str_replace('ORD', 'ZW', $order->number))
                    : $order->invoice_number,
            ]);

            return $transaction;
        });
    }

    /**
     * Trades the member's stamps for a reward on this order (BR-04).
     *
     * The rules are checked again here, with the reward and the member locked,
     * because the form request read them unlocked. Two tills must not spend
     * the same stamps or hand out the last unit twice.
     */
    private function redeemReward(Order $order, Admin $cashier, ?int $rewardId, int $due): ?RewardRedemption
    {
        if ($rewardId === null) {
            return null;
        }

        $reward = Reward::query()->with('serviceVariations:id,service_id')->whereKey($rewardId)->lockForUpdate()->firstOrFail();
        $member = $order->member_id !== null
            ? Member::query()->whereKey($order->member_id)->lockForUpdate()->first()
            : null;
        $order->load('serviceVariations');

        $refusal = RewardRedemptionRules::refusal(
            $reward,
            $order,
            $member instanceof Member ? MemberStamps::balance($member) : 0,
        );
        abort_if($refusal !== null, 422, (string) $refusal);

        $reward->decrement('stock');

        /** @var RewardRedemption $redemption */
        $redemption = $order->rewardRedemption()->create([
            'member_id' => $order->member_id,
            'reward_id' => $reward->id,
            'redeemed_by_admin_id' => $cashier->getKey(),
            'reward_name' => $reward->name,
            'stamps' => $reward->required_stamps,
            'discount' => min(RewardRedemptionRules::discountFor($reward, $order), $due),
            'redeemed_at' => now(),
        ]);

        return $redemption;
    }

    /**
     * The channels the money came in on. A bill cleared entirely by a discount
     * or a reward still needs a line, so it is labelled as such rather than
     * left empty.
     *
     * @param  list<array{method: string, amount: int, provider: string, reference: string}>  $channels
     * @return list<array{label: string, amount: int, reference?: string}>
     */
    private static function channelBreakdown(array $channels, int $amount, bool $isRewardApplied = false): array
    {
        $breakdown = array_map(
            function (array $channel): array {
                $label = $channel['provider'] === ''
                    ? $channel['method']
                    : $channel['method'].' · '.$channel['provider'];

                /* Kept beside the channel so an EDC trace stays reprintable. */
                return $channel['reference'] === ''
                    ? ['label' => $label, 'amount' => $channel['amount']]
                    : [
                        'label' => $label,
                        'amount' => $channel['amount'],
                        'reference' => $channel['reference'],
                    ];
            },
            $channels,
        );

        return $breakdown === []
            ? [['label' => $isRewardApplied ? 'Reward' : 'Diskon', 'amount' => $amount]]
            : $breakdown;
    }

    /**
     * Every method the order was ever paid with, so an instalment does not wipe
     * the channel an earlier payment came in on.
     *
     * @param  list<array{label: string, amount: int}>  $channels
     */
    private static function paymentMethodLabel(Order $order, array $channels): string
    {
        $existing = $order->payment_method === null || $order->payment_method === '—'
            ? []
            : explode(' + ', $order->payment_method);
        $methods = array_map(
            fn (array $channel): string => $channel['label'],
            $channels,
        );

        return implode(' + ', array_unique([...$existing, ...$methods]));
    }
}
