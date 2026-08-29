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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('crew_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('vehicle_name');
            $table->string('vehicle_plate');
            $table->date('service_date')->index();
            $table->timestamp('arrived_at')->nullable();
            $table->date('booking_date')->nullable();
            $table->string('source')->default('walk-in');
            $table->string('status')->default('menunggu')->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedSmallInteger('stamps_earned')->default(0);
            $table->string('reward_name')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('bay')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['service_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
