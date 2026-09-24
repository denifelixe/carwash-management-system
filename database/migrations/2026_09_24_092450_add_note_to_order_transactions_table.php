<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cashier's own line for one payment, printed on its slip under
     * LUNAS (MoM 17 Sep 2026). Unlike Master > Struk's notes it differs per
     * transaction.
     */
    public function up(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('channel_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
