-- Purple Festival Gujarat 2027 — Phase 2 Narrow Security Controls Migration
-- Compatible with MySQL 8.0, MariaDB 10.4+ (Bluehost/XAMPP), and SQLite test driver.
-- Additive and reversible (see 001_phase2_security_controls_rollback.sql).

CREATE TABLE IF NOT EXISTS `security_rate_limits` (
  `bucket_key` VARCHAR(191) NOT NULL,
  `scope` VARCHAR(64) NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `window_started_at` DATETIME NOT NULL,
  `blocked_until` DATETIME DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`bucket_key`),
  KEY `idx_security_rate_limits_scope_updated` (`scope`, `updated_at`),
  KEY `idx_security_rate_limits_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

