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
        Schema::create('daily_balance', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('cash_income')->default(0);
            $table->unsignedBigInteger('cash_expense')->default(0);
            $table->bigInteger('cash_balance')->default(0);
            $table->unsignedBigInteger('non_cash_income')->default(0);
            $table->unsignedBigInteger('non_cash_expense')->default(0);
            $table->bigInteger('non_cash_balance')->default(0);
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_balance');
    }
};
