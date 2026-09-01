<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = Service::factory()->create();
        $variation = $service->serviceVariations()->firstOrFail();
        $order = Order::factory()->create([
            'subtotal' => $service->price,
            'total' => $service->price,
            'stamps_earned' => $service->stamps,
        ]);
        $order->serviceVariations()->attach($variation, [
            'service_name' => $service->name,
            'variations' => null,
            'unit_price' => $service->price,
            'quantity' => 1,
            'total_price' => $service->price,
            'stamps' => $service->stamps,
        ]);
    }
}
