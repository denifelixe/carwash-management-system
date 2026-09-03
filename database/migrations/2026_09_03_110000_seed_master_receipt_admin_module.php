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
        $now = now();

        /* The slip settings left the App Setting page, so its blurb loses them. */
        DB::table('admin_modules')
            ->where('key', 'master_app_settings')
            ->update(['description' => 'Identitas, favicon, dan metadata', 'updated_at' => $now]);

        DB::table('admin_modules')->upsert([
            ['key' => 'master_receipt', 'name' => 'Struk', 'description' => 'Nama bisnis, catatan kaki, dan QR struk', 'sort_order' => 16, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['name', 'description', 'sort_order', 'is_active', 'updated_at']);

        $moduleId = DB::table('admin_modules')->where('key', 'master_receipt')->value('id');
        $managerId = DB::table('admin_roles')->where('key', 'manager')->value('id');

        if ($moduleId === null || $managerId === null) {
            return;
        }

        DB::table('admin_role_module')->upsert(
            [[
                'admin_role_id' => $managerId,
                'admin_module_id' => $moduleId,
                'can_create' => false,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => false,
            ]],
            ['admin_role_id', 'admin_module_id'],
            ['can_create', 'can_read', 'can_update', 'can_delete'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_modules')
            ->where('key', 'master_app_settings')
            ->update(['description' => 'Identitas, favicon, dan struk', 'updated_at' => now()]);

        $moduleId = DB::table('admin_modules')->where('key', 'master_receipt')->value('id');

        if ($moduleId === null) {
            return;
        }

        DB::table('admin_role_module')->where('admin_module_id', $moduleId)->delete();
        DB::table('admin_modules')->where('id', $moduleId)->delete();
    }
};
