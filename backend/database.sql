-- ============================================================
-- Kapada Station - Clothes Rental E-Commerce Database Schema
-- ============================================================
-- Admin password: Admin@123
-- Hash generated with: php -r "echo password_hash('Admin@123', PASSWORD_DEFAULT);"
-- ============================================================

CREATE DATABASE IF NOT EXISTS kapada_station
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE kapada_station;

-- ─── USERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)  NOT NULL,
    email       VARCHAR(255)  NOT NULL UNIQUE,
    phone       VARCHAR(20)   NOT NULL,
    password    VARCHAR(255)  NOT NULL,
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_verified TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── USER PROFILES ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id     INT UNSIGNED PRIMARY KEY,
    address     TEXT,
    city        VARCHAR(100),
    state       VARCHAR(100),
    postal_code VARCHAR(20),
    country     VARCHAR(100) DEFAULT 'India',
    chest       DECIMAL(5,2),
    waist       DECIMAL(5,2),
    hips        DECIMAL(5,2),
    height      DECIMAL(5,2),
    weight      DECIMAL(5,2),
    shoulder    DECIMAL(5,2),
    inseam      DECIMAL(5,2),
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── CATEGORIES ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    type        VARCHAR(50)  NOT NULL COMMENT 'e.g. ladies, gents, kids',
    description TEXT,
    image       VARCHAR(500),
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type   (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PRODUCTS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id    INT UNSIGNED NOT NULL,
    name           VARCHAR(255)      NOT NULL,
    description    TEXT,
    size           VARCHAR(50),
    color          VARCHAR(50),
    rental_price   DECIMAL(10,2)     NOT NULL,
    deposit_amount DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    stock          INT               NOT NULL DEFAULT 1,
    images         JSON,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_status   (status),
    CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── BOOKINGS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED,
    product_id      INT UNSIGNED NOT NULL,
    tracking_code   VARCHAR(10)  NOT NULL UNIQUE,
    customer_name   VARCHAR(150) NOT NULL,
    customer_email  VARCHAR(255) NOT NULL,
    customer_phone  VARCHAR(20)  NOT NULL,
    customer_photo  VARCHAR(500),
    id_document     VARCHAR(500),
    rental_start    DATE         NOT NULL,
    rental_end      DATE         NOT NULL,
    total_days      INT          NOT NULL,
    rental_amount   DECIMAL(10,2) NOT NULL,
    deposit_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM('pending','confirmed','active','returned','cancelled') NOT NULL DEFAULT 'pending',
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id      (user_id),
    INDEX idx_product_id   (product_id),
    INDEX idx_tracking     (tracking_code),
    INDEX idx_status       (status),
    INDEX idx_rental_start (rental_start),
    CONSTRAINT fk_book_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL,
    CONSTRAINT fk_book_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PAYMENTS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id       INT UNSIGNED NOT NULL,
    type             ENUM('deposit','rental','refund') NOT NULL,
    method           ENUM('cash','upi','bank_transfer')  NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reference_number VARCHAR(100),
    notes            TEXT,
    recorded_by      INT UNSIGNED,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking    (booking_id),
    INDEX idx_type       (type),
    INDEX idx_recorded   (recorded_by),
    CONSTRAINT fk_pay_booking  FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_recorder FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: Admin@123)
INSERT INTO users (name, email, phone, password, role, is_verified) VALUES
('Admin', 'admin@kapadastationnew.com', '9000000000', '$2y$10$nrQwuABOeXLJaRfi/FWEiOJihRIwBxTjNBcD6kemz0mKyFmOU8eae', 'admin', 1);

INSERT INTO user_profiles (user_id) VALUES (LAST_INSERT_ID());

-- Sample categories
INSERT INTO categories (name, type, description, status) VALUES
('Sarees',        'ladies', 'Traditional Indian sarees for all occasions', 'active'),
('Lehengas',      'ladies', 'Bridal and party lehengas',                   'active'),
('Salwar Kameez', 'ladies', 'Casual and festive salwar kameez sets',       'active'),
('Sherwanis',     'gents',  'Formal and wedding sherwanis',                'active'),
('Kurta Sets',    'gents',  'Traditional kurta pyjama sets',               'active'),
('Suits',         'gents',  'Formal and party wear suits',                 'active'),
('Kids Frocks',   'kids',   'Party and festive frocks for girls',          'active'),
('Kids Kurtas',   'kids',   'Traditional kurtas for boys',                 'active'),
('Footwear',      'footwear','Traditional and modern footwear',            'active'),
('Jewelry',       'jewelry','Artificial and gold-plated jewelry sets',     'active');

-- ─── SAMPLE TEST USERS ───────────────────────────────────────
-- WARNING: Remove these test accounts before deploying to production!
-- All test users share the same password: Test@1234
INSERT INTO users (name, email, phone, password, role, is_verified, created_at) VALUES
('Priya Sharma',   'priya@example.com',    '9876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1, NOW() - INTERVAL 30 DAY),
('Rahul Gupta',    'rahul@example.com',    '9123456780', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1, NOW() - INTERVAL 25 DAY),
('Anjali Patel',   'anjali@example.com',   '9988776655', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 0, NOW() - INTERVAL 20 DAY),
('Vikram Singh',   'vikram@example.com',   '9001122334', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1, NOW() - INTERVAL 15 DAY),
('Meera Nair',     'meera@example.com',    '8877665544', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1, NOW() - INTERVAL 10 DAY),
('Amit Joshi',     'amit@example.com',     '7654321098', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 0, NOW() - INTERVAL 5 DAY);

INSERT INTO user_profiles (user_id, address, city, state, postal_code)
SELECT id, '123 Main Street', 'Mumbai', 'Maharashtra', '400001' FROM users WHERE email = 'priya@example.com';
INSERT INTO user_profiles (user_id, address, city, state, postal_code)
SELECT id, '456 Park Avenue', 'Delhi', 'Delhi', '110001' FROM users WHERE email = 'rahul@example.com';
INSERT INTO user_profiles (user_id, address, city, state, postal_code)
SELECT id, '789 Garden Road', 'Ahmedabad', 'Gujarat', '380001' FROM users WHERE email = 'anjali@example.com';
INSERT INTO user_profiles (user_id, address, city, state, postal_code)
SELECT id, '321 Hill View', 'Jaipur', 'Rajasthan', '302001' FROM users WHERE email = 'vikram@example.com';
INSERT INTO user_profiles (user_id, address, city, state, postal_code)
SELECT id, '654 Lake Side', 'Kochi', 'Kerala', '682001' FROM users WHERE email = 'meera@example.com';
INSERT INTO user_profiles (user_id, address, city, state, postal_code)
SELECT id, '987 River Bank', 'Pune', 'Maharashtra', '411001' FROM users WHERE email = 'amit@example.com';

-- ─── SAMPLE PRODUCTS (18 products across categories) ─────────
INSERT INTO products (category_id, name, description, size, color, rental_price, deposit_amount, stock, images, status) VALUES
-- Ladies - Sarees
(1, 'Banarasi Silk Saree',       'Elegant Banarasi silk saree with golden zari border, perfect for weddings and festivals',       'Free Size', 'Red, Gold',          500.00,  2000.00, 3, '[]', 'active'),
(1, 'Kanjivaram Silk Saree',     'Pure silk Kanjivaram saree with traditional motifs, ideal for south Indian ceremonies',         'Free Size', 'Green, Gold',        700.00,  2500.00, 2, '[]', 'active'),
(1, 'Chiffon Party Saree',       'Light chiffon saree with embroidered border, comfortable for evening events',                   'Free Size', 'Blue, Silver',       350.00,  1500.00, 4, '[]', 'active'),
-- Ladies - Lehengas
(2, 'Bridal Lehenga Choli',      'Heavy embroidered bridal lehenga with dupatta, perfect for wedding ceremonies',                 'S, M, L',   'Maroon, Gold',      1500.00,  5000.00, 1, '[]', 'active'),
(2, 'Indo-Western Lehenga',      'Modern indo-western style lehenga with digital print',                                          'M, L, XL',  'Pink, White',        800.00,  3000.00, 2, '[]', 'active'),
-- Ladies - Salwar Kameez
(3, 'Anarkali Suit',             'Floor-length Anarkali suit with embroidery, elegant and comfortable',                          'M, L',      'Teal, Gold',         400.00,  1500.00, 3, '[]', 'active'),
(3, 'Straight Cut Suit',         'Elegant straight cut salwar kameez with dupatta',                                              'S, M, L, XL','Purple, Silver',     300.00,  1000.00, 4, '[]', 'active'),
-- Gents - Sherwanis
(4, 'Designer Sherwani',         'Richly embroidered designer sherwani with churidar and dupatta for groom',                     'L, XL, XXL','Ivory, Gold',       1200.00,  4000.00, 2, '[]', 'active'),
(4, 'Royal Achkan Sherwani',     'Traditional achkan style sherwani with mirror work, perfect for sangeet and mehendi',          'M, L, XL',  'Navy Blue, Gold',    900.00,  3500.00, 2, '[]', 'active'),
-- Gents - Kurta Sets
(5, 'Cotton Kurta Pyjama Set',   'Comfortable cotton kurta pyjama set, suitable for casual and festive occasions',               'M, L, XL, XXL','White, Gold',     350.00,  1000.00, 5, '[]', 'active'),
(5, 'Silk Kurta Set',            'Premium silk kurta with Nehru collar, comes with matching churidar',                           'M, L, XL',  'Cream, Brown',       600.00,  2000.00, 3, '[]', 'active'),
-- Gents - Suits
(6, 'Three Piece Suit',          'Classic three-piece formal suit, ideal for weddings and corporate events',                     '40, 42, 44','Charcoal Grey',      700.00,  2500.00, 3, '[]', 'active'),
-- Kids - Frocks
(7, 'Princess Party Frock',      'Beautiful sparkly princess frock for little girls, perfect for birthdays and parties',         '6-8 Years', 'Pink, White',        250.00,   800.00, 4, '[]', 'active'),
(7, 'Traditional Lehenga Choli', 'Traditional lehenga choli set for girls, ideal for cultural events and festivals',             '4-10 Years','Red, Gold',          300.00,  1000.00, 3, '[]', 'active'),
-- Kids - Kurtas
(8, 'Boys Kurta Pyjama',         'Traditional kurta pyjama set for boys, comfortable and elegant for festive occasions',         '4-12 Years','White, Blue',        200.00,   600.00, 5, '[]', 'active'),
-- Footwear
(9, 'Bridal Juttis Pair',        'Handcrafted leather juttis with embroidery, perfect complement to ethnic outfits',             '5-9 UK',    'Gold, Red',          200.00,   800.00, 4, '[]', 'active'),
-- Jewelry
(10,'Kundan Bridal Jewelry Set', 'Complete kundan bridal jewelry set including necklace, earrings, maang tikka and bangles',     'Adjustable','Gold, White',        500.00,  2000.00, 3, '[]', 'active'),
(10,'Silver Temple Jewelry Set', 'Traditional silver-tone temple jewelry with intricate carvings',                               'Adjustable','Silver',             300.00,  1200.00, 4, '[]', 'active');

-- ─── SAMPLE BOOKINGS ─────────────────────────────────────────
-- Note: We need user IDs and product IDs, using subqueries for safety
INSERT INTO bookings (user_id, product_id, tracking_code, customer_name, customer_email, customer_phone,
                      rental_start, rental_end, total_days, rental_amount, deposit_amount, status, notes, created_at)
VALUES
-- Booking 1: Priya rented Banarasi Silk Saree (returned)
((SELECT id FROM users WHERE email = 'priya@example.com'),
 (SELECT id FROM products WHERE name = 'Banarasi Silk Saree'),
 'KS001AABB', 'Priya Sharma', 'priya@example.com', '9876543210',
 DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 17 DAY),
 3, 1500.00, 2000.00, 'returned', 'Needed for Diwali celebration', NOW() - INTERVAL 25 DAY),

-- Booking 2: Rahul rented Designer Sherwani (active)
((SELECT id FROM users WHERE email = 'rahul@example.com'),
 (SELECT id FROM products WHERE name = 'Designer Sherwani'),
 'KS002CCDD', 'Rahul Gupta', 'rahul@example.com', '9123456780',
 DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY),
 5, 6000.00, 4000.00, 'active', 'For cousin wedding', NOW() - INTERVAL 5 DAY),

-- Booking 3: Anjali rented Bridal Lehenga (confirmed)
((SELECT id FROM users WHERE email = 'anjali@example.com'),
 (SELECT id FROM products WHERE name = 'Bridal Lehenga Choli'),
 'KS003EEFF', 'Anjali Patel', 'anjali@example.com', '9988776655',
 DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY),
 3, 4500.00, 5000.00, 'confirmed', 'Engagement ceremony', NOW() - INTERVAL 3 DAY),

-- Booking 4: Vikram rented Cotton Kurta Set (pending)
((SELECT id FROM users WHERE email = 'vikram@example.com'),
 (SELECT id FROM products WHERE name = 'Cotton Kurta Pyjama Set'),
 'KS004GGHH', 'Vikram Singh', 'vikram@example.com', '9001122334',
 DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY),
 2, 700.00, 1000.00, 'pending', 'Navratri event', NOW() - INTERVAL 1 DAY),

-- Booking 5: Meera rented Kundan Jewelry Set (pending)
((SELECT id FROM users WHERE email = 'meera@example.com'),
 (SELECT id FROM products WHERE name = 'Kundan Bridal Jewelry Set'),
 'KS005IIJJ', 'Meera Nair', 'meera@example.com', '8877665544',
 DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 9 DAY),
 2, 1000.00, 2000.00, 'pending', 'For sister engagement', NOW()),

-- Booking 6: Priya rented Three Piece Suit (cancelled)
((SELECT id FROM users WHERE email = 'priya@example.com'),
 (SELECT id FROM products WHERE name = 'Three Piece Suit'),
 'KS006KKLL', 'Priya Sharma', 'priya@example.com', '9876543210',
 DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 17 DAY),
 2, 1400.00, 2500.00, 'cancelled', 'Event postponed', NOW() - INTERVAL 2 DAY),

-- Booking 7: Amit rented Princess Party Frock (returned)
((SELECT id FROM users WHERE email = 'amit@example.com'),
 (SELECT id FROM products WHERE name = 'Princess Party Frock'),
 'KS007MMNN', 'Amit Joshi', 'amit@example.com', '7654321098',
 DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 8 DAY),
 2, 500.00, 800.00, 'returned', 'Daughter birthday party', NOW() - INTERVAL 12 DAY),

-- Booking 8: Rahul rented Silk Kurta Set (confirmed)
((SELECT id FROM users WHERE email = 'rahul@example.com'),
 (SELECT id FROM products WHERE name = 'Silk Kurta Set'),
 'KS008OOPP', 'Rahul Gupta', 'rahul@example.com', '9123456780',
 DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY),
 2, 1200.00, 2000.00, 'confirmed', 'Puja ceremony', NOW() - INTERVAL 1 DAY);

-- ─── SAMPLE PAYMENTS ─────────────────────────────────────────
-- Payments for returned bookings (booking 1 - KS001AABB)
INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'deposit', 'cash', 2000.00, NULL, 'Deposit paid at pickup', NOW() - INTERVAL 25 DAY
FROM bookings b WHERE b.tracking_code = 'KS001AABB';

INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'rental', 'upi', 1500.00, 'UPI123456', 'Rental paid at return', NOW() - INTERVAL 17 DAY
FROM bookings b WHERE b.tracking_code = 'KS001AABB';

-- Payments for active booking (booking 2 - KS002CCDD)
INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'deposit', 'upi', 4000.00, 'UPI789012', 'Deposit paid via UPI', NOW() - INTERVAL 5 DAY
FROM bookings b WHERE b.tracking_code = 'KS002CCDD';

-- Payments for confirmed booking (booking 3 - KS003EEFF)
INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'deposit', 'bank_transfer', 5000.00, 'NEFT345678', 'Deposit via bank transfer', NOW() - INTERVAL 3 DAY
FROM bookings b WHERE b.tracking_code = 'KS003EEFF';

-- Payments for returned booking 7 (KS007MMNN)
INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'deposit', 'cash', 800.00, NULL, 'Cash deposit', NOW() - INTERVAL 12 DAY
FROM bookings b WHERE b.tracking_code = 'KS007MMNN';

INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'rental', 'cash', 500.00, NULL, 'Cash rental payment', NOW() - INTERVAL 8 DAY
FROM bookings b WHERE b.tracking_code = 'KS007MMNN';

-- Payment for confirmed booking 8 (KS008OOPP)
INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
SELECT b.id, 'deposit', 'upi', 2000.00, 'UPI901234', 'UPI deposit paid', NOW() - INTERVAL 1 DAY
FROM bookings b WHERE b.tracking_code = 'KS008OOPP';
