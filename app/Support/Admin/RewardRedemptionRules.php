<?php

namespace App\Support\Admin;

use App\Models\Order;
use App\Models\Reward;
use App\Models\ServiceVariation;

/**
 * When a reward may be redeemed on an order, and what it takes off the bill.
 *
 * The payment form request checks these against an unlocked read so the
 * cashier gets a field error. RecordOrderPayment checks them again under row
 * locks, because two tills could otherwise spend the same stamps.
 */
class RewardRedemptionRules
{
    /**
     * Only the eligible variation with the largest discount contributes.
     * The caller caps the result at what is still due.
     */
    public static function discountFor(Reward $reward, Order $order): int
    {
        $orderVariations = $order->serviceVariations->keyBy('id');
        $largestDiscount = 0;

        foreach ($reward->serviceVariations->sortBy('id') as $variation) {
            $ordered = $orderVariations->get($variation->id);

            if (! $ordered instanceof ServiceVariation) {
                continue;
            }

            $quantity = min((int) $ordered->pivot->quantity, (int) $variation->pivot->quantity);
            $discount = (int) round(
                (int) $ordered->pivot->unit_price * $quantity * (int) $variation->pivot->discount_percent / 100,
            );

            if ($discount > $largestDiscount) {
                $largestDiscount = $discount;
            }
        }

        return $largestDiscount;
    }

    /**
     * The reason this reward cannot be redeemed on this order, or null when it can.
     */
    public static function refusal(Reward $reward, Order $order, int $balance): ?string
    {
        if ($order->transactions()->exists()) {
            return 'Reward hanya dapat ditukar pada pembayaran pertama order.';
        }

        if ($order->member_id === null) {
            return 'Reward hanya bisa ditukar oleh member.';
        }

        if ($order->rewardRedemption()->exists()) {
            return 'Order ini sudah memakai reward.';
        }

        if (! $reward->is_active) {
            return 'Reward ini sedang nonaktif.';
        }

        if ($reward->stock <= 0) {
            return 'Stok reward ini sudah habis.';
        }

        if ($balance < $reward->required_stamps) {
            return "Stempel member belum cukup: butuh {$reward->required_stamps}, saldo {$balance}.";
        }

        if (! $reward->isMerchandise() && ! self::orderHasRewardVariation($reward, $order)) {
            return 'Reward ini tidak berlaku untuk layanan di order ini.';
        }

        return null;
    }

    private static function orderHasRewardVariation(Reward $reward, Order $order): bool
    {
        $variationIds = $reward->serviceVariations->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return $order->serviceVariations->contains(
            fn (ServiceVariation $variation): bool => in_array((int) $variation->id, $variationIds, true),
        );
    }
}
