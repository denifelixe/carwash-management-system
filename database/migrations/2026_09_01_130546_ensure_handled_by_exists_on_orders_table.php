<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'handled_by')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('handled_by')->nullable()->after('handled_by_admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * This migration repairs databases where the earlier migration was
         * recorded before its column definition existed. The original
         * migration remains responsible for dropping the column on rollback.
         */
    }
};
