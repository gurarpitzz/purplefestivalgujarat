<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 18 Isolated Release Package & Zip Archive Verification Suite
 *
 * Builds the ACTUAL production release package + `.zip` archive via `scripts/build-release-package.php`,
 * verifies manifest integrity & zero excluded/secret leaks across BOTH the extracted directory and `.zip`,
 * creates an isolated external `tests/tmp/.pfg27_env.php` OUTSIDE the package docroot (with `PFG_ENV_FILE`
 * unset to prove parent-directory discovery), boots a native PHP 8.2 server pointed DIRECTLY at
 * `tests/tmp/rc-pkg` on 127.0.0.1:18098, verifies live HTTP routes/assets/API/security responses,
 * cleans up `tests/tmp/rc-pkg` & `tests/tmp/.pfg27_env.php` (keeping the `.zip` and `RELEASE_MANIFEST.json`),
 * and writes `tests/tmp/phase18_package_verification.json`.
 *
 * Usage:
 *   C:\xampp\php\php.exe tests/phase18_release_package_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 Forbidden — CLI execution only.\n");
}

$root = str_replace('\\', '/', dirname(__DIR__));
$phpBin = PHP_BINARY !== '' ? PHP_BINARY : 'C:\\xampp\\php\\php.exe';
$tmpDir = $root . '/tests/tmp';
$pkgDir = $tmpDir . '/rc-pkg';
$zipFile = $tmpDir . '/purplefestgujarat-rc.zip';
$manifestFile = $tmpDir . '/RELEASE_MANIFEST.json';
$extEnvFile = $tmpDir . '/.pfg27_env.php';
$routerFile = $tmpDir . '/rc_pkg_router.php';
$serverLog = $tmpDir . '/php_p18_server.log';
$reportFile = $tmpDir . '/phase18_package_verification.json';
$port = 18098;
$baseUrl = "http://127.0.0.1:{$port}";

if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$results = [];
$passed = 0;
$failed = 0;

$record = static function (string $id, string $name, bool $ok, string $evidence) use (&$results, &$passed, &$failed): void {
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
    $results[] = [
        'id'       => $id,
        'name'     => $name,
        'status'   => $ok ? 'PASS' : 'FAIL',
        'evidence' => $evidence,
    ];
    printf("[%s] %-7s %s — %s\n", $ok ? 'PASS' : 'FAIL', $id, $name, $evidence);
};

$removeDirRecursive = static function (string $dir) use (&$removeDirRecursive): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $dir . '/' . $item;
        if (is_dir($full) && !is_link($full)) {
            $removeDirRecursive($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($dir);
};

$isForbiddenPath = static function (string $relPath): ?string {
    $norm = ltrim(str_replace('\\', '/', $relPath), '/');
    $parts = explode('/', $norm);
    $top = strtolower($parts[0] ?? '');
    $base = strtolower(basename($norm));

    $forbiddenTopDirs = [
        '.git', '.github', '.next', 'node_modules',
        'docs', 'audit', 'project-docs', 'tests', 'data', 'archive', 'legacy',
    ];
    if (in_array($top, $forbiddenTopDirs, true)) {
        return "forbidden_dir:{$top}";
    }
    if (str_starts_with($base, '.env') || str_starts_with($base, '.pfg27_env')) {
        return 'secret_env_file';
    }
    if ($base === 'release_manifest.json' || $base === 'readme.md' || $base === '.cpanel.yml' || $base === '.gitignore') {
        return "forbidden_meta:{$base}";
    }
    if (preg_match('/\.(md|docx|pptx|xlsx)$/i', $base)) {
        return 'doc_artifact';
    }
    if ($top !== 'assets' && preg_match('/\.pdf$/i', $base)) {
        return 'non_asset_pdf';
    }
    if (in_array($norm, [
        'assets/js/registration-wizard.js',
        'assets/js/html5-qrcode.min.js',
        'assets/js/qrcode-gen.js',
        'database/schema.sql',
        'database/seed.sql',
        'scripts/build-release-package.php',
        'Inclusive Viksit Bharat 2047 Vision.png',
        'Understanding Disability Access Guide.png',
        'bg1.png',
    ], true)) {
        return "excluded_file:{$norm}";
    }
    return null;
};

$httpGet = static function (string $path) use ($baseUrl): array {
    $ch = curl_init($baseUrl . $path);
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
    $err = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'headers' => $headers, 'body' => $body, 'error' => $err];
};

$proc = null;
$pipes = [];
$zipSummary = [];

try {
    // -------------------------------------------------------------------------
    // P18-01: Build ACTUAL release package + .zip archive via scripts/build-release-package.php
    // -------------------------------------------------------------------------
    $buildCmd = sprintf(
        '%s %s --out=tests/tmp/rc-pkg --zip=tests/tmp/purplefestgujarat-rc.zip --json 2>&1',
        escapeshellarg($phpBin),
        escapeshellarg($root . '/scripts/build-release-package.php')
    );
    $buildOut = [];
    $buildCode = 0;
    exec($buildCmd, $buildOut, $buildCode);
    $buildRaw = implode("\n", $buildOut);
    $buildJson = json_decode($buildRaw, true);

    $buildOk = $buildCode === 0
        && is_array($buildJson)
        && ($buildJson['status'] ?? '') === 'PASSED'
        && (int)($buildJson['summary']['excludedLeakCount'] ?? -1) === 0
        && (int)($buildJson['summary']['phpSyntaxLintFailedCount'] ?? -1) === 0
        && !empty($buildJson['summary']['manifestOutsideWebroot'])
        && ($buildJson['zipArchive']['status'] ?? '') === 'CREATED';

    $record(
        'P18-01',
        'Build release package + .zip archive via scripts/build-release-package.php',
        $buildOk,
        $buildOk
            ? sprintf(
                'status=PASSED, included=%d files (%dB), phpLint=%d pass/0 fail, zip=%s (%dB, %d entries)',
                (int)$buildJson['summary']['includedFileCount'],
                (int)$buildJson['summary']['includedTotalBytes'],
                (int)$buildJson['summary']['phpSyntaxLintPassedCount'],
                (string)$buildJson['zipArchive']['path'],
                (int)$buildJson['zipArchive']['sizeBytes'],
                (int)$buildJson['zipArchive']['entryCount']
            )
            : "exit={$buildCode} output=" . substr($buildRaw, 0, 500)
    );

    // -------------------------------------------------------------------------
    // P18-02: Verify RELEASE_MANIFEST.json integrity & SHA-256 checksums on extracted package
    // -------------------------------------------------------------------------
    $manifest = is_file($manifestFile) ? json_decode((string)file_get_contents($manifestFile), true) : null;
    $manifestFiles = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
    $expectedPaths = [];
    $hashMismatches = [];
    $missingFromPkg = [];

    foreach ($manifestFiles as $mf) {
        $rel = (string)($mf['path'] ?? '');
        if ($rel === '') {
            continue;
        }
        $expectedPaths[$rel] = true;
        $full = $pkgDir . '/' . $rel;
        if (!is_file($full)) {
            $missingFromPkg[] = $rel;
            continue;
        }
        $actualSha = hash_file('sha256', $full);
        if ($actualSha !== (string)($mf['sha256'] ?? '')) {
            $hashMismatches[] = $rel;
        }
    }

    $pkgLeaks = [];
    $pkgFileCount = 0;
    if (is_dir($pkgDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pkgDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $info) {
            if (!$info->isFile()) {
                continue;
            }
            $pkgFileCount++;
            $abs = str_replace('\\', '/', $info->getPathname());
            $rel = substr($abs, strlen($pkgDir) + 1);
            $reason = $isForbiddenPath($rel);
            if ($reason !== null || !isset($expectedPaths[$rel])) {
                $pkgLeaks[] = $rel . '(' . ($reason ?? 'unlisted') . ')';
            }
        }
    }

    $manifestOk = is_array($manifest)
        && count($manifestFiles) > 50
        && $pkgFileCount === count($manifestFiles)
        && empty($missingFromPkg)
        && empty($hashMismatches)
        && empty($pkgLeaks)
        && !is_file($pkgDir . '/RELEASE_MANIFEST.json');

    $record(
        'P18-02',
        'Verify RELEASE_MANIFEST.json checksums & zero excluded/secret leaks in rc-pkg',
        $manifestOk,
        $manifestOk
            ? sprintf('%d/%d files verified with matching SHA-256, 0 missing, 0 leaks, manifest outside docroot', $pkgFileCount, count($manifestFiles))
            : sprintf('pkgCount=%d manifestCount=%d missing=%d hashErr=%d leaks=%s', $pkgFileCount, count($manifestFiles), count($missingFromPkg), count($hashMismatches), implode(',', array_slice($pkgLeaks, 0, 5)))
    );

    // -------------------------------------------------------------------------
    // P18-03: Verify .zip archive validity, entry parity with manifest & zero leaks
    // -------------------------------------------------------------------------
    $zipValid = false;
    $zipEntryCount = 0;
    $zipLeaks = [];
    $zipDiff = [];
    $zipInspector = 'none';
    if (is_file($zipFile)) {
        $magic = (string)file_get_contents($zipFile, false, null, 0, 4);
        $hasZipMagic = ($magic === "PK\x03\x04");
        $zipSet = [];

        if ($hasZipMagic && class_exists('ZipArchive')) {
            $zipInspector = 'ZipArchive';
            $za = new ZipArchive();
            if ($za->open($zipFile) === true) {
                $zipEntryCount = (int)$za->numFiles;
                for ($i = 0; $i < $zipEntryCount; $i++) {
                    $entryName = str_replace('\\', '/', (string)$za->getNameIndex($i));
                    $zipSet[$entryName] = true;
                    $reason = $isForbiddenPath($entryName);
                    if ($reason !== null) {
                        $zipLeaks[] = $entryName . '(' . $reason . ')';
                    }
                }
                $za->close();
            }
        } elseif ($hasZipMagic && class_exists('PharData')) {
            $zipInspector = 'PharData';
            $phar = new PharData($zipFile);
            $pharPrefix = 'phar://' . str_replace('\\', '/', realpath($zipFile) ?: $zipFile) . '/';
            $pIt = new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($pIt as $pInfo) {
                if (!$pInfo->isFile()) {
                    continue;
                }
                $pPath = str_replace('\\', '/', $pInfo->getPathname());
                $entryName = str_starts_with($pPath, $pharPrefix)
                    ? substr($pPath, strlen($pharPrefix))
                    : ltrim(str_replace('\\', '/', $pInfo->getFilename()), '/');
                $zipSet[$entryName] = true;
                $zipEntryCount++;
                $reason = $isForbiddenPath($entryName);
                if ($reason !== null) {
                    $zipLeaks[] = $entryName . '(' . $reason . ')';
                }
            }
        }

        if ($hasZipMagic && $zipEntryCount > 0) {
            $missingInZip = array_keys(array_diff_key($expectedPaths, $zipSet));
            $extraInZip = array_keys(array_diff_key($zipSet, $expectedPaths));
            $zipDiff = array_merge($missingInZip, $extraInZip);
            $zipValid = ($zipEntryCount === count($expectedPaths)) && empty($zipLeaks) && empty($zipDiff);
            $zipSummary = [
                'path'       => 'tests/tmp/purplefestgujarat-rc.zip',
                'sizeBytes'  => (int)filesize($zipFile),
                'sha256'     => (string)hash_file('sha256', $zipFile),
                'entryCount' => $zipEntryCount,
                'inspector'  => $zipInspector,
            ];
        }
    }

    $record(
        'P18-03',
        'Verify .zip archive integrity, exact manifest entry parity & zero excluded files in .zip',
        $zipValid,
        $zipValid
            ? sprintf('Valid PKZIP via %s (%d entries, %dB, sha256=%s..), 0 leaks, 100%% manifest parity', $zipInspector, $zipEntryCount, (int)($zipSummary['sizeBytes'] ?? 0), substr((string)($zipSummary['sha256'] ?? ''), 0, 16))
            : sprintf('zipExists=%s inspector=%s entries=%d expected=%d leaks=%s diff=%s', is_file($zipFile) ? 'yes' : 'no', $zipInspector, $zipEntryCount, count($expectedPaths), implode(',', array_slice($zipLeaks, 0, 5)), implode(',', array_slice($zipDiff, 0, 5)))
    );

    // -------------------------------------------------------------------------
    // P18-04: Boot isolated PHP server pointed DIRECTLY at tests/tmp/rc-pkg
    //         with external tests/tmp/.pfg27_env.php (PFG_ENV_FILE strictly UNSET!)
    // -------------------------------------------------------------------------
    $envPayload = [
        'APP_ENV'             => 'testing',
        'APP_URL'             => $baseUrl,
        'DB_DRIVER'           => 'mysql',
        'DB_HOST'             => '127.0.0.1',
        'DB_PORT'             => '3306',
        'DB_NAME'             => 'pfg27_test',
        'DB_USER'             => 'root',
        'DB_PASS'             => '',
        'AUTH_SECRET'         => bin2hex(random_bytes(24)),
        'QR_SIGNING_SECRET'   => bin2hex(random_bytes(24)),
        'MFA_ENCRYPTION_KEY'  => bin2hex(random_bytes(24)),
        'DATA_ENCRYPTION_KEY' => bin2hex(random_bytes(24)),
        'MAIL_DRIVER'         => 'file',
        'MAIL_SINK_DIR'       => $tmpDir . '/mail-sink',
        'RESEND_API_KEY'      => '',
        'EMAIL_FROM'          => 'Purple Festival RC <noreply@test.local>',
    ];
    file_put_contents($extEnvFile, "<?php\nreturn " . var_export($envPayload, true) . ";\n");

    // Minimal router simulating .htaccess inside tests/tmp/rc-pkg docroot
    $routerCode = <<<'PHP'
<?php
declare(strict_types=1);
$docroot = str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? getcwd()));
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$trimmed = ltrim($uri, '/');

if (
    preg_match('#^(database|includes|docs|audit|project-docs|tests|scripts|data|node_modules)(/|$)#i', $trimmed)
    || preg_match('#(^|/)\.(?!well-known/)#', $uri)
    || preg_match('#(^|/)(README\.md|\.env.*|\.pfg27_env.*|\.user\.ini|composer\.(json|lock)|package(-lock)?\.json)$#i', $trimmed)
    || preg_match('#\.(sql|sqlite|sqlite3|db|log|md|yml|yaml|bak|old|orig|swp)$#i', $trimmed)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden\n";
    return true;
}

$full = $docroot . '/' . $trimmed;
if ($trimmed === '') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require $docroot . '/index.php';
    return true;
}
if (is_file($full) && !str_ends_with(strtolower($full), '.php')) {
    return false;
}
if (is_file($full) && str_ends_with(strtolower($full), '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $trimmed;
    require $full;
    return true;
}
$clean = rtrim($trimmed, '/');
if (is_file($docroot . '/' . $clean . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $clean . '/index.php';
    require $docroot . '/' . $clean . '/index.php';
    return true;
}
if (is_file($docroot . '/' . $clean . '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $clean . '.php';
    require $docroot . '/' . $clean . '.php';
    return true;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 Not Found\n";
return true;
PHP;
    file_put_contents($routerFile, $routerCode);

    // Strip PFG_ENV_FILE strictly so packaged includes/config.php MUST discover parent tests/tmp/.pfg27_env.php!
    $childEnv = getenv();
    if (!is_array($childEnv)) {
        $childEnv = [];
    }
    unset($childEnv['PFG_ENV_FILE']);
    @unlink($serverLog);

    $proc = proc_open(
        [$phpBin, '-S', "127.0.0.1:{$port}", '-t', $pkgDir, $routerFile],
        [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']],
        $pipes,
        $pkgDir,
        $childEnv
    );
    $ready = false;
    if (is_resource($proc)) {
        for ($i = 0; $i < 25; $i++) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($fp) {
                fclose($fp);
                $ready = true;
                break;
            }
            usleep(150000);
        }
    }

    $record(
        'P18-04',
        'Boot isolated PHP server on packaged docroot (tests/tmp/rc-pkg) with parent .pfg27_env.php (PFG_ENV_FILE unset)',
        $ready,
        $ready ? "Listening on {$baseUrl} with docroot=tests/tmp/rc-pkg & external parent .pfg27_env.php" : 'Failed to start isolated package server'
    );

    if ($ready) {
        // ---------------------------------------------------------------------
        // P18-05: Verify public HTML routes on packaged release
        // ---------------------------------------------------------------------
        $publicChecks = [
            '/',
            '/programme.php',
            '/accessibility.php',
            '/login.php',
            '/register.php',
            '/forgot-password.php',
            '/staff/login.php',
            '/apply/index.php',
        ];
        $pubFails = [];
        foreach ($publicChecks as $p) {
            $r = $httpGet($p);
            if ($r['status'] !== 200) {
                $pubFails[] = "{$p}={$r['status']}";
            } elseif ($p === '/' && !str_contains($r['body'], 'Purple Festival Gujarat 2027')) {
                $pubFails[] = '/ missing title text';
            }
        }
        $record(
            'P18-05',
            'Packaged public page routes return HTTP 200 (8 routes incl. / with festival title)',
            empty($pubFails),
            empty($pubFails) ? '8/8 packaged public routes returned HTTP 200 and / contains "Purple Festival Gujarat 2027"' : 'Failures: ' . implode(', ', $pubFails)
        );

        // ---------------------------------------------------------------------
        // P18-06: Verify packaged static CSS/JS assets return HTTP 200
        // ---------------------------------------------------------------------
        $assetChecks = [
            '/assets/css/app.css',
            '/assets/css/auth-pages.css',
            '/assets/js/accessibility.js',
            '/assets/js/rgst-desk.js',
        ];
        $assetFails = [];
        foreach ($assetChecks as $a) {
            $r = $httpGet($a);
            if ($r['status'] !== 200 || strlen($r['body']) < 100) {
                $assetFails[] = "{$a}={$r['status']}(" . strlen($r['body']) . 'B)';
            }
        }
        $record(
            'P18-06',
            'Packaged static CSS/JS assets return HTTP 200 with non-empty payloads',
            empty($assetFails),
            empty($assetFails) ? '4/4 assets returned HTTP 200 (app.css, auth-pages.css, accessibility.js, rgst-desk.js)' : 'Failures: ' . implode(', ', $assetFails)
        );

        // ---------------------------------------------------------------------
        // P18-07: Verify packaged GET /api/registrations/index.php returns strictly 3 PRIMARY categories
        // ---------------------------------------------------------------------
        $rRegApi = $httpGet('/api/registrations/index.php');
        $regJson = json_decode($rRegApi['body'], true);
        $cats = is_array($regJson['categories'] ?? null) ? $regJson['categories'] : [];
        $codes = array_map(static fn(array $c): string => strtoupper((string)($c['code'] ?? '')), $cats);
        sort($codes);
        $regApiOk = $rRegApi['status'] === 200
            && is_array($regJson)
            && ($regJson['edition']['slug'] ?? '') === 'gujarat-2027'
            && count($cats) === 3
            && $codes === ['ATTENDEE', 'SLI', 'VOLUNTEER'];
        $record(
            'P18-07',
            'Packaged GET /api/registrations/index.php returns HTTP 200 JSON with strictly 3 PRIMARY categories',
            $regApiOk,
            $regApiOk
                ? 'HTTP 200, edition=gujarat-2027, categories=[ATTENDEE, SLI, VOLUNTEER] (parent .pfg27_env.php -> MariaDB verified)'
                : "HTTP {$rRegApi['status']} codes=" . json_encode($codes) . ' body=' . substr($rRegApi['body'], 0, 200)
        );

        // ---------------------------------------------------------------------
        // P18-08: Verify packaged auth/security boundaries (401 APIs, 302 portals, 401+410 QR pass, 403 internal)
        // ---------------------------------------------------------------------
        $secChecks = [
            ['/api/attendance/index.php', 401],
            ['/api/account/dashboard.php', 401],
            ['/api/admin/notifications.php', 401],
            ['/dashboard/index.php', 302],
            ['/admin/index.php', 302],
            ['/rgst/index.php', 302],
            ['/api/qr/pass.php', 401],
            ['/includes/config.php', 403],
            ['/database/migrate.php', 403],
            ['/.htaccess', 403],
            ['/.user.ini', 403],
        ];
        $secFails = [];
        foreach ($secChecks as [$path, $exp]) {
            $r = $httpGet($path);
            if ($r['status'] !== $exp) {
                $secFails[] = "{$path}={$r['status']}(exp {$exp})";
            }
        }
        $qrPassSrc = is_file($pkgDir . '/api/qr/pass.php') ? (string)file_get_contents($pkgDir . '/api/qr/pass.php') : '';
        if (!str_contains($qrPassSrc, '410')) {
            $secFails[] = 'api/qr/pass.php missing 410 Gone';
        }
        $record(
            'P18-08',
            'Packaged security boundaries: 401 protected APIs, 302 protected portals, 401/410 QR pass, 403 internal files',
            empty($secFails),
            empty($secFails) ? '11/11 packaged security route checks passed + api/qr/pass.php verified containing 410 Gone' : 'Failures: ' . implode(', ', $secFails)
        );
    }
} finally {
    if (is_resource($proc)) {
        @proc_terminate($proc);
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        @proc_close($proc);
        usleep(200000);
    }
    // Clean up extracted package, temp external env, and temp router; KEEP .zip and RELEASE_MANIFEST.json
    $removeDirRecursive($pkgDir);
    @unlink($extEnvFile);
    @unlink($routerFile);
}

$cleanupOk = !is_dir($pkgDir) && !is_file($extEnvFile) && is_file($zipFile) && is_file($manifestFile);
$record(
    'P18-09',
    'Clean up rc-pkg and temp .pfg27_env.php while retaining .zip archive and RELEASE_MANIFEST.json',
    $cleanupOk,
    $cleanupOk
        ? 'rc-pkg & .pfg27_env.php removed; retained tests/tmp/purplefestgujarat-rc.zip & tests/tmp/RELEASE_MANIFEST.json'
        : sprintf('pkgExists=%s envExists=%s zipExists=%s manifestExists=%s', is_dir($pkgDir) ? 'yes' : 'no', is_file($extEnvFile) ? 'yes' : 'no', is_file($zipFile) ? 'yes' : 'no', is_file($manifestFile) ? 'yes' : 'no')
);

echo "\n============================================================\n";
printf("PHASE 18 RELEASE PACKAGE VERIFICATION — TOTAL: %d | PASSED: %d | FAILED: %d\n", $passed + $failed, $passed, $failed);
echo "============================================================\n";

file_put_contents(
    $reportFile,
    json_encode([
        'generatedAt'  => gmdate('Y-m-d\TH:i:s\Z'),
        'total'        => $passed + $failed,
        'passed'       => $passed,
        'failed'       => $failed,
        'zipArchive'   => $zipSummary,
        'manifestPath' => 'tests/tmp/RELEASE_MANIFEST.json',
        'results'      => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

exit($failed > 0 ? 1 : 0);

