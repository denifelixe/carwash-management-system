<?php

use App\Models\Service;

test('the catalog migration applies the requested category order', function () {
    $categories = [
        'Cuci Mobil',
        'Paket Mobil',
        'Cuci Motor',
        'Detailing Mobil',
        'Detailing Motor',
        'Coating Mobil',
        'Coating Motor',
        'Add-on',
    ];

    $services = collect(array_reverse($categories))->map(
        fn (string $category, int $index): Service => Service::factory()->create([
            'name' => "Urutan {$category}",
            'category' => $category,
            'sort_order' => 100 + $index,
        ]),
    );
    $premiumPackage = Service::factory()->create([
        'name' => 'Urutan Paket Premium',
        'category' => 'Paket Premium Mobil',
        'sort_order' => 50,
    ]);
    $earlierCarWash = Service::factory()->create([
        'name' => 'Urutan Cuci Mobil Lebih Awal',
        'category' => 'Cuci Mobil',
        'sort_order' => 25,
    ]);

    $migration = require database_path('migrations/2026_09_01_190315_reorder_services_by_category.php');
    $migration->up();

    $targetIds = $services
        ->push($premiumPackage)
        ->push($earlierCarWash)
        ->pluck('id');
    $orderedServices = Service::query()
        ->whereKey($targetIds)
        ->orderBy('sort_order')
        ->get();

    expect($orderedServices->pluck('category')->unique()->values()->all())->toBe($categories)
        ->and($premiumPackage->refresh()->category)->toBe('Paket Mobil')
        ->and($orderedServices->where('category', 'Cuci Mobil')->first()->is($earlierCarWash))->toBeTrue()
        ->and(Service::query()->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe(range(1, Service::query()->count()));
});
