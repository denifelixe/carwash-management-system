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
        $order = Order::factory()->create([
            'subtotal' => $service->price,
            'total' => $service->price,
            'stamps_earned' => $service->stamps,
        ]);
        $order->services()->attach($service, [
            'service_name' => $service->name,
            'unit_price' => $service->price,
            'stamps' => $service->stamps,
        ]);
    }
}
