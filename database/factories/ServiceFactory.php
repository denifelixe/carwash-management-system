<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceVariation;
use App\Support\Admin\ServiceIcons;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Service $service): void {
            if (! $service->serviceVariations()->exists()) {
                ServiceVariation::factory()->for($service)->create([
                    'price' => $service->pendingVariationPrice(),
                    'is_active' => $service->is_active,
                ]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'category' => fake()->randomElement(['Cuci Mobil', 'Cuci Motor', 'Detailing', 'Interior', 'Add-on']),
            'variations' => null,
            'price' => fake()->numberBetween(2, 150) * 5000,
            'stamps' => fake()->numberBetween(0, 5),
            'icon' => ServiceIcons::DEFAULT,
            'description' => fake()->sentence(),
            'is_popular' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
