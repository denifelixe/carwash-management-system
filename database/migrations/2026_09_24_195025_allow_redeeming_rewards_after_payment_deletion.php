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
        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1);
        });

        DB::table('reward_redemptions')->whereNotNull('deleted_at')->update(['active_slot' => null]);

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->unique(['order_id', 'active_slot']);
        });

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('reward_redemptions')
            ->select('order_id')
            ->groupBy('order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Reward redemptions contain multiple historical rows for one order.');
        }

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->unique('order_id');
        });

        Schema::table('reward_redemptions', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'active_slot']);
            $table->dropColumn('active_slot');
        });
    }
};
