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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            /* The signed delta actually applied, so ins, outs and corrections sum. */
            $table->integer('quantity');
            /*
             * Before and after are frozen onto the row so the history stays
             * truthful even after the item is edited later — the same audit
             * habit order_deletions follows.
             */
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            /* What the goods cost on this restock; 0 for outs and corrections. */
            $table->unsignedInteger('unit_cost')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->datetimes();

            $table->index(['stock_item_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
