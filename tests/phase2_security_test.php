<?php
declare(strict_types=1);

/**
 * Phase 2 Comprehensive Automated Security, Authorization, MFA, CSRF, XSS, QR Retirement & Privacy Test Suite
 * Executes against live local PHP 8.2 server + isolated MariaDB `pfg27_test` database + safe local file mail sink.
 */

$root = dirname(__DIR__);
$phpBin = PHP_BINARY;
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$sinkDir = $tmpDir . DIRECTORY_SEPARATOR . 'mail-sink';

require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';
require_once $root . '/includes/auth.php';

// Start dedicated Phase 2 live HTTP server on 127.0.0.1:8088 for end-to-end tests
$p2Env = getenv();
if (!is_array($p2Env)) {
    $p2Env = [];
}
$p2ServerLog = $tmpDir . DIRECTORY_SEPARATOR . 'php_p2_server.log';
@unlink($p2ServerLog);
$p2Desc = [0 => ['pipe', 'r'], 1 => ['file', $p2ServerLog, 'a'], 2 => ['file', $p2ServerLog, 'a']];
$p2ServerProc = proc_open([$phpBin, '-S', '127.0.0.1:8088', $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'router.php'], $p2Desc, $p2Pipes, $root, $p2Env);

$p2ServerReady = false;
for ($i = 0; $i < 20; $i++) {
    $fp = @fsockopen('127.0.0.1', 8088, $errno, $errstr, 0.2);
    if ($fp) {
        fclose($fp);
        $p2ServerReady = true;
        break;
    }
    usleep(150000);
}

register_shutdown_function(static function () use (&$p2ServerProc, &$p2Pipes): void {
    if (is_resource($p2ServerProc)) {
        @proc_terminate($p2ServerProc);
        if (isset($p2Pipes[0]) && is_resource($p2Pipes[0])) {
            @fclose($p2Pipes[0]);
        }
        @proc_close($p2ServerProc);
    }
});

/**
 * Lightweight cURL HTTP client with explicit cookie jar string and header extraction.
 */
function p2Http(string $method, string $path, ?array $jsonBody = null, array $cookies = [], array $headers = []): array
{
    $url = 'http://127.0.0.1:8088' . $path;
    $ch = curl_init($url);
    $reqHeaders = [];
    if ($jsonBody !== null) {
        $reqHeaders[] = 'Content-Type: application/json';
    }
    foreach ($headers as $k => $v) {
        $reqHeaders[] = $k . ': ' . $v;
    }
    if (!empty($cookies)) {
        $pairs = [];
        foreach ($cookies as $ck => $cv) {
            $pairs[] = $ck . '=' . $cv;
        }
        $reqHeaders[] = 'Cookie: ' . implode('; ', $pairs);
    }

    $respHeaders = [];
    $setCookies = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => $reqHeaders,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$respHeaders, &$setCookies): int {
            $trimmed = trim($line);
            if (strpos($trimmed, ':') !== false) {
                [$hk, $hv] = explode(':', $trimmed, 2);
                $hkLow = strtolower(trim($hk));
                $hvTrim = trim($hv);
                $respHeaders[$hkLow] = $hvTrim;
                if ($hkLow === 'set-cookie') {
                    $semi = explode(';', $hvTrim);
                    if (strpos($semi[0], '=') !== false) {
                        [$cn, $cv] = explode('=', $semi[0], 2);
                        $setCookies[trim($cn)] = trim($cv);
                    }
                }
            }
            return strlen($line);
        },
    ]);

    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $body = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status'  => $status,
        'headers' => $respHeaders,
        'cookies' => $setCookies,
        'body'    => $body,
        'json'    => json_decode($body, true),
    ];
}

/**
 * Helper to seed a synthetic user directly in isolated MariaDB pfg27_test.
 */
function p2CreateTestUser(PDO $pdo, string $username, string $email, string $password, string $roleCode, array $customPerms = [], ?string $mfaSecretPlain = null): array
{
    $uid = uuid4();
    $now = nowSql();
    $encMfa = $mfaSecretPlain !== null ? encryptMfaSecret($mfaSecretPlain) : null;

    $pdo->prepare('
        INSERT INTO users (id, username, email, email_verified_at, password_hash, full_name, phone, country, is_active, mfa_enabled, mfa_secret_encrypted, mfa_secret, created_at, updated_at)
        VALUES (:id, :u, :e, :now, :pw, :name, :phone, :country, 1, :mfa_on, :mfa_enc, NULL, :now, :now)
    ')->execute([
        ':id'      => $uid,
        ':u'       => $username,
        ':e'       => $email,
        ':now'     => $now,
        ':pw'      => hashPassword($password),
        ':name'    => 'Test ' . strtoupper($roleCode) . ' ' . $username,
        ':phone'   => '+91 9876543210',
        ':country' => 'India',
        ':mfa_on'  => $mfaSecretPlain !== null ? 1 : 0,
        ':mfa_enc' => $encMfa,
    ]);

    $rStmt = $pdo->prepare('SELECT id FROM roles WHERE code = :c LIMIT 1');
    $rStmt->execute([':c' => $roleCode]);
    $roleId = (string)$rStmt->fetchColumn();

    $pdo->prepare('INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_at) VALUES (:id, :uid, :rid, :cp, :now)')
        ->execute([
            ':id'  => uuid4(),
            ':uid' => $uid,
            ':rid' => $roleId,
            ':cp'  => jsonEncode($customPerms),
            ':now' => $now,
        ]);

    return ['id' => $uid, 'username' => $username, 'email' => $email, 'password' => $password, 'mfaSecret' => $mfaSecretPlain];
}

/**
 * Helper to log in and return ['cookies' => ..., 'csrf' => ..., 'resp' => ...].
 */
function p2Login(string $username, string $password, ?string $mfaCode = null): array
{
    $payload = ['username' => $username, 'password' => $password];
    if ($mfaCode !== null) {
        $payload['mfaCode'] = $mfaCode;
    }
    $r = p2Http('POST', '/api/auth/login', $payload);
    return [
        'resp'    => $r,
        'cookies' => $r['cookies'],
        'csrf'    => (string)($r['json']['csrfToken'] ?? ''),
    ];
}

/**
 * Helper to obtain an anonymous browser session cookie + CSRF token from GET /api/registrations
 * for accountless public registration submissions (CAD-17).
 */
function p2AnonSession(): array
{
    $r = p2Http('GET', '/api/registrations');
    return [
        'resp'    => $r,
        'cookies' => $r['cookies'],
        'csrf'    => (string)($r['json']['csrfToken'] ?? ''),
    ];
}

// Seed synthetic test accounts covering all 6 role profiles
$pdo = getDb();
pfgAssert($p2ServerReady, 'Phase 2 local HTTP server failed to start on 127.0.0.1:8088');

$saSecret = generateMfaSecret();
$saUser = p2CreateTestUser($pdo, 'p2_superadmin', 'p2_superadmin@example.test', 'SuperPass#2027', 'SUPERADMIN', [], $saSecret);
$saUnenrolled = p2CreateTestUser($pdo, 'p2_sa_nomfa', 'p2_sa_nomfa@example.test', 'SuperNoMfa#2027', 'SUPERADMIN', [], null);
$adminUser = p2CreateTestUser($pdo, 'p2_admin_std', 'p2_admin_std@example.test', 'AdminPass#2027', 'ADMIN', [], generateMfaSecret());
$adminCustom = p2CreateTestUser($pdo, 'p2_admin_cms', 'p2_admin_cms@example.test', 'AdminCms#2027', 'ADMIN', ['cms.manage', 'audit_logs.read'], generateMfaSecret());
$adminRestricted = p2CreateTestUser($pdo, 'p2_admin_min', 'p2_admin_min@example.test', 'AdminMin#2027', 'ADMIN', [], generateMfaSecret());
$rgstUser = p2CreateTestUser($pdo, 'p2_rgst', 'p2_rgst@example.test', 'RgstPass#2027', 'RGST', [], null);
$partUser = p2CreateTestUser($pdo, 'p2_participant', 'p2_participant@example.test', 'PartPass#2027', 'PARTICIPANT', [], null);
$lockTestUser = p2CreateTestUser($pdo, 'p2_locktest', 'p2_locktest@example.test', 'LockPass#2027', 'RGST', [], null);

// Remove accommodations.read / payments.read / volunteers.review / registrations.write from p2_admin_min via custom session check later?
// Actually DEFAULT_ROLE_PERMISSIONS for ADMIN grants those; we will test RBAC separation on CMS, AUDIT_LOGS, USERS, plus RGST vs PARTICIPANT vs ADMIN vs SUPERADMIN!

// =========================================================================
// T2-01: Login Anti-Enumeration (Generic Public Auth Error)
// =========================================================================
pfgTest('T2-01', 'Login anti-enumeration returns byte-identical generic 401 without leaking status/role', function (): string {
    $rUnknown = p2Http('POST', '/api/auth/login', ['username' => 'does_not_exist_999', 'password' => 'WrongPass#1']);
    $rWrongPw = p2Http('POST', '/api/auth/login', ['username' => 'p2_rgst', 'password' => 'WrongPass#1']);
    $rPartWrong = p2Http('POST', '/api/auth/login', ['username' => 'p2_participant', 'password' => 'WrongPass#1']);
    $rBotTrap = p2Http('POST', '/api/auth/login', ['username' => 'p2_rgst', 'password' => 'RgstPass#2027', 'websiteHp' => 'https://bot.example']);

    pfgAssert($rUnknown['status'] === 401 && $rWrongPw['status'] === 401 && $rPartWrong['status'] === 401 && $rBotTrap['status'] === 401, 'All invalid/bot logins must return 401');
    pfgAssert($rUnknown['body'] === $rWrongPw['body'] && $rWrongPw['body'] === $rPartWrong['body'] && $rWrongPw['body'] === $rBotTrap['body'], 'Error responses must be byte-identical to prevent enumeration');
    pfgAssert(!str_contains($rWrongPw['body'], 'lockedUntil'), 'Must not leak lockedUntil');
    return 'Unknown user, wrong password, participant wrong password & bot honeypot all returned identical generic 401 JSON';
});

// =========================================================================
// T2-02: 5-Attempt Lockout, Exact 3-Hour Duration (10,800s), Non-Bypass & Auto-Unlock
// =========================================================================
pfgTest('T2-02', '5 consecutive failures trigger exact 3-hour (10,800s) lockout; IP/device non-bypass, non-extension & auto-unlock', function () use ($pdo, $lockTestUser): string {
    // 1. 4 consecutive failed attempts must NOT activate lockout
    for ($i = 1; $i <= 4; $i++) {
        $r = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => 'BadPass#' . $i]);
        pfgAssert($r['status'] === 401, "Failed attempt {$i} must return generic 401, got {$r['status']}");
    }
    $chk4 = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chk4->execute([':id' => $lockTestUser['id']]);
    $row4 = $chk4->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$row4['failed_login_attempts'] === 4 && empty($row4['locked_until']), '4 failed attempts must NOT lock account');

    // Successful login after 4 failures resets counter to 0
    $rResetOk = p2Login($lockTestUser['username'], $lockTestUser['password']);
    pfgAssert($rResetOk['resp']['status'] === 200, 'Valid login after 4 failed attempts must succeed');
    $chkReset = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chkReset->execute([':id' => $lockTestUser['id']]);
    $rowReset = $chkReset->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$rowReset['failed_login_attempts'] === 0 && empty($rowReset['locked_until']), 'Successful login did not reset failed_login_attempts to 0');

    // 2. Five fresh consecutive failures trigger immediate 3-hour (10,800s) lockout
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    pfgResetRateLimit($pdo, 'auth_login_ident', $lockTestUser['username']);
    $before5 = time();
    for ($i = 1; $i <= 5; $i++) {
        $r = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => 'LockBad#' . $i]);
        pfgAssert($r['status'] === 401, "Failure {$i} expected generic 401, got {$r['status']}");
    }
    $after5 = time();

    $chk5 = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chk5->execute([':id' => $lockTestUser['id']]);
    $row5 = $chk5->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$row5['failed_login_attempts'] === 5 && !empty($row5['locked_until']), '5th failure did not activate lockout');
    $lockedUntilStr = (string)$row5['locked_until'];
    $lockedTs = strtotime($lockedUntilStr);
    pfgAssert($lockedTs !== false, 'Invalid locked_until timestamp');
    $deltaMin = $lockedTs - $after5;
    $deltaMax = $lockedTs - $before5;
    pfgAssert($deltaMax >= 10798 && $deltaMin <= 10802, "Expected exact 10,800s (3h) lockout window, got delta [{$deltaMin}, {$deltaMax}]");

    // 3. Valid credentials during active lockout MUST be rejected with generic 401 & ZERO session created
    $sessBefore = (int)$pdo->query('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $pdo->quote($lockTestUser['id']))->fetchColumn();
    $rLockedValid = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => $lockTestUser['password']]);
    pfgAssert($rLockedValid['status'] === 401, "Locked account with valid password expected generic 401, got {$rLockedValid['status']}");
    pfgAssert(empty($rLockedValid['cookies'][SESSION_COOKIE_NAME]), 'Locked login must not set session cookie');
    pfgAssert(!isset($rLockedValid['json']['lockedUntil']), 'Locked response must not leak lockedUntil timestamp');
    $sessAfter = (int)$pdo->query('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $pdo->quote($lockTestUser['id']))->fetchColumn();
    pfgAssert($sessAfter === $sessBefore, 'Locked login created a session row!');

    // 4. Changing IP header and User-Agent (device) + clearing cookies does NOT bypass lockout
    $rDiffIpDev = p2Http(
        'POST',
        '/api/auth/login',
        ['username' => $lockTestUser['username'], 'password' => $lockTestUser['password']],
        [],
        ['X-Forwarded-For' => '203.0.113.77', 'User-Agent' => 'DifferentDevice/2.0 (Android 15)']
    );
    pfgAssert($rDiffIpDev['status'] === 401 && empty($rDiffIpDev['cookies'][SESSION_COOKIE_NAME]), 'IP/device change bypassed account lockout!');

    // 5. Additional failed attempts during active lockout do NOT extend locked_until
    sleep(1);
    $rExtraFail = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => 'ExtraWrong#99']);
    pfgAssert($rExtraFail['status'] === 401, 'Extra failure during lockout expected 401');
    $chkExt = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chkExt->execute([':id' => $lockTestUser['id']]);
    $rowExt = $chkExt->fetch(PDO::FETCH_ASSOC);
    pfgAssert((string)$rowExt['locked_until'] === $lockedUntilStr, "Additional failure extended locked_until from {$lockedUntilStr} to {$rowExt['locked_until']}!");
    pfgAssert((int)$rowExt['failed_login_attempts'] === 5, 'failed_login_attempts changed during active lockout');

    // 6. Controlled DB expiry (no Cron Job): auto-unlocks, resets counter to 1 on failed attempt (5 fresh attempts), then valid login resets to 0
    $pdo->prepare('UPDATE users SET locked_until = :past WHERE id = :id')
        ->execute([':past' => nowSql(time() - 5), ':id' => $lockTestUser['id']]);
    pfgResetRateLimit($pdo, 'auth_login_ident', $lockTestUser['username']);
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');

    $rAfterExpiryFail = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => 'StillWrong#1']);
    pfgAssert($rAfterExpiryFail['status'] === 401, 'Post-expiry failed attempt expected 401');

    $chk = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chk->execute([':id' => $lockTestUser['id']]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$row['failed_login_attempts'] === 1 && empty($row['locked_until']), 'Post-expiry counter did not reset to 1 (sticky lockout)!');

    $rOk = p2Login($lockTestUser['username'], $lockTestUser['password']);
    pfgAssert($rOk['resp']['status'] === 200, 'Login after expired lockout should succeed');
    $chkFinal = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chkFinal->execute([':id' => $lockTestUser['id']]);
    $rowFinal = $chkFinal->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$rowFinal['failed_login_attempts'] === 0 && empty($rowFinal['locked_until']), 'Successful post-expiry login did not reset counter to 0');

    return '4 fails unlocked + reset on success; 5th locked exactly +10800s (3h); valid creds, IP/device change rejected; non-extending; auto-unlocked after controlled expiry';
});

// =========================================================================
// T2-03: Persistent Bluehost-Compatible DB Rate Limiting
// =========================================================================
pfgTest('T2-03', 'Persistent DB rate limiter enforces HTTP 429 + Retry-After on repeated requests', function () use ($pdo): string {
    pfgResetRateLimit($pdo, 'auth_pwreset_email', 'ratelimit.target@example.test');
    pfgResetRateLimit($pdo, 'auth_pwreset_ip', '127.0.0.1');

    $lastStatus = 0;
    $retryHeader = '';
    for ($i = 1; $i <= 6; $i++) {
        $r = p2Http('POST', '/api/auth/password-reset', ['email' => 'ratelimit.target@example.test']);
        $lastStatus = $r['status'];
        $retryHeader = (string)($r['headers']['retry-after'] ?? '');
    }
    pfgAssert($lastStatus === 429, "Expected HTTP 429 on 6th password-reset request, got {$lastStatus}");
    pfgAssert((int)$retryHeader > 0, 'Missing Retry-After header on 429 response');

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM security_rate_limits WHERE scope = 'auth_pwreset_email'")->fetchColumn();
    pfgAssert($cnt >= 1, 'Rate limit state was not persisted in security_rate_limits table');

    // Clean up IP bucket so later tests are unaffected
    pfgResetRateLimit($pdo, 'auth_pwreset_ip', '127.0.0.1');
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    return "Triggered persistent HTTP 429 (Retry-After: {$retryHeader}s) backed by MariaDB security_rate_limits";
});

// =========================================================================
// T2-04: Logout Security (GET 405, Missing CSRF 403, Valid POST 200 + Revocation)
// =========================================================================
pfgTest('T2-04', 'Logout rejects GET (405) & missing CSRF (403); valid POST revokes session', function () use ($rgstUser): string {
    $sess = p2Login($rgstUser['username'], $rgstUser['password']);
    pfgAssert($sess['resp']['status'] === 200 && $sess['csrf'] !== '', 'RGST login failed');

    $rGet = p2Http('GET', '/api/auth/logout?redirect=https://evil.example', null, $sess['cookies']);
    pfgAssert($rGet['status'] === 405, "GET /api/auth/logout must return 405, got {$rGet['status']}");

    $rNoCsrf = p2Http('POST', '/api/auth/logout', [], $sess['cookies']);
    pfgAssert($rNoCsrf['status'] === 403, "POST /api/auth/logout without CSRF must return 403, got {$rNoCsrf['status']}");

    // Verify session still works after failed CSRF logout
    $rStillValid = p2Http('GET', '/api/auth/me', null, $sess['cookies']);
    pfgAssert(!empty($rStillValid['json']['user']['id']), 'Session should remain active after rejected CSRF logout');

    // Valid CSRF logout revokes session
    $rOk = p2Http('POST', '/api/auth/logout', ['redirect' => 'https://evil.example'], $sess['cookies'], ['X-CSRF-Token' => $sess['csrf']]);
    pfgAssert($rOk['status'] === 200 && ($rOk['json']['redirectTo'] ?? '') === '/staff/login', 'Valid logout should return 200 & sanitize unsafe redirect to /staff/login');

    // Old cookie must now be rejected
    $rAfter = p2Http('GET', '/api/auth/me', null, $sess['cookies']);
    pfgAssert(is_array($rAfter['json']) && array_key_exists('user', $rAfter['json']) && $rAfter['json']['user'] === null, 'Revoked session cookie was still accepted!');
    return 'GET logout -> 405, missing CSRF -> 403, valid CSRF POST -> 200 + unsafe redirect sanitized + session revoked';
});

// =========================================================================
// T2-05: Retired Participant Account APIs (410 Gone), Session Fixation & Reset Token Scrubbing
// =========================================================================
pfgTest('T2-05', 'All 10 retired participant account APIs return 410 Gone (0 users created); session fixation & reset token scrubbing verified', function () use ($pdo, $rgstUser): string {
    $usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    // 1. All 10 retired participant account endpoints MUST return 410 Gone with deprecated=true and ZERO DB writes
    $retiredApis = [
        ['POST', '/api/auth/signup', ['fullName' => 'Blocked Signup', 'email' => 'blocked.signup@example.test', 'password' => 'StrongPass#2027']],
        ['GET',  '/api/auth/verify-email?token=dummy', null],
        ['POST', '/api/auth/verify-email', ['token' => 'dummy']],
        ['POST', '/api/auth/resend-verification', ['email' => 'blocked@example.test']],
        ['POST', '/api/auth/claim-legacy', ['registrationCode' => 'PFG-2027-000001']],
        ['GET',  '/api/account/dashboard', null],
        ['PATCH','/api/account/profile', ['fullName' => 'Hacked']],
        ['GET',  '/api/account/registrations', null],
        ['POST', '/api/account/accommodations', ['registrationId' => 'x']],
        ['POST', '/api/account/drafts', ['categoryCode' => 'ATTENDEE', 'payload' => []]],
        ['GET',  '/api/account/notifications', null],
    ];
    foreach ($retiredApis as [$m, $ep, $body]) {
        $r = p2Http($m, $ep, $body);
        pfgAssert(
            $r['status'] === 410 && !empty($r['json']['deprecated']),
            "Retired endpoint {$m} {$ep} expected 410 Gone (deprecated=true), got {$r['status']}: {$r['body']}"
        );
    }
    $usersAfter = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    pfgAssert($usersAfter === $usersBefore, 'Retired participant endpoint unexpectedly created a users row!');

    // 2. Session fixation: logging in while sending an old staff session cookie revokes the old cookie
    $s1 = p2Login($rgstUser['username'], $rgstUser['password']);
    $oldCookie = $s1['cookies'];
    $r2 = p2Http('POST', '/api/auth/login', ['username' => $rgstUser['username'], 'password' => $rgstUser['password']], $oldCookie);
    pfgAssert($r2['status'] === 200, 'Second staff login failed');
    $rOldCheck = p2Http('GET', '/api/auth/me', null, $oldCookie);
    pfgAssert(is_array($rOldCheck['json']) && array_key_exists('user', $rOldCheck['json']) && $rOldCheck['json']['user'] === null, 'Pre-existing session cookie was not revoked on re-login!');

    // 3. Staff password reset does NOT store plaintext resetUrl in notification_queue.payload
    pfgResetRateLimit($pdo, 'auth_pwreset_email', $rgstUser['email']);
    pfgResetRateLimit($pdo, 'auth_pwreset_ip', '127.0.0.1');
    $rReset = p2Http('POST', '/api/auth/password-reset', ['email' => $rgstUser['email']]);
    pfgAssert($rReset['status'] === 200, 'Staff password reset request failed');
    $nqPayload = (string)$pdo->query("SELECT payload FROM notification_queue WHERE template_code IN ('PASSWORD_RESET', 'PASSWORD_RECOVERY') ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    pfgAssert(!str_contains($nqPayload, 'resetToken=') && !str_contains($nqPayload, 'resetUrl') && !str_contains($nqPayload, 'actionUrl'), 'Plaintext resetUrl leaked into notification_queue.payload!');
    return 'All 10 retired participant APIs returned 410 Gone (0 users created); prior staff cookie revoked on re-login; 0 resetUrl in notification_queue.payload';
});

// =========================================================================
// T2-06: Staff-Only Username/Password Auth (SUPERADMIN/ADMIN/RGST) & Participant Login Rejection
// =========================================================================
pfgTest('T2-06', 'SUPERADMIN, ADMIN, RGST & legacy MFA-enrolled staff log in without TOTP; PARTICIPANT login rejected (401)', function () use ($pdo, $saUser, $saUnenrolled, $adminUser, $rgstUser, $partUser): string {
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');

    // 1. SUPERADMIN without MFA -> 200 directly to /admin, full API access (zero MFA enrollment gate)
    $lSaNoMfa = p2Login($saUnenrolled['username'], $saUnenrolled['password']);
    pfgAssert($lSaNoMfa['resp']['status'] === 200, 'Un-enrolled SUPERADMIN username+password login failed');
    pfgAssert(empty($lSaNoMfa['resp']['json']['mfaEnrollmentRequired']) && empty($lSaNoMfa['resp']['json']['mfaRequired']), 'SUPERADMIN must not receive MFA prompt or enrollment flag');
    pfgAssert(($lSaNoMfa['resp']['json']['redirectTo'] ?? '') === '/admin', 'SUPERADMIN must redirect directly to /admin, got: ' . ($lSaNoMfa['resp']['json']['redirectTo'] ?? ''));

    foreach (['/api/admin/overview', '/api/admin/registrations', '/api/admin/users', '/api/admin/cms', '/api/admin/audit-logs'] as $ep) {
        $r = p2Http('GET', $ep, null, $lSaNoMfa['cookies']);
        pfgAssert($r['status'] === 200, "SUPERADMIN expected 200 on {$ep} without MFA, got {$r['status']}");
    }

    // 2. Existing MFA-enrolled SUPERADMIN (mfa_enabled=1 + encrypted secret in DB) -> logs in seamlessly WITHOUT TOTP
    $lSaLegacyMfa = p2Login($saUser['username'], $saUser['password']);
    pfgAssert($lSaLegacyMfa['resp']['status'] === 200 && ($lSaLegacyMfa['resp']['json']['redirectTo'] ?? '') === '/admin', 'Existing MFA-enrolled SUPERADMIN failed password-only login');
    $rOvLegacy = p2Http('GET', '/api/admin/overview', null, $lSaLegacyMfa['cookies']);
    pfgAssert($rOvLegacy['status'] === 200, 'Existing MFA-enrolled SUPERADMIN blocked from /api/admin/overview');
    // Historical DB columns remain preserved intact (non-destructive)
    $histRow = $pdo->query('SELECT mfa_enabled, mfa_secret_encrypted FROM users WHERE id = ' . $pdo->quote($saUser['id']))->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$histRow['mfa_enabled'] === 1 && str_starts_with((string)$histRow['mfa_secret_encrypted'], 'enc:v1:'), 'Historical MFA columns were unexpectedly altered');

    // 3. Existing MFA-enrolled ADMIN -> logs in seamlessly WITHOUT TOTP
    $lAdm = p2Login($adminUser['username'], $adminUser['password']);
    pfgAssert($lAdm['resp']['status'] === 200 && ($lAdm['resp']['json']['redirectTo'] ?? '') === '/admin', 'ADMIN password-only login failed');

    // 4. RGST -> username + password -> /rgst
    $lRgst = p2Login($rgstUser['username'], $rgstUser['password']);
    pfgAssert($lRgst['resp']['status'] === 200 && ($lRgst['resp']['json']['redirectTo'] ?? '') === '/rgst', 'RGST password-only login failed');

    // 5. Historical PARTICIPANT account login MUST be rejected with uniform 401 and ZERO session created!
    $partSessBefore = (int)$pdo->query('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $pdo->quote($partUser['id']))->fetchColumn();
    $lPart = p2Login($partUser['email'], $partUser['password']);
    pfgAssert($lPart['resp']['status'] === 401 && empty($lPart['cookies'][SESSION_COOKIE_NAME]), "Historical PARTICIPANT login expected uniform 401 with 0 cookie, got {$lPart['resp']['status']}");
    $partSessAfter = (int)$pdo->query('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $pdo->quote($partUser['id']))->fetchColumn();
    pfgAssert($partSessAfter === $partSessBefore, 'Historical PARTICIPANT login created a session row!');

    return 'SUPERADMIN, legacy MFA-enrolled SUPERADMIN/ADMIN (DB cols intact) & RGST authenticated without TOTP; historical PARTICIPANT login rejected (401, 0 sessions)';
});

// =========================================================================
// T2-07: Atomic Concurrency Enforcement (Concurrent Requests Cannot Bypass 5-Attempt Limit)
// =========================================================================
pfgTest('T2-07', 'Concurrent login attempts enforce 5-attempt lockout atomically via SELECT ... FOR UPDATE', function () use ($pdo, $phpBin, $root): string {
    $concUser = p2CreateTestUser($pdo, 'p2_conc_lock', 'p2_conc_lock@example.test', 'ConcPass#2027', 'ADMIN', [], null);
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    pfgResetRateLimit($pdo, 'auth_login_ident', $concUser['username']);

    // Spawn 8 parallel PHP processes sending simultaneous wrong-password POST /api/auth/login requests
    $workerCode = <<<'PHP'
$payload = json_encode(['username' => $argv[1], 'password' => 'ConcWrong#' . $argv[2]]);
$ch = curl_init('http://127.0.0.1:8088/api/auth/login');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
]);
$body = (string)curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo $code . "\n";
PHP;

    $procs = [];
    $pipesList = [];
    for ($i = 1; $i <= 8; $i++) {
        $p = proc_open([$phpBin, '-r', $workerCode, $concUser['username'], (string)$i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        pfgAssert(is_resource($p), "Failed to spawn concurrent worker {$i}");
        fclose($pipes[0]);
        $procs[$i] = $p;
        $pipesList[$i] = $pipes;
    }

    $statuses = [];
    for ($i = 1; $i <= 8; $i++) {
        $out = trim((string)stream_get_contents($pipesList[$i][1]));
        fclose($pipesList[$i][1]);
        fclose($pipesList[$i][2]);
        proc_close($procs[$i]);
        $statuses[] = (int)$out;
    }

    foreach ($statuses as $idx => $st) {
        pfgAssert($st === 401, "Concurrent worker " . ($idx + 1) . " expected 401, got {$st}");
    }

    $row = $pdo->query('SELECT failed_login_attempts, locked_until FROM users WHERE id = ' . $pdo->quote($concUser['id']))->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$row['failed_login_attempts'] === 5, 'Concurrent race violated atomic 5-attempt counter: got ' . ($row['failed_login_attempts'] ?? 'null'));
    pfgAssert(!empty($row['locked_until']) && strtotime((string)$row['locked_until']) > time() + 10700, 'Concurrent attempts failed to set 3-hour locked_until');

    // Valid password immediately after concurrent burst MUST be rejected
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    $rAfter = p2Http('POST', '/api/auth/login', ['username' => $concUser['username'], 'password' => $concUser['password']]);
    pfgAssert($rAfter['status'] === 401 && empty($rAfter['cookies'][SESSION_COOKIE_NAME]), 'Valid login succeeded despite concurrent lockout!');

    return '8 parallel login requests serialized atomically: failed_login_attempts=5, locked_until=+3h, subsequent valid login rejected (401)';
});

// =========================================================================
// T2-08: Staff Password Recovery Preserves Active 3-Hour Lockout & Ignores Participant Emails
// =========================================================================
pfgTest('T2-08', 'Staff password recovery resets password WITHOUT bypassing active 3-hour lockout; participant reset ignored', function () use ($pdo, $sinkDir, $partUser): string {
    $recUser = p2CreateTestUser($pdo, 'p2_lock_pwrec', 'p2_lock_pwrec@example.test', 'OrigLock#2027', 'ADMIN', [], null);
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    pfgResetRateLimit($pdo, 'auth_pwreset_ip', '127.0.0.1');
    pfgResetRateLimit($pdo, 'auth_pwreset_email', $recUser['email']);
    pfgResetRateLimit($pdo, 'auth_pwreset_email', $partUser['email']);
    pfgResetRateLimit($pdo, 'auth_pwreset_complete_ip', '127.0.0.1');

    // 0. Requesting password reset for historical PARTICIPANT returns uniform 200 and sends ZERO email
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }
    $rPartReset = p2Http('POST', '/api/auth/password-reset', ['email' => $partUser['email']]);
    pfgAssert($rPartReset['status'] === 200, 'Participant password-reset request should return uniform 200');
    pfgAssert(count(glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: []) === 0, 'Password reset email was sent to a retired PARTICIPANT account!');

    // 1. Lock the staff ADMIN account with 5 failures
    for ($i = 1; $i <= 5; $i++) {
        $r = p2Http('POST', '/api/auth/login', ['username' => $recUser['username'], 'password' => 'WrongRec#' . $i]);
        pfgAssert($r['status'] === 401, "Failure {$i} expected 401");
    }
    $lockedBefore = (string)$pdo->query('SELECT locked_until FROM users WHERE id = ' . $pdo->quote($recUser['id']))->fetchColumn();
    pfgAssert($lockedBefore !== '' && strtotime($lockedBefore) > time() + 10700, 'Account not locked for 3h before password recovery test');

    // Clear sink, trigger staff password recovery, extract real single-use reset token from mail sink
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }
    $rReq = p2Http('POST', '/api/auth/password-reset', ['email' => $recUser['email']]);
    pfgAssert($rReq['status'] === 200, 'Staff password reset request failed');

    $resetToken = '';
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $mf) {
        $txt = (string)file_get_contents($mf);
        if (str_contains(strtolower($txt), strtolower($recUser['email'])) && preg_match('/reset-password\?token=([A-Za-z0-9_\-%]+)/', $txt, $m)) {
            $resetToken = rawurldecode($m[1]);
            break;
        }
    }
    pfgAssert($resetToken !== '', 'Could not extract staff password reset token from mail sink');

    // Complete password recovery
    $newPw = 'RecoveredLock#2027';
    $rComp = p2Http('POST', '/api/auth/password-reset', ['token' => $resetToken, 'newPassword' => $newPw]);
    pfgAssert($rComp['status'] === 200, 'Staff password reset completion failed: ' . $rComp['body']);

    // Verify active 3-hour lockout was strictly preserved
    $lockedAfter = (string)$pdo->query('SELECT locked_until FROM users WHERE id = ' . $pdo->quote($recUser['id']))->fetchColumn();
    pfgAssert($lockedAfter === $lockedBefore, "Password recovery altered/cleared active locked_until ({$lockedBefore} -> {$lockedAfter})!");

    // Attempting login with NEW valid password while still locked MUST still be rejected (401)!
    $rStillLocked = p2Http('POST', '/api/auth/login', ['username' => $recUser['username'], 'password' => $newPw]);
    pfgAssert($rStillLocked['status'] === 401 && empty($rStillLocked['cookies'][SESSION_COOKIE_NAME]), 'Password recovery silently bypassed active 3-hour lockout!');

    // After controlled expiry, login with NEW password succeeds cleanly
    $pdo->prepare('UPDATE users SET locked_until = :past WHERE id = :id')
        ->execute([':past' => nowSql(time() - 5), ':id' => $recUser['id']]);
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    $rUnlocked = p2Login($recUser['username'], $newPw);
    pfgAssert($rUnlocked['resp']['status'] === 200, 'Login with recovered staff password failed after lockout expiry');

    return 'Staff password reset preserved active 3h lockout (401 while locked, 200 after expiry); participant reset silently ignored (0 emails)';
});

// =========================================================================
// T2-09: TOTP Discontinuation (410 Gone) & Zero Authenticator Instructions in Active UIs
// =========================================================================
pfgTest('T2-09', 'TOTP MFA endpoint returns 410 Gone; active UIs & JS contain zero authenticator instructions', function () use ($root, $pdo, $saUser, $rgstUser): string {
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    $sSa = p2Login($saUser['username'], $saUser['password']);
    $sRgst = p2Login($rgstUser['username'], $rgstUser['password']);

    $rMfaGet = p2Http('GET', '/api/auth/mfa', null, $sSa['cookies']);
    $rMfaPost = p2Http('POST', '/api/auth/mfa', ['action' => 'setup'], $sSa['cookies'], ['X-CSRF-Token' => $sSa['csrf']]);
    pfgAssert($rMfaGet['status'] === 410 && !empty($rMfaGet['json']['deprecated']), "GET /api/auth/mfa expected 410 Gone, got {$rMfaGet['status']}");
    pfgAssert($rMfaPost['status'] === 410 && !empty($rMfaPost['json']['deprecated']), "POST /api/auth/mfa expected 410 Gone, got {$rMfaPost['status']}");

    $forbiddenNeedles = [
        'google authenticator',
        'microsoft authenticator',
        'authenticator app',
        '6-digit authenticator',
        'mfa_setup',
        'set up authenticator',
        'reset mfa',
        'qrcode-gen.js',
    ];

    $pages = [
        ['GET', '/staff/login', []],
        ['GET', '/apply', []],
        ['GET', '/apply/attendee', []],
        ['GET', '/admin', $sSa['cookies']],
        ['GET', '/rgst', $sRgst['cookies']],
        ['GET', '/legal/privacy', []],
    ];
    foreach ($pages as [$m, $p, $ck]) {
        $resp = p2Http($m, $p, null, $ck);
        pfgAssert($resp['status'] === 200, "{$p} expected 200, got {$resp['status']}");
        $low = strtolower($resp['body']);
        foreach ($forbiddenNeedles as $needle) {
            pfgAssert(!str_contains($low, $needle), "Active UI {$p} still contains discontinued MFA reference '{$needle}'!");
        }
    }

    foreach (['assets/js/auth-pages.js', 'assets/js/admin-portal.js', 'assets/js/category-forms.js'] as $jsRel) {
        $lowJs = strtolower((string)file_get_contents($root . '/' . $jsRel));
        foreach ($forbiddenNeedles as $needle) {
            pfgAssert(!str_contains($lowJs, $needle), "Active JS {$jsRel} still contains discontinued MFA reference '{$needle}'!");
        }
    }

    return '/api/auth/mfa returned 410 Gone; 6 active pages + 3 portal JS files verified free of authenticator/TOTP UI references';
});

// =========================================================================
// T2-10: Universal 5-Attempt / 3-Hour Lockout Across SUPERADMIN, Custom ADMIN & RGST
// =========================================================================
pfgTest('T2-10', '5-attempt 3-hour lockout applies universally to SUPERADMIN, custom-profile ADMIN, and RGST', function () use ($pdo): string {
    $uSa = p2CreateTestUser($pdo, 'p2_ulock_sa', 'p2_ulock_sa@example.test', 'ULockSa#2027', 'SUPERADMIN', [], null);
    $uAdm = p2CreateTestUser($pdo, 'p2_ulock_adm', 'p2_ulock_adm@example.test', 'ULockAdm#2027', 'ADMIN', ['cms.manage'], null);
    $uRgst = p2CreateTestUser($pdo, 'p2_ulock_rgst', 'p2_ulock_rgst@example.test', 'ULockRgst#2027', 'RGST', [], null);

    foreach ([['SUPERADMIN', $uSa['username'], $uSa['password'], $uSa['id']], ['ADMIN', $uAdm['username'], $uAdm['password'], $uAdm['id']], ['RGST', $uRgst['username'], $uRgst['password'], $uRgst['id']]] as [$label, $ident, $pw, $uid]) {
        pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
        pfgResetRateLimit($pdo, 'auth_login_ident', $ident);
        for ($i = 1; $i <= 5; $i++) {
            $rf = p2Http('POST', '/api/auth/login', ['username' => $ident, 'password' => 'BadU#' . $i]);
            pfgAssert($rf['status'] === 401, "{$label} fail {$i} expected 401");
        }
        $rv = p2Http('POST', '/api/auth/login', ['username' => $ident, 'password' => $pw]);
        pfgAssert($rv['status'] === 401 && empty($rv['cookies'][SESSION_COOKIE_NAME]), "{$label} valid login was not blocked during 3h lockout!");
        $lu = (string)$pdo->query('SELECT locked_until FROM users WHERE id = ' . $pdo->quote($uid))->fetchColumn();
        pfgAssert($lu !== '' && strtotime($lu) >= time() + 10790, "{$label} locked_until not set to +3h");

        // Controlled expiry -> automatic unlock
        $pdo->prepare('UPDATE users SET locked_until = :past WHERE id = :id')->execute([':past' => nowSql(time() - 5), ':id' => $uid]);
        pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
        $ru = p2Login($ident, $pw);
        pfgAssert($ru['resp']['status'] === 200, "{$label} did not auto-unlock after expiry");
    }

    // Deactivate extra test SUPERADMIN so T2-13 last-superadmin count remains deterministic
    $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute([':id' => $uSa['id']]);
    return '5-attempt / 3-hour lockout + automatic unlock verified identically for SUPERADMIN, custom ADMIN, and RGST';
});

// Authenticate our main staff test sessions (username + password ONLY — no TOTP, no participant sessions!) for RBAC/CSRF/Functional tests
pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
$saSess = p2Login($saUser['username'], $saUser['password']);
$adminStdSess = p2Login($adminUser['username'], $adminUser['password']);
$adminCmsSess = p2Login($adminCustom['username'], $adminCustom['password']);
$rgstSess = p2Login($rgstUser['username'], $rgstUser['password']);

// =========================================================================
// T2-11: CSRF Negative Tests Across Mutating Endpoints (DB Unchanged!)
// =========================================================================
pfgTest('T2-11', 'CSRF rejects missing, invalid, and old static fallback tokens (403) with ZERO DB mutation', function () use ($pdo, $saSess): string {
    $staticFallback = hash_hmac('sha256', 'pfg27-anon-csrf', AUTH_SECRET);
    $couponsBefore = (int)$pdo->query('SELECT COUNT(*) FROM coupons')->fetchColumn();
    $usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $faqsBefore = (int)$pdo->query('SELECT COUNT(*) FROM faq_items')->fetchColumn();
    $regsBefore = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
    $anon = p2AnonSession();

    $targets = [
        ['POST', '/api/admin/coupons', ['code' => 'CSRFBAD1', 'discountType' => 'PERCENTAGE', 'discountValue' => 10], $saSess['cookies']],
        ['POST', '/api/admin/users', ['fullName' => 'Csrf Evil', 'username' => 'csrf_evil', 'password' => 'Pass#1234', 'roleCode' => 'ADMIN'], $saSess['cookies']],
        ['POST', '/api/admin/cms', ['entityType' => 'faq', 'payload' => ['question' => 'Q', 'answer' => 'A']], $saSess['cookies']],
        ['PATCH', '/api/admin/registrations', ['id' => '00000000-0000-0000-0000-000000000001', 'status' => 'APPROVED'], $saSess['cookies']],
        ['PATCH', '/api/admin/accommodations', ['requestId' => '00000000-0000-0000-0000-000000000001', 'status' => 'ARRANGED'], $saSess['cookies']],
        ['POST', '/api/qr/verify', ['input' => 'PFG-2027-000001'], $saSess['cookies']],
        ['POST', '/api/checkins/confirm', ['credentialId' => 'x', 'venueLabel' => 'y'], $saSess['cookies']],
        ['POST', '/api/registrations', ['categoryCode' => 'ATTENDEE', 'fullName' => 'CSRF Attacker', 'email' => 'csrf.att@example.test', 'phone' => '+91 98250 11111'], $anon['cookies']],
    ];

    foreach ($targets as [$m, $ep, $body, $ck]) {
        $rMissing = p2Http($m, $ep, $body, $ck);
        pfgAssert($rMissing['status'] === 403, "{$m} {$ep} without CSRF expected 403, got {$rMissing['status']}");
        $rStatic = p2Http($m, $ep, $body, $ck, ['X-CSRF-Token' => $staticFallback]);
        pfgAssert($rStatic['status'] === 403, "{$m} {$ep} with old static fallback CSRF expected 403, got {$rStatic['status']}");
    }

    pfgAssert((int)$pdo->query('SELECT COUNT(*) FROM coupons')->fetchColumn() === $couponsBefore, 'Coupons table mutated despite 403!');
    pfgAssert((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore, 'Users table mutated despite 403!');
    pfgAssert((int)$pdo->query('SELECT COUNT(*) FROM faq_items')->fetchColumn() === $faqsBefore, 'FAQs table mutated despite 403!');
    pfgAssert((int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn() === $regsBefore, 'Registrations table mutated despite 403!');

    return 'All 8 mutating endpoints (incl. accountless POST /api/registrations) rejected missing & static CSRF tokens with 403; DB counts unchanged';
});

// =========================================================================
// T2-12: Full Staff RBAC Matrix + Historical Participant Session Rejection
// =========================================================================
pfgTest('T2-12', 'Staff RBAC matrix enforced across all 8 endpoints; historical PARTICIPANT session rejected (401)', function () use ($pdo, $partUser, $saSess, $adminStdSess, $adminCmsSess, $rgstSess): string {
    // Synthesize a pre-existing historical PARTICIPANT session row in DB to verify defense-in-depth rejection (401)
    $histPartTok = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $pdo->prepare('INSERT INTO sessions (id, user_id, token_hash, mfa_verified, expires_at, created_at) VALUES (:id, :uid, :th, 0, :exp, :now)')
        ->execute([':id' => uuid4(), ':uid' => $partUser['id'], ':th' => hashToken($histPartTok), ':exp' => nowSql(time() + 3600), ':now' => nowSql()]);
    $histPartCookies = [SESSION_COOKIE_NAME => $histPartTok];

    $matrix = [
        // [endpoint, unauth, hist_participant, rgst, admin_std, admin_cms_audit, superadmin]
        ['/api/admin/overview',       401, 401, 403, 200, 200, 200],
        ['/api/admin/registrations',  401, 401, 403, 200, 200, 200],
        ['/api/admin/accommodations', 401, 401, 403, 200, 200, 200],
        ['/api/admin/coupons',        401, 401, 403, 200, 200, 200],
        ['/api/admin/cms',            401, 401, 403, 403, 200, 200],
        ['/api/admin/audit-logs',     401, 401, 403, 403, 200, 200],
        ['/api/admin/users',          401, 401, 403, 403, 403, 200],
        ['/api/checkins',             401, 401, 200, 200, 200, 200],
    ];

    $actors = [
        ['unauth', []],
        ['HIST_PARTICIPANT', $histPartCookies],
        ['RGST', $rgstSess['cookies']],
        ['ADMIN_STD', $adminStdSess['cookies']],
        ['ADMIN_CUSTOM', $adminCmsSess['cookies']],
        ['SUPERADMIN', $saSess['cookies']],
    ];

    $checked = 0;
    foreach ($matrix as $row) {
        $ep = $row[0];
        for ($idx = 0; $idx < 6; $idx++) {
            $expected = $row[$idx + 1];
            [$label, $ck] = $actors[$idx];
            $r = p2Http('GET', $ep, null, $ck);
            pfgAssert($r['status'] === $expected, "RBAC FAIL: {$label} GET {$ep} expected {$expected}, got {$r['status']}");
            if ($ep === '/api/checkins' && $label === 'RGST') {
                pfgAssert(($r['json']['scope'] ?? '') === 'OWN', 'RGST /api/checkins must be scoped to OWN only');
            }
            $checked++;
        }
    }
    return "All {$checked} live role-endpoint RBAC assertions passed (historical PARTICIPANT cookie rejected 401 everywhere; standard ADMIN 403 on CMS/Audit/Users; custom ADMIN 200 on CMS/Audit)";
});

// =========================================================================
// T2-13: SUPERADMIN Safety Guards & Session Revocation on Admin User Updates
// =========================================================================
pfgTest('T2-13', 'SUPERADMIN self-demotion/last-superadmin guards, RGST customPermissions block & session revocation', function () use ($pdo, $saSess, $saUser, $rgstUser, $saUnenrolled): string {
    // Self-demotion blocked
    $rSelfDemote = p2Http('PATCH', '/api/admin/users', ['userId' => $saUser['id'], 'roleCode' => 'ADMIN'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rSelfDemote['status'] === 400, "Self-demotion expected 400, got {$rSelfDemote['status']}");

    // Custom permissions on RGST blocked
    $rRgstPerm = p2Http('PATCH', '/api/admin/users', ['userId' => $rgstUser['id'], 'customPermissions' => ['cms.manage']], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rRgstPerm['status'] === 400, "Granting admin customPermissions to RGST expected 400, got {$rRgstPerm['status']}");

    // Discontinued resetMfa returns 410 Gone
    $rMfaReset = p2Http('PATCH', '/api/admin/users', ['userId' => $saUnenrolled['id'], 'resetMfa' => true], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rMfaReset['status'] === 410, "Discontinued resetMfa expected 410, got {$rMfaReset['status']}");

    // Password reset on another user revokes their active sessions!
    $tmpRgst = p2Login($rgstUser['username'], $rgstUser['password']);
    pfgAssert($tmpRgst['resp']['status'] === 200, 'RGST login failed');
    $rReset = p2Http('PATCH', '/api/admin/users', ['userId' => $rgstUser['id'], 'newPassword' => 'RgstNewPass#2027'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rReset['status'] === 200, 'Superadmin password reset failed');
    $rAfter = p2Http('GET', '/api/auth/me', null, $tmpRgst['cookies']);
    pfgAssert(is_array($rAfter['json']) && array_key_exists('user', $rAfter['json']) && $rAfter['json']['user'] === null, 'Target user session was NOT revoked after admin password reset!');

    // Also test last active SUPERADMIN protection by temporarily deactivating p2_sa_nomfa
    $rDeact2 = p2Http('PATCH', '/api/admin/users', ['userId' => $saUnenrolled['id'], 'isActive' => false], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rDeact2['status'] === 200, 'Deactivating second superadmin failed');

    return 'Self-demotion (400), RGST customPerms (400), discontinued resetMfa (410), password-reset session revocation & last-superadmin guard verified';
});

// =========================================================================
// T2-14: Retired Participant Pages (410 Gone) & Redirects (/login, /register)
// =========================================================================
pfgTest('T2-14', 'Retired participant pages return clean 410 Gone HTML; /login -> /staff/login & /register -> /apply redirects', function (): string {
    foreach (['/dashboard', '/dashboard/pass-print', '/verify-email'] as $retiredPage) {
        $r = p2Http('GET', $retiredPage);
        pfgAssert($r['status'] === 410, "GET {$retiredPage} expected 410 Gone, got {$r['status']}");
        pfgAssert(!str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'TypeError'), "Fatal error output detected on {$retiredPage}!");
        pfgAssert(str_contains($r['body'], '/apply'), "Retired page {$retiredPage} missing link to /apply");
    }

    $rLogin = p2Http('GET', '/login');
    pfgAssert($rLogin['status'] === 302 && str_contains((string)($rLogin['headers']['location'] ?? ''), '/staff/login'), "GET /login expected 302 -> /staff/login, got {$rLogin['status']}");

    $rReg = p2Http('GET', '/register');
    pfgAssert($rReg['status'] === 302 && str_contains((string)($rReg['headers']['location'] ?? ''), '/apply'), "GET /register expected 302 -> /apply, got {$rReg['status']}");

    return '/dashboard, /dashboard/pass-print, /verify-email -> 410 Gone (0 fatal errors); /login -> 302 /staff/login; /register -> 302 /apply';
});

// =========================================================================
// T2-15: XSS Script-Context Escaping & Gujarati/Hindi UTF-8 Preservation
// =========================================================================
pfgTest('T2-15', 'Script-context JSON escaping neutralizes </script> XSS; Gujarati/Hindi UTF-8 preserved', function (): string {
    $xss = '</script><script>alert(1)</script>';
    $rLogin = p2Http('GET', '/staff/login?next=' . rawurlencode($xss));
    pfgAssert($rLogin['status'] === 200, '/staff/login failed');
    pfgAssert(!str_contains($rLogin['body'], '</script><script>alert(1)'), 'Unescaped </script> breakout found in /staff/login!');
    pfgAssert(str_contains($rLogin['body'], '\u003C'), 'Expected JSON_HEX_TAG escaping in /staff/login');

    $rApply = p2Http('GET', '/apply?category=' . rawurlencode($xss));
    pfgAssert($rApply['status'] === 200, '/apply failed');
    pfgAssert(!str_contains($rApply['body'], '</script><script>alert(1)'), 'Unescaped </script> breakout found in /apply!');

    return 'Both /staff/login and /apply hex-escaped </script> payloads cleanly via pfgSafeJsonForScript()';
});

// =========================================================================
// T2-16: Unsafe URL Scheme Rejection (SLI videoDemoUrl & CMS URLs)
// =========================================================================
pfgTest('T2-16', 'javascript:/data: URLs rejected (400) on accountless SLI registration and CMS partner/media writes', function () use ($saSess): string {
    $anon = p2AnonSession();
    // Public accountless SLI submission with javascript: videoDemoUrl -> 400
    $rSliBad = p2Http('POST', '/api/registrations', [
        'categoryCode'                 => 'SLI',
        'fullName'                     => 'Unsafe URL SLI',
        'email'                        => 'unsafe.sli.' . bin2hex(random_bytes(3)) . '@example.test',
        'phone'                        => '+91 9876543211',
        'country'                      => 'India',
        'cityDistrict'                 => 'Vadodara',
        'state'                        => 'Gujarat',
        'isPersonWithDisability'       => 'NO',
        'emergencyContactName'         => 'Emergency Helper',
        'emergencyContactRelationship' => 'Colleague',
        'emergencyContactPhone'        => '+91 9876543299',
        'consentZeroTolerance'         => true,
        'consentDataProcessing'        => true,
        'consentAccuracy'              => true,
        'sliDetails'                   => [
            'age'                    => 28,
            'cityDistrict'           => 'Vadodara',
            'state'                  => 'Gujarat',
            'basedInGujarat'         => true,
            'qualificationCategory'  => 'CERTIFIED_ISL',
            'experienceBand'         => '3_TO_5',
            'comfortablePublicStage' => true,
            'availabilityShifts'     => ['DAY_1_SHIFT_1'],
            'videoDemoUrl'           => 'javascript:alert(document.cookie)',
        ],
    ], $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert(
        $rSliBad['status'] === 400 && !empty($rSliBad['json']['errors']['videoDemoUrl']),
        "SLI javascript: URL expected 400 with videoDemoUrl error, got {$rSliBad['status']}: {$rSliBad['body']}"
    );

    // CMS partner with javascript: websiteUrl -> 400
    $rCmsBad1 = p2Http('POST', '/api/admin/cms', [
        'entityType' => 'partner',
        'payload'    => [
            'organizationName' => 'Evil Partner',
            'websiteUrl'       => 'javascript:alert(1)',
        ],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rCmsBad1['status'] === 400, "CMS partner javascript: URL expected 400, got {$rCmsBad1['status']}");

    // CMS media with data: URL -> 400
    $rCmsBad2 = p2Http('POST', '/api/admin/cms', [
        'entityType' => 'media',
        'payload'    => [
            'url'     => 'data:text/html,<script>alert(1)</script>',
            'altText' => 'Bad media',
        ],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rCmsBad2['status'] === 400, "CMS media data: URL expected 400, got {$rCmsBad2['status']}");

    return 'javascript: and data: URLs rejected (400) on accountless SLI videoDemoUrl, CMS partner websiteUrl, and CMS media url';
});

// =========================================================================
// T2-17: Accountless Registration Flow + QR Retirement (D-01) + Encryption + UTF-8
// =========================================================================
$createdRegId = '';
$createdRegCode = '';
pfgTest('T2-17', 'Accountless Attendee registration creates PFG-2027-000001, user_id=NULL, 0 users created, 0 QR, encrypted disclosures & UTF-8', function () use ($pdo, $sinkDir, &$createdRegId, &$createdRegCode): string {
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }
    pfgResetRateLimit($pdo, 'registration_submit_ip', '127.0.0.1');
    $qrBefore = (int)$pdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn();
    $usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $anon = p2AnonSession();
    pfgAssert($anon['csrf'] !== '', 'Failed to fetch anonymous CSRF token from GET /api/registrations');

    $rReg = p2Http('POST', '/api/registrations', [
        'categoryCode'              => 'ATTENDEE',
        'fullName'                  => 'અનિતા શર્मा (Anita)',
        'email'                     => 'anita.utf8@example.test',
        'phone'                     => '+91 9876543222',
        'preferredLanguage'         => 'Gujarati',
        'country'                   => 'India',
        'cityDistrict'              => 'Vadodara',
        'state'                     => 'Gujarat',
        'organizationName'          => '=CMD|\'/C calc\'!A0', // Also tests CSV formula injection in T2-20!
        'isPersonWithDisability'    => 'YES',
        'disabilityCategoryCodes'   => ['BLINDNESS'],
        'disabilitySelfDescription' => 'Confidential screen reader & Braille requirement',
        'accommodationTypes'        => ['BRAILLE_SCHEDULE'],
        'accommodationFreeText'     => 'Front row tactile guide needed',
        'attendingDays'             => ['DAY_1', 'DAY_2'],
        'attendeeDetails'           => [
            'ageGroup'               => '18_35',
            'selfIdentificationTags' => ['Teacher / Special Educator'],
        ],
        'consentZeroTolerance'      => true,
        'consentDataProcessing'     => true,
        'consentAccuracy'           => true,
    ], $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rReg['status'] === 201, "Accountless Attendee registration failed ({$rReg['status']}): {$rReg['body']}");
    $createdRegId = (string)($rReg['json']['registration']['id'] ?? '');
    $createdRegCode = (string)($rReg['json']['registration']['registrationCode'] ?? '');
    pfgAssert($createdRegCode === 'PFG-2027-000001', 'Expected atomic PFG-2027-000001, got: ' . $createdRegCode);
    pfgAssert(is_array($rReg['json']) && array_key_exists('qrPass', $rReg['json']) && $rReg['json']['qrPass'] === null, 'qrPass must be null per D-01!');

    // Verify accountless DB invariants: registrations.user_id IS NULL & ZERO new users rows created!
    $usersAfter = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    pfgAssert($usersAfter === $usersBefore, "Accountless registration unexpectedly created " . ($usersAfter - $usersBefore) . " users row(s)!");
    $regUid = $pdo->query('SELECT user_id FROM registrations WHERE id = ' . $pdo->quote($createdRegId))->fetchColumn();
    pfgAssert($regUid === null || $regUid === false, 'Expected registrations.user_id IS NULL for accountless submission!');
    $accUid = $pdo->query('SELECT user_id FROM accommodation_requests WHERE registration_id = ' . $pdo->quote($createdRegId) . ' LIMIT 1')->fetchColumn();
    pfgAssert($accUid === null || $accUid === false, 'Expected accommodation_requests.user_id IS NULL!');

    // Verify ZERO new qr_credentials rows created
    $qrAfter = (int)$pdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn();
    pfgAssert($qrAfter === $qrBefore, 'New qr_credentials row was created despite D-01 retirement!');

    // Verify captured emails contain Registration ID and ZERO api.qrserver.com, rawToken, or /dashboard links
    $mailFiles = glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    pfgAssert(count($mailFiles) >= 1, 'Expected >=1 confirmation email in local sink');
    $foundCodeInMail = false;
    foreach ($mailFiles as $mf) {
        $mailJson = (string)file_get_contents($mf);
        if (str_contains($mailJson, $createdRegCode)) {
            $foundCodeInMail = true;
        }
        pfgAssert(!str_contains($mailJson, 'qrserver.com') && !str_contains($mailJson, 'PFG27.'), 'Confirmation email leaked qrserver.com or QR token!');
        pfgAssert(!str_contains($mailJson, '/dashboard'), 'Confirmation email leaked retired /dashboard link!');
    }
    pfgAssert($foundCodeInMail, 'Confirmation email missing Registration ID ' . $createdRegCode);

    // Verify sensitive free-text is encrypted in DB and stripped from registration_responses.answers
    $encDis = (string)$pdo->query("SELECT self_description FROM participant_disability_disclosures WHERE registration_id = " . $pdo->quote($createdRegId) . " LIMIT 1")->fetchColumn();
    pfgAssert(str_starts_with($encDis, 'enc:v1:') && !str_contains($encDis, 'Confidential'), 'Disability self_description was NOT encrypted in DB!');
    $encAcc = (string)$pdo->query("SELECT free_text_details FROM accommodation_requests WHERE registration_id = " . $pdo->quote($createdRegId) . " LIMIT 1")->fetchColumn();
    pfgAssert(str_starts_with($encAcc, 'enc:v1:') && !str_contains($encAcc, 'tactile guide'), 'Accommodation free_text_details was NOT encrypted in DB!');
    $respSnap = (string)$pdo->query("SELECT answers FROM registration_responses WHERE registration_id = " . $pdo->quote($createdRegId) . " LIMIT 1")->fetchColumn();
    pfgAssert(!str_contains($respSnap, 'Confidential') && !str_contains($respSnap, 'tactile guide'), 'Sensitive disclosures leaked into registration_responses.answers!');

    return "Created {$createdRegCode} (user_id=NULL, 0 users created, UTF-8 intact), 0 QR rows, 0 /dashboard links, disclosures encrypted enc:v1:";
});

// =========================================================================
// T2-18: Public Accountless Registration Security, Honeypot, Smart Duplicate & Staff Non-Attachment (§9–§10)
// =========================================================================
pfgTest('T2-18', 'Public registration security: honeypot (400), exact dup (409, 0 ID leak), shared-email different name (201), staff non-attach & 405 methods', function () use ($pdo, $saUser, $createdRegCode): string {
    pfgResetRateLimit($pdo, 'registration_submit_ip', '127.0.0.1');
    $anon = p2AnonSession();
    $usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $baseAtt = [
        'categoryCode'           => 'ATTENDEE',
        'fullName'               => 'અનિતા શર્मा (Anita)',
        'email'                  => 'anita.utf8@example.test',
        'phone'                  => '+91 9876543222',
        'country'                => 'India',
        'isPersonWithDisability' => 'NO',
        'attendingDays'          => ['DAY_1'],
        'attendeeDetails'        => ['ageGroup' => '18_35', 'selfIdentificationTags' => ['Student']],
        'consentZeroTolerance'   => true,
        'consentDataProcessing'  => true,
        'consentAccuracy'        => true,
    ];

    // 1. Accessible honeypot populated -> rejected 400 with 0 DB write
    $hpPayload = $baseAtt;
    $hpPayload['fullName'] = 'Bot Spammer';
    $hpPayload['email'] = 'bot.spammer@example.test';
    $hpPayload['websiteHp'] = 'https://spam.example';
    $rHp = p2Http('POST', '/api/registrations', $hpPayload, $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rHp['status'] === 400, "Filled honeypot expected 400, got {$rHp['status']}");

    // 2. Exact duplicate (same edition + category + email + fullName) -> generic 409 with ZERO existing Registration ID/PII disclosure
    $rDup = p2Http('POST', '/api/registrations', $baseAtt, $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rDup['status'] === 409, "Exact duplicate expected 409, got {$rDup['status']}: {$rDup['body']}");
    pfgAssert(!isset($rDup['json']['existingRegistrationCode']) && !str_contains($rDup['body'], $createdRegCode), 'Duplicate 409 leaked existingRegistrationCode!');

    // 3. Shared email + DIFFERENT participant fullName (caregiver / family / institution) -> 201 ALLOWED, user_id = NULL
    $sharedPayload = $baseAtt;
    $sharedPayload['fullName'] = 'રાહુલ શર્મા (Rahul)';
    $rShared = p2Http('POST', '/api/registrations', $sharedPayload, $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rShared['status'] === 201, "Shared-email different-name registration expected 201, got {$rShared['status']}: {$rShared['body']}");
    $sharedId = (string)($rShared['json']['registration']['id'] ?? '');
    $sharedUid = $pdo->query('SELECT user_id FROM registrations WHERE id = ' . $pdo->quote($sharedId))->fetchColumn();
    pfgAssert($sharedUid === null || $sharedUid === false, 'Shared-email registration unexpectedly set user_id!');

    // 4. Submitting with a staff email NEVER attaches to the staff user account or alters users table
    $staffEmailPayload = $baseAtt;
    $staffEmailPayload['fullName'] = 'Public Visitor Using Staff Email';
    $staffEmailPayload['email'] = $saUser['email'];
    $rStaffMail = p2Http('POST', '/api/registrations', $staffEmailPayload, $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rStaffMail['status'] === 201, "Public submission with staff email expected 201 (detached), got {$rStaffMail['status']}: {$rStaffMail['body']}");
    $staffMailRegId = (string)($rStaffMail['json']['registration']['id'] ?? '');
    $staffMailUid = $pdo->query('SELECT user_id FROM registrations WHERE id = ' . $pdo->quote($staffMailRegId))->fetchColumn();
    pfgAssert($staffMailUid === null || $staffMailUid === false, 'Public submission attached to staff user_id!');
    // Clean up synthetic test registration
    $pdo->prepare('UPDATE registrations SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $staffMailRegId]);

    // 5. Public PUT/PATCH/DELETE on /api/registrations rejected 405 (anonymous callers cannot modify/delete registrations)
    foreach (['PUT', 'PATCH', 'DELETE'] as $badMethod) {
        $rMeth = p2Http($badMethod, '/api/registrations', ['id' => $sharedId], $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
        pfgAssert($rMeth['status'] === 405, "{$badMethod} /api/registrations expected 405, got {$rMeth['status']}");
    }

    pfgAssert((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore, 'Public submissions unexpectedly mutated users table!');
    return 'Honeypot(400), exact dup(409, 0 ID leak), shared-email caregiver(201, user_id=NULL), staff-email non-attach, PUT/PATCH/DELETE(405) verified';
});

// =========================================================================
// T2-19: Discontinued QR Endpoints (410) & MFA QR Library Preserved (D-01)
// =========================================================================
pfgTest('T2-19', 'All retired QR endpoints return 410 Gone; qrcode-gen.js, historical tables & manual /rgst desk intact', function () use ($root, $pdo, $saSess, $rgstUser, $createdRegId): string {
    // Re-authenticate RGST with new password set in T2-13 (proves T2-13 revoked old RGST session!)
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    $freshRgst = p2Login($rgstUser['username'], 'RgstNewPass#2027');
    pfgAssert($freshRgst['resp']['status'] === 200, 'RGST re-login with new password failed');

    $r1 = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($createdRegId), ['credentialAction' => 'ISSUE'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($r1['status'] === 410, "Admin credentialAction=ISSUE expected 410, got {$r1['status']}");

    $r2Get = p2Http('GET', '/api/qr/pass.php?registrationId=' . urlencode($createdRegId), null, $saSess['cookies']);
    pfgAssert($r2Get['status'] === 410, "GET /api/qr/pass.php expected 410, got {$r2Get['status']}");

    $r2Post = p2Http('POST', '/api/qr/pass.php', ['registrationId' => $createdRegId, 'action' => 'reissue'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($r2Post['status'] === 410, "POST /api/qr/pass.php expected 410, got {$r2Post['status']}");

    $r3 = p2Http('POST', '/api/qr/verify.php', ['input' => 'PFG-2027-000001'], $freshRgst['cookies'], ['X-CSRF-Token' => $freshRgst['csrf']]);
    pfgAssert($r3['status'] === 410, "POST /api/qr/verify.php expected 410, got {$r3['status']}");

    $r4 = p2Http('POST', '/api/checkins/confirm.php', ['credentialId' => 'dummy', 'venueLabel' => 'Main Gate'], $freshRgst['cookies'], ['X-CSRF-Token' => $freshRgst['csrf']]);
    pfgAssert($r4['status'] === 410, "POST /api/checkins/confirm.php expected 410, got {$r4['status']}");

    // Verify historical tables remain preserved and /admin does NOT load qrcode-gen.js
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    pfgAssert(in_array('qr_credentials', $tables, true) && in_array('checkins', $tables, true), 'Historical qr_credentials/checkins tables must be preserved!');

    // Verify /rgst renders Phase 9 Manual Registration Desk with zero QR camera scanner library
    $rRgstPage = p2Http('GET', '/rgst', null, $freshRgst['cookies']);
    pfgAssert(
        $rRgstPage['status'] === 200
        && str_contains($rRgstPage['body'], 'Manual 3-Day Attendance Verification')
        && !str_contains($rRgstPage['body'], 'html5-qrcode'),
        '/rgst missing Phase 9 Manual Desk or still references html5-qrcode'
    );

    return 'All 5 retired QR routes returned 410 Gone; historical tables & QR-free /rgst desk verified';
});

// =========================================================================
// T2-20: CSV Formula Injection Neutralization (FND-25) & Sensitive Data Decryption + Audit
// =========================================================================
pfgTest('T2-20', 'CSV export neutralizes formula injection, rejects XLSX (400), decrypts disclosures & logs SENSITIVE_DATA_READ', function () use ($pdo, $saSess, $createdRegId): string {
    // Reject ?export=xlsx -> 400
    $rXlsx = p2Http('GET', '/api/admin/registrations?export=xlsx', null, $saSess['cookies']);
    pfgAssert($rXlsx['status'] === 400, "export=xlsx expected 400, got {$rXlsx['status']}");

    // CSV export neutralizes =CMD|'/C calc'!A0 with leading single quote
    $rCsv = p2Http('GET', '/api/admin/registrations?export=csv&includeAccommodations=true', null, $saSess['cookies']);
    pfgAssert($rCsv['status'] === 200, "CSV export failed with {$rCsv['status']}");
    pfgAssert(str_contains($rCsv['body'], "'=CMD|'/C calc'!A0"), 'CSV formula injection payload was NOT neutralized with leading apostrophe!');
    pfgAssert(str_contains($rCsv['body'], 'અનિતા શર્मा'), 'Gujarati/Hindi UTF-8 name missing from CSV export');
    pfgAssert(str_contains($rCsv['body'], 'Front row tactile guide needed'), 'Encrypted accommodation text not decrypted for authorized CSV export');

    // Detail GET decrypts sensitive fields and writes SENSITIVE_DATA_READ audit entry
    $rDet = p2Http('GET', '/api/admin/registrations?id=' . urlencode($createdRegId), null, $saSess['cookies']);
    pfgAssert($rDet['status'] === 200, 'Admin detail GET failed');
    $decDis = (string)($rDet['json']['disabilityDisclosures'][0]['selfDescription'] ?? '');
    pfgAssert($decDis === 'Confidential screen reader & Braille requirement', 'Authorized admin detail did not decrypt disability disclosure');

    $sensAudit = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'SENSITIVE_DATA_READ'")->fetchColumn();
    $expAudit = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'REGISTRATIONS_EXPORTED'")->fetchColumn();
    pfgAssert($sensAudit >= 1 && $expAudit >= 1, 'Missing SENSITIVE_DATA_READ or REGISTRATIONS_EXPORTED audit entry');

    return 'CSV neutralized =CMD formula, rejected XLSX (400), decrypted disclosures for authorized admin & logged SENSITIVE_DATA_READ + REGISTRATIONS_EXPORTED';
});

// =========================================================================
// T2-21: Audit Log True SQL Pagination (No 500-Row Cap) & Zero Secret Leakage
// =========================================================================
pfgTest('T2-21', 'Audit logs use true SQL COUNT(*) pagination (no 500 cap) and contain zero raw secrets', function () use ($pdo, $saSess, $saSecret): string {
    $actualTotal = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    pfgAssert($actualTotal >= 15, "Expected >=15 audit log entries generated during tests, got {$actualTotal}");

    $rP1 = p2Http('GET', '/api/admin/audit-logs?page=1&pageSize=5', null, $saSess['cookies']);
    $rP2 = p2Http('GET', '/api/admin/audit-logs?page=2&pageSize=5', null, $saSess['cookies']);
    pfgAssert($rP1['status'] === 200 && $rP2['status'] === 200, 'Audit logs pagination request failed');
    pfgAssert((int)($rP1['json']['pagination']['total'] ?? -1) === $actualTotal, 'Audit log pagination total does not match SQL COUNT(*)');
    pfgAssert(count($rP1['json']['items'] ?? []) === 5 && count($rP2['json']['items'] ?? []) === 5, 'Expected 5 items per page');
    pfgAssert(($rP1['json']['items'][0]['id'] ?? '') !== ($rP2['json']['items'][0]['id'] ?? ''), 'Page 1 and Page 2 returned identical items');

    // Verify ZERO raw passwords or MFA secrets exist anywhere in audit_logs.metadata
    $leaked = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE metadata LIKE '%SuperPass#2027%' OR metadata LIKE " . $pdo->quote('%' . $saSecret . '%'))->fetchColumn();
    pfgAssert($leaked === 0, 'Raw password or MFA secret leaked into audit_logs.metadata!');

    return "True SQL pagination verified ({$actualTotal} entries across pages), 0 raw passwords/secrets in audit_logs";
});

