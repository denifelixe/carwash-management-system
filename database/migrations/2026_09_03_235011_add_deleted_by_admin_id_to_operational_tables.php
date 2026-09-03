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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('deleted_by_admin_id')
                ->nullable()
                ->after('deleted_at')
                ->constrained('admins')
                ->nullOnDelete();
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            $table->foreignId('deleted_by_admin_id')
                ->nullable()
                ->after('deleted_at')
                ->constrained('admins')
                ->nullOnDelete();
        });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->foreignId('deleted_by_admin_id')
                ->nullable()
                ->after('deleted_at')
                ->constrained('admins')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_admin_id');
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_admin_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_admin_id');
        });
    }
};
