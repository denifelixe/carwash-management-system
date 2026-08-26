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
        Schema::create('admin_role_module', function (Blueprint $table) {
            $table->foreignId('admin_role_id')
                ->constrained('admin_roles')
                ->cascadeOnDelete();
            $table->foreignId('admin_module_id')
                ->constrained('admin_modules')
                ->cascadeOnDelete();
            $table->boolean('can_create')->default(false);
            $table->boolean('can_read')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);

            $table->unique(['admin_role_id', 'admin_module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_role_module');
    }
};
