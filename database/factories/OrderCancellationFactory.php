<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCancellation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCancellation>
 */
class OrderCancellationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'reason' => fake()->sentence(),
            'previous_status' => 'menunggu',
            'cancelled_by_admin_id' => Admin::factory(),
            'cancelled_by_name' => fake()->name(),
            'cancelled_at' => now(),
        ];
    }
}
