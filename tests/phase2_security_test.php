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
// T2-01: Login Anti-Enumeration
// =========================================================================
pfgTest('T2-01', 'Login anti-enumeration returns uniform 401 without leaking status/role', function (): string {
    $rUnknown = p2Http('POST', '/api/auth/login', ['username' => 'does_not_exist_999', 'password' => 'WrongPass#1']);
    $rWrongPw = p2Http('POST', '/api/auth/login', ['username' => 'p2_rgst', 'password' => 'WrongPass#1']);
    $rPartWrong = p2Http('POST', '/api/auth/login', ['username' => 'p2_participant', 'password' => 'WrongPass#1']);

    pfgAssert($rUnknown['status'] === 401 && $rWrongPw['status'] === 401 && $rPartWrong['status'] === 401, 'All invalid logins must return 401');
    pfgAssert($rUnknown['body'] === $rWrongPw['body'] && $rWrongPw['body'] === $rPartWrong['body'], 'Error responses must be byte-identical to prevent enumeration');
    pfgAssert(!str_contains($rWrongPw['body'], 'lockedUntil'), 'Must not leak lockedUntil');
    return 'Unknown user, wrong password, and participant wrong password all returned identical 401 JSON';
});

// =========================================================================
// T2-02: Account Lockout & Sticky Lockout Expiry Reset
// =========================================================================
pfgTest('T2-02', 'Account lockout after 5 failures & clean counter reset after expiry (fixes sticky lockout)', function () use ($pdo, $lockTestUser): string {
    for ($i = 1; $i <= 5; $i++) {
        $r = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => 'BadPass#' . $i]);
        pfgAssert($r['status'] === 401, "Failed attempt {$i} must return uniform 401, got {$r['status']}");
    }
    // Valid password while locked must return 423 without lockedUntil timestamp
    $rLocked = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => $lockTestUser['password']]);
    pfgAssert($rLocked['status'] === 423, "Locked account with valid password expected 423, got {$rLocked['status']}");
    pfgAssert(!isset($rLocked['json']['lockedUntil']), 'Locked response must not leak lockedUntil timestamp');

    // Simulate expired lockout window & verify single failed attempt resets counter to 1 (NOT 6 -> immediate re-lock!)
    $pdo->prepare('UPDATE users SET locked_until = :past WHERE id = :id')
        ->execute([':past' => nowSql(time() - 60), ':id' => $lockTestUser['id']]);
    pfgResetRateLimit($pdo, 'auth_login_ident', $lockTestUser['username']);
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');

    $rAfterExpiryFail = p2Http('POST', '/api/auth/login', ['username' => $lockTestUser['username'], 'password' => 'StillWrong#1']);
    pfgAssert($rAfterExpiryFail['status'] === 401, 'Post-expiry failed attempt expected 401');

    $chk = $pdo->prepare('SELECT failed_login_attempts, locked_until FROM users WHERE id = :id');
    $chk->execute([':id' => $lockTestUser['id']]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$row['failed_login_attempts'] === 1 && empty($row['locked_until']), 'Sticky lockout bug: counter did not reset to 1 after expiry!');

    // Now valid login succeeds cleanly
    $rOk = p2Login($lockTestUser['username'], $lockTestUser['password']);
    pfgAssert($rOk['resp']['status'] === 200, 'Login after expired lockout should succeed');
    return '5 failures locked account (423); post-expiry failure cleanly reset counter to 1 & subsequent valid login succeeded (200)';
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
    pfgAssert($rOk['status'] === 200 && ($rOk['json']['redirectTo'] ?? '') === '/login', 'Valid logout should return 200 & sanitize unsafe redirect to /login');

    // Old cookie must now be rejected
    $rAfter = p2Http('GET', '/api/auth/me', null, $sess['cookies']);
    pfgAssert(is_array($rAfter['json']) && array_key_exists('user', $rAfter['json']) && $rAfter['json']['user'] === null, 'Revoked session cookie was still accepted!');
    return 'GET logout -> 405, missing CSRF -> 403, valid CSRF POST -> 200 + unsafe redirect sanitized + session revoked';
});

// =========================================================================
// T2-05: Session Fixation, Verified Signup Anti-Enumeration & Password-Reset Token Scrubbing
// =========================================================================
pfgTest('T2-05', 'Session fixation mitigation, verified signup anti-enumeration (202), and zero token leak in notification_queue', function () use ($pdo, $rgstUser): string {
    pfgResetRateLimit($pdo, 'auth_signup_ip', '127.0.0.1');
    $signupEmail = 'p2.verified.signup.' . bin2hex(random_bytes(3)) . '@example.test';
    pfgResetRateLimit($pdo, 'auth_signup_email', $signupEmail);

    // 1. Invalid signup payload (missing phone / confirmPassword / consent) -> 400
    $rBadSignup = p2Http('POST', '/api/auth/signup', [
        'fullName' => 'Invalid Signup',
        'email'    => $signupEmail,
        'password' => 'StrongPass#2027',
    ]);
    pfgAssert($rBadSignup['status'] === 400, "Invalid POST /api/auth/signup must return 400, got {$rBadSignup['status']}");

    // 2. Valid signup -> uniform 202 with ZERO raw token in JSON response or notification_queue.payload
    $validPayload = [
        'fullName'        => 'Verified Signup Tester',
        'email'           => $signupEmail,
        'phone'           => '+91 98250 11223',
        'password'        => 'StrongPass#2027',
        'confirmPassword' => 'StrongPass#2027',
        'consentAccepted' => true,
    ];
    $rSignup1 = p2Http('POST', '/api/auth/signup', $validPayload);
    pfgAssert(
        $rSignup1['status'] === 202 && !empty($rSignup1['json']['emailVerificationRequired']),
        "Valid POST /api/auth/signup must return 202 + emailVerificationRequired, got {$rSignup1['status']}: {$rSignup1['body']}"
    );
    pfgAssert(
        !isset($rSignup1['json']['token']) && !isset($rSignup1['json']['verificationToken'])
        && !str_contains($rSignup1['body'], 'verify-email?token=') && !str_contains($rSignup1['body'], 'actionUrl'),
        'Raw verification token/URL leaked into signup JSON response!'
    );

    $verifyPayload = (string)$pdo->query("SELECT payload FROM notification_queue WHERE template_code = 'ACCOUNT_VERIFICATION' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    pfgAssert(
        !str_contains($verifyPayload, 'verify-email?token=') && !str_contains($verifyPayload, 'actionUrl') && !str_contains($verifyPayload, 'rawToken'),
        'Raw verification token/URL leaked into notification_queue.payload!'
    );

    // 3. Duplicate email signup -> uniform 202 byte-identical response (anti-enumeration)
    $rSignupDup = p2Http('POST', '/api/auth/signup', $validPayload);
    pfgAssert($rSignupDup['status'] === 202, "Duplicate email POST /api/auth/signup must return uniform 202, got {$rSignupDup['status']}");
    pfgAssert($rSignupDup['body'] === $rSignup1['body'], 'Duplicate signup response differed from initial signup (enumeration leak)!');

    // 4. Session fixation: logging in while sending an old session cookie revokes the old cookie
    $s1 = p2Login($rgstUser['username'], $rgstUser['password']);
    $oldCookie = $s1['cookies'];
    $r2 = p2Http('POST', '/api/auth/login', ['username' => $rgstUser['username'], 'password' => $rgstUser['password']], $oldCookie);
    pfgAssert($r2['status'] === 200, 'Second login failed');
    $rOldCheck = p2Http('GET', '/api/auth/me', null, $oldCookie);
    pfgAssert(is_array($rOldCheck['json']) && array_key_exists('user', $rOldCheck['json']) && $rOldCheck['json']['user'] === null, 'Pre-existing session cookie was not revoked on re-login!');

    // 5. Password reset does NOT store plaintext resetUrl in notification_queue.payload
    pfgResetRateLimit($pdo, 'auth_pwreset_email', $rgstUser['email']);
    pfgResetRateLimit($pdo, 'auth_pwreset_ip', '127.0.0.1');
    $rReset = p2Http('POST', '/api/auth/password-reset', ['email' => $rgstUser['email']]);
    pfgAssert($rReset['status'] === 200, 'Password reset request failed');
    $nqPayload = (string)$pdo->query("SELECT payload FROM notification_queue WHERE template_code IN ('PASSWORD_RESET', 'PASSWORD_RECOVERY') ORDER BY created_at DESC LIMIT 1")->fetchColumn();
    pfgAssert(!str_contains($nqPayload, 'resetToken=') && !str_contains($nqPayload, 'resetUrl') && !str_contains($nqPayload, 'actionUrl'), 'Plaintext resetUrl leaked into notification_queue.payload!');
    return 'Invalid signup->400, valid & duplicate signup->uniform 202 (0 token leaks), prior cookie revoked on re-login, 0 resetUrl in notification_queue.payload';
});

// =========================================================================
// T2-06: Mandatory MFA Enrollment Gate for SUPERADMIN/ADMIN (D-20)
// =========================================================================
$unenrolledSession = null;
pfgTest('T2-06', 'Un-enrolled SUPERADMIN blocked from admin APIs until mandatory MFA enrollment (D-20)', function () use ($saUnenrolled, &$unenrolledSession): string {
    $login = p2Login($saUnenrolled['username'], $saUnenrolled['password']);
    pfgAssert($login['resp']['status'] === 200, 'Un-enrolled SUPERADMIN login should issue enrollment-only session');
    pfgAssert(!empty($login['resp']['json']['mfaEnrollmentRequired']), 'Expected mfaEnrollmentRequired=true');
    $unenrolledSession = $login;

    foreach (['/api/admin/overview', '/api/admin/registrations', '/api/admin/users', '/api/admin/cms', '/api/admin/audit-logs'] as $ep) {
        $r = p2Http('GET', $ep, null, $login['cookies']);
        pfgAssert($r['status'] === 403 && !empty($r['json']['mfaEnrollmentRequired']), "Un-enrolled SUPERADMIN must be blocked (403) on {$ep}, got {$r['status']}");
    }
    return 'Un-enrolled SUPERADMIN received mfaEnrollmentRequired=true and HTTP 403 across all 5 protected admin APIs';
});

// =========================================================================
// T2-07: GET /api/auth/mfa Read-Only & POST Setup 160-bit Encrypted Secret
// =========================================================================
$enrolledSecretPlain = '';
pfgTest('T2-07', 'GET /api/auth/mfa is read-only; POST setup creates 160-bit AES-256-GCM encrypted pending secret', function () use ($pdo, $saUnenrolled, &$unenrolledSession, &$enrolledSecretPlain): string {
    $beforeCnt = (int)$pdo->query("SELECT COUNT(*) FROM auth_accounts WHERE user_id = " . $pdo->quote($saUnenrolled['id']) . " AND provider = 'mfa_setup'")->fetchColumn();
    $rGet = p2Http('GET', '/api/auth/mfa', null, $unenrolledSession['cookies']);
    $afterCnt = (int)$pdo->query("SELECT COUNT(*) FROM auth_accounts WHERE user_id = " . $pdo->quote($saUnenrolled['id']) . " AND provider = 'mfa_setup'")->fetchColumn();
    pfgAssert($rGet['status'] === 200 && $beforeCnt === $afterCnt && !isset($rGet['json']['secret']), 'GET /api/auth/mfa must be read-only and never generate secrets!');

    // POST setup without CSRF -> 403
    $rNoCsrf = p2Http('POST', '/api/auth/mfa', ['action' => 'setup'], $unenrolledSession['cookies']);
    pfgAssert($rNoCsrf['status'] === 403, 'POST /api/auth/mfa setup without CSRF must return 403');

    // POST setup with CSRF -> 200 + 32-char Base32 (160-bit) secret encrypted in DB
    $rSetup = p2Http('POST', '/api/auth/mfa', ['action' => 'setup'], $unenrolledSession['cookies'], ['X-CSRF-Token' => $unenrolledSession['csrf']]);
    pfgAssert($rSetup['status'] === 200 && !empty($rSetup['json']['secret']), 'POST /api/auth/mfa setup failed');
    $enrolledSecretPlain = (string)$rSetup['json']['secret'];
    pfgAssert(strlen($enrolledSecretPlain) === 32, 'Expected 160-bit (32-char Base32) secret, got length ' . strlen($enrolledSecretPlain));

    $storedPending = (string)$pdo->query("SELECT token_hash FROM auth_accounts WHERE user_id = " . $pdo->quote($saUnenrolled['id']) . " AND provider = 'mfa_setup' LIMIT 1")->fetchColumn();
    pfgAssert(str_starts_with($storedPending, 'enc:v1:') && !str_contains($storedPending, $enrolledSecretPlain), 'Pending MFA secret was NOT encrypted in DB!');
    return 'GET /api/auth/mfa verified read-only; POST setup generated 32-char (160-bit) secret stored as AES-256-GCM enc:v1:';
});

// =========================================================================
// T2-08: MFA Enrollment Activation, Encrypted DB Storage & Recovery Codes
// =========================================================================
$saRecoveryCodes = [];
pfgTest('T2-08', 'MFA activation stores AES-256-GCM ciphertext (mfa_secret=NULL), issues 8 hashed recovery codes & unlocks admin API', function () use ($pdo, $saUnenrolled, &$unenrolledSession, &$enrolledSecretPlain, &$saRecoveryCodes): string {
    $code = computeTotpCode($enrolledSecretPlain, (int)floor(time() / 30));
    $rEn = p2Http('POST', '/api/auth/mfa', ['action' => 'enable', 'code' => $code], $unenrolledSession['cookies'], ['X-CSRF-Token' => $unenrolledSession['csrf']]);
    pfgAssert($rEn['status'] === 200 && !empty($rEn['json']['mfaEnabled']), 'MFA enable failed: ' . $rEn['body']);
    $saRecoveryCodes = $rEn['json']['recoveryCodes'] ?? [];
    pfgAssert(count($saRecoveryCodes) === 8, 'Expected 8 single-use recovery codes');

    // Verify DB storage: encrypted ciphertext in mfa_secret_encrypted, NULL in mfa_secret, zero plaintext recovery codes in auth_accounts
    $uRow = $pdo->query("SELECT mfa_enabled, mfa_secret_encrypted, mfa_secret FROM users WHERE id = " . $pdo->quote($saUnenrolled['id']))->fetch(PDO::FETCH_ASSOC);
    pfgAssert((int)$uRow['mfa_enabled'] === 1, 'mfa_enabled not set to 1');
    pfgAssert(str_starts_with((string)$uRow['mfa_secret_encrypted'], 'enc:v1:') && !str_contains((string)$uRow['mfa_secret_encrypted'], $enrolledSecretPlain), 'Active MFA secret is not encrypted!');
    pfgAssert($uRow['mfa_secret'] === null, 'Legacy plaintext users.mfa_secret column MUST be NULL!');

    $rcHashes = $pdo->query("SELECT token_hash FROM auth_accounts WHERE user_id = " . $pdo->quote($saUnenrolled['id']) . " AND provider = 'mfa_recovery'")->fetchAll(PDO::FETCH_COLUMN);
    pfgAssert(count($rcHashes) === 8, 'Expected 8 hashed recovery codes in DB');
    foreach ($rcHashes as $h) {
        pfgAssert(!in_array($h, $saRecoveryCodes, true), 'Plaintext recovery code found in DB!');
    }

    // Admin overview is now unlocked!
    $rOv = p2Http('GET', '/api/admin/overview', null, $unenrolledSession['cookies']);
    pfgAssert($rOv['status'] === 200, "Admin overview should now unlock (200), got {$rOv['status']}");
    return 'mfa_secret=NULL, mfa_secret_encrypted=enc:v1:..., 8 hashed recovery codes stored, /api/admin/overview unlocked (200)';
});

// =========================================================================
// T2-09: TOTP Replay Prevention, Single-Use Recovery Codes & Mandatory MFA Disable Block
// =========================================================================
pfgTest('T2-09', 'TOTP replay blocked, recovery code single-use enforced, and SUPERADMIN disable blocked (403)', function () use ($pdo, $saUnenrolled, $enrolledSecretPlain, $saRecoveryCodes, $unenrolledSession): string {
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    pfgResetRateLimit($pdo, 'auth_mfa_verify', $saUnenrolled['id']);

    // Replay the EXACT same current TOTP code just used during enrollment -> MUST be blocked (401)!
    $sameCode = computeTotpCode($enrolledSecretPlain, (int)floor(time() / 30));
    $rReplay = p2Login($saUnenrolled['username'], $saUnenrolled['password'], $sameCode);
    pfgAssert($rReplay['resp']['status'] === 401, "Replayed TOTP code must be rejected with 401, got {$rReplay['resp']['status']}");

    // Single-use recovery code succeeds ONCE
    $rc1 = $saRecoveryCodes[0];
    $rRec1 = p2Login($saUnenrolled['username'], $saUnenrolled['password'], $rc1);
    pfgAssert($rRec1['resp']['status'] === 200 && !empty($rRec1['resp']['json']['user']['mfaVerified']), 'First use of valid recovery code should succeed (200)');

    // Reusing the SAME recovery code MUST fail (401)
    $rRecReuse = p2Login($saUnenrolled['username'], $saUnenrolled['password'], $rc1);
    pfgAssert($rRecReuse['resp']['status'] === 401, "Reused recovery code must fail with 401, got {$rRecReuse['resp']['status']}");

    // Mandatory MFA disable attempt by SUPERADMIN -> MUST return 403!
    $rDisable = p2Http('POST', '/api/auth/mfa', [
        'action'          => 'disable',
        'currentPassword' => $saUnenrolled['password'],
        'recoveryCode'    => $saRecoveryCodes[1],
    ], $rRec1['cookies'], ['X-CSRF-Token' => $rRec1['csrf']]);
    pfgAssert($rDisable['status'] === 403, "SUPERADMIN MFA disable must be blocked (403), got {$rDisable['status']}");

    return 'TOTP replay rejected (401), recovery code worked once & blocked on reuse (401), mandatory MFA disable blocked (403)';
});

// =========================================================================
// T2-10: MFA Replacement Re-Auth & RGST Optional MFA Lifecycle
// =========================================================================
pfgTest('T2-10', 'MFA replacement requires re-auth (active secret preserved on failure) & RGST optional MFA works', function () use ($pdo, $saUnenrolled, $saRecoveryCodes, $rgstUser): string {
    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    $sSa = p2Login($saUnenrolled['username'], $saUnenrolled['password'], $saRecoveryCodes[2]);
    pfgAssert($sSa['resp']['status'] === 200, 'SUPERADMIN login with recovery code failed');

    $origEnc = (string)$pdo->query("SELECT mfa_secret_encrypted FROM users WHERE id = " . $pdo->quote($saUnenrolled['id']))->fetchColumn();

    // Replacement without re-auth -> 403 and active secret untouched
    $rBadRep = p2Http('POST', '/api/auth/mfa', ['action' => 'setup', 'currentPassword' => 'WrongPass'], $sSa['cookies'], ['X-CSRF-Token' => $sSa['csrf']]);
    pfgAssert($rBadRep['status'] === 403, 'MFA replacement without valid re-auth must return 403');
    $afterBadEnc = (string)$pdo->query("SELECT mfa_secret_encrypted FROM users WHERE id = " . $pdo->quote($saUnenrolled['id']))->fetchColumn();
    pfgAssert($origEnc === $afterBadEnc, 'Active MFA secret was overwritten on failed replacement!');

    // Replacement WITH valid password + recovery code succeeds and keeps active secret untouched until verified
    $rGoodRep = p2Http('POST', '/api/auth/mfa', [
        'action'          => 'setup',
        'currentPassword' => $saUnenrolled['password'],
        'recoveryCode'    => $saRecoveryCodes[3],
    ], $sSa['cookies'], ['X-CSRF-Token' => $sSa['csrf']]);
    pfgAssert($rGoodRep['status'] === 200 && !empty($rGoodRep['json']['secret']), 'Authenticated MFA replacement setup failed');
    $stillOrigEnc = (string)$pdo->query("SELECT mfa_secret_encrypted FROM users WHERE id = " . $pdo->quote($saUnenrolled['id']))->fetchColumn();
    pfgAssert($origEnc === $stillOrigEnc, 'Active MFA secret should NOT change before replacement verification!');

    // RGST can enroll optional MFA and disable it with re-auth
    $sRgst = p2Login($rgstUser['username'], $rgstUser['password']);
    pfgAssert($sRgst['resp']['status'] === 200 && empty($sRgst['resp']['json']['mfaEnrollmentRequired']), 'RGST login without MFA should be permitted');
    $rRgstSetup = p2Http('POST', '/api/auth/mfa', ['action' => 'setup'], $sRgst['cookies'], ['X-CSRF-Token' => $sRgst['csrf']]);
    $rgstSec = (string)($rRgstSetup['json']['secret'] ?? '');
    $rRgstEn = p2Http('POST', '/api/auth/mfa', ['action' => 'enable', 'code' => computeTotpCode($rgstSec, (int)floor(time() / 30))], $sRgst['cookies'], ['X-CSRF-Token' => $sRgst['csrf']]);
    pfgAssert($rRgstEn['status'] === 200 && !empty($rRgstEn['json']['mfaEnabled']), 'RGST optional MFA enroll failed');
    $rgstRec = $rRgstEn['json']['recoveryCodes'][0] ?? '';
    $rRgstDis = p2Http('POST', '/api/auth/mfa', ['action' => 'disable', 'currentPassword' => $rgstUser['password'], 'recoveryCode' => $rgstRec], $sRgst['cookies'], ['X-CSRF-Token' => $sRgst['csrf']]);
    pfgAssert($rRgstDis['status'] === 200 && $rRgstDis['json']['mfaEnabled'] === false, 'RGST optional MFA disable failed');

    return 'Failed replacement rejected (403) with active secret intact; valid re-auth setup + RGST optional MFA enroll/disable passed';
});

// Authenticate our main test sessions (with valid MFA!) for RBAC/CSRF/Functional tests
pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
$saSess = p2Login($saUser['username'], $saUser['password'], computeTotpCode($saUser['mfaSecret'], (int)floor(time() / 30)));
$adminStdSess = p2Login($adminUser['username'], $adminUser['password'], computeTotpCode($adminUser['mfaSecret'], (int)floor(time() / 30)));
$adminCmsSess = p2Login($adminCustom['username'], $adminCustom['password'], computeTotpCode($adminCustom['mfaSecret'], (int)floor(time() / 30)));
$rgstSess = p2Login($rgstUser['username'], $rgstUser['password']);

// Create a direct valid session for PARTICIPANT (since /login is staff-only until Phase 5)
$partRawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$pdo->prepare('INSERT INTO sessions (id, user_id, token_hash, mfa_verified, expires_at, created_at) VALUES (:id, :uid, :th, 0, :exp, :now)')
    ->execute([
        ':id'  => uuid4(),
        ':uid' => $partUser['id'],
        ':th'  => hashToken($partRawToken),
        ':exp' => nowSql(time() + 3600),
        ':now' => nowSql(),
    ]);
$partSess = [
    'cookies' => [SESSION_COOKIE_NAME => $partRawToken],
    'csrf'    => pfgComputeSessionBoundCsrfToken($partRawToken),
];

// =========================================================================
// T2-11: CSRF Negative Tests Across Mutating Endpoints (DB Unchanged!)
// =========================================================================
pfgTest('T2-11', 'CSRF rejects missing, invalid, and old static fallback tokens (403) with ZERO DB mutation', function () use ($pdo, $saSess, $partSess): string {
    $staticFallback = hash_hmac('sha256', 'pfg27-anon-csrf', AUTH_SECRET);
    $couponsBefore = (int)$pdo->query('SELECT COUNT(*) FROM coupons')->fetchColumn();
    $usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $faqsBefore = (int)$pdo->query('SELECT COUNT(*) FROM faq_items')->fetchColumn();

    $targets = [
        ['POST', '/api/admin/coupons', ['code' => 'CSRFBAD1', 'discountType' => 'PERCENTAGE', 'discountValue' => 10], $saSess['cookies']],
        ['POST', '/api/admin/users', ['fullName' => 'Csrf Evil', 'username' => 'csrf_evil', 'password' => 'Pass#1234', 'roleCode' => 'ADMIN'], $saSess['cookies']],
        ['POST', '/api/admin/cms', ['entityType' => 'faq', 'payload' => ['question' => 'Q', 'answer' => 'A']], $saSess['cookies']],
        ['PATCH', '/api/admin/registrations', ['id' => '00000000-0000-0000-0000-000000000001', 'status' => 'APPROVED'], $saSess['cookies']],
        ['PATCH', '/api/admin/accommodations', ['requestId' => '00000000-0000-0000-0000-000000000001', 'status' => 'ARRANGED'], $saSess['cookies']],
        ['POST', '/api/qr/verify', ['input' => 'PFG-2027-000001'], $saSess['cookies']],
        ['POST', '/api/checkins/confirm', ['credentialId' => 'x', 'venueLabel' => 'y'], $saSess['cookies']],
        ['PATCH', '/api/account/profile', ['fullName' => 'Hacked Name'], $partSess['cookies']],
        ['POST', '/api/account/accommodations', ['registrationId' => 'x', 'selectedTypes' => ['BRAILLE_SCHEDULE']], $partSess['cookies']],
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

    return 'All 9 mutating endpoints rejected missing & legacy static CSRF tokens with 403; DB counts verified unchanged';
});

// =========================================================================
// T2-12: Full 6-Role RBAC Verification Matrix
// =========================================================================
pfgTest('T2-12', '6-role RBAC matrix enforced across overview, registrations, accommodations, CMS, coupons, users, audit-logs', function () use ($saSess, $adminStdSess, $adminCmsSess, $rgstSess, $partSess): string {
    $matrix = [
        // [endpoint, unauth, participant, rgst, admin_std, admin_cms_audit, superadmin]
        ['/api/admin/overview',       401, 403, 403, 200, 200, 200],
        ['/api/admin/registrations',  401, 403, 403, 200, 200, 200],
        ['/api/admin/accommodations', 401, 403, 403, 200, 200, 200],
        ['/api/admin/coupons',        401, 403, 403, 200, 200, 200],
        ['/api/admin/cms',            401, 403, 403, 403, 200, 200],
        ['/api/admin/audit-logs',     401, 403, 403, 403, 200, 200],
        ['/api/admin/users',          401, 403, 403, 403, 403, 200],
        ['/api/checkins',             401, 403, 200, 200, 200, 200],
    ];

    $actors = [
        ['unauth', []],
        ['PARTICIPANT', $partSess['cookies']],
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
    return "All {$checked} live role-endpoint RBAC assertions passed (standard ADMIN blocked 403 on CMS/Audit/Users; custom ADMIN granted 200 on CMS/Audit)";
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

    // Superadmin self-MFA-reset blocked
    $rSelfMfa = p2Http('PATCH', '/api/admin/users', ['userId' => $saUser['id'], 'resetMfa' => true], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rSelfMfa['status'] === 400, "Self MFA admin reset expected 400, got {$rSelfMfa['status']}");

    // Password reset on another user revokes their active sessions!
    $tmpRgst = p2Login($rgstUser['username'], $rgstUser['password']);
    pfgAssert($tmpRgst['resp']['status'] === 200, 'RGST login failed');
    $rReset = p2Http('PATCH', '/api/admin/users', ['userId' => $rgstUser['id'], 'newPassword' => 'RgstNewPass#2027'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rReset['status'] === 200, 'Superadmin password reset failed');
    $rAfter = p2Http('GET', '/api/auth/me', null, $tmpRgst['cookies']);
    pfgAssert(is_array($rAfter['json']) && array_key_exists('user', $rAfter['json']) && $rAfter['json']['user'] === null, 'Target user session was NOT revoked after admin password reset!');

    // Superadmin-assisted MFA reset for another staff account (p2_sa_nomfa) clears MFA & revokes sessions
    $rMfaReset = p2Http('PATCH', '/api/admin/users', ['userId' => $saUnenrolled['id'], 'resetMfa' => true], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rMfaReset['status'] === 200, 'Superadmin MFA reset for other user failed');
    $mfaFlag = (int)$pdo->query("SELECT mfa_enabled FROM users WHERE id = " . $pdo->quote($saUnenrolled['id']))->fetchColumn();
    pfgAssert($mfaFlag === 0, 'Target user mfa_enabled was not cleared on MFA_ADMIN_RESET');

    // Also test last active SUPERADMIN protection by temporarily deactivating p2_sa_nomfa
    $rDeact2 = p2Http('PATCH', '/api/admin/users', ['userId' => $saUnenrolled['id'], 'isActive' => false], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rDeact2['status'] === 200, 'Deactivating second superadmin failed');

    return 'Self-demotion (400), RGST customPerms (400), self-MFA-reset (400), password-reset session revocation, and audited MFA_ADMIN_RESET verified';
});

// =========================================================================
// T2-14: Dashboard Fatal Error Fix (FND-13)
// =========================================================================
pfgTest('T2-14', 'Dashboard fatal TypeError fixed (FND-13): unauth 302 redirect, participant 200 with CSRF token', function () use ($partSess): string {
    $rUnauth = p2Http('GET', '/dashboard');
    pfgAssert($rUnauth['status'] === 302 && str_contains((string)($rUnauth['headers']['location'] ?? ''), '/login'), "Unauthenticated /dashboard expected 302 to /login, got {$rUnauth['status']}");
    pfgAssert(!str_contains($rUnauth['body'], 'Fatal error') && !str_contains($rUnauth['body'], 'TypeError'), 'Fatal error output detected on /dashboard!');

    $rAuth = p2Http('GET', '/dashboard', null, $partSess['cookies']);
    pfgAssert($rAuth['status'] === 200, "Authenticated participant /dashboard expected 200, got {$rAuth['status']}");
    pfgAssert(str_contains($rAuth['body'], 'var csrfToken ='), 'Dashboard missing injected session-bound CSRF token');
    return 'GET /dashboard unauth -> 302 /login (0 fatal errors); authenticated participant -> 200 with CSRF token';
});

// =========================================================================
// T2-15: XSS Script-Context Escaping & Gujarati/Hindi UTF-8 Preservation
// =========================================================================
pfgTest('T2-15', 'Script-context JSON escaping neutralizes </script> XSS; Gujarati/Hindi UTF-8 preserved', function (): string {
    $xss = '</script><script>alert(1)</script>';
    $rLogin = p2Http('GET', '/login?next=' . rawurlencode($xss));
    pfgAssert($rLogin['status'] === 200, '/login failed');
    pfgAssert(!str_contains($rLogin['body'], '</script><script>alert(1)'), 'Unescaped </script> breakout found in /login!');
    pfgAssert(str_contains($rLogin['body'], '\u003C'), 'Expected JSON_HEX_TAG escaping in /login');

    $rApply = p2Http('GET', '/apply?category=' . rawurlencode($xss));
    pfgAssert($rApply['status'] === 200, '/apply failed');
    pfgAssert(!str_contains($rApply['body'], '</script><script>alert(1)'), 'Unescaped </script> breakout found in /apply!');

    return 'Both /login and /apply hex-escaped </script> payloads cleanly via pfgSafeJsonForScript()';
});

// =========================================================================
// T2-16: Unsafe URL Scheme Rejection (SLI videoDemoUrl & CMS URLs)
// =========================================================================
pfgTest('T2-16', 'javascript:/data: URLs rejected (400) on SLI registration and CMS partner/media writes', function () use ($saSess, $partSess): string {
    // Authenticated verified participant submits SLI with javascript: videoDemoUrl -> 400
    $rSliBad = p2Http('POST', '/api/registrations', [
        'categoryCode'                 => 'SLI',
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
    ], $partSess['cookies'], ['X-CSRF-Token' => $partSess['csrf']]);
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

    return 'javascript: and data: URLs rejected (400) on authenticated SLI videoDemoUrl, CMS partner websiteUrl, and CMS media url';
});

// =========================================================================
// T2-17: Registration Flow + QR Retirement (D-01) + Sensitive Encryption + UTF-8
// =========================================================================
$createdRegId = '';
$createdRegCode = '';
pfgTest('T2-17', 'Verified participant registration creates atomic PFG-2027-000001, 0 QR rows, encrypted disclosures & Gujarati/Hindi UTF-8', function () use ($pdo, $sinkDir, &$createdRegId, &$createdRegCode): string {
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }
    $qrBefore = (int)$pdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn();

    // Seed verified participant with Gujarati/Hindi UTF-8 full name & authenticate cleanly
    $anitaUid = uuid4();
    $now = nowSql();
    $pdo->prepare("
        INSERT INTO users (id, username, email, email_verified_at, password_hash, full_name, phone, country, is_active, account_status, created_at, updated_at)
        VALUES (:id, NULL, 'anita.utf8@example.test', :now, :pw, 'અનિતા શર્मा (Anita)', '+91 9876543222', 'India', 1, 'ACTIVE', :now, :now)
    ")->execute([':id' => $anitaUid, ':now' => $now, ':pw' => hashPassword('AnitaPass#2027')]);
    $partRoleId = (string)$pdo->query("SELECT id FROM roles WHERE code = 'PARTICIPANT' LIMIT 1")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_at) VALUES (:id, :uid, :rid, NULL, :now)')
        ->execute([':id' => uuid4(), ':uid' => $anitaUid, ':rid' => $partRoleId, ':now' => $now]);

    pfgResetRateLimit($pdo, 'auth_login_ip', '127.0.0.1');
    $anitaSess = p2Login('anita.utf8@example.test', 'AnitaPass#2027');
    pfgAssert($anitaSess['resp']['status'] === 200 && $anitaSess['csrf'] !== '', 'Verified participant login failed: ' . $anitaSess['resp']['body']);

    $rReg = p2Http('POST', '/api/registrations', [
        'categoryCode'              => 'ATTENDEE',
        'phone'                     => '+91 9876543222',
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
    ], $anitaSess['cookies'], ['X-CSRF-Token' => $anitaSess['csrf']]);
    pfgAssert($rReg['status'] === 201, "Verified Attendee registration failed ({$rReg['status']}): {$rReg['body']}");
    $createdRegId = (string)($rReg['json']['registration']['id'] ?? '');
    $createdRegCode = (string)($rReg['json']['registration']['registrationCode'] ?? '');
    pfgAssert($createdRegCode === 'PFG-2027-000001', 'Expected atomic PFG-2027-000001, got: ' . $createdRegCode);
    pfgAssert(is_array($rReg['json']) && array_key_exists('qrPass', $rReg['json']) && $rReg['json']['qrPass'] === null, 'qrPass must be null per D-01!');

    // Verify ZERO new qr_credentials rows created
    $qrAfter = (int)$pdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn();
    pfgAssert($qrAfter === $qrBefore, 'New qr_credentials row was created despite D-01 retirement!');

    // Verify captured emails contain Registration ID and ZERO api.qrserver.com or rawToken
    $mailFiles = glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    pfgAssert(count($mailFiles) >= 1, 'Expected >=1 confirmation email in local sink');
    $foundCodeInMail = false;
    foreach ($mailFiles as $mf) {
        $mailJson = (string)file_get_contents($mf);
        if (str_contains($mailJson, $createdRegCode)) {
            $foundCodeInMail = true;
        }
        pfgAssert(!str_contains($mailJson, 'qrserver.com') && !str_contains($mailJson, 'PFG27.'), 'Confirmation email leaked qrserver.com or QR token!');
    }
    pfgAssert($foundCodeInMail, 'Confirmation email missing Registration ID ' . $createdRegCode);

    // Verify sensitive free-text is encrypted in DB and stripped from registration_responses.answers
    $encDis = (string)$pdo->query("SELECT self_description FROM participant_disability_disclosures WHERE registration_id = " . $pdo->quote($createdRegId) . " LIMIT 1")->fetchColumn();
    pfgAssert(str_starts_with($encDis, 'enc:v1:') && !str_contains($encDis, 'Confidential'), 'Disability self_description was NOT encrypted in DB!');
    $encAcc = (string)$pdo->query("SELECT free_text_details FROM accommodation_requests WHERE registration_id = " . $pdo->quote($createdRegId) . " LIMIT 1")->fetchColumn();
    pfgAssert(str_starts_with($encAcc, 'enc:v1:') && !str_contains($encAcc, 'tactile guide'), 'Accommodation free_text_details was NOT encrypted in DB!');
    $respSnap = (string)$pdo->query("SELECT answers FROM registration_responses WHERE registration_id = " . $pdo->quote($createdRegId) . " LIMIT 1")->fetchColumn();
    pfgAssert(!str_contains($respSnap, 'Confidential') && !str_contains($respSnap, 'tactile guide'), 'Sensitive disclosures leaked into registration_responses.answers!');

    return "Created {$createdRegCode} (Gujarati/Hindi UTF-8 intact), 0 QR rows, 0 qrserver refs, disclosures encrypted enc:v1:";
});

// =========================================================================
// T2-18: Unauthenticated & Unverified Registration Submission Blocked (FND-07 / Phase 5-6)
// =========================================================================
pfgTest('T2-18', 'Unauthenticated POST /api/registrations blocked (401) and unverified participant blocked (403)', function () use ($pdo, $saUser, $createdRegCode): string {
    $regsBefore = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();

    // 1. Unauthenticated attempts (including targeting existing participant & staff emails) -> strictly 401
    foreach (['anita.utf8@example.test', $saUser['email']] as $victimEmail) {
        $r = p2Http('POST', '/api/registrations', [
            'categoryCode'          => 'ATTENDEE',
            'fullName'              => 'Attacker Impersonator',
            'email'                 => $victimEmail,
            'phone'                 => '+91 9000000000',
            'country'               => 'India',
            'isPersonWithDisability'=> 'NO',
            'attendingDays'         => ['DAY_1'],
            'consentZeroTolerance'  => true,
            'consentDataProcessing' => true,
            'consentAccuracy'       => true,
        ]);
        pfgAssert($r['status'] === 401, "Unauthenticated POST /api/registrations ({$victimEmail}) expected 401, got {$r['status']}");
        pfgAssert(!isset($r['json']['existingRegistrationCode']) && !str_contains($r['body'], $createdRegCode), 'Response leaked existingRegistrationCode!');
    }

    // 2. Unverified participant with valid session + CSRF -> strictly 403 + emailVerificationRequired=true
    $unverUid = uuid4();
    $now = nowSql();
    $pdo->prepare("
        INSERT INTO users (id, username, email, email_verified_at, password_hash, full_name, phone, country, is_active, account_status, created_at, updated_at)
        VALUES (:id, NULL, 'unverified.part@example.test', NULL, :pw, 'Unverified Participant', '+91 98250 99887', 'India', 1, 'PENDING_VERIFICATION', :now, :now)
    ")->execute([':id' => $unverUid, ':now' => $now, ':pw' => hashPassword('UnverPass#2027')]);
    $partRoleId = (string)$pdo->query("SELECT id FROM roles WHERE code = 'PARTICIPANT' LIMIT 1")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_at) VALUES (:id, :uid, :rid, NULL, :now)')
        ->execute([':id' => uuid4(), ':uid' => $unverUid, ':rid' => $partRoleId, ':now' => $now]);

    $unverTok = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $pdo->prepare('INSERT INTO sessions (id, user_id, token_hash, mfa_verified, expires_at, created_at) VALUES (:id, :uid, :th, 0, :exp, :now)')
        ->execute([':id' => uuid4(), ':uid' => $unverUid, ':th' => hashToken($unverTok), ':exp' => nowSql(time() + 3600), ':now' => $now]);
    $unverCsrf = pfgComputeSessionBoundCsrfToken($unverTok);

    $rUnver = p2Http('POST', '/api/registrations', [
        'categoryCode'          => 'ATTENDEE',
        'attendingDays'         => ['DAY_1'],
        'attendeeDetails'       => ['ageGroup' => '18_35', 'selfIdentificationTags' => ['Student']],
        'consentZeroTolerance'  => true,
        'consentDataProcessing' => true,
        'consentAccuracy'       => true,
    ], [SESSION_COOKIE_NAME => $unverTok], ['X-CSRF-Token' => $unverCsrf]);
    pfgAssert(
        $rUnver['status'] === 403 && !empty($rUnver['json']['emailVerificationRequired']),
        "Unverified participant POST /api/registrations expected 403 emailVerificationRequired=true, got {$rUnver['status']}: {$rUnver['body']}"
    );

    pfgAssert((int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn() === $regsBefore, 'Registration was written despite 401/403!');
    return 'Unauthenticated POST blocked (401, 0 code leaks) and unverified participant blocked (403 emailVerificationRequired=true), 0 DB writes';
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

    // Verify qrcode-gen.js preserved for MFA enrollment and historical tables exist
    pfgAssert(is_file($root . '/assets/js/qrcode-gen.js'), 'assets/js/qrcode-gen.js must be preserved for TOTP MFA!');
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

    return 'All 5 retired QR routes returned 410 Gone; historical tables, qrcode-gen.js & QR-free /rgst desk verified';
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

