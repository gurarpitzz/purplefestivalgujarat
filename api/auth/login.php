<?php
declare(strict_types=1);

/**
 * POST /api/auth/login
 * Phase 2 Hardened Staff Authentication:
 * - Persistent DB-backed rate limiting (IP + identifier)
 * - Constant-time dummy bcrypt check & uniform errors (zero account/lockout/role enumeration)
 * - Expired lockout reset (fixes sticky lockout)
 * - Mandatory TOTP MFA enforcement for SUPERADMIN & ADMIN (D-20)
 * - TOTP replay prevention + single-use recovery code verification
 * - Session fixation protection & session-bound CSRF token issuance
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
    $mfaCode = isset($input['mfaCode'])
        ? (string)$input['mfaCode']
        : (isset($input['mfa_code']) ? (string)$input['mfa_code'] : '');
    $recoveryCode = isset($input['recoveryCode'])
        ? (string)$input['recoveryCode']
        : (isset($input['recovery_code']) ? (string)$input['recovery_code'] : '');

    if ($identifier === '' || $password === '') {
        jsonResponse(['error' => 'Username and password are required.'], 400);
    }

    // Persistent Bluehost-compatible rate limits (per IP and per identifier)
    $clientIp = getClientIp() ?: 'unknown';
    pfgRequireRateLimit($pdo, 'auth_login_ip', $clientIp, 30, 900, 900);
    pfgRequireRateLimit($pdo, 'auth_login_ident', $identifier, 12, 900, 900);

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
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['is_active']) || empty($user['password_hash'])) {
        pfgDummyPasswordVerify($password);
        logAudit([
            'actorUserId'  => null,
            'action'       => 'LOGIN_FAILED',
            'resourceType' => 'user',
            'resourceId'   => null,
            'metadata'     => ['reason' => 'invalid_credentials'],
        ]);
        jsonResponse(['error' => 'Invalid username or password.'], 401);
    }

    $nowTs = time();
    $nowSqlStr = nowSql($nowTs);
    $lockedUntilTs = !empty($user['locked_until']) ? strtotime((string)$user['locked_until']) : false;
    $isCurrentlyLocked = ($lockedUntilTs !== false && $lockedUntilTs > $nowTs);
    $lockExpired = ($lockedUntilTs !== false && $lockedUntilTs <= $nowTs);

    // Always verify password BEFORE revealing lock status or role restrictions (prevents enumeration)
    $passwordValid = verifyPassword($password, (string)$user['password_hash']);

    if (!$passwordValid) {
        if ($isCurrentlyLocked) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'LOGIN_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
                'metadata'     => ['reason' => 'locked_invalid_password'],
            ]);
            jsonResponse(['error' => 'Invalid username or password.'], 401);
        }

        // Reset counter cleanly if previous lockout window has already expired (fixes sticky lockout)
        $baseAttempts = $lockExpired ? 0 : (int)($user['failed_login_attempts'] ?? 0);
        $nextAttempts = $baseAttempts + 1;
        $shouldLock = $nextAttempts >= MAX_FAILED_ATTEMPTS;
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

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => $shouldLock ? 'LOGIN_ACCOUNT_LOCKED' : 'LOGIN_FAILED',
            'resourceType' => 'user',
            'resourceId'   => $user['id'],
            'metadata'     => ['failedAttempts' => $nextAttempts],
        ]);

        // Uniform 401 error prevents unauthenticated callers from enumerating valid usernames
        jsonResponse(['error' => 'Invalid username or password.'], 401);
    }

    // Password is valid — now enforce active lockout without disclosing exact timestamp
    if ($isCurrentlyLocked) {
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'LOGIN_LOCKED_BLOCKED',
            'resourceType' => 'user',
            'resourceId'   => $user['id'],
        ]);
        jsonResponse([
            'error' => 'Account temporarily locked due to repeated failed sign-in attempts. Please try again later.',
        ], 423);
    }

    $rp = getUserRolesAndPermissions((string)$user['id'], $pdo);
    $roles = $rp['roles'];
    $permissions = $rp['permissions'];

    $isStaff = in_array('SUPERADMIN', $roles, true)
        || in_array('ADMIN', $roles, true)
        || in_array('RGST', $roles, true);

    if (!$isStaff) {
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'auth',
            'resourceId'   => $user['id'],
            'metadata'     => ['reason' => 'non_staff_login_blocked'],
        ]);
        jsonResponse([
            'error' => 'This portal is restricted to authorized festival staff (Superadmin, Admin, and Registration Desk).',
        ], 403);
    }

    // Resolve and decrypt active MFA secret (auto-upgrading legacy plaintext storage if present)
    $rawStoredSecret = !empty($user['mfa_secret_encrypted'])
        ? (string)$user['mfa_secret_encrypted']
        : (!empty($user['mfa_secret']) ? (string)$user['mfa_secret'] : '');
    $mfaSecretPlain = decryptMfaSecret($rawStoredSecret);
    $mfaEnabled = !empty($user['mfa_enabled']) && $mfaSecretPlain !== '';
    $mfaVerified = false;
    $usedRecoveryCode = false;

    if ($mfaEnabled) {
        // Transparently migrate any legacy plaintext MFA secret into authenticated AES-256-GCM storage
        if (!empty($user['mfa_secret']) || !str_starts_with((string)($user['mfa_secret_encrypted'] ?? ''), 'enc:v1:')) {
            try {
                $pdo->prepare('UPDATE users SET mfa_secret_encrypted = :enc, mfa_secret = NULL, updated_at = :now WHERE id = :id')
                    ->execute([
                        ':enc' => encryptMfaSecret($mfaSecretPlain),
                        ':now' => $nowSqlStr,
                        ':id'  => $user['id'],
                    ]);
            } catch (Throwable $e) {
            }
        }

        $cleanedTotp = preg_replace('/\s+/', '', $mfaCode) ?? '';
        $cleanedRecovery = trim($recoveryCode);

        // Allow passing an 8-char recovery code via mfaCode field as well (e.g. XXXX-XXXX)
        if ($cleanedRecovery === '' && $cleanedTotp !== '' && !preg_match('/^\d{6}$/', $cleanedTotp)) {
            $cleanedRecovery = $cleanedTotp;
            $cleanedTotp = '';
        }

        if ($cleanedTotp === '' && $cleanedRecovery === '') {
            jsonResponse([
                'mfaRequired' => true,
                'message'     => 'Multi-factor authentication is enabled. Please enter your 6-digit authenticator code or an 8-character recovery code.',
            ], 200);
        }

        // Throttle MFA verification attempts per user
        pfgRequireRateLimit($pdo, 'auth_mfa_verify', (string)$user['id'], 5, 900, 900, (string)$user['id']);

        if ($cleanedTotp !== '') {
            $matchedSlice = verifyMfaTokenWithSlice($mfaSecretPlain, $cleanedTotp, 1);
            if ($matchedSlice === null || !consumeTotpSliceIfFresh($pdo, (string)$user['id'], $matchedSlice)) {
                logAudit([
                    'actorUserId'  => $user['id'],
                    'action'       => 'MFA_FAILED',
                    'resourceType' => 'user',
                    'resourceId'   => $user['id'],
                    'metadata'     => ['reason' => $matchedSlice === null ? 'invalid_totp' : 'totp_replay_blocked'],
                ]);
                jsonResponse([
                    'error'       => 'Invalid or already used authenticator code. Please try again.',
                    'mfaRequired' => true,
                ], 401);
            }
            $mfaVerified = true;
        } else {
            if (!consumeMfaRecoveryCode($pdo, (string)$user['id'], $cleanedRecovery)) {
                logAudit([
                    'actorUserId'  => $user['id'],
                    'action'       => 'MFA_FAILED',
                    'resourceType' => 'user',
                    'resourceId'   => $user['id'],
                    'metadata'     => ['reason' => 'invalid_recovery_code'],
                ]);
                jsonResponse([
                    'error'       => 'Invalid or already used recovery code.',
                    'mfaRequired' => true,
                ], 401);
            }
            $mfaVerified = true;
            $usedRecoveryCode = true;
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'MFA_RECOVERY_USED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
                'metadata'     => ['remainingRecoveryCodes' => countRemainingRecoveryCodes($pdo, (string)$user['id'])],
            ]);
        }

        pfgResetRateLimit($pdo, 'auth_mfa_verify', (string)$user['id']);
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'MFA_VERIFIED',
            'resourceType' => 'user',
            'resourceId'   => $user['id'],
            'metadata'     => ['method' => $usedRecoveryCode ? 'recovery_code' : 'totp'],
        ]);
    }

    // Clear failed attempts and login rate limits on successful authentication
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
    pfgResetRateLimit($pdo, 'auth_login_ident', $identifier);

    $mfaRequiredForRole = isMfaRequiredForRoles($roles);
    $mfaEnrollmentRequired = $mfaRequiredForRole && !$mfaEnabled;

    $session = createSession((string)$user['id'], [
        'mfaVerified' => $mfaVerified,
    ], $pdo);

    $redirectTo = $mfaEnrollmentRequired
        ? '/admin?mfa_setup=1'
        : getPortalPathForRoles($roles);
    $displayUsername = !empty($user['username']) ? (string)$user['username'] : (string)$user['email'];

    logAudit([
        'actorUserId'  => $user['id'],
        'action'       => 'LOGIN_SUCCESS',
        'resourceType' => 'session',
        'resourceId'   => $session['sessionId'],
        'metadata'     => [
            'username'              => $displayUsername,
            'roles'                 => $roles,
            'mfaVerified'           => $mfaVerified,
            'mfaEnrollmentRequired' => $mfaEnrollmentRequired,
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
            'mfaEnabled'            => $mfaEnabled,
            'mfaVerified'           => $mfaVerified,
            'mfaRequiredForRole'    => $mfaRequiredForRole,
            'mfaEnrollmentRequired' => $mfaEnrollmentRequired,
        ],
        'mfaEnrollmentRequired' => $mfaEnrollmentRequired,
        'csrfToken'             => $session['csrfToken'],
        'redirectTo'            => $redirectTo,
    ], 200);
} catch (Throwable $e) {
    error_log('POST /api/auth/login error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to complete sign-in right now. Please try again.'], 500);
}
