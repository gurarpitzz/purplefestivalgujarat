<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Atomic Registration Numbering Service
 *
 * Completely decoupled from legacy QR credential issuance (Decision D-01).
 *
 * Concurrency & Monotonicity Guarantees (MySQL 8.0 & MariaDB 10.4+ InnoDB):
 * - Uses a dedicated InnoDB AUTO_INCREMENT sequence table `registration_number_sequences`.
 * - Each call inserts a row into `registration_number_sequences` and reads `LAST_INSERT_ID()`,
 *   which is protected by InnoDB's lightweight atomic auto-increment lock across concurrent connections.
 * - Allocated sequence rows are retained permanently even if a subsequent registration transaction
 *   rolls back or a registration is later soft-deleted — preventing number reuse AND ensuring
 *   the high-water mark survives MariaDB 10.4 and MySQL 8.0 server restarts.
 * - Format: PFG-2027-000001 .. PFG-2027-000099 -> PFG-2027-000100 -> PFG-2027-001000 -> PFG-2027-010000
 *   (minimum 6 zero-padded digits; naturally expands to 7+ digits at 1,000,000+).
 * - Legacy Registration IDs (including PFG-2027-000001-XXXX format) are NEVER renumbered;
 *   `syncRegistrationSequenceFloor()` guarantees new allocations always start strictly above
 *   the highest existing numeric sequence in `registrations`.
 */

require_once __DIR__ . '/config.php';

if (!defined('PFG_REGISTRATION_CODE_PREFIX')) {
    define('PFG_REGISTRATION_CODE_PREFIX', 'PFG-2027');
}

if (!defined('PFG_REGISTRATION_CODE_MIN_DIGITS')) {
    define('PFG_REGISTRATION_CODE_MIN_DIGITS', 6);
}

if (!function_exists('formatRegistrationCode')) {
    /**
     * Formats a positive sequence integer into the official human-readable Registration ID.
     * Examples: 1 -> PFG-2027-000001, 99 -> PFG-2027-000099, 100 -> PFG-2027-000100,
     *           1000 -> PFG-2027-001000, 10000 -> PFG-2027-010000, 1000000 -> PFG-2027-1000000.
     */
    function formatRegistrationCode(int $sequenceNumber, string $prefix = PFG_REGISTRATION_CODE_PREFIX): string
    {
        if ($sequenceNumber < 1) {
            throw new InvalidArgumentException('Registration sequence number must be >= 1.');
        }
        $numStr = (string)$sequenceNumber;
        $padded = strlen($numStr) < PFG_REGISTRATION_CODE_MIN_DIGITS
            ? str_pad($numStr, PFG_REGISTRATION_CODE_MIN_DIGITS, '0', STR_PAD_LEFT)
            : $numStr;

        return strtoupper(trim($prefix)) . '-' . $padded;
    }
}

if (!function_exists('extractRegistrationSequenceNumber')) {
    /**
     * Extracts the numeric sequence component from both current (PFG-2027-000100)
     * and legacy (PFG-2027-000099-A1B2) Registration IDs. Returns null if not parseable.
     */
    function extractRegistrationSequenceNumber(string $registrationCode, string $prefix = PFG_REGISTRATION_CODE_PREFIX): ?int
    {
        $escapedPrefix = preg_quote(strtoupper(trim($prefix)), '/');
        if (preg_match('/^' . $escapedPrefix . '-(\d{1,12})(?:-[A-Z0-9]+)?$/i', trim($registrationCode), $m)) {
            $val = (int)$m[1];
            return $val > 0 ? $val : null;
        }
        return null;
    }
}

if (!function_exists('scanLegacyRegistrationSequence')) {
    /**
     * Scans all rows (including soft-deleted rows) in `registrations` to find the highest
     * existing numeric sequence number without modifying any legacy registration code.
     */
    function scanLegacyRegistrationSequence(PDO $pdo, string $prefix = PFG_REGISTRATION_CODE_PREFIX): int
    {
        $maxSeq = 0;
        $stmt = $pdo->query('SELECT registration_code FROM registrations');
        if ($stmt) {
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $code = (string)($row['registration_code'] ?? '');
                $parsed = extractRegistrationSequenceNumber($code, $prefix);
                if ($parsed !== null && $parsed > $maxSeq) {
                    $maxSeq = $parsed;
                }
            }
        }
        return $maxSeq;
    }
}

if (!function_exists('syncRegistrationSequenceFloor')) {
    /**
     * Ensures `registration_number_sequences` will always allocate strictly above
     * the highest existing legacy registration number (surviving MariaDB/MySQL restarts).
     *
     * Returns an associative report of the floor state.
     */
    function syncRegistrationSequenceFloor(PDO $pdo, ?string $editionId = null, string $prefix = PFG_REGISTRATION_CODE_PREFIX): array
    {
        $legacyMax = scanLegacyRegistrationSequence($pdo, $prefix);
        $currentSeqMax = (int)$pdo->query('SELECT COALESCE(MAX(seq_number), 0) FROM registration_number_sequences')->fetchColumn();

        if ($editionId === null || $editionId === '') {
            $edStmt = $pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1');
            $edId = $edStmt ? $edStmt->fetchColumn() : false;
            if (!$edId) {
                $edStmt2 = $pdo->query('SELECT id FROM festival_editions LIMIT 1');
                $edId = $edStmt2 ? $edStmt2->fetchColumn() : false;
            }
            $editionId = is_string($edId) && $edId !== '' ? $edId : '00000000-0000-4000-8000-000000002027';
        }

        $floorInserted = false;
        if ($legacyMax > $currentSeqMax) {
            $markerCode = 'LEGACY-FLOOR-' . str_pad((string)$legacyMax, 6, '0', STR_PAD_LEFT);
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO registration_number_sequences (seq_number, edition_id, registration_code, allocated_at)
                 VALUES (:seq, :edition_id, :code, :now)'
            );
            $ins->execute([
                ':seq'        => $legacyMax,
                ':edition_id' => $editionId,
                ':code'       => $markerCode,
                ':now'        => nowSql(),
            ]);
            $floorInserted = true;
            $currentSeqMax = (int)$pdo->query('SELECT COALESCE(MAX(seq_number), 0) FROM registration_number_sequences')->fetchColumn();
        }

        return [
            'legacyMax'       => $legacyMax,
            'sequenceMax'     => $currentSeqMax,
            'nextAllocation'  => formatRegistrationCode($currentSeqMax + 1, $prefix),
            'floorInserted'   => $floorInserted,
        ];
    }
}

if (!function_exists('allocateNextRegistrationCode')) {
    /**
     * Atomically allocates the next unique, monotonic Registration ID using
     * `registration_number_sequences` (InnoDB AUTO_INCREMENT).
     *
     * Concurrency guarantee:
     * - Even under 100+ simultaneous requests, InnoDB guarantees every caller receives
     *   a strictly distinct `seq_number` with zero collisions and zero deadlocks.
     * - Even if the caller's outer registration transaction fails and rolls back,
     *   InnoDB's AUTO_INCREMENT counter does NOT roll back and never reuses the number.
     * - Calling outside a transaction (preferred) also permanently retains the sequence row
     *   so MariaDB 10.4 restarts never reset AUTO_INCREMENT below a rolled-back allocation.
     */
    function allocateNextRegistrationCode(
        PDO $pdo,
        ?string $editionId = null,
        string $prefix = PFG_REGISTRATION_CODE_PREFIX
    ): string {
        if ($editionId === null || trim($editionId) === '') {
            $edStmt = $pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1');
            $edId = $edStmt ? $edStmt->fetchColumn() : false;
            $editionId = is_string($edId) && $edId !== '' ? $edId : '00000000-0000-4000-8000-000000002027';
        }

        // Loop only as a defensive safeguard if a legacy code happens to occupy an already-existing slot
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $ins = $pdo->prepare(
                'INSERT INTO registration_number_sequences (edition_id, allocated_at) VALUES (:edition_id, :now)'
            );
            $ins->execute([
                ':edition_id' => $editionId,
                ':now'        => nowSql(),
            ]);

            $seq = (int)$pdo->lastInsertId();
            if ($seq < 1) {
                throw new RuntimeException('Atomic registration sequence allocator failed to obtain LAST_INSERT_ID().');
            }

            $code = formatRegistrationCode($seq, $prefix);

            // Stamp the generated code on the sequence row for auditability
            $upd = $pdo->prepare(
                'UPDATE registration_number_sequences SET registration_code = :code WHERE seq_number = :seq'
            );
            $upd->execute([
                ':code' => $code,
                ':seq'  => $seq,
            ]);

            // Defensive check against any un-synced legacy registration row
            $chk = $pdo->prepare('SELECT 1 FROM registrations WHERE registration_code = :code LIMIT 1');
            $chk->execute([':code' => $code]);
            if (!$chk->fetchColumn()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to allocate unique registration code after defensive collision retries.');
    }
}

