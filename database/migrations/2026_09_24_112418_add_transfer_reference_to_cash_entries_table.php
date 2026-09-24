<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Setor Tunai is written as two entries — Tunai out, Setor Tunai in —
     * that must be edited and deleted together (MoM 17 Sep 2026). This key
     * ties the pair; a plain entry leaves it null.
     */
    public function up(): void
    {
        Schema::table('cash_entries', function (Blueprint $table) {
            $table->string('transfer_reference', 64)->nullable()->after('reference')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_entries', function (Blueprint $table) {
            $table->dropIndex(['transfer_reference']);
            $table->dropColumn('transfer_reference');
        });
    }
};
