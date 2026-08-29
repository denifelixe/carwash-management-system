<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('admin_modules')
            ->where('key', 'customers')
            ->update(['key' => 'members']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_modules')
            ->where('key', 'members')
            ->update(['key' => 'customers']);
    }
};
