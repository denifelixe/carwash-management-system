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

        /*
         * Leads sits directly under Member in the sidebar, so everything from
         * Stock Inventory onwards shifts one place down.
         */
        DB::table('admin_modules')->where('sort_order', '>=', 7)->increment('sort_order');

        DB::table('admin_modules')->upsert([
            ['key' => 'leads', 'name' => 'Leads', 'description' => 'Calon pelanggan (Non-member)', 'sort_order' => 7, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['name', 'description', 'sort_order', 'is_active', 'updated_at']);

        $moduleId = DB::table('admin_modules')->where('key', 'leads')->value('id');
        $managerId = DB::table('admin_roles')->where('key', 'manager')->value('id');

        if ($moduleId === null || $managerId === null) {
            return;
        }

        DB::table('admin_role_module')->upsert(
            [[
                'admin_role_id' => $managerId,
                'admin_module_id' => $moduleId,
                'can_create' => true,
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
        $moduleId = DB::table('admin_modules')->where('key', 'leads')->value('id');

        if ($moduleId === null) {
            return;
        }

        DB::table('admin_role_module')->where('admin_module_id', $moduleId)->delete();
        DB::table('admin_modules')->where('id', $moduleId)->delete();
        DB::table('admin_modules')->where('sort_order', '>=', 8)->decrement('sort_order');
    }
};
