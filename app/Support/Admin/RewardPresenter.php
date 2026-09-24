<?php

namespace App\Support\Admin;

use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Support\Demo\DateFilter;

/**
 * One payload shape for rewards and redemptions. The live module, the demo
 * console, the POS, and the member portal all render the same rows.
 */
class RewardPresenter
{
    /**
     * @return array{id: int, name: string, description: string, requiredStamps: int, applicableVariations: list<array{serviceVariationId: int, quantity: int, discountPercent: int}>, icon: string, status: string, stock: int, redeemed: int}
     */
    public static function reward(Reward $reward): array
    {
        return [
            'id' => $reward->id,
            'name' => $reward->name,
            'description' => $reward->description ?? '',
            'requiredStamps' => $reward->required_stamps,
            'applicableVariations' => $reward->serviceVariations->map(fn ($variation): array => [
                'serviceVariationId' => $variation->id,
                'quantity' => (int) $variation->pivot->quantity,
                'discountPercent' => (int) $variation->pivot->discount_percent,
            ])->values()->all(),
            'icon' => $reward->icon,
            'status' => $reward->is_active ? 'aktif' : 'nonaktif',
            'stock' => $reward->stock,
            'redeemed' => (int) ($reward->redemptions_count ?? $reward->redemptions()->count()),
        ];
    }

    /**
     * @return array{id: int, date: string, time: string, member: string, memberId: string, order: string, reward: string, stamps: int, discount: int, by: string}
     */
    public static function redemption(RewardRedemption $redemption): array
    {
        $member = $redemption->relationLoaded('member') ? $redemption->member : null;
        $order = $redemption->relationLoaded('order') ? $redemption->order : null;
        $redeemedBy = $redemption->relationLoaded('redeemedBy') ? $redemption->redeemedBy : null;

        return [
            'id' => $redemption->id,
            'date' => DateFilter::format($redemption->redeemed_at->toDateString()),
            'time' => $redemption->redeemed_at->format('H.i'),
            'member' => $member?->name ?? '—',
            'memberId' => 'MEM-'.str_pad((string) $redemption->member_id, 6, '0', STR_PAD_LEFT),
            'order' => $order?->number ?? '—',
            'reward' => $redemption->reward_name,
            'stamps' => $redemption->stamps,
            'discount' => $redemption->discount,
            'by' => $redeemedBy?->name ?? 'Sistem',
        ];
    }
}
