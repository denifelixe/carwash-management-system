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
            ->where('key', 'reports')
            ->update(['sort_order' => 9]);

        DB::table('admin_modules')
            ->where('key', 'users_and_roles')
            ->update(['sort_order' => 10]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_modules')
            ->where('key', 'users_and_roles')
            ->update(['sort_order' => 9]);

        DB::table('admin_modules')
            ->where('key', 'reports')
            ->update(['sort_order' => 10]);
    }
};
