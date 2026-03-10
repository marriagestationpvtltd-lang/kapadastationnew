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
('Kids Kurtas',   'kids',   'Traditional kurtas for boys',                 'active');

-- Sample products
INSERT INTO products (category_id, name, description, size, color, rental_price, deposit_amount, stock, images, status) VALUES
(1, 'Banarasi Silk Saree',    'Elegant Banarasi silk saree with zari border', 'Free Size', 'Red',    500.00, 2000.00, 3, '[]', 'active'),
(1, 'Kanjivaram Saree',       'Pure silk Kanjivaram saree',                   'Free Size', 'Green',  700.00, 2500.00, 2, '[]', 'active'),
(2, 'Bridal Lehenga',         'Heavy bridal lehenga with embroidery',         'M',         'Maroon', 1500.00, 5000.00, 1, '[]', 'active'),
(4, 'Designer Sherwani',      'Embroidered designer sherwani',                'L',         'Ivory',  1200.00, 4000.00, 2, '[]', 'active'),
(5, 'Cotton Kurta Set',       'Festive cotton kurta pyjama set',              'XL',        'White',   350.00, 1000.00, 5, '[]', 'active'),
(7, 'Party Frock',            'Sparkly party frock for girls',                '6-8 years', 'Pink',    250.00,  800.00, 4, '[]', 'active');
