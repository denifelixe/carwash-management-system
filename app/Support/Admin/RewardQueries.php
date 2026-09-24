<?php

namespace App\Support\Admin;

use App\Models\Member;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads for the reward catalog and the redemptions made against it.
 */
class RewardQueries
{
    public const REDEMPTIONS_PER_PAGE = 10;

    /**
     * Offered in the category field alongside whatever the catalog already
     * uses, so a new outlet does not start from an empty list.
     *
     * @var list<string>
     */
    public const DEFAULT_CATEGORIES = ['Add-on', 'Layanan', 'Diskon', 'Merchandise'];

    /**
     * @return array{redemptionPage: int}
     */
    public static function filters(Request $request): array
    {
        return [
            'redemptionPage' => max(1, $request->integer('redemptionPage', 1)),
        ];
    }

    /**
     * The whole catalog. It is a short, hand-kept list, so it is sent in one go
     * and searched on the page rather than paginated.
     *
     * @return Collection<int, Reward>
     */
    public static function catalog(): Collection
    {
        return Reward::query()
            ->with('serviceVariations:id,service_id')
            ->withCount('redemptions')
            ->orderByDesc('is_active')
            ->orderBy('required_stamps')
            ->orderBy('name')
            ->get();
    }

    /**
     * What the cashier can offer and what the member portal lists. The POS
     * still checks stock and the stamp balance for each order.
     *
     * @return Collection<int, Reward>
     */
    public static function activeCatalog(): Collection
    {
        return Reward::query()
            ->active()
            ->with('serviceVariations:id,service_id')
            ->withCount('redemptions')
            ->orderBy('required_stamps')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function categoryOptions(): array
    {
        return array_values(array_unique([
            ...self::DEFAULT_CATEGORIES,
            ...Reward::query()->distinct()->orderBy('category')->pluck('category')->all(),
        ]));
    }

    /**
     * Services and variations for the picker, including retired selections.
     *
     * @return list<array{id: int, name: string, category: string, categoryGroup: string, icon: string, isActive: bool, variations: list<array{id: int, label: string, price: int, isActive: bool}>}>
     */
    public static function serviceOptions(): array
    {
        return array_values(Service::query()
            ->with(['serviceVariations' => fn ($query) => $query->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'category_group', 'icon', 'is_active'])
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
                'categoryGroup' => $service->category_group,
                'icon' => $service->icon,
                'isActive' => $service->is_active,
                'variations' => $service->serviceVariations->map(fn ($variation): array => [
                    'id' => $variation->id,
                    'label' => collect($variation->variations ?? [])->map(
                        fn (string $value, string $attribute): string => "$attribute: $value",
                    )->join(' · '),
                    'price' => (int) $variation->price,
                    'isActive' => $variation->is_active,
                ])->all(),
            ])
            ->all());
    }

    /**
     * The wallet balance of every active member who holds stamps. The page
     * uses it to count, for each reward, how many members could redeem it now.
     *
     * @return list<int>
     */
    public static function stampBalances(): array
    {
        return array_values(MemberStamps::withBalances(Member::query())
            ->where('is_active', true)
            ->get(['id'])
            ->map(fn (Member $member): int => MemberStamps::balance($member))
            ->filter(fn (int $balance): bool => $balance > 0)
            ->values()
            ->all());
    }

    /**
     * @return array{total: int, active: int, redeemed: int, circulatingStamps: int, members: int}
     */
    public static function stats(): array
    {
        return [
            'total' => Reward::query()->count(),
            'active' => Reward::query()->active()->count(),
            'redeemed' => RewardRedemption::query()->count(),
            'circulatingStamps' => MemberStamps::circulating(),
            'members' => Member::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @param  array{redemptionPage: int}  $filters
     * @return LengthAwarePaginator<int, RewardRedemption>
     */
    public static function redemptionPage(array $filters): LengthAwarePaginator
    {
        return RewardRedemption::query()
            ->with(['member:id,name', 'order:id,number', 'redeemedBy:id,name'])
            ->latest('redeemed_at')
            ->latest('id')
            ->paginate(self::REDEMPTIONS_PER_PAGE, page: $filters['redemptionPage'], pageName: 'redemptionPage');
    }

    /**
     * Stamps redeemed and rewards handed out on one business day, for the
     * dashboard card.
     *
     * @return array{stamps: int, rewards: int}
     */
    public static function redeemedOnDate(string $date): array
    {
        $redemptions = RewardRedemption::query()->whereDate('redeemed_at', $date);

        return [
            'stamps' => (int) (clone $redemptions)->sum('stamps'),
            'rewards' => (clone $redemptions)->count(),
        ];
    }
}
