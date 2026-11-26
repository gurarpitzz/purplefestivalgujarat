<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Real Native XAMPP Apache 2.4 Verification Harness (Section 15)
 * Boots an isolated XAMPP Apache httpd.exe on 127.0.0.1:8089 pointing directly at the repository
 * with AllowOverride All so Apache natively executes the real .htaccess file, mod_rewrite,
 * mod_headers, mod_authz_core, and PHP 8.2.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden\n");
}

$root = str_replace('\\', '/', dirname(__DIR__));
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$httpdBin = 'C:\\xampp\\apache\\bin\\httpd.exe';
$phpDll = 'C:/xampp/php/php8apache2_4.dll';
if (!is_file($httpdBin) || !is_file($phpDll)) {
    fwrite(STDERR, "ERROR: XAMPP Apache or PHP module not found at {$httpdBin} / {$phpDll}\n");
    exit(2);
}

$testEnvFile = str_replace('\\', '/', $tmpDir . '/test_env.php');
if (!is_file($testEnvFile)) {
    fwrite(STDERR, "ERROR: Run tests/run.php first so test_env.php and MariaDB pfg27_test exist.\n");
    exit(2);
}

$confPath = $tmpDir . DIRECTORY_SEPARATOR . 'httpd-pfg27-verify.conf';
$errLog = str_replace('\\', '/', $tmpDir . '/apache_verify_error.log');
$accLog = str_replace('\\', '/', $tmpDir . '/apache_verify_access.log');
$pidFile = str_replace('\\', '/', $tmpDir . '/apache_verify.pid');
@unlink($errLog);
@unlink($accLog);
@unlink($pidFile);

$conf = <<<CONF
ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8089
ServerName 127.0.0.1:8089
PidFile "{$pidFile}"
ErrorLog "{$errLog}"
LogLevel warn

LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule env_module modules/mod_env.so
LoadModule headers_module modules/mod_headers.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule php_module "{$phpDll}"

PHPIniDir "C:/xampp/php"
TypesConfig conf/mime.types
AddType application/x-httpd-php .php
DirectoryIndex index.php index.html

CustomLog "{$accLog}" common

SetEnv PFG_ENV_FILE "{$testEnvFile}"

DocumentRoot "{$root}"
<Directory "{$root}">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
CONF;

file_put_contents($confPath, $conf);

$apacheStd = $tmpDir . DIRECTORY_SEPARATOR . 'apache_proc.log';
@unlink($apacheStd);
$desc = [
    0 => ['pipe', 'r'],
    1 => ['file', $apacheStd, 'a'],
    2 => ['file', $apacheStd, 'a'],
];
$proc = proc_open([$httpdBin, '-f', str_replace('\\', '/', $confPath), '-X'], $desc, $pipes, 'C:\\xampp\\apache');
if (!is_resource($proc)) {
    fwrite(STDERR, "ERROR: Failed to launch XAMPP Apache httpd.exe\n");
    exit(2);
}

$ready = false;
for ($i = 0; $i < 25; $i++) {
    $fp = @fsockopen('127.0.0.1', 8089, $errno, $errstr, 0.2);
    if ($fp) {
        fclose($fp);
        $ready = true;
        break;
    }
    usleep(200000);
}

$results = [];
$passed = 0;
$failed = 0;

$apacheGet = static function (string $path): array {
    $ch = curl_init('http://127.0.0.1:8089' . $path);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            $t = trim($line);
            if (strpos($t, ':') !== false) {
                [$k, $v] = explode(':', $t, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    $body = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'headers' => $headers, 'body' => $body];
};

$record = static function (string $id, string $name, bool $ok, string $evidence) use (&$results, &$passed, &$failed): void {
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
    $results[] = ['id' => $id, 'name' => $name, 'status' => $ok ? 'PASS' : 'FAIL', 'evidence' => $evidence];
    printf("[%s] %-7s %s — %s\n", $ok ? 'PASS' : 'FAIL', $id, $name, $evidence);
};

try {
    if (!$ready) {
        $errTxt = is_file($errLog) ? trim((string)file_get_contents($errLog)) : '';
        $procTxt = is_file($apacheStd) ? trim((string)file_get_contents($apacheStd)) : '';
        throw new RuntimeException("Apache did not start on 127.0.0.1:8089. errLog={$errTxt} proc={$procTxt}");
    }

    // 1. Public & clean extensionless rewrite routes
    foreach ([
        ['A-01', 'GET / (Homepage)', '/', 200],
        ['A-02', 'GET /apply (Extensionless rewrite)', '/apply', 200],
        ['A-03', 'GET /login (Extensionless rewrite)', '/login', 200],
        ['A-04', 'GET /programme (Extensionless rewrite)', '/programme', 200],
        ['A-05', 'GET /accessibility (Extensionless rewrite)', '/accessibility', 200],
        ['A-06', 'GET /assets/css/app.css (Static CSS)', '/assets/css/app.css', 200],
        ['A-07', 'GET /assets/js/qrcode-gen.js (MFA QR library)', '/assets/js/qrcode-gen.js', 200],
        ['A-08', 'GET /api/auth/me (API rewrite)', '/api/auth/me', 200],
        ['A-09', 'GET /api/registrations (API rewrite + MariaDB)', '/api/registrations', 200],
        ['A-10', 'GET /admin unauth redirect', '/admin', 302],
        ['A-11', 'GET /rgst unauth redirect', '/rgst', 302],
        ['A-12', 'GET /dashboard unauth redirect (FND-13)', '/dashboard', 302],
        ['A-13', 'GET /api/admin/overview unauth 401', '/api/admin/overview', 401],
        ['A-14', 'GET /api/admin/cms unauth 401', '/api/admin/cms', 401],
        ['A-15', 'GET /README.md (.htaccess 403)', '/README.md', 403],
        ['A-16', 'GET /docs/WEBSITE_AUDIT.md (.htaccess 403)', '/docs/WEBSITE_AUDIT.md', 403],
        ['A-17', 'GET /audit/MASTER_CODEBASE_AUDIT.md (.htaccess 403)', '/audit/MASTER_CODEBASE_AUDIT.md', 403],
        ['A-18', 'GET /project-docs/PROJECT_BASELINE.md (.htaccess 403)', '/project-docs/PROJECT_BASELINE.md', 403],
        ['A-19', 'GET /database/schema.sql (.htaccess 403)', '/database/schema.sql', 403],
        ['A-20', 'GET /includes/config.php (.htaccess 403)', '/includes/config.php', 403],
        ['A-21', 'GET /scripts/verify-bluehost-env.php (.htaccess 403)', '/scripts/verify-bluehost-env.php', 403],
        ['A-22', 'GET /tests/run.php (.htaccess 403)', '/tests/run.php', 403],
        ['A-23', 'GET /.user.ini (.htaccess 403)', '/.user.ini', 403],
        ['A-24', 'GET /.cpanel.yml (.htaccess 403)', '/.cpanel.yml', 403],
        ['A-25', 'GET /nonexistent-pfg27-route (clean 404)', '/nonexistent-pfg27-route', 404],
    ] as [$id, $label, $path, $exp]) {
        $r = $apacheGet($path);
        $ok = ($r['status'] === $exp);
        if ($path === '/api/registrations' && $ok) {
            $j = json_decode($r['body'], true);
            $ok = is_array($j) && ($j['edition']['slug'] ?? '') === 'gujarat-2027' && count($j['categories'] ?? []) === 8;
        }
        $serverHdr = $r['headers']['server'] ?? 'Apache';
        $record($id, $label, $ok, "HTTP {$r['status']} (expected {$exp}) | Server: {$serverHdr}");
    }

    // 2. Real Apache .htaccess Security Headers check
    $home = $apacheGet('/');
    $hdrs = $home['headers'];
    $hasNosniff = strtolower((string)($hdrs['x-content-type-options'] ?? '')) === 'nosniff';
    $hasFrame = strtoupper((string)($hdrs['x-frame-options'] ?? '')) === 'SAMEORIGIN';
    $hasRef = !empty($hdrs['referrer-policy']);
    $hasPerm = !empty($hdrs['permissions-policy']);
    $hasCsp = !empty($hdrs['content-security-policy']);
    $record(
        'A-26',
        'Apache .htaccess security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, CSP)',
        $hasNosniff && $hasFrame && $hasRef && $hasPerm && $hasCsp,
        sprintf(
            'nosniff=%s frame=%s referrer=%s permissions=%s csp=%s',
            $hasNosniff ? 'ok' : 'MISSING',
            $hasFrame ? 'ok' : 'MISSING',
            $hasRef ? 'ok' : 'MISSING',
            $hasPerm ? 'ok' : 'MISSING',
            $hasCsp ? 'ok' : 'MISSING'
        )
    );
} finally {
    if (is_resource($proc)) {
        @proc_terminate($proc);
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        @proc_close($proc);
    }
    @unlink($confPath);
}

echo "\n============================================================\n";
printf("APACHE VERIFICATION — TOTAL: %d | PASSED: %d | FAILED: %d\n", $passed + $failed, $passed, $failed);
echo "============================================================\n";

file_put_contents(
    $tmpDir . DIRECTORY_SEPARATOR . 'apache_results.json',
    json_encode(['passed' => $passed, 'failed' => $failed, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

exit($failed > 0 ? 1 : 0);

