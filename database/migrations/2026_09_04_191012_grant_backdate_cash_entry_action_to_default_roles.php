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
        $this->updateActions(function (array $actions): array {
            $actions[] = 'edit_cash_entry_backdate';

            return array_values(array_unique($actions));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateActions(
            fn (array $actions): array => array_values(array_filter(
                $actions,
                fn (string $action): bool => $action !== 'edit_cash_entry_backdate',
            )),
        );
    }

    /**
     * @param  callable(list<string>): list<string>  $transform
     */
    private function updateActions(callable $transform): void
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

        $assignments = DB::table('admin_role_module')
            ->whereIn('admin_role_id', $roleIds)
            ->where('admin_module_id', $financeModuleId)
            ->get(['admin_role_id', 'additional_actions']);

        foreach ($assignments as $assignment) {
            $actions = json_decode(
                (string) ($assignment->additional_actions ?? '[]'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            DB::table('admin_role_module')
                ->where('admin_role_id', $assignment->admin_role_id)
                ->where('admin_module_id', $financeModuleId)
                ->update([
                    'additional_actions' => json_encode(
                        $transform(is_array($actions) ? $actions : []),
                        JSON_THROW_ON_ERROR,
                    ),
                ]);
        }
    }
};
