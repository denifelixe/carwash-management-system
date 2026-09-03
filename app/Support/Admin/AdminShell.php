<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Support\AppSettings;
use App\Support\Demo\Brand;
use App\Support\Timezones;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-type ShellModule array{key: string, label: string, caption: string, icon: string, href: string|null, enabled: bool, active: bool, children: list<array<string, mixed>>}
 */
class AdminShell
{
    public function __construct(private TransactionShiftResolver $transactionShiftResolver) {}

    /** @var array<string, string> */
    private const MODULE_ICONS = [
        'dashboard' => 'dashboard',
        'orders' => 'orders',
        'pos' => 'pos',
        'bookings' => 'bookings',
        'finance' => 'finance',
        'members' => 'members',
        'leads' => 'leads',
        'inventory' => 'inventory',
        'rewards' => 'rewards',
        'users_and_roles' => 'users',
        'reports' => 'reports',
        'master_services' => 'services',
        'master_work_shifts' => 'work-shifts',
        'master_timezone' => 'timezone',
        'master_app_settings' => 'app-settings',
        'master_receipt' => 'receipt',
    ];

    /**
     * @return array<string, mixed>
     */
    public function props(Admin $admin, string $pageTitle, ?string $activeModuleKey = null): array
    {
        $admin->loadMissing('role');
        $role = $admin->getRelation('role');
        $shiftAssignment = $this->transactionShiftResolver->presentation($admin, now());
        $timezone = AppSettings::timezone();

        return [
            'mode' => 'live',
            'pageTitle' => $pageTitle,
            'brand' => Brand::identity(),
            'notifications' => [],
            'timezone' => [
                'id' => $timezone,
                'code' => Timezones::code($timezone),
            ],
            'role' => [
                'key' => $admin->is_owner ? 'owner' : ($role instanceof AdminRole ? $role->key : 'staff'),
                'name' => $admin->is_owner ? 'Owner' : ($role instanceof AdminRole ? $role->name : 'Staf'),
                'description' => $admin->is_owner
                    ? 'Akses penuh ke seluruh modul sistem.'
                    : ($role instanceof AdminRole ? ($role->description ?? 'Akses staf belum ditentukan.') : 'Akses staf belum ditentukan.'),
                'accent' => '#0891b2',
                'icon' => $admin->is_owner
                    ? '👑'
                    : ($role instanceof AdminRole ? $role->icon : RoleIcons::DEFAULT),
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
                'shift' => $shiftAssignment['label'],
                'avatar' => $admin->profilePhotoUrl(),
            ],
            'transactionShift' => $shiftAssignment,
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
            'leads' => 'admin.leads.index',
            'users_and_roles' => 'admin.users.index',
            'master_services' => 'admin.master.services.index',
            'master_work_shifts' => 'admin.master.work-shifts.index',
            'master_timezone' => 'admin.master.timezone.index',
            'master_app_settings' => 'admin.master.app-settings.index',
            'master_receipt' => 'admin.master.receipt.index',
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

        return $modules->sortBy('sort_order')->values();
    }
}
