<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('order_services');
        Schema::rename('order_service_items', 'order_services');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Migrasi order ke service variation tidak dapat dibatalkan tanpa kehilangan snapshot baru.');
    }
};
