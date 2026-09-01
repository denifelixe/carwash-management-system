<?php

namespace App\Http\Controllers\Demo;

use App\Support\Admin\ServiceIcons;
use App\Support\Demo\Catalog;
use App\Support\Demo\Operations;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Master data for the service catalog (BR-03), sharing the live module page.
 */
class ServiceController extends AdminController
{
    public function index(Request $request): Response
    {
        $catalog = Catalog::services();

        /** The fixture order is the catalog order the demo console shows. */
        $services = array_map(
            fn (array $service, int $index): array => [
                'id' => $service['id'],
                'name' => $service['name'],
                'category' => $service['category'],
                'price' => $service['price'],
                'variations' => $service['variations'],
                'service_variations' => array_map(fn (array $variation): array => [
                    'id' => $variation['id'],
                    'variations' => $variation['variations'],
                    'price' => $variation['price'],
                    'is_active' => $variation['isActive'],
                    'order_count' => self::orderCountFor($service['id']),
                ], $service['serviceVariations']),
                'stamps' => $service['stamps'],
                'icon' => $service['icon'],
                'description' => $service['description'],
                'is_popular' => $service['popular'],
                'is_active' => $service['isActive'],
                'sort_order' => $index + 1,
                'order_count' => self::orderCountFor($service['id']),
            ],
            $catalog,
            array_keys($catalog),
        );

        return $this->page($request, 'admin/master/Services', [
            'services' => $services,
            'categories' => array_values(array_unique(array_column($services, 'category'))),
            'icons' => ServiceIcons::options(),
            'capabilities' => ['create' => true, 'update' => true, 'delete' => true],
        ]);
    }

    /**
     * Orders in the demo fixtures that already booked the given service.
     */
    private static function orderCountFor(int $serviceId): int
    {
        return count(array_filter(
            Operations::orders(),
            fn (array $order): bool => in_array($serviceId, $order['serviceIds'], true),
        ));
    }
}
