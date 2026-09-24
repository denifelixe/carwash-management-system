<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('reward_service')
            ->join('service_variations', 'service_variations.service_id', '=', 'reward_service.service_id')
            ->select(['reward_service.reward_id', 'service_variations.id as service_variation_id'])
            ->orderBy('reward_service.reward_id')
            ->orderBy('service_variations.id')
            ->chunk(200, function ($rows): void {
                DB::table('reward_service_variation')->insert($rows->map(fn (object $row): array => [
                    'reward_id' => $row->reward_id,
                    'service_variation_id' => $row->service_variation_id,
                    'quantity' => 1,
                    'discount_percent' => 100,
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('reward_service_variation')->delete();
    }
};
