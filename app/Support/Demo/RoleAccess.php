<?php

namespace App\Support\Demo;

/**
 * Role-based access control for the admin console (BR-11).
 *
 * The prototype keeps the active role in the session rather than in a database,
 * so the matrix below is the single source of truth for both the navigation
 * shown to the user and the middleware that guards each module.
 */
class RoleAccess
{
    public const SESSION_KEY = 'carwash_role';

    public const DEFAULT_ROLE = 'owner';

    /**
     * Admin modules, in sidebar order. `route` is the named route of the module.
     *
     * @return list<array{key: string, label: string, caption: string, icon: string, route: string}>
     */
    public static function modules(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'caption' => 'Ringkasan operasional', 'icon' => 'dashboard', 'route' => 'demo.admin.dashboard'],
            ['key' => 'orders', 'label' => 'Order', 'caption' => 'Proses kendaraan masuk', 'icon' => 'orders', 'route' => 'demo.admin.orders'],
            ['key' => 'pos', 'label' => 'Kasir POS', 'caption' => 'Pembayaran order', 'icon' => 'pos', 'route' => 'demo.admin.pos'],
            ['key' => 'bookings', 'label' => 'Booking Order', 'caption' => 'Jadwal pelanggan', 'icon' => 'bookings', 'route' => 'demo.admin.bookings'],
            ['key' => 'finance', 'label' => 'Keuangan', 'caption' => 'Arus kas harian', 'icon' => 'finance', 'route' => 'demo.admin.finance'],
            ['key' => 'members', 'label' => 'Member', 'caption' => 'Database & stempel', 'icon' => 'members', 'route' => 'demo.admin.members'],
            ['key' => 'inventory', 'label' => 'Stock Inventory', 'caption' => 'Stok operasional', 'icon' => 'inventory', 'route' => 'demo.admin.inventory'],
            ['key' => 'rewards', 'label' => 'Reward', 'caption' => 'Katalog & syarat stempel', 'icon' => 'rewards', 'route' => 'demo.admin.rewards'],
            ['key' => 'reports', 'label' => 'Laporan', 'caption' => 'Monitoring & rekap', 'icon' => 'reports', 'route' => 'demo.admin.reports'],
            ['key' => 'users', 'label' => 'User & Role', 'caption' => 'Hak akses pegawai', 'icon' => 'users', 'route' => 'demo.admin.users'],
            ['key' => 'master_services', 'label' => 'Layanan', 'caption' => 'Master data layanan', 'icon' => 'services', 'route' => 'demo.admin.master.services'],
            ['key' => 'master_work_shifts', 'label' => 'Shift', 'caption' => 'Master jadwal kerja', 'icon' => 'work-shifts', 'route' => 'demo.admin.master.work-shifts'],
            ['key' => 'master_timezone', 'label' => 'Timezone', 'caption' => 'Zona waktu operasional', 'icon' => 'timezone', 'route' => 'demo.admin.master.timezone'],
            ['key' => 'master_app_settings', 'label' => 'App Setting', 'caption' => 'Nama, foto, dan favicon aplikasi', 'icon' => 'app-settings', 'route' => 'demo.admin.master.app-settings'],
        ];
    }

    /**
     * @return list<array{key: string, name: string, description: string, accent: string, icon: string}>
     */
    public static function roles(): array
    {
        return [
            ['key' => 'owner', 'name' => 'Owner', 'description' => 'Akses penuh ke seluruh modul sistem.', 'accent' => '#0891b2', 'icon' => '👑'],
            ['key' => 'manager', 'name' => 'Manager', 'description' => 'Akses penuh operasional & manajemen bisnis.', 'accent' => '#7c3aed', 'icon' => '📊'],
            ['key' => 'cashier', 'name' => 'Kasir', 'description' => 'POS, customer, keuangan, booking, dan stok.', 'accent' => '#059669', 'icon' => '💳'],
            ['key' => 'cs', 'name' => 'CS / Front Office', 'description' => 'Menangani customer dan membuat order.', 'accent' => '#d97706', 'icon' => '🎧'],
            ['key' => 'finance', 'name' => 'Finance', 'description' => 'Manajemen keuangan dan laporan.', 'accent' => '#dc2626', 'icon' => '🧾'],
        ];
    }

    /**
     * Module keys each role may reach.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            'owner' => ['dashboard', 'orders', 'pos', 'members', 'finance', 'bookings', 'inventory', 'rewards', 'users', 'reports', 'master_services', 'master_work_shifts', 'master_timezone', 'master_app_settings'],
            'manager' => ['dashboard', 'orders', 'pos', 'members', 'finance', 'bookings', 'inventory', 'rewards', 'reports', 'master_services', 'master_work_shifts', 'master_timezone', 'master_app_settings'],
            'cashier' => ['pos', 'members', 'finance', 'bookings', 'inventory'],
            'cs' => ['members', 'orders'],
            'finance' => ['finance', 'reports'],
        ];
    }

    /**
     * Modules the given role may reach, in sidebar order.
     *
     * @return list<array{key: string, label: string, caption: string, icon: string, route: string}>
     */
    public static function modulesFor(string $role): array
    {
        $allowed = self::matrix()[$role] ?? [];

        return array_values(array_filter(
            self::modules(),
            fn (array $module): bool => in_array($module['key'], $allowed, true),
        ));
    }

    public static function allows(string $role, string $module): bool
    {
        return in_array($module, self::matrix()[$role] ?? [], true);
    }

    public static function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::matrix());
    }

    /**
     * The landing module for a role, used after switching roles.
     */
    public static function homeRouteFor(string $role): string
    {
        $modules = self::modulesFor($role);

        return $modules[0]['route'] ?? 'home';
    }

    /**
     * @return array{key: string, name: string, description: string, accent: string, icon: string}
     */
    public static function role(string $key): array
    {
        foreach (self::roles() as $role) {
            if ($role['key'] === $key) {
                return $role;
            }
        }

        return self::roles()[0];
    }

    /**
     * The staff account bound to each role in the demo, shown in the sidebar footer.
     *
     * @return array<string, array{id: int, name: string, initials: string, shift: string, avatar: null}>
     */
    public static function personaFor(): array
    {
        return [
            'owner' => ['id' => 1, 'name' => 'Achmad Tarmizi', 'initials' => 'AT', 'shift' => 'Shift Pagi', 'avatar' => null],
            'manager' => ['id' => 2, 'name' => 'Sinta Dewi', 'initials' => 'SD', 'shift' => 'Shift Pagi', 'avatar' => null],
            'cashier' => ['id' => 3, 'name' => 'Yuni Astuti', 'initials' => 'YA', 'shift' => 'Shift Pagi', 'avatar' => null],
            'cs' => ['id' => 4, 'name' => 'Rina Marlina', 'initials' => 'RM', 'shift' => 'Shift Sore', 'avatar' => null],
            'finance' => ['id' => 5, 'name' => 'Bayu Anggara', 'initials' => 'BA', 'shift' => 'Shift Pagi', 'avatar' => null],
        ];
    }

    /**
     * @return list<string>
     */
    public static function shifts(): array
    {
        return ['Shift Pagi', 'Shift Sore'];
    }

    /**
     * Adapt the demo fixtures to the same page contract used by the live module.
     *
     * @return array<string, mixed>
     */
    public static function userRoleProps(): array
    {
        $modules = array_map(
            fn (array $module, int $index): array => [
                'id' => $index + 1,
                'key' => $module['key'] === 'users' ? 'users_and_roles' : $module['key'],
                'label' => $module['label'],
                'caption' => $module['caption'],
            ],
            self::modules(),
            array_keys(self::modules()),
        );
        $staffRoles = array_values(array_filter(
            self::roles(),
            fn (array $role): bool => $role['key'] !== 'owner',
        ));
        $roles = array_map(function (array $role, int $index) use ($modules): array {
            $readableModules = self::matrix()[$role['key']] ?? [];

            return [
                'id' => $index + 1,
                'key' => $role['key'],
                'name' => $role['name'],
                'description' => $role['description'],
                'is_active' => true,
                'staff_count' => count(array_filter(
                    self::staff(),
                    fn (array $staff): bool => $staff['role'] === $role['key'],
                )),
                'permissions' => array_map(function (array $module) use ($readableModules): array {
                    $demoModuleKey = $module['key'] === 'users_and_roles' ? 'users' : $module['key'];
                    $canRead = in_array($demoModuleKey, $readableModules, true);

                    return [
                        'module_id' => $module['id'],
                        'can_create' => $canRead && $demoModuleKey !== 'dashboard',
                        'can_read' => $canRead,
                        'can_update' => $canRead && $demoModuleKey !== 'dashboard',
                        'can_delete' => false,
                    ];
                }, $modules),
            ];
        }, $staffRoles, array_keys($staffRoles));
        $roleIds = array_column($roles, 'id', 'key');
        $shiftIds = array_flip(self::shifts());

        return [
            'staff' => array_map(fn (array $staff): array => [
                'id' => $staff['id'],
                'name' => $staff['name'],
                'email' => $staff['email'],
                'phone' => $staff['phone'],
                'role_id' => $staff['role'] === 'owner' ? null : $roleIds[$staff['role']],
                'role_key' => $staff['role'],
                'role_name' => self::role($staff['role'])['name'],
                'work_shift_id' => $shiftIds[$staff['shift']] + 1,
                'shift_name' => $staff['shift'],
                'is_owner' => $staff['role'] === 'owner',
                'is_active' => $staff['status'] === 'aktif',
                'last_active' => $staff['lastActive'],
                'initials' => $staff['initials'],
                'avatar' => null,
            ], self::staff()),
            'roles' => $roles,
            'shifts' => array_map(
                fn (string $shift, int $index): array => ['id' => $index + 1, 'name' => $shift],
                self::shifts(),
                array_keys(self::shifts()),
            ),
            'allModules' => $modules,
            'ownerSummary' => [
                'key' => 'owner',
                'name' => 'Owner',
                'description' => self::role('owner')['description'],
                'staff_count' => count(array_filter(
                    self::staff(),
                    fn (array $staff): bool => $staff['role'] === 'owner',
                )),
                'module_count' => count($modules),
            ],
            'capabilities' => ['create' => true, 'update' => true, 'update_photo' => false],
        ];
    }

    /**
     * Staff directory managed on the User & Role page (BR-11).
     *
     * @return list<array{id: int, name: string, email: string, phone: string, role: string, shift: string, status: string, lastActive: string, initials: string}>
     */
    public static function staff(): array
    {
        return [
            ['id' => 1, 'name' => 'Achmad Tarmizi', 'email' => 'achmad@zenwash.id', 'phone' => '0811-2000-1000', 'role' => 'owner', 'shift' => 'Shift Pagi', 'status' => 'aktif', 'lastActive' => 'Online sekarang', 'initials' => 'AT'],
            ['id' => 2, 'name' => 'Sinta Dewi', 'email' => 'sinta@zenwash.id', 'phone' => '0812-4400-7788', 'role' => 'manager', 'shift' => 'Shift Pagi', 'status' => 'aktif', 'lastActive' => '10 menit lalu', 'initials' => 'SD'],
            ['id' => 3, 'name' => 'Yuni Astuti', 'email' => 'yuni@zenwash.id', 'phone' => '0813-9911-2233', 'role' => 'cashier', 'shift' => 'Shift Pagi', 'status' => 'aktif', 'lastActive' => 'Online sekarang', 'initials' => 'YA'],
            ['id' => 4, 'name' => 'Rina Marlina', 'email' => 'rina@zenwash.id', 'phone' => '0857-3322-1100', 'role' => 'cs', 'shift' => 'Shift Sore', 'status' => 'aktif', 'lastActive' => '25 menit lalu', 'initials' => 'RM'],
            ['id' => 5, 'name' => 'Bayu Anggara', 'email' => 'bayu@zenwash.id', 'phone' => '0878-5566-4433', 'role' => 'finance', 'shift' => 'Shift Pagi', 'status' => 'aktif', 'lastActive' => '1 jam lalu', 'initials' => 'BA'],
            ['id' => 6, 'name' => 'Tari Wulandari', 'email' => 'tari@zenwash.id', 'phone' => '0896-7788-9900', 'role' => 'cashier', 'shift' => 'Shift Sore', 'status' => 'aktif', 'lastActive' => 'Kemarin, 21.10', 'initials' => 'TW'],
            ['id' => 7, 'name' => 'Ilham Maulana', 'email' => 'ilham@zenwash.id', 'phone' => '0852-1122-3344', 'role' => 'cs', 'shift' => 'Shift Pagi', 'status' => 'aktif', 'lastActive' => '2 hari lalu', 'initials' => 'IM'],
            ['id' => 8, 'name' => 'Rangga Saputra', 'email' => 'rangga@zenwash.id', 'phone' => '0838-9900-1122', 'role' => 'manager', 'shift' => 'Shift Sore', 'status' => 'nonaktif', 'lastActive' => '3 minggu lalu', 'initials' => 'RS'],
        ];
    }
}
