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

        DB::table('admin_work_shifts')->upsert([
            ['key' => 'morning', 'name' => 'Shift Pagi', 'starts_at' => '08:00:00', 'ends_at' => '16:00:00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'evening', 'name' => 'Shift Sore', 'starts_at' => '16:00:00', 'ends_at' => '23:00:00', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['name', 'starts_at', 'ends_at', 'is_active', 'updated_at']);

        DB::table('admin_roles')->upsert([
            ['key' => 'manager', 'name' => 'Manager', 'description' => 'Akses penuh operasional dan manajemen bisnis.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'cashier', 'name' => 'Kasir', 'description' => 'Menangani POS, member, booking, dan stok.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'cs', 'name' => 'CS / Front Office', 'description' => 'Menangani member dan membuat order.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'finance', 'name' => 'Finance', 'description' => 'Mengelola keuangan dan laporan.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['name', 'description', 'is_active', 'updated_at']);

        $matrix = [
            'manager' => ['dashboard', 'orders', 'pos', 'customers', 'finance', 'bookings', 'inventory', 'rewards', 'reports'],
            'cashier' => ['pos', 'customers', 'finance', 'bookings', 'inventory'],
            'cs' => ['customers', 'orders'],
            'finance' => ['finance', 'reports'],
        ];
        $roles = DB::table('admin_roles')->whereIn('key', array_keys($matrix))->pluck('id', 'key');
        $modules = DB::table('admin_modules')->pluck('id', 'key');
        $permissions = [];

        foreach ($matrix as $roleKey => $moduleKeys) {
            foreach ($moduleKeys as $moduleKey) {
                $permissions[] = [
                    'admin_role_id' => $roles[$roleKey],
                    'admin_module_id' => $modules[$moduleKey],
                    'can_create' => $moduleKey !== 'dashboard',
                    'can_read' => true,
                    'can_update' => $moduleKey !== 'dashboard',
                    'can_delete' => false,
                ];
            }
        }

        DB::table('admin_role_module')->upsert(
            $permissions,
            ['admin_role_id', 'admin_module_id'],
            ['can_create', 'can_read', 'can_update', 'can_delete'],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleIds = DB::table('admin_roles')
            ->whereIn('key', ['manager', 'cashier', 'cs', 'finance'])
            ->pluck('id');

        DB::table('admin_role_module')->whereIn('admin_role_id', $roleIds)->delete();
        DB::table('admin_roles')->whereIn('id', $roleIds)->delete();
        DB::table('admin_work_shifts')->whereIn('key', ['morning', 'evening'])->delete();
    }
};
