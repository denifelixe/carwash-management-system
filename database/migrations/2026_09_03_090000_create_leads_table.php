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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('vehicle_name')->nullable();
            /*
             * A lead is identified by the car it arrived in, not by its name:
             * the plate is the one field the counter always has, and the one
             * the next visit can be matched on. Hence the unique index.
             */
            $table->string('vehicle_plate')->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('converted_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTime('converted_at')->nullable();
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
