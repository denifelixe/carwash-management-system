<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderDeletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDeletion>
 */
class OrderDeletionFactory extends Factory
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
            'deleted_by_admin_id' => Admin::factory(),
            'deleted_by_name' => fake()->name(),
            'deleted_at' => now(),
        ];
    }
}
