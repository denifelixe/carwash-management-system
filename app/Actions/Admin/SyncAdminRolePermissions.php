<?php

namespace App\Actions\Admin;

use App\Models\AdminRole;

class SyncAdminRolePermissions
{
    /**
     * @param  list<array{module_id: int, can_create: bool, can_read: bool, can_update: bool, can_delete: bool}>  $permissions
     */
    public function handle(AdminRole $role, array $permissions): void
    {
        $role->modules()->sync(collect($permissions)->mapWithKeys(
            fn (array $permission): array => [
                $permission['module_id'] => [
                    'can_create' => $permission['can_create'],
                    'can_read' => $permission['can_read'],
                    'can_update' => $permission['can_update'],
                    'can_delete' => $permission['can_delete'],
                ],
            ],
        )->all());
    }
}
