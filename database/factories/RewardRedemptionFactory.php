<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Order;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardRedemption>
 */
class RewardRedemptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'order_id' => fn (array $attributes): int => Order::factory()->create([
                'member_id' => $attributes['member_id'],
                'status' => 'selesai',
            ])->id,
            'reward_id' => Reward::factory(),
            'reward_name' => fake()->randomElement(['Gratis Semir Ban', 'Gratis Parfum Mobil']),
            'stamps' => fake()->numberBetween(3, 10),
            'discount' => 0,
            'redeemed_at' => now(),
        ];
    }
}
