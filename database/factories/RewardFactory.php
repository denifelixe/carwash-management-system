<?php

namespace Database\Factories;

use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Gratis Semir Ban', 'Gratis Parfum Mobil', 'Gratis Cuci Mobil Reguler', 'Tumbler Eksklusif']).' '.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['🎁', '🚗', '💨', '🥤']),
            'category' => fake()->randomElement(['Add-on', 'Layanan', 'Diskon', 'Merchandise']),
            'required_stamps' => fake()->numberBetween(3, 12),
            'stock' => fake()->numberBetween(10, 50),
            'is_active' => true,
        ];
    }

    /**
     * A reward taken out of the catalog.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * A reward with nothing left to hand out.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (): array => [
            'stock' => 0,
        ]);
    }
}
