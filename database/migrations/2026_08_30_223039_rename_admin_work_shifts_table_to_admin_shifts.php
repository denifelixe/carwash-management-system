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
        Schema::rename('admin_work_shifts', 'admin_shifts');

        Schema::table('admin_shifts', function (Blueprint $table) {
            $table->renameIndex('admin_work_shifts_key_unique', 'admin_shifts_key_unique');
            $table->renameIndex('admin_work_shifts_is_active_index', 'admin_shifts_is_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_shifts', function (Blueprint $table) {
            $table->renameIndex('admin_shifts_key_unique', 'admin_work_shifts_key_unique');
            $table->renameIndex('admin_shifts_is_active_index', 'admin_work_shifts_is_active_index');
        });

        Schema::rename('admin_shifts', 'admin_work_shifts');
    }
};
