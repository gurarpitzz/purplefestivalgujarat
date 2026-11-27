<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Clean Bluehost cPanel Release Packager (Phase 18)
 *
 * Strictly CLI-only (`PHP_SAPI === 'cli'`). Builds a clean, production-ready
 * deployment directory containing ONLY verified production runtime files while
 * excluding all development, test, audit, governance, legacy, and secret artifacts.
 *
 * Writes `RELEASE_MANIFEST.json` in the parent directory of `--out` (never inside
 * the `public_html` release directory itself) containing SHA-256 checksums, byte
 * sizes, PHP syntax lint verification results, and exclusion audit counts.
 *
 * Usage:
 *   php scripts/build-release-package.php
 *   php scripts/build-release-package.php --out=tests/tmp/release-package --json
 *   php scripts/build-release-package.php --out=tests/tmp/release-package --zip=tests/tmp/pfg2027-release.zip --json
 *   php scripts/build-release-package.php --verify-only --json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden — CLI execution only.\n";
    exit(1);
}

$repoRoot = realpath(dirname(__DIR__));
if ($repoRoot === false) {
    fwrite(STDERR, "ERROR: Unable to resolve repository root.\n");
    exit(1);
}
$repoRoot = str_replace('\\', '/', $repoRoot);

// Parse CLI options
$outDirArg = 'tests/tmp/release-package';
$zipPathArg = null;
$verifyOnly = false;
$jsonOutput = false;
$skipLint = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $val = trim(substr($arg, strlen('--out=')));
        if ($val !== '') {
            $outDirArg = $val;
        }
    } elseif (str_starts_with($arg, '--zip=')) {
        $val = trim(substr($arg, strlen('--zip=')));
        if ($val !== '') {
            $zipPathArg = $val;
        }
    } elseif ($arg === '--zip') {
        $zipPathArg = '__DEFAULT__';
    } elseif ($arg === '--verify-only') {
        $verifyOnly = true;
    } elseif ($arg === '--json') {
        $jsonOutput = true;
    } elseif ($arg === '--skip-lint') {
        $skipLint = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Purple Festival Gujarat 2027 — Clean Bluehost Release Packager\n";
        echo "Usage: php scripts/build-release-package.php [--out=<dir>] [--zip=<path>] [--verify-only] [--json] [--skip-lint]\n";
        exit(0);
    }
}

$isAbsPath = static function (string $p): bool {
    return str_starts_with($p, '/') || (bool)preg_match('/^[A-Za-z]:[\/\\\\]/', $p);
};

$outDir = $isAbsPath($outDirArg)
    ? str_replace('\\', '/', $outDirArg)
    : $repoRoot . '/' . ltrim(str_replace('\\', '/', $outDirArg), '/');
$outDir = rtrim($outDir, '/');

// Safety guard: never allow --out to point to repo root or parent
if ($outDir === $repoRoot || str_starts_with($repoRoot . '/', $outDir . '/')) {
    fwrite(STDERR, "SAFETY ABORT: --out must be a dedicated subdirectory or external path, never the repository root.\n");
    exit(2);
}

$manifestPath = dirname($outDir) . '/RELEASE_MANIFEST.json';

$zipPath = null;
if ($zipPathArg !== null) {
    if ($zipPathArg === '__DEFAULT__') {
        $zipPath = dirname($outDir) . '/pfg2027-bluehost-release.zip';
    } else {
        $zipPath = $isAbsPath($zipPathArg)
            ? str_replace('\\', '/', $zipPathArg)
            : $repoRoot . '/' . ltrim(str_replace('\\', '/', $zipPathArg), '/');
    }
    if (!str_ends_with(strtolower($zipPath), '.zip')) {
        $zipPath .= '.zip';
    }
    // Safety guard: never write zip archive inside $outDir webroot
    if (str_starts_with($zipPath, $outDir . '/')) {
        fwrite(STDERR, "SAFETY ABORT: --zip path must be outside the --out webroot directory.\n");
        exit(2);
    }
}

/**
 * Authoritative Allowlist & Exclusion Rules (Phase 18 / §40.7)
 */
$allowedRootFiles = [
    '.htaccess',
    '.user.ini',
    'accessibility.php',
    'favicon.svg',
    'forgot-password.php',
    'index.php',
    'login.php',
    'programme.php',
    'register.php',
    'reset-password.php',
    'robots.txt',
    'site.webmanifest',
    'sitemap.xml',
    'verify-email.php',
];

$allowedRecursiveDirs = [
    'admin',
    'api',
    'apply',
    'assets',
    'auth',
    'dashboard',
    'includes',
    'legal',
    'rgst',
    'staff',
    'database/migrations',
];

$allowedExplicitFiles = [
    'database/migrate.php',
    'database/seed.php',
    'database/bootstrap-superadmin.php',
    'scripts/process-notification-queue.php',
    'scripts/verify-bluehost-env.php',
];

$strictlyExcludedDirectories = [
    '.git',
    '.github',
    '.next',
    'node_modules',
    'docs',
    'audit',
    'project-docs',
    'tests',
    'data',
    'archive',
    'legacy',
];

$strictlyExcludedFiles = [
    'assets/js/registration-wizard.js',
    'assets/js/html5-qrcode.min.js',
    'assets/js/qrcode-gen.js',
    'assets/js/participant-dashboard.js',
    'Inclusive Viksit Bharat 2047 Vision.png',
    'Understanding Disability Access Guide.png',
    'bg1.png',
    'README.md',
    '.cpanel.yml',
    '.gitattributes',
    '.gitignore',
    'database/schema.sql',
    'database/seed.sql',
    'scripts/build-release-package.php',
    'scripts/deploy-staging.sh',
];

/**
 * Determines whether a relative repository path is explicitly excluded and returns the audit category.
 */
$classifyExclusion = static function (string $relPath) use (
    $strictlyExcludedDirectories,
    $strictlyExcludedFiles
): ?string {
    $norm = ltrim(str_replace('\\', '/', $relPath), '/');
    $parts = explode('/', $norm);
    $top = $parts[0] ?? '';
    $base = basename($norm);
    $lowerBase = strtolower($base);

    if (in_array($top, $strictlyExcludedDirectories, true)) {
        return 'excluded_directory:' . $top;
    }
    if (in_array($norm, ['assets/js/registration-wizard.js', 'assets/js/html5-qrcode.min.js', 'assets/js/qrcode-gen.js', 'assets/js/participant-dashboard.js'], true)) {
        return 'retired_legacy_js';
    }
    if (in_array($norm, [
        'Inclusive Viksit Bharat 2047 Vision.png',
        'Understanding Disability Access Guide.png',
        'bg1.png',
    ], true)) {
        return 'root_scratch_image';
    }
    if (str_starts_with($lowerBase, '.env') || str_starts_with($lowerBase, '.pfg27_env')) {
        return 'environment_secret_file';
    }
    // §11 Preserve legitimate public PDFs, images, and fonts inside assets/; exclude source docs elsewhere
    if (preg_match('/\.(md|docx|pptx|xlsx)$/i', $base)) {
        return 'documentation_or_source_doc';
    }
    if ($top !== 'assets' && preg_match('/\.pdf$/i', $base)) {
        return 'documentation_or_source_doc';
    }
    if (preg_match('/^(package(-lock)?\.json|tsconfig\.json|next\.config\..*)$/i', $base)) {
        return 'node_or_legacy_framework_config';
    }
    if (!str_contains($norm, '/') && preg_match('/\.sql$/i', $base)) {
        return 'root_sql_dump';
    }
    if (in_array($norm, ['database/schema.sql', 'database/seed.sql'], true)) {
        return 'non_migration_sql_dump';
    }
    if (in_array($norm, $strictlyExcludedFiles, true)) {
        return 'repo_tooling_or_metadata';
    }
    return null;
};

/**
 * Recursively removes a build output directory inside tests/tmp or target path.
 */
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

// 1. Scan entire repository to audit inclusions and exclusions
$exclusionCounts = [
    'excluded_directory:.git'         => 0,
    'excluded_directory:.github'      => 0,
    'excluded_directory:docs'         => 0,
    'excluded_directory:audit'        => 0,
    'excluded_directory:project-docs' => 0,
    'excluded_directory:tests'        => 0,
    'retired_legacy_js'               => 0,
    'root_scratch_image'              => 0,
    'environment_secret_file'         => 0,
    'documentation_or_source_doc'     => 0,
    'node_or_legacy_framework_config' => 0,
    'root_sql_dump'                   => 0,
    'non_migration_sql_dump'          => 0,
    'repo_tooling_or_metadata'        => 0,
    'other_unlisted_file'             => 0,
];

$candidateIncludeSet = [];

// Root public PHP files + .htaccess + .user.ini
foreach ($allowedRootFiles as $rf) {
    if (is_file($repoRoot . '/' . $rf)) {
        $candidateIncludeSet[$rf] = true;
    }
}
foreach (glob($repoRoot . '/*.php') ?: [] as $rootPhp) {
    $bn = basename($rootPhp);
    if ($classifyExclusion($bn) === null) {
        $candidateIncludeSet[$bn] = true;
    }
}

// Explicit allowed files in database/ and scripts/
foreach ($allowedExplicitFiles as $ef) {
    if (is_file($repoRoot . '/' . $ef)) {
        $candidateIncludeSet[$ef] = true;
    }
}

// Allowed recursive runtime directories
foreach ($allowedRecursiveDirs as $rDir) {
    $absDir = $repoRoot . '/' . $rDir;
    if (!is_dir($absDir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $absFile = str_replace('\\', '/', $fileInfo->getPathname());
        $relFile = substr($absFile, strlen($repoRoot) + 1);
        if ($classifyExclusion($relFile) !== null) {
            continue;
        }
        $candidateIncludeSet[$relFile] = true;
    }
}

// Full workspace audit walk to count every excluded file accurately
$outDirRelPrefix = str_starts_with($outDir . '/', $repoRoot . '/')
    ? substr($outDir, strlen($repoRoot) + 1)
    : null;
$manifestRelPath = str_starts_with($manifestPath, $repoRoot . '/')
    ? substr($manifestPath, strlen($repoRoot) + 1)
    : null;
$zipRelPath = ($zipPath !== null && str_starts_with($zipPath, $repoRoot . '/'))
    ? substr($zipPath, strlen($repoRoot) + 1)
    : null;

$totalExcludedFiles = 0;
$repoIterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($repoRoot, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $current) use ($repoRoot, $outDirRelPrefix, &$exclusionCounts, &$totalExcludedFiles): bool {
            $abs = str_replace('\\', '/', $current->getPathname());
            $rel = substr($abs, strlen($repoRoot) + 1);
            // Skip our own target --out directory if inside repo
            if ($outDirRelPrefix !== null && ($rel === $outDirRelPrefix || str_starts_with($rel, $outDirRelPrefix . '/'))) {
                return false;
            }
            // Fast-skip .git directory internals after recording its exclusion
            if ($current->isDir() && $rel === '.git') {
                $exclusionCounts['excluded_directory:.git']++;
                $totalExcludedFiles++;
                return false;
            }
            return true;
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($repoIterator as $info) {
    if (!$info->isFile()) {
        continue;
    }
    $abs = str_replace('\\', '/', $info->getPathname());
    $rel = substr($abs, strlen($repoRoot) + 1);
    if ($manifestRelPath !== null && $rel === $manifestRelPath) {
        continue;
    }
    if ($zipRelPath !== null && $rel === $zipRelPath) {
        continue;
    }
    if (isset($candidateIncludeSet[$rel])) {
        continue;
    }
    $cat = $classifyExclusion($rel) ?? 'other_unlisted_file';
    $exclusionCounts[$cat] = ($exclusionCounts[$cat] ?? 0) + 1;
    $totalExcludedFiles++;
}

$includedPaths = array_keys($candidateIncludeSet);
sort($includedPaths, SORT_STRING);

// 2. Prepare clean output directory (unless --verify-only)
if (!$verifyOnly) {
    if (is_dir($outDir)) {
        $removeDirRecursive($outDir);
    }
    if (!mkdir($outDir, 0755, true) && !is_dir($outDir)) {
        fwrite(STDERR, "ERROR: Failed to create output directory: {$outDir}\n");
        exit(1);
    }
}

// 3. Copy files, compute SHA-256 & byte size, and run PHP syntax lint
$manifestFiles = [];
$totalBytes = 0;
$phpLintPass = 0;
$phpLintFail = 0;
$lintErrors = [];
$phpBin = PHP_BINARY !== '' ? PHP_BINARY : 'php';

foreach ($includedPaths as $relPath) {
    $srcPath = $repoRoot . '/' . $relPath;
    if (!is_file($srcPath)) {
        continue;
    }

    $dstPath = $outDir . '/' . $relPath;
    if (!$verifyOnly) {
        $dstParent = dirname($dstPath);
        if (!is_dir($dstParent) && !mkdir($dstParent, 0755, true) && !is_dir($dstParent)) {
            fwrite(STDERR, "ERROR: Failed to create directory {$dstParent}\n");
            exit(1);
        }
        if (!copy($srcPath, $dstPath)) {
            fwrite(STDERR, "ERROR: Failed to copy {$relPath}\n");
            exit(1);
        }
    }

    $targetToInspect = $verifyOnly ? $srcPath : $dstPath;
    $size = (int)filesize($targetToInspect);
    $sha256 = (string)hash_file('sha256', $targetToInspect);
    $totalBytes += $size;

    $lintStatus = 'N/A';
    if (str_ends_with(strtolower($relPath), '.php')) {
        if ($skipLint) {
            $lintStatus = 'SKIPPED';
        } else {
            $cmd = escapeshellarg($phpBin) . ' -l ' . escapeshellarg($targetToInspect) . ' 2>&1';
            $outLines = [];
            $code = 0;
            exec($cmd, $outLines, $code);
            if ($code === 0) {
                $lintStatus = 'PASS';
                $phpLintPass++;
            } else {
                $lintStatus = 'FAIL';
                $phpLintFail++;
                $lintErrors[$relPath] = implode("\n", $outLines);
            }
        }
    }

    $manifestFiles[] = [
        'path'      => $relPath,
        'sizeBytes' => $size,
        'sha256'    => $sha256,
        'phpLint'   => $lintStatus,
    ];
}

// 4. Post-build leak audit: verify ZERO excluded files or manifest leaked into $outDir
$leakedFiles = [];
if (!$verifyOnly && is_dir($outDir)) {
    $outIt = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($outDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($outIt as $fInfo) {
        if (!$fInfo->isFile()) {
            continue;
        }
        $abs = str_replace('\\', '/', $fInfo->getPathname());
        $rel = substr($abs, strlen($outDir) + 1);
        $exclCat = $classifyExclusion($rel);
        if ($exclCat !== null || !isset($candidateIncludeSet[$rel]) || basename($rel) === 'RELEASE_MANIFEST.json') {
            $leakedFiles[] = [
                'path'   => $rel,
                'reason' => $exclCat ?? 'unlisted_or_manifest_in_webroot',
            ];
        }
    }
}

// Resolve git metadata safely
$gitBranch = trim((string)@shell_exec('git -C ' . escapeshellarg($repoRoot) . ' branch --show-current 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null')));
$gitCommit = trim((string)@shell_exec('git -C ' . escapeshellarg($repoRoot) . ' rev-parse --short HEAD 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null')));

// 4b. Optional §11 verifiable .zip release archive creation (--zip=<path>)
$zipReport = null;
$zipOk = true;
if ($zipPath !== null && !$verifyOnly && $phpLintFail === 0 && count($leakedFiles) === 0 && count($manifestFiles) > 0) {
    $zipParent = dirname($zipPath);
    if (!is_dir($zipParent)) {
        @mkdir($zipParent, 0755, true);
    }
    if (is_file($zipPath)) {
        @unlink($zipPath);
    }

    $zipMethod = null;
    $zipError = null;
    $zipEntryCount = 0;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $openRes = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openRes === true) {
            foreach ($manifestFiles as $mf) {
                $rel = (string)$mf['path'];
                $src = $outDir . '/' . $rel;
                if (is_file($src)) {
                    $zip->addFile($src, $rel);
                }
            }
            $zip->close();

            $verifyZip = new ZipArchive();
            if ($verifyZip->open($zipPath) === true) {
                $zipEntryCount = (int)$verifyZip->numFiles;
                $verifyZip->close();
                $zipMethod = 'ZipArchive';
            } else {
                $zipError = 'ZipArchive verification open failed.';
            }
        } else {
            $zipError = 'ZipArchive open failed with code ' . (string)$openRes;
        }
    } elseif (class_exists('PharData')) {
        try {
            $phar = new PharData($zipPath, 0, null, Phar::ZIP);
            foreach ($manifestFiles as $mf) {
                $rel = (string)$mf['path'];
                $src = $outDir . '/' . $rel;
                if (is_file($src)) {
                    $phar->addFile($src, $rel);
                    $zipEntryCount++;
                }
            }
            $zipMethod = 'PharData';
        } catch (Throwable $pe) {
            $zipError = 'PharData fallback failed: ' . $pe->getMessage();
        }
    } else {
        // OS fallback: `zip` on Linux/macOS or PowerShell Compress-Archive on Windows
        $cmdOut = [];
        $cmdCode = 1;
        if (DIRECTORY_SEPARATOR === '\\') {
            $psCmd = sprintf(
                'powershell -NoProfile -Command "Compress-Archive -Path %s -DestinationPath %s -Force"',
                escapeshellarg($outDir . '/*'),
                escapeshellarg($zipPath)
            );
            exec($psCmd . ' 2>&1', $cmdOut, $cmdCode);
            $zipMethod = 'PowerShell-Compress-Archive';
        } else {
            $shCmd = sprintf(
                'cd %s && zip -qr %s .',
                escapeshellarg($outDir),
                escapeshellarg($zipPath)
            );
            exec($shCmd . ' 2>&1', $cmdOut, $cmdCode);
            $zipMethod = 'cli-zip';
        }
        if ($cmdCode === 0 && is_file($zipPath)) {
            $zipEntryCount = count($manifestFiles);
        } else {
            $zipError = 'No zip library available and OS fallback failed: ' . implode(' ', $cmdOut);
        }
    }

    if ($zipError === null && is_file($zipPath) && (int)filesize($zipPath) > 0) {
        $zipReport = [
            'status'         => 'CREATED',
            'method'         => $zipMethod,
            'path'           => str_starts_with($zipPath, $repoRoot . '/')
                ? substr($zipPath, strlen($repoRoot) + 1)
                : $zipPath,
            'sizeBytes'      => (int)filesize($zipPath),
            'sha256'         => (string)hash_file('sha256', $zipPath),
            'entryCount'     => $zipEntryCount,
            'outsideWebroot' => !str_starts_with($zipPath, $outDir . '/'),
        ];
    } else {
        $zipOk = false;
        $zipReport = [
            'status' => 'FAILED',
            'method' => $zipMethod ?? 'none',
            'path'   => str_starts_with($zipPath, $repoRoot . '/')
                ? substr($zipPath, strlen($repoRoot) + 1)
                : $zipPath,
            'error'  => $zipError ?? 'Zip archive could not be created.',
        ];
    }
}

$statusOk = ($phpLintFail === 0 && count($leakedFiles) === 0 && count($manifestFiles) > 0 && $zipOk);

$manifestData = [
    'project'      => 'Purple Festival Gujarat 2027',
    'packageType'  => 'bluehost-cpanel-production-runtime',
    'status'       => $statusOk ? 'PASSED' : 'FAILED',
    'generatedAt'  => gmdate('Y-m-d\TH:i:s\Z'),
    'gitBranch'    => $gitBranch !== '' ? $gitBranch : 'unknown',
    'gitCommit'    => $gitCommit !== '' ? $gitCommit : 'unknown',
    'verifyOnly'   => $verifyOnly,
    'outputDir'    => $outDirArg,
    'manifestPath' => str_starts_with($manifestPath, $repoRoot . '/')
        ? substr($manifestPath, strlen($repoRoot) + 1)
        : $manifestPath,
    'zipArchive'   => $zipReport,
    'summary'      => [
        'includedFileCount'        => count($manifestFiles),
        'includedTotalBytes'       => $totalBytes,
        'excludedFileCount'        => $totalExcludedFiles,
        'excludedLeakCount'        => count($leakedFiles),
        'phpSyntaxLintPassedCount' => $phpLintPass,
        'phpSyntaxLintFailedCount' => $phpLintFail,
        'manifestOutsideWebroot'   => !str_starts_with($manifestPath, $outDir . '/'),
    ],
    'exclusionAuditCounts' => $exclusionCounts,
    'leakedFiles'          => $leakedFiles,
    'phpLintErrors'        => $lintErrors,
    'files'                => $manifestFiles,
];

// 5. Write RELEASE_MANIFEST.json in --out parent (never inside public_html / $outDir)
$manifestParent = dirname($manifestPath);
if (!is_dir($manifestParent)) {
    @mkdir($manifestParent, 0755, true);
}
file_put_contents(
    $manifestPath,
    json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

if ($jsonOutput) {
    // Emit compact summary + manifest location for automated CI/test consumption
    echo json_encode([
        'status'               => $manifestData['status'],
        'gitBranch'            => $manifestData['gitBranch'],
        'gitCommit'            => $manifestData['gitCommit'],
        'outputDir'            => $manifestData['outputDir'],
        'manifestPath'         => $manifestData['manifestPath'],
        'zipArchive'           => $manifestData['zipArchive'],
        'summary'              => $manifestData['summary'],
        'exclusionAuditCounts' => $manifestData['exclusionAuditCounts'],
        'leakedFiles'          => $manifestData['leakedFiles'],
        'phpLintErrors'        => $manifestData['phpLintErrors'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "=== Purple Festival Gujarat 2027 — Release Package Builder ===\n";
    echo "Status:                {$manifestData['status']}\n";
    echo "Git Branch / Commit:   {$manifestData['gitBranch']} @ {$manifestData['gitCommit']}\n";
    echo "Output Directory:      {$manifestData['outputDir']}\n";
    echo "Manifest Path:         {$manifestData['manifestPath']} (outside webroot: " . ($manifestData['summary']['manifestOutsideWebroot'] ? 'YES' : 'NO') . ")\n";
    if (is_array($zipReport)) {
        echo "Zip Archive:           {$zipReport['path']} ({$zipReport['status']}" . (isset($zipReport['sizeBytes']) ? ", {$zipReport['sizeBytes']} bytes, {$zipReport['entryCount']} entries, sha256={$zipReport['sha256']}" : '') . ")\n";
    }
    echo "Included Files:        {$manifestData['summary']['includedFileCount']} ({$manifestData['summary']['includedTotalBytes']} bytes)\n";
    echo "Excluded Files:        {$manifestData['summary']['excludedFileCount']}\n";
    echo "Excluded Leaks Found:  {$manifestData['summary']['excludedLeakCount']}\n";
    echo "PHP Syntax Lint:       {$phpLintPass} passed, {$phpLintFail} failed\n";
}

exit($statusOk ? 0 : 1);
