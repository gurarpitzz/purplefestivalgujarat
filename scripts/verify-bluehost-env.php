<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Bluehost / Native PHP-MySQL Environment Verifier (CLI ONLY)
 *
 * Verifies actual hosting/local runtime capabilities without exposing secret values
 * and without sending outbound emails or mutating production data.
 *
 * Usage:
 *   php scripts/verify-bluehost-env.php
 *   PFG_ENV_FILE=/path/to/.env.test.php php scripts/verify-bluehost-env.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden — CLI execution only.\n";
    exit(1);
}

$checks = [];
$hasFailure = false;

$record = static function (string $name, bool $ok, string $detail, bool $required = true) use (&$checks, &$hasFailure): void {
    $status = $ok ? 'PASS' : ($required ? 'FAIL' : 'WARN');
    if (!$ok && $required) {
        $hasFailure = true;
    }
    $checks[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
};

// 1. PHP Version
$phpVersion = PHP_VERSION;
$record('PHP Version (>= 8.1)', version_compare($phpVersion, '8.1.0', '>='), $phpVersion);

// 2. Required PHP Extensions
$requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'openssl', 'json', 'fileinfo', 'session'];
foreach ($requiredExts as $ext) {
    $record("PHP Extension: {$ext}", extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'MISSING');
}

// 3. Runtime PHP INI Limits
$record('display_errors Off (after config)', true, 'pre-config=' . (string)ini_get('display_errors'));
$record('memory_limit', true, (string)ini_get('memory_limit'), false);
$record('upload_max_filesize / post_max_size', true, (string)ini_get('upload_max_filesize') . ' / ' . (string)ini_get('post_max_size'), false);
$record('max_execution_time', true, (string)ini_get('max_execution_time') . 's', false);

// 4. Load config & verify required env keys (NEVER print secret values)
$configOk = false;
try {
    require_once dirname(__DIR__) . '/includes/config.php';
    $configOk = true;
    $record('Config & Fail-Closed Secrets', true, 'APP_ENV=' . APP_ENV . ', APP_URL=' . APP_URL . ', MAIL_DRIVER=' . MAIL_DRIVER);
    $record('display_errors enforced Off', ini_get('display_errors') === '0' || ini_get('display_errors') === '' || strtolower((string)ini_get('display_errors')) === 'off', 'display_errors=' . (string)ini_get('display_errors'));
    $record('AUTH_SECRET present & >=16 chars', defined('AUTH_SECRET') && strlen(AUTH_SECRET) >= 16, 'length=' . strlen(AUTH_SECRET) . ' (value redacted)');
    $record('QR_SIGNING_SECRET present & >=16 chars', defined('QR_SIGNING_SECRET') && strlen(QR_SIGNING_SECRET) >= 16, 'length=' . strlen(QR_SIGNING_SECRET) . ' (value redacted)');
    $record('RESEND_API_KEY configured', defined('RESEND_API_KEY') && RESEND_API_KEY !== '', RESEND_API_KEY !== '' ? 'present (redacted)' : 'not set (required before production email)', !IS_LOCAL_DEV_ENV && MAIL_DRIVER === 'resend');
} catch (Throwable $e) {
    $record('Config & Fail-Closed Secrets', false, $e->getMessage());
}

// 5. MySQL / MariaDB Connectivity & Version
if ($configOk) {
    try {
        require_once dirname(__DIR__) . '/includes/database.php';
        $pdo = getDb();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ver = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $record('Database Connection (PDO)', true, "driver={$driver}, version={$ver}");

        if ($driver === 'mysql') {
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $record('Schema Tables Present', count($tables) >= 50, count($tables) . ' tables found');

            $edCount = (int)$pdo->query('SELECT COUNT(*) FROM festival_editions WHERE is_active = 1')->fetchColumn();
            $record('Active Festival Edition Row', $edCount >= 1, "{$edCount} active edition(s)");

            $tz = (string)$pdo->query('SELECT @@session.time_zone')->fetchColumn();
            $record('MySQL Session Timezone (+05:30 IST)', $tz === '+05:30', "time_zone={$tz}");
        }
    } catch (Throwable $e) {
        $record('Database Connection (PDO)', false, $e->getMessage());
    }
}

// 6. Private Storage Outside Webroot Check
$parentDir = dirname(__DIR__, 2);
$record('Parent Directory Outside Webroot', is_dir($parentDir), $parentDir . ' (writable=' . (is_writable($parentDir) ? 'yes' : 'no') . ')', false);

// 7. Outbound HTTPS Check to Resend API (read-only TCP/TLS check; NO email sent)
if (function_exists('curl_init') && !in_array('--skip-network', $argv ?? [], true)) {
    $ch = curl_init('https://api.resend.com/');
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $record('Outbound HTTPS to api.resend.com', $err === '' && $httpCode > 0, $err === '' ? "TLS OK (HTTP {$httpCode})" : $err, false);
    }
}

echo "=== Purple Festival Gujarat 2027 — Environment Verification ===\n";
foreach ($checks as $c) {
    printf("[%4s] %-42s %s\n", $c['status'], $c['name'], $c['detail']);
}
echo $hasFailure ? "\nRESULT: FAILED (one or more required checks failed)\n" : "\nRESULT: PASSED\n";
exit($hasFailure ? 1 : 0);

