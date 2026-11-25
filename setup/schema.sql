-- EventEase Phase 1 schema: MariaDB 10.4 / MySQL, safe for an empty database.
-- No DROP or destructive reset statements. Run with an authorized database administrator.
CREATE DATABASE IF NOT EXISTS `eventease_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eventease_db`;

CREATE TABLE IF NOT EXISTS staff_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_username (username),
  UNIQUE KEY uq_staff_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL,
  description TEXT NULL,
  capacity INT UNSIGNED NOT NULL,
  base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_venue_slug (slug),
  CONSTRAINT chk_venue_capacity CHECK (capacity > 0),
  CONSTRAINT chk_venue_price CHECK (base_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venue_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  package_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_package_venue_name (venue_id, name),
  CONSTRAINT fk_package_venue FOREIGN KEY (venue_id) REFERENCES venues(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_package_price CHECK (package_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_inclusions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id BIGINT UNSIGNED NOT NULL,
  item_text VARCHAR(255) NOT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_inclusions_package (package_id, display_order),
  CONSTRAINT fk_inclusion_package FOREIGN KEY (package_id) REFERENCES packages(id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_addon_name (name),
  CONSTRAINT chk_addon_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_code VARCHAR(32) NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_email VARCHAR(191) NULL,
  customer_phone VARCHAR(30) NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  guest_count INT UNSIGNED NOT NULL,
  venue_id BIGINT UNSIGNED NOT NULL,
  package_id BIGINT UNSIGNED NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  venue_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  package_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  addons_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  down_payment_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  down_payment_required DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','confirmed','paid','cancelled','completed','rejected')
    NOT NULL DEFAULT 'pending',
  status_reason TEXT NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reservation_reference (reference_code),
  KEY idx_reservation_conflict (venue_id, status, start_at, end_at),
  KEY idx_reservation_month (start_at, status),
  KEY idx_reservation_customer_phone (customer_phone),
  CONSTRAINT fk_reservation_venue FOREIGN KEY (venue_id) REFERENCES venues(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_reservation_package FOREIGN KEY (package_id) REFERENCES packages(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_reservation_approver FOREIGN KEY (approved_by) REFERENCES staff_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_reservation_times CHECK (end_at > start_at),
  CONSTRAINT chk_reservation_guests CHECK (guest_count > 0),
  CONSTRAINT chk_reservation_total CHECK (total_amount >= 0),
  CONSTRAINT chk_reservation_deposit CHECK (down_payment_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_addons (
  reservation_id BIGINT UNSIGNED NOT NULL,
  addon_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (reservation_id, addon_id),
  CONSTRAINT fk_res_addon_res FOREIGN KEY (reservation_id) REFERENCES reservations(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_res_addon_addon FOREIGN KEY (addon_id) REFERENCES addons(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_res_addon_qty CHECK (quantity > 0),
  CONSTRAINT chk_res_addon_price CHECK (unit_price >= 0 AND line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('cash','bank_transfer','e_wallet','other') NOT NULL DEFAULT 'other',
  payment_reference VARCHAR(120) NULL,
  proof_path VARCHAR(255) NULL,
  notes TEXT NULL,
  received_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_reservation (reservation_id, received_at),
  KEY idx_payment_reference (payment_reference),
  CONSTRAINT fk_payment_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_payment_staff FOREIGN KEY (recorded_by) REFERENCES staff_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  performed_by BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reservation_history (reservation_id, created_at),
  CONSTRAINT fk_history_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_history_actor FOREIGN KEY (performed_by) REFERENCES staff_users(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep sample venues, packages, pricing and staff credentials EMPTY until confirmed.
-- Confirmation collision prevention will be handled with transaction + venue row lock.
