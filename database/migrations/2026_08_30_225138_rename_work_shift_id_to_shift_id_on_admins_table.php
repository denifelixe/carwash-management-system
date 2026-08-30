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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('admins', function (Blueprint $table) {
                $table->renameColumn('work_shift_id', 'shift_id');
            });

            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropForeign('admins_work_shift_id_foreign');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->renameColumn('work_shift_id', 'shift_id');
            $table->renameIndex('admins_work_shift_id_foreign', 'admins_shift_id_foreign');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->foreign('shift_id', 'admins_shift_id_foreign')
                ->references('id')
                ->on('admin_shifts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('admins', function (Blueprint $table) {
                $table->renameColumn('shift_id', 'work_shift_id');
            });

            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropForeign('admins_shift_id_foreign');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->renameColumn('shift_id', 'work_shift_id');
            $table->renameIndex('admins_shift_id_foreign', 'admins_work_shift_id_foreign');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->foreign('work_shift_id', 'admins_work_shift_id_foreign')
                ->references('id')
                ->on('admin_shifts')
                ->nullOnDelete();
        });
    }
};
