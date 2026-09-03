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
        $icons = [
            'manager' => '🧑‍💼',
            'cashier' => '💳',
            'cs' => '🎧',
            'finance' => '🧾',
        ];

        foreach ($icons as $key => $icon) {
            DB::table('admin_roles')->where('key', $key)->update(['icon' => $icon]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_roles')
            ->whereIn('key', ['manager', 'cashier', 'cs', 'finance'])
            ->update(['icon' => '🛡️']);
    }
};
