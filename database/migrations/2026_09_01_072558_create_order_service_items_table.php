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
        Schema::create('order_service_items', function (Blueprint $table): void {
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_variation_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->json('variations')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('total_price');
            $table->unsignedSmallInteger('stamps');

            $table->primary(['order_id', 'service_variation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_service_items');
    }
};
