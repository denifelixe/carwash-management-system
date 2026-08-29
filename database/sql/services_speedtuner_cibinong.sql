-- =============================================================================
-- Katalog layanan Speedtuner Cibinong (Jl. Raya Sukahati No. 15, Sukahati)
-- Sumber: flyer "Our Menu & Special Package", "Coating Lite", "Supreme Wash",
--         "Our Coating & Detailing Package" (mobil dan motor).
--
-- Target : MySQL 8 / utf8mb4, tabel `services`.
-- Jalankan: mysql -u root -p carwash_management_system < database/sql/services_speedtuner_cibinong.sql
--
-- Catatan penerjemahan menu -> tabel:
--  1. Tabel `services` hanya punya satu kolom `price`, sedangkan flyer memakai
--     harga per ukuran kendaraan (Small/Medium/Large/Extra Large). Tiap ukuran
--     karena itu ditulis sebagai baris tersendiri dengan sufiks ukuran pada nama
--     (kolom `name` unik). Kalau nanti ukuran mau jadi dimensi sendiri, skema
--     perlu tabel varian harga, bukan penambahan baris seperti ini.
--  2. `stamps` = 1 untuk layanan yang mencakup Regular Wash (mengikuti promo
--     "Get 1 Free after 5x Regular Wash"); Express Wash, add-on, coating, dan
--     detailing diberi 0. Sesuaikan kalau aturan stempelnya berbeda.
--  3. `is_popular` hanya ditandai pada Regular Wash dan tiga Paket Hemat yang
--     ditonjolkan di flyer.
--  4. Statement memakai ON DUPLICATE KEY UPDATE (kunci: `name`), jadi file ini
--     aman dijalankan ulang untuk memperbarui harga/deskripsi.
--  5. Menghapus baris contoh bawaan seeder tidak dilakukan di sini karena
--     `order_services` memakai foreign key RESTRICT. Hapus manual bila perlu:
--       DELETE FROM services WHERE name IN ('Cuci Mobil Reguler','Cuci Mobil + Wax','Snow Wash Premium','Cuci Motor Reguler','Deep Clean Interior');
-- =============================================================================

SET NAMES utf8mb4;

INSERT INTO `services`
    (`name`, `category`, `price`, `stamps`, `icon`, `description`, `is_popular`, `is_active`, `created_at`, `updated_at`)
VALUES
-- ---------------------------------------------------------------- Cuci Mobil
('Express Wash', 'Cuci Mobil', 45000, 0, '🚿', 'Cuci cepat bagian luar bodi. Tidak termasuk garansi hujan.', 0, 1, NOW(), NOW()),
('Regular Wash', 'Cuci Mobil', 60000, 1, '🚗', 'Cuci reguler standar. Termasuk garansi hujan 24 jam.', 1, 1, NOW(), NOW()),
('Regular Wash (Large)', 'Cuci Mobil', 70000, 1, '🚙', 'Cuci reguler untuk kendaraan ukuran besar. Termasuk garansi hujan 24 jam.', 0, 1, NOW(), NOW()),
('Wash + Body Sealant', 'Cuci Mobil', 135000, 1, '✨', 'Regular wash dengan lapisan body sealant.', 0, 1, NOW(), NOW()),
('Wash + Glass Sealant', 'Cuci Mobil', 100000, 1, '🧴', 'Regular wash dengan lapisan glass sealant.', 0, 1, NOW(), NOW()),
('Wash + After Off-Road', 'Cuci Mobil', 150000, 1, '💧', 'Pencucian menyeluruh setelah pemakaian off-road.', 0, 1, NOW(), NOW()),

-- ------------------------------------------------------- Paket spesial mobil
('Paket Hemat 1', 'Paket Mobil', 500000, 1, '⭐', 'Full body wax, jamur kaca removal, regular wash, free glass coating, dan free parfum.', 1, 1, NOW(), NOW()),
('Paket Hemat 2', 'Paket Mobil', 350000, 1, '⭐', 'Full body wax, freshner fogging, regular wash, dan free parfum.', 1, 1, NOW(), NOW()),
('Paket Hemat 3', 'Paket Mobil', 250000, 1, '⭐', 'Full body wax, glass sealant, regular wash, dan free parfum.', 1, 1, NOW(), NOW()),
('Paket Wax', 'Paket Mobil', 200000, 1, '✨', 'Full body wax dan regular wash.', 0, 1, NOW(), NOW()),
('Paket Jamur', 'Paket Mobil', 200000, 1, '🧴', 'Jamur kaca removal, glass sealant, dan regular wash.', 0, 1, NOW(), NOW()),
('Paket Aspal', 'Paket Mobil', 100000, 1, '⚫', 'Aspal removal dan regular wash.', 0, 1, NOW(), NOW()),
('Paket Fogging', 'Paket Mobil', 150000, 1, '💨', 'Freshner fogging dan regular wash.', 0, 1, NOW(), NOW()),
('Glass Coating', 'Paket Mobil', 300000, 1, '🛡️', 'Jamur kaca removal, coating kaca depan, dan regular wash.', 0, 1, NOW(), NOW()),
('Headlamp Restore', 'Paket Mobil', 300000, 1, '💎', 'Headlamp restore dan regular wash.', 0, 1, NOW(), NOW()),

-- ------------------------------------------------------------- Coating Lite
-- Include: 1 layer coating, express polish, interior cleaning, engine bay cleaning.
('Coating Lite - Small', 'Coating Mobil', 800000, 0, '🛡️', '1 layer coating, express polish, interior cleaning, dan engine bay cleaning. Ukuran small.', 0, 1, NOW(), NOW()),
('Coating Lite - Medium', 'Coating Mobil', 950000, 0, '🛡️', '1 layer coating, express polish, interior cleaning, dan engine bay cleaning. Ukuran medium.', 0, 1, NOW(), NOW()),
('Coating Lite - Large', 'Coating Mobil', 1100000, 0, '🛡️', '1 layer coating, express polish, interior cleaning, dan engine bay cleaning. Ukuran large.', 0, 1, NOW(), NOW()),
('Coating Lite - Extra Large', 'Coating Mobil', 1250000, 0, '🛡️', '1 layer coating, express polish, interior cleaning, dan engine bay cleaning. Ukuran extra large.', 0, 1, NOW(), NOW()),

-- --------------------------------------------------------- Coating 2 layer (mobil)
-- Include: 2 layer coating, exterior detailing, multi step polish, glass coating,
-- free freshner fogging, rims detailing, body waterspot removal, engine cleaning
-- dressing, interior cleaning dressing.
('Coating Mobil - Small', 'Coating Mobil', 2000000, 0, '🛡️', '2 layer coating, exterior detailing, multi step polish, glass coating, rims detailing, body waterspot removal, engine dan interior cleaning dressing, free freshner fogging. Ukuran small.', 0, 1, NOW(), NOW()),
('Coating Mobil - Medium', 'Coating Mobil', 2500000, 0, '🛡️', '2 layer coating, exterior detailing, multi step polish, glass coating, rims detailing, body waterspot removal, engine dan interior cleaning dressing, free freshner fogging. Ukuran medium.', 0, 1, NOW(), NOW()),
('Coating Mobil - Large', 'Coating Mobil', 3000000, 0, '🛡️', '2 layer coating, exterior detailing, multi step polish, glass coating, rims detailing, body waterspot removal, engine dan interior cleaning dressing, free freshner fogging. Ukuran large.', 0, 1, NOW(), NOW()),
('Coating Mobil - Extra Large', 'Coating Mobil', 3500000, 0, '🛡️', '2 layer coating, exterior detailing, multi step polish, glass coating, rims detailing, body waterspot removal, engine dan interior cleaning dressing, free freshner fogging. Ukuran extra large.', 0, 1, NOW(), NOW()),

-- ------------------------------------------------------------- Supreme Wash
-- Include: 1 layer coating, single step polish, windshield glass coating, engine bay
-- cleaning, detailing ruang roda, rims cleaning, interior cleaning, free fogging freshener.
('Supreme Wash - Small', 'Paket Premium Mobil', 1200000, 0, '⭐', '1 layer coating, single step polish, windshield glass coating, engine bay cleaning, detailing ruang roda, rims cleaning, interior cleaning, dan free fogging freshener. Ukuran small.', 0, 1, NOW(), NOW()),
('Supreme Wash - Medium', 'Paket Premium Mobil', 1400000, 0, '⭐', '1 layer coating, single step polish, windshield glass coating, engine bay cleaning, detailing ruang roda, rims cleaning, interior cleaning, dan free fogging freshener. Ukuran medium.', 0, 1, NOW(), NOW()),
('Supreme Wash - Large', 'Paket Premium Mobil', 1600000, 0, '⭐', '1 layer coating, single step polish, windshield glass coating, engine bay cleaning, detailing ruang roda, rims cleaning, interior cleaning, dan free fogging freshener. Ukuran large.', 0, 1, NOW(), NOW()),
('Supreme Wash - Extra Large', 'Paket Premium Mobil', 2000000, 0, '⭐', '1 layer coating, single step polish, windshield glass coating, engine bay cleaning, detailing ruang roda, rims cleaning, interior cleaning, dan free fogging freshener. Ukuran extra large.', 0, 1, NOW(), NOW()),

-- --------------------------------------------------------- Detailing mobil
('Interior Detailing - Small', 'Detailing Mobil', 750000, 0, '🧽', 'Interior detailing termasuk fogging dan extra service by request. Ukuran small.', 0, 1, NOW(), NOW()),
('Interior Detailing - Medium', 'Detailing Mobil', 850000, 0, '🧽', 'Interior detailing termasuk fogging dan extra service by request. Ukuran medium.', 0, 1, NOW(), NOW()),
('Interior Detailing - Large', 'Detailing Mobil', 950000, 0, '🧽', 'Interior detailing termasuk fogging dan extra service by request. Ukuran large.', 0, 1, NOW(), NOW()),
('Interior Detailing - Extra Large', 'Detailing Mobil', 1000000, 0, '🧽', 'Interior detailing termasuk fogging dan extra service by request. Ukuran extra large.', 0, 1, NOW(), NOW()),
('Exterior Detailing - Small', 'Detailing Mobil', 1000000, 0, '💎', 'Exterior detailing termasuk multi step polish, waterspot removal, dan sealant. Ukuran small.', 0, 1, NOW(), NOW()),
('Exterior Detailing - Medium', 'Detailing Mobil', 1200000, 0, '💎', 'Exterior detailing termasuk multi step polish, waterspot removal, dan sealant. Ukuran medium.', 0, 1, NOW(), NOW()),
('Exterior Detailing - Large', 'Detailing Mobil', 1350000, 0, '💎', 'Exterior detailing termasuk multi step polish, waterspot removal, dan sealant. Ukuran large.', 0, 1, NOW(), NOW()),
('Exterior Detailing - Extra Large', 'Detailing Mobil', 1800000, 0, '💎', 'Exterior detailing termasuk multi step polish, waterspot removal, dan sealant. Ukuran extra large.', 0, 1, NOW(), NOW()),
('Complete Detailing - Small', 'Detailing Mobil', 1900000, 0, '✨', 'Paket detailing lengkap interior dan eksterior. Ukuran small.', 0, 1, NOW(), NOW()),
('Complete Detailing - Medium', 'Detailing Mobil', 2100000, 0, '✨', 'Paket detailing lengkap interior dan eksterior. Ukuran medium.', 0, 1, NOW(), NOW()),
('Complete Detailing - Large', 'Detailing Mobil', 2550000, 0, '✨', 'Paket detailing lengkap interior dan eksterior. Ukuran large.', 0, 1, NOW(), NOW()),
('Complete Detailing - Extra Large', 'Detailing Mobil', 3050000, 0, '✨', 'Paket detailing lengkap interior dan eksterior. Ukuran extra large.', 0, 1, NOW(), NOW()),
('Express Polish - Small', 'Detailing Mobil', 500000, 0, '💎', 'Poles cepat untuk mengembalikan kilau cat. Ukuran small.', 0, 1, NOW(), NOW()),
('Express Polish - Medium', 'Detailing Mobil', 650000, 0, '💎', 'Poles cepat untuk mengembalikan kilau cat. Ukuran medium.', 0, 1, NOW(), NOW()),
('Express Polish - Large', 'Detailing Mobil', 800000, 0, '💎', 'Poles cepat untuk mengembalikan kilau cat. Ukuran large.', 0, 1, NOW(), NOW()),
('Express Polish - Extra Large', 'Detailing Mobil', 950000, 0, '💎', 'Poles cepat untuk mengembalikan kilau cat. Ukuran extra large.', 0, 1, NOW(), NOW()),
('Engine Detailing', 'Detailing Mobil', 500000, 0, '🔧', 'Engine detailing untuk semua ukuran kendaraan.', 0, 1, NOW(), NOW()),

-- ------------------------------------------------- Add-on detailing (mobil)
('Seat Remove Interior Detailing - Small', 'Add-on', 500000, 0, '🪑', 'Opsional bongkar jok untuk interior detailing. Ukuran small.', 0, 1, NOW(), NOW()),
('Seat Remove Interior Detailing - Medium', 'Add-on', 550000, 0, '🪑', 'Opsional bongkar jok untuk interior detailing. Ukuran medium.', 0, 1, NOW(), NOW()),
('Seat Remove Interior Detailing - Large', 'Add-on', 600000, 0, '🪑', 'Opsional bongkar jok untuk interior detailing. Ukuran large.', 0, 1, NOW(), NOW()),
('Seat Remove Interior Detailing - Extra Large', 'Add-on', 700000, 0, '🪑', 'Opsional bongkar jok untuk interior detailing. Ukuran extra large.', 0, 1, NOW(), NOW()),
('Extra Service', 'Add-on', 20000, 0, '⭐', 'Layanan tambahan sesuai permintaan pelanggan.', 0, 1, NOW(), NOW()),

-- ---------------------------------------------------------------- Cuci motor
('Motorcycle Wash', 'Cuci Motor', 25000, 0, '🏍️', 'Cuci motor harian.', 0, 1, NOW(), NOW()),
('Sport Motorcycle Wash', 'Cuci Motor', 50000, 0, '🏍️', 'Cuci motor sport atau motor besar.', 0, 1, NOW(), NOW()),
('Extra Sealant Motor', 'Cuci Motor', 25000, 0, '🧴', 'Tambahan sealant untuk cuci motor.', 0, 1, NOW(), NOW()),

-- ------------------------------------------------------------- Coating motor
-- Include: 2 layer coating, full body detailing, multi step polish,
-- body waterspot removal, engine cleaning dressing.
('Coating Motor - Small', 'Coating Motor', 500000, 0, '🛡️', '2 layer coating, full body detailing, multi step polish, body waterspot removal, dan engine cleaning dressing. Ukuran small.', 0, 1, NOW(), NOW()),
('Coating Motor - Medium', 'Coating Motor', 700000, 0, '🛡️', '2 layer coating, full body detailing, multi step polish, body waterspot removal, dan engine cleaning dressing. Ukuran medium.', 0, 1, NOW(), NOW()),
('Coating Motor - Large', 'Coating Motor', 1500000, 0, '🛡️', '2 layer coating, full body detailing, multi step polish, body waterspot removal, dan engine cleaning dressing. Ukuran large.', 0, 1, NOW(), NOW()),
('Coating Motor - Extra Large', 'Coating Motor', 2000000, 0, '🛡️', '2 layer coating, full body detailing, multi step polish, body waterspot removal, dan engine cleaning dressing. Ukuran extra large.', 0, 1, NOW(), NOW()),

-- ---------------------------------------------------------- Detailing motor
('Complete Detailing Motor - Small', 'Detailing Motor', 250000, 0, '🛵', 'Complete detailing motor termasuk wash dan multi step polish. Ukuran small.', 0, 1, NOW(), NOW()),
('Complete Detailing Motor - Medium', 'Detailing Motor', 350000, 0, '🛵', 'Complete detailing motor termasuk wash dan multi step polish. Ukuran medium.', 0, 1, NOW(), NOW()),
('Complete Detailing Motor - Large', 'Detailing Motor', 700000, 0, '🛵', 'Complete detailing motor termasuk wash dan multi step polish. Ukuran large.', 0, 1, NOW(), NOW()),
('Complete Detailing Motor - Extra Large', 'Detailing Motor', 1000000, 0, '🛵', 'Complete detailing motor termasuk wash dan multi step polish. Ukuran extra large.', 0, 1, NOW(), NOW())

ON DUPLICATE KEY UPDATE
    `category` = VALUES(`category`),
    `price` = VALUES(`price`),
    `stamps` = VALUES(`stamps`),
    `icon` = VALUES(`icon`),
    `description` = VALUES(`description`),
    `is_popular` = VALUES(`is_popular`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = NOW();
