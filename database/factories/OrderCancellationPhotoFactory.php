<?php

namespace Database\Factories;

use App\Models\OrderCancellation;
use App\Models\OrderCancellationPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderCancellationPhoto>
 */
class OrderCancellationPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_cancellation_id' => OrderCancellation::factory(),
            'disk' => 'local',
            'path' => 'order-cancellations/'.fake()->uuid().'.jpg',
            'original_name' => 'bukti.jpg',
            'size' => 1024,
        ];
    }
}
