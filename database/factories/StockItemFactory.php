<?php

namespace Database\Factories;

use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('???-###'),
            'name' => fake()->randomElement(['Snow Foam', 'Shampoo Konsentrat', 'Carnauba Wax', 'Microfiber Towel', 'Semir Ban']).' '.fake()->unique()->numerify('###'),
            'category' => fake()->randomElement(['Bahan Cuci', 'Detailing', 'Perlengkapan', 'Add-on', 'Interior']),
            'unit' => fake()->randomElement(['liter', 'galon', 'botol', 'pcs', 'set']),
            'quantity' => fake()->numberBetween(10, 80),
            'min_quantity' => fake()->numberBetween(1, 8),
            'unit_cost' => fake()->numberBetween(10, 500) * 1000,
            'supplier' => fake()->randomElement(['PT Kilau Kimia', 'Detail Pro ID', 'Toko Bersih Jaya', 'Aroma Nusantara']),
            'notes' => null,
            'is_active' => true,
        ];
    }

    /**
     * An item sitting at or under its reorder point.
     */
    public function lowStock(): static
    {
        return $this->state(fn (): array => [
            'quantity' => 2,
            'min_quantity' => 6,
        ]);
    }
}
