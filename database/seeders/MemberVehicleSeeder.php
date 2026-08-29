<?php

namespace Database\Seeders;

use App\Models\MemberVehicle;
use Illuminate\Database\Seeder;

class MemberVehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MemberVehicle::factory()->create();
    }
}
