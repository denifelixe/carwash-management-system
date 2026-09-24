<?php

namespace App\Support\Admin;

use App\Models\Member;
use App\Models\Order;
use App\Models\RewardRedemption;
use App\Support\Demo\DateFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The stamp wallet (BR-02, BR-04): the one place a member's balance is worked out.
 *
 * Stamps are credited from `orders.stamps_earned` once the order is completed
 * or paid in full. Cancelled orders never earn them. Redemptions are the debits.
 * The balance is always calculated from those two sides and never stored, so a
 * reopened order or a voided redemption fixes itself.
 */
class MemberStamps
{
    /** @param Builder<Order> $query */
    private static function earningOrders(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'batal')
            ->where(fn (Builder $eligible): Builder => $eligible
                ->where('status', 'selesai')
                ->orWhere(fn (Builder $paid): Builder => $paid
                    ->where('total', '>', 0)
                    ->whereColumn('paid_amount', '>=', 'total')));
    }

    /**
     * Adds `stamps_earned_total`, `stamps_redeemed_total` and
     * `rewards_claimed_count` to a member query, for balance() to read.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public static function withBalances(Builder $query): Builder
    {
        return $query
            ->withSum([
                'orders as stamps_earned_total' => fn ($orderQuery) => self::earningOrders($orderQuery),
            ], 'stamps_earned')
            ->withSum('rewardRedemptions as stamps_redeemed_total', 'stamps')
            ->withCount('rewardRedemptions as rewards_claimed_count');
    }

    /**
     * Stamps earned over the member's lifetime. It reads the withBalances()
     * aggregate when that was loaded, and queries otherwise.
     */
    public static function earned(Member $member): int
    {
        if (array_key_exists('stamps_earned_total', $member->getAttributes())) {
            return (int) $member->getAttribute('stamps_earned_total');
        }

        return (int) self::earningOrders($member->orders()->getQuery())->sum('stamps_earned');
    }

    public static function redeemed(Member $member): int
    {
        if (array_key_exists('stamps_redeemed_total', $member->getAttributes())) {
            return (int) $member->getAttribute('stamps_redeemed_total');
        }

        return (int) $member->rewardRedemptions()->sum('stamps');
    }

    public static function rewardsClaimed(Member $member): int
    {
        if (array_key_exists('rewards_claimed_count', $member->getAttributes())) {
            return (int) $member->getAttribute('rewards_claimed_count');
        }

        return $member->rewardRedemptions()->count();
    }

    /**
     * Stamps the member can still spend. It never goes below zero, even if an
     * order is reopened after its stamps were already redeemed.
     */
    public static function balance(Member $member): int
    {
        return max(self::earned($member) - self::redeemed($member), 0);
    }

    /**
     * Balances that are not yet spent, summed across every member.
     */
    public static function circulating(): int
    {
        $earned = (int) self::earningOrders(Order::query()->whereNotNull('member_id'))
            ->sum('stamps_earned');

        return max($earned - (int) RewardRedemption::query()->sum('stamps'), 0);
    }

    /**
     * The member's ledger, newest first: credits from completed orders and
     * debits from redemptions, in one list.
     *
     * @return list<array{id: string, title: string, detail: string, stamps: int, type: string, date: string, icon: string}>
     */
    public static function history(Member $member, int $limit = 50): array
    {
        $credits = self::earningOrders(Order::query()->whereBelongsTo($member))
            ->where('stamps_earned', '>', 0)
            ->with('serviceVariations:id,service_id')
            ->latest('service_date')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => 'order-'.$order->id,
                'sortKey' => $order->service_date->toDateString().' '.($order->settlement_entered_at ?? $order->created_at)?->format('H:i:s'),
                'title' => $order->serviceVariations->pluck('pivot.service_name')->unique()->join(', ') ?: $order->number,
                'detail' => $order->vehicle_plate,
                'stamps' => (int) $order->stamps_earned,
                'type' => 'earn',
                'date' => DateFilter::format($order->service_date->toDateString()),
                'icon' => Str::contains($order->vehicle_name, ['Motor', 'NMax', 'Vario']) ? '🛵' : '✨',
            ]);

        $debits = RewardRedemption::query()
            ->whereBelongsTo($member)
            ->with('order:id,number')
            ->latest('redeemed_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (RewardRedemption $redemption): array => [
                'id' => 'redeem-'.$redemption->id,
                'sortKey' => $redemption->redeemed_at->format('Y-m-d H:i:s'),
                'title' => 'Tukar reward '.$redemption->reward_name,
                'detail' => 'Order '.($redemption->order->number ?? '—'),
                'stamps' => -$redemption->stamps,
                'type' => 'redeem',
                'date' => DateFilter::format($redemption->redeemed_at->toDateString()),
                'icon' => '🎁',
            ]);

        return array_values($credits
            ->concat($debits)
            ->sortByDesc('sortKey')
            ->take($limit)
            ->map(fn (array $entry): array => collect($entry)->except('sortKey')->all())
            ->all());
    }
}
