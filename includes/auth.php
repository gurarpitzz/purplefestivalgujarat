<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Authentication, RBAC, Session, CSRF & RFC 6238 TOTP MFA Layer
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// ============================================================================
// 1. TOKEN & PASSWORD CRYPTOGRAPHY
// ============================================================================

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
        // Normalize Node bcryptjs $2a$/$2b$ prefix if needed (PHP password_verify supports $2a$, $2b$, $2y$ natively)
        return password_verify($password, $hash);
    }
}

// ============================================================================
// 2. CSRF PROTECTION
// ============================================================================

if (!function_exists('ensurePhpSessionStarted')) {
    function ensurePhpSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || stripos(APP_URL, 'https://') === 0;
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

if (!function_exists('getCsrfToken')) {
    /**
     * Returns or generates a per-session CSRF token (with stateless HMAC fallback).
     */
    function getCsrfToken(): string
    {
        ensurePhpSessionStarted();
        if (!empty($_SESSION['pfg_csrf_token']) && is_string($_SESSION['pfg_csrf_token'])) {
            return $_SESSION['pfg_csrf_token'];
        }
        $sessionSeed = $_COOKIE[SESSION_COOKIE_NAME] ?? session_id() ?: 'pfg27-anon-csrf';
        $token = hash_hmac('sha256', (string)$sessionSeed, AUTH_SECRET);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['pfg_csrf_token'] = $token;
        }
        return $token;
    }
}

if (!function_exists('verifyCsrfToken')) {
    /**
     * Verifies a CSRF token passed explicitly, via X-CSRF-Token header, or via POST/JSON body.
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
        if (!is_string($token) || $token === '') {
            return false;
        }
        $expected = getCsrfToken();
        if (hash_equals($expected, $token)) {
            return true;
        }
        $sessionSeed = $_COOKIE[SESSION_COOKIE_NAME] ?? session_id() ?: 'pfg27-anon-csrf';
        $hmacExpected = hash_hmac('sha256', (string)$sessionSeed, AUTH_SECRET);
        return hash_equals($hmacExpected, $token);
    }
}

// ============================================================================
// 3. PURE-PHP RFC 6238 TOTP MFA
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
     * Generates a 160-bit Base32 RFC 6238 TOTP secret.
     * When $email is provided, returns ['secret' => ..., 'otpauthUrl' => ...];
     * when called without arguments, returns the Base32 secret string.
     */
    function generateMfaSecret(?string $email = null)
    {
        $secret = substr(base32Encode(random_bytes(10)), 0, 16);
        if ($email !== null && $email !== '') {
            return [
                'secret'     => $secret,
                'otpauthUrl' => buildOtpAuthUrl($email, $secret),
            ];
        }
        return $secret;
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

        if (!preg_match('/^\d{6}$/', $code) || $secret === '') {
            return false;
        }

        $currentSlice = (int)floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = computeTotpCode($secret, $currentSlice + $i);
            if ($candidate !== '' && hash_equals($candidate, $code)) {
                return true;
            }
        }
        return false;
    }
}

// ============================================================================
// 4. SESSION & COOKIE MANAGEMENT
// ============================================================================

if (!function_exists('setSessionCookie')) {
    function setSessionCookie(string $rawToken, int $expiresTimestamp): void
    {
        $_COOKIE[SESSION_COOKIE_NAME] = $rawToken;
        if (headers_sent()) {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || stripos(APP_URL, 'https://') === 0;

        setcookie(SESSION_COOKIE_NAME, $rawToken, [
            'expires'  => $expiresTimestamp,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('clearSessionCookie')) {
    function clearSessionCookie(): void
    {
        unset($_COOKIE[SESSION_COOKIE_NAME]);
        if (headers_sent()) {
            return;
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || stripos(APP_URL, 'https://') === 0;

        setcookie(SESSION_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('createSession')) {
    /**
     * Creates a new authenticated session row and sets the HttpOnly `pfg_session` cookie.
     */
    function createSession(string $userId, array $options = [], ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? getDb();
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
        ];
    }
}

if (!function_exists('destroyCurrentSession')) {
    /**
     * Revokes the current session in the database and deletes the `pfg_session` cookie.
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
    }
}

// ============================================================================
// 5. USER, ROLES & PERMISSIONS RESOLUTION
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
                $basePerms = DEFAULT_ROLE_PERMISSIONS[$rCode] ?? [];
                foreach ($basePerms as $p) {
                    $permMap[$p] = true;
                }
                $custom = jsonDecode($row['custom_permissions'] ?? null, []);
                if (is_array($custom)) {
                    foreach ($custom as $cp) {
                        if (is_string($cp) && $cp !== '') {
                            $permMap[$cp] = true;
                        }
                    }
                }
            }
        } else {
            $roles = ['PARTICIPANT'];
            foreach (DEFAULT_ROLE_PERMISSIONS['PARTICIPANT'] as $p) {
                $permMap[$p] = true;
            }
        }

        return [
            'roles'       => $roles,
            'permissions' => array_keys($permMap),
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

        return [
            'id'          => (string)$row['user_id'],
            'email'       => (string)$row['email'],
            'fullName'    => (string)$row['full_name'],
            'full_name'   => (string)$row['full_name'],
            'phone'       => $row['phone'] !== null ? (string)$row['phone'] : null,
            'country'     => (string)($row['country'] ?? 'India'),
            'roles'       => $rp['roles'],
            'permissions' => $rp['permissions'],
            'mfaEnabled'  => (bool)$row['mfa_enabled'],
            'mfa_enabled' => (bool)$row['mfa_enabled'],
            'mfaVerified' => (bool)$row['mfa_verified'],
            'mfa_verified'=> (bool)$row['mfa_verified'],
            'sessionId'   => (string)$row['session_id'],
            'session_id'  => (string)$row['session_id'],
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
// 6. RESPONSE & ACCESS CONTROL GUARDS
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
        $currentUri = $_SERVER['REQUEST_URI'] ?? '/dashboard';
        header('Location: /login?redirect=' . rawurlencode($currentUri));
        exit;
    }
}

if (!function_exists('requireRole')) {
    /**
     * Requires the authenticated user to hold at least one role in $roles.
     */
    function requireRole(array $roles, ?array $user = null): array
    {
        $user = $user ?? requireAuth();
        if (hasRole($user, $roles)) {
            return $user;
        }
        if (isApiRequest()) {
            jsonResponse(['error' => 'Forbidden: insufficient role permissions.'], 403);
        }
        header('Location: ' . getPortalPathForRoles($user['roles'] ?? ['PARTICIPANT']));
        exit;
    }
}

if (!function_exists('requirePermission')) {
    /**
     * Requires the authenticated user to hold the specified permission code (or SUPERADMIN).
     */
    function requirePermission(string $perm, ?array $user = null): array
    {
        $user = $user ?? requireAuth();
        if (hasPermission($user, $perm)) {
            return $user;
        }
        if (isApiRequest()) {
            jsonResponse(['error' => "Permission {$perm} is required."], 403);
        }
        header('Location: ' . getPortalPathForRoles($user['roles'] ?? ['PARTICIPANT']));
        exit;
    }
}

// ============================================================================
// 7. IMMUTABLE AUDIT LOGGING
// ============================================================================

if (!function_exists('logAudit')) {
    /**
     * Writes an immutable audit log entry to `audit_logs`.
     * Supports:
     *   - logAudit(['actorUserId' => ..., 'action' => ..., 'resourceType' => ..., 'resourceId' => ..., 'metadata' => ...])
     *   - logAudit($actorUserId, $action, $resourceType, $resourceId, $metadata)
     *   - logAudit($pdo, $actorUserId, $action, $resourceType, $resourceId, $metadata)
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
                $metaJson = is_string($metadata) ? $metadata : jsonEncode($metadata);
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
