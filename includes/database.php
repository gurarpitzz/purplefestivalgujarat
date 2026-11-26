<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — PDO Database Layer (Phase 3 Hardened)
 *
 * Architectural Guarantees (Phase 3B / FND-29):
 * - ZERO per-request DDL: `getDb()` NEVER executes CREATE TABLE or ALTER TABLE during HTTP requests.
 * - Schema creation and upgrades are strictly handled via the controlled CLI migration runner:
 *     `php database/migrate.php --up --seed`
 * - Performs a fast, read-only schema-readiness check once per PHP process so misconfigured or
 *   unmigrated databases fail cleanly instead of silently running DDL in production.
 * - Enforces MySQL 8.0 / MariaDB 10.4+ in staging/production (zero silent SQLite fallback).
 */

require_once __DIR__ . '/config.php';

/**
 * PDO subclass providing cross-driver SQL normalization where needed.
 */
class PfgPdo extends PDO
{
    public bool $isSqlite = false;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->isSqlite = stripos($dsn, 'sqlite:') === 0;
    }

    public function normalizeSql(string $sql): string
    {
        if ($this->isSqlite) {
            $sql = preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT OR IGNORE INTO', $sql) ?? $sql;
            $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql) ?? $sql;
            if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
                $sql = preg_replace('/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+.+$/is', '', $sql) ?? $sql;
                $sql = preg_replace('/^\s*INSERT\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql) ?? $sql;
            }
        } else {
            $sql = preg_replace('/\bINSERT\s+OR\s+IGNORE\s+INTO\b/i', 'INSERT IGNORE INTO', $sql) ?? $sql;
        }

        return $sql;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        return parent::prepare($this->normalizeSql((string)$query), $options);
    }

    #[\ReturnTypeWillChange]
    public function query($query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        $normalized = $this->normalizeSql((string)$query);
        if ($fetchMode === null) {
            return parent::query($normalized);
        }
        return parent::query($normalized, $fetchMode, ...$fetchModeArgs);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        return parent::exec($this->normalizeSql((string)$statement));
    }
}

if (!function_exists('pfgValidateSchemaReady')) {
    /**
     * Lightweight, read-only schema validation (ZERO DDL).
     * Verifies `schema_migrations` exists and contains at least one applied migration.
     */
    function pfgValidateSchemaReady(PDO $pdo): void
    {
        try {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE status = 'APPLIED'")->fetchColumn();
            if ($cnt < 1) {
                throw new RuntimeException(
                    'Database schema has not been migrated. Run `php database/migrate.php --up --seed` via CLI.'
                );
            }
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database schema is uninitialized (missing schema_migrations). Run `php database/migrate.php --up --seed` via CLI.',
                0,
                $e
            );
        }
    }
}

if (!function_exists('getDb')) {
    /**
     * Returns the singleton PDO connection without executing any DDL.
     * Enforces MySQL/MariaDB in staging/production (fails closed if DB credentials are missing).
     */
    function getDb(bool $reset = false): PDO
    {
        static $pdo = null;
        static $schemaValidated = false;

        if ($reset) {
            $pdo = null;
            $schemaValidated = false;
        }
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];

        $isLocalDev = defined('IS_LOCAL_DEV_ENV') && IS_LOCAL_DEV_ENV;
        $driver = defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'mysql';
        $hasMysqlCreds = (DB_NAME !== '' && DB_USER !== '');

        if ($driver === 'sqlite') {
            if (!$isLocalDev) {
                throw new RuntimeException('SQLite database driver is prohibited in staging and production environments.');
            }
            $sqlitePath = SQLITE_FALLBACK_PATH;
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $dsn = 'sqlite:' . $sqlitePath;
            $pdo = new PfgPdo($dsn, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            if (!$hasMysqlCreds) {
                throw new RuntimeException('Database credentials (DB_NAME and DB_USER) are not configured. Silent SQLite fallback is disabled.');
            }
            if (DB_SOCKET !== '') {
                $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', DB_SOCKET, DB_NAME);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            }
            $pdo = new PfgPdo($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '+05:30'");
        }

        if (!$schemaValidated && !($pdo instanceof PfgPdo && $pdo->isSqlite)) {
            pfgValidateSchemaReady($pdo);
            $schemaValidated = true;
        }

        return $pdo;
    }
}

if (!function_exists('seedDatabase')) {
    /**
     * Backward-compatible wrapper delegating to the safe, idempotent CLI reference seeder (`database/seed.php`).
     * Never invoked automatically during HTTP requests.
     */
    function seedDatabase(PDO $pdo): void
    {
        require_once dirname(__DIR__) . '/database/seed.php';
        pfgRunSafeReferenceSeed($pdo);
    }
}

if (!function_exists('getActiveEdition')) {
    /**
     * Returns the active festival edition row ('gujarat-2027').
     */
    function getActiveEdition(?PDO $pdo = null): ?array
    {
        $pdo = $pdo ?? getDb();
        $stmt = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => FESTIVAL_EDITION_SLUG]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $stmt = $pdo->query('SELECT * FROM festival_editions WHERE is_active = 1 LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        return $row ?: null;
    }
}

if (!function_exists('queueNotification')) {
    /**
     * Enqueues a notification job in the canonical `notification_queue` table
     * with honest lifecycle state (`QUEUED`), event_type, channel, and optional idempotency_key.
     */
    function queueNotification(array $params, ?PDO $pdo = null): string
    {
        $pdo = $pdo ?? getDb();
        $templateCode = (string)($params['templateCode'] ?? $params['template_code'] ?? 'GENERIC');
        $eventType = (string)($params['eventType'] ?? $params['event_type'] ?? $templateCode);
        $channel = strtoupper(trim((string)($params['channel'] ?? 'EMAIL')));
        $recipientUserId = $params['recipientUserId'] ?? $params['recipient_user_id'] ?? null;
        $recipientEmail = strtolower(trim((string)($params['recipientEmail'] ?? $params['recipient_email'] ?? '')));
        $idempotencyKey = isset($params['idempotencyKey']) && trim((string)$params['idempotencyKey']) !== ''
            ? substr(trim((string)$params['idempotencyKey']), 0, 128)
            : null;
        $variables = is_array($params['variables'] ?? null) ? $params['variables'] : [];
        $subject = (string)($params['subject'] ?? $params['fallbackSubject'] ?? 'Purple Festival Gujarat 2027 Notification');
        $bodyHtml = $params['bodyHtml'] ?? null;
        $bodyText = $params['bodyText'] ?? null;

        if ($idempotencyKey !== null) {
            $dup = $pdo->prepare('SELECT id FROM notification_queue WHERE idempotency_key = :ikey LIMIT 1');
            $dup->execute([':ikey' => $idempotencyKey]);
            $existingId = $dup->fetchColumn();
            if (is_string($existingId) && $existingId !== '') {
                return $existingId;
            }
        }

        $tplStmt = $pdo->prepare('SELECT * FROM notification_templates WHERE code = :code AND is_active = 1 LIMIT 1');
        $tplStmt->execute([':code' => $templateCode]);
        $tpl = $tplStmt->fetch();
        if ($tpl) {
            $subject = (string)$tpl['subject'];
            $bodyHtml = (string)$tpl['body_html'];
            $bodyText = (string)$tpl['body_text'];
            foreach ($variables as $k => $v) {
                $placeholder = '{{' . $k . '}}';
                $subject = str_replace($placeholder, (string)$v, $subject);
                $bodyHtml = str_replace($placeholder, htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $bodyHtml);
                $bodyText = str_replace($placeholder, (string)$v, $bodyText);
            }
        }

        $jobId = uuid4();
        $now = nowSql();
        $safePayloadVars = function_exists('pfgScrubSensitiveArray') ? pfgScrubSensitiveArray($variables) : $variables;

        $ins = $pdo->prepare("
            INSERT INTO notification_queue
            (id, template_code, event_type, channel, recipient_user_id, recipient_email, subject, body_html, body_text, payload, status, provider, idempotency_key, attempts, max_attempts, created_at, updated_at)
            VALUES (:id, :template_code, :event_type, :channel, :recipient_user_id, :recipient_email, :subject, :body_html, :body_text, :payload, 'QUEUED', 'queue', :idempotency_key, 0, 3, :now, :now)
        ");
        $ins->execute([
            ':id'                => $jobId,
            ':template_code'     => $templateCode,
            ':event_type'        => $eventType,
            ':channel'           => $channel,
            ':recipient_user_id' => $recipientUserId,
            ':recipient_email'   => $recipientEmail,
            ':subject'           => $subject,
            ':body_html'         => $bodyHtml,
            ':body_text'         => $bodyText,
            ':payload'           => jsonEncode($safePayloadVars),
            ':idempotency_key'   => $idempotencyKey,
            ':now'               => $now,
        ]);

        return $jobId;
    }
}
