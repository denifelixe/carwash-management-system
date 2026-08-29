<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SyncAdminRolePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRoleRequest;
use App\Http\Requests\Admin\UpdateAdminRoleRequest;
use App\Models\AdminRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminRoleController extends Controller
{
    public function store(
        StoreAdminRoleRequest $request,
        SyncAdminRolePermissions $syncPermissions,
    ): RedirectResponse {
        $validated = $request->validated();
        $permissions = $validated['permissions'];
        unset($validated['permissions']);

        DB::transaction(function () use ($validated, $permissions, $syncPermissions): void {
            $role = AdminRole::query()->create([
                ...$validated,
                'key' => $this->uniqueKey($validated['name']),
            ]);
            $syncPermissions->handle($role, $permissions);
        });

        return to_route('admin.users.index')->with('success', 'Role berhasil ditambahkan.');
    }

    public function update(
        UpdateAdminRoleRequest $request,
        AdminRole $adminRole,
        SyncAdminRolePermissions $syncPermissions,
    ): RedirectResponse {
        $validated = $request->validated();
        $permissions = $validated['permissions'];
        unset($validated['permissions']);

        DB::transaction(function () use ($adminRole, $validated, $permissions, $syncPermissions): void {
            $adminRole->update($validated);
            $syncPermissions->handle($adminRole, $permissions);
        });

        return to_route('admin.users.index')->with('success', 'Role dan hak akses berhasil diperbarui.');
    }

    private function uniqueKey(string $name): string
    {
        $baseKey = Str::of($name)->slug('_')->lower()->toString();
        $key = $baseKey;
        $suffix = 2;

        while (AdminRole::query()->where('key', $key)->exists()) {
            $key = "{$baseKey}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
