-- Rollback for 006_phase4_notifications_and_delivery_tracking.php

DROP TABLE IF EXISTS `in_app_notifications`;

ALTER TABLE `notification_delivery_logs`
  DROP INDEX `idx_ndl_job_created`,
  DROP INDEX `uq_ndl_provider_event`,
  DROP COLUMN `error_category`,
  DROP COLUMN `http_status`,
  DROP COLUMN `provider_event_id`,
  DROP COLUMN `event_type`,
  DROP COLUMN `attempt_number`;

ALTER TABLE `notification_queue`
  DROP INDEX `idx_nq_entity`,
  DROP INDEX `idx_nq_locked_by`,
  DROP INDEX `idx_nq_claim`,
  DROP COLUMN `ephemeral_secret_enc`,
  DROP COLUMN `error_category`,
  DROP COLUMN `resent_from_id`,
  DROP COLUMN `bounced_at`,
  DROP COLUMN `last_provider_event_at`,
  DROP COLUMN `last_provider_event`,
  DROP COLUMN `last_attempt_at`,
  DROP COLUMN `locked_until`,
  DROP COLUMN `locked_by`,
  DROP COLUMN `priority`,
  DROP COLUMN `entity_id`,
  DROP COLUMN `entity_type`;

