<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 8 Remediation Migration 011
 * Aligns canonical permissions registry (`programme.manage`, `communications.manage`)
 * and system operational permission profiles (`programme_coordinator`, `communications_manager`).
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

    // 1. Ensure `permissions` table has rows for `programme.manage` and `communications.manage`
    $newPermissions = [
        ['id' => '20000000-0000-4000-8000-000000000101', 'code' => 'programme.manage',      'description' => 'Manage festival programme sessions, tracks, and venue logistics'],
        ['id' => '20000000-0000-4000-8000-000000000102', 'code' => 'communications.manage', 'description' => 'Manage festival announcements and participant communication broadcasts'],
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

    // 2. Grant the new permissions to SUPERADMIN in `role_permissions` if missing
    if (pfgDbTableExists($pdo, 'roles') && pfgDbTableExists($pdo, 'role_permissions') && !empty($permIdsByCode)) {
        $saRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'SUPERADMIN' LIMIT 1")->fetchColumn();
        if ($saRoleId) {
            $chkRp = $pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = :rid AND permission_id = :pid');
            $insRp = $pdo->prepare('INSERT IGNORE INTO role_permissions (id, role_id, permission_id) VALUES (:id, :rid, :pid)');
            $rpIdx = 101;
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

    // 3. Update system operational profiles `programme_coordinator` and `communications_manager`
    if (pfgDbTableExists($pdo, 'admin_permission_profiles')) {
        $profileUpdates = [
            'programme_coordinator'  => 'programme.manage',
            'communications_manager' => 'communications.manage',
        ];
        $selProf = $pdo->prepare('SELECT id, permissions_json FROM admin_permission_profiles WHERE slug = :slug LIMIT 1');
        $updProf = $pdo->prepare('UPDATE admin_permission_profiles SET permissions_json = :perms, updated_at = :now WHERE id = :id');

        foreach ($profileUpdates as $slug => $permCode) {
            $selProf->execute([':slug' => $slug]);
            $row = $selProf->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                continue;
            }
            $decoded = json_decode((string)($row['permissions_json'] ?? '[]'), true);
            $perms = is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
            if (!in_array($permCode, $perms, true)) {
                $perms[] = $permCode;
                $updProf->execute([
                    ':perms' => json_encode($perms, JSON_UNESCAPED_SLASHES),
                    ':now'   => $now,
                    ':id'    => (string)$row['id'],
                ]);
                $changes['profilesUpdated'][] = $slug . '+' . $permCode;
            }
        }
    }

    return $changes;
};
