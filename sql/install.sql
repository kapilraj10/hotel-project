-- Initializer for restaurant_db
CREATE DATABASE IF NOT EXISTS restaurant_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE restaurant_db;

-- Admin users
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  image_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  category_id INT NOT NULL,
  description TEXT,
  image_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Tables info
CREATE TABLE IF NOT EXISTS tables_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_number VARCHAR(50) NOT NULL UNIQUE,
  table_type VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Available',
  image_path VARCHAR(255)
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  items_json JSON NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  payment_method VARCHAR(20) NOT NULL,
  table_number VARCHAR(50),
  table_type VARCHAR(50),
  order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_items_category ON items(category_id);
CREATE INDEX idx_orders_status ON orders(status);

INSERT INTO superadmin (username, password) VALUES ('kapil', '$2y$10$jlzsS8e98J7aIEST2BNzDOEEx8DWNBjPIR0plMOzv654rLu4aVa9a')
ON DUPLICATE KEY UPDATE username=VALUES(username);

-- Ensure `tables_info` has a capacity column (idempotent on MySQL 8+)
-- This keeps backward compatibility with older installs that used `table_type` only.
ALTER TABLE tables_info
  ADD COLUMN IF NOT EXISTS capacity INT UNSIGNED NOT NULL DEFAULT 1;

-- Create `rooms` table if it doesn't already exist.
-- Columns: id, room_number (unique), room_type, price, status, created_at, updated_at
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `room_number` VARCHAR(20) NOT NULL UNIQUE,
  `room_type` VARCHAR(100) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('Available','Occupied') NOT NULL DEFAULT 'Available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful verification statements (safe to run manually):
-- SHOW TABLES LIKE 'rooms';
-- DESCRIBE rooms;
