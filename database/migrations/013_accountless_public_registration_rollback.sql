-- Rollback note for 013_accountless_public_registration.php
-- Non-destructive by design: dropping the composite index is safe, while reverting
-- `registrations.user_id` or `privacy_consent_records.user_id` back to NOT NULL is ONLY
-- possible if zero accountless rows (user_id IS NULL) exist.

ALTER TABLE `registrations` DROP INDEX `idx_reg_edition_cat_email`;
ALTER TABLE `registrations` DROP COLUMN `preferred_language`;

