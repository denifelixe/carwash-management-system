<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /* The service catalogue is imported from database/sql/, not seeded:
         * see the header of services_speedtuner_cibinong.sql. ServiceSeeder
         * stays available for sample data via db:seed --class=ServiceSeeder. */

        // Admin::factory(10)->create();

        Admin::factory()->create([
            'name' => 'Test Admin',
            'email' => 'test@example.com',
        ]);
    }
}
