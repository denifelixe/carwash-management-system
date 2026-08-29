<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderTransaction>
 */
class OrderTransactionFactory extends Factory
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
            'reference' => 'TRX-'.fake()->unique()->bothify('########'),
            'type' => 'Pembayaran Sebagian',
            'amount' => 20000,
            'channel_breakdown' => [['label' => 'Tunai', 'amount' => 20000]],
            'paid_at' => now(),
        ];
    }
}
