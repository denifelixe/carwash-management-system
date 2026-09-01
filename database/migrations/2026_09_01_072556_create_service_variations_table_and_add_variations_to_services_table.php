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
        Schema::table('services', function (Blueprint $table): void {
            $table->json('variations')->nullable()->after('category');
        });

        Schema::create('service_variations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_service_id')->nullable()->unique();
            $table->json('variations')->nullable();
            $table->unsignedBigInteger('price');
            $table->boolean('is_active')->default(true)->index();
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_variations');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('variations');
        });
    }
};
