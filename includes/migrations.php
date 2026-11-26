<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Versioned Database Migration Engine
 *
 * Native PHP + PDO MySQL 8.0 / MariaDB 10.4+ (Bluehost cPanel & XAMPP compatible).
 * Zero Docker, zero Composer, zero third-party frameworks.
 *
 * Guarantees:
 * - Tracks every migration in `schema_migrations` with SHA-256 checksum & timing.
 * - Acquires MySQL advisory lock `GET_LOCK('pfg27_schema_migration', 15)` to prevent concurrent runs.
 * - Detects checksum drift/tampering on already-applied migrations and aborts loudly.
 * - Provides MySQL 8.0 AND MariaDB 10.4+ portable `information_schema` helpers for conditional
 *   column, index, and foreign key creation (avoiding MariaDB-only `ADD COLUMN IF NOT EXISTS`).
 * - Supports both `.sql` and `.php` migrations in deterministic numeric order.
 */

require_once __DIR__ . '/config.php';

if (!defined('PFG_REQUIRED_SCHEMA_VERSION')) {
    define('PFG_REQUIRED_SCHEMA_VERSION', '013_accountless_public_registration');
}

if (!function_exists('pfgCreateDirectPdo')) {
    /**
     * Opens a raw PDO connection directly to MySQL/MariaDB WITHOUT running any implicit DDL
     * or schema checks — intended strictly for CLI migration/seeding/verification scripts.
     */
    function pfgCreateDirectPdo(?string $overrideDbName = null): PDO
    {
        $dbName = $overrideDbName !== null && $overrideDbName !== '' ? $overrideDbName : DB_NAME;
        if ($dbName === '' || DB_USER === '') {
            throw new RuntimeException('Database credentials (DB_NAME and DB_USER) are not configured.');
        }

        if (DB_SOCKET !== '') {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', DB_SOCKET, $dbName);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, $dbName);
        }

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '+05:30'");
        return $pdo;
    }
}

if (!function_exists('pfgDbTableExists')) {
    function pfgDbTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl'
        );
        $stmt->execute([':tbl' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('pfgDbColumnExists')) {
    function pfgDbColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
        );
        $stmt->execute([':tbl' => $table, ':col' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('pfgDbIndexExists')) {
    function pfgDbIndexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tbl AND INDEX_NAME = :idx'
        );
        $stmt->execute([':tbl' => $table, ':idx' => $indexName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('pfgDbForeignKeyExists')) {
    function pfgDbForeignKeyExists(PDO $pdo, string $table, string $constraintName): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tbl
               AND CONSTRAINT_NAME = :cname
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        $stmt->execute([':tbl' => $table, ':cname' => $constraintName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('pfgDbEnsureColumn')) {
    /**
     * MySQL 8.0 + MariaDB 10.4+ portable idempotent ADD COLUMN helper.
     */
    function pfgDbEnsureColumn(PDO $pdo, string $table, string $column, string $definition): bool
    {
        if (!pfgDbTableExists($pdo, $table) || pfgDbColumnExists($pdo, $table, $column)) {
            return false;
        }
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        return true;
    }
}

if (!function_exists('pfgDbEnsureIndex')) {
    /**
     * MySQL 8.0 + MariaDB 10.4+ portable idempotent ADD INDEX / ADD UNIQUE INDEX helper.
     */
    function pfgDbEnsureIndex(PDO $pdo, string $table, string $indexName, string $columnsSql, bool $unique = false): bool
    {
        if (!pfgDbTableExists($pdo, $table) || pfgDbIndexExists($pdo, $table, $indexName)) {
            return false;
        }
        $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD %s `%s` (%s)', $table, $kind, $indexName, $columnsSql));
        return true;
    }
}

if (!function_exists('pfgDbCountOrphans')) {
    /**
     * Counts non-null child values in `$childTable.$childColumn` that have no matching row in `$parentTable.$parentColumn`.
     */
    function pfgDbCountOrphans(
        PDO $pdo,
        string $childTable,
        string $childColumn,
        string $parentTable,
        string $parentColumn = 'id'
    ): int {
        if (!pfgDbTableExists($pdo, $childTable) || !pfgDbTableExists($pdo, $parentTable)) {
            return 0;
        }
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` c LEFT JOIN `%s` p ON c.`%s` = p.`%s`
             WHERE c.`%s` IS NOT NULL AND c.`%s` <> \'\' AND p.`%s` IS NULL',
            $childTable,
            $parentTable,
            $childColumn,
            $parentColumn,
            $childColumn,
            $childColumn,
            $parentColumn
        );
        return (int)$pdo->query($sql)->fetchColumn();
    }
}

if (!function_exists('pfgDbEnsureForeignKey')) {
    /**
     * Non-destructively validates zero orphans and adds a safe, non-cascading foreign key constraint.
     * NEVER deletes orphaned records automatically — returns status 'SKIPPED_ORPHANS' with orphan count if found.
     */
    function pfgDbEnsureForeignKey(
        PDO $pdo,
        string $childTable,
        string $constraintName,
        string $childColumn,
        string $parentTable,
        string $parentColumn = 'id',
        string $onDelete = 'RESTRICT',
        string $onUpdate = 'CASCADE'
    ): array {
        if (!pfgDbTableExists($pdo, $childTable) || !pfgDbTableExists($pdo, $parentTable)) {
            return ['constraint' => $constraintName, 'status' => 'SKIPPED_TABLE_MISSING', 'orphans' => 0];
        }
        if (pfgDbForeignKeyExists($pdo, $childTable, $constraintName)) {
            return ['constraint' => $constraintName, 'status' => 'ALREADY_EXISTS', 'orphans' => 0];
        }

        // Normalize empty strings to NULL for nullable FK columns before orphan check
        if (strtoupper($onDelete) === 'SET NULL') {
            $pdo->exec(sprintf(
                "UPDATE `%s` SET `%s` = NULL WHERE `%s` = ''",
                $childTable,
                $childColumn,
                $childColumn
            ));
        }

        $orphans = pfgDbCountOrphans($pdo, $childTable, $childColumn, $parentTable, $parentColumn);
        if ($orphans > 0) {
            return [
                'constraint' => $constraintName,
                'status'     => 'SKIPPED_ORPHANS',
                'orphans'    => $orphans,
                'detail'     => "{$childTable}.{$childColumn} -> {$parentTable}.{$parentColumn} has {$orphans} orphan row(s)",
            ];
        }

        // Ensure supporting index exists on child column (good practice for MySQL/MariaDB FK performance)
        $autoIdx = 'idx_fk_' . substr($childTable, 0, 24) . '_' . substr($childColumn, 0, 24);
        if (!pfgDbIndexExists($pdo, $childTable, $autoIdx)) {
            try {
                $pdo->exec(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)', $childTable, $autoIdx, $childColumn));
            } catch (Throwable $e) {
                // Supporting index may already exist under another name
            }
        }

        $sql = sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
            $childTable,
            $constraintName,
            $childColumn,
            $parentTable,
            $parentColumn,
            strtoupper($onDelete),
            strtoupper($onUpdate)
        );
        $pdo->exec($sql);

        return ['constraint' => $constraintName, 'status' => 'ADDED', 'orphans' => 0];
    }
}

if (!function_exists('pfgEnsureMigrationsTable')) {
    function pfgEnsureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `version` VARCHAR(120) NOT NULL,
                `filename` VARCHAR(255) NOT NULL,
                `checksum_sha256` CHAR(64) NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'APPLIED',
                `applied_at` DATETIME NOT NULL,
                `execution_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                `notes` TEXT DEFAULT NULL,
                PRIMARY KEY (`version`),
                KEY `idx_schema_migrations_status` (`status`),
                KEY `idx_schema_migrations_applied` (`applied_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('pfgComputeMigrationChecksum')) {
    /**
     * Computes a cross-platform (Windows CRLF / Bluehost Linux LF) normalized SHA-256 checksum
     * so line-ending conversions during Git/cPanel deployment never cause false checksum drift,
     * while any actual SQL/PHP content modification still triggers immediate tamper detection.
     */
    function pfgComputeMigrationChecksum(string $filePath): string
    {
        $raw = (string)file_get_contents($filePath);
        $normalized = rtrim(str_replace(["\r\n", "\r"], "\n", $raw), "\n");
        return hash('sha256', $normalized);
    }
}

if (!function_exists('pfgDiscoverMigrations')) {
    /**
     * Discovers all ordered forward migrations (`.sql` and `.php`) in `database/migrations/`,
     * excluding `*_rollback.*` files.
     */
    function pfgDiscoverMigrations(?string $dir = null): array
    {
        $migrationsDir = $dir ?? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
        if (!is_dir($migrationsDir)) {
            return [];
        }

        $files = scandir($migrationsDir) ?: [];
        $list = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (preg_match('/_rollback\.(sql|php)$/i', $file)) {
                continue;
            }
            if (!preg_match('/^(\d{3}_[a-zA-Z0-9_]+)\.(sql|php)$/', $file, $m)) {
                continue;
            }
            $fullPath = $migrationsDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fullPath)) {
                continue;
            }
            $list[] = [
                'version'  => $m[1],
                'type'     => strtolower($m[2]),
                'filename' => $file,
                'path'     => $fullPath,
                'checksum' => pfgComputeMigrationChecksum($fullPath),
            ];
        }

        usort($list, static fn(array $a, array $b): int => strcmp($a['filename'], $b['filename']));
        return $list;
    }
}

if (!function_exists('pfgSplitSqlStatements')) {
    /**
     * Splits a MySQL/MariaDB SQL script into individual executable statements,
     * preserving string literals and stripping `--` single-line comments.
     */
    function pfgSplitSqlStatements(string $sql): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $cleanLines = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $cleanLines[] = $line;
        }
        $joined = implode("\n", $cleanLines);

        $statements = [];
        $buf = '';
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $len = strlen($joined);

        for ($i = 0; $i < $len; $i++) {
            $ch = $joined[$i];
            $prev = $i > 0 ? $joined[$i - 1] : '';

            if ($ch === "'" && !$inDouble && !$inBacktick && $prev !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && !$inBacktick && $prev !== '\\') {
                $inDouble = !$inDouble;
            } elseif ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $stmt = trim($buf);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buf = '';
            } else {
                $buf .= $ch;
            }
        }
        $tail = trim($buf);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}

if (!function_exists('pfgGetMigrationStatus')) {
    /**
     * Returns full status report of discovered vs applied migrations, including checksum drift verification.
     */
    function pfgGetMigrationStatus(PDO $pdo, ?string $dir = null): array
    {
        $discovered = pfgDiscoverMigrations($dir);
        $hasTable = pfgDbTableExists($pdo, 'schema_migrations');
        $appliedMap = [];

        if ($hasTable) {
            $rows = $pdo->query('SELECT * FROM schema_migrations ORDER BY version ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $appliedMap[(string)$row['version']] = $row;
            }
        }

        $items = [];
        $pending = [];
        $checksumMismatches = [];

        foreach ($discovered as $m) {
            $ver = $m['version'];
            $rec = $appliedMap[$ver] ?? null;
            if ($rec && ($rec['status'] ?? '') === 'APPLIED') {
                $checksumOk = hash_equals((string)$rec['checksum_sha256'], $m['checksum']);
                if (!$checksumOk) {
                    $checksumMismatches[] = [
                        'version'          => $ver,
                        'filename'         => $m['filename'],
                        'recordedChecksum' => (string)$rec['checksum_sha256'],
                        'currentChecksum'  => $m['checksum'],
                    ];
                }
                $items[] = [
                    'version'     => $ver,
                    'filename'    => $m['filename'],
                    'state'       => $checksumOk ? 'APPLIED' : 'CHECKSUM_MISMATCH',
                    'appliedAt'   => (string)$rec['applied_at'],
                    'executionMs' => (int)$rec['execution_ms'],
                    'notes'       => $rec['notes'] ?? null,
                ];
            } else {
                $pending[] = $m;
                $items[] = [
                    'version'     => $ver,
                    'filename'    => $m['filename'],
                    'state'       => 'PENDING',
                    'appliedAt'   => null,
                    'executionMs' => null,
                    'notes'       => null,
                ];
            }
        }

        return [
            'hasMigrationsTable' => $hasTable,
            'discoveredCount'    => count($discovered),
            'appliedCount'       => count($discovered) - count($pending),
            'pendingCount'       => count($pending),
            'checksumMismatches' => $checksumMismatches,
            'items'              => $items,
            'pending'            => $pending,
        ];
    }
}

if (!function_exists('pfgRunMigrations')) {
    /**
     * Executes all pending migrations in strict numeric order under a MySQL advisory lock.
     * Aborts immediately if any already-applied migration's checksum has drifted.
     */
    function pfgRunMigrations(PDO $pdo, ?string $dir = null): array
    {
        $lockName = 'pfg27_schema_migration_' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 16);
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 15)');
        $lockStmt->execute([':lock_name' => $lockName]);
        $gotLock = (int)$lockStmt->fetchColumn();
        if ($gotLock !== 1) {
            throw new RuntimeException('Could not acquire MySQL migration advisory lock. Another migration may be running.');
        }

        try {
            pfgEnsureMigrationsTable($pdo);
            $status = pfgGetMigrationStatus($pdo, $dir);

            if (!empty($status['checksumMismatches'])) {
                $bad = array_map(static fn(array $x): string => $x['filename'], $status['checksumMismatches']);
                throw new RuntimeException(
                    'Migration checksum mismatch detected for already-applied migration(s): ' . implode(', ', $bad)
                    . '. Never modify an already-applied migration file; create a new numbered migration instead.'
                );
            }

            $appliedNow = [];
            foreach ($status['pending'] as $m) {
                $start = microtime(true);
                $notes = null;

                try {
                    if ($m['type'] === 'sql') {
                        $sql = (string)file_get_contents($m['path']);
                        $statements = pfgSplitSqlStatements($sql);
                        foreach ($statements as $stmtSql) {
                            $pdo->exec($stmtSql);
                        }
                        $notes = jsonEncode(['statementsExecuted' => count($statements)]);
                    } elseif ($m['type'] === 'php') {
                        $runner = require $m['path'];
                        if (!is_callable($runner)) {
                            throw new RuntimeException("PHP migration {$m['filename']} must return a callable.");
                        }
                        $result = $runner($pdo);
                        $notes = is_array($result) ? jsonEncode($result) : (string)$result;
                    }

                    $elapsedMs = (int)round((microtime(true) - $start) * 1000);
                    $ins = $pdo->prepare(
                        "INSERT INTO schema_migrations (version, filename, checksum_sha256, status, applied_at, execution_ms, notes)
                         VALUES (:version, :filename, :checksum, 'APPLIED', :applied_at, :ms, :notes)
                         ON DUPLICATE KEY UPDATE
                             filename = VALUES(filename),
                             checksum_sha256 = VALUES(checksum_sha256),
                             status = 'APPLIED',
                             applied_at = VALUES(applied_at),
                             execution_ms = VALUES(execution_ms),
                             notes = VALUES(notes)"
                    );
                    $ins->execute([
                        ':version'    => $m['version'],
                        ':filename'   => $m['filename'],
                        ':checksum'   => $m['checksum'],
                        ':applied_at' => nowSql(),
                        ':ms'         => $elapsedMs,
                        ':notes'      => $notes,
                    ]);

                    $appliedNow[] = [
                        'version'     => $m['version'],
                        'filename'    => $m['filename'],
                        'executionMs' => $elapsedMs,
                        'notes'       => $notes,
                    ];
                } catch (Throwable $e) {
                    $elapsedMs = (int)round((microtime(true) - $start) * 1000);
                    try {
                        $failIns = $pdo->prepare(
                            "INSERT INTO schema_migrations (version, filename, checksum_sha256, status, applied_at, execution_ms, notes)
                             VALUES (:version, :filename, :checksum, 'FAILED', :applied_at, :ms, :notes)
                             ON DUPLICATE KEY UPDATE
                                 status = 'FAILED',
                                 applied_at = VALUES(applied_at),
                                 execution_ms = VALUES(execution_ms),
                                 notes = VALUES(notes)"
                        );
                        $failIns->execute([
                            ':version'    => $m['version'],
                            ':filename'   => $m['filename'],
                            ':checksum'   => $m['checksum'],
                            ':applied_at' => nowSql(),
                            ':ms'         => $elapsedMs,
                            ':notes'      => 'ERROR: ' . $e->getMessage(),
                        ]);
                    } catch (Throwable $inner) {
                        // ignore secondary logging failure
                    }
                    throw new RuntimeException("Migration {$m['filename']} failed: " . $e->getMessage(), 0, $e);
                }
            }

            return [
                'appliedNow'   => $appliedNow,
                'finalStatus'  => pfgGetMigrationStatus($pdo, $dir),
            ];
        } finally {
            try {
                $rel = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $rel->execute([':lock_name' => $lockName]);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
}

