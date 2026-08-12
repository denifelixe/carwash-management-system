<?php

namespace App\Support\Carwash;

/**
 * Brand identity and shift definitions for the carwash prototype.
 */
class Brand
{
    /**
     * @return array{name: string, logo: string, whatsapp: string, instagram: string, stampTarget: int, stampReward: string, today: string}
     */
    public static function identity(): array
    {
        return [
            'name' => 'ST Webapp',
            'logo' => '🚗',
            'whatsapp' => '6281800090009',
            'instagram' => 'zenwash.id',
            'stampTarget' => 10,
            'stampReward' => 'Gratis 1x Cuci Mobil Reguler',
            'today' => 'Minggu, 3 Agustus 2026',
        ];
    }

    /**
     * Document metadata shared by every page through the root template.
     *
     * @return array{title: string, description: string, keywords: string, themeColor: string, locale: string, ogImage: string}
     */
    public static function meta(): array
    {
        $identity = self::identity();

        return [
            'title' => $identity['name'].' — Aplikasi Manajemen Carwash',
            'description' => 'Aplikasi manajemen carwash '.$identity['name'].': kasir POS, order & antrean, booking, stok, keuangan, serta kartu stempel digital untuk member.',
            'keywords' => 'carwash, cuci mobil, aplikasi carwash, kasir carwash, pos cuci mobil, detailing, coating, kartu stempel, loyalty member',
            'themeColor' => '#0284C7',
            'locale' => 'id_ID',
            'ogImage' => '/og-image.png',
        ];
    }

    /**
     * Shift breakdown powering the shift-based overview on the dashboard.
     *
     * @return list<array{id: string, name: string, time: string, cashier: string, initials: string, revenue: int, transactions: int, moneyIn: int, moneyOut: int, status: string}>
     */
    public static function shifts(): array
    {
        return [
            ['id' => 'pagi', 'name' => 'Shift Pagi', 'time' => '07.00 - 15.00', 'cashier' => 'Yuni Astuti', 'initials' => 'YA', 'revenue' => 2940000, 'transactions' => 23, 'moneyIn' => 2940000, 'moneyOut' => 480000, 'status' => 'selesai'],
            ['id' => 'sore', 'name' => 'Shift Sore', 'time' => '15.00 - 22.00', 'cashier' => 'Rina Marlina', 'initials' => 'RM', 'revenue' => 1910000, 'transactions' => 15, 'moneyIn' => 1910000, 'moneyOut' => 165000, 'status' => 'berjalan'],
        ];
    }

    /**
     * Operational alerts shown in the admin bell menu.
     *
     * @return list<array{id: int, title: string, message: string, time: string, icon: string, type: string, unread: bool}>
     */
    public static function notifications(): array
    {
        return [
            ['id' => 1, 'title' => 'Antrean baru masuk', 'message' => 'B 1234 CDE • Cuci Mobil + Wax menunggu bay kosong.', 'time' => '5 menit lalu', 'icon' => '🚗', 'type' => 'queue', 'unread' => true],
            ['id' => 2, 'title' => 'Stok snow foam menipis', 'message' => 'Tersisa 3 galon, segera lakukan restock.', 'time' => '40 menit lalu', 'icon' => '⚠️', 'type' => 'stock', 'unread' => true],
            ['id' => 3, 'title' => 'Member baru bergabung', 'message' => 'Nadia Putri mendaftar lewat kasir.', 'time' => '1 jam lalu', 'icon' => '🎫', 'type' => 'member', 'unread' => true],
            ['id' => 4, 'title' => 'Reward ditukar', 'message' => 'Siti Rahmawati menukar 8 stempel untuk gratis vacuum.', 'time' => '2 jam lalu', 'icon' => '🎁', 'type' => 'reward', 'unread' => false],
            ['id' => 5, 'title' => 'Target harian tercapai', 'message' => 'Omzet hari ini menembus Rp 4,5 juta sebelum jam 15.00.', 'time' => '3 jam lalu', 'icon' => '🎯', 'type' => 'target', 'unread' => false],
            ['id' => 6, 'title' => 'Ulasan baru bintang 5', 'message' => 'Hendra Gunawan memberi rating 5 untuk nano coating.', 'time' => 'Kemarin, 17.20', 'icon' => '⭐', 'type' => 'review', 'unread' => false],
        ];
    }

    /**
     * Loyalty updates shown in the customer bell menu.
     *
     * @return list<array{id: int, title: string, message: string, time: string, icon: string, type: string, unread: bool}>
     */
    public static function memberNotifications(): array
    {
        return [
            ['id' => 1, 'title' => 'Stempel bertambah!', 'message' => 'Cuci Mobil + Wax kamu menambah 1 stempel.', 'time' => '2 jam lalu', 'icon' => '✨', 'type' => 'stamp', 'unread' => true],
            ['id' => 2, 'title' => 'Tinggal 3 stempel lagi!', 'message' => 'Kumpulkan 3 stempel lagi untuk gratis 1x cuci reguler.', 'time' => 'Kemarin, 09.10', 'icon' => '🎯', 'type' => 'progress', 'unread' => true],
            ['id' => 3, 'title' => 'Reward baru tersedia', 'message' => 'Gratis Parfum Mobil kini bisa ditukar dengan 4 stempel.', 'time' => '2 hari lalu', 'icon' => '🎁', 'type' => 'reward', 'unread' => true],
            ['id' => 4, 'title' => 'Promo Senin Kinclong', 'message' => 'Diskon 30% Snow Wash Premium setiap hari Senin.', 'time' => '3 hari lalu', 'icon' => '❄️', 'type' => 'promo', 'unread' => false],
            ['id' => 5, 'title' => 'Waktunya cuci lagi', 'message' => 'B 1234 CDE terakhir dicuci 12 hari lalu.', 'time' => '4 hari lalu', 'icon' => '🌿', 'type' => 'reminder', 'unread' => false],
            ['id' => 6, 'title' => 'Selamat datang di ZenWash', 'message' => 'Kartu member digital kamu sudah aktif.', 'time' => '12 Jan 2024', 'icon' => '🎉', 'type' => 'account', 'unread' => false],
        ];
    }

    /**
     * @return list<array{id: int, title: string, description: string, badge: string, gradFrom: string, gradTo: string, icon: string}>
     */
    public static function promos(): array
    {
        return [
            ['id' => 1, 'title' => 'Senin Kinclong', 'description' => 'Diskon 30% Snow Wash Premium setiap hari Senin.', 'badge' => 'Diskon 30%', 'gradFrom' => '#0284c7', 'gradTo' => '#22d3ee', 'icon' => '❄️'],
            ['id' => 2, 'title' => 'Double Stempel Weekend', 'description' => 'Dapat 2 stempel setiap cuci di Sabtu & Minggu.', 'badge' => '2x Stempel', 'gradFrom' => '#7c3aed', 'gradTo' => '#6366f1', 'icon' => '⚡'],
            ['id' => 3, 'title' => 'Ajak Teman', 'description' => 'Dapat 1 stempel bonus untuk setiap teman yang bergabung.', 'badge' => '+1 Stempel', 'gradFrom' => '#f59e0b', 'gradTo' => '#fbbf24', 'icon' => '👥'],
        ];
    }
}
