<?php
declare(strict_types=1);

/**
 * Isolated CLI worker spawned in parallel (10 / 25 / 50 / 100 processes simultaneously)
 * by Phase 3 concurrency stress tests to verify atomic Registration ID allocation on MariaDB/MySQL.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/registration-id.php';

$dbOverride = $argv[1] ?? '';
$editionId = $argv[2] ?? '00000000-0000-4000-8000-000000002027';
$workerIdx = (int)($argv[3] ?? 0);
$barrierTs = isset($argv[4]) ? (float)$argv[4] : 0.0;

// Synchronized start barrier so all worker processes hit InnoDB at the exact same millisecond
if ($barrierTs > 0.0) {
    $waitUs = (int)(($barrierTs - microtime(true)) * 1000000);
    if ($waitUs > 0 && $waitUs < 5000000) {
        usleep($waitUs);
    }
}

try {
    require_once dirname(__DIR__) . '/includes/migrations.php';
    $pdo = pfgCreateDirectPdo($dbOverride !== '' ? $dbOverride : null);
    $code = allocateNextRegistrationCode($pdo, $editionId);
    echo json_encode(['ok' => true, 'worker' => $workerIdx, 'code' => $code]);
    exit(0);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'worker' => $workerIdx, 'error' => $e->getMessage()]);
    exit(1);
}

