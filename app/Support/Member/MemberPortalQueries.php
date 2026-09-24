<?php

namespace App\Support\Member;

use App\Models\Member;
use App\Models\Order;
use App\Models\Reward;
use App\Models\Service;
use App\Support\Admin\MemberStamps;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\OrderQueries;
use App\Support\Admin\RewardPresenter;
use App\Support\Admin\RewardQueries;
use App\Support\Demo\DateFilter;

/**
 * Reads for the live member portal. Every query is scoped to the signed-in
 * member, and the shapes are the ones the demo portal fixtures use, because
 * both render the same pages.
 */
class MemberPortalQueries
{
    /** How far back the portal lists visits and stamp movements. */
    public const HISTORY_LIMIT = 30;

    /**
     * The member card: identity, vehicles, and the stamp wallet.
     *
     * @return array<string, mixed>
     */
    public static function member(Member $member): array
    {
        /** @var Member $member */
        $member = OrderQueries::withMemberAggregates(Member::query())->findOrFail($member->getKey());

        return [
            ...OrderPresenter::customer($member),
            'joinedAt' => $member->created_at !== null
                ? DateFilter::format($member->created_at->toDateString())
                : '',
            'referralCode' => null,
        ];
    }

    /**
     * @return list<array{id: string, title: string, detail: string, stamps: int, type: string, date: string, icon: string}>
     */
    public static function stampHistory(Member $member): array
    {
        return MemberStamps::history($member, self::HISTORY_LIMIT);
    }

    /**
     * The member's visits, newest first. A visit still in progress shows up
     * too, but its stamps are only added once it is completed or paid in full.
     *
     * @return list<array{id: int, service: string, vehicle: string, date: string, total: int, stamps: int, rating: int, status: string}>
     */
    public static function washHistory(Member $member): array
    {
        return array_values(Order::query()
            ->whereBelongsTo($member)
            ->whereNotIn('status', ['batal', 'booking'])
            ->with('serviceVariations:id,service_id')
            ->latest('service_date')
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'service' => $order->serviceVariations->pluck('pivot.service_name')->unique()->join(', ') ?: $order->number,
                'vehicle' => $order->vehicle_plate,
                'date' => DateFilter::format($order->service_date->toDateString()),
                'total' => (int) $order->total,
                'stamps' => $order->hasEarnedStamps() ? (int) $order->stamps_earned : 0,
                /* The live portal does not collect ratings; 0 hides the stars. */
                'rating' => 0,
                'status' => $order->status,
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rewards(): array
    {
        return array_values(RewardQueries::activeCatalog()
            ->map(fn (Reward $reward): array => RewardPresenter::reward($reward))
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function services(): array
    {
        return array_values(Service::query()
            ->where('is_active', true)
            ->with(['serviceVariations' => fn ($query) => $query->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Service $service): array => OrderPresenter::service($service))
            ->all());
    }

    /**
     * The category chips for a list of services or rewards, in list order.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public static function categoriesOf(array $rows): array
    {
        return array_values(array_unique(array_map(
            fn (array $row): string => (string) $row['category'],
            $rows,
        )));
    }
}
