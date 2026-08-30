<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Support\Demo\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-type ShellModule array{key: string, label: string, caption: string, icon: string, href: string|null, enabled: bool, active: bool, children: list<array<string, mixed>>}
 */
class AdminShell
{
    /** @var array<string, string> */
    private const MODULE_ICONS = [
        'dashboard' => 'dashboard',
        'orders' => 'orders',
        'pos' => 'pos',
        'bookings' => 'bookings',
        'finance' => 'finance',
        'members' => 'members',
        'inventory' => 'inventory',
        'rewards' => 'rewards',
        'users_and_roles' => 'users',
        'reports' => 'reports',
        'master_services' => 'services',
        'master_work_shifts' => 'work-shifts',
        'master_timezone' => 'timezone',
        'master_app_settings' => 'app-settings',
    ];

    /**
     * @return array<string, mixed>
     */
    public function props(Admin $admin, string $pageTitle, ?string $activeModuleKey = null): array
    {
        $admin->loadMissing('role');
        $role = $admin->getRelation('role');

        return [
            'mode' => 'live',
            'pageTitle' => $pageTitle,
            'brand' => Brand::identity(),
            'notifications' => [],
            'role' => [
                'key' => $admin->is_owner ? 'owner' : ($role instanceof AdminRole ? $role->key : 'staff'),
                'name' => $admin->is_owner ? 'Owner' : ($role instanceof AdminRole ? $role->name : 'Staf'),
                'description' => $admin->is_owner
                    ? 'Akses penuh ke seluruh modul sistem.'
                    : ($role instanceof AdminRole ? ($role->description ?? 'Akses staf belum ditentukan.') : 'Akses staf belum ditentukan.'),
                'accent' => '#0891b2',
                'icon' => $admin->is_owner ? '👑' : '🛡️',
            ],
            'modules' => $this->modulesFor($admin, $activeModuleKey),
            'persona' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'initials' => Str::of($admin->name)
                    ->squish()
                    ->explode(' ')
                    ->take(2)
                    ->map(fn (string $name): string => Str::upper(Str::substr($name, 0, 1)))
                    ->implode(''),
                'shift' => $admin->workShift?->name ?? '',
                'avatar' => $admin->profilePhotoUrl(),
            ],
            'profileHref' => route('admin.profile.edit', absolute: false),
            'headerAction' => null,
            'exitAction' => [
                'label' => 'Keluar',
                'href' => route('admin.logout', absolute: false),
                'method' => 'post',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function modulesFor(Admin $admin, ?string $activeModuleKey): array
    {
        $modules = $admin->is_owner
            ? AdminModule::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
            : $this->readableModulesFor($admin);

        $entries = [];

        foreach ($modules as $module) {
            $entries[] = $this->moduleEntry($module, $activeModuleKey);
        }

        return ModuleGroups::fold($entries);
    }

    /**
     * @return ShellModule
     */
    private function moduleEntry(AdminModule $module, ?string $activeModuleKey): array
    {
        $routeName = match ($module->key) {
            'dashboard' => 'admin.dashboard',
            'orders' => 'admin.orders.index',
            'pos' => 'admin.pos.index',
            'bookings' => 'admin.bookings.index',
            'finance' => 'admin.finance.index',
            'members' => 'admin.members.index',
            'users_and_roles' => 'admin.users.index',
            'master_services' => 'admin.master.services.index',
            'master_work_shifts' => 'admin.master.work-shifts.index',
            'master_timezone' => 'admin.master.timezone.index',
            'master_app_settings' => 'admin.master.app-settings.index',
            default => null,
        };

        return [
            'key' => $module->key,
            'label' => $module->name,
            'caption' => $module->description ?? '',
            'icon' => self::MODULE_ICONS[$module->key] ?? 'dashboard',
            'href' => $routeName !== null ? route($routeName, absolute: false) : null,
            'enabled' => $routeName !== null,
            'active' => $module->key === $activeModuleKey,
            'children' => [],
        ];
    }

    /**
     * @return Collection<int, AdminModule>
     */
    private function readableModulesFor(Admin $admin): Collection
    {
        $admin->loadMissing([
            'role.readableModules' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
        ]);

        $role = $admin->getRelation('role');
        $modules = $role instanceof AdminRole && $role->is_active
            ? $role->readableModules
            : new Collection;

        if (! $modules->contains('key', 'dashboard')) {
            $dashboard = AdminModule::query()
                ->where('key', 'dashboard')
                ->where('is_active', true)
                ->first();

            if ($dashboard !== null) {
                $modules->prepend($dashboard);
            }
        }

        return $modules->sortBy('sort_order')->values();
    }
}
