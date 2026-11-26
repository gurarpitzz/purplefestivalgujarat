<?php
declare(strict_types=1);

/**
 * GET / POST /api/auth/mfa
 * Phase 2 Hardened RFC 6238 TOTP MFA Lifecycle (D-20):
 * - GET: Strictly READ-ONLY status query (NEVER creates or overwrites secrets!)
 * - POST (CSRF-protected & rate-limited):
 *     * action=setup: Initiates 160-bit TOTP enrollment (or replacement, requiring password + current TOTP/recovery code)
 *     * action=enable: Verifies pending TOTP code, encrypts secret with AES-256-GCM (mfa_secret=NULL),
 *       marks session mfa_verified=1, revokes other sessions, issues 8 single-use recovery codes (shown ONCE)
 *     * action=disable: Blocked for SUPERADMIN & ADMIN (mandatory MFA per D-20); permitted for RGST/PARTICIPANT
 *       with currentPassword + current TOTP/recovery verification
 *     * action=regenerate_recovery: Re-issues 8 single-use recovery codes after password + TOTP verification
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = getDb();
    $user = getCurrentUser($pdo);
    if (!$user) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }

    $userId = (string)$user['id'];
    $mfaRequiredForRole = isMfaRequiredForRoles($user['roles'] ?? []);

    // Strictly READ-ONLY GET endpoint — zero side effects or secret generation
    if ($method === 'GET') {
        $remainingCodes = !empty($user['mfaEnabled']) ? countRemainingRecoveryCodes($pdo, $userId) : 0;
        jsonResponse([
            'mfaEnabled'             => (bool)$user['mfaEnabled'],
            'mfaVerified'            => (bool)$user['mfaVerified'],
            'mfaRequiredForRole'     => $mfaRequiredForRole,
            'mfaEnrollmentRequired'  => $mfaRequiredForRole && empty($user['mfaEnabled']),
            'remainingRecoveryCodes' => $remainingCodes,
            'csrfToken'              => getCsrfToken(),
        ], 200);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed.'], 405);
    }

    requireCsrfToken($user);
    pfgRequireRateLimit($pdo, 'auth_mfa_ops', $userId, 15, 900, 900, $userId);

    $input = readRequestInput();
    $action = strtolower(trim((string)($input['action'] ?? 'enable'))) ?: 'enable';

    // Load authoritative user row
    $uStmt = $pdo->prepare('SELECT id, email, password_hash, mfa_enabled, mfa_secret_encrypted, mfa_secret FROM users WHERE id = :id LIMIT 1');
    $uStmt->execute([':id' => $userId]);
    $dbUser = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$dbUser) {
        jsonResponse(['error' => 'Account not found.'], 404);
    }

    $storedActive = !empty($dbUser['mfa_secret_encrypted'])
        ? (string)$dbUser['mfa_secret_encrypted']
        : (!empty($dbUser['mfa_secret']) ? (string)$dbUser['mfa_secret'] : '');
    $activeSecretPlain = decryptMfaSecret($storedActive);
    $currentlyEnabled = !empty($dbUser['mfa_enabled']) && $activeSecretPlain !== '';

    // Helper: verify current password + current TOTP (or recovery code) for sensitive MFA modifications
    $verifyCurrentReauth = static function (array $payload) use ($pdo, $dbUser, $userId, $activeSecretPlain): void {
        $currentPassword = (string)($payload['currentPassword'] ?? $payload['password'] ?? '');
        $currentCode = preg_replace('/\s+/', '', (string)($payload['currentCode'] ?? $payload['currentTotp'] ?? $payload['code'] ?? '')) ?? '';
        $recoveryCode = trim((string)($payload['recoveryCode'] ?? ''));

        if ($currentPassword === '' || !verifyPassword($currentPassword, (string)($dbUser['password_hash'] ?? ''))) {
            logAudit([
                'actorUserId'  => $userId,
                'action'       => 'MFA_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $userId,
                'metadata'     => ['reason' => 'invalid_reauth_password'],
            ]);
            jsonResponse(['error' => 'Current password verification is required for this MFA action.'], 403);
        }

        if ($recoveryCode !== '') {
            if (!consumeMfaRecoveryCode($pdo, $userId, $recoveryCode)) {
                logAudit([
                    'actorUserId'  => $userId,
                    'action'       => 'MFA_FAILED',
                    'resourceType' => 'user',
                    'resourceId'   => $userId,
                    'metadata'     => ['reason' => 'invalid_reauth_recovery'],
                ]);
                jsonResponse(['error' => 'Invalid or already used recovery code.'], 403);
            }
            logAudit([
                'actorUserId'  => $userId,
                'action'       => 'MFA_RECOVERY_USED',
                'resourceType' => 'user',
                'resourceId'   => $userId,
                'metadata'     => ['context' => 'mfa_management'],
            ]);
            return;
        }

        $slice = verifyMfaTokenWithSlice($activeSecretPlain, $currentCode, 1);
        if ($slice === null || !consumeTotpSliceIfFresh($pdo, $userId, $slice)) {
            logAudit([
                'actorUserId'  => $userId,
                'action'       => 'MFA_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $userId,
                'metadata'     => ['reason' => 'invalid_reauth_totp'],
            ]);
            jsonResponse(['error' => 'Valid current 6-digit authenticator code (or recovery code) is required.'], 403);
        }
    };

    // -------------------------------------------------------------------------
    // ACTION: setup — Generate pending encrypted 160-bit TOTP secret
    // -------------------------------------------------------------------------
    if ($action === 'setup' || $action === 'init' || $action === 'replace') {
        if ($currentlyEnabled) {
            // Replacing an already-active MFA secret requires full re-authentication (never silently overwritten!)
            $verifyCurrentReauth($input);
        }

        $mfa = generateMfaSecret((string)$dbUser['email']);
        $secret = (string)$mfa['secret'];
        $otpauthUrl = (string)$mfa['otpauthUrl'];
        $encryptedPending = encryptMfaSecret($secret);

        $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider = 'mfa_setup'")
            ->execute([':uid' => $userId]);

        $pdo->prepare("
            INSERT INTO auth_accounts
            (id, user_id, provider, provider_account_id, token_hash, expires_at, created_at)
            VALUES (:id, :uid, 'mfa_setup', :mode, :enc_secret, :expires_at, :now)
        ")->execute([
            ':id'         => uuid4(),
            ':uid'        => $userId,
            ':mode'       => $currentlyEnabled ? 'replace' : 'enroll',
            ':enc_secret' => $encryptedPending,
            ':expires_at' => nowSql(time() + 15 * 60),
            ':now'        => nowSql(),
        ]);

        logAudit([
            'actorUserId'  => $userId,
            'action'       => $currentlyEnabled ? 'MFA_REPLACEMENT_INITIATED' : 'MFA_SETUP_INITIATED',
            'resourceType' => 'user',
            'resourceId'   => $userId,
        ]);

        jsonResponse([
            'setupPending' => true,
            'mode'         => $currentlyEnabled ? 'replace' : 'enroll',
            'mfaEnabled'   => $currentlyEnabled,
            'secret'       => $secret,
            'otpauthUrl'   => $otpauthUrl,
            'expiresInSec' => 900,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // ACTION: enable — Verify pending 6-digit TOTP code & activate MFA
    // -------------------------------------------------------------------------
    if ($action === 'enable' || $action === 'verify') {
        $cleanedCode = preg_replace('/\s+/', '', (string)($input['code'] ?? '')) ?? '';
        if (!preg_match('/^\d{6}$/', $cleanedCode)) {
            jsonResponse(['error' => 'Please enter a valid 6-digit authenticator code.'], 400);
        }

        $pStmt = $pdo->prepare("
            SELECT *
            FROM auth_accounts
            WHERE user_id = :uid
              AND provider = 'mfa_setup'
              AND expires_at > :now
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $pStmt->execute([':uid' => $userId, ':now' => nowSql()]);
        $pending = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pending || empty($pending['token_hash'])) {
            jsonResponse(['error' => 'MFA setup session expired or not initiated. Please start MFA setup again.'], 400);
        }

        $pendingSecretPlain = decryptMfaSecret((string)$pending['token_hash']);
        $matchedSlice = verifyMfaTokenWithSlice($pendingSecretPlain, $cleanedCode, 1);
        if ($matchedSlice === null) {
            logAudit([
                'actorUserId'  => $userId,
                'action'       => 'MFA_FAILED',
                'resourceType' => 'user',
                'resourceId'   => $userId,
                'metadata'     => ['reason' => 'enrollment_code_mismatch'],
            ]);
            jsonResponse(['error' => 'Invalid verification code. Please check your authenticator app.'], 400);
        }

        $now = nowSql();
        $encryptedActive = encryptMfaSecret($pendingSecretPlain);

        // Store ONLY authenticated ciphertext in mfa_secret_encrypted; NEVER store plaintext in mfa_secret
        $pdo->prepare("
            UPDATE users
            SET mfa_enabled = 1,
                mfa_secret_encrypted = :enc_secret,
                mfa_secret = NULL,
                updated_at = :now
            WHERE id = :id
        ")->execute([
            ':enc_secret' => $encryptedActive,
            ':now'        => $now,
            ':id'         => $userId,
        ]);

        // Record initial TOTP slice to prevent immediate replay
        $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider = 'mfa_replay'")
            ->execute([':uid' => $userId]);
        consumeTotpSliceIfFresh($pdo, $userId, $matchedSlice);

        // Upgrade current session to mfa_verified = 1 and revoke any other sessions
        if (!empty($user['sessionId'])) {
            $pdo->prepare('UPDATE sessions SET mfa_verified = 1 WHERE id = :sid')
                ->execute([':sid' => $user['sessionId']]);
            revokeAllUserSessions($userId, (string)$user['sessionId'], $pdo, $userId, 'mfa_activated');
        }

        // Consume pending setup row
        $pdo->prepare('DELETE FROM auth_accounts WHERE id = :id')
            ->execute([':id' => $pending['id']]);

        // Generate 8 single-use recovery codes (plaintext returned ONCE; only hashes stored)
        $recoveryCodes = generateMfaRecoveryCodes($pdo, $userId, 8);

        $isReplacement = ($pending['provider_account_id'] ?? '') === 'replace' || $currentlyEnabled;
        logAudit([
            'actorUserId'  => $userId,
            'action'       => $isReplacement ? 'MFA_REPLACED' : 'MFA_ENROLLED',
            'resourceType' => 'user',
            'resourceId'   => $userId,
            'metadata'     => ['recoveryCodesIssued' => count($recoveryCodes)],
        ]);

        jsonResponse([
            'success'                => true,
            'mfaEnabled'             => true,
            'mfaVerified'            => true,
            'mfaEnrollmentRequired'  => false,
            'recoveryCodes'          => $recoveryCodes,
            'remainingRecoveryCodes' => count($recoveryCodes),
            'message'                => $isReplacement
                ? 'Authenticator replaced successfully. Save your new single-use recovery codes immediately.'
                : 'Multi-factor authentication is now active. Save your single-use recovery codes in a secure place.',
        ], 200);
    }

    // -------------------------------------------------------------------------
    // ACTION: disable — Blocked for SUPERADMIN & ADMIN (D-20); re-auth required for optional roles
    // -------------------------------------------------------------------------
    if ($action === 'disable') {
        if ($mfaRequiredForRole) {
            logAudit([
                'actorUserId'  => $userId,
                'action'       => 'ACCESS_DENIED',
                'resourceType' => 'security',
                'resourceId'   => $userId,
                'metadata'     => ['reason' => 'mandatory_mfa_disable_blocked', 'roles' => $user['roles']],
            ]);
            jsonResponse([
                'error' => 'Multi-factor authentication is mandatory for SUPERADMIN and ADMIN accounts and cannot be disabled. Use authenticator replacement or Superadmin-assisted recovery instead.',
            ], 403);
        }

        if (!$currentlyEnabled) {
            jsonResponse(['error' => 'MFA is not currently enabled.'], 400);
        }

        $verifyCurrentReauth($input);

        $pdo->prepare("
            UPDATE users
            SET mfa_enabled = 0,
                mfa_secret_encrypted = NULL,
                mfa_secret = NULL,
                updated_at = :now
            WHERE id = :id
        ")->execute([':now' => nowSql(), ':id' => $userId]);

        $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider IN ('mfa_setup', 'mfa_recovery', 'mfa_replay')")
            ->execute([':uid' => $userId]);

        logAudit([
            'actorUserId'  => $userId,
            'action'       => 'MFA_DISABLED',
            'resourceType' => 'user',
            'resourceId'   => $userId,
        ]);

        jsonResponse(['success' => true, 'mfaEnabled' => false], 200);
    }

    // -------------------------------------------------------------------------
    // ACTION: regenerate_recovery — Issue fresh single-use recovery codes
    // -------------------------------------------------------------------------
    if ($action === 'regenerate_recovery') {
        if (!$currentlyEnabled) {
            jsonResponse(['error' => 'MFA must be enabled before generating recovery codes.'], 400);
        }
        $verifyCurrentReauth($input);
        $codes = generateMfaRecoveryCodes($pdo, $userId, 8);
        logAudit([
            'actorUserId'  => $userId,
            'action'       => 'MFA_RECOVERY_REGENERATED',
            'resourceType' => 'user',
            'resourceId'   => $userId,
            'metadata'     => ['recoveryCodesIssued' => count($codes)],
        ]);
        jsonResponse([
            'success'                => true,
            'recoveryCodes'          => $codes,
            'remainingRecoveryCodes' => count($codes),
        ], 200);
    }

    jsonResponse(['error' => 'Unsupported MFA action.'], 400);
} catch (Throwable $e) {
    error_log('/api/auth/mfa error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process MFA request.'], 500);
}
