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
        DB::table('admins')
            ->where('email', 'deni.victoria@gmail.com')
            ->update(['is_hidden' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admins')
            ->where('email', 'deni.victoria@gmail.com')
            ->update(['is_hidden' => false]);
    }
};
