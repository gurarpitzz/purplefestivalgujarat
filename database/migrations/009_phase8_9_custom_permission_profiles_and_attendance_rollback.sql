-- Rollback for 009_phase8_9_custom_permission_profiles_and_attendance.php
-- WARNING: drops all custom permission profiles; affected ADMIN accounts fall back to default ADMIN role permissions.

ALTER TABLE `attendance_records` DROP INDEX `idx_attendance_edition_date_status`;

ALTER TABLE `users` DROP FOREIGN KEY `fk_users_perm_profile`;

ALTER TABLE `users`
  DROP INDEX `idx_users_perm_profile`,
  DROP COLUMN `permission_profile_id`;

DROP TABLE IF EXISTS `admin_permission_profiles`;

DELETE FROM `schema_migrations` WHERE `version` = '009_phase8_9_custom_permission_profiles_and_attendance';
