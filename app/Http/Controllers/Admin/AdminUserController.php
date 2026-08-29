<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserShiftRequest;
use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Models\AdminWorkShift;
use App\Support\Admin\AdminShell;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.users_and_roles.read');

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');
        $modules = AdminModule::query()->where('is_active', true)->orderBy('sort_order')->get();
        $roles = AdminRole::query()
            ->withCount('admins')
            ->with(['modules' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('name')
            ->get();
        $admins = Admin::query()
            ->with(['role:id,key,name', 'workShift:id,name'])
            ->orderByDesc('is_owner')
            ->latest('id')
            ->get();

        return Inertia::render('admin/Users', array_merge(
            $adminShell->props($authenticatedAdmin, 'User & Role', 'users_and_roles'),
            [
                'staff' => $admins->map(fn (Admin $admin): array => $this->staffData($admin))->all(),
                'roles' => $roles->map(fn (AdminRole $role): array => $this->roleData($role, $modules))->all(),
                'shifts' => AdminWorkShift::query()->where('is_active', true)->orderBy('starts_at')->get(['id', 'name']),
                'allModules' => $modules->map(fn (AdminModule $module): array => [
                    'id' => $module->id,
                    'key' => $module->key,
                    'label' => $module->name,
                    'caption' => $module->description ?? '',
                ])->all(),
                'ownerSummary' => [
                    'key' => 'owner',
                    'name' => 'Owner',
                    'description' => 'Akses penuh ke seluruh modul sistem.',
                    'staff_count' => $admins->where('is_owner', true)->count(),
                    'module_count' => $modules->count(),
                ],
                'capabilities' => [
                    'create' => Gate::allows('admin.users_and_roles.create'),
                    'update' => Gate::allows('admin.users_and_roles.update'),
                ],
            ],
        ));
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        Admin::query()->create($request->validated());

        return to_route('admin.users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function update(UpdateAdminUserRequest $request, Admin $adminUser): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $adminUser->update($data);

        return to_route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function updateShift(UpdateAdminUserShiftRequest $request, Admin $adminUser): RedirectResponse
    {
        $adminUser->update($request->validated());

        return to_route('admin.users.index')->with('success', 'Shift user berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function staffData(Admin $admin): array
    {
        $role = $admin->getRelation('role');
        $workShift = $admin->getRelation('workShift');

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => $admin->phone ?? '',
            'role_id' => $admin->role_id,
            'role_key' => $admin->is_owner ? 'owner' : ($role instanceof AdminRole ? $role->key : 'unassigned'),
            'role_name' => $admin->is_owner ? 'Owner' : ($role instanceof AdminRole ? $role->name : 'Belum ada role'),
            'work_shift_id' => $admin->work_shift_id,
            'shift_name' => $workShift instanceof AdminWorkShift ? $workShift->name : 'Tidak ada Shift',
            'is_owner' => $admin->is_owner,
            'is_active' => $admin->is_active,
            'last_active' => $admin->last_login_at?->diffForHumans() ?? 'Belum pernah login',
            'initials' => Str::of($admin->name)
                ->squish()
                ->explode(' ')
                ->take(2)
                ->map(fn (string $name): string => Str::upper(Str::substr($name, 0, 1)))
                ->implode(''),
        ];
    }

    /**
     * @param  Collection<int, AdminModule>  $modules
     * @return array<string, mixed>
     */
    private function roleData(AdminRole $role, Collection $modules): array
    {
        $assignedModules = $role->modules->keyBy('id');

        return [
            'id' => $role->id,
            'key' => $role->key,
            'name' => $role->name,
            'description' => $role->description ?? '',
            'is_active' => $role->is_active,
            'staff_count' => $role->admins_count,
            'permissions' => $modules->map(function (AdminModule $module) use ($assignedModules): array {
                $assignedModule = $assignedModules->get($module->id);
                $pivot = $assignedModule?->getRelation('pivot');

                return [
                    'module_id' => $module->id,
                    'can_create' => $pivot instanceof Pivot && (bool) $pivot->getAttribute('can_create'),
                    'can_read' => $pivot instanceof Pivot && (bool) $pivot->getAttribute('can_read'),
                    'can_update' => $pivot instanceof Pivot && (bool) $pivot->getAttribute('can_update'),
                    'can_delete' => $pivot instanceof Pivot && (bool) $pivot->getAttribute('can_delete'),
                ];
            })->all(),
        ];
    }
}
