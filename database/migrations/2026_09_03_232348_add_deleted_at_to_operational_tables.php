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
            $table->dateTime('deleted_at')->nullable()->index()->after('updated_at');
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable()->index()->after('updated_at');
        });

        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable()->index()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
