<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('reward_service');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('reward_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unique(['reward_id', 'service_id']);
        });

        $rows = DB::table('reward_service_variation')
            ->join('service_variations', 'service_variations.id', '=', 'reward_service_variation.service_variation_id')
            ->select(['reward_service_variation.reward_id', 'service_variations.service_id'])
            ->distinct()
            ->get();

        if ($rows->isNotEmpty()) {
            DB::table('reward_service')->insert($rows->map(fn (object $row): array => (array) $row)->all());
        }
    }
};
