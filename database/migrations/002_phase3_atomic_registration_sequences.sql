-- Purple Festival Gujarat 2027 — Phase 3 Migration 002
-- Dedicated InnoDB AUTO_INCREMENT sequence table for atomic, monotonic Registration ID allocation.
-- Completely decoupled from legacy qr_credentials (Decision D-01).
-- Compatible with MySQL 5.7, MySQL 8.0, MariaDB 10.4+ (Bluehost cPanel & XAMPP).

CREATE TABLE IF NOT EXISTS `registration_number_sequences` (
  `seq_number` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` CHAR(36) NOT NULL,
  `registration_code` VARCHAR(64) DEFAULT NULL,
  `allocated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`seq_number`),
  KEY `idx_reg_num_seq_edition` (`edition_id`),
  UNIQUE KEY `uq_reg_num_seq_code` (`registration_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

