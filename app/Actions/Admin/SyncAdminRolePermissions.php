<?php

namespace App\Actions\Admin;

use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Support\Admin\AdminModuleActions;

class SyncAdminRolePermissions
{
    /**
     * @param  list<array{module_id: int, can_create: bool, can_read: bool, can_update: bool, can_delete: bool, additional_actions?: list<string>}>  $permissions
     */
    public function handle(AdminRole $role, array $permissions): void
    {
        $moduleKeys = AdminModule::query()
            ->whereKey(collect($permissions)->pluck('module_id'))
            ->pluck('key', 'id');

        $role->modules()->sync(collect($permissions)->mapWithKeys(
            function (array $permission) use ($moduleKeys): array {
                $allowedActions = array_column(
                    AdminModuleActions::for((string) $moduleKeys->get($permission['module_id'], '')),
                    'key',
                );

                return [$permission['module_id'] => [
                    'can_create' => $permission['can_create'],
                    'can_read' => $permission['can_read'],
                    'can_update' => $permission['can_update'],
                    'can_delete' => $permission['can_delete'],
                    'additional_actions' => json_encode(
                        array_values(array_intersect(
                            $permission['additional_actions'] ?? [],
                            $allowedActions,
                        )),
                        JSON_THROW_ON_ERROR,
                    ),
                ]];
            },
        )->all());
    }
}
