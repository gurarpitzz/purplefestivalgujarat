<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Superadmin Staff & User Management API
 * Endpoint: GET / POST / PATCH /api/admin/users (or /api/admin/users.php)
 * Strictly restricted to SUPERADMIN role (admin_accounts.manage).
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['SUPERADMIN']);
    requirePermission(PERMISSIONS['ADMIN_ACCOUNTS_MANAGE'], $user);
    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        requireCsrfToken($user);
    }
    $pdo = getDb();

    // =========================================================================
    // GET: List All Users with Roles & Custom Permissions
    // =========================================================================
    if ($method === 'GET') {
        $roleFilter = strtoupper(trim((string)($_GET['role'] ?? '')));
        $search = strtolower(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')));

        $stUsers = $pdo->query('
            SELECT id, username, email, full_name, phone, country, is_active, mfa_enabled, last_login_at, created_at
            FROM users
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
        ');
        $allUsers = $stUsers ? $stUsers->fetchAll(PDO::FETCH_ASSOC) : [];

        $rolesByUser = [];
        $stRoles = $pdo->query('
            SELECT ur.user_id, ur.role_id, r.code AS role_code, ur.custom_permissions
            FROM user_roles ur
            INNER JOIN roles r ON ur.role_id = r.id
        ');
        foreach (($stRoles ? $stRoles->fetchAll(PDO::FETCH_ASSOC) : []) as $rl) {
            $uid = (string)$rl['user_id'];
            if (!isset($rolesByUser[$uid])) {
                $rolesByUser[$uid] = [
                    'roles'             => [],
                    'customPermissions' => [],
                ];
            }
            $rCode = (string)$rl['role_code'];
            if (!in_array($rCode, $rolesByUser[$uid]['roles'], true)) {
                $rolesByUser[$uid]['roles'][] = $rCode;
            }
            $cPerms = jsonDecode($rl['custom_permissions'] ?? '[]', []);
            if (is_array($cPerms)) {
                foreach ($cPerms as $cp) {
                    if (is_string($cp) && $cp !== '' && !in_array($cp, $rolesByUser[$uid]['customPermissions'], true)) {
                        $rolesByUser[$uid]['customPermissions'][] = $cp;
                    }
                }
            }
        }

        $items = [];
        foreach ($allUsers as $u) {
            $uid = (string)$u['id'];
            $rp = $rolesByUser[$uid] ?? null;
            $roles = ($rp && count($rp['roles']) > 0) ? $rp['roles'] : ['PARTICIPANT'];
            $customPermissions = $rp['customPermissions'] ?? [];

            if ($roleFilter !== '' && !in_array($roleFilter, $roles, true)) {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower(
                    ($u['full_name'] ?? '') . ' ' .
                    ($u['username'] ?? '') . ' ' .
                    ($u['email'] ?? '') . ' ' .
                    ($u['phone'] ?? '')
                );
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

            $uname = !empty($u['username'])
                ? (string)$u['username']
                : explode('@', (string)$u['email'])[0];

            $items[] = [
                'id'                => $uid,
                'username'          => $uname,
                'email'             => (string)$u['email'],
                'fullName'          => (string)$u['full_name'],
                'phone'             => $u['phone'] !== null ? (string)$u['phone'] : null,
                'country'           => (string)($u['country'] ?? 'India'),
                'isActive'          => !empty($u['is_active']),
                'mfaEnabled'        => !empty($u['mfa_enabled']),
                'roles'             => $roles,
                'customPermissions' => $customPermissions,
                'lastLoginAt'       => $u['last_login_at'] ?? null,
                'createdAt'         => (string)($u['created_at'] ?? ''),
            ];
        }

        jsonResponse([
            'items'                => $items,
            'availablePermissions' => array_values(PERMISSIONS),
        ]);
    }

    // =========================================================================
    // POST: Create Staff Account (ADMIN, RGST, SUPERADMIN, PARTICIPANT)
    // =========================================================================
    if ($method === 'POST') {
        $input = readRequestInput();
        $fullName = trim((string)($input['fullName'] ?? ''));
        $rawUsername = strtolower(trim((string)($input['username'] ?? '')));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $phone = trim((string)($input['phone'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $roleCode = strtoupper(trim((string)($input['roleCode'] ?? 'ADMIN')));
        $rawCustomPerms = isset($input['customPermissions']) && is_array($input['customPermissions'])
            ? $input['customPermissions']
            : [];

        if (strlen($fullName) < 2) {
            jsonResponse(['error' => 'Full name is required (min 2 characters).'], 400);
        }
        $username = preg_replace('/[^a-z0-9._-]/', '', $rawUsername) ?: '';
        if ($username === '' && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $username = preg_replace('/[^a-z0-9._-]/', '', strtolower((string)explode('@', $email)[0])) ?: '';
        }
        if (strlen($username) < 3) {
            jsonResponse(['error' => 'Username is required (min 3 alphanumeric characters: a-z, 0-9, ., _, -).'], 400);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $username . '@staff.purplefestivalgujarat.com';
        }
        if (strlen($password) < 8) {
            jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
        }
        if (!in_array($roleCode, ['ADMIN', 'RGST', 'SUPERADMIN', 'PARTICIPANT'], true)) {
            jsonResponse(['error' => 'Invalid roleCode. Allowed: ADMIN, RGST, SUPERADMIN, PARTICIPANT.'], 400);
        }

        $stCheck = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = :username OR LOWER(email) = :email LIMIT 1');
        $stCheck->execute([
            ':username' => $username,
            ':email'    => $email,
        ]);
        if ($stCheck->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['error' => 'An account with this username or email already exists.'], 409);
        }

        $stRole = $pdo->prepare('SELECT id, code FROM roles WHERE code = :code LIMIT 1');
        $stRole->execute([':code' => $roleCode]);
        $roleRow = $stRole->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) {
            jsonResponse(['error' => "Role {$roleCode} is not seeded in the database."], 500);
        }

        $validPermValues = array_values(PERMISSIONS);
        $filteredCustomPerms = [];
        if (in_array($roleCode, ['ADMIN', 'SUPERADMIN'], true)) {
            foreach ($rawCustomPerms as $p) {
                if (is_string($p) && in_array($p, $validPermValues, true) && !in_array($p, $filteredCustomPerms, true)) {
                    $filteredCustomPerms[] = $p;
                }
            }
        }

        $userId = uuid4();
        $passwordHash = hashPassword($password);
        $now = nowSql();

        $insUser = $pdo->prepare('
            INSERT INTO users (id, username, email, email_verified_at, password_hash, full_name, phone, country, is_active, created_at, updated_at)
            VALUES (:id, :username, :email, :now, :pw, :name, :phone, :country, 1, :now, :now)
        ');
        $insUser->execute([
            ':id'       => $userId,
            ':username' => $username,
            ':email'    => $email,
            ':now'      => $now,
            ':pw'       => $passwordHash,
            ':name'     => $fullName,
            ':phone'    => $phone !== '' ? $phone : null,
            ':country'  => 'India',
        ]);

        $insRole = $pdo->prepare('
            INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_by, assigned_at)
            VALUES (:id, :uid, :rid, :perms, :by, :now)
        ');
        $insRole->execute([
            ':id'    => uuid4(),
            ':uid'   => $userId,
            ':rid'   => $roleRow['id'],
            ':perms' => jsonEncode($filteredCustomPerms),
            ':by'    => $user['id'],
            ':now'   => $now,
        ]);

        try {
            $actStmt = $pdo->prepare('
                INSERT INTO admin_actions (id, admin_user_id, target_user_id, action_type, after_state, created_at)
                VALUES (:id, :admin_id, :target_id, :action_type, :after_state, :now)
            ');
            $actStmt->execute([
                ':id'          => uuid4(),
                ':admin_id'    => $user['id'],
                ':target_id'   => $userId,
                ':action_type' => "STAFF_ACCOUNT_CREATED_{$roleCode}",
                ':after_state' => jsonEncode([
                    'username'          => $username,
                    'email'             => $email,
                    'roleCode'          => $roleCode,
                    'customPermissions' => $filteredCustomPerms,
                ]),
                ':now'         => $now,
            ]);
        } catch (Throwable $e) {
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'STAFF_USER_CREATED',
            'resourceType' => 'user',
            'resourceId'   => $userId,
            'metadata'     => [
                'username'          => $username,
                'email'             => $email,
                'roleCode'          => $roleCode,
                'customPermissions' => $filteredCustomPerms,
            ],
        ]);

        jsonResponse([
            'user' => [
                'id'                => $userId,
                'username'          => $username,
                'email'             => $email,
                'fullName'          => $fullName,
                'phone'             => $phone !== '' ? $phone : null,
                'isActive'          => true,
                'createdAt'         => $now,
                'roles'             => [$roleCode],
                'customPermissions' => $filteredCustomPerms,
            ],
        ], 201);
    }

    // =========================================================================
    // PATCH: Toggle Active Status, Reset Password, Update Role / Custom Permissions
    // =========================================================================
    if ($method === 'PATCH') {
        $input = readRequestInput();
        $targetUserId = trim((string)($input['userId'] ?? $input['id'] ?? ''));

        if ($targetUserId === '') {
            jsonResponse(['error' => 'userId is required.'], 400);
        }

        if ($targetUserId === (string)$user['id'] && array_key_exists('isActive', $input) && empty($input['isActive'])) {
            jsonResponse(['error' => 'You cannot deactivate your own Superadmin account.'], 400);
        }

        $stTarget = $pdo->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stTarget->execute([':id' => $targetUserId]);
        $target = $stTarget->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            jsonResponse(['error' => 'User not found.'], 404);
        }

        $targetRp = getUserRolesAndPermissions($targetUserId, $pdo);
        $targetRoles = $targetRp['roles'];
        $targetIsSuperadmin = in_array('SUPERADMIN', $targetRoles, true);

        $newRoleCode = (!empty($input['roleCode']) && is_string($input['roleCode']))
            ? strtoupper(trim($input['roleCode']))
            : null;
        if ($newRoleCode !== null && !in_array($newRoleCode, ['ADMIN', 'RGST', 'SUPERADMIN', 'PARTICIPANT'], true)) {
            jsonResponse(['error' => 'Invalid roleCode. Allowed: ADMIN, RGST, SUPERADMIN, PARTICIPANT.'], 400);
        }

        // Prevent self-demotion
        if ($targetUserId === (string)$user['id'] && $newRoleCode !== null && $newRoleCode !== 'SUPERADMIN') {
            jsonResponse(['error' => 'You cannot demote your own Superadmin account.'], 400);
        }

        $now = nowSql();
        $nextActive = array_key_exists('isActive', $input)
            ? (!empty($input['isActive']) ? 1 : 0)
            : (int)$target['is_active'];

        // Protect the last active SUPERADMIN from deactivation or demotion
        $demotingSuperadmin = $targetIsSuperadmin && ($nextActive === 0 || ($newRoleCode !== null && $newRoleCode !== 'SUPERADMIN'));
        if ($demotingSuperadmin) {
            $cntStmt = $pdo->query("
                SELECT COUNT(DISTINCT u.id)
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE r.code = 'SUPERADMIN' AND u.is_active = 1 AND u.deleted_at IS NULL
            ");
            $activeSuperadmins = (int)($cntStmt ? $cntStmt->fetchColumn() : 0);
            if ($activeSuperadmins <= 1) {
                jsonResponse(['error' => 'Cannot deactivate or demote the last active Superadmin account.'], 400);
            }
        }

        // Reject custom administrative permissions on RGST or PARTICIPANT accounts
        $effectiveRoleAfter = $newRoleCode ?? ($targetRoles[0] ?? 'PARTICIPANT');
        if (isset($input['customPermissions']) && is_array($input['customPermissions']) && count($input['customPermissions']) > 0) {
            if (!in_array($effectiveRoleAfter, ['ADMIN', 'SUPERADMIN'], true)) {
                jsonResponse(['error' => 'Custom administrative permissions cannot be assigned to RGST or PARTICIPANT accounts.'], 400);
            }
        }

        $newPassword = (string)($input['newPassword'] ?? $input['password'] ?? '');
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            jsonResponse(['error' => 'New password must be at least 8 characters.'], 400);
        }

        // Superadmin-assisted audited MFA recovery for another locked-out staff member (never self-bypass)
        $resetMfa = !empty($input['resetMfa']);
        if ($resetMfa) {
            if ($targetUserId === (string)$user['id']) {
                jsonResponse(['error' => 'You cannot reset your own MFA through admin recovery. Use authenticated MFA replacement instead.'], 400);
            }
            $pdo->prepare('UPDATE users SET mfa_enabled = 0, mfa_secret_encrypted = NULL, mfa_secret = NULL, updated_at = :now WHERE id = :id')
                ->execute([':now' => $now, ':id' => $targetUserId]);
            $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider IN ('mfa_setup', 'mfa_recovery', 'mfa_replay')")
                ->execute([':uid' => $targetUserId]);
            revokeAllUserSessions($targetUserId, null, $pdo, (string)$user['id'], 'superadmin_mfa_reset');
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'MFA_ADMIN_RESET',
                'resourceType' => 'user',
                'resourceId'   => $targetUserId,
                'metadata'     => ['targetEmail' => $target['email']],
            ]);
        }

        $shouldRevokeSessions = false;
        $revokeReason = 'admin_account_update';

        if (array_key_exists('isActive', $input) || $newPassword !== '') {
            if ($newPassword !== '') {
                $up = $pdo->prepare('UPDATE users SET is_active = :active, password_hash = :pw, failed_login_attempts = 0, locked_until = NULL, updated_at = :now WHERE id = :id');
                $up->execute([
                    ':active' => $nextActive,
                    ':pw'     => hashPassword($newPassword),
                    ':now'    => $now,
                    ':id'     => $targetUserId,
                ]);
                $shouldRevokeSessions = true;
                $revokeReason = 'admin_password_reset';
            } else {
                $up = $pdo->prepare('UPDATE users SET is_active = :active, updated_at = :now WHERE id = :id');
                $up->execute([
                    ':active' => $nextActive,
                    ':now'    => $now,
                    ':id'     => $targetUserId,
                ]);
            }

            if ($nextActive === 0) {
                $shouldRevokeSessions = true;
                $revokeReason = 'account_deactivated';
            }
        }

        // Optional role change
        if ($newRoleCode !== null) {
            $stR = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
            $stR->execute([':code' => $newRoleCode]);
            $rRow = $stR->fetch(PDO::FETCH_ASSOC);
            if ($rRow) {
                $delUr = $pdo->prepare('DELETE FROM user_roles WHERE user_id = :uid');
                $delUr->execute([':uid' => $targetUserId]);
                $insUr = $pdo->prepare('
                    INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_by, assigned_at)
                    VALUES (:id, :uid, :rid, :perms, :by, :now)
                ');
                $insUr->execute([
                    ':id'    => uuid4(),
                    ':uid'   => $targetUserId,
                    ':rid'   => $rRow['id'],
                    ':perms' => '[]',
                    ':by'    => $user['id'],
                    ':now'   => $now,
                ]);
                $shouldRevokeSessions = true;
                $revokeReason = 'role_changed';
                logAudit([
                    'actorUserId'  => $user['id'],
                    'action'       => 'ROLE_CHANGED',
                    'resourceType' => 'user',
                    'resourceId'   => $targetUserId,
                    'metadata'     => ['previousRoles' => $targetRoles, 'newRole' => $newRoleCode],
                ]);
            }
        }

        // Custom permissions update
        if (isset($input['customPermissions']) && is_array($input['customPermissions'])) {
            $validPermValues = array_values(PERMISSIONS);
            $cleanPerms = [];
            if (in_array($effectiveRoleAfter, ['ADMIN', 'SUPERADMIN'], true)) {
                foreach ($input['customPermissions'] as $p) {
                    if (is_string($p) && in_array($p, $validPermValues, true) && !in_array($p, $cleanPerms, true)) {
                        $cleanPerms[] = $p;
                    }
                }
            }

            $upUr = $pdo->prepare('UPDATE user_roles SET custom_permissions = :perms WHERE user_id = :uid');
            $upUr->execute([
                ':perms' => jsonEncode($cleanPerms),
                ':uid'   => $targetUserId,
            ]);
            $shouldRevokeSessions = true;
            $revokeReason = 'permissions_changed';
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'PERMISSIONS_CHANGED',
                'resourceType' => 'user',
                'resourceId'   => $targetUserId,
                'metadata'     => ['customPermissions' => $cleanPerms],
            ]);
        }

        if ($shouldRevokeSessions) {
            revokeAllUserSessions($targetUserId, null, $pdo, (string)$user['id'], $revokeReason);
        }

        try {
            $actStmt = $pdo->prepare('
                INSERT INTO admin_actions (id, admin_user_id, target_user_id, action_type, before_state, after_state, created_at)
                VALUES (:id, :admin_id, :target_id, :action_type, :before_state, :after_state, :now)
            ');
            $actStmt->execute([
                ':id'           => uuid4(),
                ':admin_id'     => $user['id'],
                ':target_id'    => $targetUserId,
                ':action_type'  => 'USER_ACCOUNT_UPDATED',
                ':before_state' => jsonEncode(['isActive' => (bool)$target['is_active']]),
                ':after_state'  => jsonEncode([
                    'isActive'          => (bool)$nextActive,
                    'customPermissions' => $input['customPermissions'] ?? null,
                    'passwordReset'     => $newPassword !== '',
                ]),
                ':now'          => $now,
            ]);
        } catch (Throwable $e) {
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'USER_ACCOUNT_UPDATED',
            'resourceType' => 'user',
            'resourceId'   => $targetUserId,
            'metadata'     => [
                'email'                    => $target['email'],
                'isActive'                 => (bool)$nextActive,
                'customPermissionsUpdated' => isset($input['customPermissions']),
                'passwordReset'            => $newPassword !== '',
            ],
        ]);

        jsonResponse([
            'updated' => true,
            'userId'  => $targetUserId,
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/users.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process user management request.'], 500);
}
