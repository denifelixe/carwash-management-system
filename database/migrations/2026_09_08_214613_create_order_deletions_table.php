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
        Schema::create('order_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('previous_status');
            $table->foreignId('deleted_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('deleted_by_name');
            $table->dateTime('deleted_at');
            $table->datetimes();
            $table->index(['order_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_deletions');
    }
};
