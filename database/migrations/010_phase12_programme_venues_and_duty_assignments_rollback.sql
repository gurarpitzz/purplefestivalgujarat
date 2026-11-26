-- Rollback for 010_phase12_programme_venues_and_duty_assignments.php
-- WARNING: drops all duty assignments. Venue / session rows themselves are preserved.

DROP TABLE IF EXISTS `duty_assignments`;

ALTER TABLE `festival_sessions`
  DROP COLUMN `updated_at`,
  DROP COLUMN `slis_needed`,
  DROP COLUMN `volunteers_needed`,
  DROP COLUMN `description`;

ALTER TABLE `venues`
  DROP COLUMN `updated_at`,
  DROP COLUMN `operational_notes`;

DELETE FROM `schema_migrations` WHERE `version` = '010_phase12_programme_venues_and_duty_assignments';
