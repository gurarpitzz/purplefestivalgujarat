<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 8/9 Migration 009
 * Reusable Custom Operational Permission Profiles (assignable ONLY to ADMIN accounts by the
 * application layer) & Registration-Desk Attendance Analytics Index.
 *
 * 100% portable across MySQL 8.0 and MariaDB 10.4+. Idempotent; zero ON DELETE CASCADE.
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsAdded'    => [],
        'indexesAdded'    => [],
        'tablesCreated'   => [],
        'fksAdded'        => [],
        'profilesSeeded'  => 0,
    ];

    // 1. admin_permission_profiles
    if (!pfgDbTableExists($pdo, 'admin_permission_profiles')) {
        $pdo->exec("
            CREATE TABLE `admin_permission_profiles` (
              `id` CHAR(36) NOT NULL,
              `slug` VARCHAR(64) NOT NULL,
              `name` VARCHAR(120) NOT NULL,
              `description` TEXT DEFAULT NULL,
              `permissions_json` LONGTEXT NOT NULL,
              `is_system` TINYINT(1) NOT NULL DEFAULT 0,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_by_user_id` CHAR(36) DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_app_slug` (`slug`),
              KEY `idx_app_created_by` (`created_by_user_id`),
              CONSTRAINT `fk_app_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'admin_permission_profiles';
        $changes['fksAdded'][] = 'fk_app_created_by';
    }
    $res = pfgDbEnsureForeignKey($pdo, 'admin_permission_profiles', 'fk_app_created_by', 'created_by_user_id', 'users', 'id', 'SET NULL', 'RESTRICT');
    if ($res['status'] === 'ADDED') {
        $changes['fksAdded'][] = 'fk_app_created_by';
    }

    // 2. users.permission_profile_id (+ index + SET NULL FK)
    if (pfgDbEnsureColumn($pdo, 'users', 'permission_profile_id', 'CHAR(36) DEFAULT NULL')) {
        $changes['columnsAdded'][] = 'users.permission_profile_id';
    }
    if (pfgDbEnsureIndex($pdo, 'users', 'idx_users_perm_profile', '`permission_profile_id`', false)) {
        $changes['indexesAdded'][] = 'users.idx_users_perm_profile';
    }
    if (!pfgDbForeignKeyExists($pdo, 'users', 'fk_users_perm_profile')) {
        // Column was just added as all-NULL (or is pre-existing); normalise blanks so the FK can attach.
        $pdo->exec("UPDATE `users` SET `permission_profile_id` = NULL WHERE `permission_profile_id` = ''");
        $pdo->exec(
            'ALTER TABLE `users` ADD CONSTRAINT `fk_users_perm_profile` FOREIGN KEY (`permission_profile_id`) '
            . 'REFERENCES `admin_permission_profiles` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT'
        );
        $changes['fksAdded'][] = 'fk_users_perm_profile';
    }

    // 3. Seed 7 default operational profiles (INSERT IGNORE on unique slug — organizer edits never overwritten).
    //    Permission codes mirror includes/config.php PERMISSIONS. Superadmin-only authorities
    //    (admin_accounts.manage, settings.manage, audit_logs.read) are intentionally never included.
    $defaults = [
        ['90000000-0000-4000-8000-000000000001', 'registration_manager', 'Registration Manager',
            'Reviews, edits and exports registrations across all three categories.',
            ['account.read.own', 'registrations.read.all', 'registrations.write', 'registrations.export', 'attendance.read.full']],
        ['90000000-0000-4000-8000-000000000002', 'volunteer_coordinator', 'Volunteer Coordinator',
            'Reviews volunteer applications and manages volunteer duty assignments.',
            ['account.read.own', 'registrations.read.all', 'volunteers.review', 'attendance.read.full']],
        ['90000000-0000-4000-8000-000000000003', 'sli_coordinator', 'SLI Coordinator',
            'Reviews Indian Sign Language interpreter applications and session pairings.',
            ['account.read.own', 'registrations.read.all', 'sli.review', 'accommodations.read']],
        ['90000000-0000-4000-8000-000000000004', 'accessibility_coordinator', 'Accessibility Coordinator',
            'Reads and manages accommodation requests and accessibility logistics.',
            ['account.read.own', 'registrations.read.all', 'accommodations.read', 'accommodations.manage']],
        ['90000000-0000-4000-8000-000000000005', 'programme_coordinator', 'Programme Coordinator',
            'Plans programme sessions, venues and volunteer / SLI duty rosters.',
            ['account.read.own', 'registrations.read.all', 'volunteers.review', 'sli.review', 'attendance.read.full']],
        ['90000000-0000-4000-8000-000000000006', 'content_manager', 'Content Manager',
            'Manages public website content, programme copy, FAQs and partners.',
            ['account.read.own', 'cms.manage']],
        ['90000000-0000-4000-8000-000000000007', 'communications_manager', 'Communications Manager',
            'Handles participant communications and registration exports for outreach.',
            ['account.read.own', 'registrations.read.all', 'registrations.export']],
    ];
    $ins = $pdo->prepare("
        INSERT IGNORE INTO admin_permission_profiles
            (id, slug, name, description, permissions_json, is_system, is_active, created_by_user_id, created_at, updated_at)
        VALUES (:id, :slug, :name, :description, :perms, 1, 1, NULL, :now, :now)
    ");
    $now = date('Y-m-d H:i:s');
    foreach ($defaults as [$id, $slug, $name, $desc, $perms]) {
        $ins->execute([
            ':id'          => $id,
            ':slug'        => $slug,
            ':name'        => $name,
            ':description' => $desc,
            ':perms'       => json_encode($perms, JSON_UNESCAPED_SLASHES),
            ':now'         => $now,
        ]);
        $changes['profilesSeeded'] += $ins->rowCount();
    }

    // 4. Fast RGST-desk daily analytics (edition + IST date + status).
    if (pfgDbEnsureIndex($pdo, 'attendance_records', 'idx_attendance_edition_date_status', '`edition_id`, `attendance_date_ist`, `status`', false)) {
        $changes['indexesAdded'][] = 'attendance_records.idx_attendance_edition_date_status';
    }

    return $changes;
};
