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
        $roleIds = DB::table('admin_roles')
            ->whereIn('key', ['manager', 'finance'])
            ->pluck('id');
        $financeModuleId = DB::table('admin_modules')
            ->where('key', 'finance')
            ->value('id');

        if ($financeModuleId === null) {
            return;
        }

        DB::table('admin_role_module')
            ->whereIn('admin_role_id', $roleIds)
            ->where('admin_module_id', $financeModuleId)
            ->update([
                'additional_actions' => json_encode(['view_non_cash_balance'], JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleIds = DB::table('admin_roles')
            ->whereIn('key', ['manager', 'finance'])
            ->pluck('id');
        $financeModuleId = DB::table('admin_modules')
            ->where('key', 'finance')
            ->value('id');

        if ($financeModuleId === null) {
            return;
        }

        DB::table('admin_role_module')
            ->whereIn('admin_role_id', $roleIds)
            ->where('admin_module_id', $financeModuleId)
            ->update(['additional_actions' => null]);
    }
};
