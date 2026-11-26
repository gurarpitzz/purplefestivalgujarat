<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Native PHP Automated Test Runner (Zero Composer / Zero Docker)
 * Runs against isolated local MariaDB/MySQL `pfg27_test` database and local file mail sink.
 *
 * Usage:
 *   C:\xampp\php\php.exe tests/run.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden\n");
}

$root = dirname(__DIR__);
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$sinkDir = $tmpDir . DIRECTORY_SEPARATOR . 'mail-sink';
if (!is_dir($sinkDir)) {
    mkdir($sinkDir, 0775, true);
}

// Generate an isolated ephemeral test env file inside tests/tmp/ (gitignored)
$testEnvFile = $tmpDir . DIRECTORY_SEPARATOR . 'test_env.php';
$testAuthSecret = bin2hex(random_bytes(24));
$testQrSecret = bin2hex(random_bytes(24));
$testMfaKey = bin2hex(random_bytes(24));
$testDataKey = bin2hex(random_bytes(24));

$testEnvContent = "<?php\nreturn " . var_export([
    'APP_ENV'             => 'testing',
    'APP_URL'             => 'http://127.0.0.1:8088',
    'DB_DRIVER'           => 'mysql',
    'DB_HOST'             => '127.0.0.1',
    'DB_PORT'             => '3306',
    'DB_NAME'             => 'pfg27_test',
    'DB_USER'             => 'root',
    'DB_PASS'             => '',
    'AUTH_SECRET'         => $testAuthSecret,
    'QR_SIGNING_SECRET'   => $testQrSecret,
    'MFA_ENCRYPTION_KEY'  => $testMfaKey,
    'DATA_ENCRYPTION_KEY' => $testDataKey,
    'MAIL_DRIVER'         => 'file',
    'MAIL_SINK_DIR'       => $sinkDir,
    'RESEND_API_KEY'      => '',
    'EMAIL_FROM'          => 'Purple Festival Test <noreply@test.local>',
], true) . ";\n";

file_put_contents($testEnvFile, $testEnvContent);
putenv('PFG_ENV_FILE=' . $testEnvFile);

$results = [];
$passed = 0;
$failed = 0;

function pfgTest(string $id, string $name, callable $fn): void
{
    global $results, $passed, $failed;
    try {
        $evidence = $fn();
        $passed++;
        $results[] = [
            'id'       => $id,
            'name'     => $name,
            'status'   => 'PASS',
            'evidence' => is_string($evidence) ? $evidence : 'Assertion passed',
        ];
        printf("[PASS] %-7s %s — %s\n", $id, $name, is_string($evidence) ? $evidence : 'OK');
    } catch (Throwable $e) {
        $failed++;
        $results[] = [
            'id'       => $id,
            'name'     => $name,
            'status'   => 'FAIL',
            'evidence' => $e->getMessage(),
        ];
        printf("[FAIL] %-7s %s — %s\n", $id, $name, $e->getMessage());
    }
}

function pfgAssert(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/phase1_containment_test.php';
require_once __DIR__ . '/phase2_security_test.php';
require_once __DIR__ . '/phase3_database_test.php';
require_once __DIR__ . '/phase4_notification_test.php';
require_once __DIR__ . '/phase5_15_full_system_test.php';

echo "\n============================================================\n";
printf("TOTAL: %d | PASSED: %d | FAILED: %d\n", $passed + $failed, $passed, $failed);
echo "============================================================\n";

// Write JSON summary for reporting
$summaryJson = json_encode(
    ['total' => $passed + $failed, 'passed' => $passed, 'failed' => $failed, 'results' => $results],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'phase17_php_results.json', $summaryJson);
file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'phase4_results.json', $summaryJson);

exit($failed > 0 ? 1 : 0);

