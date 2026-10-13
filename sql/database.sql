-- ============================================================
-- SHOE STOCK  -  database schema (MySQL 5.7+ / MariaDB 10.3+)
-- Import this once in phpMyAdmin (Import tab) or with:
--   mysql -u root -p < sql/database.sql
-- On shared hosting the database already exists: delete the
-- CREATE DATABASE and USE lines below, select your database in
-- phpMyAdmin, then import.
-- ============================================================

CREATE DATABASE IF NOT EXISTS shoe_store
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shoe_store;

-- People who can sign in. Roles:
--   admin  = everything, including adding people and removing shoes
--   staff  = add shoes, change counts, edit shoes
--   viewer = look only
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','staff','viewer') NOT NULL DEFAULT 'viewer',
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per brand + colour. The photo and optional serial code
-- belong here. Different sizes of the same row live in shoe_sizes.
CREATE TABLE IF NOT EXISTS shoes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand       VARCHAR(60) NOT NULL,
    colour      VARCHAR(60) NOT NULL DEFAULT '',
    serial_code VARCHAR(60) NULL,
    photo       VARCHAR(64) NULL,
    created_at  DATETIME    NOT NULL,
    updated_at  DATETIME    NOT NULL,
    UNIQUE KEY uq_shoes_brand_colour (brand, colour),
    UNIQUE KEY uq_shoes_serial (serial_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pairs in storage for each size of a shoe.
CREATE TABLE IF NOT EXISTS shoe_sizes (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shoe_id  INT UNSIGNED  NOT NULL,
    size     DECIMAL(4,1)  NOT NULL,
    quantity INT           NOT NULL DEFAULT 0,
    UNIQUE KEY uq_sizes_shoe_size (shoe_id, size),
    CONSTRAINT fk_sizes_shoe FOREIGN KEY (shoe_id)
        REFERENCES shoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change to a count, kept even after a shoe is removed.
CREATE TABLE IF NOT EXISTS stock_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shoe_id    INT UNSIGNED NULL,
    label      VARCHAR(140) NOT NULL,
    size       DECIMAL(4,1) NULL,
    change_qty INT          NOT NULL,
    note       VARCHAR(120) NULL,
    user_id    INT UNSIGNED NULL,
    created_at DATETIME     NOT NULL,
    KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-ins, used to slow down password guessing.
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    created_at DATETIME    NOT NULL,
    KEY idx_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Handy queries for your report or viewing in phpMyAdmin
-- ------------------------------------------------------------
-- Pairs per brand:
--   SELECT s.brand, SUM(z.quantity) AS pairs
--   FROM shoes s JOIN shoe_sizes z ON z.shoe_id = s.id
--   GROUP BY s.brand ORDER BY pairs DESC;
--
-- Same size across brands (size 41):
--   SELECT s.brand, s.colour, z.quantity
--   FROM shoe_sizes z JOIN shoes s ON s.id = z.shoe_id
--   WHERE z.size = 41 ORDER BY s.brand;
--
-- Low stock (3 pairs or fewer):
--   SELECT s.brand, s.colour, z.size, z.quantity
--   FROM shoe_sizes z JOIN shoes s ON s.id = z.shoe_id
--   WHERE z.quantity <= 3 ORDER BY z.quantity;
