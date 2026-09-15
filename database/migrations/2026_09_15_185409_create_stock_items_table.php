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
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            /* The SKU is the item's identity at the counter, so it is unique. */
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->string('unit', 32);
            /*
             * On hand is denormalised so the list does not sum the whole
             * movement log per row. Its only writer is RecordStockMovement,
             * which locks this row first. Signed rather than unsigned so a
             * counting bug surfaces as a readable negative instead of a 500
             * from strict mode; the invariant itself is enforced in validation.
             */
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('min_quantity')->default(0);
            $table->unsignedInteger('unit_cost')->default(0);
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
