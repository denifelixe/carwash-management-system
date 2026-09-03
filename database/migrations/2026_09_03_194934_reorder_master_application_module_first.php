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
        $this->setMasterModuleOrder([
            'master_app_settings' => 11,
            'master_services' => 12,
            'master_work_shifts' => 13,
            'master_timezone' => 14,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setMasterModuleOrder([
            'master_services' => 11,
            'master_work_shifts' => 12,
            'master_timezone' => 13,
            'master_app_settings' => 14,
        ]);
    }

    /**
     * @param  array<string, int>  $sortOrders
     */
    private function setMasterModuleOrder(array $sortOrders): void
    {
        foreach ($sortOrders as $moduleKey => $sortOrder) {
            DB::table('admin_modules')
                ->where('key', $moduleKey)
                ->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);
        }
    }
};
