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
        /*
         * Reward sits directly under Member in the sidebar, pushing Leads and
         * Stock Inventory one place down.
         */
        DB::table('admin_modules')->where('key', 'rewards')->update(['sort_order' => 7]);
        DB::table('admin_modules')->where('key', 'leads')->update(['sort_order' => 8]);
        DB::table('admin_modules')->where('key', 'inventory')->update(['sort_order' => 9]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_modules')->where('key', 'leads')->update(['sort_order' => 7]);
        DB::table('admin_modules')->where('key', 'inventory')->update(['sort_order' => 8]);
        DB::table('admin_modules')->where('key', 'rewards')->update(['sort_order' => 9]);
    }
};
