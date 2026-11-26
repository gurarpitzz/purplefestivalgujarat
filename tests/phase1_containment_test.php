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

// T1-02: Isolated MariaDB pfg27_test clean migration & seed initialization (controlled CLI runner — zero per-request DDL)
pfgTest('T1-02', 'Isolated MariaDB pfg27_test clean migration & seed initialization', function () use ($root): string {
    require_once $root . '/includes/config.php';
    require_once $root . '/includes/migrations.php';
    require_once $root . '/database/seed.php';
    require_once $root . '/includes/database.php';

    // Reset pfg27_test tables cleanly (isolated test DB ONLY!)
    pfgAssert(DB_NAME === 'pfg27_test', 'Safety abort: DB_NAME must be pfg27_test');
    $bootstrapPdo = pfgCreateDirectPdo('pfg27_test');
    $bootstrapPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $existingTables = $bootstrapPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existingTables as $tbl) {
        $bootstrapPdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string)$tbl) . '`');
    }
    $bootstrapPdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Run controlled versioned migrations + safe reference seeder (Phase 3B/3C replaces per-request DDL)
    $migResult = pfgRunMigrations($bootstrapPdo);
    pfgRunSafeReferenceSeed($bootstrapPdo);

    $pdo = getDb(true);
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $edCount = (int)$pdo->query('SELECT COUNT(*) FROM festival_editions WHERE is_active = 1')->fetchColumn();
    $totalCatCount = (int)$pdo->query('SELECT COUNT(*) FROM registration_categories')->fetchColumn();
    $primaryActiveCount = (int)$pdo->query("SELECT COUNT(*) FROM registration_categories WHERE category_tier = 'PRIMARY' AND is_active = 1")->fetchColumn();
    $legacyInactiveCount = (int)$pdo->query("SELECT COUNT(*) FROM registration_categories WHERE category_tier = 'LEGACY' AND is_active = 0")->fetchColumn();
    $partnerCount = (int)$pdo->query("SELECT COUNT(*) FROM partners WHERE status = 'PUBLISHED'")->fetchColumn();
    $fkCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    )->fetchColumn();
    $appliedMigrations = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

    pfgAssert(count($tables) === 70, 'Expected 70 tables across migrations 000–012, got ' . count($tables));
    pfgAssert($appliedMigrations === 13, 'Expected 13 applied migrations (000–012), got ' . $appliedMigrations);
    pfgAssert(in_array('security_rate_limits', $tables, true), 'Missing Phase 2 security_rate_limits table');
    pfgAssert(in_array('schema_migrations', $tables, true), 'Missing Phase 3 schema_migrations table');
    pfgAssert(in_array('registration_number_sequences', $tables, true), 'Missing Phase 3 registration_number_sequences table');
    pfgAssert(in_array('attendance_records', $tables, true), 'Missing Phase 3 attendance_records table');
    pfgAssert(in_array('in_app_notifications', $tables, true), 'Missing Phase 4 in_app_notifications table');
    pfgAssert(in_array('email_verification_tokens', $tables, true), 'Missing Phase 5 email_verification_tokens table');
    pfgAssert(in_array('legacy_registration_claims', $tables, true), 'Missing Phase 5 legacy_registration_claims table');
    pfgAssert(in_array('attendee_details', $tables, true), 'Missing Phase 6 attendee_details table');
    pfgAssert(in_array('registration_drafts', $tables, true), 'Missing Phase 6 registration_drafts table');
    pfgAssert(in_array('registration_status_history', $tables, true), 'Missing Phase 7 registration_status_history table');
    pfgAssert(in_array('admin_permission_profiles', $tables, true), 'Missing Phase 8 admin_permission_profiles table');
    pfgAssert(in_array('duty_assignments', $tables, true), 'Missing Phase 12 duty_assignments table');
    pfgAssert($edCount === 1, 'Expected 1 active festival edition, got ' . $edCount);
    pfgAssert(
        $totalCatCount === 8 && $primaryActiveCount === 3 && $legacyInactiveCount === 5,
        "Expected 8 total categories (3 PRIMARY active, 5 LEGACY inactive), got total={$totalCatCount} primaryActive={$primaryActiveCount} legacyInactive={$legacyInactiveCount}"
    );
    pfgAssert($partnerCount === 11, 'Expected 11 seeded partners, got ' . $partnerCount);
    pfgAssert($fkCount >= 79, 'Expected >= 79 enforced foreign keys, got ' . $fkCount);

    return sprintf(
        'Fresh MariaDB pfg27_test migrated (%d migrations) & seeded: 70 tables, %d FKs, 1 active edition, 8 categories (3 PRIMARY active, 5 LEGACY inactive), 11 partners',
        $appliedMigrations,
        $fkCount
    );
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
    $p1Log = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'php_p1_server.log';
    @unlink($p1Log);
    $desc = [0 => ['pipe', 'r'], 1 => ['file', $p1Log, 'a'], 2 => ['file', $p1Log, 'a']];
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
                $cats = $json['categories'] ?? [];
                $codes = array_map(static fn(array $c): string => strtoupper((string)($c['code'] ?? '')), is_array($cats) ? $cats : []);
                sort($codes);
                pfgAssert(
                    is_array($json)
                    && ($json['edition']['slug'] ?? '') === 'gujarat-2027'
                    && count($cats) === 3
                    && $codes === ['ATTENDEE', 'SLI', 'VOLUNTEER'],
                    'Invalid /api/registrations payload (expected strictly 3 PRIMARY categories ATTENDEE, VOLUNTEER, SLI): ' . json_encode($codes)
                );
            }
        }
        return 'All 17 live HTTP checks passed (GET /api/registrations -> 200 w/ strictly 3 PRIMARY categories & 0 legacy exposed; 403 on all 7 internal/doc paths)';
    } finally {
        proc_terminate($proc);
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            @fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            @fclose($pipes[2]);
        }
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

