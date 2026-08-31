<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            ['name' => 'Cuci Mobil Reguler', 'category' => 'Cuci Mobil', 'price' => 45000, 'stamps' => 1, 'icon' => '🚗', 'description' => 'Cuci body, sela ban, dan lap kering menyeluruh.', 'is_popular' => true, 'sort_order' => 1],
            ['name' => 'Cuci Mobil + Wax', 'category' => 'Cuci Mobil', 'price' => 85000, 'stamps' => 1, 'icon' => '✨', 'description' => 'Cuci reguler dan lapisan wax.', 'is_popular' => true, 'sort_order' => 2],
            ['name' => 'Snow Wash Premium', 'category' => 'Cuci Mobil', 'price' => 120000, 'stamps' => 2, 'icon' => '❄️', 'description' => 'Busa salju pH netral.', 'is_popular' => true, 'sort_order' => 3],
            ['name' => 'Cuci Motor Reguler', 'category' => 'Cuci Motor', 'price' => 20000, 'stamps' => 1, 'icon' => '🏍️', 'description' => 'Cuci motor dengan pengeringan blower.', 'is_popular' => false, 'sort_order' => 4],
            ['name' => 'Deep Clean Interior', 'category' => 'Interior', 'price' => 150000, 'stamps' => 2, 'icon' => '🧽', 'description' => 'Pembersihan menyeluruh interior kendaraan.', 'is_popular' => true, 'sort_order' => 5],
        ];

        foreach ($services as $service) {
            Service::query()->updateOrCreate(
                ['name' => $service['name']],
                [...$service, 'is_active' => true],
            );
        }
    }
}
