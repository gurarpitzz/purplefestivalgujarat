-- Rollback for 007_phase5_participant_auth_and_verification.php
-- WARNING: drops Phase 5 verification tokens and legacy-claim audit rows. Export first if needed.

DROP TABLE IF EXISTS `legacy_registration_claims`;
DROP TABLE IF EXISTS `email_verification_tokens`;

ALTER TABLE `users`
  DROP COLUMN `emergency_contact_phone`,
  DROP COLUMN `emergency_contact_relationship`,
  DROP COLUMN `emergency_contact_name`,
  DROP COLUMN `account_consent_at`,
  DROP COLUMN `pending_email`,
  DROP COLUMN `preferred_language`,
  DROP COLUMN `state`,
  DROP COLUMN `city_district`;

DELETE FROM `schema_migrations` WHERE `version` = '007_phase5_participant_auth_and_verification';
