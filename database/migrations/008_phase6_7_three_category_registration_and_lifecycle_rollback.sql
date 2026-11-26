-- Rollback for 008_phase6_7_three_category_registration_and_lifecycle.php
-- WARNING: drops attendee_details, registration_status_history and registration_drafts data.
-- Historical LEGACY registration_categories rows are never deleted; they are re-activated
-- (pre-008 state: every seeded category was active).

UPDATE `registration_categories` SET `is_active` = 1 WHERE `category_tier` = 'LEGACY';

DELETE FROM `accommodation_types`
 WHERE `id` IN (
   '41000000-0000-4000-8000-000000000101',
   '41000000-0000-4000-8000-000000000102',
   '41000000-0000-4000-8000-000000000103',
   '41000000-0000-4000-8000-000000000104',
   '41000000-0000-4000-8000-000000000105'
 )
 AND `id` NOT IN (SELECT `accommodation_type_id` FROM (SELECT `accommodation_type_id` FROM `accommodation_requests` WHERE `accommodation_type_id` IS NOT NULL) AS used_types);

DROP TABLE IF EXISTS `registration_drafts`;
DROP TABLE IF EXISTS `registration_status_history`;
DROP TABLE IF EXISTS `attendee_details`;

ALTER TABLE `sli_applications`
  DROP INDEX `idx_sli_qualification_category`,
  DROP INDEX `idx_sli_based_in_gujarat`,
  DROP COLUMN `availability_shifts`,
  DROP COLUMN `comfortable_public_stage`,
  DROP COLUMN `experience_band`,
  DROP COLUMN `qualification_category`,
  DROP COLUMN `age`;

ALTER TABLE `volunteer_applications`
  DROP INDEX `idx_vol_primary_role`,
  DROP INDEX `idx_vol_msu_status`,
  DROP COLUMN `availability_shifts`,
  DROP COLUMN `languages_other`,
  DROP COLUMN `anchoring_experience`,
  DROP COLUMN `primary_role`,
  DROP COLUMN `msu_faculty_department`,
  DROP COLUMN `msu_affiliation_status`;

ALTER TABLE `accommodation_requests`
  DROP COLUMN `escort_name_encrypted`,
  DROP COLUMN `escort_accompanying`;

ALTER TABLE `registrations`
  DROP INDEX `idx_reg_edition_state`,
  DROP COLUMN `public_review_note`,
  DROP COLUMN `emergency_contact_phone`,
  DROP COLUMN `emergency_contact_relationship`,
  DROP COLUMN `emergency_contact_name`,
  DROP COLUMN `state`,
  DROP COLUMN `city_district`;

DELETE FROM `schema_migrations` WHERE `version` = '008_phase6_7_three_category_registration_and_lifecycle';
