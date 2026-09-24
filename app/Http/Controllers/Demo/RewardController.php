<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\Paginated;
use App\Support\Admin\RewardQueries;
use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Reward catalog and stamp requirements (BR-13), sharing the live module page.
 */
class RewardController extends AdminController
{
    public function index(Request $request): Response
    {
        $filters = RewardQueries::filters($request);
        $rewards = Catalog::rewards();
        $customers = Customers::all();

        return $this->page($request, 'admin/Rewards', [
            'rewards' => $rewards,
            'redemptions' => Paginated::fromArray(
                Catalog::rewardRedemptions(),
                $filters['redemptionPage'],
                RewardQueries::REDEMPTIONS_PER_PAGE,
            ),
            'stats' => [
                'total' => count($rewards),
                'active' => count(array_filter($rewards, fn (array $reward): bool => $reward['status'] === 'aktif')),
                'redeemed' => array_sum(array_column($rewards, 'redeemed')),
                'circulatingStamps' => array_sum(array_column($customers, 'stamps')),
                'members' => count(array_filter($customers, fn (array $customer): bool => $customer['status'] === 'aktif')),
            ],
            'stampBalances' => array_values(array_filter(
                array_column($customers, 'stamps'),
                fn (int $stamps): bool => $stamps > 0,
            )),
            'serviceOptions' => array_map(
                fn (array $service): array => [
                    'id' => $service['id'],
                    'name' => $service['name'],
                    'category' => $service['category'],
                    'categoryGroup' => $service['categoryGroup'],
                    'icon' => $service['icon'],
                    'isActive' => $service['isActive'],
                    'variations' => array_map(fn (array $variation): array => [
                        'id' => $variation['id'],
                        'label' => collect($variation['variations'] ?? [])->map(
                            fn (string $value, string $attribute): string => "$attribute: $value",
                        )->join(' · '),
                        'price' => $variation['price'],
                        'isActive' => $variation['isActive'],
                    ], $service['serviceVariations']),
                ],
                Catalog::services(),
            ),
            'filters' => $filters,
            'capabilities' => ['create' => true, 'update' => true, 'delete' => true],
        ]);
    }
}
