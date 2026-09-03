<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('08##########'),
            'vehicle_name' => fake()->randomElement(['Toyota Avanza', 'Honda Brio', 'Daihatsu Xenia']),
            'vehicle_plate' => fake()->unique()->bothify('? #### ??'),
            'notes' => null,
            'is_active' => true,
            'converted_member_id' => null,
            'converted_at' => null,
        ];
    }
}
