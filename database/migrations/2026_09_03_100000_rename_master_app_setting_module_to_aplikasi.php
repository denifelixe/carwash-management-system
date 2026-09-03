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
            ->where('key', 'master_app_settings')
            ->update([
                'name' => 'Aplikasi',
                'description' => 'Identitas, favicon, dan struk',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_modules')
            ->where('key', 'master_app_settings')
            ->update([
                'name' => 'App Setting',
                'description' => 'Nama, foto, dan favicon aplikasi',
                'updated_at' => now(),
            ]);
    }
};
