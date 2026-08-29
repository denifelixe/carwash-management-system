<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberVehicle>
 */
class MemberVehicleFactory extends Factory
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
            'name' => fake()->randomElement(['Toyota Avanza', 'Honda Brio', 'Yamaha NMax']),
            'plate' => fake()->unique()->bothify('? #### ??'),
            'type' => fake()->randomElement(['Mobil', 'Motor']),
            'is_primary' => true,
        ];
    }
}
