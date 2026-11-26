-- Rollback for 003_phase3_schema_evolution_and_separation.php
-- Execute ONLY after rolling back 005_phase3_referential_integrity_and_indexes.

DROP TABLE IF EXISTS `attendance_correction_logs`;
DROP TABLE IF EXISTS `attendance_records`;

ALTER TABLE `notification_queue`
  DROP INDEX `idx_nq_event_type`,
  DROP INDEX `idx_nq_provider_msg`,
  DROP INDEX `idx_nq_status_next_retry`,
  DROP INDEX `uq_notification_queue_idempotency`,
  DROP COLUMN `failed_at`,
  DROP COLUMN `delivered_at`,
  DROP COLUMN `provider_accepted_at`,
  DROP COLUMN `idempotency_key`,
  DROP COLUMN `provider_message_id`,
  DROP COLUMN `provider`,
  DROP COLUMN `channel`,
  DROP COLUMN `event_type`;

ALTER TABLE `registrations`
  DROP INDEX `uq_registrations_idempotency`,
  DROP COLUMN `idempotency_key`,
  DROP COLUMN `assignment_status`;

ALTER TABLE `registration_categories`
  DROP COLUMN `category_tier`;

ALTER TABLE `users`
  DROP COLUMN `account_status`;
