<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Controlled CLI Migration & Seeding Runner
 *
 * Strictly CLI-only (HTTP access returns 403 and .htaccess blocks /database/*).
 * Replaces all per-request DDL that previously ran inside getDb().
 *
 * Usage:
 *   php database/migrate.php --status
 *   php database/migrate.php --up
 *   php database/migrate.php --seed
 *   php database/migrate.php --up --seed
 *   php database/migrate.php --verify
 *   php database/migrate.php --fresh-test-only   (STRICTLY gated to APP_ENV=testing & test DB!)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden — CLI execution only.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/registration-id.php';

$args = array_slice($argv ?? [], 1);
if (empty($args)) {
    echo "Purple Festival Gujarat 2027 — Migration Runner\n";
    echo "Options:\n";
    echo "  --status           Show applied vs pending migrations and checksum verification\n";
    echo "  --up               Apply all pending migrations in order\n";
    echo "  --seed             Run idempotent non-destructive reference data seeder\n";
    echo "  --verify           Verify schema version, checksums, tables, and FK count\n";
    echo "  --fresh-test-only  Reset isolated testing database ONLY (requires APP_ENV=testing)\n";
    exit(0);
}

$wantStatus = in_array('--status', $args, true);
$wantUp = in_array('--up', $args, true);
$wantSeed = in_array('--seed', $args, true);
$wantVerify = in_array('--verify', $args, true);
$wantFreshTest = in_array('--fresh-test-only', $args, true);

try {
    $pdo = pfgCreateDirectPdo();
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbVer = (string)$pdo->query('SELECT VERSION()')->fetchColumn();

    if ($wantFreshTest) {
        if (APP_ENV !== 'testing' || !preg_match('/^pfg27_.*test/i', $dbName)) {
            fwrite(STDERR, "SAFETY ABORT: --fresh-test-only is strictly restricted to APP_ENV=testing and a database matching pfg27_*test* (got APP_ENV=" . APP_ENV . ", DB={$dbName}).\n");
            exit(2);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $tbl) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string)$tbl) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        echo "[RESET] Dropped " . count($tables) . " table(s) in isolated test DB `{$dbName}`.\n";
        $wantUp = true;
        $wantSeed = true;
    }

    if ($wantUp) {
        $res = pfgRunMigrations($pdo);
        $appliedCount = count($res['appliedNow']);
        echo "[MIGRATE] Database `{$dbName}` ({$dbVer}): applied {$appliedCount} new migration(s).\n";
        foreach ($res['appliedNow'] as $item) {
            printf("  + %-55s (%dms)\n", $item['filename'], $item['executionMs']);
        }
    }

    if ($wantSeed) {
        require_once __DIR__ . '/seed.php';
        $seedReport = pfgRunSafeReferenceSeed($pdo);
        echo "[SEED] Safe idempotent reference seed complete: " . jsonEncode($seedReport) . "\n";
    }

    if ($wantStatus || $wantVerify) {
        $status = pfgGetMigrationStatus($pdo);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $fkCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        )->fetchColumn();

        echo "=== Migration Status (`{$dbName}`, {$dbVer}) ===\n";
        echo "Tables: " . count($tables) . " | Foreign Keys: {$fkCount} | Applied: {$status['appliedCount']}/{$status['discoveredCount']} | Pending: {$status['pendingCount']}\n";
        foreach ($status['items'] as $it) {
            printf("  [%-17s] %-55s %s\n", $it['state'], $it['filename'], $it['appliedAt'] ?? '-');
        }

        if ($wantVerify) {
            if (!empty($status['checksumMismatches'])) {
                fwrite(STDERR, "VERIFY FAILED: Checksum mismatch detected!\n");
                exit(1);
            }
            if ($status['pendingCount'] > 0) {
                fwrite(STDERR, "VERIFY FAILED: {$status['pendingCount']} pending migration(s) not yet applied.\n");
                exit(1);
            }
            echo "VERIFY PASSED: Schema is up to date, checksums verified, zero pending migrations.\n";
        }
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "MIGRATION ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

