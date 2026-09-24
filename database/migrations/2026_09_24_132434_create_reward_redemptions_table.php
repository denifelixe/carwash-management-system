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
        /*
         * The debit side of the stamp wallet. A member's balance is the stamps
         * earned on their completed orders minus the rows here, so a redemption
         * is voided by soft-deleting it, never by editing the stamps.
         */
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            /* One reward per order, enforced here as well as in the payment. */
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('redeemed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            /* Snapshots, so an edited or deleted reward does not rewrite history. */
            $table->string('reward_name');
            $table->unsignedSmallInteger('stamps');
            $table->unsignedInteger('discount')->default(0);
            $table->dateTime('redeemed_at')->index();
            $table->datetimes();
            $table->dateTime('deleted_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};
