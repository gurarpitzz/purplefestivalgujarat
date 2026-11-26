<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Superadmin Staff & User Management API
 * Endpoint: GET / POST / PATCH /api/admin/users (or /api/admin/users.php)
 * Strictly restricted to SUPERADMIN role (admin_accounts.manage) + verified D-20 MFA + session-bound CSRF on writes.
 *
 * Phase 8 additions:
 *  - `permissionProfileId` (ADMIN accounts only; RGST / PARTICIPANT / SUPERADMIN receive 400)
 *  - `customPermissions` (ADMIN / SUPERADMIN only; unknown codes and Superadmin-only authorities rejected with 400)
 *  - Server-side pagination, effective-permission resolution, staff lifecycle (activate / deactivate / reset password / reset MFA)
 *  - Session revocation on role, profile, custom-permission, active-status or password change.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin-common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

const PFG_USER_ROLE_CODES = ['ADMIN', 'RGST', 'SUPERADMIN', 'PARTICIPANT'];

/**
 * True when the request body explicitly carries a (non-empty) permission profile reference.
 */
function pfgUsersProfileRequested(array $input): bool
{
    foreach (['permissionProfileId', 'permission_profile_id'] as $k) {
        if (array_key_exists($k, $input) && $input[$k] !== null && $input[$k] !== '' && $input[$k] !== false) {
            return true;
        }
    }
    return false;
}

function pfgUsersProfileKeyPresent(array $input): bool
{
    return array_key_exists('permissionProfileId', $input) || array_key_exists('permission_profile_id', $input);
}

function pfgUsersProfileValue(array $input): ?string
{
    $v = $input['permissionProfileId'] ?? $input['permission_profile_id'] ?? null;
    return is_string($v) && trim($v) !== '' ? trim($v) : null;
}

function pfgUsersCustomPermsRequested(array $input): bool
{
    foreach (['customPermissions', 'custom_permissions'] as $k) {
        if (isset($input[$k]) && is_array($input[$k]) && count($input[$k]) > 0) {
            return true;
        }
    }
    return false;
}

function pfgUsersCustomPermsKeyPresent(array $input): bool
{
    foreach (['customPermissions', 'custom_permissions'] as $k) {
        if (isset($input[$k]) && is_array($input[$k])) {
            return true;
        }
    }
    return false;
}

function pfgUsersRawCustomPerms(array $input): array
{
    $v = $input['customPermissions'] ?? $input['custom_permissions'] ?? [];
    return is_array($v) ? $v : [];
}

/**
 * Validates custom permission codes. 400 on unknown codes or Superadmin-only authorities (anti-escalation).
 */
function pfgUsersValidateCustomPerms(array $raw, string $roleCode): array
{
    $valid = array_values(PERMISSIONS);
    $forbidden = pfgProfileForbiddenPermissions();
    $clean = [];
    $unknown = [];
    foreach ($raw as $p) {
        if (!is_string($p) || $p === '') {
            jsonResponse(['error' => 'Every custom permission must be a non-empty string.'], 400);
        }
        if (!in_array($p, $valid, true)) {
            $unknown[] = $p;
            continue;
        }
        if ($roleCode === 'ADMIN' && in_array($p, $forbidden, true)) {
            jsonResponse(['error' => "Permission {$p} is a Superadmin-only authority and cannot be granted to an ADMIN account."], 400);
        }
        if (!in_array($p, $clean, true)) {
            $clean[] = $p;
        }
    }
    if ($unknown) {
        jsonResponse(['error' => 'Unknown permission code(s): ' . implode(', ', array_slice($unknown, 0, 10)), 'unknown' => $unknown], 400);
    }
    return $clean;
}

/**
 * Loads an ACTIVE permission profile by id or sends 400/503.
 */
function pfgUsersRequireActiveProfile(PDO $pdo, string $profileId): array
{
    if (!pfgAdminTableExists($pdo, 'admin_permission_profiles') || !pfgAdminColumnExists($pdo, 'users', 'permission_profile_id')) {
        jsonResponse(['error' => 'Permission profiles are unavailable until migration 009 has been applied.'], 503);
    }
    $st = $pdo->prepare('SELECT id, slug, name, is_active FROM admin_permission_profiles WHERE id = :id LIMIT 1');
    $st->execute([':id' => $profileId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(['error' => 'Permission profile not found.'], 400);
    }
    if (empty($row['is_active'])) {
        jsonResponse(['error' => 'Permission profile is inactive.'], 400);
    }
    return $row;
}

function pfgUsersHighestRole(array $roles): string
{
    foreach (['SUPERADMIN', 'ADMIN', 'RGST'] as $r) {
        if (in_array($r, $roles, true)) {
            return $r;
        }
    }
    return 'PARTICIPANT';
}

function pfgUsersCountActiveSuperadmins(PDO $pdo): int
{
    $st = $pdo->query("
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE r.code = 'SUPERADMIN' AND u.is_active = 1 AND u.deleted_at IS NULL
    ");
    return (int)($st ? $st->fetchColumn() : 0);
}

try {
    $user = requireRole(['SUPERADMIN']);
    requirePermission(PERMISSIONS['ADMIN_ACCOUNTS_MANAGE'], $user);
    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        requireCsrfToken($user);
    }
    $pdo = getDb();
    $hasProfileColumn = pfgAdminColumnExists($pdo, 'users', 'permission_profile_id')
        && pfgAdminTableExists($pdo, 'admin_permission_profiles');

    // =========================================================================
    // GET: Paginated user list with roles, profile & effective permissions
    // =========================================================================
    if ($method === 'GET') {
        $roleFilter = strtoupper(trim((string)($_GET['role'] ?? '')));
        $search = strtolower(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')));
        [$page, $pageSize, $offset] = pfgAdminPageParams(50, 200);

        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($roleFilter !== '') {
            if (!in_array($roleFilter, PFG_USER_ROLE_CODES, true)) {
                jsonResponse(['error' => 'Invalid role filter.'], 400);
            }
            if ($roleFilter === 'PARTICIPANT') {
                $where[] = "(EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = 'PARTICIPANT')
                            OR NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id))";
            } else {
                $where[] = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = :role_filter)';
                $params[':role_filter'] = $roleFilter;
            }
        }
        if ($search !== '') {
            $where[] = "(LOWER(u.full_name) LIKE :q OR LOWER(COALESCE(u.username, '')) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(COALESCE(u.phone, '')) LIKE :q)";
            $params[':q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }
        $whereSql = implode(' AND ', $where);

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $profileSelect = $hasProfileColumn ? ', u.permission_profile_id' : '';
        $stUsers = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.full_name, u.phone, u.country, u.is_active, u.mfa_enabled, u.last_login_at, u.created_at{$profileSelect}
            FROM users u
            WHERE {$whereSql}
            ORDER BY u.created_at DESC, u.id ASC
            LIMIT {$pageSize} OFFSET {$offset}
        ");
        $stUsers->execute($params);
        $pageUsers = $stUsers->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $profilesById = [];
        $profileList = [];
        if ($hasProfileColumn) {
            $stP = $pdo->query('SELECT id, slug, name, is_active FROM admin_permission_profiles ORDER BY name ASC');
            foreach (($stP ? $stP->fetchAll(PDO::FETCH_ASSOC) : []) as $p) {
                $profilesById[(string)$p['id']] = $p;
                if (!empty($p['is_active'])) {
                    $profileList[] = ['id' => (string)$p['id'], 'slug' => (string)$p['slug'], 'name' => (string)$p['name']];
                }
            }
        }

        $customByUser = [];
        if ($pageUsers) {
            $ids = array_map(static fn(array $u): string => (string)$u['id'], $pageUsers);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stR = $pdo->prepare("SELECT ur.user_id, ur.custom_permissions FROM user_roles ur WHERE ur.user_id IN ({$ph})");
            $stR->execute($ids);
            foreach (($stR->fetchAll(PDO::FETCH_ASSOC) ?: []) as $rl) {
                $uid = (string)$rl['user_id'];
                $cPerms = jsonDecode($rl['custom_permissions'] ?? '[]', []);
                foreach (is_array($cPerms) ? $cPerms : [] as $cp) {
                    if (is_string($cp) && $cp !== '') {
                        $customByUser[$uid][$cp] = true;
                    }
                }
            }
        }

        $items = [];
        foreach ($pageUsers as $u) {
            $uid = (string)$u['id'];
            $rp = getUserRolesAndPermissions($uid, $pdo);
            $profileId = $hasProfileColumn && !empty($u['permission_profile_id']) ? (string)$u['permission_profile_id'] : null;
            $profileRow = $profileId !== null ? ($profilesById[$profileId] ?? null) : null;
            $uname = !empty($u['username']) ? (string)$u['username'] : explode('@', (string)$u['email'])[0];

            $items[] = [
                'id'                   => $uid,
                'username'             => $uname,
                'email'                => (string)$u['email'],
                'fullName'             => (string)$u['full_name'],
                'phone'                => $u['phone'] !== null ? (string)$u['phone'] : null,
                'country'              => (string)($u['country'] ?? 'India'),
                'isActive'             => !empty($u['is_active']),
                'mfaEnabled'           => !empty($u['mfa_enabled']),
                'roles'                => $rp['roles'],
                'customPermissions'    => array_keys($customByUser[$uid] ?? []),
                'permissionProfileId'  => $profileRow ? (string)$profileRow['id'] : null,
                'permissionProfileSlug' => $profileRow ? (string)$profileRow['slug'] : null,
                'permissionProfileName' => $profileRow ? (string)$profileRow['name'] : null,
                'effectivePermissions' => $rp['permissions'],
                'lastLoginAt'          => $u['last_login_at'] ?? null,
                'createdAt'            => (string)($u['created_at'] ?? ''),
            ];
        }

        jsonResponse([
            'items'                => $items,
            'availablePermissions' => array_values(PERMISSIONS),
            'forbiddenForAdmin'    => pfgProfileForbiddenPermissions(),
            'profiles'             => $profileList,
            'pagination'           => pfgAdminPaginationMeta($page, $pageSize, $total),
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
        if (!in_array($roleCode, PFG_USER_ROLE_CODES, true)) {
            jsonResponse(['error' => 'Invalid roleCode. Allowed: ADMIN, RGST, SUPERADMIN, PARTICIPANT.'], 400);
        }

        // Anti-escalation: operational profiles & custom permissions belong to the ADMIN tier ONLY.
        if (pfgUsersProfileRequested($input) && $roleCode !== 'ADMIN') {
            jsonResponse(['error' => 'Permission profiles can only be assigned to ADMIN accounts.'], 400);
        }
        if (pfgUsersCustomPermsRequested($input) && !in_array($roleCode, ['ADMIN', 'SUPERADMIN'], true)) {
            jsonResponse(['error' => 'Custom administrative permissions cannot be assigned to RGST or PARTICIPANT accounts.'], 400);
        }

        $profileId = null;
        if ($roleCode === 'ADMIN' && pfgUsersProfileRequested($input)) {
            $profileId = (string)pfgUsersRequireActiveProfile($pdo, (string)pfgUsersProfileValue($input))['id'];
        }
        $filteredCustomPerms = in_array($roleCode, ['ADMIN', 'SUPERADMIN'], true)
            ? pfgUsersValidateCustomPerms(pfgUsersRawCustomPerms($input), $roleCode)
            : [];

        $stCheck = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = :username OR LOWER(email) = :email LIMIT 1');
        $stCheck->execute([':username' => $username, ':email' => $email]);
        if ($stCheck->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['error' => 'An account with this username or email already exists.'], 409);
        }

        $stRole = $pdo->prepare('SELECT id, code FROM roles WHERE code = :code LIMIT 1');
        $stRole->execute([':code' => $roleCode]);
        $roleRow = $stRole->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) {
            jsonResponse(['error' => "Role {$roleCode} is not seeded in the database."], 500);
        }

        $userId = uuid4();
        $passwordHash = hashPassword($password);
        $now = nowSql();

        $pdo->beginTransaction();
        try {
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
            if ($profileId !== null) {
                $pdo->prepare('UPDATE users SET permission_profile_id = :pid WHERE id = :id')
                    ->execute([':pid' => $profileId, ':id' => $userId]);
            }
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
            $pdo->commit();
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txe;
        }

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
                    'username'            => $username,
                    'email'               => $email,
                    'roleCode'            => $roleCode,
                    'permissionProfileId' => $profileId,
                    'customPermissions'   => $filteredCustomPerms,
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
                'username'            => $username,
                'email'               => $email,
                'roleCode'            => $roleCode,
                'permissionProfileId' => $profileId,
                'customPermissions'   => $filteredCustomPerms,
            ],
        ]);

        jsonResponse([
            'user' => [
                'id'                  => $userId,
                'username'            => $username,
                'email'               => $email,
                'fullName'            => $fullName,
                'phone'               => $phone !== '' ? $phone : null,
                'isActive'            => true,
                'createdAt'           => $now,
                'roles'               => [$roleCode],
                'customPermissions'   => $filteredCustomPerms,
                'permissionProfileId' => $profileId,
            ],
        ], 201);
    }

    // =========================================================================
    // PATCH: Lifecycle, role, profile, custom permissions, password & MFA recovery
    // =========================================================================
    if ($method === 'PATCH' || $method === 'PUT') {
        $input = readRequestInput();
        $targetUserId = trim((string)($input['userId'] ?? $input['id'] ?? ''));

        if ($targetUserId === '') {
            jsonResponse(['error' => 'userId is required.'], 400);
        }
        $isSelf = $targetUserId === (string)$user['id'];

        if ($isSelf && array_key_exists('isActive', $input) && empty($input['isActive'])) {
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
        $currentRole = pfgUsersHighestRole($targetRoles);
        $targetIsSuperadmin = $currentRole === 'SUPERADMIN';

        $newRoleCode = (!empty($input['roleCode']) && is_string($input['roleCode']))
            ? strtoupper(trim($input['roleCode']))
            : null;
        if ($newRoleCode !== null && !in_array($newRoleCode, PFG_USER_ROLE_CODES, true)) {
            jsonResponse(['error' => 'Invalid roleCode. Allowed: ADMIN, RGST, SUPERADMIN, PARTICIPANT.'], 400);
        }

        // Prevent self-demotion
        if ($isSelf && $newRoleCode !== null && $newRoleCode !== 'SUPERADMIN') {
            jsonResponse(['error' => 'You cannot demote your own Superadmin account.'], 400);
        }

        $roleChanging = $newRoleCode !== null && $newRoleCode !== $currentRole;
        $effectiveRoleAfter = $newRoleCode ?? $currentRole;

        $now = nowSql();
        $nextActive = array_key_exists('isActive', $input)
            ? (!empty($input['isActive']) ? 1 : 0)
            : (int)$target['is_active'];

        // Protect the last active SUPERADMIN from deactivation or demotion
        $demotingSuperadmin = $targetIsSuperadmin && ($nextActive === 0 || ($newRoleCode !== null && $newRoleCode !== 'SUPERADMIN'));
        if ($demotingSuperadmin && pfgUsersCountActiveSuperadmins($pdo) <= 1) {
            jsonResponse(['error' => 'Cannot deactivate or demote the last active Superadmin account.'], 400);
        }

        // Anti-escalation: profiles → ADMIN only; custom permissions → ADMIN / SUPERADMIN only
        if (pfgUsersProfileRequested($input) && $effectiveRoleAfter !== 'ADMIN') {
            jsonResponse(['error' => 'Permission profiles can only be assigned to ADMIN accounts (never RGST, PARTICIPANT or SUPERADMIN).'], 400);
        }
        if (pfgUsersCustomPermsRequested($input) && !in_array($effectiveRoleAfter, ['ADMIN', 'SUPERADMIN'], true)) {
            jsonResponse(['error' => 'Custom administrative permissions cannot be assigned to RGST or PARTICIPANT accounts.'], 400);
        }

        $newProfileId = null;
        $profileChange = false;
        if (pfgUsersProfileKeyPresent($input)) {
            if (pfgUsersProfileRequested($input)) {
                $newProfileId = (string)pfgUsersRequireActiveProfile($pdo, (string)pfgUsersProfileValue($input))['id'];
            }
            $currentProfileId = $hasProfileColumn && !empty($target['permission_profile_id']) ? (string)$target['permission_profile_id'] : null;
            if ($newProfileId !== $currentProfileId) {
                if (!$hasProfileColumn) {
                    jsonResponse(['error' => 'Permission profiles are unavailable until migration 009 has been applied.'], 503);
                }
                $profileChange = true;
            }
        }

        $cleanCustom = null;
        if (pfgUsersCustomPermsKeyPresent($input)) {
            $cleanCustom = in_array($effectiveRoleAfter, ['ADMIN', 'SUPERADMIN'], true)
                ? pfgUsersValidateCustomPerms(pfgUsersRawCustomPerms($input), $effectiveRoleAfter)
                : [];
        }

        $newPassword = (string)($input['newPassword'] ?? $input['password'] ?? '');
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            jsonResponse(['error' => 'New password must be at least 8 characters.'], 400);
        }

        $resetMfa = !empty($input['resetMfa']);
        if ($resetMfa && $isSelf) {
            jsonResponse(['error' => 'You cannot reset your own MFA through admin recovery. Use authenticated MFA replacement instead.'], 400);
        }

        $roleRowId = null;
        if ($roleChanging) {
            $stR = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
            $stR->execute([':code' => $newRoleCode]);
            $roleRowId = $stR->fetchColumn() ?: null;
            if ($roleRowId === null) {
                jsonResponse(['error' => "Role {$newRoleCode} is not seeded in the database."], 500);
            }
        }

        // ---------- all validation passed: apply atomically ----------
        $revokeReasons = [];
        $pdo->beginTransaction();
        try {
            if ($resetMfa) {
                $pdo->prepare('UPDATE users SET mfa_enabled = 0, mfa_secret_encrypted = NULL, mfa_secret = NULL, updated_at = :now WHERE id = :id')
                    ->execute([':now' => $now, ':id' => $targetUserId]);
                $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider IN ('mfa_setup', 'mfa_recovery', 'mfa_replay')")
                    ->execute([':uid' => $targetUserId]);
                $revokeReasons[] = 'superadmin_mfa_reset';
            }

            if (array_key_exists('isActive', $input) && $nextActive !== (int)$target['is_active']) {
                $pdo->prepare('UPDATE users SET is_active = :active, updated_at = :now WHERE id = :id')
                    ->execute([':active' => $nextActive, ':now' => $now, ':id' => $targetUserId]);
                $revokeReasons[] = $nextActive === 0 ? 'account_deactivated' : 'account_reactivated';
            }

            if ($newPassword !== '') {
                $pdo->prepare('UPDATE users SET password_hash = :pw, failed_login_attempts = 0, locked_until = NULL, updated_at = :now WHERE id = :id')
                    ->execute([':pw' => hashPassword($newPassword), ':now' => $now, ':id' => $targetUserId]);
                $revokeReasons[] = 'admin_password_reset';
            }

            if ($roleChanging) {
                $pdo->prepare('DELETE FROM user_roles WHERE user_id = :uid')->execute([':uid' => $targetUserId]);
                $pdo->prepare('
                    INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_by, assigned_at)
                    VALUES (:id, :uid, :rid, :perms, :by, :now)
                ')->execute([
                    ':id'    => uuid4(),
                    ':uid'   => $targetUserId,
                    ':rid'   => $roleRowId,
                    ':perms' => '[]',
                    ':by'    => $user['id'],
                    ':now'   => $now,
                ]);
                // A role change always detaches any operational profile (profiles are ADMIN-tier only).
                if ($hasProfileColumn && !empty($target['permission_profile_id']) && !$profileChange) {
                    $pdo->prepare('UPDATE users SET permission_profile_id = NULL, updated_at = :now WHERE id = :id')
                        ->execute([':now' => $now, ':id' => $targetUserId]);
                }
                $revokeReasons[] = 'role_changed';
            }

            if ($profileChange) {
                $pdo->prepare('UPDATE users SET permission_profile_id = :pid, updated_at = :now WHERE id = :id')
                    ->execute([':pid' => $newProfileId, ':now' => $now, ':id' => $targetUserId]);
                $revokeReasons[] = 'permission_profile_changed';
            }

            if ($cleanCustom !== null) {
                $pdo->prepare('UPDATE user_roles SET custom_permissions = :perms WHERE user_id = :uid')
                    ->execute([':perms' => jsonEncode($cleanCustom), ':uid' => $targetUserId]);
                $revokeReasons[] = 'permissions_changed';
            }

            $pdo->commit();
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txe;
        }

        if ($roleChanging) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'ROLE_CHANGED',
                'resourceType' => 'user',
                'resourceId'   => $targetUserId,
                'metadata'     => ['previousRoles' => $targetRoles, 'newRole' => $newRoleCode],
            ]);
        }
        if ($profileChange) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'PERMISSION_PROFILE_ASSIGNED',
                'resourceType' => 'user',
                'resourceId'   => $targetUserId,
                'metadata'     => ['permissionProfileId' => $newProfileId, 'previousProfileId' => $target['permission_profile_id'] ?? null],
            ]);
        }
        if ($cleanCustom !== null) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'PERMISSIONS_CHANGED',
                'resourceType' => 'user',
                'resourceId'   => $targetUserId,
                'metadata'     => ['customPermissions' => $cleanCustom],
            ]);
        }
        if ($resetMfa) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'MFA_ADMIN_RESET',
                'resourceType' => 'user',
                'resourceId'   => $targetUserId,
                'metadata'     => ['targetEmail' => $target['email']],
            ]);
        }

        $sessionsRevoked = 0;
        if ($revokeReasons) {
            // When a Superadmin edits their own account, keep their current session; every other session dies.
            $keep = $isSelf ? (string)($user['sessionId'] ?? '') : null;
            $sessionsRevoked = revokeAllUserSessions($targetUserId, $keep !== '' ? $keep : null, $pdo, (string)$user['id'], implode(',', array_unique($revokeReasons)));
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
                ':before_state' => jsonEncode([
                    'isActive'            => (bool)$target['is_active'],
                    'role'                => $currentRole,
                    'permissionProfileId' => $target['permission_profile_id'] ?? null,
                ]),
                ':after_state'  => jsonEncode([
                    'isActive'            => (bool)$nextActive,
                    'role'                => $effectiveRoleAfter,
                    'permissionProfileId' => $profileChange ? $newProfileId : ($target['permission_profile_id'] ?? null),
                    'customPermissions'   => $cleanCustom,
                    'passwordReset'       => $newPassword !== '',
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
                'roleChanged'              => $roleChanging,
                'permissionProfileChanged' => $profileChange,
                'customPermissionsUpdated' => $cleanCustom !== null,
                'passwordReset'            => $newPassword !== '',
                'mfaReset'                 => $resetMfa,
                'sessionsRevoked'          => $sessionsRevoked,
            ],
        ]);

        jsonResponse([
            'updated'         => true,
            'userId'          => $targetUserId,
            'sessionsRevoked' => $sessionsRevoked,
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/users.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process user management request.'], 500);
}
