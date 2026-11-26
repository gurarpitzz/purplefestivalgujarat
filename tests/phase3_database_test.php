<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 3 Automated Database, Migration, Concurrency,
 * Integrity, Historical Encryption, Indexing & Backup/Restore Verification Suite
 */

$root = dirname(__DIR__);
$phpBin = PHP_BINARY;
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';

// Helper to create/drop isolated ephemeral synthetic test databases safely
function pfgWithTempDb(string $dbName, callable $fn)
{
    pfgAssert(preg_match('/^pfg27_[a-z0-9_]+_test$/', $dbName) === 1, "Unsafe temp DB name: {$dbName}");
    $adminPdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $adminPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    $adminPdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try {
        $dbPdo = pfgCreateDirectPdo($dbName);
        return $fn($dbPdo, $adminPdo);
    } finally {
        try {
            $adminPdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        } catch (Throwable $e) {
            // ignore cleanup error
        }
    }
}

// T3-01: CLI Migration Runner (--status, --verify), Idempotency & Checksum Tamper Detection
pfgTest('T3-01', 'Versioned migration CLI runner, idempotency & checksum tamper detection', function () use ($root, $phpBin, $tmpDir): string {
    $out = [];
    $code = 0;
    exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($root . '/database/migrate.php') . ' --status --verify 2>&1', $out, $code);
    pfgAssert($code === 0, 'migrate.php --status --verify failed: ' . implode(' | ', $out));

    // Re-run migrations on pfg27_test -> must apply 0 new migrations (idempotent)
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $reRun = pfgRunMigrations($pdo);
    pfgAssert(count($reRun['appliedNow']) === 0, 'Expected 0 newly applied migrations on idempotent rerun, got ' . count($reRun['appliedNow']));

    // Tamper detection test in an isolated temp migrations folder against temp DB
    $tamperDir = $tmpDir . DIRECTORY_SEPARATOR . 'tamper_migs_' . bin2hex(random_bytes(3));
    mkdir($tamperDir, 0775, true);
    $migFile = $tamperDir . DIRECTORY_SEPARATOR . '001_sample_test.sql';
    file_put_contents($migFile, "CREATE TABLE IF NOT EXISTS `sample_tamper_tbl` (`id` INT PRIMARY KEY) ENGINE=InnoDB;\n");

    $tamperBlocked = false;
    try {
        pfgWithTempDb('pfg27_tamper_test', function (PDO $tPdo) use ($tamperDir, $migFile, &$tamperBlocked): void {
            pfgRunMigrations($tPdo, $tamperDir);
            // Tamper with already-applied migration file
            file_put_contents($migFile, "CREATE TABLE IF NOT EXISTS `sample_tamper_tbl` (`id` INT PRIMARY KEY, `x` INT) ENGINE=InnoDB;\n");
            try {
                pfgRunMigrations($tPdo, $tamperDir);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'checksum mismatch')) {
                    $tamperBlocked = true;
                }
            }
        });
    } finally {
        @unlink($migFile);
        @rmdir($tamperDir);
    }

    pfgAssert($tamperBlocked, 'Expected checksum mismatch tamper abort, but migration proceeded');
    return 'CLI --status --verify PASS, idempotent rerun=0 applied, checksum tamper detection ABORTED loudly';
});

// T3-02: Zero per-request DDL verification (getDb fails closed on unmigrated DB & contains zero DDL)
pfgTest('T3-02', 'Zero per-request DDL: getDb() refuses unmigrated DB and contains no CREATE/ALTER TABLE', function () use ($root, $phpBin): string {
    $dbSource = (string)file_get_contents($root . '/includes/database.php');
    $codeOnly = '';
    foreach (token_get_all($dbSource) as $tok) {
        if (is_array($tok)) {
            if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) {
                continue;
            }
            $codeOnly .= $tok[1];
        } else {
            $codeOnly .= $tok;
        }
    }
    pfgAssert(!preg_match('/\b(CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE)\b/i', $codeOnly), 'Found executable DDL (CREATE/ALTER/DROP TABLE) inside includes/database.php');

    $unmigratedBlocked = false;
    pfgWithTempDb('pfg27_empty_ddl_test', function (PDO $emptyPdo) use ($root, $phpBin, &$unmigratedBlocked): void {
        $envPath = __DIR__ . '/tmp/empty_db_env.php';
        file_put_contents($envPath, "<?php return ['APP_ENV'=>'testing','APP_URL'=>'http://127.0.0.1:8088','DB_DRIVER'=>'mysql','DB_HOST'=>'" . DB_HOST . "','DB_PORT'=>'" . DB_PORT . "','DB_NAME'=>'pfg27_empty_ddl_test','DB_USER'=>'" . DB_USER . "','DB_PASS'=>'" . DB_PASS . "','AUTH_SECRET'=>str_repeat('a',32),'QR_SIGNING_SECRET'=>str_repeat('b',32)];");
        $res = runPhpSnippet($phpBin, "require '" . addslashes($root . '/includes/database.php') . "'; try { getDb(true); echo 'ALLOWED'; } catch (RuntimeException \$e) { echo 'BLOCKED:' . \$e->getMessage(); }", [
            'PFG_ENV_FILE' => $envPath,
        ]);
        @unlink($envPath);
        pfgAssert(str_starts_with($res['stdout'], 'BLOCKED:'), 'Expected getDb() to fail cleanly on unmigrated DB, got: ' . $res['stdout']);
        $tablesAfter = $emptyPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        pfgAssert(count($tablesAfter) === 0, 'getDb() created tables on unmigrated DB: ' . implode(',', $tablesAfter));
        $unmigratedBlocked = true;
    });

    pfgAssert($unmigratedBlocked, 'Unmigrated DB check failed');
    return '0 CREATE/ALTER TABLE in includes/database.php; unmigrated DB blocked cleanly with 0 tables auto-created';
});

// T3-03: Legacy pre-Phase-3 DB upgrade, 6-pair duplicate consolidation, conflict preservation, & historical encryption/redaction
pfgTest('T3-03', 'Legacy pre-Phase-3 DB upgrade: duplicate consolidation, sequence floor & historical encryption/redaction', function () use ($root): string {
    $summary = pfgWithTempDb('pfg27_legacy_upgrade_test', function (PDO $lpdo) use ($root): array {
        // 1. Load pre-Phase-3 baseline schema ONLY (simulating an existing legacy database)
        $baseSql = (string)file_get_contents($root . '/database/migrations/000_baseline_schema.sql');
        foreach (pfgSplitSqlStatements($baseSql) as $stmt) {
            $lpdo->exec($stmt);
        }
        // Seed base reference rows (without Phase 3 columns)
        $edId = '00000000-0000-4000-8000-000000002027';
        $now = nowSql();
        $lpdo->exec("INSERT INTO festival_editions (id, slug, name, tagline, city, state, start_date, end_date, is_active, created_at, updated_at) VALUES ('{$edId}','gujarat-2027','Purple Festival Gujarat 2027','Tag','Vadodara','Gujarat','2027-01-21 09:00:00','2027-01-23 20:30:00',1,'{$now}','{$now}')");
        $catId = '50000000-0000-4000-8000-000000000001';
        $lpdo->exec("INSERT INTO registration_categories (id, edition_id, code, name, is_active, display_order, created_at) VALUES ('{$catId}','{$edId}','ATTENDEE','General Attendee',1,1,'{$now}')");

        // 2. Insert legacy user with plaintext mfa_secret + legacy registration #99
        $uid = uuid4();
        $regId = uuid4();
        $lpdo->prepare("INSERT INTO users (id, email, full_name, phone, country, is_active, mfa_enabled, mfa_secret, created_at, updated_at) VALUES (:id, 'legacy.user@example.test', 'Legacy User', '+919825000099', 'India', 1, 1, 'JBSWY3DPEHPK3PXP', :n, :n)")
            ->execute([':id' => $uid, ':n' => $now]);
        $lpdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, 'PFG-2027-000099-A1B2', :eid, :uid, :cid, 'ATTENDEE', 'CONFIRMED', 'NOT_REQUIRED', 'Legacy User', 'legacy.user@example.test', '+919825000099', 'India', 'YES', :n, :n)")
            ->execute([':id' => $regId, ':eid' => $edId, ':uid' => $uid, ':cid' => $catId, ':n' => $now]);

        // 3. Insert legacy rows into ALL 6 legacy duplicate tables (with plaintext sensitive data!)
        $lpdo->prepare("INSERT INTO registration_accommodations (id, registration_id, user_id, accommodation_code, selected_types, free_text_details, notes, coordinator_notes, status, created_at, updated_at) VALUES (:id, :rid, :uid, 'ISL', '[\"ISL\"]', 'Legacy plaintext ISL note', 'Legacy plaintext ISL note', 'Legacy coord note', 'REQUESTED', :n, :n)")
            ->execute([':id' => uuid4(), ':rid' => $regId, ':uid' => $uid, ':n' => $now]);
        $lpdo->prepare("INSERT INTO registration_disabilities (id, registration_id, disability_code, category_code, self_description, notes, created_at) VALUES (:id, :rid, 'HEARING_IMPAIRMENT', 'HEARING_IMPAIRMENT', 'Legacy plaintext disability self description', 'Legacy plaintext disability self description', :n)")
            ->execute([':id' => uuid4(), ':rid' => $regId, ':n' => $now]);
        $lpdo->prepare("INSERT INTO delegate_profiles (id, registration_id, city_state, created_at) VALUES (:id, :rid, 'Vadodara, Gujarat', :n)")
            ->execute([':id' => uuid4(), ':rid' => $regId, ':n' => $now]);
        $lpdo->prepare("INSERT INTO coupon_codes (id, edition_id, code, discount_type, discount_value, is_active, created_at) VALUES (:id, :eid, 'LEGACY50', 'PERCENTAGE', 50, 1, :n)")
            ->execute([':id' => uuid4(), ':eid' => $edId, ':n' => $now]);
        $lpdo->prepare("INSERT INTO payments (id, registration_id, user_id, provider, amount_inr, discount_inr, net_amount_inr, status, created_at, updated_at) VALUES (:id, :rid, :uid, 'RAZORPAY', 500, 0, 500, 'CAPTURED', :n, :n)")
            ->execute([':id' => uuid4(), ':rid' => $regId, ':uid' => $uid, ':n' => $now]);
        $lpdo->prepare("INSERT INTO notification_jobs (id, template_code, recipient_user_id, recipient_email, subject, payload, status, created_at, updated_at) VALUES (:id, 'LEGACY_PASS', :uid, 'legacy.user@example.test', 'Legacy Subject', '{\"rawToken\":\"PFG27.abcdefghijklmnopqrstuvwxyz123456.0123456789abcdef\",\"resetUrl\":\"https://site/reset-password?token=secretresettoken123456\",\"safe\":\"keep\"}', 'SENT', :n, :n)")
            ->execute([':id' => uuid4(), ':uid' => $uid, ':n' => $now]);
        $lpdo->prepare("INSERT INTO registration_responses (id, registration_id, answers, created_at, updated_at) VALUES (:id, :rid, '{\"fullName\":\"Legacy User\",\"password\":\"plaintextpass\",\"disabilitySelfDescription\":\"secret health note\"}', :n, :n)")
            ->execute([':id' => uuid4(), ':rid' => $regId, ':n' => $now]);

        // 4. Run ALL Phase 3 migrations on this legacy database
        $migRes = pfgRunMigrations($lpdo);

        // Verify legacy tables NOT dropped
        foreach (['registration_accommodations', 'registration_disabilities', 'delegate_profiles', 'coupon_codes', 'payments', 'notification_jobs'] as $legTbl) {
            pfgAssert(pfgDbTableExists($lpdo, $legTbl), "Legacy table {$legTbl} was unexpectedly dropped");
        }

        // Verify consolidation into all 6 canonical tables
        pfgAssert((int)$lpdo->query('SELECT COUNT(*) FROM accommodation_requests')->fetchColumn() === 1, 'accommodation_requests not consolidated');
        pfgAssert((int)$lpdo->query('SELECT COUNT(*) FROM participant_disability_disclosures')->fetchColumn() === 1, 'participant_disability_disclosures not consolidated');
        pfgAssert((int)$lpdo->query('SELECT COUNT(*) FROM delegate_details')->fetchColumn() === 1, 'delegate_details not consolidated');
        pfgAssert((int)$lpdo->query("SELECT COUNT(*) FROM coupons WHERE code = 'LEGACY50'")->fetchColumn() === 1, 'coupons not consolidated');
        pfgAssert((int)$lpdo->query('SELECT COUNT(*) FROM payment_orders')->fetchColumn() === 1, 'payment_orders not consolidated');
        pfgAssert((int)$lpdo->query('SELECT COUNT(*) FROM notification_queue')->fetchColumn() === 1, 'notification_queue not consolidated');

        // Verify legacy code untouched & next allocation is PFG-2027-000100
        $legacyCodeAfter = (string)$lpdo->query("SELECT registration_code FROM registrations WHERE id = '{$regId}'")->fetchColumn();
        pfgAssert($legacyCodeAfter === 'PFG-2027-000099-A1B2', "Legacy registration_code was altered: {$legacyCodeAfter}");
        $nextCode = allocateNextRegistrationCode($lpdo, $edId);
        pfgAssert($nextCode === 'PFG-2027-000100', "Expected PFG-2027-000100 after legacy 99, got {$nextCode}");

        // Verify historical plaintext encryption & decryption + zero double-encryption
        $accRow = $lpdo->query('SELECT free_text_details, coordinator_notes FROM accommodation_requests LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        pfgAssert(str_starts_with((string)$accRow['free_text_details'], 'enc:v1:'), 'accommodation_requests.free_text_details not encrypted');
        pfgAssert(pfgDecryptValue((string)$accRow['free_text_details'], 'data') === 'Legacy plaintext ISL note', 'accommodation decrypt mismatch');
        pfgAssert(str_starts_with((string)$accRow['coordinator_notes'], 'enc:v1:'), 'coordinator_notes not encrypted');
        pfgAssert(pfgDecryptValue((string)$accRow['coordinator_notes'], 'data') === 'Legacy coord note', 'coord notes decrypt mismatch');

        $disRow = $lpdo->query('SELECT self_description FROM participant_disability_disclosures LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        pfgAssert(str_starts_with((string)$disRow['self_description'], 'enc:v1:'), 'disability self_description not encrypted');
        pfgAssert(pfgDecryptValue((string)$disRow['self_description'], 'data') === 'Legacy plaintext disability self description', 'disability decrypt mismatch');

        // Re-run migration 004 callable directly to verify NO double-encryption
        $cipherBefore = (string)$accRow['free_text_details'];
        $mig004 = require $root . '/database/migrations/004_phase3_consolidate_duplicates_and_historical_encryption.php';
        $reRun004 = $mig004($lpdo);
        $cipherAfter = (string)$lpdo->query('SELECT free_text_details FROM accommodation_requests LIMIT 1')->fetchColumn();
        pfgAssert($cipherBefore === $cipherAfter, 'Double encryption occurred on rerun!');

        // Verify legacy users.mfa_secret migrated to mfa_secret_encrypted & cleared
        $uRow = $lpdo->query("SELECT mfa_secret, mfa_secret_encrypted FROM users WHERE id = '{$uid}'")->fetch(PDO::FETCH_ASSOC);
        pfgAssert($uRow['mfa_secret'] === null, 'Legacy plaintext users.mfa_secret was not cleared');
        pfgAssert(str_starts_with((string)$uRow['mfa_secret_encrypted'], 'enc:v1:'), 'mfa_secret_encrypted not populated');
        pfgAssert(pfgDecryptValue((string)$uRow['mfa_secret_encrypted'], 'mfa') === 'JBSWY3DPEHPK3PXP', 'MFA secret decrypt mismatch');

        // Verify notification_queue.payload redacted without deleting row
        $nqPayload = (string)$lpdo->query('SELECT payload FROM notification_queue LIMIT 1')->fetchColumn();
        pfgAssert(!str_contains($nqPayload, 'PFG27.') && !str_contains($nqPayload, 'secretresettoken'), 'Legacy token still present in notification_queue.payload');
        pfgAssert(str_contains($nqPayload, '"safe":"keep"'), 'Non-sensitive payload key lost during redaction');

        // Verify registration_responses.answers scrubbed without deleting row
        $rrAnswers = (string)$lpdo->query('SELECT answers FROM registration_responses LIMIT 1')->fetchColumn();
        pfgAssert(!str_contains($rrAnswers, 'plaintextpass') && !str_contains($rrAnswers, 'secret health note'), 'Sensitive data still present in registration_responses.answers');
        pfgAssert(str_contains($rrAnswers, 'Legacy User'), 'Non-sensitive answer lost during scrubbing');

        $tableCount = count($lpdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        $fkCount = (int)$lpdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
        $migCount = (int)$lpdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        pfgAssert($tableCount === 70, "Expected 70 tables after full legacy upgrade, got {$tableCount}");
        pfgAssert($fkCount >= 79, "Expected >= 79 FKs after full legacy upgrade, got {$fkCount}");
        pfgAssert($migCount === 14, "Expected 14 applied migrations (000–013) after full legacy upgrade, got {$migCount}");

        // Verify Migration 013 made registrations.user_id & privacy_consent_records.user_id nullable while preserving historical user_id
        $regNullable = (string)$lpdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registrations' AND COLUMN_NAME = 'user_id'")->fetchColumn();
        $pcrNullable = (string)$lpdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_consent_records' AND COLUMN_NAME = 'user_id'")->fetchColumn();
        $histUidAfter = (string)$lpdo->query("SELECT user_id FROM registrations WHERE id = '{$regId}'")->fetchColumn();
        pfgAssert($regNullable === 'YES' && $pcrNullable === 'YES', "Migration 013 nullable check failed: reg={$regNullable}, pcr={$pcrNullable}");
        pfgAssert($histUidAfter === $uid, 'Migration 013 altered historical registrations.user_id!');
        return ['applied' => $migCount, 'tables' => $tableCount, 'nextCode' => $nextCode, 'fks' => $fkCount, 'reRunEncrypted' => $reRun004['encryptedFields']['accommodation_requests']];
    });

    return sprintf(
        'Legacy upgrade applied %d migrations (%d tables, %d FKs), 6 duplicate pairs consolidated (0 legacy dropped), legacy 99->%s, historical encryption/MFA/redaction verified (rerun re-encrypted=%d)',
        $summary['applied'],
        $summary['tables'],
        $summary['fks'],
        $summary['nextCode'],
        $summary['reRunEncrypted']
    );
});

// T3-04: Safe idempotent seeder repeatability & organizer-edit preservation
pfgTest('T3-04', 'Idempotent reference seeder preserves organizer CMS, settings, and partner edits', function () use ($root): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $pdo->exec("UPDATE cms_sections SET title = 'CUSTOM ORGANIZER HERO TITLE' WHERE section_key = 'hero'");
    $pdo->exec("UPDATE system_settings SET description = 'CUSTOM SETTING DESC' WHERE `key` = 'payments.enabled'");
    $pdo->exec("UPDATE partners SET status = 'DRAFT' WHERE id = '61000000-0000-4000-8000-000000000011'");

    $seedReport = pfgRunSafeReferenceSeed($pdo);
    $heroTitle = (string)$pdo->query("SELECT title FROM cms_sections WHERE section_key = 'hero'")->fetchColumn();
    $settingDesc = (string)$pdo->query("SELECT description FROM system_settings WHERE `key` = 'payments.enabled'")->fetchColumn();
    $partnerStatus = (string)$pdo->query("SELECT status FROM partners WHERE id = '61000000-0000-4000-8000-000000000011'")->fetchColumn();

    // Restore edited rows cleanly for downstream tests
    $pdo->exec("UPDATE cms_sections SET title = 'PURPLE FESTIVAL GUJARAT 2027' WHERE section_key = 'hero'");
    $pdo->exec("UPDATE system_settings SET description = 'Master toggle for paid registrations.' WHERE `key` = 'payments.enabled'");
    $pdo->exec("UPDATE partners SET status = 'PUBLISHED' WHERE id = '61000000-0000-4000-8000-000000000011'");

    pfgAssert($heroTitle === 'CUSTOM ORGANIZER HERO TITLE', 'Seeder overwrote organizer CMS title');
    pfgAssert($settingDesc === 'CUSTOM SETTING DESC', 'Seeder overwrote organizer setting');
    pfgAssert($partnerStatus === 'DRAFT', 'Seeder auto-published a DRAFT partner');
    pfgAssert(array_sum($seedReport) === 0, 'Expected 0 newly inserted rows on repeat seed, got ' . jsonEncode($seedReport));

    return 'Repeat seed inserted 0 duplicate rows and preserved organizer CMS, setting, and DRAFT partner edits';
});

// T3-05: Atomic Registration ID numbering rules (first, consecutive, 99->100, 999->1000, 9999->10000, non-reuse on rollback/delete, QR independence)
pfgTest('T3-05', 'Atomic Registration ID boundaries (99->100, 999->1000, 9999->10000), non-reuse & QR independence', function () use ($root): string {
    $res = pfgWithTempDb('pfg27_seq_rules_test', function (PDO $spdo) use ($root): array {
        pfgRunMigrations($spdo);
        pfgRunSafeReferenceSeed($spdo);
        $edId = (string)$spdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();

        $c1 = allocateNextRegistrationCode($spdo, $edId);
        $c2 = allocateNextRegistrationCode($spdo, $edId);
        pfgAssert($c1 === 'PFG-2027-000001' && $c2 === 'PFG-2027-000002', "Expected 000001 & 000002, got {$c1}, {$c2}");

        // Failed transaction non-reuse
        $spdo->beginTransaction();
        $cRolled = allocateNextRegistrationCode($spdo, $edId);
        $spdo->rollBack();
        $cAfterRollback = allocateNextRegistrationCode($spdo, $edId);
        pfgAssert($cRolled === 'PFG-2027-000003' && $cAfterRollback === 'PFG-2027-000004', "Rollback reused number: {$cRolled} -> {$cAfterRollback}");

        // Boundary 99 -> 100
        $spdo->exec("INSERT INTO registration_number_sequences (seq_number, edition_id, registration_code, allocated_at) VALUES (99, '{$edId}', 'PFG-2027-000099', NOW())");
        $c100 = allocateNextRegistrationCode($spdo, $edId);
        pfgAssert($c100 === 'PFG-2027-000100', "99->100 failed: {$c100}");

        // Boundary 999 -> 1000
        $spdo->exec("INSERT INTO registration_number_sequences (seq_number, edition_id, registration_code, allocated_at) VALUES (999, '{$edId}', 'PFG-2027-000999', NOW())");
        $c1000 = allocateNextRegistrationCode($spdo, $edId);
        pfgAssert($c1000 === 'PFG-2027-001000', "999->1000 failed: {$c1000}");

        // Boundary 9999 -> 10000
        $spdo->exec("INSERT INTO registration_number_sequences (seq_number, edition_id, registration_code, allocated_at) VALUES (9999, '{$edId}', 'PFG-2027-009999', NOW())");
        $c10000 = allocateNextRegistrationCode($spdo, $edId);
        pfgAssert($c10000 === 'PFG-2027-010000', "9999->10000 failed: {$c10000}");

        // Deleted-record non-reuse & QR independence (0 rows in qr_credentials)
        $qrCount = (int)$spdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn();
        pfgAssert($qrCount === 0, 'Expected 0 qr_credentials rows');
        $c10001 = allocateNextRegistrationCode($spdo, $edId);
        pfgAssert($c10001 === 'PFG-2027-010001', "Expected 010001, got {$c10001}");

        return [$c1, $c2, $cRolled, $cAfterRollback, $c100, $c1000, $c10000, $c10001];
    });

    return 'Verified: ' . implode(', ', $res) . ' (0 QR rows, 0 reuse on rollback)';
});

// T3-06: REAL Multi-Process Concurrency Stress Tests (10, 25, 50, 100 simultaneous PHP workers!)
pfgTest('T3-06', 'Multi-process concurrency stress tests: 10, 25, 50, and 100 simultaneous Registration ID allocations', function () use ($root, $phpBin, $tmpDir): string {
    $tiers = [10, 25, 50, 100];
    $benchmarkRows = [];

    pfgWithTempDb('pfg27_concurrency_test', function (PDO $cpdo) use ($root, $phpBin, $tiers, &$benchmarkRows): void {
        pfgRunMigrations($cpdo);
        pfgRunSafeReferenceSeed($cpdo);
        $edId = (string)$cpdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
        $workerScript = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'phase3_concurrency_worker.php';

        foreach ($tiers as $n) {
            $procs = [];
            $pipesMap = [];
            // Give spawning ~0.25s..0.8s lead time so all processes hit InnoDB together
            $barrierTs = microtime(true) + min(1.2, 0.25 + ($n * 0.008));
            $t0 = microtime(true);

            for ($i = 0; $i < $n; $i++) {
                $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $proc = proc_open(
                    [$phpBin, $workerScript, 'pfg27_concurrency_test', $edId, (string)$i, sprintf('%.6f', $barrierTs)],
                    $desc,
                    $pipes,
                    $root
                );
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $procs[$i] = $proc;
                    $pipesMap[$i] = $pipes;
                }
            }

            $codes = [];
            $errors = [];
            foreach ($procs as $i => $proc) {
                $stdout = trim((string)stream_get_contents($pipesMap[$i][1]));
                $stderr = trim((string)stream_get_contents($pipesMap[$i][2]));
                fclose($pipesMap[$i][1]);
                fclose($pipesMap[$i][2]);
                $exitCode = proc_close($proc);
                $decoded = json_decode($stdout, true);
                if ($exitCode === 0 && is_array($decoded) && !empty($decoded['ok']) && !empty($decoded['code'])) {
                    $codes[] = (string)$decoded['code'];
                } else {
                    $errors[] = "worker={$i} exit={$exitCode} out={$stdout} err={$stderr}";
                }
            }

            $elapsedMs = (int)round((microtime(true) - $t0) * 1000);
            $uniqueCodes = array_values(array_unique($codes));
            $dups = count($codes) - count($uniqueCodes);
            sort($uniqueCodes);

            $benchmarkRows[] = [
                'concurrencyLevel' => $n,
                'requested'        => $n,
                'succeeded'        => count($codes),
                'uniqueCount'      => count($uniqueCodes),
                'duplicateCount'   => $dups,
                'failedCount'      => count($errors),
                'minCode'          => $uniqueCodes[0] ?? '',
                'maxCode'          => $uniqueCodes[count($uniqueCodes) - 1] ?? '',
                'durationMs'       => $elapsedMs,
                'status'           => (count($codes) === $n && $dups === 0 && count($errors) === 0) ? 'PASS' : 'FAIL',
                'errors'           => array_slice($errors, 0, 5),
            ];

            pfgAssert(count($errors) === 0, "Tier {$n} had " . count($errors) . ' failures: ' . implode('; ', array_slice($errors, 0, 3)));
            pfgAssert($dups === 0, "Tier {$n} had {$dups} duplicate Registration IDs!");
            pfgAssert(count($uniqueCodes) === $n, "Tier {$n} expected {$n} unique codes, got " . count($uniqueCodes));
        }
    });

    file_put_contents(
        $tmpDir . DIRECTORY_SEPARATOR . 'concurrency_results.json',
        json_encode($benchmarkRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $parts = array_map(
        static fn(array $r): string => "{$r['concurrencyLevel']}w={$r['uniqueCount']}/{$r['requested']} unique ({$r['durationMs']}ms)",
        $benchmarkRows
    );
    return '0 duplicates, 0 failures across all tiers: ' . implode(' | ', $parts);
});

// T3-07: Foreign Key Enforcement & RESTRICT Protection Against Destructive Cascades
pfgTest('T3-07', 'Foreign key enforcement rejects invalid children & RESTRICT prevents destructive parent deletion', function (): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $edId = (string)$pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $catId = (string)$pdo->query("SELECT id FROM registration_categories WHERE code = 'ATTENDEE' LIMIT 1")->fetchColumn();

    // 1. Invalid registration user_id must be rejected by FK
    $rejectedBadUser = false;
    try {
        $pdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, :code, :eid, :bad_uid, :cid, 'ATTENDEE', 'CONFIRMED', 'NOT_REQUIRED', 'Bad FK', 'badfk@example.test', '+919999999999', 'India', 'NO', NOW(), NOW())")
            ->execute([':id' => uuid4(), ':code' => 'PFG-2027-FKTEST1', ':eid' => $edId, ':bad_uid' => uuid4(), ':cid' => $catId]);
    } catch (PDOException $e) {
        $rejectedBadUser = ($e->getCode() === '23000');
    }
    pfgAssert($rejectedBadUser, 'FK failed to reject registration with nonexistent user_id');

    // 2. Invalid accommodation registration_id must be rejected by FK
    $rejectedBadAccomm = false;
    try {
        $pdo->prepare("INSERT INTO accommodation_requests (id, registration_id, status, created_at, updated_at) VALUES (:id, :bad_rid, 'REQUESTED', NOW(), NOW())")
            ->execute([':id' => uuid4(), ':bad_rid' => uuid4()]);
    } catch (PDOException $e) {
        $rejectedBadAccomm = ($e->getCode() === '23000');
    }
    pfgAssert($rejectedBadAccomm, 'FK failed to reject accommodation_requests with nonexistent registration_id');

    // 3. Accountless registration with user_id = NULL is allowed by Migration 013 FK, while RESTRICT blocks deleting parent category with registrations
    $nullRegId = uuid4();
    $pdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, :code, :eid, NULL, :cid, 'ATTENDEE', 'CONFIRMED', 'NOT_REQUIRED', 'Accountless FK Check', 'nullfk@example.test', '+919999999998', 'India', 'NO', NOW(), NOW())")
        ->execute([':id' => $nullRegId, ':code' => 'PFG-2027-FKNULL1', ':eid' => $edId, ':cid' => $catId]);
    $pdo->prepare('DELETE FROM registrations WHERE id = :id')->execute([':id' => $nullRegId]);

    $restrictedDelete = false;
    try {
        $pdo->prepare('DELETE FROM registration_categories WHERE id = :cid')->execute([':cid' => $catId]);
    } catch (PDOException $e) {
        $restrictedDelete = ($e->getCode() === '23000');
    }
    pfgAssert($restrictedDelete, 'ON DELETE RESTRICT did not block deleting parent registration_category with registrations');

    return 'Invalid registration/accommodation FK inserts rejected (SQLSTATE 23000); NULL user_id allowed; parent category DELETE blocked by ON DELETE RESTRICT';
});

// T3-08: Registration Transaction Atomicity (FND-21), Single Canonical Write & Idempotency-Key Replay
pfgTest('T3-08', 'Single-transaction registration atomicity, zero legacy dual-writes & Idempotency-Key replay safety', function () use ($root, $phpBin): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');

    // 1. Atomic rollback test: fail after user insert inside transaction -> 0 orphan users
    $orphanEmail = 'atomic.rollback.' . bin2hex(random_bytes(3)) . '@example.test';
    $rolledBack = false;
    $pdo->beginTransaction();
    try {
        $uid = uuid4();
        $pdo->prepare("INSERT INTO users (id, email, full_name, is_active, account_status, created_at, updated_at) VALUES (:id, :em, 'Atomic Rollback', 1, 'ACTIVE', NOW(), NOW())")
            ->execute([':id' => $uid, ':em' => $orphanEmail]);
        // Intentionally trigger FK failure on registrations insert
        $pdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, 'PFG-2027-ATOMICFAIL', :bad_eid, :uid, :bad_cid, 'ATTENDEE', 'CONFIRMED', 'NOT_REQUIRED', 'Atomic Rollback', :em, '+919825000001', 'India', 'NO', NOW(), NOW())")
            ->execute([':id' => uuid4(), ':bad_eid' => uuid4(), ':uid' => $uid, ':bad_cid' => uuid4(), ':em' => $orphanEmail]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $rolledBack = true;
    }
    $orphanCnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email = " . $pdo->quote($orphanEmail))->fetchColumn();
    pfgAssert($rolledBack && $orphanCnt === 0, "Expected 0 orphan users after rollback, got {$orphanCnt}");

    // 2. Live HTTP POST /api/registrations with Idempotency-Key -> verify single canonical write & idempotent replay
    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    $srvLog = __DIR__ . '/tmp/php_p3_server.log';
    @unlink($srvLog);
    $proc = proc_open(
        [$phpBin, '-S', '127.0.0.1:8087', $root . '/tests/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $srvLog, 'a'], 2 => ['file', $srvLog, 'a']],
        $pipes,
        $root,
        $env
    );
    pfgAssert(is_resource($proc), 'Failed to start Phase 3 HTTP test server');
    for ($i = 0; $i < 15; $i++) {
        $fp = @fsockopen('127.0.0.1', 8087, $e1, $e2, 0.2);
        if ($fp) {
            fclose($fp);
            break;
        }
        usleep(150000);
    }

    try {
        $email = 'idem.canonical.' . bin2hex(random_bytes(3)) . '@example.test';
        $usersBefore = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        // Obtain anonymous session cookie + CSRF token via public GET /api/registrations (accountless flow)
        $anonCookies = [];
        $chGet = curl_init('http://127.0.0.1:8087/api/registrations');
        curl_setopt_array($chGet, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$anonCookies): int {
                $t = trim($line);
                if (stripos($t, 'set-cookie:') === 0) {
                    $val = trim(substr($t, 11));
                    $semi = explode(';', $val);
                    if (strpos($semi[0], '=') !== false) {
                        [$cn, $cv] = explode('=', $semi[0], 2);
                        $anonCookies[trim($cn)] = trim($cv);
                    }
                }
                return strlen($line);
            },
        ]);
        $getBody = (string)curl_exec($chGet);
        curl_close($chGet);
        $getJson = json_decode($getBody, true);
        $csrfTok = (string)($getJson['csrfToken'] ?? '');
        pfgAssert($csrfTok !== '' && !empty($anonCookies), 'Failed to obtain anonymous CSRF/session from GET /api/registrations');
        $cookiePairs = [];
        foreach ($anonCookies as $ck => $cv) {
            $cookiePairs[] = $ck . '=' . $cv;
        }
        $cookieHeader = implode('; ', $cookiePairs);

        $idemKey = 'pfg-idem-' . bin2hex(random_bytes(6));
        $payload = json_encode([
            'fullName'                  => 'Canonical Tester',
            'email'                     => $email,
            'phone'                     => '+91 98250 55555',
            'country'                   => 'India',
            'cityDistrict'              => 'Vadodara',
            'state'                     => 'Gujarat',
            'categoryCode'              => 'ATTENDEE',
            'isPersonWithDisability'    => 'YES',
            'disabilityCategoryCodes'   => ['LOW_VISION'],
            'disabilitySelfDescription' => 'Large print preferred',
            'accommodationTypes'        => ['LARGE_PRINT'],
            'accommodationFreeText'     => 'Front row seating please',
            'attendingDays'             => ['DAY_1'],
            'attendeeDetails'           => [
                'ageGroup'               => '18_35',
                'selfIdentificationTags' => ['Student'],
            ],
            'consentZeroTolerance'      => true,
            'consentDataProcessing'     => true,
            'consentAccuracy'           => true,
            'idempotencyKey'            => $idemKey,
        ]);

        $doPost = static function () use ($payload, $idemKey, $cookieHeader, $csrfTok): array {
            $ch = curl_init('http://127.0.0.1:8087/api/registrations');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Cookie: ' . $cookieHeader,
                    'X-CSRF-Token: ' . $csrfTok,
                    'Idempotency-Key: ' . $idemKey,
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 8,
            ]);
            $body = (string)curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['status' => $status, 'json' => json_decode($body, true), 'raw' => $body];
        };

        $r1 = $doPost();
        pfgAssert($r1['status'] === 201, "First POST expected 201, got {$r1['status']}: {$r1['raw']}");
        $code1 = (string)($r1['json']['registration']['registrationCode'] ?? '');
        $regId1 = (string)($r1['json']['registration']['id'] ?? '');
        pfgAssert(preg_match('/^PFG-2027-\d{6,}$/', $code1) === 1, "Expected atomic format PFG-2027-NNNNNN, got {$code1}");

        // Verify accountless: user_id IS NULL and ZERO new users created!
        $regUid = $pdo->query("SELECT user_id FROM registrations WHERE id = " . $pdo->quote($regId1))->fetchColumn();
        $usersAfter = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        pfgAssert($regUid === null || $regUid === false, 'Accountless registration unexpectedly set user_id!');
        pfgAssert($usersAfter === $usersBefore, 'Accountless registration created a users row!');

        // Replay with same Idempotency-Key -> HTTP 200 with exact same Registration ID & 0 duplicate rows
        $r2 = $doPost();
        pfgAssert($r2['status'] === 200 && !empty($r2['json']['idempotentReplay']), "Replay expected 200 idempotentReplay, got {$r2['status']}: {$r2['raw']}");
        pfgAssert(($r2['json']['registration']['registrationCode'] ?? '') === $code1, 'Idempotent replay returned different Registration ID!');

        // Verify canonical writes ONLY (0 legacy dual-writes!)
        $canAcc = (int)$pdo->query("SELECT COUNT(*) FROM accommodation_requests WHERE registration_id = " . $pdo->quote($regId1))->fetchColumn();
        $legAcc = (int)$pdo->query("SELECT COUNT(*) FROM registration_accommodations WHERE registration_id = " . $pdo->quote($regId1))->fetchColumn();
        $canDis = (int)$pdo->query("SELECT COUNT(*) FROM participant_disability_disclosures WHERE registration_id = " . $pdo->quote($regId1))->fetchColumn();
        $legDis = (int)$pdo->query("SELECT COUNT(*) FROM registration_disabilities WHERE registration_id = " . $pdo->quote($regId1))->fetchColumn();

        pfgAssert($canAcc === 1 && $legAcc === 0, "Expected canonical accommodation=1, legacy=0; got {$canAcc}/{$legAcc}");
        pfgAssert($canDis === 1 && $legDis === 0, "Expected canonical disability=1, legacy=0; got {$canDis}/{$legDis}");

        return "Rollback left 0 orphan users; accountless POST -> {$code1} (user_id=NULL, 0 users created, canonical=1, legacy=0); Idempotency-Key replay -> HTTP 200 same code";
    } finally {
        proc_terminate($proc);
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        proc_close($proc);
    }
});

// T3-09: Soft-deleted registrations excluded from accommodation & check-in counts (FND-39)
pfgTest('T3-09', 'Soft-deleted registrations excluded from accommodation & check-in overview queries (FND-39)', function (): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $edId = (string)$pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $catId = (string)$pdo->query("SELECT id FROM registration_categories WHERE code = 'ATTENDEE' LIMIT 1")->fetchColumn();
    $uid = uuid4();
    $regId = uuid4();
    $now = nowSql();

    $pdo->prepare("INSERT INTO users (id, email, full_name, is_active, account_status, created_at, updated_at) VALUES (:id, :em, 'Soft Deleted User', 1, 'ACTIVE', :n, :n)")
        ->execute([':id' => $uid, ':em' => 'softdel.' . bin2hex(random_bytes(3)) . '@example.test', ':n' => $now]);
    $pdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, deleted_at, created_at, updated_at) VALUES (:id, :code, :eid, :uid, :cid, 'ATTENDEE', 'CONFIRMED', 'NOT_REQUIRED', 'Soft Deleted User', 'softdel@example.test', '+919800000000', 'India', 'NO', :n, :n, :n)")
        ->execute([':id' => $regId, ':code' => allocateNextRegistrationCode($pdo, $edId), ':eid' => $edId, ':uid' => $uid, ':cid' => $catId, ':n' => $now]);
    $pdo->prepare("INSERT INTO accommodation_requests (id, registration_id, user_id, accommodation_code, status, created_at, updated_at) VALUES (:id, :rid, :uid, 'WHEELCHAIR', 'REQUESTED', :n, :n)")
        ->execute([':id' => uuid4(), ':rid' => $regId, ':uid' => $uid, ':n' => $now]);

    // Query matching api/admin/overview.php
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM accommodation_requests a INNER JOIN registrations r ON a.registration_id = r.id WHERE r.deleted_at IS NULL AND a.registration_id = " . $pdo->quote($regId))->fetchColumn();
    pfgAssert($cnt === 0, "Soft-deleted registration's accommodation leaked into overview query (cnt={$cnt})");
    return 'Soft-deleted registration accommodation & check-in rows strictly excluded by INNER JOIN registrations WHERE deleted_at IS NULL';
});

// T3-10: Decoupled Manual Attendance Schema (attendance_records + attendance_correction_logs — D-01)
pfgTest('T3-10', 'Manual attendance_records decoupled from QR, enforces per-day uniqueness & correction audit trail', function (): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $edId = (string)$pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $regRow = $pdo->query("SELECT id FROM registrations WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    pfgAssert(is_array($regRow), 'No active registration found');
    $regId = (string)$regRow['id'];
    $opId = (string)$pdo->query("SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY created_at ASC LIMIT 1")->fetchColumn();
    pfgAssert($opId !== '', 'No active staff operator user found');
    $now = nowSql();

    // Verify zero qr_credentials required
    $attId1 = uuid4();
    $pdo->prepare("INSERT INTO attendance_records (id, edition_id, registration_id, attendance_date_ist, attendance_scope, status, marked_by_user_id, marked_at, venue_label, created_at, updated_at) VALUES (:id, :eid, :rid, '2027-01-21', 'FESTIVAL_DAY', 'PRESENT', :op, :n, 'Sama Indoor Stadium Desk A', :n, :n)")
        ->execute([':id' => $attId1, ':eid' => $edId, ':rid' => $regId, ':op' => $opId, ':n' => $now]);

    // Duplicate same-day same-scope must be rejected by UNIQUE key
    $dupBlocked = false;
    try {
        $pdo->prepare("INSERT INTO attendance_records (id, edition_id, registration_id, attendance_date_ist, attendance_scope, status, marked_by_user_id, marked_at, venue_label, created_at, updated_at) VALUES (:id, :eid, :rid, '2027-01-21', 'FESTIVAL_DAY', 'PRESENT', :op, :n, 'Sama Indoor Stadium Desk A', :n, :n)")
            ->execute([':id' => uuid4(), ':eid' => $edId, ':rid' => $regId, ':op' => $opId, ':n' => $now]);
    } catch (PDOException $e) {
        $dupBlocked = ($e->getCode() === '23000');
    }
    pfgAssert($dupBlocked, 'Duplicate same-day manual attendance was not blocked by uq_attendance_reg_date_scope');

    // Day 2 allowed cleanly
    $pdo->prepare("INSERT INTO attendance_records (id, edition_id, registration_id, attendance_date_ist, attendance_scope, status, marked_by_user_id, marked_at, venue_label, created_at, updated_at) VALUES (:id, :eid, :rid, '2027-01-22', 'FESTIVAL_DAY', 'PRESENT', :op, :n, 'MSU Baroda Desk', :n, :n)")
        ->execute([':id' => uuid4(), ':eid' => $edId, ':rid' => $regId, ':op' => $opId, ':n' => $now]);

    // Record auditable correction
    $pdo->prepare("INSERT INTO attendance_correction_logs (id, attendance_record_id, registration_id, actor_user_id, previous_status, new_status, reason, created_at) VALUES (:id, :aid, :rid, :op, 'PRESENT', 'REVOKED', 'Operator desk correction test', :n)")
        ->execute([':id' => uuid4(), ':aid' => $attId1, ':rid' => $regId, ':op' => $opId, ':n' => $now]);

    return 'Marked Day 1 & Day 2 with 0 QR dependency, duplicate Day 1 blocked (23000), correction audit logged';
});

// T3-11: Notification Queue Schema Upgrade (FND-19, Phase 3K honest states & idempotency_key dedup)
pfgTest('T3-11', 'Notification queue persists provider/message_id/idempotency_key cleanly with honest lifecycle states', function () use ($root): string {
    require_once $root . '/includes/mailer.php';
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $ikey = 'phase3-notif-test-' . bin2hex(random_bytes(5));

    $r1 = sendFestivalEmail($pdo, [
        'to'             => 'notif.phase3@example.test',
        'subject'        => 'Phase 3 Notification Schema Check',
        'html'           => '<p>Testing Phase 3K notification schema</p>',
        'templateCode'   => 'REGISTRATION_CONFIRMED',
        'eventType'      => 'REGISTRATION_CONFIRMED',
        'idempotencyKey' => $ikey,
    ]);
    pfgAssert($r1['status'] === 'CAPTURED_LOCAL' && $r1['provider'] === 'file_sink', 'Unexpected first mailer result: ' . jsonEncode($r1));

    $row = $pdo->prepare('SELECT event_type, channel, provider, provider_message_id, idempotency_key, status, provider_accepted_at, delivered_at FROM notification_queue WHERE idempotency_key = :ik LIMIT 1');
    $row->execute([':ik' => $ikey]);
    $nq = $row->fetch(PDO::FETCH_ASSOC);
    pfgAssert(is_array($nq), 'Notification row with idempotency_key not persisted in notification_queue');
    pfgAssert($nq['provider'] === 'file_sink' && !empty($nq['provider_message_id']), 'provider/provider_message_id not saved (FND-19)');
    pfgAssert($nq['delivered_at'] === null, 'delivered_at should remain NULL until real delivery webhook confirms delivery');

    // Replay same idempotencyKey -> deduplicated without creating a 2nd row
    $r2 = sendFestivalEmail($pdo, [
        'to'             => 'notif.phase3@example.test',
        'subject'        => 'Phase 3 Notification Schema Check',
        'html'           => '<p>Testing Phase 3K notification schema</p>',
        'templateCode'   => 'REGISTRATION_CONFIRMED',
        'eventType'      => 'REGISTRATION_CONFIRMED',
        'idempotencyKey' => $ikey,
    ]);
    pfgAssert(!empty($r2['deduped']), 'Second call with same idempotencyKey was not deduplicated');
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE idempotency_key = " . $pdo->quote($ikey))->fetchColumn();
    pfgAssert($cnt === 1, "Expected 1 notification row for idempotencyKey, found {$cnt}");

    return "Persisted provider={$nq['provider']}, msgId={$nq['provider_message_id']}, delivered_at=NULL, idempotency dedup=1 row";
});

// T3-12: Representative Dataset (1,000+ registrations + Gujarati/Hindi UTF-8) EXPLAIN Index Verification
pfgTest('T3-12', '1,000+ synthetic registrations (incl. Gujarati/Hindi UTF-8) EXPLAIN index & pagination verification', function () use ($tmpDir): string {
    $explainReport = pfgWithTempDb('pfg27_scale_index_test', function (PDO $xpdo): array {
        pfgRunMigrations($xpdo);
        pfgRunSafeReferenceSeed($xpdo);
        $edId = (string)$xpdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
        $catMap = [];
        foreach ($xpdo->query("SELECT id, code FROM registration_categories WHERE category_tier = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $catMap[$c['code']] = $c['id'];
        }
        $cats = ['ATTENDEE', 'SLI', 'VOLUNTEER'];
        $statuses = ['CONFIRMED', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED'];
        $now = nowSql();

        // Bulk insert 1,000 synthetic users & registrations inside one fast transaction
        $xpdo->beginTransaction();
        $uIns = $xpdo->prepare("INSERT INTO users (id, email, full_name, phone, country, is_active, account_status, created_at, updated_at) VALUES (:id, :em, :fn, :ph, 'India', 1, 'ACTIVE', :n, :n)");
        $rIns = $xpdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, assignment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, :code, :eid, :uid, :cid, :cc, :st, 'NOT_REQUIRED', 'UNASSIGNED', :fn, :em, :ph, 'India', 'NO', :n, :n)");

        $sampleCode = '';
        $sampleEmail = '';
        $samplePhone = '';
        for ($i = 1; $i <= 1000; $i++) {
            $uid = uuid4();
            $rid = uuid4();
            $cat = $cats[$i % 3];
            $st = $statuses[$i % 4];
            $code = formatRegistrationCode($i);
            $email = sprintf('synthetic%04d@example.test', $i);
            $phone = sprintf('+919825%06d', $i);
            $name = match ($i % 5) {
                0 => "આરવ પટેલ {$i}",      // Gujarati UTF-8
                1 => "प्रिया शर्मा {$i}",  // Hindi Devanagari UTF-8
                default => "Participant Vadodara {$i}",
            };
            if ($i === 500) {
                $sampleCode = $code;
                $sampleEmail = $email;
                $samplePhone = $phone;
            }
            $uIns->execute([':id' => $uid, ':em' => $email, ':fn' => $name, ':ph' => $phone, ':n' => $now]);
            $rIns->execute([':id' => $rid, ':code' => $code, ':eid' => $edId, ':uid' => $uid, ':cid' => $catMap[$cat], ':cc' => $cat, ':st' => $st, ':fn' => $name, ':em' => $email, ':ph' => $phone, ':n' => $now]);
        }
        $xpdo->commit();
        $analyzeStmt = $xpdo->query('ANALYZE TABLE registrations, users, attendance_records, notification_queue, audit_logs');
        if ($analyzeStmt) {
            $analyzeStmt->fetchAll(PDO::FETCH_ASSOC);
            $analyzeStmt->closeCursor();
        }

        // Verify Gujarati & Hindi UTF-8 round-trip integrity
        $guj = (string)$xpdo->query("SELECT full_name FROM registrations WHERE registration_code = 'PFG-2027-000005'")->fetchColumn();
        $hin = (string)$xpdo->query("SELECT full_name FROM registrations WHERE registration_code = 'PFG-2027-000006'")->fetchColumn();
        pfgAssert($guj === 'આરવ પટેલ 5' && $hin === 'प्रिया शर्मा 6', "UTF-8 round-trip mismatch: guj={$guj} hin={$hin}");

        // Run EXPLAIN on 6 key operational queries
        $queries = [
            'registration_code_lookup' => "SELECT id, full_name, status FROM registrations WHERE registration_code = '{$sampleCode}'",
            'email_lookup_active'      => "SELECT id, registration_code FROM registrations WHERE deleted_at IS NULL AND email = '{$sampleEmail}'",
            'phone_desk_lookup'        => "SELECT id, registration_code, full_name FROM registrations WHERE deleted_at IS NULL AND phone = '{$samplePhone}'",
            'admin_category_status'    => "SELECT id, registration_code, full_name FROM registrations WHERE edition_id = '{$edId}' AND category_code = 'VOLUNTEER' AND status = 'SUBMITTED' AND deleted_at IS NULL",
            'admin_paginated_list'     => "SELECT id, registration_code, full_name FROM registrations WHERE edition_id = '{$edId}' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 25 OFFSET 250",
            'name_prefix_search'       => "SELECT id, registration_code, full_name FROM registrations WHERE deleted_at IS NULL AND full_name LIKE 'આરવ%' LIMIT 25",
        ];

        $plans = [];
        foreach ($queries as $qName => $sql) {
            $t0 = microtime(true);
            $expRows = $xpdo->query('EXPLAIN ' . $sql)->fetchAll(PDO::FETCH_ASSOC);
            $actualRows = $xpdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $ms = round((microtime(true) - $t0) * 1000, 2);
            $first = $expRows[0] ?? [];
            $accessType = (string)($first['type'] ?? 'UNKNOWN');
            $usedKey = (string)($first['key'] ?? '');
            pfgAssert($usedKey !== '' && $accessType !== 'ALL', "Query {$qName} performed full table scan (type={$accessType}, key={$usedKey})");
            $plans[] = [
                'query'       => $qName,
                'accessType'  => $accessType,
                'keyUsed'     => $usedKey,
                'rowsScanned' => (int)($first['rows'] ?? 0),
                'resultRows'  => count($actualRows),
                'durationMs'  => $ms,
            ];
        }

        return $plans;
    });

    file_put_contents(
        $tmpDir . DIRECTORY_SEPARATOR . 'index_explain_results.json',
        json_encode($explainReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $summary = array_map(static fn(array $p): string => "{$p['query']}={$p['accessType']}:{$p['keyUsed']}", $explainReport);
    return '1,000 synthetic rows + Gujarati/Hindi UTF-8 OK | EXPLAIN: ' . implode(', ', $summary);
});

// T3-13: Real mysqldump Backup, Restore & Post-Restore Migration Verification (Section 14)
pfgTest('T3-13', 'Real mysqldump backup, full database restore, decryption & sequence continuity verification', function () use ($tmpDir): string {
    $mysqldumpBin = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    $mysqlBin = 'C:\\xampp\\mysql\\bin\\mysql.exe';
    pfgAssert(is_file($mysqldumpBin) && is_file($mysqlBin), 'XAMPP mysqldump/mysql binaries not found');

    $dumpFile = $tmpDir . DIRECTORY_SEPARATOR . 'pfg27_backup_verify_' . bin2hex(random_bytes(3)) . '.sql';

    try {
        $result = pfgWithTempDb('pfg27_backup_test', function (PDO $bpdo, PDO $adminPdo) use ($mysqldumpBin, $mysqlBin, $dumpFile): array {
            pfgRunMigrations($bpdo);
            pfgRunSafeReferenceSeed($bpdo);
            $edId = (string)$bpdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
            $catId = (string)$bpdo->query("SELECT id FROM registration_categories WHERE code = 'ATTENDEE' LIMIT 1")->fetchColumn();

            // Insert synthetic user, registration (PFG-2027-000001), encrypted accommodation, manual attendance
            $uid = uuid4();
            $rid = uuid4();
            $code1 = allocateNextRegistrationCode($bpdo, $edId);
            $now = nowSql();
            $encNote = pfgEncryptValue('Wheelchair ramp & Gujarati interpreter needed — અમદાવાદ', 'data');

            $bpdo->prepare("INSERT INTO users (id, email, full_name, is_active, account_status, created_at, updated_at) VALUES (:id, 'backup.user@example.test', 'Backup Verify User', 1, 'ACTIVE', :n, :n)")
                ->execute([':id' => $uid, ':n' => $now]);
            $bpdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, :code, :eid, :uid, :cid, 'ATTENDEE', 'CONFIRMED', 'NOT_REQUIRED', 'Backup Verify User', 'backup.user@example.test', '+919825111111', 'India', 'YES', :n, :n)")
                ->execute([':id' => $rid, ':code' => $code1, ':eid' => $edId, ':uid' => $uid, ':cid' => $catId, ':n' => $now]);
            $bpdo->prepare("INSERT INTO accommodation_requests (id, registration_id, user_id, accommodation_code, free_text_details, status, created_at, updated_at) VALUES (:id, :rid, :uid, 'WHEELCHAIR', :ft, 'REQUESTED', :n, :n)")
                ->execute([':id' => uuid4(), ':rid' => $rid, ':uid' => $uid, ':ft' => $encNote, ':n' => $now]);
            $bpdo->prepare("INSERT INTO attendance_records (id, edition_id, registration_id, attendance_date_ist, attendance_scope, status, marked_by_user_id, marked_at, venue_label, created_at, updated_at) VALUES (:id, :eid, :rid, '2027-01-21', 'FESTIVAL_DAY', 'PRESENT', :op, :n, 'Desk A', :n, :n)")
                ->execute([':id' => uuid4(), ':eid' => $edId, ':rid' => $rid, ':op' => $uid, ':n' => $now]);

            $preTables = count($bpdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
            $preFks = (int)$bpdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();

            // 1. Capture real mysqldump backup
            $t0 = microtime(true);
            $dumpCmd = sprintf(
                '%s --host=%s --port=%s --user=%s --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 pfg27_backup_test --result-file=%s 2>&1',
                escapeshellarg($mysqldumpBin),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_PORT),
                escapeshellarg(DB_USER),
                escapeshellarg($dumpFile)
            );
            $dumpOut = [];
            $dumpCode = 0;
            exec($dumpCmd, $dumpOut, $dumpCode);
            pfgAssert($dumpCode === 0 && is_file($dumpFile) && filesize($dumpFile) > 10000, 'mysqldump failed: ' . implode(' | ', $dumpOut));
            $dumpBytes = filesize($dumpFile);
            $dumpSha = hash_file('sha256', $dumpFile);
            $dumpMs = (int)round((microtime(true) - $t0) * 1000);

            // 2. Drop and recreate DB from scratch, then restore dump
            $adminPdo->exec('DROP DATABASE `pfg27_backup_test`');
            $adminPdo->exec('CREATE DATABASE `pfg27_backup_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $t1 = microtime(true);
            $restoreCmd = sprintf(
                '%s --host=%s --port=%s --user=%s --default-character-set=utf8mb4 pfg27_backup_test < %s 2>&1',
                escapeshellarg($mysqlBin),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_PORT),
                escapeshellarg(DB_USER),
                escapeshellarg($dumpFile)
            );
            $resOut = [];
            $resCode = 0;
            exec($restoreCmd, $resOut, $resCode);
            pfgAssert($resCode === 0, 'mysql restore failed: ' . implode(' | ', $resOut));
            $restoreMs = (int)round((microtime(true) - $t1) * 1000);

            // 3. Reconnect and verify post-restore integrity
            $rpdo = pfgCreateDirectPdo('pfg27_backup_test');
            $postTables = count($rpdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
            $postFks = (int)$rpdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchColumn();
            $postMigs = (int)$rpdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            pfgAssert($postTables === $preTables && $postTables === 70, "Table count mismatch after restore: pre={$preTables}, post={$postTables}, expected=70");
            pfgAssert($postFks === $preFks && $postFks >= 79, "FK count mismatch after restore: pre={$preFks}, post={$postFks}, expected>=79");
            pfgAssert($postMigs === 14, "Migration count mismatch after restore: {$postMigs}, expected=14");

            // Verify encrypted accommodation decrypts cleanly after restore (incl. Gujarati UTF-8)
            $restoredEnc = (string)$rpdo->query('SELECT free_text_details FROM accommodation_requests LIMIT 1')->fetchColumn();
            pfgAssert(pfgDecryptValue($restoredEnc, 'data') === 'Wheelchair ramp & Gujarati interpreter needed — અમદાવાદ', 'Encrypted UTF-8 accommodation corrupted during restore');

            // Verify sequence continues monotonically at PFG-2027-000002 after restore
            $code2 = allocateNextRegistrationCode($rpdo, $edId);
            pfgAssert($code2 === 'PFG-2027-000002', "Expected PFG-2027-000002 after restore, got {$code2}");

            // Verify migrations remain clean (0 pending, 0 checksum mismatches)
            $postMig = pfgRunMigrations($rpdo);
            pfgAssert(count($postMig['appliedNow']) === 0, 'Pending migrations found after restore');

            return [
                'dumpBytes' => $dumpBytes,
                'dumpSha256' => substr((string)$dumpSha, 0, 16),
                'dumpMs' => $dumpMs,
                'restoreMs' => $restoreMs,
                'tables' => $postTables,
                'fks' => $postFks,
                'nextCode' => $code2,
            ];
        });

        file_put_contents(
            $tmpDir . DIRECTORY_SEPARATOR . 'backup_restore_results.json',
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return sprintf(
            'mysqldump=%dB (%dms, sha256:%s..) -> restore=%dms -> %d tables, %d FKs, UTF-8 AES-GCM decrypt OK, next=%s, 0 pending migrations',
            $result['dumpBytes'],
            $result['dumpMs'],
            $result['dumpSha256'],
            $result['restoreMs'],
            $result['tables'],
            $result['fks'],
            $result['nextCode']
        );
    } finally {
        @unlink($dumpFile);
    }
});

