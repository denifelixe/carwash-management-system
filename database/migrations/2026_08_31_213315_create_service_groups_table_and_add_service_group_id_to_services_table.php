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
        Schema::create('service_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->datetimes();
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('service_group_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_group_id');
        });

        Schema::dropIfExists('service_groups');
    }
};
