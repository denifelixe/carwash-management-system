<?php

namespace Database\Factories;

use App\Models\OrderDeletion;
use App\Models\OrderDeletionAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDeletionAttachment>
 */
class OrderDeletionAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_deletion_id' => OrderDeletion::factory(),
            'disk' => 'local',
            'path' => 'order-deletions/'.fake()->uuid().'.jpg',
            'original_name' => 'bukti.jpg',
            'size' => 1024,
        ];
    }
}
