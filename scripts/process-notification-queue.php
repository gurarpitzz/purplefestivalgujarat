<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Bluehost cPanel Cron Notification Queue Worker (CLI ONLY)
 *
 * Designed for scheduled execution via Bluehost cPanel Cron Jobs (NOT a persistent daemon).
 * Safe under overlapping cron invocations via atomic InnoDB lease claiming (`pfgClaimNotificationBatch`)
 * and automatic abandoned-lease recovery (`pfgRecoverAbandonedNotifications`).
 *
 * Usage:
 *   php scripts/process-notification-queue.php
 *   php scripts/process-notification-queue.php --batch=50 --max-seconds=40 --json
 *   php scripts/process-notification-queue.php --driver=simulate --simulate=accept --json  (local/test ONLY)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden — CLI execution only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/notification-service.php';

$batchLimit = NOTIFICATION_BATCH_LIMIT;
$maxSeconds = NOTIFICATION_MAX_SECONDS;
$lockTtl = NOTIFICATION_LOCK_TTL_SECONDS;
$workerId = 'cron-' . getmypid() . '-' . bin2hex(random_bytes(4));
$driverOverride = null;
$simScenario = null;
$outputJson = false;
$drainLoop = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--batch=')) {
        $batchLimit = max(1, min(200, (int)substr($arg, 8)));
    } elseif (str_starts_with($arg, '--max-seconds=')) {
        $maxSeconds = max(1, min(300, (int)substr($arg, 14)));
    } elseif (str_starts_with($arg, '--lock-ttl=')) {
        $lockTtl = max(30, min(900, (int)substr($arg, 11)));
    } elseif (str_starts_with($arg, '--worker-id=')) {
        $workerId = substr(trim(substr($arg, 12)), 0, 96) ?: $workerId;
    } elseif (str_starts_with($arg, '--driver=')) {
        $driverOverride = strtolower(trim(substr($arg, 9)));
    } elseif (str_starts_with($arg, '--simulate=')) {
        $simScenario = strtolower(trim(substr($arg, 11)));
    } elseif ($arg === '--drain') {
        $drainLoop = true;
    } elseif ($arg === '--json') {
        $outputJson = true;
    }
}

// Guard: never permit simulator flags outside local dev/testing
if (($driverOverride === 'simulate' || $simScenario !== null) && (!defined('IS_LOCAL_DEV_ENV') || !IS_LOCAL_DEV_ENV)) {
    fwrite(STDERR, "ERROR: --driver=simulate and --simulate are strictly prohibited outside development/testing.\n");
    exit(2);
}

try {
    $pdo = getDb();
    $tStart = microtime(true);

    $aggregate = [
        'workerId'       => $workerId,
        'driver'         => $driverOverride ?? MAIL_DRIVER,
        'batches'        => 0,
        'recovered'      => 0,
        'claimed'        => 0,
        'processed'      => 0,
        'accepted'       => 0,
        'capturedLocal'  => 0,
        'retryScheduled' => 0,
        'failed'         => 0,
        'unknown'        => 0,
        'stoppedByTime'  => false,
        'durationMs'     => 0,
        'peakMemoryKb'   => 0,
    ];

    do {
        $remaining = max(1, (int)ceil($maxSeconds - (microtime(true) - $tStart)));
        $res = pfgProcessNotificationQueueBatch($pdo, [
            'batchLimit'     => $batchLimit,
            'maxSeconds'     => $remaining,
            'lockTtlSeconds' => $lockTtl,
            'workerId'       => $workerId,
            'driver'         => $driverOverride,
            'simScenario'    => $simScenario,
        ]);

        $aggregate['batches']++;
        $aggregate['recovered'] += (int)$res['recovered'];
        $aggregate['claimed'] += (int)$res['claimed'];
        $aggregate['processed'] += (int)$res['processed'];
        $aggregate['accepted'] += (int)$res['accepted'];
        $aggregate['capturedLocal'] += (int)$res['capturedLocal'];
        $aggregate['retryScheduled'] += (int)$res['retryScheduled'];
        $aggregate['failed'] += (int)$res['failed'];
        $aggregate['unknown'] += (int)$res['unknown'];
        $aggregate['stoppedByTime'] = $aggregate['stoppedByTime'] || !empty($res['stoppedByTime']);

        if (!$drainLoop || (int)$res['claimed'] === 0 || (microtime(true) - $tStart) >= $maxSeconds) {
            break;
        }
    } while (true);

    $aggregate['durationMs'] = (int)round((microtime(true) - $tStart) * 1000);
    $aggregate['peakMemoryKb'] = (int)round(memory_get_peak_usage(true) / 1024);

    if ($outputJson) {
        echo json_encode($aggregate, JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        printf(
            "[%s] worker=%s driver=%s batches=%d recovered=%d claimed=%d processed=%d accepted=%d local=%d retry=%d failed=%d unknown=%d time=%dms mem=%dKB\n",
            nowSql(),
            $aggregate['workerId'],
            $aggregate['driver'],
            $aggregate['batches'],
            $aggregate['recovered'],
            $aggregate['claimed'],
            $aggregate['processed'],
            $aggregate['accepted'],
            $aggregate['capturedLocal'],
            $aggregate['retryScheduled'],
            $aggregate['failed'],
            $aggregate['unknown'],
            $aggregate['durationMs'],
            $aggregate['peakMemoryKb']
        );
    }

    exit(0);
} catch (Throwable $e) {
    error_log('[process-notification-queue] Fatal error: ' . $e->getMessage());
    if ($outputJson) {
        echo json_encode(['error' => $e->getMessage(), 'workerId' => $workerId]) . "\n";
    } else {
        fwrite(STDERR, 'WORKER ERROR: ' . $e->getMessage() . "\n");
    }
    exit(1);
}

