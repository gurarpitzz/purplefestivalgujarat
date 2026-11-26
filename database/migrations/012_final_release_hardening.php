<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Final Release Hardening Migration 012 (§5.1)
 * Adds granular notification permissions (`notifications.read`, `notifications.retry`,
 * `notifications.resend`, `notifications.auth.manage`) and `attendance.override`,
 * aligns operational permission profiles with least-privilege boundaries, and ensures
 * strictly Superadmin-only isolation for `notifications.auth.manage`.
 *
 * 100% portable across MySQL 8.0 and MariaDB 10.4+. Strictly additive & idempotent.
 */

return static function (PDO $pdo): array {
    $changes = [
        'permissionsAdded'     => [],
        'rolePermissionsAdded' => [],
        'profilesUpdated'      => [],
    ];

    $now = date('Y-m-d H:i:s');

    // 1. Insert the 5 new granular permission codes (INSERT IGNORE / code-checked)
    $newPermissions = [
        ['id' => '20000000-0000-4000-8000-000000000103', 'code' => 'notifications.read',        'description' => 'Inspect non-authentication notification queue entries and delivery logs'],
        ['id' => '20000000-0000-4000-8000-000000000104', 'code' => 'notifications.retry',       'description' => 'Re-queue failed or pending non-authentication notifications for delivery retry'],
        ['id' => '20000000-0000-4000-8000-000000000105', 'code' => 'notifications.resend',      'description' => 'Issue audited intentional resends of non-authentication notifications'],
        ['id' => '20000000-0000-4000-8000-000000000106', 'code' => 'notifications.auth.manage', 'description' => 'Superadmin-only authority to inspect or manage authentication and security notifications'],
        ['id' => '20000000-0000-4000-8000-000000000107', 'code' => 'attendance.override',       'description' => 'Perform audited attendance corrections and future event-day attendance overrides'],
    ];

    $permIdsByCode = [];
    if (pfgDbTableExists($pdo, 'permissions')) {
        $selPerm = $pdo->prepare('SELECT id FROM permissions WHERE code = :code LIMIT 1');
        $insPerm = $pdo->prepare('INSERT IGNORE INTO permissions (id, code, description, created_at) VALUES (:id, :code, :desc, :now)');
        foreach ($newPermissions as $perm) {
            $selPerm->execute([':code' => $perm['code']]);
            $existingId = $selPerm->fetchColumn();
            if ($existingId) {
                $permIdsByCode[$perm['code']] = (string)$existingId;
                continue;
            }
            $insPerm->execute([
                ':id'   => $perm['id'],
                ':code' => $perm['code'],
                ':desc' => $perm['description'],
                ':now'  => $now,
            ]);
            if ($insPerm->rowCount() > 0) {
                $changes['permissionsAdded'][] = $perm['code'];
            }
            $selPerm->execute([':code' => $perm['code']]);
            $resolvedId = $selPerm->fetchColumn();
            if ($resolvedId) {
                $permIdsByCode[$perm['code']] = (string)$resolvedId;
            }
        }
    }

    // 2. Grant the 5 new permissions to SUPERADMIN in `role_permissions` if missing
    if (pfgDbTableExists($pdo, 'roles') && pfgDbTableExists($pdo, 'role_permissions') && !empty($permIdsByCode)) {
        $saRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'SUPERADMIN' LIMIT 1")->fetchColumn();
        if ($saRoleId) {
            $chkRp = $pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = :rid AND permission_id = :pid');
            $insRp = $pdo->prepare('INSERT IGNORE INTO role_permissions (id, role_id, permission_id) VALUES (:id, :rid, :pid)');
            $rpIdx = 103;
            foreach ($permIdsByCode as $code => $pid) {
                $chkRp->execute([':rid' => (string)$saRoleId, ':pid' => $pid]);
                if ((int)$chkRp->fetchColumn() > 0) {
                    continue;
                }
                $insRp->execute([
                    ':id'  => sprintf('21000000-0000-4000-8000-%012d', $rpIdx++),
                    ':rid' => (string)$saRoleId,
                    ':pid' => $pid,
                ]);
                if ($insRp->rowCount() > 0) {
                    $changes['rolePermissionsAdded'][] = 'SUPERADMIN:' . $code;
                }
            }
        }
    }

    // 3. Align operational permission profiles in `admin_permission_profiles`
    if (pfgDbTableExists($pdo, 'admin_permission_profiles')) {
        $selProf = $pdo->prepare('SELECT id, permissions_json FROM admin_permission_profiles WHERE slug = :slug LIMIT 1');
        $updProf = $pdo->prepare('UPDATE admin_permission_profiles SET permissions_json = :perms, updated_at = :now WHERE id = :id');

        $profileRules = [
            'communications_manager' => [
                'ensure' => ['communications.manage', 'notifications.read', 'notifications.retry', 'notifications.resend'],
                'remove' => ['notifications.auth.manage'],
            ],
            'registration_manager' => [
                'ensure' => ['notifications.read', 'attendance.override'],
                'remove' => ['notifications.retry', 'notifications.resend', 'notifications.auth.manage'],
            ],
            'content_manager' => [
                'ensure' => [],
                'remove' => ['notifications.read', 'notifications.retry', 'notifications.resend', 'notifications.auth.manage'],
            ],
            'volunteer_coordinator' => [
                'ensure' => [],
                'remove' => ['notifications.read', 'notifications.retry', 'notifications.resend', 'notifications.auth.manage'],
            ],
            'sli_coordinator' => [
                'ensure' => [],
                'remove' => ['notifications.read', 'notifications.retry', 'notifications.resend', 'notifications.auth.manage'],
            ],
            'accessibility_coordinator' => [
                'ensure' => [],
                'remove' => ['notifications.read', 'notifications.retry', 'notifications.resend', 'notifications.auth.manage'],
            ],
            'programme_coordinator' => [
                'ensure' => [],
                'remove' => ['notifications.read', 'notifications.retry', 'notifications.resend', 'notifications.auth.manage'],
            ],
        ];

        foreach ($profileRules as $slug => $rule) {
            $selProf->execute([':slug' => $slug]);
            $row = $selProf->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                continue;
            }
            $decoded = json_decode((string)($row['permissions_json'] ?? '[]'), true);
            $origPerms = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
            $perms = array_values(array_diff($origPerms, $rule['remove']));
            foreach ($rule['ensure'] as $addCode) {
                if (!in_array($addCode, $perms, true)) {
                    $perms[] = $addCode;
                }
            }
            if ($perms !== $origPerms) {
                $updProf->execute([
                    ':perms' => json_encode($perms, JSON_UNESCAPED_SLASHES),
                    ':now'   => $now,
                    ':id'    => (string)$row['id'],
                ]);
                $changes['profilesUpdated'][] = $slug;
            }
        }

        // Defense-in-depth: ensure NO profile holds Superadmin-only `notifications.auth.manage`
        $allProfs = $pdo->query('SELECT id, slug, permissions_json FROM admin_permission_profiles')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($allProfs as $pRow) {
            $decoded = json_decode((string)($pRow['permissions_json'] ?? '[]'), true);
            if (!is_array($decoded) || !in_array('notifications.auth.manage', $decoded, true)) {
                continue;
            }
            $filtered = array_values(array_diff(array_filter($decoded, 'is_string'), ['notifications.auth.manage']));
            $updProf->execute([
                ':perms' => json_encode($filtered, JSON_UNESCAPED_SLASHES),
                ':now'   => $now,
                ':id'    => (string)$pRow['id'],
            ]);
            if (!in_array((string)$pRow['slug'], $changes['profilesUpdated'], true)) {
                $changes['profilesUpdated'][] = (string)$pRow['slug'];
            }
        }
    }

    return $changes;
};

