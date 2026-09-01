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
        Schema::table('service_variations', function (Blueprint $table): void {
            $table->dropUnique(['legacy_service_id']);
            $table->dropColumn('legacy_service_id');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_group_id');
            $table->dropColumn('price');
        });

        Schema::drop('service_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Penghapusan schema service group dan base price bersifat satu arah.');
    }
};
