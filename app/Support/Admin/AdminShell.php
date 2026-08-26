<?php

namespace App\Support\Admin;

use App\Models\Admin;
use App\Models\AdminModule;
use App\Support\Demo\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AdminShell
{
    /** @var array<string, string> */
    private const MODULE_ICONS = [
        'dashboard' => 'dashboard',
        'orders' => 'orders',
        'pos' => 'pos',
        'bookings' => 'bookings',
        'finance' => 'finance',
        'customers' => 'customers',
        'inventory' => 'inventory',
        'rewards' => 'rewards',
        'users_and_roles' => 'users',
        'reports' => 'reports',
    ];

    /**
     * @return array<string, mixed>
     */
    public function props(Admin $admin, string $pageTitle, ?string $activeModuleKey = null): array
    {
        $admin->loadMissing('role');

        return [
            'mode' => 'live',
            'pageTitle' => $pageTitle,
            'brand' => Brand::identity(),
            'notifications' => [],
            'role' => [
                'key' => $admin->is_owner ? 'owner' : ($admin->role?->key ?? 'staff'),
                'name' => $admin->is_owner ? 'Owner' : ($admin->role?->name ?? 'Staf'),
                'description' => $admin->is_owner
                    ? 'Akses penuh ke seluruh modul sistem.'
                    : ($admin->role?->description ?? 'Akses staf belum ditentukan.'),
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
                'shift' => '',
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
     * @return list<array{key: string, label: string, caption: string, icon: string, href: string|null, enabled: bool, active: bool}>
     */
    private function modulesFor(Admin $admin, ?string $activeModuleKey): array
    {
        $modules = $admin->is_owner
            ? AdminModule::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
            : $this->readableModulesFor($admin);

        return $modules
            ->map(static function (AdminModule $module) use ($activeModuleKey): array {
                $isDashboard = $module->key === 'dashboard';

                return [
                    'key' => $module->key,
                    'label' => $module->name,
                    'caption' => $module->description ?? '',
                    'icon' => self::MODULE_ICONS[$module->key] ?? 'dashboard',
                    'href' => $isDashboard ? route('admin.dashboard', absolute: false) : null,
                    'enabled' => $isDashboard,
                    'active' => $module->key === $activeModuleKey,
                ];
            })
            ->values()
            ->all();
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

        $modules = $admin->role?->readableModules ?? new Collection;

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
