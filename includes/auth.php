<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Authentication, RBAC, Session, CSRF, Rate Limiting & RFC 6238 TOTP MFA Layer
 * Phase 2 Hardened Security Core (Native PHP + PDO MySQL/MariaDB, Bluehost-compatible).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// ============================================================================
// 1. TOKEN, PASSWORD & HTTPS HELPERS
// ============================================================================

if (!function_exists('pfgIsHttpsRequest')) {
    /**
     * Determines whether session cookies should carry the Secure flag.
     * Does NOT blindly trust spoofable HTTP_X_FORWARDED_PROTO headers.
     */
    function pfgIsHttpsRequest(): bool
    {
        if (defined('APP_URL') && stripos((string)APP_URL, 'https://') === 0) {
            return true;
        }
        return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    }
}

if (!function_exists('hashToken')) {
    /**
     * Hashes an opaque session or reset token with HMAC-SHA256 using AUTH_SECRET.
     */
    function hashToken(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, AUTH_SECRET);
    }
}

if (!function_exists('hashPassword')) {
    /**
     * Hashes a plaintext password using bcrypt (cost 12).
     */
    function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

if (!function_exists('verifyPassword')) {
    /**
     * Verifies a plaintext password against a bcrypt hash ($2a$, $2b$, $2y$).
     */
    function verifyPassword(string $password, ?string $hash): bool
    {
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }
}

if (!function_exists('pfgDummyPasswordVerify')) {
    /**
     * Performs a constant-cost bcrypt check when a user record is not found to mitigate timing enumeration.
     */
    function pfgDummyPasswordVerify(string $password): void
    {
        static $dummyHash = '$2y$12$abcdefghijklmnopqrstuu5s5s5s5s5s5s5s5s5s5s5s5s5s5s5s.';
        @password_verify($password, $dummyHash);
    }
}

// ============================================================================
// 2. SESSION-BOUND CSRF PROTECTION (ZERO STATIC ANONYMOUS FALLBACK)
// ============================================================================

if (!function_exists('ensurePhpSessionStarted')) {
    function ensurePhpSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $isHttps = pfgIsHttpsRequest();
            @session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            @session_start();
        }
    }
}

if (!function_exists('pfgComputeSessionBoundCsrfToken')) {
    /**
     * Derives a cryptographic CSRF token strictly bound to an authenticated `pfg_session` cookie token.
     */
    function pfgComputeSessionBoundCsrfToken(string $rawSessionToken): string
    {
        return hash_hmac('sha256', 'pfg27:csrf:v2:' . hashToken($rawSessionToken), AUTH_SECRET);
    }
}

if (!function_exists('getCsrfToken')) {
    /**
     * Returns a session-bound CSRF token.
     * - For authenticated users: strictly bound to their active `pfg_session` token (automatically rotates on login/logout).
     * - For unauthenticated browser sessions: random 256-bit token stored in PHP $_SESSION.
     * NEVER uses a static anonymous fallback.
     */
    function getCsrfToken(): string
    {
        ensurePhpSessionStarted();
        $rawCookie = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
        if (is_string($rawCookie) && trim($rawCookie) !== '') {
            $bound = pfgComputeSessionBoundCsrfToken($rawCookie);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['pfg_csrf_token'] = $bound;
            }
            return $bound;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (empty($_SESSION['pfg_csrf_token']) || !is_string($_SESSION['pfg_csrf_token']) || strlen($_SESSION['pfg_csrf_token']) < 32) {
                $_SESSION['pfg_csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['pfg_csrf_token'];
        }
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Verifies a session-bound CSRF token passed via X-CSRF-Token header, form field, or JSON body.
     * Fails closed if no active session exists or if token does not match.
     */
    function verifyCsrfToken(?string $token = null): bool
    {
        ensurePhpSessionStarted();
        if ($token === null || $token === '') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_POST['csrf_token']
                ?? $_POST['_csrf']
                ?? null;
            if ($token === null) {
                $input = readRequestInput();
                $token = $input['csrf_token'] ?? $input['csrfToken'] ?? null;
            }
        }
        if (!is_string($token) || trim($token) === '') {
            return false;
        }
        $provided = trim($token);

        $rawCookie = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
        if (is_string($rawCookie) && trim($rawCookie) !== '') {
            $expected = pfgComputeSessionBoundCsrfToken($rawCookie);
            return hash_equals($expected, $provided);
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['pfg_csrf_token']) && is_string($_SESSION['pfg_csrf_token'])) {
            return hash_equals($_SESSION['pfg_csrf_token'], $provided);
        }

        return false;
    }
}

if (!function_exists('requireCsrfToken')) {
    /**
     * Enforces valid CSRF token on all authenticated state-changing endpoints BEFORE any DB mutation.
     */
    function requireCsrfToken(?array $user = null): void
    {
        if (verifyCsrfToken()) {
            return;
        }
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'CSRF_REJECTED',
            'resourceType' => 'security',
            'resourceId'   => $_SERVER['REQUEST_URI'] ?? null,
            'metadata'     => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri'    => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
            ],
        ]);
        jsonResponse(['error' => 'Invalid or missing CSRF token.'], 403);
    }
}

// ============================================================================
// 3. PERSISTENT BLUEHOST-COMPATIBLE RATE LIMITING (MySQL/MariaDB/SQLite)
// ============================================================================

if (!function_exists('pfgCheckRateLimit')) {
    /**
     * Persistent database-backed sliding/fixed window rate limiter compatible with shared Bluehost hosting.
     * Returns ['allowed' => bool, 'attempts' => int, 'retryAfter' => int].
     */
    function pfgCheckRateLimit(
        PDO $pdo,
        string $scope,
        string $identifier,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds = 900,
        bool $increment = true
    ): array {
        $normScope = strtolower(trim($scope));
        $normIdent = strtolower(trim($identifier));
        $bucketKey = hash('sha256', $normScope . '|' . $normIdent);
        $nowTs = time();
        $nowStr = nowSql($nowTs);

        try {
            $stmt = $pdo->prepare('SELECT attempts, window_started_at, blocked_until FROM security_rate_limits WHERE bucket_key = :k LIMIT 1');
            $stmt->execute([':k' => $bucketKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['blocked_until'])) {
                $blockedTs = strtotime((string)$row['blocked_until']);
                if ($blockedTs !== false && $blockedTs > $nowTs) {
                    return [
                        'allowed'    => false,
                        'attempts'   => (int)$row['attempts'],
                        'retryAfter' => max(1, $blockedTs - $nowTs),
                    ];
                }
            }

            $attempts = 0;
            $windowStartedTs = $nowTs;
            if ($row) {
                $prevWindowTs = strtotime((string)$row['window_started_at']);
                $prevBlockedTs = !empty($row['blocked_until']) ? strtotime((string)$row['blocked_until']) : false;
                if ($prevBlockedTs !== false && $prevBlockedTs <= $nowTs) {
                    // Block expired -> cleanly reset window
                    $attempts = 0;
                    $windowStartedTs = $nowTs;
                } elseif ($prevWindowTs !== false && ($nowTs - $prevWindowTs) < $windowSeconds) {
                    $attempts = (int)$row['attempts'];
                    $windowStartedTs = $prevWindowTs;
                }
            }

            if (!$increment) {
                $allowed = $attempts < $maxAttempts;
                return [
                    'allowed'    => $allowed,
                    'attempts'   => $attempts,
                    'retryAfter' => $allowed ? 0 : max(1, $windowSeconds - ($nowTs - $windowStartedTs)),
                ];
            }

            $nextAttempts = $attempts + 1;
            $exceeded = $nextAttempts > $maxAttempts;
            $blockedUntilStr = $exceeded ? nowSql($nowTs + $blockSeconds) : null;
            $windowStr = nowSql($windowStartedTs);

            if ($row) {
                $upd = $pdo->prepare('
                    UPDATE security_rate_limits
                    SET attempts = :att,
                        window_started_at = :ws,
                        blocked_until = :bu,
                        updated_at = :now
                    WHERE bucket_key = :k
                ');
                $upd->execute([
                    ':att' => $nextAttempts,
                    ':ws'  => $windowStr,
                    ':bu'  => $blockedUntilStr,
                    ':now' => $nowStr,
                    ':k'   => $bucketKey,
                ]);
            } else {
                $ins = $pdo->prepare('
                    INSERT INTO security_rate_limits (bucket_key, scope, attempts, window_started_at, blocked_until, updated_at)
                    VALUES (:k, :sc, :att, :ws, :bu, :now)
                ');
                $ins->execute([
                    ':k'   => $bucketKey,
                    ':sc'  => $normScope,
                    ':att' => $nextAttempts,
                    ':ws'  => $windowStr,
                    ':bu'  => $blockedUntilStr,
                    ':now' => $nowStr,
                ]);
            }

            return [
                'allowed'    => !$exceeded,
                'attempts'   => $nextAttempts,
                'retryAfter' => $exceeded ? $blockSeconds : 0,
            ];
        } catch (Throwable $e) {
            error_log('pfgCheckRateLimit warning: ' . $e->getMessage());
            return ['allowed' => true, 'attempts' => 0, 'retryAfter' => 0];
        }
    }
}

if (!function_exists('pfgResetRateLimit')) {
    function pfgResetRateLimit(PDO $pdo, string $scope, string $identifier): void
    {
        try {
            $bucketKey = hash('sha256', strtolower(trim($scope)) . '|' . strtolower(trim($identifier)));
            $stmt = $pdo->prepare('DELETE FROM security_rate_limits WHERE bucket_key = :k');
            $stmt->execute([':k' => $bucketKey]);
        } catch (Throwable $e) {
        }
    }
}

if (!function_exists('pfgRequireRateLimit')) {
    function pfgRequireRateLimit(
        PDO $pdo,
        string $scope,
        string $identifier,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds = 900,
        ?string $actorUserId = null
    ): void {
        $rl = pfgCheckRateLimit($pdo, $scope, $identifier, $maxAttempts, $windowSeconds, $blockSeconds, true);
        if (!empty($rl['allowed'])) {
            return;
        }
        $retry = (int)($rl['retryAfter'] ?? $blockSeconds);
        logAudit([
            'actorUserId'  => $actorUserId,
            'action'       => 'RATE_LIMIT_EXCEEDED',
            'resourceType' => 'security',
            'resourceId'   => $scope,
            'metadata'     => [
                'scope'      => $scope,
                'attempts'   => $rl['attempts'] ?? 0,
                'retryAfter' => $retry,
            ],
        ]);
        if (!headers_sent()) {
            header('Retry-After: ' . $retry);
        }
        jsonResponse([
            'error'      => 'Too many requests. Please wait before trying again.',
            'retryAfter' => $retry,
        ], 429);
    }
}

// ============================================================================
// 4. PURE-PHP RFC 6238 TOTP MFA (160-BIT ENTROPY, AES-256-GCM, REPLAY GUARD, RECOVERY CODES)
// ============================================================================

if (!function_exists('base32Encode')) {
    function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $len = strlen($binary);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }
        $output = '';
        $bitLen = strlen($bits);
        for ($i = 0; $i < $bitLen; $i += 5) {
            $chunk = str_pad(substr($bits, $i, 5), 5, '0', STR_PAD_RIGHT);
            $output .= $alphabet[bindec($chunk)];
        }
        return $output;
    }
}

if (!function_exists('base32Decode')) {
    function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $cleaned = strtoupper((string)preg_replace('/[^A-Za-z2-7]/', '', $base32));
        $bits = '';
        $len = strlen($cleaned);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $cleaned[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        $bitLen = strlen($bits);
        for ($i = 0; $i + 8 <= $bitLen; $i += 8) {
            $binary .= chr(bindec(substr($bits, $i, 8)));
        }
        return $binary;
    }
}

if (!function_exists('buildOtpAuthUrl')) {
    function buildOtpAuthUrl(string $email, string $secret, string $issuer = 'Purple Festival Gujarat 2027'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($email);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer)
        );
    }
}

if (!function_exists('generateMfaSecret')) {
    /**
     * Generates a cryptographically strong 160-bit (20-byte / 32 Base32 character) RFC 6238 TOTP secret.
     */
    function generateMfaSecret(?string $email = null)
    {
        $secret = base32Encode(random_bytes(20)); // 20 bytes = 160 bits = exactly 32 Base32 chars
        if ($email !== null && $email !== '') {
            return [
                'secret'     => $secret,
                'otpauthUrl' => buildOtpAuthUrl($email, $secret),
            ];
        }
        return $secret;
    }
}

if (!function_exists('encryptMfaSecret')) {
    function encryptMfaSecret(string $plaintextSecret): string
    {
        return pfgEncryptValue($plaintextSecret, 'mfa');
    }
}

if (!function_exists('decryptMfaSecret')) {
    function decryptMfaSecret(?string $storedSecret): string
    {
        $decrypted = pfgDecryptValue($storedSecret, 'mfa');
        return is_string($decrypted) ? $decrypted : '';
    }
}

if (!function_exists('computeTotpCode')) {
    function computeTotpCode(string $base32Secret, int $timeSlice): string
    {
        $secretKey = base32Decode($base32Secret);
        if ($secretKey === '') {
            return '';
        }
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        $offset = ord($hash[19]) & 0x0f;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$truncated, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('verifyMfaTokenWithSlice')) {
    /**
     * Verifies a 6-digit RFC 6238 TOTP code against a Base32 secret (+/- $window slices)
     * and returns the matching 30-second integer time slice (or null on mismatch).
     */
    function verifyMfaTokenWithSlice(string $secret, string $code, int $window = 1): ?int
    {
        $cleanedCode = preg_replace('/\s+/', '', $code) ?? '';
        $cleanedSecret = preg_replace('/\s+/', '', $secret) ?? '';
        if (!preg_match('/^\d{6}$/', $cleanedCode) || $cleanedSecret === '') {
            return null;
        }
        $currentSlice = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $slice = $currentSlice + $i;
            $candidate = computeTotpCode($cleanedSecret, $slice);
            if ($candidate !== '' && hash_equals($candidate, $cleanedCode)) {
                return $slice;
            }
        }
        return null;
    }
}

if (!function_exists('verifyMfaToken')) {
    /**
     * Verifies a 6-digit RFC 6238 TOTP code against a Base32 secret (+/- 1 window).
     * Accepts arguments in either ($secret, $code) or ($code, $secret) order.
     */
    function verifyMfaToken(string $arg1, string $arg2, int $window = 1): bool
    {
        $a = preg_replace('/\s+/', '', $arg1) ?? '';
        $b = preg_replace('/\s+/', '', $arg2) ?? '';

        if (preg_match('/^\d{6}$/', $a) && !preg_match('/^\d{6}$/', $b)) {
            $code = $a;
            $secret = $b;
        } else {
            $secret = $a;
            $code = $b;
        }

        return verifyMfaTokenWithSlice($secret, $code, $window) !== null;
    }
}

if (!function_exists('consumeTotpSliceIfFresh')) {
    /**
     * Prevents TOTP code replay within the 30–90 second validity window by tracking the last accepted slice.
     */
    function consumeTotpSliceIfFresh(PDO $pdo, string $userId, int $timeSlice): bool
    {
        $stmt = $pdo->prepare("SELECT id, provider_account_id FROM auth_accounts WHERE user_id = :uid AND provider = 'mfa_replay' LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $lastSlice = (int)($row['provider_account_id'] ?? 0);
            if ($timeSlice <= $lastSlice) {
                return false; // Replay detected!
            }
            $upd = $pdo->prepare("UPDATE auth_accounts SET provider_account_id = :slice, token_hash = :th, expires_at = :exp WHERE id = :id");
            $upd->execute([
                ':slice' => (string)$timeSlice,
                ':th'    => hashToken('slice:' . $userId . ':' . $timeSlice),
                ':exp'   => nowSql(time() + 86400),
                ':id'    => $row['id'],
            ]);
            return true;
        }

        $ins = $pdo->prepare("
            INSERT INTO auth_accounts (id, user_id, provider, provider_account_id, token_hash, expires_at, created_at)
            VALUES (:id, :uid, 'mfa_replay', :slice, :th, :exp, :now)
        ");
        $ins->execute([
            ':id'    => uuid4(),
            ':uid'   => $userId,
            ':slice' => (string)$timeSlice,
            ':th'    => hashToken('slice:' . $userId . ':' . $timeSlice),
            ':exp'   => nowSql(time() + 86400),
            ':now'   => nowSql(),
        ]);
        return true;
    }
}

if (!function_exists('pfgHashRecoveryCode')) {
    function pfgHashRecoveryCode(string $userId, string $rawCode): string
    {
        $normalized = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $rawCode));
        return hash_hmac('sha256', 'pfg27:mfa-recovery:' . $userId . ':' . $normalized, AUTH_SECRET);
    }
}

if (!function_exists('generateMfaRecoveryCodes')) {
    /**
     * Generates single-use recovery codes, persists ONLY cryptographic hashes in auth_accounts,
     * and returns plaintext codes ONCE for the user to store safely.
     */
    function generateMfaRecoveryCodes(PDO $pdo, string $userId, int $count = 8): array
    {
        $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider = 'mfa_recovery'")
            ->execute([':uid' => $userId]);

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $alphaMax = strlen($alphabet) - 1;
        $codes = [];
        $now = nowSql();
        $ins = $pdo->prepare("
            INSERT INTO auth_accounts (id, user_id, provider, provider_account_id, token_hash, expires_at, created_at)
            VALUES (:id, :uid, 'mfa_recovery', :slot, :th, NULL, :now)
        ");

        for ($i = 0; $i < $count; $i++) {
            $part1 = '';
            $part2 = '';
            for ($c = 0; $c < 4; $c++) {
                $part1 .= $alphabet[random_int(0, $alphaMax)];
                $part2 .= $alphabet[random_int(0, $alphaMax)];
            }
            $code = $part1 . '-' . $part2;
            $codes[] = $code;
            $ins->execute([
                ':id'   => uuid4(),
                ':uid'  => $userId,
                ':slot' => 'rc_' . ($i + 1),
                ':th'   => pfgHashRecoveryCode($userId, $code),
                ':now'  => $now,
            ]);
        }

        return $codes;
    }
}

if (!function_exists('consumeMfaRecoveryCode')) {
    /**
     * Validates and atomically consumes a single-use MFA recovery code. Returns true once, false on reuse.
     */
    function consumeMfaRecoveryCode(PDO $pdo, string $userId, string $rawCode): bool
    {
        $normalized = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $rawCode));
        if (strlen($normalized) !== 8) {
            return false;
        }
        $hash = pfgHashRecoveryCode($userId, $rawCode);
        $stmt = $pdo->prepare("
            SELECT id FROM auth_accounts
            WHERE user_id = :uid AND provider = 'mfa_recovery' AND token_hash = :th
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':th' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $del = $pdo->prepare("DELETE FROM auth_accounts WHERE id = :id");
        $del->execute([':id' => $row['id']]);
        return $del->rowCount() > 0;
    }
}

if (!function_exists('countRemainingRecoveryCodes')) {
    function countRemainingRecoveryCodes(PDO $pdo, string $userId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM auth_accounts WHERE user_id = :uid AND provider = 'mfa_recovery'");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('isMfaRequiredForRoles')) {
    /**
     * D-20 Approved MFA Policy:
     * - SUPERADMIN: Mandatory TOTP MFA
     * - ADMIN: Mandatory TOTP MFA
     * - RGST: Optional MFA initially
     * - PARTICIPANT: Optional MFA
     */
    function isMfaRequiredForRoles(array $roles): bool
    {
        return in_array('SUPERADMIN', $roles, true) || in_array('ADMIN', $roles, true);
    }
}

if (!function_exists('userRequiresMfaEnrollment')) {
    function userRequiresMfaEnrollment(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        return isMfaRequiredForRoles($roles) && empty($user['mfaEnabled']);
    }
}

if (!function_exists('userHasCompletedRequiredMfa')) {
    function userHasCompletedRequiredMfa(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        if (!isMfaRequiredForRoles($roles)) {
            return true;
        }
        return !empty($user['mfaEnabled']) && !empty($user['mfaVerified']);
    }
}

// ============================================================================
// 5. SESSION & COOKIE MANAGEMENT (WITH FIXATION PROTECTION & BULK REVOCATION)
// ============================================================================

if (!function_exists('setSessionCookie')) {
    function setSessionCookie(string $rawToken, int $expiresTimestamp): void
    {
        $_COOKIE[SESSION_COOKIE_NAME] = $rawToken;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['pfg_csrf_token'] = pfgComputeSessionBoundCsrfToken($rawToken);
        }
        if (headers_sent()) {
            return;
        }
        setcookie(SESSION_COOKIE_NAME, $rawToken, [
            'expires'  => $expiresTimestamp,
            'path'     => '/',
            'secure'   => pfgIsHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('clearSessionCookie')) {
    function clearSessionCookie(): void
    {
        unset($_COOKIE[SESSION_COOKIE_NAME]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['pfg_csrf_token']);
        }
        if (headers_sent()) {
            return;
        }
        setcookie(SESSION_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => pfgIsHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('createSession')) {
    /**
     * Creates a new authenticated session row, mitigates session fixation by revoking any prior cookie
     * and regenerating PHP session ID, sets the HttpOnly `pfg_session` cookie, and rotates the CSRF token.
     */
    function createSession(string $userId, array $options = [], ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? getDb();

        // Revoke any pre-existing session cookie to prevent session fixation
        $prevCookie = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
        if (is_string($prevCookie) && trim($prevCookie) !== '') {
            try {
                $pdo->prepare('UPDATE sessions SET revoked_at = :now WHERE token_hash = :th AND revoked_at IS NULL')
                    ->execute([':now' => nowSql(), ':th' => hashToken($prevCookie)]);
            } catch (Throwable $e) {
            }
        }

        ensurePhpSessionStarted();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash = hashToken($rawToken);
        $expiresTimestamp = time() + SESSION_DURATION_SECONDS;
        $expiresAt = nowSql($expiresTimestamp);
        $sessionId = uuid4();

        $ipAddress = $options['ipAddress'] ?? getClientIp();
        $userAgent = $options['userAgent'] ?? getClientUserAgent();
        $mfaVerified = !empty($options['mfaVerified']) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO sessions (id, user_id, token_hash, ip_address, user_agent, mfa_verified, expires_at, created_at)
            VALUES (:id, :user_id, :token_hash, :ip_address, :user_agent, :mfa_verified, :expires_at, :created_at)
        ");
        $stmt->execute([
            ':id'           => $sessionId,
            ':user_id'      => $userId,
            ':token_hash'   => $tokenHash,
            ':ip_address'   => $ipAddress,
            ':user_agent'   => $userAgent,
            ':mfa_verified' => $mfaVerified,
            ':expires_at'   => $expiresAt,
            ':created_at'   => nowSql(),
        ]);

        setSessionCookie($rawToken, $expiresTimestamp);

        return [
            'rawToken'  => $rawToken,
            'expiresAt' => $expiresAt,
            'sessionId' => $sessionId,
            'csrfToken' => pfgComputeSessionBoundCsrfToken($rawToken),
        ];
    }
}

if (!function_exists('destroyCurrentSession')) {
    /**
     * Revokes the current session in the database, clears the `pfg_session` cookie, and destroys PHP session state.
     */
    function destroyCurrentSession(?PDO $pdo = null): void
    {
        $rawToken = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
        if (is_string($rawToken) && $rawToken !== '') {
            $pdo = $pdo ?? getDb();
            $tokenHash = hashToken($rawToken);
            $stmt = $pdo->prepare("UPDATE sessions SET revoked_at = :now WHERE token_hash = :token_hash");
            $stmt->execute([
                ':now'        => nowSql(),
                ':token_hash' => $tokenHash,
            ]);
        }
        clearSessionCookie();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            @session_destroy();
        }
    }
}

if (!function_exists('revokeAllUserSessions')) {
    /**
     * Revokes all active sessions for a user (optionally keeping one current session ID) and audits the event.
     */
    function revokeAllUserSessions(
        string $userId,
        ?string $exceptSessionId = null,
        ?PDO $pdo = null,
        ?string $actorUserId = null,
        string $reason = 'security_policy'
    ): int {
        $pdo = $pdo ?? getDb();
        $now = nowSql();
        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $stmt = $pdo->prepare('UPDATE sessions SET revoked_at = :now WHERE user_id = :uid AND id <> :sid AND revoked_at IS NULL');
            $stmt->execute([':now' => $now, ':uid' => $userId, ':sid' => $exceptSessionId]);
        } else {
            $stmt = $pdo->prepare('UPDATE sessions SET revoked_at = :now WHERE user_id = :uid AND revoked_at IS NULL');
            $stmt->execute([':now' => $now, ':uid' => $userId]);
        }
        $revokedCount = $stmt->rowCount();

        logAudit([
            'actorUserId'  => $actorUserId ?? $userId,
            'action'       => 'SESSIONS_REVOKED',
            'resourceType' => 'session',
            'resourceId'   => $userId,
            'metadata'     => [
                'reason'       => $reason,
                'revokedCount' => $revokedCount,
                'keptCurrent'  => $exceptSessionId !== null && $exceptSessionId !== '',
            ],
        ]);

        return $revokedCount;
    }
}

// ============================================================================
// 6. USER, ROLES & PERMISSIONS RESOLUTION
// ============================================================================

if (!function_exists('getUserRolesAndPermissions')) {
    /**
     * Returns ['roles' => [...], 'permissions' => [...]] for a user.
     */
    function getUserRolesAndPermissions(string $userId, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? getDb();
        $stmt = $pdo->prepare("
            SELECT r.code AS role_code, ur.custom_permissions
            FROM user_roles ur
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $assigned = $stmt->fetchAll();

        $roles = [];
        $permMap = [];

        if (!empty($assigned)) {
            foreach ($assigned as $row) {
                $rCode = (string)$row['role_code'];
                if (!in_array($rCode, $roles, true)) {
                    $roles[] = $rCode;
                }
            }
        } else {
            $roles = ['PARTICIPANT'];
        }

        // Phase 8 / CRIT-01: reusable operational permission profile (ADMIN tier ONLY — never RGST / PARTICIPANT / SUPERADMIN).
        // When an ADMIN (without SUPERADMIN) has an active permission profile, enforce strict least privilege:
        // grant ONLY account.read.own + profile permissions (filtered against forbidden perms) + explicit user_roles.custom_permissions,
        // and do NOT merge the broad 17 DEFAULT_ROLE_PERMISSIONS['ADMIN'].
        // Legacy/unprofiled ADMINs (permission_profile_id IS NULL) preserve DEFAULT_ROLE_PERMISSIONS['ADMIN'].
        $profileId = null;
        $profileSlug = null;
        $activeAdminProfile = null;
        if (in_array('ADMIN', $roles, true) && !in_array('SUPERADMIN', $roles, true)) {
            $activeAdminProfile = pfgResolvePermissionProfile($userId, $pdo);
            if ($activeAdminProfile !== null) {
                $profileId = $activeAdminProfile['id'];
                $profileSlug = $activeAdminProfile['slug'];
            }
        }

        if (!empty($assigned)) {
            $validPerms = array_values(PERMISSIONS);
            foreach ($assigned as $row) {
                $rCode = (string)$row['role_code'];
                if ($rCode === 'ADMIN' && $activeAdminProfile !== null) {
                    $permMap[PERMISSIONS['ACCOUNT_READ_OWN']] = true;
                } else {
                    $basePerms = DEFAULT_ROLE_PERMISSIONS[$rCode] ?? [];
                    foreach ($basePerms as $p) {
                        $permMap[$p] = true;
                    }
                }
                // Only ADMIN/SUPERADMIN may receive explicit custom administrative permissions
                if (in_array($rCode, ['ADMIN', 'SUPERADMIN'], true)) {
                    $custom = jsonDecode($row['custom_permissions'] ?? null, []);
                    if (is_array($custom)) {
                        foreach ($custom as $cp) {
                            if (is_string($cp) && in_array($cp, $validPerms, true)) {
                                $permMap[$cp] = true;
                            }
                        }
                    }
                }
            }
        } else {
            foreach (DEFAULT_ROLE_PERMISSIONS['PARTICIPANT'] as $p) {
                $permMap[$p] = true;
            }
        }

        if ($activeAdminProfile !== null) {
            $permMap[PERMISSIONS['ACCOUNT_READ_OWN']] = true;
            $forbidden = pfgProfileForbiddenPermissions();
            foreach ($activeAdminProfile['permissions'] as $pp) {
                if (is_string($pp) && !in_array($pp, $forbidden, true)) {
                    $permMap[$pp] = true;
                }
            }
        }

        return [
            'roles'                 => $roles,
            'permissions'           => array_keys($permMap),
            'permissionProfileId'   => $profileId,
            'permissionProfileSlug' => $profileSlug,
        ];
    }
}

if (!function_exists('pfgProfileForbiddenPermissions')) {
    /**
     * Permission codes that can NEVER be conferred through an operational permission profile (CAD-04 / MED-05).
     */
    function pfgProfileForbiddenPermissions(): array
    {
        return [
            PERMISSIONS['ADMIN_ACCOUNTS_MANAGE'],
            PERMISSIONS['SETTINGS_MANAGE'],
            PERMISSIONS['AUDIT_LOGS_READ'],
        ];
    }
}

if (!function_exists('pfgResolvePermissionProfile')) {
    /**
     * Resolves the active operational permission profile assigned to an ADMIN user.
     * Returns ['id','slug','permissions'=>[valid, non-forbidden codes]] or null when none / table not migrated.
     * Fails closed (null => no extra permissions) on any database error.
     */
    function pfgResolvePermissionProfile(string $userId, PDO $pdo): ?array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.slug, p.permissions_json
                FROM users u
                INNER JOIN admin_permission_profiles p ON p.id = u.permission_profile_id
                WHERE u.id = :uid AND p.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([':uid' => $userId]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        $decoded = jsonDecode($row['permissions_json'] ?? '[]', []);
        $valid = array_values(PERMISSIONS);
        $forbidden = pfgProfileForbiddenPermissions();
        $perms = [];
        if (is_array($decoded)) {
            foreach ($decoded as $code) {
                if (is_string($code) && in_array($code, $valid, true) && !in_array($code, $forbidden, true) && !in_array($code, $perms, true)) {
                    $perms[] = $code;
                }
            }
        }
        return [
            'id'          => (string)$row['id'],
            'slug'        => (string)$row['slug'],
            'permissions' => $perms,
        ];
    }
}

if (!function_exists('getCurrentUser')) {
    /**
     * Resolves the currently authenticated user from the `pfg_session` cookie.
     * Returns null if unauthenticated, expired, revoked, or inactive.
     */
    function getCurrentUser(?PDO $pdo = null): ?array
    {
        $rawToken = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
        if (!is_string($rawToken) || trim($rawToken) === '') {
            return null;
        }

        $pdo = $pdo ?? getDb();
        $tokenHash = hashToken($rawToken);
        $now = nowSql();

        $stmt = $pdo->prepare("
            SELECT
                s.id AS session_id,
                s.mfa_verified,
                u.id AS user_id,
                u.email,
                u.full_name,
                u.phone,
                u.country,
                u.is_active,
                u.mfa_enabled,
                u.deleted_at
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.token_hash = :token_hash
              AND s.revoked_at IS NULL
              AND s.expires_at > :now
            LIMIT 1
        ");
        $stmt->execute([
            ':token_hash' => $tokenHash,
            ':now'        => $now,
        ]);
        $row = $stmt->fetch();

        if (!$row || empty($row['is_active']) || !empty($row['deleted_at'])) {
            return null;
        }

        $rp = getUserRolesAndPermissions((string)$row['user_id'], $pdo);
        $mfaRequired = isMfaRequiredForRoles($rp['roles']);
        $mfaEnabled = (bool)$row['mfa_enabled'];
        $mfaVerified = (bool)$row['mfa_verified'];

        return [
            'id'                    => (string)$row['user_id'],
            'email'                 => (string)$row['email'],
            'fullName'              => (string)$row['full_name'],
            'full_name'             => (string)$row['full_name'],
            'phone'                 => $row['phone'] !== null ? (string)$row['phone'] : null,
            'country'               => (string)($row['country'] ?? 'India'),
            'roles'                 => $rp['roles'],
            'permissions'           => $rp['permissions'],
            'permissionProfileId'   => $rp['permissionProfileId'] ?? null,
            'permissionProfileSlug' => $rp['permissionProfileSlug'] ?? null,
            'mfaEnabled'            => $mfaEnabled,
            'mfa_enabled'           => $mfaEnabled,
            'mfaVerified'           => $mfaVerified,
            'mfa_verified'          => $mfaVerified,
            'mfaRequiredForRole'    => $mfaRequired,
            'mfaEnrollmentRequired' => $mfaRequired && !$mfaEnabled,
            'sessionId'             => (string)$row['session_id'],
            'session_id'            => (string)$row['session_id'],
        ];
    }
}

if (!function_exists('hasRole')) {
    function hasRole(?array $user, array $allowedRoles): bool
    {
        if (!$user || empty($user['roles']) || !is_array($user['roles'])) {
            return false;
        }
        foreach ($user['roles'] as $r) {
            if (in_array($r, $allowedRoles, true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission(?array $user, string $perm): bool
    {
        if (!$user) {
            return false;
        }
        $roles = $user['roles'] ?? [];
        if (is_array($roles) && in_array('SUPERADMIN', $roles, true)) {
            return true;
        }
        $perms = $user['permissions'] ?? [];
        return is_array($perms) && in_array($perm, $perms, true);
    }
}

if (!function_exists('getPortalPathForRoles')) {
    function getPortalPathForRoles(array $roles): string
    {
        if (in_array('SUPERADMIN', $roles, true) || in_array('ADMIN', $roles, true)) {
            return '/admin';
        }
        if (in_array('RGST', $roles, true)) {
            return '/rgst';
        }
        return '/dashboard';
    }
}

// ============================================================================
// 7. RESPONSE & ACCESS CONTROL GUARDS (WITH MANDATORY ADMIN MFA ENFORCEMENT)
// ============================================================================

if (!function_exists('jsonResponse')) {
    /**
     * Outputs a JSON HTTP response with status code and terminates script execution.
     */
    function jsonResponse($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo jsonEncode($data);
        exit;
    }
}

if (!function_exists('isApiRequest')) {
    function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($uri, '/api/') !== false) {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('enforceStaffMfaGate')) {
    /**
     * Enforces D-20 mandatory MFA policy for SUPERADMIN and ADMIN accounts on protected resources.
     */
    function enforceStaffMfaGate(array $user): void
    {
        if (userHasCompletedRequiredMfa($user)) {
            return;
        }
        $enrollmentNeeded = userRequiresMfaEnrollment($user);
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'security',
            'resourceId'   => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
            'metadata'     => [
                'reason' => $enrollmentNeeded ? 'MFA_ENROLLMENT_REQUIRED' : 'MFA_VERIFICATION_REQUIRED',
                'roles'  => $user['roles'] ?? [],
            ],
        ]);
        if (isApiRequest()) {
            jsonResponse([
                'error'                 => $enrollmentNeeded
                    ? 'Multi-factor authentication enrollment is mandatory for your role before accessing administrative operations.'
                    : 'Multi-factor authentication verification is required for this session.',
                'mfaEnrollmentRequired' => $enrollmentNeeded,
                'mfaRequired'           => !$enrollmentNeeded,
            ], 403);
        }
        header('Location: /admin?mfa_setup=1');
        exit;
    }
}

if (!function_exists('requireAuth')) {
    /**
     * Requires an authenticated user session.
     * Sends 401 JSON for API endpoints or redirects to /login for browser pages.
     */
    function requireAuth(?PDO $pdo = null): array
    {
        $user = getCurrentUser($pdo);
        if ($user) {
            return $user;
        }
        if (isApiRequest()) {
            jsonResponse(['error' => 'Authentication required.'], 401);
        }
        $currentUri = pfgValidateSafeRedirect($_SERVER['REQUEST_URI'] ?? '/dashboard', '/dashboard');
        header('Location: /login?next=' . rawurlencode($currentUri));
        exit;
    }
}

if (!function_exists('requireRole')) {
    /**
     * Requires the authenticated user to hold at least one role in $roles,
     * and enforces mandatory MFA for SUPERADMIN/ADMIN unless $allowPendingMfa is true.
     */
    function requireRole(array $roles, ?array $user = null, bool $allowPendingMfa = false): array
    {
        $user = $user ?? requireAuth();
        if (!hasRole($user, $roles)) {
            logAudit([
                'actorUserId'  => $user['id'] ?? null,
                'action'       => 'ACCESS_DENIED',
                'resourceType' => 'rbac',
                'resourceId'   => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
                'metadata'     => [
                    'requiredRoles' => $roles,
                    'actualRoles'   => $user['roles'] ?? [],
                ],
            ]);
            if (isApiRequest()) {
                jsonResponse(['error' => 'Forbidden: insufficient role permissions.'], 403);
            }
            header('Location: ' . getPortalPathForRoles($user['roles'] ?? ['PARTICIPANT']));
            exit;
        }
        if (!$allowPendingMfa) {
            enforceStaffMfaGate($user);
        }
        return $user;
    }
}

if (!function_exists('requirePermission')) {
    /**
     * Requires the authenticated user to hold the specified permission code (or SUPERADMIN),
     * and enforces mandatory MFA for SUPERADMIN/ADMIN.
     */
    function requirePermission(string $perm, ?array $user = null, bool $allowPendingMfa = false): array
    {
        $user = $user ?? requireAuth();
        if (!$allowPendingMfa) {
            enforceStaffMfaGate($user);
        }
        if (hasPermission($user, $perm)) {
            return $user;
        }
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'rbac',
            'resourceId'   => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
            'metadata'     => [
                'requiredPermission' => $perm,
                'actualRoles'        => $user['roles'] ?? [],
            ],
        ]);
        if (isApiRequest()) {
            jsonResponse(['error' => "Permission {$perm} is required."], 403);
        }
        header('Location: ' . getPortalPathForRoles($user['roles'] ?? ['PARTICIPANT']));
        exit;
    }
}

// ============================================================================
// 8. IMMUTABLE AUDIT LOGGING (WITH AUTOMATIC SECRET SCRUBBING)
// ============================================================================

if (!function_exists('logAudit')) {
    /**
     * Writes an immutable audit log entry to `audit_logs`, automatically scrubbing secret/token fields.
     */
    function logAudit(...$args): void
    {
        try {
            $pdo = null;
            if (!empty($args) && $args[0] instanceof PDO) {
                $pdo = array_shift($args);
            }
            $pdo = $pdo ?? getDb();

            if (!empty($args) && is_array($args[0])) {
                $params = $args[0];
                $actorUserId  = $params['actorUserId']  ?? $params['actor_user_id']  ?? null;
                $action       = (string)($params['action'] ?? 'UNKNOWN_ACTION');
                $resourceType = (string)($params['resourceType'] ?? $params['resource_type'] ?? 'system');
                $resourceId   = $params['resourceId']   ?? $params['resource_id']   ?? null;
                $ipAddress    = $params['ipAddress']    ?? $params['ip_address']    ?? getClientIp();
                $userAgent    = $params['userAgent']    ?? $params['user_agent']    ?? getClientUserAgent();
                $metadata     = $params['metadata']     ?? $params['details']       ?? null;
            } else {
                $actorUserId  = $args[0] ?? null;
                $action       = (string)($args[1] ?? 'UNKNOWN_ACTION');
                $resourceType = (string)($args[2] ?? 'system');
                $resourceId   = isset($args[3]) ? (string)$args[3] : null;
                $metadata     = $args[4] ?? null;
                $ipAddress    = $args[5] ?? getClientIp();
                $userAgent    = $args[6] ?? getClientUserAgent();
            }

            $metaJson = null;
            if ($metadata !== null) {
                if (is_array($metadata)) {
                    $safeMeta = function_exists('pfgScrubSensitiveArray') ? pfgScrubSensitiveArray($metadata) : $metadata;
                    $metaJson = jsonEncode($safeMeta);
                } else {
                    $metaJson = (string)$metadata;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs
                (id, actor_user_id, action, resource_type, resource_id, ip_address, user_agent, metadata, created_at)
                VALUES (:id, :actor_user_id, :action, :resource_type, :resource_id, :ip_address, :user_agent, :metadata, :created_at)
            ");
            $stmt->execute([
                ':id'            => uuid4(),
                ':actor_user_id' => $actorUserId ?: null,
                ':action'        => $action,
                ':resource_type' => $resourceType,
                ':resource_id'   => $resourceId !== null ? (string)$resourceId : null,
                ':ip_address'    => $ipAddress,
                ':user_agent'    => $userAgent,
                ':metadata'      => $metaJson,
                ':created_at'    => nowSql(),
            ]);
        } catch (Throwable $e) {
            error_log('logAudit error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('recordAuditLog')) {
    function recordAuditLog(...$args): void
    {
        logAudit(...$args);
    }
}
