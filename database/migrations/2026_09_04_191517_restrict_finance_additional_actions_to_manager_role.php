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
        $this->updateFinanceRoleActions(
            fn (array $actions): array => array_values(array_diff($actions, [
                'edit_cash_entry_backdate',
                'view_non_cash_balance',
            ])),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateFinanceRoleActions(function (array $actions): array {
            $actions[] = 'edit_cash_entry_backdate';
            $actions[] = 'view_non_cash_balance';

            return array_values(array_unique($actions));
        });
    }

    /**
     * @param  callable(list<string>): list<string>  $transform
     */
    private function updateFinanceRoleActions(callable $transform): void
    {
        $financeRoleId = DB::table('admin_roles')
            ->where('key', 'finance')
            ->value('id');
        $financeModuleId = DB::table('admin_modules')
            ->where('key', 'finance')
            ->value('id');

        if ($financeRoleId === null || $financeModuleId === null) {
            return;
        }

        $actions = DB::table('admin_role_module')
            ->where('admin_role_id', $financeRoleId)
            ->where('admin_module_id', $financeModuleId)
            ->value('additional_actions');
        $decodedActions = json_decode(
            (string) ($actions ?? '[]'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        DB::table('admin_role_module')
            ->where('admin_role_id', $financeRoleId)
            ->where('admin_module_id', $financeModuleId)
            ->update([
                'additional_actions' => json_encode(
                    $transform(is_array($decodedActions) ? $decodedActions : []),
                    JSON_THROW_ON_ERROR,
                ),
            ]);
    }
};
