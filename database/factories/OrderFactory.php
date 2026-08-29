<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->bothify('??????'),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('08##########'),
            'vehicle_name' => fake()->randomElement(['Toyota Avanza', 'Honda Brio', 'Yamaha NMax']),
            'vehicle_plate' => fake()->bothify('? #### ??'),
            /* The service day is the Jakarta business day, the way the order
             * module writes it — not the UTC day the app stores instants in. */
            'service_date' => now()->toDateString(),
            'arrived_at' => now(),
            'source' => 'walk-in',
            'status' => 'menunggu',
            'subtotal' => 45000,
            'discount' => 0,
            'total' => 45000,
            'paid_amount' => 0,
            'stamps_earned' => 0,
        ];
    }
}
