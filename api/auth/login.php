<?php
declare(strict_types=1);

/**
 * POST /api/auth/login
 * Staff-only authentication with username (SUPERADMIN, ADMIN, RGST), password, and optional TOTP MFA code.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_DURATION_SECONDS = 15 * 60; // 15 minutes

try {
    $pdo = getDb();
    $input = readRequestInput();

    $identifier = strtolower(trim((string)($input['username'] ?? $input['identifier'] ?? $input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $mfaCode = isset($input['mfaCode']) ? (string)$input['mfaCode'] : (isset($input['mfa_code']) ? (string)$input['mfa_code'] : '');

    if ($identifier === '') {
        jsonResponse(['error' => 'Please enter your username.'], 400);
    }
    if ($password === '') {
        jsonResponse(['error' => 'Password is required.'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE (LOWER(username) = :ident_user OR LOWER(email) = :ident_email)
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        ':ident_user'  => $identifier,
        ':ident_email' => $identifier,
    ]);
    $user = $stmt->fetch();

    if (!$user || empty($user['is_active']) || empty($user['password_hash'])) {
        jsonResponse(['error' => 'Invalid username or password.'], 401);
    }

    $nowTs = time();
    if (!empty($user['locked_until'])) {
        $lockedUntilTs = strtotime((string)$user['locked_until']);
        if ($lockedUntilTs !== false && $lockedUntilTs > $nowTs) {
            $minutesRemaining = max(1, (int)ceil(($lockedUntilTs - $nowTs) / 60));
            $plural = $minutesRemaining === 1 ? '' : 's';
            jsonResponse([
                'error'       => "Account temporarily locked due to multiple failed sign-in attempts. Please try again in {$minutesRemaining} minute{$plural}.",
                'lockedUntil' => date('c', $lockedUntilTs),
            ], 423);
        }
    }

    $passwordValid = verifyPassword($password, (string)$user['password_hash']);
    if (!$passwordValid) {
        $nextAttempts = ((int)($user['failed_login_attempts'] ?? 0)) + 1;
        $shouldLock = $nextAttempts >= MAX_FAILED_ATTEMPTS;
        $lockedUntil = $shouldLock ? nowSql($nowTs + LOCKOUT_DURATION_SECONDS) : null;

        $upd = $pdo->prepare("
            UPDATE users
            SET failed_login_attempts = :attempts,
                locked_until = :locked_until,
                updated_at = :updated_at
            WHERE id = :id
        ");
        $upd->execute([
            ':attempts'     => $nextAttempts,
            ':locked_until' => $lockedUntil,
            ':updated_at'   => nowSql($nowTs),
            ':id'           => $user['id'],
        ]);

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => $shouldLock ? 'LOGIN_ACCOUNT_LOCKED' : 'LOGIN_FAILED',
            'resourceType' => 'user',
            'resourceId'   => $user['id'],
            'metadata'     => ['failedAttempts' => $nextAttempts, 'identifier' => $identifier],
        ]);

        if ($shouldLock) {
            jsonResponse([
                'error' => 'Too many failed sign-in attempts. Your account has been locked for 15 minutes for security.',
            ], 423);
        }

        jsonResponse(['error' => 'Invalid username or password.'], 401);
    }

    $rp = getUserRolesAndPermissions((string)$user['id'], $pdo);
    $roles = $rp['roles'];
    $permissions = $rp['permissions'];

    $isStaff = in_array('SUPERADMIN', $roles, true)
        || in_array('ADMIN', $roles, true)
        || in_array('RGST', $roles, true);

    if (!$isStaff) {
        jsonResponse([
            'error' => 'This portal is restricted to authorized festival staff (Superadmin, Admin, and Registration Desk).',
        ], 403);
    }

    // MFA TOTP check when enabled
    $mfaSecret = !empty($user['mfa_secret_encrypted'])
        ? (string)$user['mfa_secret_encrypted']
        : (!empty($user['mfa_secret']) ? (string)$user['mfa_secret'] : '');

    if (!empty($user['mfa_enabled']) && $mfaSecret !== '') {
        $cleanedCode = preg_replace('/\s+/', '', $mfaCode) ?? '';
        if ($cleanedCode === '') {
            jsonResponse([
                'mfaRequired' => true,
                'message'     => 'Multi-factor authentication is enabled. Please enter your 6-digit authenticator code.',
            ], 200);
        }

        if (!verifyMfaToken($mfaSecret, $cleanedCode)) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'LOGIN_MFA_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
            ]);
            jsonResponse([
                'error'       => 'Invalid authenticator code. Please try again.',
                'mfaRequired' => true,
            ], 401);
        }
    }

    $upd = $pdo->prepare("
        UPDATE users
        SET failed_login_attempts = 0,
            locked_until = NULL,
            last_login_at = :last_login_at,
            updated_at = :updated_at
        WHERE id = :id
    ");
    $nowSqlStr = nowSql($nowTs);
    $upd->execute([
        ':last_login_at' => $nowSqlStr,
        ':updated_at'    => $nowSqlStr,
        ':id'            => $user['id'],
    ]);

    createSession((string)$user['id'], [
        'mfaVerified' => !empty($user['mfa_enabled']),
    ], $pdo);

    $redirectTo = getPortalPathForRoles($roles);
    $displayUsername = !empty($user['username']) ? (string)$user['username'] : (string)$user['email'];

    logAudit([
        'actorUserId'  => $user['id'],
        'action'       => 'LOGIN_SUCCESS',
        'resourceType' => 'session',
        'resourceId'   => $user['id'],
        'metadata'     => [
            'username'    => $displayUsername,
            'roles'       => $roles,
            'mfaVerified' => !empty($user['mfa_enabled']),
        ],
    ]);

    jsonResponse([
        'user' => [
            'id'          => (string)$user['id'],
            'username'    => $displayUsername,
            'email'       => (string)$user['email'],
            'fullName'    => (string)$user['full_name'],
            'phone'       => $user['phone'] !== null ? (string)$user['phone'] : null,
            'country'     => (string)($user['country'] ?? 'India'),
            'roles'       => $roles,
            'permissions' => $permissions,
            'mfaEnabled'  => (bool)$user['mfa_enabled'],
        ],
        'redirectTo' => $redirectTo,
    ], 200);
} catch (Throwable $e) {
    error_log('POST /api/auth/login error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to complete sign-in right now. Please try again.'], 500);
}
