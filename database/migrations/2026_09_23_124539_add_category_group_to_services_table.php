<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The order picker opens on a handful of groups (Cuci, Detailing, Coating,
     * Paket, Add-on) above the categories (MoM 17 Sep 2026). Existing rows are
     * grouped by the first word of their category, which is how the catalog
     * is already named ("Cuci Mobil" → Cuci); Master > Layanan can change it.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('category_group', 100)->default('')->after('category');
        });

        DB::table('services')->select(['id', 'category'])->orderBy('id')->each(function (object $service): void {
            DB::table('services')
                ->where('id', $service->id)
                ->update(['category_group' => strtok(trim((string) $service->category), ' ') ?: 'Lainnya']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('category_group');
        });
    }
};
