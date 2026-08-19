<?php

namespace App\Support\Carwash;

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
            ['key' => 'dashboard', 'label' => 'Dashboard', 'caption' => 'Ringkasan operasional', 'icon' => 'dashboard', 'route' => 'carwash.admin.dashboard'],
            ['key' => 'bookings', 'label' => 'Booking Order', 'caption' => 'Jadwal pelanggan', 'icon' => 'bookings', 'route' => 'carwash.admin.bookings'],
            ['key' => 'orders', 'label' => 'Order', 'caption' => 'Proses kendaraan masuk', 'icon' => 'orders', 'route' => 'carwash.admin.orders'],
            ['key' => 'pos', 'label' => 'Kasir POS', 'caption' => 'Pembayaran order', 'icon' => 'pos', 'route' => 'carwash.admin.pos'],
            ['key' => 'finance', 'label' => 'Keuangan', 'caption' => 'Arus kas harian', 'icon' => 'finance', 'route' => 'carwash.admin.finance'],
            ['key' => 'customers', 'label' => 'Member', 'caption' => 'Database & stempel', 'icon' => 'customers', 'route' => 'carwash.admin.customers'],
            ['key' => 'inventory', 'label' => 'Stock Inventory', 'caption' => 'Stok operasional', 'icon' => 'inventory', 'route' => 'carwash.admin.inventory'],
            ['key' => 'rewards', 'label' => 'Reward', 'caption' => 'Katalog & syarat stempel', 'icon' => 'rewards', 'route' => 'carwash.admin.rewards'],
            ['key' => 'users', 'label' => 'User & Role', 'caption' => 'Hak akses pegawai', 'icon' => 'users', 'route' => 'carwash.admin.users'],
            ['key' => 'reports', 'label' => 'Laporan', 'caption' => 'Monitoring & rekap', 'icon' => 'reports', 'route' => 'carwash.admin.reports'],
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
            'owner' => ['dashboard', 'orders', 'pos', 'customers', 'finance', 'bookings', 'inventory', 'rewards', 'users', 'reports'],
            'manager' => ['dashboard', 'orders', 'pos', 'customers', 'finance', 'bookings', 'inventory', 'rewards', 'reports'],
            'cashier' => ['pos', 'customers', 'finance', 'bookings', 'inventory'],
            'cs' => ['customers', 'orders'],
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
     * @return array<string, array{name: string, title: string, initials: string}>
     */
    public static function personaFor(): array
    {
        return [
            'owner' => ['name' => 'Achmad Tarmizi', 'title' => 'Pemilik ZenWash', 'initials' => 'AT'],
            'manager' => ['name' => 'Sinta Dewi', 'title' => 'Manager Operasional', 'initials' => 'SD'],
            'cashier' => ['name' => 'Yuni Astuti', 'title' => 'Kasir Shift Pagi', 'initials' => 'YA'],
            'cs' => ['name' => 'Rina Marlina', 'title' => 'CS / Front Office', 'initials' => 'RM'],
            'finance' => ['name' => 'Bayu Anggara', 'title' => 'Staff Finance', 'initials' => 'BA'],
        ];
    }

    /**
     * Staff directory managed on the User & Role page (BR-11).
     *
     * @return list<array{id: int, name: string, email: string, phone: string, role: string, status: string, lastActive: string, initials: string}>
     */
    public static function staff(): array
    {
        return [
            ['id' => 1, 'name' => 'Achmad Tarmizi', 'email' => 'achmad@zenwash.id', 'phone' => '0811-2000-1000', 'role' => 'owner', 'status' => 'aktif', 'lastActive' => 'Online sekarang', 'initials' => 'AT'],
            ['id' => 2, 'name' => 'Sinta Dewi', 'email' => 'sinta@zenwash.id', 'phone' => '0812-4400-7788', 'role' => 'manager', 'status' => 'aktif', 'lastActive' => '10 menit lalu', 'initials' => 'SD'],
            ['id' => 3, 'name' => 'Yuni Astuti', 'email' => 'yuni@zenwash.id', 'phone' => '0813-9911-2233', 'role' => 'cashier', 'status' => 'aktif', 'lastActive' => 'Online sekarang', 'initials' => 'YA'],
            ['id' => 4, 'name' => 'Rina Marlina', 'email' => 'rina@zenwash.id', 'phone' => '0857-3322-1100', 'role' => 'cs', 'status' => 'aktif', 'lastActive' => '25 menit lalu', 'initials' => 'RM'],
            ['id' => 5, 'name' => 'Bayu Anggara', 'email' => 'bayu@zenwash.id', 'phone' => '0878-5566-4433', 'role' => 'finance', 'status' => 'aktif', 'lastActive' => '1 jam lalu', 'initials' => 'BA'],
            ['id' => 6, 'name' => 'Tari Wulandari', 'email' => 'tari@zenwash.id', 'phone' => '0896-7788-9900', 'role' => 'cashier', 'status' => 'aktif', 'lastActive' => 'Kemarin, 21.10', 'initials' => 'TW'],
            ['id' => 7, 'name' => 'Ilham Maulana', 'email' => 'ilham@zenwash.id', 'phone' => '0852-1122-3344', 'role' => 'cs', 'status' => 'aktif', 'lastActive' => '2 hari lalu', 'initials' => 'IM'],
            ['id' => 8, 'name' => 'Rangga Saputra', 'email' => 'rangga@zenwash.id', 'phone' => '0838-9900-1122', 'role' => 'manager', 'status' => 'nonaktif', 'lastActive' => '3 minggu lalu', 'initials' => 'RS'],
        ];
    }
}
