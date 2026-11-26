-- Rollback for 012_final_release_hardening.php
-- Removes the 5 granular permissions added in migration 012 from operational profiles,
-- role_permissions, and permissions, and deletes the schema_migrations record.

UPDATE `admin_permission_profiles`
SET `permissions_json` = REPLACE(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(
                REPLACE(
                  REPLACE(
                    REPLACE(`permissions_json`, ',"notifications.read"', ''),
                    '"notifications.read",', ''
                  ),
                  '"notifications.read"', ''
                ),
                ',"notifications.retry"', ''
              ),
              '"notifications.retry",', ''
            ),
            '"notifications.retry"', ''
          ),
          ',"notifications.resend"', ''
        ),
        '"notifications.resend",', ''
      ),
      '"notifications.resend"', ''
    ),
    `updated_at` = NOW()
WHERE `slug` = 'communications_manager';

UPDATE `admin_permission_profiles`
SET `permissions_json` = REPLACE(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(`permissions_json`, ',"notifications.read"', ''),
              '"notifications.read",', ''
            ),
            '"notifications.read"', ''
          ),
          ',"attendance.override"', ''
        ),
        '"attendance.override",', ''
      ),
      '"attendance.override"', ''
    ),
    `updated_at` = NOW()
WHERE `slug` = 'registration_manager';

DELETE rp
FROM `role_permissions` rp
INNER JOIN `permissions` p ON p.id = rp.permission_id
WHERE p.code IN (
  'notifications.read',
  'notifications.retry',
  'notifications.resend',
  'notifications.auth.manage',
  'attendance.override'
);

DELETE FROM `permissions`
WHERE `code` IN (
  'notifications.read',
  'notifications.retry',
  'notifications.resend',
  'notifications.auth.manage',
  'attendance.override'
);

DELETE FROM `schema_migrations`
WHERE `version` = '012_final_release_hardening';

