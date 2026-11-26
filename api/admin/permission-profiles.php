<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Operational Permission Profiles API (Phase 8, CAD-04)
 * Endpoint: GET / POST / PATCH / DELETE /api/admin/permission-profiles
 *
 * - SUPERADMIN only (admin_accounts.manage) + mandatory verified MFA (D-20) + session-bound CSRF on writes.
 * - Profiles are reusable permission bundles that can be assigned ONLY to ADMIN accounts (enforced in users.php).
 * - A profile can never confer `admin_accounts.manage` / `settings.manage` (Superadmin-only authorities) and any
 *   unknown permission code is rejected with 400.
 * - Changing a profile's permissions / active flag revokes the sessions of every ADMIN currently assigned to it.
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

/**
 * Permission codes that may legitimately be placed in a profile.
 */
function pfgProfileAssignablePermissions(): array
{
    return array_values(array_diff(array_values(PERMISSIONS), pfgProfileForbiddenPermissions()));
}

/**
 * Validates a submitted permissions array. Calls jsonResponse(400) on unknown / forbidden / malformed codes.
 */
function pfgProfileValidatePermissions($raw): array
{
    if (!is_array($raw)) {
        jsonResponse(['error' => 'permissions must be an array of permission codes.'], 400);
    }
    $assignable = pfgProfileAssignablePermissions();
    $forbidden = pfgProfileForbiddenPermissions();
    $clean = [];
    $unknown = [];
    foreach ($raw as $code) {
        if (!is_string($code) || $code === '') {
            jsonResponse(['error' => 'Every permission code must be a non-empty string.'], 400);
        }
        if (in_array($code, $forbidden, true)) {
            jsonResponse([
                'error'     => "Permission {$code} is a Superadmin-only authority and cannot be granted through a profile.",
                'forbidden' => [$code],
            ], 400);
        }
        if (!in_array($code, $assignable, true)) {
            $unknown[] = $code;
            continue;
        }
        if (!in_array($code, $clean, true)) {
            $clean[] = $code;
        }
    }
    if ($unknown) {
        jsonResponse(['error' => 'Unknown permission code(s): ' . implode(', ', array_slice($unknown, 0, 10)), 'unknown' => $unknown], 400);
    }
    $own = PERMISSIONS['ACCOUNT_READ_OWN'];
    if (!in_array($own, $clean, true)) {
        array_unshift($clean, $own);
    }
    return $clean;
}

function pfgProfileFormat(array $row, int $assignedCount): array
{
    $perms = jsonDecode($row['permissions_json'] ?? '[]', []);
    return [
        'id'            => (string)$row['id'],
        'slug'          => (string)$row['slug'],
        'name'          => (string)$row['name'],
        'description'   => $row['description'] !== null ? (string)$row['description'] : '',
        'permissions'   => is_array($perms) ? array_values(array_filter($perms, 'is_string')) : [],
        'isSystem'      => !empty($row['is_system']),
        'isActive'      => !empty($row['is_active']),
        'assignedCount' => $assignedCount,
        'createdAt'     => (string)($row['created_at'] ?? ''),
        'updatedAt'     => (string)($row['updated_at'] ?? ''),
    ];
}

function pfgProfileAssignedCount(PDO $pdo, string $profileId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE permission_profile_id = :pid AND deleted_at IS NULL');
    $st->execute([':pid' => $profileId]);
    return (int)$st->fetchColumn();
}

function pfgProfileRevokeAssigned(PDO $pdo, string $profileId, string $actorId, string $reason): int
{
    $st = $pdo->prepare('SELECT id FROM users WHERE permission_profile_id = :pid');
    $st->execute([':pid' => $profileId]);
    $total = 0;
    foreach (($st->fetchAll(PDO::FETCH_COLUMN) ?: []) as $uid) {
        $total += revokeAllUserSessions((string)$uid, null, $pdo, $actorId, $reason);
    }
    return $total;
}

function pfgProfileSlugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = (string)preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim($slug, '_');
    return substr($slug, 0, 64);
}

function pfgProfileResolveId(array $input): string
{
    $id = $_GET['id'] ?? $input['id'] ?? $input['profileId'] ?? '';
    return is_string($id) ? trim($id) : '';
}

try {
    $user = requireRole(['SUPERADMIN']);
    requirePermission(PERMISSIONS['ADMIN_ACCOUNTS_MANAGE'], $user);
    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        requireCsrfToken($user);
    }
    $pdo = getDb();

    if (!pfgAdminTableExists($pdo, 'admin_permission_profiles')) {
        jsonResponse(['error' => 'Permission profiles are unavailable until migration 009 has been applied.'], 503);
    }

    // =========================================================================
    // GET: list profiles with assignment counts
    // =========================================================================
    if ($method === 'GET') {
        $counts = [];
        $stCnt = $pdo->query('SELECT permission_profile_id, COUNT(*) AS c FROM users WHERE permission_profile_id IS NOT NULL AND deleted_at IS NULL GROUP BY permission_profile_id');
        foreach (($stCnt ? $stCnt->fetchAll(PDO::FETCH_ASSOC) : []) as $c) {
            $counts[(string)$c['permission_profile_id']] = (int)$c['c'];
        }
        $rows = $pdo->query('SELECT * FROM admin_permission_profiles ORDER BY is_system DESC, name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = [];
        foreach ($rows as $r) {
            $items[] = pfgProfileFormat($r, $counts[(string)$r['id']] ?? 0);
        }
        jsonResponse([
            'items'                 => $items,
            'availablePermissions'  => pfgProfileAssignablePermissions(),
            'forbiddenPermissions'  => pfgProfileForbiddenPermissions(),
        ]);
    }

    // =========================================================================
    // POST: create profile
    // =========================================================================
    if ($method === 'POST') {
        $input = readRequestInput();
        $name = pfgAdminCleanText($input['name'] ?? '', 120);
        if ($name === null || strlen($name) < 2) {
            jsonResponse(['error' => 'Profile name is required (min 2 characters).'], 400);
        }
        $description = pfgAdminCleanText($input['description'] ?? null, 1000);
        $permissions = pfgProfileValidatePermissions($input['permissions'] ?? []);

        $slugInput = isset($input['slug']) && is_string($input['slug']) && trim($input['slug']) !== '' ? $input['slug'] : $name;
        $slug = pfgProfileSlugify($slugInput);
        if (strlen($slug) < 2) {
            jsonResponse(['error' => 'A valid slug could not be derived from the profile name.'], 400);
        }
        $chk = $pdo->prepare('SELECT id FROM admin_permission_profiles WHERE slug = :s OR LOWER(name) = :n LIMIT 1');
        $chk->execute([':s' => $slug, ':n' => strtolower($name)]);
        if ($chk->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['error' => 'A profile with this name or slug already exists.'], 409);
        }

        $id = uuid4();
        $now = nowSql();
        $ins = $pdo->prepare('
            INSERT INTO admin_permission_profiles (id, slug, name, description, permissions_json, is_system, is_active, created_by_user_id, created_at, updated_at)
            VALUES (:id, :slug, :name, :desc, :perms, 0, 1, :by, :now, :now)
        ');
        $ins->execute([
            ':id'    => $id,
            ':slug'  => $slug,
            ':name'  => $name,
            ':desc'  => $description,
            ':perms' => jsonEncode($permissions),
            ':by'    => $user['id'],
            ':now'   => $now,
        ]);

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'PERMISSION_PROFILE_CREATED',
            'resourceType' => 'admin_permission_profile',
            'resourceId'   => $id,
            'metadata'     => ['slug' => $slug, 'name' => $name, 'permissions' => $permissions],
        ]);

        $row = $pdo->prepare('SELECT * FROM admin_permission_profiles WHERE id = :id');
        $row->execute([':id' => $id]);
        jsonResponse(['profile' => pfgProfileFormat($row->fetch(PDO::FETCH_ASSOC), 0)], 201);
    }

    // =========================================================================
    // PATCH: edit profile (name / description / permissions / active flag)
    // =========================================================================
    if ($method === 'PATCH' || $method === 'PUT') {
        $input = readRequestInput();
        $id = pfgProfileResolveId($input);
        if ($id === '') {
            jsonResponse(['error' => 'Profile id is required.'], 400);
        }
        $st = $pdo->prepare('SELECT * FROM admin_permission_profiles WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            jsonResponse(['error' => 'Permission profile not found.'], 404);
        }

        $name = (string)$existing['name'];
        if (array_key_exists('name', $input)) {
            $n = pfgAdminCleanText($input['name'], 120);
            if ($n === null || strlen($n) < 2) {
                jsonResponse(['error' => 'Profile name is required (min 2 characters).'], 400);
            }
            if (strtolower($n) !== strtolower($name)) {
                $dup = $pdo->prepare('SELECT id FROM admin_permission_profiles WHERE LOWER(name) = :n AND id <> :id LIMIT 1');
                $dup->execute([':n' => strtolower($n), ':id' => $id]);
                if ($dup->fetch(PDO::FETCH_ASSOC)) {
                    jsonResponse(['error' => 'A profile with this name already exists.'], 409);
                }
            }
            $name = $n;
        }
        $description = array_key_exists('description', $input)
            ? pfgAdminCleanText($input['description'], 1000)
            : ($existing['description'] !== null ? (string)$existing['description'] : null);

        $oldPerms = jsonDecode($existing['permissions_json'] ?? '[]', []);
        $oldPerms = is_array($oldPerms) ? array_values($oldPerms) : [];
        $newPerms = array_key_exists('permissions', $input)
            ? pfgProfileValidatePermissions($input['permissions'])
            : $oldPerms;
        $isActive = array_key_exists('isActive', $input) ? (!empty($input['isActive']) ? 1 : 0) : (int)$existing['is_active'];

        $permsChanged = array_key_exists('permissions', $input)
            && (array_values(array_diff($oldPerms, $newPerms)) !== [] || array_values(array_diff($newPerms, $oldPerms)) !== []);
        $activeChanged = $isActive !== (int)$existing['is_active'];

        $up = $pdo->prepare('
            UPDATE admin_permission_profiles
            SET name = :name, description = :desc, permissions_json = :perms, is_active = :active, updated_at = :now
            WHERE id = :id
        ');
        $up->execute([
            ':name'   => $name,
            ':desc'   => $description,
            ':perms'  => jsonEncode($newPerms),
            ':active' => $isActive,
            ':now'    => nowSql(),
            ':id'     => $id,
        ]);

        $revoked = 0;
        if ($permsChanged || $activeChanged) {
            $revoked = pfgProfileRevokeAssigned($pdo, $id, (string)$user['id'], 'permission_profile_changed');
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'PERMISSION_PROFILE_UPDATED',
            'resourceType' => 'admin_permission_profile',
            'resourceId'   => $id,
            'metadata'     => [
                'slug'            => $existing['slug'],
                'permissionsFrom' => $oldPerms,
                'permissionsTo'   => $newPerms,
                'isActive'        => (bool)$isActive,
                'sessionsRevoked' => $revoked,
            ],
        ]);

        $st->execute([':id' => $id]);
        jsonResponse([
            'profile'         => pfgProfileFormat($st->fetch(PDO::FETCH_ASSOC), pfgProfileAssignedCount($pdo, $id)),
            'sessionsRevoked' => $revoked,
        ]);
    }

    // =========================================================================
    // DELETE: remove unused, non-system profile
    // =========================================================================
    if ($method === 'DELETE') {
        $input = readRequestInput();
        $id = pfgProfileResolveId($input);
        if ($id === '') {
            jsonResponse(['error' => 'Profile id is required.'], 400);
        }
        $st = $pdo->prepare('SELECT * FROM admin_permission_profiles WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            jsonResponse(['error' => 'Permission profile not found.'], 404);
        }
        if (!empty($existing['is_system'])) {
            jsonResponse(['error' => 'System profiles cannot be deleted. Deactivate the profile instead.'], 409);
        }
        $assigned = pfgProfileAssignedCount($pdo, $id);
        if ($assigned > 0) {
            jsonResponse(['error' => "This profile is assigned to {$assigned} account(s). Reassign them before deleting.", 'assignedCount' => $assigned], 409);
        }
        $pdo->prepare('DELETE FROM admin_permission_profiles WHERE id = :id')->execute([':id' => $id]);
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'PERMISSION_PROFILE_DELETED',
            'resourceType' => 'admin_permission_profile',
            'resourceId'   => $id,
            'metadata'     => ['slug' => $existing['slug'], 'name' => $existing['name']],
        ]);
        jsonResponse(['deleted' => true, 'id' => $id]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/permission-profiles.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process permission profile request.'], 500);
}
