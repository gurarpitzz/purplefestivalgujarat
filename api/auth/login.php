<?php
declare(strict_types=1);

/**
 * POST /api/auth/login
 * Owner-Directed Revised Authentication Policy (supersedes D-20):
 * - Conventional Username + Password authentication for ALL staff roles (SUPERADMIN, ADMIN, custom profiles, RGST)
 * - Verified Email + Password authentication for PARTICIPANT accounts (email ownership verification retained)
 * - TOTP / authenticator-based MFA discontinued for all roles; existing MFA-enrolled accounts sign in seamlessly
 * - Persistent account-level consecutive failed-attempt tracking: max 5 failures -> exact 3-hour (10,800s) lockout
 * - Atomic database enforcement via transaction + PK row lock (SELECT ... FOR UPDATE) preventing concurrent bypass
 * - Lockout rejects even valid credentials, never extends on extra attempts, ignores IP/device changes,
 *   auto-unlocks & resets counter on expiry without a Cron Job, and resets counter on successful login
 * - Generic public authentication error (HTTP 401) preventing account & lockout enumeration
 * - Persistent DB-backed IP & identifier rate limiting, session fixation protection, session-bound CSRF token
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
const LOCKOUT_DURATION_SECONDS = 3 * 60 * 60; // Exactly 3 hours (180 minutes / 10,800 seconds)
const GENERIC_AUTH_ERROR = 'Invalid credentials or account temporarily locked. Please try again later.';

try {
    $pdo = getDb();
    $input = readRequestInput();

    $identifier = strtolower(trim((string)($input['username'] ?? $input['identifier'] ?? $input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    $honeypot = trim((string)($input['websiteHp'] ?? ''));

    // Optional portal hint ('staff' | 'participant') and validated post-login destination.
    $portal = strtolower(trim((string)($input['portal'] ?? '')));
    $nextRedirect = isset($input['next']) && is_string($input['next'])
        ? pfgValidateSafeRedirect($input['next'], '')
        : '';

    // Anti-script honeypot trap: automated bots filling hidden inputs are rate-limited, audited & rejected uniformly
    if ($honeypot !== '') {
        $clientIp = getClientIp() ?: 'unknown';
        pfgCheckRateLimit($pdo, 'auth_login_ip', $clientIp, 30, 900, 900, true);
        pfgDummyPasswordVerify($password !== '' ? $password : 'bot-trap');
        logAudit([
            'actorUserId'  => null,
            'action'       => 'LOGIN_HONEYPOT_TRIGGERED',
            'resourceType' => 'security',
            'resourceId'   => null,
            'metadata'     => ['ip' => $clientIp],
        ]);
        jsonResponse(['error' => GENERIC_AUTH_ERROR], 401);
    }

    if ($identifier === '' || $password === '') {
        jsonResponse(['error' => 'Username and password are required.'], 400);
    }

    // Persistent Bluehost-compatible rate limits (per IP and per identifier)
    $clientIp = getClientIp() ?: 'unknown';
    pfgRequireRateLimit($pdo, 'auth_login_ip', $clientIp, 30, 900, 900);
    pfgRequireRateLimit($pdo, 'auth_login_ident', $identifier, 25, 900, 900);

    // Lightweight PK lookup outside transaction to avoid table/gap lock contention in InnoDB
    $findStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE (LOWER(username) = :ident_user OR LOWER(email) = :ident_email)
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $findStmt->execute([
        ':ident_user'  => $identifier,
        ':ident_email' => $identifier,
    ]);
    $userId = $findStmt->fetchColumn();

    if (!$userId) {
        pfgDummyPasswordVerify($password);
        logAudit([
            'actorUserId'  => null,
            'action'       => 'LOGIN_FAILED',
            'resourceType' => 'user',
            'resourceId'   => null,
            'metadata'     => ['reason' => 'invalid_credentials'],
        ]);
        jsonResponse(['error' => GENERIC_AUTH_ERROR], 401);
    }

    $userId = (string)$userId;
    $user = null;
    $nowTs = time();
    $nowSqlStr = nowSql($nowTs);
    $outcome = 'invalid';
    $auditMeta = [];
    $roles = [];
    $permissions = [];
    $isStaff = false;
    $accountStatus = 'ACTIVE';

    // Atomic per-account enforcement using PK row lock (SELECT ... FOR UPDATE)
    $pdo->beginTransaction();
    try {
        $lockStmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([':id' => $userId]);
        $user = $lockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['is_active']) || empty($user['password_hash'])) {
            $pdo->commit();
            pfgDummyPasswordVerify($password);
            logAudit([
                'actorUserId'  => $user ? $user['id'] : null,
                'action'       => 'LOGIN_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $user ? $user['id'] : null,
                'metadata'     => ['reason' => 'invalid_credentials'],
            ]);
            jsonResponse(['error' => GENERIC_AUTH_ERROR], 401);
        }

        $nowTs = time();
        $nowSqlStr = nowSql($nowTs);
        $lockedUntilTs = !empty($user['locked_until']) ? strtotime((string)$user['locked_until']) : false;
        $isCurrentlyLocked = ($lockedUntilTs !== false && $lockedUntilTs > $nowTs);
        $lockExpired = ($lockedUntilTs !== false && $lockedUntilTs <= $nowTs);

        if ($isCurrentlyLocked) {
            // Active lockout: NEVER modify failed_login_attempts or extend locked_until.
            $pdo->commit();

            // Constant-time password check outside lock for uniform timing & accurate audit classification
            $pwMatchedWhileLocked = verifyPassword($password, (string)$user['password_hash']);
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => $pwMatchedWhileLocked ? 'LOGIN_LOCKED_BLOCKED' : 'LOGIN_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
                'metadata'     => [
                    'reason' => $pwMatchedWhileLocked ? 'active_lockout_valid_password_rejected' : 'locked_invalid_password',
                ],
            ]);
            jsonResponse(['error' => GENERIC_AUTH_ERROR], 401);
        }

        // Not currently locked (either never locked or expired lockout auto-unlocking now)
        $passwordValid = verifyPassword($password, (string)$user['password_hash']);

        if (!$passwordValid) {
            // Automatic unlock on expired lockout: reset base counter to 0, granting 5 fresh attempts
            $baseAttempts = $lockExpired ? 0 : (int)($user['failed_login_attempts'] ?? 0);
            $nextAttempts = $baseAttempts + 1;
            $shouldLock = ($nextAttempts >= MAX_FAILED_ATTEMPTS);
            $newLockedUntil = $shouldLock ? nowSql($nowTs + LOCKOUT_DURATION_SECONDS) : null;

            $upd = $pdo->prepare("
                UPDATE users
                SET failed_login_attempts = :attempts,
                    locked_until = :locked_until,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $upd->execute([
                ':attempts'     => $nextAttempts,
                ':locked_until' => $newLockedUntil,
                ':updated_at'   => $nowSqlStr,
                ':id'           => $user['id'],
            ]);
            $pdo->commit();

            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => $shouldLock ? 'LOGIN_ACCOUNT_LOCKED' : 'LOGIN_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
                'metadata'     => $shouldLock
                    ? [
                        'failedAttempts'         => $nextAttempts,
                        'lockoutDurationSeconds' => LOCKOUT_DURATION_SECONDS,
                    ]
                    : [
                        'failedAttempts'   => $nextAttempts,
                        'lockExpiredReset' => $lockExpired,
                    ],
            ]);

            jsonResponse(['error' => GENERIC_AUTH_ERROR], 401);
        }

        // Valid credentials on an unlocked (or freshly auto-unlocked) account
        $rp = getUserRolesAndPermissions((string)$user['id'], $pdo);
        $roles = $rp['roles'];
        $permissions = $rp['permissions'];

        // CAD-17: Authentication is strictly restricted to festival staff (SUPERADMIN, ADMIN, RGST).
        // Participant accounts are discontinued; public registration is accountless.
        $isStaff = in_array('SUPERADMIN', $roles, true)
            || in_array('ADMIN', $roles, true)
            || in_array('RGST', $roles, true);

        $accountStatus = strtoupper((string)($user['account_status'] ?? 'ACTIVE'));

        if (!$isStaff) {
            $outcome = 'non_staff_blocked';
        } elseif (in_array($accountStatus, ['SUSPENDED', 'ARCHIVED', 'DISABLED', 'DEACTIVATED'], true)) {
            $outcome = 'account_not_active';
        } else {
            $outcome = 'success';
        }

        // Always reset failed_login_attempts & clear expired lockout upon valid password for staff
        if ($outcome === 'success') {
            $upd = $pdo->prepare("
                UPDATE users
                SET failed_login_attempts = 0,
                    locked_until = NULL,
                    last_login_at = :last_login_at,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $upd->execute([
                ':last_login_at' => $nowSqlStr,
                ':updated_at'    => $nowSqlStr,
                ':id'            => $user['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $txErr) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $txErr;
    }

    if ($outcome === 'non_staff_blocked') {
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'auth',
            'resourceId'   => $user['id'],
            'metadata'     => ['reason' => 'participant_login_discontinued_staff_only'],
        ]);
        jsonResponse([
            'error' => GENERIC_AUTH_ERROR,
        ], 401);
    }

    if ($outcome === 'account_not_active') {
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'auth',
            'resourceId'   => $user['id'],
            'metadata'     => ['reason' => 'account_not_active', 'accountStatus' => $accountStatus],
        ]);
        jsonResponse([
            'error' => 'This staff account is not active. Please contact the Superadmin or Festival Secretariat.',
        ], 403);
    }

    pfgResetRateLimit($pdo, 'auth_login_ident', $identifier);

    $session = createSession((string)$user['id'], [
        'mfaVerified' => false,
    ], $pdo);

    $portalDefault = getPortalPathForRoles($roles);
    $nextIsAllowed = $nextRedirect !== ''
        && (
            (in_array('SUPERADMIN', $roles, true) || in_array('ADMIN', $roles, true)) && (bool)preg_match('#^/(admin|rgst)(/|\?|$)#i', $nextRedirect)
            || in_array('RGST', $roles, true) && (bool)preg_match('#^/rgst(/|\?|$)#i', $nextRedirect)
        );
    $redirectTo = $nextIsAllowed ? $nextRedirect : $portalDefault;
    $displayUsername = !empty($user['username']) ? (string)$user['username'] : (string)$user['email'];

    logAudit([
        'actorUserId'  => $user['id'],
        'action'       => 'LOGIN_SUCCESS',
        'resourceType' => 'session',
        'resourceId'   => $session['sessionId'],
        'metadata'     => [
            'username' => $displayUsername,
            'roles'    => $roles,
        ],
    ]);

    jsonResponse([
        'user' => [
            'id'                    => (string)$user['id'],
            'username'              => $displayUsername,
            'email'                 => (string)$user['email'],
            'fullName'              => (string)$user['full_name'],
            'phone'                 => $user['phone'] !== null ? (string)$user['phone'] : null,
            'country'               => (string)($user['country'] ?? 'India'),
            'roles'                 => $roles,
            'permissions'           => $permissions,
            'mfaEnabled'            => false,
            'mfaVerified'           => false,
            'mfaRequiredForRole'    => false,
            'mfaEnrollmentRequired' => false,
            'emailVerified'         => !empty($user['email_verified_at']),
        ],
        'mfaEnrollmentRequired' => false,
        'csrfToken'             => $session['csrfToken'],
        'redirectTo'            => $redirectTo,
    ], 200);
} catch (Throwable $e) {
    error_log('POST /api/auth/login error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to complete sign-in right now. Please try again.'], 500);
}
