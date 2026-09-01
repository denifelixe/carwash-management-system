-- =============================================================================
-- Katalog layanan Speedtuner Cibinong untuk skema parent + service variations.
-- Target: MySQL 8 / utf8mb4. File ini idempotent berdasarkan nama service dan
-- kombinasi variation. Harga hanya disimpan pada service_variations.
-- =============================================================================

SET NAMES utf8mb4;

DROP TEMPORARY TABLE IF EXISTS service_catalog_import;
CREATE TEMPORARY TABLE service_catalog_import (
    name VARCHAR(255) NOT NULL,
    category VARCHAR(255) NOT NULL,
    price BIGINT UNSIGNED NOT NULL,
    stamps SMALLINT UNSIGNED NOT NULL,
    icon VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_popular TINYINT(1) NOT NULL,
    is_active TINYINT(1) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

INSERT INTO service_catalog_import
    (name, category, price, stamps, icon, description, is_popular, is_active, created_at, updated_at)
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
('Complete Detailing Motor - Extra Large', 'Detailing Motor', 1000000, 0, '🛵', 'Complete detailing motor termasuk wash dan multi step polish. Ukuran extra large.', 0, 1, NOW(), NOW());

DROP TEMPORARY TABLE IF EXISTS service_catalog_normalized;
CREATE TEMPORARY TABLE service_catalog_normalized AS
SELECT
    CASE
        WHEN name REGEXP ' - (Small|Medium|Large|Extra Large)$'
            THEN REGEXP_REPLACE(name, ' - (Small|Medium|Large|Extra Large)$', '')
        ELSE name
    END AS logical_name,
    category,
    price,
    stamps,
    icon,
    CASE
        WHEN name REGEXP ' - (Small|Medium|Large|Extra Large)$'
            THEN REGEXP_REPLACE(description, ' Ukuran (small|medium|large|extra large)\\.$', '')
        ELSE description
    END AS description,
    is_popular,
    is_active,
    CASE
        WHEN name REGEXP ' - Extra Large$' THEN 'Extra Large'
        WHEN name REGEXP ' - Large$' THEN 'Large'
        WHEN name REGEXP ' - Medium$' THEN 'Medium'
        WHEN name REGEXP ' - Small$' THEN 'Small'
        ELSE NULL
    END AS size_value,
    CASE
        WHEN name REGEXP ' - Small$' THEN 1
        WHEN name REGEXP ' - Medium$' THEN 2
        WHEN name REGEXP ' - Large$' THEN 3
        WHEN name REGEXP ' - Extra Large$' THEN 4
        ELSE 0
    END AS size_rank
FROM service_catalog_import;

INSERT INTO services
    (name, category, variations, stamps, icon, description, is_popular, is_active, created_at, updated_at)
SELECT
    logical_name,
    MAX(CASE WHEN size_rank IN (0, 1) THEN category END),
    CASE
        WHEN MAX(size_rank) > 0
            THEN JSON_OBJECT('Ukuran', JSON_ARRAY('Small', 'Medium', 'Large', 'Extra Large'))
        ELSE NULL
    END,
    MAX(CASE WHEN size_rank IN (0, 1) THEN stamps END),
    MAX(CASE WHEN size_rank IN (0, 1) THEN icon END),
    MAX(CASE WHEN size_rank IN (0, 1) THEN description END),
    MAX(is_popular),
    MAX(is_active),
    NOW(),
    NOW()
FROM service_catalog_normalized
GROUP BY logical_name
ON DUPLICATE KEY UPDATE
    category = VALUES(category),
    variations = VALUES(variations),
    stamps = VALUES(stamps),
    icon = VALUES(icon),
    description = VALUES(description),
    is_popular = VALUES(is_popular),
    is_active = VALUES(is_active),
    updated_at = NOW();

UPDATE service_variations AS variation
INNER JOIN services AS service
    ON service.id = variation.service_id
INNER JOIN service_catalog_normalized AS catalog
    ON catalog.logical_name COLLATE utf8mb4_unicode_ci = service.name
    AND (
        (catalog.size_value IS NULL AND variation.variations IS NULL)
        OR JSON_UNQUOTE(JSON_EXTRACT(variation.variations, '$.Ukuran')) = catalog.size_value
    )
SET
    variation.price = catalog.price,
    variation.is_active = catalog.is_active,
    variation.updated_at = NOW()
WHERE variation.service_id = service.id;

INSERT INTO service_variations
    (service_id, variations, price, is_active, created_at, updated_at)
SELECT
    service.id,
    CASE
        WHEN catalog.size_value IS NULL THEN NULL
        ELSE JSON_OBJECT('Ukuran', catalog.size_value)
    END,
    catalog.price,
    catalog.is_active,
    NOW(),
    NOW()
FROM service_catalog_normalized AS catalog
INNER JOIN services AS service
    ON service.name = catalog.logical_name COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
    SELECT 1
    FROM service_variations AS variation
    WHERE variation.service_id = service.id
      AND (
          (catalog.size_value IS NULL AND variation.variations IS NULL)
          OR JSON_UNQUOTE(JSON_EXTRACT(variation.variations, '$.Ukuran')) = catalog.size_value
      )
);

DROP TEMPORARY TABLE service_catalog_normalized;
DROP TEMPORARY TABLE service_catalog_import;
