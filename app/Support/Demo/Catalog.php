<?php

namespace App\Support\Demo;

/**
 * Service catalog (BR-03) and reward catalog (BR-04, BR-13).
 */
class Catalog
{
    /**
     * Services shared by the POS grid, order builder, and customer catalog.
     *
     * @return list<array{id: int, name: string, category: string, price: int, stamps: int, icon: string, description: string, popular: bool, isActive: bool, serviceGroup?: array{id: int, name: string}}>
     */
    public static function services(): array
    {
        return [
            ['id' => 1, 'name' => 'Cuci Mobil Reguler', 'category' => 'Cuci Mobil', 'price' => 45000, 'stamps' => 1, 'icon' => '🚗', 'description' => 'Cuci body, sela ban, dan lap kering menyeluruh.', 'popular' => true, 'isActive' => true],
            ['id' => 2, 'name' => 'Cuci Mobil + Wax', 'category' => 'Cuci Mobil', 'price' => 85000, 'stamps' => 1, 'icon' => '✨', 'description' => 'Cuci reguler plus lapisan wax agar cat lebih berkilau.', 'popular' => true, 'isActive' => true],
            ['id' => 3, 'name' => 'Snow Wash Premium', 'category' => 'Cuci Mobil', 'price' => 120000, 'stamps' => 2, 'icon' => '❄️', 'description' => 'Busa salju pH netral, aman untuk cat dan lapisan coating.', 'popular' => true, 'isActive' => true],
            ['id' => 4, 'name' => 'Cuci Motor Reguler', 'category' => 'Cuci Motor', 'price' => 20000, 'stamps' => 1, 'icon' => '🏍️', 'description' => 'Cuci motor cepat dengan pengeringan blower.', 'popular' => false, 'isActive' => true],
            ['id' => 5, 'name' => 'Cuci Motor + Semir', 'category' => 'Cuci Motor', 'price' => 35000, 'stamps' => 1, 'icon' => '🛵', 'description' => 'Cuci motor lengkap dengan semir ban dan bodi mengkilap.', 'popular' => false, 'isActive' => true],
            ['id' => 6, 'name' => 'Poles Body Detailing', 'category' => 'Detailing', 'price' => 450000, 'stamps' => 4, 'icon' => '💎', 'description' => 'Hilangkan baret halus dan kembalikan kilau cat mobil.', 'popular' => false, 'isActive' => true],
            ['id' => 7, 'name' => 'Nano Ceramic Coating', 'category' => 'Detailing', 'price' => 1500000, 'stamps' => 10, 'icon' => '🛡️', 'description' => 'Proteksi cat hingga 12 bulan, anti air dan gores ringan.', 'popular' => false, 'isActive' => true],
            ['id' => 8, 'name' => 'Engine Bay Cleaning', 'category' => 'Detailing', 'price' => 90000, 'stamps' => 1, 'icon' => '🔧', 'description' => 'Bersihkan ruang mesin dari kerak oli dan debu.', 'popular' => false, 'isActive' => true],
            ['id' => 9, 'name' => 'Deep Clean Interior', 'category' => 'Interior', 'price' => 150000, 'stamps' => 2, 'icon' => '🧽', 'description' => 'Vacuum menyeluruh, bersihkan dashboard, dan door trim.', 'popular' => true, 'isActive' => true],
            ['id' => 10, 'name' => 'Salon Jok & Karpet', 'category' => 'Interior', 'price' => 350000, 'stamps' => 3, 'icon' => '🪑', 'description' => 'Cuci jok, karpet, dan plafon dengan mesin extractor.', 'popular' => false, 'isActive' => true],
            ['id' => 11, 'name' => 'Parfum & Anti Jamur Kaca', 'category' => 'Add-on', 'price' => 60000, 'stamps' => 0, 'icon' => '💨', 'description' => 'Wangi kabin tahan lama plus kaca bebas jamur.', 'popular' => false, 'isActive' => true],
            ['id' => 12, 'name' => 'Semir Ban Premium', 'category' => 'Add-on', 'price' => 25000, 'stamps' => 0, 'icon' => '⚫', 'description' => 'Ban hitam pekat mengkilap tahan hingga 2 minggu.', 'popular' => false, 'isActive' => true],
            ['id' => 13, 'name' => 'Coating Lite - Small', 'category' => 'Coating Mobil', 'price' => 800000, 'stamps' => 0, 'icon' => '🛡️', 'description' => 'Coating Lite untuk kendaraan ukuran small.', 'popular' => false, 'isActive' => true, 'serviceGroup' => ['id' => 1, 'name' => 'Coating Lite']],
            ['id' => 14, 'name' => 'Coating Lite - Medium', 'category' => 'Coating Mobil', 'price' => 950000, 'stamps' => 0, 'icon' => '🛡️', 'description' => 'Coating Lite untuk kendaraan ukuran medium.', 'popular' => false, 'isActive' => true, 'serviceGroup' => ['id' => 1, 'name' => 'Coating Lite']],
            ['id' => 15, 'name' => 'Coating Lite - Large', 'category' => 'Coating Mobil', 'price' => 1100000, 'stamps' => 0, 'icon' => '🛡️', 'description' => 'Coating Lite untuk kendaraan ukuran large.', 'popular' => false, 'isActive' => true, 'serviceGroup' => ['id' => 1, 'name' => 'Coating Lite']],
            ['id' => 16, 'name' => 'Coating Lite - Extra Large', 'category' => 'Coating Mobil', 'price' => 1250000, 'stamps' => 0, 'icon' => '🛡️', 'description' => 'Coating Lite untuk kendaraan ukuran extra large.', 'popular' => false, 'isActive' => true, 'serviceGroup' => ['id' => 1, 'name' => 'Coating Lite']],
        ];
    }

    /**
     * Reward catalog. `applicableServiceIds` limits redemption to services that
     * already exist in the order being paid.
     *
     * @return list<array{id: int, name: string, description: string, requiredStamps: int, applicableServiceIds: list<int>, icon: string, category: string, status: string, stock: int, redeemed: int}>
     */
    public static function rewards(): array
    {
        return [
            ['id' => 1, 'name' => 'Gratis Semir Ban', 'description' => 'Semir ban premium gratis untuk satu kali kunjungan.', 'requiredStamps' => 3, 'applicableServiceIds' => [12], 'icon' => '⚫', 'category' => 'Add-on', 'status' => 'aktif', 'stock' => 50, 'redeemed' => 34],
            ['id' => 2, 'name' => 'Gratis Parfum Mobil', 'description' => 'Wangi kabin tahan lama untuk sekali kunjungan.', 'requiredStamps' => 4, 'applicableServiceIds' => [], 'icon' => '💨', 'category' => 'Add-on', 'status' => 'aktif', 'stock' => 60, 'redeemed' => 28],
            ['id' => 3, 'name' => 'Gratis Vacuum Interior', 'description' => 'Vacuum kabin menyeluruh tanpa biaya tambahan.', 'requiredStamps' => 5, 'applicableServiceIds' => [], 'icon' => '🧽', 'category' => 'Add-on', 'status' => 'aktif', 'stock' => 35, 'redeemed' => 19],
            ['id' => 4, 'name' => 'Gratis Cuci Motor', 'description' => 'Tukar stempel dengan satu kali cuci motor reguler.', 'requiredStamps' => 6, 'applicableServiceIds' => [4], 'icon' => '🏍️', 'category' => 'Layanan', 'status' => 'aktif', 'stock' => 40, 'redeemed' => 22],
            ['id' => 5, 'name' => 'Diskon 50% Snow Wash', 'description' => 'Potongan setengah harga untuk snow wash premium.', 'requiredStamps' => 8, 'applicableServiceIds' => [3], 'icon' => '❄️', 'category' => 'Diskon', 'status' => 'aktif', 'stock' => 25, 'redeemed' => 11],
            ['id' => 6, 'name' => 'Gratis Cuci Mobil Reguler', 'description' => 'Satu kali cuci mobil reguler gratis.', 'requiredStamps' => 10, 'applicableServiceIds' => [1], 'icon' => '🚗', 'category' => 'Layanan', 'status' => 'aktif', 'stock' => 30, 'redeemed' => 15],
            ['id' => 7, 'name' => 'Tumbler ZenWash Eksklusif', 'description' => 'Merchandise tumbler stainless edisi terbatas.', 'requiredStamps' => 12, 'applicableServiceIds' => [], 'icon' => '🥤', 'category' => 'Merchandise', 'status' => 'aktif', 'stock' => 12, 'redeemed' => 6],
            ['id' => 8, 'name' => 'Voucher Poles Body Rp 100rb', 'description' => 'Potongan langsung untuk layanan poles body detailing.', 'requiredStamps' => 15, 'applicableServiceIds' => [6], 'icon' => '💎', 'category' => 'Diskon', 'status' => 'aktif', 'stock' => 18, 'redeemed' => 4],
            ['id' => 9, 'name' => 'Gratis Deep Clean Interior', 'description' => 'Interior detailing menyeluruh tanpa biaya.', 'requiredStamps' => 20, 'applicableServiceIds' => [9], 'icon' => '🪑', 'category' => 'Layanan', 'status' => 'aktif', 'stock' => 10, 'redeemed' => 2],
            ['id' => 10, 'name' => 'Gratis Nano Ceramic Coating', 'description' => 'Reward utama: coating penuh selama 12 bulan proteksi.', 'requiredStamps' => 40, 'applicableServiceIds' => [7], 'icon' => '🛡️', 'category' => 'Layanan', 'status' => 'nonaktif', 'stock' => 3, 'redeemed' => 1],
        ];
    }

    /**
     * @return list<string>
     */
    public static function serviceCategories(): array
    {
        return array_values(array_unique(array_column(self::services(), 'category')));
    }

    /**
     * @return list<string>
     */
    public static function rewardCategories(): array
    {
        return array_values(array_unique(array_column(self::rewards(), 'category')));
    }
}
