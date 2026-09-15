<?php

namespace Database\Factories;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 12);

        return [
            'stock_item_id' => StockItem::factory(),
            'type' => 'masuk',
            'quantity' => $quantity,
            'quantity_before' => 0,
            'quantity_after' => $quantity,
            'unit_cost' => 0,
            'note' => null,
            'recorded_by_admin_id' => null,
            'recorded_at' => now(),
        ];
    }
}
