-- =============================================================================
-- AUTO HUB Spare Parts & Accessories Database Schema & Seed Data
-- Database: auto_hub
-- Sri Lanka Automotive E-Commerce Store
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `auto_hub` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `auto_hub`;

-- -----------------------------------------------------------------------------
-- 1. Table: users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `postal_code` VARCHAR(20) NULL,
  `province` VARCHAR(100) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  `profile_image` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: vehicle_makes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicle_makes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: vehicle_models
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicle_models` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `make_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`make_id`) REFERENCES `vehicle_makes`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_make_model` (`make_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: vehicle_years
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicle_years` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `model_id` INT NOT NULL,
  `year` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`model_id`) REFERENCES `vehicle_models`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_model_year` (`model_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: categories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-cogs',
  `status` VARCHAR(20) DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: products
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `brand` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `short_description` TEXT NULL,
  `specs` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `old_price` DECIMAL(10,2) NULL,
  `discount` INT DEFAULT 0,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `image` VARCHAR(255) NOT NULL,
  `gallery` TEXT NULL,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `part_number` VARCHAR(50) NULL,
  `rating` DECIMAL(3,2) DEFAULT 4.80,
  `reviews_count` INT DEFAULT 10,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_bestseller` TINYINT(1) DEFAULT 0,
  `is_new` TINYINT(1) DEFAULT 0,
  `status` VARCHAR(20) DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: product_vehicle_compatibility
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_vehicle_compatibility` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `make_id` INT NOT NULL,
  `model_id` INT NOT NULL,
  `year` INT NOT NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`make_id`) REFERENCES `vehicle_makes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`model_id`) REFERENCES `vehicle_models`(`id`) ON DELETE CASCADE,
  INDEX `idx_compat_search` (`make_id`, `model_id`, `year`),
  INDEX `idx_compat_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: cart
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `session_id` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_cart_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: cart_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cart_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `cart`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_cart_product` (`cart_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Table: wishlist
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_wishlist` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Table: orders
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
  `payment_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `transaction_id` VARCHAR(100) NULL,
  `payment_reference` VARCHAR(100) NULL,
  `paid_at` DATETIME NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending Payment',
  `shipping_name` VARCHAR(100) NOT NULL,
  `shipping_phone` VARCHAR(30) NOT NULL,
  `shipping_email` VARCHAR(100) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `shipping_city` VARCHAR(100) NOT NULL,
  `shipping_postal_code` VARCHAR(20) NULL,
  `shipping_province` VARCHAR(100) NOT NULL,
  `delivery_instructions` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. Table: order_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. Table: payments
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `payment_gateway` VARCHAR(50) NOT NULL DEFAULT 'PayHere',
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'LKR',
  `transaction_id` VARCHAR(100) NULL,
  `card_type` VARCHAR(50) NULL,
  `card_masked` VARCHAR(30) NULL,
  `payment_status` ENUM('Pending', 'Paid', 'Failed', 'Cancelled', 'Refunded') NOT NULL DEFAULT 'Pending',
  `status_message` VARCHAR(255) NULL,
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. Table: password_resets
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`token_hash`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- 1. Insert Initial Users
-- Passwords:
-- Admin: Admin@2026 ($2y$10$IKwCkstIKrteBjnUzrQt5.oSTUS4gitlsOFtxLheARX2XSq8q9Ide)
-- Customers: Autohub@2026 ($2y$10$aHNkR/k.F2nKCUrCkSV6ienT6t6vNQxkV0gG1.q1yCv9jVTgRiBcu)
INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password`, `role`, `profile_image`) VALUES
(1, 'System Administrator', 'admin@autohub.lk', '070 727 5599', '$2y$10$IKwCkstIKrteBjnUzrQt5.oSTUS4gitlsOFtxLheARX2XSq8q9Ide', 'admin', NULL),
(2, 'Kasun Jayasuriya', 'kasun@autohub.lk', '070 727 5599', '$2y$10$aHNkR/k.F2nKCUrCkSV6ienT6t6vNQxkV0gG1.q1yCv9jVTgRiBcu', 'customer', NULL),
(3, 'Nimal Perera', 'nimal@autohub.lk', '071 234 5678', '$2y$10$aHNkR/k.F2nKCUrCkSV6ienT6t6vNQxkV0gG1.q1yCv9jVTgRiBcu', 'customer', NULL);

-- 2. Insert Vehicle Makes (Exact 5 Brands)
INSERT INTO `vehicle_makes` (`id`, `name`) VALUES
(1, 'Toyota'),
(2, 'Honda'),
(3, 'Nissan'),
(4, 'Suzuki'),
(5, 'BMW');

-- 3. Insert Vehicle Models (Exact 5 Models per Brand = 25 Models)
INSERT INTO `vehicle_models` (`id`, `make_id`, `name`) VALUES
-- Toyota (Make 1)
(1, 1, 'Corolla'),
(2, 1, 'Yaris'),
(3, 1, 'Camry'),
(4, 1, 'Prius'),
(5, 1, 'Hilux'),
-- Honda (Make 2)
(6, 2, 'Civic'),
(7, 2, 'Accord'),
(8, 2, 'Vezel'),
(9, 2, 'Fit'),
(10, 2, 'CR-V'),
-- Nissan (Make 3)
(11, 3, 'Sunny'),
(12, 3, 'March'),
(13, 3, 'Note'),
(14, 3, 'X-Trail'),
(15, 3, 'Navara'),
-- Suzuki (Make 4)
(16, 4, 'Alto'),
(17, 4, 'Wagon R'),
(18, 4, 'Swift'),
(19, 4, 'Vitara'),
(20, 4, 'Celerio'),
-- BMW (Make 5)
(21, 5, '3 Series'),
(22, 5, '5 Series'),
(23, 5, '7 Series'),
(24, 5, 'X3'),
(25, 5, 'X5');

-- 4. Insert Vehicle Years for models (Models 1 to 25)
INSERT INTO `vehicle_years` (`model_id`, `year`) VALUES
(1, 2026), (1, 2025), (1, 2024), (1, 2023), (1, 2022), (1, 2021), (1, 2020), (1, 2018), (1, 2015), (1, 2014),
(2, 2026), (2, 2025), (2, 2024), (2, 2023), (2, 2022), (2, 2021), (2, 2020), (2, 2018), (2, 2015), (2, 2014),
(3, 2026), (3, 2025), (3, 2024), (3, 2023), (3, 2022), (3, 2021), (3, 2020), (3, 2018), (3, 2015), (3, 2014),
(4, 2026), (4, 2025), (4, 2024), (4, 2023), (4, 2022), (4, 2021), (4, 2020), (4, 2018), (4, 2015), (4, 2014),
(5, 2026), (5, 2025), (5, 2024), (5, 2023), (5, 2022), (5, 2021), (5, 2020), (5, 2018), (5, 2015), (5, 2014),
(6, 2026), (6, 2025), (6, 2024), (6, 2023), (6, 2022), (6, 2021), (6, 2020), (6, 2018), (6, 2015), (6, 2014),
(7, 2026), (7, 2025), (7, 2024), (7, 2023), (7, 2022), (7, 2021), (7, 2020), (7, 2018), (7, 2015), (7, 2014),
(8, 2026), (8, 2025), (8, 2024), (8, 2023), (8, 2022), (8, 2021), (8, 2020), (8, 2018), (8, 2015), (8, 2014),
(9, 2026), (9, 2025), (9, 2024), (9, 2023), (9, 2022), (9, 2021), (9, 2020), (9, 2018), (9, 2015), (9, 2014),
(10, 2026), (10, 2025), (10, 2024), (10, 2023), (10, 2022), (10, 2021), (10, 2020), (10, 2018), (10, 2015), (10, 2014),
(11, 2026), (11, 2025), (11, 2024), (11, 2023), (11, 2022), (11, 2021), (11, 2020), (11, 2018), (11, 2015), (11, 2014),
(12, 2026), (12, 2025), (12, 2024), (12, 2023), (12, 2022), (12, 2021), (12, 2020), (12, 2018), (12, 2015), (12, 2014),
(13, 2026), (13, 2025), (13, 2024), (13, 2023), (13, 2022), (13, 2021), (13, 2020), (13, 2018), (13, 2015), (13, 2014),
(14, 2026), (14, 2025), (14, 2024), (14, 2023), (14, 2022), (14, 2021), (14, 2020), (14, 2018), (14, 2015), (14, 2014),
(15, 2026), (15, 2025), (15, 2024), (15, 2023), (15, 2022), (15, 2021), (15, 2020), (15, 2018), (15, 2015), (15, 2014),
(16, 2026), (16, 2025), (16, 2024), (16, 2023), (16, 2022), (16, 2021), (16, 2020), (16, 2018), (16, 2015), (16, 2014),
(17, 2026), (17, 2025), (17, 2024), (17, 2023), (17, 2022), (17, 2021), (17, 2020), (17, 2018), (17, 2015), (17, 2014),
(18, 2026), (18, 2025), (18, 2024), (18, 2023), (18, 2022), (18, 2021), (18, 2020), (18, 2018), (18, 2015), (18, 2014),
(19, 2026), (19, 2025), (19, 2024), (19, 2023), (19, 2022), (19, 2021), (19, 2020), (19, 2018), (19, 2015), (19, 2014),
(20, 2026), (20, 2025), (20, 2024), (20, 2023), (20, 2022), (20, 2021), (20, 2020), (20, 2018), (20, 2015), (20, 2014),
(21, 2026), (21, 2025), (21, 2024), (21, 2023), (21, 2022), (21, 2021), (21, 2020), (21, 2018), (21, 2015), (21, 2014),
(22, 2026), (22, 2025), (22, 2024), (22, 2023), (22, 2022), (22, 2021), (22, 2020), (22, 2018), (22, 2015), (22, 2014),
(23, 2026), (23, 2025), (23, 2024), (23, 2023), (23, 2022), (23, 2021), (23, 2020), (23, 2018), (23, 2015), (23, 2014),
(24, 2026), (24, 2025), (24, 2024), (24, 2023), (24, 2022), (24, 2021), (24, 2020), (24, 2018), (24, 2015), (24, 2014),
(25, 2026), (25, 2025), (25, 2024), (25, 2023), (25, 2022), (25, 2021), (25, 2020), (25, 2018), (25, 2015), (25, 2014);

-- 5. Insert Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `icon`, `status`) VALUES
(1, 'Body Parts', 'body-parts', 'Bumpers, fenders, grilles, side mirrors, door handles, headlamp assemblies and trim kits designed for exact OEM fitment.', 'images/categories/body.jpg', 'fa-car-side', 'active'),
(2, 'Brake System', 'brake-system', 'High-performance ceramic brake pads, slotted and ventilated brake discs, brake calipers, master cylinders, and ABS sensors.', 'images/categories/brake.jpg', 'fa-compact-disc', 'active'),
(3, 'Cooling System', 'cooling-system', 'Aluminum core radiators, high-output cooling fan motors, thermostats, water pumps, heater cores, and coolant expansion tanks.', 'images/categories/cooling.jpg', 'fa-fan', 'active'),
(4, 'Electrical', 'electrical', 'Maintenance-free starter batteries, iridium spark plugs, high-output alternators, starter motors, and automotive LED lighting kits.', 'images/categories/electrical.jpg', 'fa-bolt', 'active'),
(5, 'Engine Parts', 'engine-parts', 'OEM hydraulic engine mountings, timing belt and chain kits, cylinder head gaskets, piston rings, and valve cover assemblies.', 'images/categories/engine.jpg', 'fa-cogs', 'active'),
(6, 'Filters', 'filters', 'Multi-layer high efficiency oil filters, engine air intake filters, cabin air filters with active carbon, and direct fuel filter units.', 'images/categories/filters.jpg', 'fa-filter', 'active');

-- 6. Insert Products (Sri Lankan automotive spare parts)
INSERT INTO `products` (`id`, `category_id`, `brand`, `name`, `slug`, `description`, `short_description`, `specs`, `price`, `old_price`, `discount`, `stock_quantity`, `image`, `gallery`, `sku`, `part_number`, `rating`, `reviews_count`, `is_featured`, `is_bestseller`, `is_new`, `status`) VALUES
(1, 2, 'Brembo', 'Brembo Premium Ceramic Front Brake Pads', 'brembo-premium-ceramic-front-brake-pads', 
'Engineered specifically for Toyota Premio, Corolla, and Allion, these premium Brembo ceramic brake pads deliver exceptional stopping power with virtually zero brake dust and silent operation. Formulated with heat-dissipating metallic fibers and ceramic compound for maximum safety under heavy tropical driving conditions in Sri Lanka.',
'Ultra-quiet ceramic front brake pads for Toyota Premio and Corolla with anti-squeal shims and superior stopping power.',
'{"Position":"Front Axle (Left & Right)","Material":"Premium Low-Metallic Ceramic","Thickness":"17.5 mm","Length":"123 mm","Origin":"Japan / Italy OEM Spec","Warranty":"12 Months / 20,000 KM"}',
8500.00, 9800.00, 13, 25, 'images/products/brake_pad.jpg', 'images/products/brake_pad.jpg,images/products/brake_disc.jpg', 'BP-TY-1001', '04465-02220', 4.90, 48, 1, 1, 0, 'active'),

(2, 6, 'Bosch', 'Bosch Premium MicroFiltration Oil Filter', 'bosch-premium-microfiltration-oil-filter',
'Bosch Premium Oil Filters utilize an exclusive blend of natural and synthetic filtration media for superior particle filtration and increased engine protection. Proven 99% filtration efficiency against dirt and contaminants.',
'High-grade spin-on microfiltration oil filter with anti-drainback silicone valve for prolonged engine life.',
'{"Filter Type":"Spin-On Oil Filter","Media":"Synthetic Blend Microfiber","Thread Size":"3/4-16 UNF","Relief Valve":"Yes (14 PSI)","Origin":"Germany OEM Spec","Warranty":"6 Months"}',
3500.00, 4200.00, 16, 40, 'images/products/oil_filter.jpg', 'images/products/oil_filter.jpg,images/products/air_filter.jpg', 'FL-BO-04152', '0986AF0012', 4.85, 36, 1, 1, 0, 'active'),

(3, 6, 'Denso', 'Denso High Flow Engine Air Filter', 'denso-high-flow-engine-air-filter',
'Manufactured to exact OEM tolerances by Denso Japan. Triple-layer polyurethane needle-punch media captures 99.5% of harmful road dust, pollen and sand while enabling uninhibited engine breathing and responsive acceleration.',
'OEM standard high-flow engine air intake filter panel designed for maximum throttle response.',
'{"Filter Type":"Rigid Air Filter Panel","Dimensions":"240 mm x 175 mm x 50 mm","Seal":"Molded Polyurethane","Origin":"Japan","Warranty":"12 Months"}',
5800.00, 6800.00, 14, 30, 'images/products/air_filter.jpg', 'images/products/air_filter.jpg', 'FL-DN-13718', '17801-21050', 4.90, 52, 1, 0, 1, 'active'),

(4, 5, 'Toyota Genuine', 'Engine Timing Chain & Camshaft Sprocket Kit', 'engine-timing-chain-camshaft-sprocket-kit',
'Complete timing chain replacement kit including silent link chain, hydraulic tensioner, crank sprocket, intake and exhaust camshaft sprockets, and reinforced guide rails. Restores precise valve timing and silent engine idling.',
'Heavy-duty alloy steel timing chain kit with hydraulic tensioner and hardened sprockets.',
'{"Components":"Timing Chain, Tensioner, 2 Sprockets, 2 Guides","Chain Links":"134 Links","Material":"Hardened Chromoly Alloy","Origin":"Japan","Warranty":"24 Months / 50,000 KM"}',
38500.00, 44000.00, 12, 12, 'images/products/shock_absorber.jpg', 'images/products/shock_absorber.jpg', 'ENG-TC-5520', '13506-21030', 4.95, 20, 1, 0, 0, 'active'),

(5, 6, 'Toyota Genuine', 'Genuine Premium Spin-On Oil Filter', 'genuine-premium-spin-on-oil-filter',
'Official Toyota genuine parts oil filter engineered with pre-lubricated high-temp O-rings and multi-pleat element. Protects VVT-i and Dual VVT-i valve mechanisms from abrasive metal debris.',
'Authentic Toyota factory replacement oil filter with heat-resistant Viton anti-drain valve.',
'{"Type":"Spin-On Canister","Height":"65 mm","Diameter":"68 mm","Origin":"Japan (Toyota Motor Corp)","Warranty":"Factory Fitment Guaranteed"}',
2900.00, 3400.00, 14, 60, 'images/products/oil_filter.jpg', 'images/products/oil_filter.jpg', 'FL-TY-90915', '90915-YZZE1', 5.00, 84, 1, 1, 0, 'active'),

(6, 6, 'Denso', 'High-Flow Cabin & Engine Air Filter Combo', 'high-flow-cabin-engine-air-filter-combo',
'Complete air filtration value pack containing 1 High Flow Engine Air Filter and 1 Active Carbon Cabin AC Filter. Eliminates smog, vehicle exhaust odors, pollen, and bacteria from entering the passenger cabin.',
'Combo pack: 1 High Flow Engine Air Filter + 1 Carbon AC Cabin Filter for total vehicle filtration.',
'{"Cabin Filter":"4-Layer Active Coconut Carbon","Engine Filter":"Precision Polyurethane Panel","Origin":"Japan Spec","Warranty":"6 Months"}',
6200.00, 7500.00, 17, 22, 'images/products/honda_air_filter.jpg', 'images/products/honda_air_filter.jpg', 'FL-CB-9941', '87139-30040-SET', 4.80, 29, 0, 0, 1, 'active'),

(7, 2, 'Toyota Genuine', 'Toyota Premio Front Brake Disc', 'toyota-premio-front-brake-disc',
'Precision-balanced ventilated front brake rotor pair for Toyota Premio and Allion. High carbon cast iron composition resists thermal cracking and brake fading during heavy city traffic and hill-country descents.',
'Heavy-duty vented front brake disc rotor pair with anti-corrosion zinc protection.',
'{"Type":"Ventilated Front Rotors","Diameter":"275 mm","Bolt Pattern":"5 Holes x 100 mm","Thickness":"25 mm","Origin":"Japan OEM","Warranty":"12 Months"}',
26500.00, 30000.00, 11, 14, 'images/products/brake_disc.jpg', 'images/products/brake_disc.jpg,images/products/brake_pad.jpg', 'BD-TY-2024', '43512-20700', 4.90, 31, 1, 1, 0, 'active'),

(8, 3, 'KoyoRad', 'Toyota Premio Radiator Assembly', 'toyota-premio-radiator-assembly',
'Direct-fit aluminum radiator assembly with high-density cooling fins and reinforced composite plastic tanks. Tested to 2.5 Bar burst pressure for tropical Sri Lankan heat resistance.',
'OEM spec full aluminum core radiator assembly for automatic and CVT Premio transmissions.',
'{"Core Material":"Aircraft Grade Aluminum","Tank Material":"Reinforced Nylon Plastic","Core Thickness":"16 mm","Transmission":"Auto / CVT with Oil Cooler","Origin":"Japan","Warranty":"1 Year"}',
36000.00, 42000.00, 14, 8, 'images/products/radiator.jpg', 'images/products/radiator.jpg', 'CL-TY-PR240', '16400-21180', 4.85, 18, 1, 0, 0, 'active'),

(9, 4, 'NGK', 'Toyota Premio Spark Plug Set', 'toyota-premio-spark-plug-set',
'NGK Laser Iridium Spark Plug 4-Piece Engine Set for Toyota 1NZ-FE and 2ZR-FAE engines. Features 0.6mm ultra-fine laser welded iridium center electrode for instant ignition response and peak fuel mileage.',
'Set of 4 Laser Iridium high-performance spark plugs for clean ignition and fuel efficiency.',
'{"Electrode":"0.6mm Laser Iridium Tip","Thread Diameter":"14 mm","Hex Size":"16 mm","Quantity":"4 Plugs (Pack)","Origin":"Japan","Warranty":"50,000 KM"}',
8400.00, 9500.00, 11, 35, 'images/products/spark_plug.jpg', 'images/products/spark_plug.jpg', 'EL-NGK-7751', 'ILKAR7B11', 4.95, 65, 1, 1, 0, 'active'),

(10, 5, 'Toyota Genuine', 'Toyota Engine Mount', 'toyota-engine-mount',
'OEM Hydraulic Right-Hand Engine Mounting for Toyota Premio, Allion, and Corolla. Absorbs 95% of engine vibrations and eliminates harsh cabin resonance during idling and gear shifts.',
'Hydraulic fluid-filled heavy duty engine mounting for vibration-free ride comfort.',
'{"Position":"Right Side (Engine Timing Side)","Type":"Hydraulic Fluid Damped Rubber","Material":"Natural Rubber & Forged Steel","Origin":"Japan","Warranty":"12 Months"}',
18500.00, 21000.00, 11, 10, 'images/products/stabilizer_link.jpg', 'images/products/stabilizer_link.jpg', 'ENG-TY-EM880', '12305-21220', 4.75, 15, 0, 0, 0, 'active'),

(11, 6, 'Toyota Genuine', 'Toyota Air Filter', 'toyota-air-filter',
'Authentic Toyota genuine intake air filter. High dirt-holding capacity prevents microscopic dust particles from eroding cylinder walls and piston rings in dry and dusty driving conditions.',
'Original Toyota replacement engine intake filter for crisp acceleration and clean combustion.',
'{"Material":"Multi-density cellulose fleece","Dimensions":"240 x 175 x 52 mm","Origin":"Japan (Toyota Motor Corp)","Warranty":"100% Genuine Fit"}',
4200.00, 4900.00, 14, 45, 'images/products/air_filter.jpg', 'images/products/air_filter.jpg', 'FL-TY-17801', '17801-0T020', 4.90, 42, 1, 0, 1, 'active'),

(12, 6, 'Toyota Genuine', 'Toyota Fuel Filter', 'toyota-fuel-filter',
'High-pressure in-tank fuel filter and strainer assembly for Toyota EFI fuel systems. Protects precision fuel injectors from sediment, rust, and contaminated fuel.',
'In-tank fuel filter assembly with high-efficiency particulate strainer.',
'{"Mounting":"Inside Fuel Tank with Pump Bracket","Filtration Rating":"10 Microns","Origin":"Japan","Warranty":"12 Months"}',
7800.00, 8900.00, 12, 20, 'images/products/oil_filter.jpg', 'images/products/oil_filter.jpg', 'FL-TY-23300', '77024-52120', 4.80, 24, 0, 0, 0, 'active'),

(13, 4, 'Amaron', 'Suzuki Alto / Wagon R Amaron Go MF Car Battery 12V 35Ah', 'suzuki-alto-wagon-r-amaron-go-battery',
'Zero-maintenance automotive battery with patented Silven X alloy technology for extreme tropical weather durability. High cranking power and vibration resistance.',
'Zero-maintenance 12V 35Ah automotive battery with Silven X alloy technology.',
'{"Voltage":"12V","Capacity":"35Ah","CCA":"300A","Terminal":"Small Post (Left Hand - JIS)","Warranty":"24 Months Official Islandwide"}',
28500.00, 32000.00, 11, 15, 'images/products/battery.jpg', 'images/products/battery.jpg', 'EL-AM-35L', 'AAM-GO-00038B20L', 4.90, 64, 1, 1, 0, 'active'),

(14, 6, 'Mann-Filter', 'BMW 3 Series High-Flow Engine Air Filter', 'bmw-3-series-high-flow-engine-air-filter',
'Manufactured by Mann-Filter to strict German automotive standards. Features synthetic micro-pleat media that traps microscopic dirt while maintaining unrestricted high airflow.',
'German OEM standard engine air filter providing 99.8% filtration efficiency for BMW 3-Series.',
'{"Dimensions":"294 mm x 211 mm x 44 mm","Media":"Multi-Grade Microfiber Cellulose","Origin":"Germany","Warranty":"6 Months OEM"}',
12500.00, 14000.00, 11, 15, 'images/products/air_filter.jpg', 'images/products/air_filter.jpg', 'FL-BMW-3021', '13718507320', 4.80, 32, 1, 0, 1, 'active'),

(15, 4, 'Philips', 'Philips Ultinon Pro9000 LED Headlight Bulb Set H4', 'philips-ultinon-pro9000-led-headlight',
'Premium automotive LED headlight bulbs producing +250% brighter, pure 5800K white light with sharp cutoff beam preventing oncoming glare. AirBoost cooling technology ensures 5000+ hour lifespan.',
'High-power 5800K Cool White LED headlight bulb kit with AirBoost active cooling.',
'{"Base":"H4 (High/Low Beam)","Lumens":"3200 Lumens / Set","Color Temp":"5800K Pure White","Lifespan":"5,000+ Hours","Warranty":"3 Years Islandwide"}',
24500.00, 28000.00, 12, 18, 'images/products/led_headlight.jpg', 'images/products/led_headlight.jpg', 'EL-PH-H4-9000', '11342U90CWX2', 4.90, 38, 1, 1, 0, 'active'),

(16, 2, 'Brembo', 'Brembo Rear Ceramic Brake Pad Set', 'brembo-rear-ceramic-brake-pad-set',
'Matching rear axle ceramic brake pads for Japanese sedans and hatchbacks. Exceptional balance, silent braking, and extended rotor life.',
'Premium ceramic rear brake pad set with low-dust formulation and anti-rattle clips.',
'{"Position":"Rear Axle","Material":"Ceramic Compound","Origin":"Italy / Japan Spec","Warranty":"12 Months / 20,000 KM"}',
7200.00, 8200.00, 12, 28, 'images/products/rear_brake_pad.jpg', 'images/products/rear_brake_pad.jpg', 'BP-TY-1002', '04466-02180', 4.85, 27, 0, 0, 0, 'active');

-- 7. Insert Vehicle Compatibility Mappings
-- Products compatible with Toyota Premio (model_id: 1, make_id: 1) across various years (including 2024!)
INSERT INTO `product_vehicle_compatibility` (`product_id`, `make_id`, `model_id`, `year`) VALUES
-- Product 1: Brembo Front Brake Pads -> Toyota Premio 2014-2026
(1, 1, 1, 2026), (1, 1, 1, 2025), (1, 1, 1, 2024), (1, 1, 1, 2023), (1, 1, 1, 2022), (1, 1, 1, 2021), (1, 1, 1, 2020), (1, 1, 1, 2019), (1, 1, 1, 2018), (1, 1, 1, 2017), (1, 1, 1, 2016), (1, 1, 1, 2015), (1, 1, 1, 2014),
-- Product 1 -> Toyota Corolla 2014-2024
(1, 1, 2, 2024), (1, 1, 2, 2023), (1, 1, 2, 2022), (1, 1, 2, 2021), (1, 1, 2, 2020), (1, 1, 2, 2019), (1, 1, 2, 2018),
-- Product 2: Bosch Oil Filter -> Toyota Premio 2015-2026
(2, 1, 1, 2026), (2, 1, 1, 2025), (2, 1, 1, 2024), (2, 1, 1, 2023), (2, 1, 1, 2022), (2, 1, 1, 2021), (2, 1, 1, 2020), (2, 1, 1, 2018), (2, 1, 1, 2016),
-- Product 2 -> Toyota Corolla
(2, 1, 2, 2024), (2, 1, 2, 2023), (2, 1, 2, 2022), (2, 1, 2, 2020), (2, 1, 2, 2018),
-- Product 3: Denso Air Filter -> Toyota Premio 2016-2026
(3, 1, 1, 2026), (3, 1, 1, 2025), (3, 1, 1, 2024), (3, 1, 1, 2023), (3, 1, 1, 2022), (3, 1, 1, 2021), (3, 1, 1, 2020), (3, 1, 1, 2018),
-- Product 4: Timing Chain -> Toyota Premio
(4, 1, 1, 2024), (4, 1, 1, 2023), (4, 1, 1, 2022), (4, 1, 1, 2021), (4, 1, 1, 2020), (4, 1, 1, 2018), (4, 1, 1, 2016),
-- Product 5: Genuine Spin-On Oil Filter -> Toyota Premio 2010-2026
(5, 1, 1, 2026), (5, 1, 1, 2025), (5, 1, 1, 2024), (5, 1, 1, 2023), (5, 1, 1, 2022), (5, 1, 1, 2021), (5, 1, 1, 2020), (5, 1, 1, 2018), (5, 1, 1, 2016),
-- Product 5 -> Toyota Prius, Aqua
(5, 1, 3, 2024), (5, 1, 3, 2023), (5, 1, 3, 2022), (5, 1, 4, 2023), (5, 1, 4, 2022),
-- Product 6: Cabin & Engine Combo -> Toyota Premio 2018-2026
(6, 1, 1, 2026), (6, 1, 1, 2025), (6, 1, 1, 2024), (6, 1, 1, 2023), (6, 1, 1, 2022), (6, 1, 1, 2020),
-- Product 7: Toyota Premio Front Brake Disc -> Toyota Premio 2014-2026
(7, 1, 1, 2026), (7, 1, 1, 2025), (7, 1, 1, 2024), (7, 1, 1, 2023), (7, 1, 1, 2022), (7, 1, 1, 2021), (7, 1, 1, 2020), (7, 1, 1, 2018), (7, 1, 1, 2016),
-- Product 8: Toyota Premio Radiator -> Toyota Premio 2014-2026
(8, 1, 1, 2026), (8, 1, 1, 2025), (8, 1, 1, 2024), (8, 1, 1, 2023), (8, 1, 1, 2022), (8, 1, 1, 2021), (8, 1, 1, 2020), (8, 1, 1, 2018),
-- Product 9: Toyota Premio Spark Plug Set -> Toyota Premio 2015-2026
(9, 1, 1, 2026), (9, 1, 1, 2025), (9, 1, 1, 2024), (9, 1, 1, 2023), (9, 1, 1, 2022), (9, 1, 1, 2021), (9, 1, 1, 2020), (9, 1, 1, 2018),
-- Product 10: Toyota Engine Mount -> Toyota Premio 2015-2026
(10, 1, 1, 2026), (10, 1, 1, 2025), (10, 1, 1, 2024), (10, 1, 1, 2023), (10, 1, 1, 2022), (10, 1, 1, 2020),
-- Product 11: Toyota Air Filter -> Toyota Premio 2016-2026
(11, 1, 1, 2026), (11, 1, 1, 2025), (11, 1, 1, 2024), (11, 1, 1, 2023), (11, 1, 1, 2022), (11, 1, 1, 2020),
-- Product 12: Toyota Fuel Filter -> Toyota Premio 2014-2026
(12, 1, 1, 2026), (12, 1, 1, 2025), (12, 1, 1, 2024), (12, 1, 1, 2023), (12, 1, 1, 2022), (12, 1, 1, 2020),
-- Product 13: Amaron Battery -> Suzuki Alto (model_id: 31), Suzuki Wagon R (model_id: 32)
(13, 5, 31, 2026), (13, 5, 31, 2025), (13, 5, 31, 2024), (13, 5, 31, 2023), (13, 5, 31, 2022), (13, 5, 31, 2020),
(13, 5, 32, 2025), (13, 5, 32, 2024), (13, 5, 32, 2023), (13, 5, 32, 2022), (13, 5, 32, 2020),
-- Product 14: Mann-Filter Air Filter -> BMW 3 Series (model_id: 13, make_id: 2)
(14, 2, 13, 2026), (14, 2, 13, 2025), (14, 2, 13, 2024), (14, 2, 13, 2023), (14, 2, 13, 2022), (14, 2, 13, 2020), (14, 2, 13, 2018),
-- Product 15: Philips LED -> Universal fitment for Premio, Corolla, Hilux, Civic, Alto
(15, 1, 1, 2024), (15, 1, 2, 2024), (15, 1, 6, 2024), (15, 3, 19, 2024), (15, 5, 31, 2024),
-- Product 16: Brembo Rear Brake Pads -> Toyota Premio, Corolla
(16, 1, 1, 2024), (16, 1, 1, 2023), (16, 1, 1, 2022), (16, 1, 2, 2024), (16, 1, 2, 2023);

-- 8. Insert Sample Orders
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `delivery_fee`, `discount_amount`, `payment_method`, `status`, `shipping_name`, `shipping_phone`, `shipping_email`, `shipping_address`, `shipping_city`, `shipping_province`, `delivery_instructions`, `created_at`) VALUES
(1, 'AH-89421', 2, 17350.00, 350.00, 0.00, 'cod', 'shipped', 'Kasun Jayasuriya', '+94 77 987 6543', 'kasun@autohub.lk', 'No. 45/2, Flower Road', 'Colombo 07', 'Western Province', 'Please call before arriving', NOW() - INTERVAL 2 DAY),
(2, 'AH-89422', 3, 36350.00, 350.00, 0.00, 'card', 'processing', 'Nimal Perera', '+94 71 234 5678', 'nimal@autohub.lk', '128, Kandy Road', 'Kadawatha', 'Western Province', 'Leave at front desk', NOW() - INTERVAL 1 DAY);

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 2, 8500.00),
(2, 8, 1, 36000.00);
