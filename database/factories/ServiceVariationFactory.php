<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceVariation>
 */
class ServiceVariationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'variations' => null,
            'price' => fake()->numberBetween(20_000, 2_000_000),
            'is_active' => true,
        ];
    }
}
