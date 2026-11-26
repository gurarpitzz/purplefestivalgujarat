<?php
declare(strict_types=1);

/**
 * Phase 1 Automated Containment, Isolation & Native PHP/MySQL Verification Suite
 */

$root = dirname(__DIR__);
$phpBin = PHP_BINARY;
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$sinkDir = $tmpDir . DIRECTORY_SEPARATOR . 'mail-sink';

// Helper to run an isolated PHP child snippet with controlled env vars
function runPhpSnippet(string $phpBin, string $code, array $envOverrides = []): array
{
    $tmpScript = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'snippet_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($tmpScript, "<?php\ndeclare(strict_types=1);\n" . $code);

    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    foreach ($envOverrides as $k => $v) {
        if ($v === null) {
            unset($env[$k]);
        } else {
            $env[$k] = (string)$v;
        }
    }

    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open([$phpBin, $tmpScript], $desc, $pipes, dirname(__DIR__), $env);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    @unlink($tmpScript);

    return ['exitCode' => $exitCode, 'stdout' => trim((string)$stdout), 'stderr' => trim((string)$stderr)];
}

// T1-01: PHP syntax lint across ALL project PHP files
pfgTest('T1-01', 'PHP syntax lint across 100% of project .php files', function () use ($root, $phpBin): string {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $checked = 0;
    $errors = [];
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (
            str_contains($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . '.next' . DIRECTORY_SEPARATOR)
            || str_contains($path, DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR)
        ) {
            continue;
        }
        $out = [];
        $code = 0;
        exec(escapeshellarg($phpBin) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        $checked++;
        if ($code !== 0) {
            $errors[] = $path . ': ' . implode(' ', $out);
        }
    }
    pfgAssert($checked >= 45, "Expected >=45 PHP files, found {$checked}");
    pfgAssert(empty($errors), 'Lint errors: ' . implode('; ', $errors));
    return "Linted {$checked} PHP files with PHP " . PHP_VERSION . " — 0 syntax errors";
});

// T1-02: Isolated MariaDB pfg27_test fresh initialization (57 tables + active edition)
pfgTest('T1-02', 'Isolated MariaDB pfg27_test clean schema & seed initialization', function () use ($root): string {
    require_once $root . '/includes/config.php';
    require_once $root . '/includes/database.php';

    // Reset pfg27_test tables cleanly (isolated test DB ONLY!)
    pfgAssert(DB_NAME === 'pfg27_test', 'Safety abort: DB_NAME must be pfg27_test');
    $bootstrapPdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=pfg27_test;charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $bootstrapPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $existingTables = $bootstrapPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existingTables as $tbl) {
        $bootstrapPdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string)$tbl) . '`');
    }
    $bootstrapPdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo = getDb(true);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $edCount = (int)$pdo->query('SELECT COUNT(*) FROM festival_editions WHERE is_active = 1')->fetchColumn();
    $catCount = (int)$pdo->query('SELECT COUNT(*) FROM registration_categories WHERE is_active = 1')->fetchColumn();
    $partnerCount = (int)$pdo->query('SELECT COUNT(*) FROM partners WHERE status = \'PUBLISHED\'')->fetchColumn();

    pfgAssert(count($tables) === 57, 'Expected 57 tables, got ' . count($tables));
    pfgAssert($edCount === 1, 'Expected 1 active festival edition, got ' . $edCount);
    pfgAssert($catCount === 8, 'Expected 8 active categories, got ' . $catCount);
    pfgAssert($partnerCount === 11, 'Expected 11 seeded partners, got ' . $partnerCount);

    return "Fresh MariaDB pfg27_test initialized: 57 tables, 1 active edition, 8 categories, 11 partners";
});

// T1-03: Fail-closed when AUTH_SECRET / QR_SIGNING_SECRET missing
pfgTest('T1-03', 'Fail-closed rejection when AUTH_SECRET is missing', function () use ($root, $phpBin): string {
    $badEnv = __DIR__ . '/tmp/bad_env_missing.php';
    file_put_contents($badEnv, "<?php return ['APP_ENV' => 'production', 'APP_URL' => 'https://example.com', 'DB_NAME' => 'pfg27_test', 'DB_USER' => 'root'];");
    $res = runPhpSnippet($phpBin, "try { require '" . addslashes($root . '/includes/config.php') . "'; echo 'ALLOWED'; } catch (RuntimeException \$e) { echo 'BLOCKED:' . \$e->getMessage(); }", [
        'PFG_ENV_FILE' => $badEnv,
        'AUTH_SECRET'  => null,
    ]);
    @unlink($badEnv);
    pfgAssert(str_starts_with($res['stdout'], 'BLOCKED:'), 'Expected BLOCKED, got: ' . $res['stdout'] . ' ' . $res['stderr']);
    return $res['stdout'];
});

// T1-04: Fail-closed when hardcoded placeholder secret is used
pfgTest('T1-04', 'Fail-closed rejection of known hardcoded placeholder AUTH_SECRET', function () use ($root, $phpBin): string {
    $badEnv = __DIR__ . '/tmp/bad_env_placeholder.php';
    file_put_contents($badEnv, "<?php return ['APP_ENV' => 'production', 'APP_URL' => 'https://example.com', 'AUTH_SECRET' => 'pfg2027-prod-auth-secret-change-in-env', 'QR_SIGNING_SECRET' => '123456789012345678901234'];");
    $res = runPhpSnippet($phpBin, "try { require '" . addslashes($root . '/includes/config.php') . "'; echo 'ALLOWED'; } catch (RuntimeException \$e) { echo 'BLOCKED:' . \$e->getMessage(); }", [
        'PFG_ENV_FILE' => $badEnv,
    ]);
    @unlink($badEnv);
    pfgAssert(str_starts_with($res['stdout'], 'BLOCKED:'), 'Expected BLOCKED, got: ' . $res['stdout']);
    return $res['stdout'];
});

// T1-05: Silent SQLite fallback blocked in production/staging
pfgTest('T1-05', 'No silent SQLite fallback in production when DB credentials missing', function () use ($root, $phpBin): string {
    $badEnv = __DIR__ . '/tmp/bad_env_nodb.php';
    file_put_contents($badEnv, "<?php return ['APP_ENV' => 'production', 'APP_URL' => 'https://example.com', 'AUTH_SECRET' => str_repeat('a', 32), 'QR_SIGNING_SECRET' => str_repeat('b', 32)];");
    $res = runPhpSnippet($phpBin, "require '" . addslashes($root . '/includes/database.php') . "'; try { getDb(); echo 'ALLOWED'; } catch (RuntimeException \$e) { echo 'BLOCKED:' . \$e->getMessage(); }", [
        'PFG_ENV_FILE' => $badEnv,
    ]);
    @unlink($badEnv);
    pfgAssert(str_starts_with($res['stdout'], 'BLOCKED:'), 'Expected BLOCKED, got: ' . $res['stdout'] . ' ' . $res['stderr']);
    return $res['stdout'];
});

// T1-06: Host Header Injection blocked in production when APP_URL unset
pfgTest('T1-06', 'Host Header Injection blocked when APP_URL unset in production', function () use ($root, $phpBin): string {
    $badEnv = __DIR__ . '/tmp/bad_env_noappurl.php';
    file_put_contents($badEnv, "<?php return ['APP_ENV' => 'production', 'AUTH_SECRET' => str_repeat('a', 32), 'QR_SIGNING_SECRET' => str_repeat('b', 32)];");
    $res = runPhpSnippet($phpBin, "\$_SERVER['HTTP_HOST'] = 'evil.attacker.example'; try { require '" . addslashes($root . '/includes/config.php') . "'; echo 'ALLOWED:' . APP_URL; } catch (RuntimeException \$e) { echo 'BLOCKED:' . \$e->getMessage(); }", [
        'PFG_ENV_FILE' => $badEnv,
    ]);
    @unlink($badEnv);
    pfgAssert(str_starts_with($res['stdout'], 'BLOCKED:'), 'Expected BLOCKED, got: ' . $res['stdout']);
    return $res['stdout'];
});

// T1-07: Safe local email sink (MAIL_DRIVER=file) captures email without external calls & scrubs rawToken
pfgTest('T1-07', 'Safe local email sink captures email and scrubs secrets', function () use ($root, $sinkDir): string {
    require_once $root . '/includes/mailer.php';
    require_once $root . '/includes/database.php';
    $pdo = getDb();

    // Clear old captures
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }

    $res = sendFestivalEmail($pdo, [
        'to'           => 'synthetic.tester@example.test',
        'subject'      => 'Phase 1 Sink Verification',
        'html'         => '<p>Hello synthetic tester</p>',
        'text'         => 'Hello synthetic tester',
        'templateCode' => 'PHASE1_TEST',
        'metadata'     => ['rawToken' => 'PFG27.secret.token', 'resetUrl' => 'https://secret/reset', 'safeKey' => 'ok'],
    ]);

    pfgAssert($res['sent'] === true && $res['provider'] === 'file_sink' && $res['status'] === 'CAPTURED_LOCAL', 'Unexpected mailer response: ' . json_encode($res));
    $files = glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    pfgAssert(count($files) === 1, 'Expected 1 captured email JSON, found ' . count($files));
    $captured = json_decode((string)file_get_contents($files[0]), true);
    pfgAssert(is_array($captured) && ($captured['to'] ?? '') === 'synthetic.tester@example.test', 'Invalid captured envelope');
    pfgAssert(!isset($captured['metadata']['rawToken']) && !isset($captured['metadata']['resetUrl']), 'Secrets were not scrubbed from mail sink metadata');
    return 'Captured locally -> ' . basename($files[0]) . ' (provider=file_sink, rawToken/resetUrl scrubbed)';
});

// T1-08: MAIL_DRIVER=disabled never calls Resend or PHP mail()
pfgTest('T1-08', 'MAIL_DRIVER=disabled blocks all outbound email attempts', function () use ($root, $phpBin): string {
    $envPath = __DIR__ . '/tmp/disabled_mail_env.php';
    file_put_contents($envPath, "<?php return ['APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1:8088', 'DB_NAME' => 'pfg27_test', 'DB_USER' => 'root', 'AUTH_SECRET' => str_repeat('a', 32), 'QR_SIGNING_SECRET' => str_repeat('b', 32), 'MAIL_DRIVER' => 'disabled'];");
    $res = runPhpSnippet($phpBin, "require '" . addslashes($root . '/includes/database.php') . "'; require '" . addslashes($root . '/includes/mailer.php') . "'; \$r = sendFestivalEmail(getDb(), ['to'=>'a@example.test','subject'=>'Test','html'=>'<p>x</p>']); echo json_encode(\$r);", [
        'PFG_ENV_FILE' => $envPath,
    ]);
    @unlink($envPath);
    $decoded = json_decode($res['stdout'], true);
    pfgAssert(is_array($decoded) && $decoded['sent'] === false && $decoded['provider'] === 'disabled', 'Unexpected output: ' . $res['stdout']);
    return 'provider=disabled, sent=false, status=' . $decoded['status'];
});

// T1-09: .htaccess Bluehost-safe containment verification
pfgTest('T1-09', '.htaccess containment rules & Bluehost CGI/FPM safety check', function () use ($root): string {
    $ht = (string)file_get_contents($root . '/.htaccess');
    pfgAssert(str_contains($ht, 'database|includes|docs|audit|project-docs|tests|scripts|data|node_modules'), 'Missing blocked directories rule');
    pfgAssert(str_contains($ht, 'README\\.md') && str_contains($ht, 'md|yml|yaml'), 'Missing markdown/yaml FilesMatch block');
    pfgAssert(str_contains($ht, 'Strict-Transport-Security') && str_contains($ht, 'Content-Security-Policy') && str_contains($ht, 'Permissions-Policy'), 'Missing security headers');
    pfgAssert(str_contains($ht, 'RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]'), 'Missing HTTPS redirect');
    pfgAssert(!preg_match('/^\s*php_(flag|value)\b/mi', $ht), 'Bare php_flag/php_value found (crashes Bluehost CGI/FPM/LSAPI!)');
    pfgAssert(is_file($root . '/.user.ini'), 'Missing .user.ini for Bluehost PHP hardening');
    return 'All internal dirs/md/yaml blocked, HTTPS redirect + HSTS/CSP/Permissions-Policy present, 0 bare php_flag directives, .user.ini present';
});

// T1-10: Live native PHP HTTP server smoke test against MariaDB pfg27_test
pfgTest('T1-10', 'Live native PHP 8.2 + MariaDB HTTP routing & containment smoke test', function () use ($root, $phpBin): string {
    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([$phpBin, '-S', '127.0.0.1:8088', $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'router.php'], $desc, $pipes, $root, $env);
    pfgAssert(is_resource($proc), 'Failed to start local PHP test server');

    // Wait up to 3s for port
    $ready = false;
    for ($i = 0; $i < 15; $i++) {
        $fp = @fsockopen('127.0.0.1', 8088, $errno, $errstr, 0.2);
        if ($fp) {
            fclose($fp);
            $ready = true;
            break;
        }
        usleep(200000);
    }

    $httpGet = static function (string $path): array {
        $ch = curl_init('http://127.0.0.1:8088' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 6,
        ]);
        $body = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    };

    try {
        pfgAssert($ready, 'Local PHP server did not start on 127.0.0.1:8088');
        $checks = [
            ['/', 200],
            ['/apply', 200],
            ['/login', 200],
            ['/programme', 200],
            ['/accessibility', 200],
            ['/api/auth/me', 200],
            ['/api/registrations', 200],
            ['/admin', 302],
            ['/rgst', 302],
            ['/api/admin/overview', 401],
            ['/README.md', 403],
            ['/docs/WEBSITE_AUDIT.md', 403],
            ['/audit/MASTER_CODEBASE_AUDIT.md', 403],
            ['/project-docs/PROJECT_BASELINE.md', 403],
            ['/database/schema.sql', 403],
            ['/includes/config.php', 403],
            ['/scripts/verify-bluehost-env.php', 403],
        ];
        foreach ($checks as [$path, $expected]) {
            $r = $httpGet($path);
            pfgAssert($r['status'] === $expected, "GET {$path} expected HTTP {$expected}, got {$r['status']}");
            if ($path === '/api/registrations') {
                $json = json_decode($r['body'], true);
                pfgAssert(is_array($json) && ($json['edition']['slug'] ?? '') === 'gujarat-2027' && count($json['categories'] ?? []) === 8, 'Invalid /api/registrations payload');
            }
        }
        return 'All 17 live HTTP checks passed (including GET /api/registrations -> 200 w/ 8 DB categories & 403 on all 7 internal/doc paths)';
    } finally {
        proc_terminate($proc);
        @fclose($pipes[0]);
        @fclose($pipes[1]);
        @fclose($pipes[2]);
        proc_close($proc);
    }
});

// T1-11: Bluehost environment verification CLI script runs cleanly
pfgTest('T1-11', 'CLI execution of scripts/verify-bluehost-env.php passes locally', function () use ($root, $phpBin): string {
    $out = [];
    $code = 0;
    exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/scripts/verify-bluehost-env.php') . ' --skip-network 2>&1', $out, $code);
    $joined = implode(' | ', array_slice($out, -3));
    pfgAssert($code === 0, 'verify-bluehost-env.php failed: ' . implode("\n", $out));
    return $joined;
});

