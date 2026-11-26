-- Rollback for 011_phase8_profile_permissions_alignment.php
-- Removes programme.manage from programme_coordinator and communications.manage from communications_manager,
-- removes role_permissions & permissions rows for those two codes, and removes the migration record.

UPDATE `admin_permission_profiles`
SET `permissions_json` = REPLACE(
      REPLACE(
        REPLACE(`permissions_json`, ',"programme.manage"', ''),
        '"programme.manage",', ''
      ),
      '"programme.manage"', ''
    ),
    `updated_at` = NOW()
WHERE `slug` = 'programme_coordinator';

UPDATE `admin_permission_profiles`
SET `permissions_json` = REPLACE(
      REPLACE(
        REPLACE(`permissions_json`, ',"communications.manage"', ''),
        '"communications.manage",', ''
      ),
      '"communications.manage"', ''
    ),
    `updated_at` = NOW()
WHERE `slug` = 'communications_manager';

DELETE rp
FROM `role_permissions` rp
INNER JOIN `permissions` p ON p.id = rp.permission_id
WHERE p.code IN ('programme.manage', 'communications.manage');

DELETE FROM `permissions`
WHERE `code` IN ('programme.manage', 'communications.manage');

DELETE FROM `schema_migrations`
WHERE `version` = '011_phase8_profile_permissions_alignment';
