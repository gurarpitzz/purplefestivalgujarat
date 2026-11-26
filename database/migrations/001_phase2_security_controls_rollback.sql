-- Rollback for 001_phase2_security_controls.sql
-- Safely removes only the Phase 2 rate-limiting table without touching any participant or credential tables.

DROP TABLE IF EXISTS `security_rate_limits`;

