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
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->index()->after('is_active');
        });

        /** Keep the catalog looking exactly as it did before the column existed. */
        DB::table('services')
            ->orderBy('category')
            ->orderBy('name')
            ->pluck('id')
            ->each(function (int $id, int $index): void {
                DB::table('services')->where('id', $id)->update(['sort_order' => $index + 1]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
