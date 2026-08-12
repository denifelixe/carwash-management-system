<?php

namespace App\Support\Carwash;

/**
 * Service catalog (BR-03) and reward catalog (BR-04, BR-13).
 */
class Catalog
{
    /**
     * Services shared by the POS grid, order builder, and customer catalog.
     *
     * @return list<array{id: int, name: string, category: string, price: int, duration: int, stamps: int, icon: string, description: string, popular: bool, isActive: bool}>
     */
    public static function services(): array
    {
        return [
            ['id' => 1, 'name' => 'Cuci Mobil Reguler', 'category' => 'Cuci Mobil', 'price' => 45000, 'duration' => 30, 'stamps' => 1, 'icon' => '🚗', 'description' => 'Cuci body, sela ban, dan lap kering menyeluruh.', 'popular' => true, 'isActive' => true],
            ['id' => 2, 'name' => 'Cuci Mobil + Wax', 'category' => 'Cuci Mobil', 'price' => 85000, 'duration' => 45, 'stamps' => 1, 'icon' => '✨', 'description' => 'Cuci reguler plus lapisan wax agar cat lebih berkilau.', 'popular' => true, 'isActive' => true],
            ['id' => 3, 'name' => 'Snow Wash Premium', 'category' => 'Cuci Mobil', 'price' => 120000, 'duration' => 60, 'stamps' => 2, 'icon' => '❄️', 'description' => 'Busa salju pH netral, aman untuk cat dan lapisan coating.', 'popular' => true, 'isActive' => true],
            ['id' => 4, 'name' => 'Cuci Motor Reguler', 'category' => 'Cuci Motor', 'price' => 20000, 'duration' => 15, 'stamps' => 1, 'icon' => '🏍️', 'description' => 'Cuci motor cepat dengan pengeringan blower.', 'popular' => false, 'isActive' => true],
            ['id' => 5, 'name' => 'Cuci Motor + Semir', 'category' => 'Cuci Motor', 'price' => 35000, 'duration' => 25, 'stamps' => 1, 'icon' => '🛵', 'description' => 'Cuci motor lengkap dengan semir ban dan bodi mengkilap.', 'popular' => false, 'isActive' => true],
            ['id' => 6, 'name' => 'Poles Body Detailing', 'category' => 'Detailing', 'price' => 450000, 'duration' => 180, 'stamps' => 4, 'icon' => '💎', 'description' => 'Hilangkan baret halus dan kembalikan kilau cat mobil.', 'popular' => false, 'isActive' => true],
            ['id' => 7, 'name' => 'Nano Ceramic Coating', 'category' => 'Detailing', 'price' => 1500000, 'duration' => 300, 'stamps' => 10, 'icon' => '🛡️', 'description' => 'Proteksi cat hingga 12 bulan, anti air dan gores ringan.', 'popular' => false, 'isActive' => true],
            ['id' => 8, 'name' => 'Engine Bay Cleaning', 'category' => 'Detailing', 'price' => 90000, 'duration' => 45, 'stamps' => 1, 'icon' => '🔧', 'description' => 'Bersihkan ruang mesin dari kerak oli dan debu.', 'popular' => false, 'isActive' => true],
            ['id' => 9, 'name' => 'Deep Clean Interior', 'category' => 'Interior', 'price' => 150000, 'duration' => 90, 'stamps' => 2, 'icon' => '🧽', 'description' => 'Vacuum menyeluruh, bersihkan dashboard, dan door trim.', 'popular' => true, 'isActive' => true],
            ['id' => 10, 'name' => 'Salon Jok & Karpet', 'category' => 'Interior', 'price' => 350000, 'duration' => 120, 'stamps' => 3, 'icon' => '🪑', 'description' => 'Cuci jok, karpet, dan plafon dengan mesin extractor.', 'popular' => false, 'isActive' => true],
            ['id' => 11, 'name' => 'Parfum & Anti Jamur Kaca', 'category' => 'Add-on', 'price' => 60000, 'duration' => 20, 'stamps' => 0, 'icon' => '💨', 'description' => 'Wangi kabin tahan lama plus kaca bebas jamur.', 'popular' => false, 'isActive' => true],
            ['id' => 12, 'name' => 'Semir Ban Premium', 'category' => 'Add-on', 'price' => 25000, 'duration' => 10, 'stamps' => 0, 'icon' => '⚫', 'description' => 'Ban hitam pekat mengkilap tahan hingga 2 minggu.', 'popular' => false, 'isActive' => true],
        ];
    }

    /**
     * Reward catalog. `requiredStamps` is the only condition for redemption (BR-04).
     *
     * @return list<array{id: int, name: string, description: string, requiredStamps: int, icon: string, category: string, status: string, stock: int, redeemed: int}>
     */
    public static function rewards(): array
    {
        return [
            ['id' => 1, 'name' => 'Gratis Semir Ban', 'description' => 'Semir ban premium gratis untuk satu kali kunjungan.', 'requiredStamps' => 3, 'icon' => '⚫', 'category' => 'Add-on', 'status' => 'aktif', 'stock' => 50, 'redeemed' => 34],
            ['id' => 2, 'name' => 'Gratis Parfum Mobil', 'description' => 'Wangi kabin tahan lama untuk sekali kunjungan.', 'requiredStamps' => 4, 'icon' => '💨', 'category' => 'Add-on', 'status' => 'aktif', 'stock' => 60, 'redeemed' => 28],
            ['id' => 3, 'name' => 'Gratis Vacuum Interior', 'description' => 'Vacuum kabin menyeluruh tanpa biaya tambahan.', 'requiredStamps' => 5, 'icon' => '🧽', 'category' => 'Add-on', 'status' => 'aktif', 'stock' => 35, 'redeemed' => 19],
            ['id' => 4, 'name' => 'Gratis Cuci Motor', 'description' => 'Tukar stempel dengan satu kali cuci motor reguler.', 'requiredStamps' => 6, 'icon' => '🏍️', 'category' => 'Layanan', 'status' => 'aktif', 'stock' => 40, 'redeemed' => 22],
            ['id' => 5, 'name' => 'Diskon 50% Snow Wash', 'description' => 'Potongan setengah harga untuk snow wash premium.', 'requiredStamps' => 8, 'icon' => '❄️', 'category' => 'Diskon', 'status' => 'aktif', 'stock' => 25, 'redeemed' => 11],
            ['id' => 6, 'name' => 'Gratis Cuci Mobil Reguler', 'description' => 'Satu kali cuci mobil reguler gratis.', 'requiredStamps' => 10, 'icon' => '🚗', 'category' => 'Layanan', 'status' => 'aktif', 'stock' => 30, 'redeemed' => 15],
            ['id' => 7, 'name' => 'Tumbler ZenWash Eksklusif', 'description' => 'Merchandise tumbler stainless edisi terbatas.', 'requiredStamps' => 12, 'icon' => '🥤', 'category' => 'Merchandise', 'status' => 'aktif', 'stock' => 12, 'redeemed' => 6],
            ['id' => 8, 'name' => 'Voucher Poles Body Rp 100rb', 'description' => 'Potongan langsung untuk layanan poles body detailing.', 'requiredStamps' => 15, 'icon' => '💎', 'category' => 'Diskon', 'status' => 'aktif', 'stock' => 18, 'redeemed' => 4],
            ['id' => 9, 'name' => 'Gratis Deep Clean Interior', 'description' => 'Interior detailing menyeluruh tanpa biaya.', 'requiredStamps' => 20, 'icon' => '🪑', 'category' => 'Layanan', 'status' => 'aktif', 'stock' => 10, 'redeemed' => 2],
            ['id' => 10, 'name' => 'Gratis Nano Ceramic Coating', 'description' => 'Reward utama: coating penuh selama 12 bulan proteksi.', 'requiredStamps' => 40, 'icon' => '🛡️', 'category' => 'Layanan', 'status' => 'nonaktif', 'stock' => 3, 'redeemed' => 1],
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
