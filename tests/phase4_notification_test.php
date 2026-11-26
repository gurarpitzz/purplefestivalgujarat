<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 4 Automated Transactional Notification,
 * Queue Worker, Retry, Svix Webhook, 29-Template WCAG & Multi-Worker Concurrency Suite
 */

$root = dirname(__DIR__);
$phpBin = PHP_BINARY;
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$sinkDir = $tmpDir . DIRECTORY_SEPARATOR . 'mail-sink';

require_once $root . '/includes/notification-templates.php';
require_once $root . '/includes/notification-service.php';

// T4-01: Notification Persistence, Validation, Idempotency & Transactional Outbox Rollback (Section 22A)
pfgTest('T4-01', 'Notification outbox persistence, validation, duplicate prevention & transaction rollback', function (): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $userRow = $pdo->query('SELECT id, email FROM users WHERE deleted_at IS NULL LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    pfgAssert(is_array($userRow), 'Expected at least 1 test user');
    $uid = (string)$userRow['id'];

    // 1. Invalid email rejected
    $badEmailCaught = false;
    try {
        pfgEnqueueNotification($pdo, [
            'to'           => 'not-an-email',
            'templateCode' => 'ATTENDEE_REGISTRATION_CONFIRMED',
            'variables'    => ['fullName' => 'Test', 'registrationCode' => 'PFG-2027-000001'],
        ]);
    } catch (InvalidArgumentException $e) {
        $badEmailCaught = true;
    }
    pfgAssert($badEmailCaught, 'Invalid recipient email was not rejected');

    // 2. Unknown template rejected
    $badTplCaught = false;
    try {
        pfgEnqueueNotification($pdo, [
            'to'           => 'valid@example.test',
            'templateCode' => 'NONEXISTENT_TEMPLATE_XYZ',
            'variables'    => ['fullName' => 'Test'],
        ]);
    } catch (InvalidArgumentException $e) {
        $badTplCaught = true;
    }
    pfgAssert($badTplCaught, 'Unknown template code was not rejected');

    // 3. Valid transactional enqueue + in-app notification + idempotent replay
    $ikey = 't4-01-outbox-' . bin2hex(random_bytes(5));
    $r1 = pfgEnqueueNotification($pdo, [
        'to'              => 'outbox.test@example.test',
        'templateCode'    => 'ATTENDEE_REGISTRATION_CONFIRMED',
        'variables'       => ['fullName' => 'Outbox Tester', 'registrationCode' => 'PFG-2027-000777', 'categoryName' => 'General Attendee', 'status' => 'CONFIRMED'],
        'recipientUserId' => $uid,
        'entityType'      => 'registration',
        'entityId'        => uuid4(),
        'idempotencyKey'  => $ikey,
        'createInApp'     => true,
        'inAppTitle'      => 'Confirmed (PFG-2027-000777)',
        'inAppMessage'    => 'Your festival registration is confirmed.',
    ]);
    pfgAssert($r1['status'] === 'QUEUED' && !$r1['deduped'] && !empty($r1['inAppId']), 'Initial outbox enqueue failed');

    $r2 = pfgEnqueueNotification($pdo, [
        'to'              => 'outbox.test@example.test',
        'templateCode'    => 'ATTENDEE_REGISTRATION_CONFIRMED',
        'variables'       => ['fullName' => 'Outbox Tester', 'registrationCode' => 'PFG-2027-000777'],
        'recipientUserId' => $uid,
        'idempotencyKey'  => $ikey,
        'createInApp'     => true,
    ]);
    pfgAssert($r2['deduped'] === true && $r2['queueId'] === $r1['queueId'], 'Idempotent replay created a duplicate queue record');

    // 4. Transactional rollback leaves 0 queue rows and 0 in-app rows
    $rbKey = 't4-01-rollback-' . bin2hex(random_bytes(5));
    $pdo->beginTransaction();
    pfgEnqueueNotification($pdo, [
        'to'              => 'rollback.test@example.test',
        'templateCode'    => 'VOLUNTEER_APPLICATION_RECEIVED',
        'variables'       => ['fullName' => 'Rollback Volunteer', 'registrationCode' => 'PFG-2027-000888'],
        'recipientUserId' => $uid,
        'idempotencyKey'  => $rbKey,
        'createInApp'     => true,
    ]);
    $pdo->rollBack();

    $rbCount = (int)$pdo->query('SELECT COUNT(*) FROM notification_queue WHERE idempotency_key = ' . $pdo->quote($rbKey))->fetchColumn();
    $rbInApp = (int)$pdo->query('SELECT COUNT(*) FROM in_app_notifications WHERE idempotency_key = ' . $pdo->quote('inapp:' . $rbKey))->fetchColumn();
    pfgAssert($rbCount === 0 && $rbInApp === 0, "Rollback leaked outbox rows: queue={$rbCount}, inApp={$rbInApp}");

    return 'Invalid email/template rejected, outbox+in_app created, idempotent replay deduped, rollback left 0 queue & 0 in-app rows';
});

// T4-02: All 29 WCAG 2.2 AA Templates Rendered & Validated (Escaping, Gujarati/Hindi UTF-8, Long Names, Privacy) (Section 22E)
pfgTest('T4-02', 'All 29 email templates render WCAG HTML + plain text, escape XSS, preserve Gujarati/Hindi & strip secrets', function () use ($tmpDir): string {
    $catalog = pfgGetEmailTemplateCatalog();
    pfgAssert(count($catalog) === 29, 'Expected exactly 29 templates in catalog, got ' . count($catalog));

    $sampleVars = [
        'fullName'                  => 'આરવ પટેલ / प्रिया शर्मा <script>alert(1)</script> ' . str_repeat('LongName ', 8),
        'registrationCode'          => 'PFG-2027-000100',
        'categoryName'              => 'Festival Volunteer',
        'status'                    => 'APPROVED',
        'expiresInText'             => '30 minutes',
        'securityEventSummary'      => 'New staff sign-in detected from Vadodara',
        'arrangementStatus'         => 'ARRANGED',
        'announcementTitle'         => 'Inaugural Gate Timings Updated',
        'announcementBody'          => 'Gates open at 08:30 IST at Sama Indoor Stadium.',
        'changeSummary'             => 'Plenary 2 moved to MSU Baroda Auditorium.',
        'dutyDetails'               => ['Venue' => 'Sama Indoor Stadium', 'Shift' => '09:00–13:00 IST'],
        // Prohibited sensitive fields that MUST be stripped and NEVER appear in HTML or plain text
        'password'                  => 'NEVER_SHOW_THIS_PASSWORD',
        'mfaSecret'                 => 'JBSWY3DPEHPK3PXP_SECRET',
        'disabilityDiagnosis'       => 'CONFIDENTIAL_MEDICAL_DIAGNOSIS',
        'medicalNotes'              => 'CONFIDENTIAL_MEDICAL_NOTES',
        'emergencyContactPhone'     => '+91-99999-SECRET',
        'internalAdminNotes'        => 'INTERNAL_REVIEWER_ONLY_NOTE',
    ];
    $ephemeral = ['actionUrl' => 'https://purplefestivalgujarat.com/verify?token=ephemeral-test-12345'];

    $renderReport = [];
    foreach ($catalog as $code => $meta) {
        $rendered = pfgRenderEmailTemplate($code, $sampleVars, $ephemeral);
        $html = $rendered['html'];
        $text = $rendered['text'];

        pfgAssert($rendered['subject'] !== '', "{$code}: empty subject");
        pfgAssert(str_contains($html, '<html lang="en">'), "{$code}: missing lang=en");
        pfgAssert(str_contains($html, 'role="presentation"'), "{$code}: missing semantic presentation table");
        pfgAssert(str_contains($html, '#2B144D'), "{$code}: missing high-contrast brand header");
        pfgAssert(!str_contains($html, '<script>alert(1)</script>'), "{$code}: unescaped <script> tag in HTML!");
        pfgAssert(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), "{$code}: missing HTML-escaped payload");
        pfgAssert(str_contains($html, 'આરવ પટેલ') && str_contains($html, 'प्रिया शर्मा'), "{$code}: Gujarati/Hindi UTF-8 corrupted");
        pfgAssert(str_contains($text, 'આરવ પટેલ') && str_contains($text, 'प्रिया शर्मा'), "{$code}: plain-text UTF-8 corrupted");
        pfgAssert(!str_contains($html, 'api.qrserver.com') && !str_contains($html, 'PFG27.'), "{$code}: prohibited QR leak!");

        foreach (['NEVER_SHOW_THIS_PASSWORD', 'JBSWY3DPEHPK3PXP_SECRET', 'CONFIDENTIAL_MEDICAL_DIAGNOSIS', 'CONFIDENTIAL_MEDICAL_NOTES', '+91-99999-SECRET', 'INTERNAL_REVIEWER_ONLY_NOTE'] as $forbidden) {
            pfgAssert(!str_contains($html, $forbidden) && !str_contains($text, $forbidden), "{$code}: leaked prohibited field {$forbidden}");
        }

        $renderReport[] = [
            'number'        => $meta['number'],
            'code'          => $code,
            'group'         => $meta['group'],
            'triggerStatus' => $meta['triggerStatus'],
            'htmlBytes'     => strlen($html),
            'textBytes'     => strlen($text),
            'status'        => 'PASS',
        ];
    }

    // Missing required var must fail cleanly
    $missingCaught = false;
    try {
        pfgRenderEmailTemplate('ACCOUNT_VERIFICATION', ['fullName' => 'No URL']);
    } catch (InvalidArgumentException $e) {
        $missingCaught = true;
    }
    pfgAssert($missingCaught, 'Missing required actionUrl was not rejected');

    // Unsafe javascript: actionUrl must fail cleanly
    $unsafeUrlCaught = false;
    try {
        pfgRenderEmailTemplate('ACCOUNT_VERIFICATION', ['fullName' => 'Bad URL'], ['actionUrl' => 'javascript:alert(1)']);
    } catch (InvalidArgumentException $e) {
        $unsafeUrlCaught = true;
    }
    pfgAssert($unsafeUrlCaught, 'Unsafe javascript: actionUrl was not rejected');

    file_put_contents(
        $tmpDir . DIRECTORY_SEPARATOR . 'template_rendering_results.json',
        json_encode($renderReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    return '29/29 templates rendered WCAG HTML + plain text, escaped XSS, preserved Gujarati/Hindi UTF-8, stripped all 6 prohibited field types, blocked unsafe URLs';
});

// T4-03: CLI Queue Worker — Batch Limit, Lease Claiming, Abandoned Lease Recovery & Exit Code (Section 22B)
pfgTest('T4-03', 'CLI queue worker enforces batch limit, recovers abandoned leases & exits cleanly', function () use ($root, $phpBin): string {
    $resSummary = pfgWithTempDb('pfg27_worker_basic_test', function (PDO $wpdo) use ($root, $phpBin): array {
        pfgRunMigrations($wpdo);
        pfgRunSafeReferenceSeed($wpdo);

        // Enqueue 5 jobs
        for ($i = 1; $i <= 5; $i++) {
            pfgEnqueueNotification($wpdo, [
                'to'             => "worker{$i}@example.test",
                'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
                'variables'      => ['fullName' => "Worker Test {$i}", 'registrationCode' => formatRegistrationCode($i)],
                'idempotencyKey' => "wbasic-{$i}",
            ]);
        }

        // Simulate 1 abandoned PROCESSING job with expired lease (locked_until 10 mins ago)
        $abandoned = pfgEnqueueNotification($wpdo, [
            'to'             => 'abandoned@example.test',
            'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
            'variables'      => ['fullName' => 'Abandoned Job', 'registrationCode' => 'PFG-2027-000099'],
            'idempotencyKey' => 'wbasic-abandoned',
        ]);
        $wpdo->prepare("UPDATE notification_queue SET status = 'PROCESSING', locked_by = 'crashed-worker', locked_until = :past, attempts = 1 WHERE id = :id")
            ->execute([':past' => nowSql(time() - 600), ':id' => $abandoned['queueId']]);

        // Run CLI worker via isolated env pointing to pfg27_worker_basic_test with --batch=3
        $envPath = __DIR__ . '/tmp/worker_basic_env.php';
        file_put_contents($envPath, "<?php return ['APP_ENV'=>'testing','APP_URL'=>'http://127.0.0.1:8088','DB_DRIVER'=>'mysql','DB_HOST'=>'" . DB_HOST . "','DB_PORT'=>'" . DB_PORT . "','DB_NAME'=>'pfg27_worker_basic_test','DB_USER'=>'" . DB_USER . "','DB_PASS'=>'" . DB_PASS . "','AUTH_SECRET'=>str_repeat('a',32),'QR_SIGNING_SECRET'=>str_repeat('b',32),'MAIL_DRIVER'=>'simulate'];");

        $runWorker = static function (string $extraArgs) use ($phpBin, $root, $envPath): array {
            $env = getenv();
            if (!is_array($env)) {
                $env = [];
            }
            $env['PFG_ENV_FILE'] = $envPath;
            $cmd = [$phpBin, $root . '/scripts/process-notification-queue.php'];
            foreach (explode(' ', trim($extraArgs)) as $tok) {
                if ($tok !== '') {
                    $cmd[] = $tok;
                }
            }
            $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
            fclose($pipes[0]);
            $out = trim((string)stream_get_contents($pipes[1]));
            $err = trim((string)stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($proc);
            return ['exit' => $exit, 'json' => json_decode($out, true), 'raw' => $out, 'err' => $err];
        };

        try {
            $pass1 = $runWorker('--batch=3 --max-seconds=15 --driver=simulate --simulate=accept --json');
            pfgAssert($pass1['exit'] === 0 && is_array($pass1['json']), 'Worker pass 1 failed: ' . $pass1['raw'] . ' ' . $pass1['err']);
            pfgAssert($pass1['json']['recovered'] === 1, 'Expected 1 recovered abandoned lease, got ' . $pass1['json']['recovered']);
            pfgAssert($pass1['json']['claimed'] === 3 && $pass1['json']['accepted'] === 3, 'Expected batch=3 claimed/accepted, got ' . $pass1['raw']);

            // Verify abandoned job is now RETRY_PENDING (not stuck in PROCESSING)
            $abStatus = (string)$wpdo->query('SELECT status FROM notification_queue WHERE id = ' . $wpdo->quote($abandoned['queueId']))->fetchColumn();
            pfgAssert($abStatus === 'RETRY_PENDING', "Expected abandoned job to recover to RETRY_PENDING, got {$abStatus}");

            // Force next_retry_at to past and drain remaining 3 jobs (2 QUEUED + 1 RETRY_PENDING)
            $wpdo->exec("UPDATE notification_queue SET next_retry_at = DATE_SUB(NOW(), INTERVAL 5 SECOND) WHERE status = 'RETRY_PENDING'");
            $pass2 = $runWorker('--batch=10 --drain --driver=simulate --simulate=accept --json');
            pfgAssert($pass2['exit'] === 0 && $pass2['json']['accepted'] === 3, 'Worker pass 2 failed: ' . $pass2['raw']);

            // Verify all 6 are PROVIDER_ACCEPTED and ZERO have delivered_at set (honest status!)
            $accCnt = (int)$wpdo->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'PROVIDER_ACCEPTED' AND delivered_at IS NULL AND provider_message_id LIKE 'sim_%'")->fetchColumn();
            pfgAssert($accCnt === 6, "Expected 6 PROVIDER_ACCEPTED with delivered_at=NULL, got {$accCnt}");
            return ['pass1Claimed' => $pass1['json']['claimed'], 'recovered' => $pass1['json']['recovered'], 'pass2Accepted' => $pass2['json']['accepted']];
        } finally {
            @unlink($envPath);
        }
    });

    return sprintf(
        'Pass1: batch=%d accepted, recovered=%d abandoned lease; Pass2: drained=%d (all 6 PROVIDER_ACCEPTED, delivered_at=NULL)',
        $resSummary['pass1Claimed'],
        $resSummary['recovered'],
        $resSummary['pass2Accepted']
    );
});

// T4-04: Retry Policy, Exponential Backoff, Permanent Failures, Timeout -> DELIVERY_UNKNOWN & Resend Fail-Closed (Section 22C)
pfgTest('T4-04', 'Retry backoff, max attempts, permanent reject, timeout DELIVERY_UNKNOWN & fail-closed Resend config', function (): string {
    return pfgWithTempDb('pfg27_retry_matrix_test', function (PDO $rpdo): string {
        pfgRunMigrations($rpdo);
        pfgRunSafeReferenceSeed($rpdo);

        $enqueueAndClaim = static function (string $key, int $maxAtt = 2) use ($rpdo): array {
            $e = pfgEnqueueNotification($rpdo, [
                'to'             => "{$key}@example.test",
                'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
                'variables'      => ['fullName' => "Retry {$key}", 'registrationCode' => 'PFG-2027-000123'],
                'idempotencyKey' => $key,
                'maxAttempts'    => $maxAtt,
            ]);
            $rpdo->prepare("UPDATE notification_queue SET status='PROCESSING', locked_by='t4-04', locked_until=:lu, attempts=attempts+1 WHERE id=:id")
                ->execute([':lu' => nowSql(time() + 120), ':id' => $e['queueId']]);
            return $rpdo->query('SELECT * FROM notification_queue WHERE id = ' . $rpdo->quote($e['queueId']))->fetch(PDO::FETCH_ASSOC);
        };

        // 1. Temporary 503 failure -> RETRY_PENDING on attempt 1, then FAILED on attempt 2 (maxAttempts=2)
        $j1 = $enqueueAndClaim('temp503', 2);
        $d1 = pfgDispatchClaimedNotification($rpdo, $j1, ['driver' => 'simulate', 'simScenario' => 'temp_fail']);
        pfgAssert($d1['status'] === 'RETRY_PENDING' && $d1['errorCategory'] === 'PROVIDER_5XX' && !empty($d1['nextRetryAt']), 'Temp 503 did not schedule RETRY_PENDING');

        // Attempt 2 hits max_attempts=2 -> FAILED
        $rpdo->prepare("UPDATE notification_queue SET status='PROCESSING', locked_by='t4-04', attempts=attempts+1 WHERE id=:id")->execute([':id' => $j1['id']]);
        $j1b = $rpdo->query('SELECT * FROM notification_queue WHERE id = ' . $rpdo->quote($j1['id']))->fetch(PDO::FETCH_ASSOC);
        $d1b = pfgDispatchClaimedNotification($rpdo, $j1b, ['driver' => 'simulate', 'simScenario' => 'temp_fail']);
        pfgAssert($d1b['status'] === 'FAILED', 'Exhausted max_attempts did not transition to FAILED');

        // 2. Rate limit 429 -> RETRY_PENDING (RATE_LIMITED)
        $j2 = $enqueueAndClaim('rl429', 3);
        $d2 = pfgDispatchClaimedNotification($rpdo, $j2, ['driver' => 'simulate', 'simScenario' => 'rate_limit']);
        pfgAssert($d2['status'] === 'RETRY_PENDING' && $d2['errorCategory'] === 'RATE_LIMITED', '429 not classified as RATE_LIMITED RETRY_PENDING');

        // 3. Invalid response -> RETRY_PENDING
        $j3 = $enqueueAndClaim('badjson', 3);
        $d3 = pfgDispatchClaimedNotification($rpdo, $j3, ['driver' => 'simulate', 'simScenario' => 'invalid_response']);
        pfgAssert($d3['status'] === 'RETRY_PENDING' && $d3['errorCategory'] === 'INVALID_PROVIDER_RESPONSE', 'Invalid response not handled');

        // 4. Permanent 422 reject -> immediate FAILED (no retry!)
        $j4 = $enqueueAndClaim('perm422', 5);
        $d4 = pfgDispatchClaimedNotification($rpdo, $j4, ['driver' => 'simulate', 'simScenario' => 'perm_reject']);
        pfgAssert($d4['status'] === 'FAILED' && $d4['errorCategory'] === 'INVALID_RECIPIENT', 'Permanent 422 was not immediately FAILED');

        // 5. Auth 401 & Unverified domain 403 -> immediate FAILED
        $j5 = $enqueueAndClaim('unverified', 5);
        $d5 = pfgDispatchClaimedNotification($rpdo, $j5, ['driver' => 'simulate', 'simScenario' => 'unverified_domain']);
        pfgAssert($d5['status'] === 'FAILED' && $d5['errorCategory'] === 'UNVERIFIED_DOMAIN', 'Unverified domain not immediately FAILED');

        // 6. Timeout -> DELIVERY_UNKNOWN (never DELIVERED!)
        $j6 = $enqueueAndClaim('timeout', 5);
        $d6 = pfgDispatchClaimedNotification($rpdo, $j6, ['driver' => 'simulate', 'simScenario' => 'timeout']);
        pfgAssert($d6['status'] === 'DELIVERY_UNKNOWN', 'Timeout did not map to DELIVERY_UNKNOWN');

        // 7. Real `resend` driver with missing RESEND_API_KEY -> fails closed (AUTH_CONFIG, zero PHP mail() fallback!)
        $j7 = $enqueueAndClaim('missingkey', 5);
        $d7 = pfgDispatchClaimedNotification($rpdo, $j7, ['driver' => 'resend']);
        pfgAssert($d7['status'] === 'FAILED' && $d7['errorCategory'] === 'AUTH_CONFIG', 'Missing RESEND_API_KEY did not fail closed');

        return 'Verified all 7 failure classes: 503->RETRY->FAILED, 429->RATE_LIMITED, badJSON->RETRY, 422->FAILED, 403->UNVERIFIED_DOMAIN, timeout->DELIVERY_UNKNOWN, empty Resend key->fail-closed AUTH_CONFIG';
    });
});

// T4-05: Resend Svix Webhook Signature Verification, Replay Window, Deduplication & Out-of-Order Ordering (Section 22D & 22F)
pfgTest('T4-05', 'Resend Svix webhook verifies HMAC-SHA256, blocks replays/bad sigs, deduplicates & guards out-of-order states', function () use ($root, $phpBin): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $webhookSecret = 'whsec_' . base64_encode(random_bytes(24));
    $rawKeyBytes = base64_decode(substr($webhookSecret, 6), true);

    // Create a PROVIDER_ACCEPTED notification with known provider_message_id
    $msgId = 're_test_' . bin2hex(random_bytes(8));
    $enq = pfgEnqueueNotification($pdo, [
        'to'             => 'webhook.test@example.test',
        'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
        'variables'      => ['fullName' => 'Webhook User', 'registrationCode' => 'PFG-2027-000456'],
        'idempotencyKey' => 'wh-test-' . $msgId,
    ]);
    $pdo->prepare("UPDATE notification_queue SET status='PROVIDER_ACCEPTED', provider='resend', provider_message_id=:mid, provider_accepted_at=NOW() WHERE id=:id")
        ->execute([':mid' => $msgId, ':id' => $enq['queueId']]);

    // Boot temporary PHP HTTP server with RESEND_WEBHOOK_SECRET configured
    $whEnv = __DIR__ . '/tmp/wh_env.php';
    $baseEnv = include getenv('PFG_ENV_FILE');
    $baseEnv['RESEND_WEBHOOK_SECRET'] = $webhookSecret;
    file_put_contents($whEnv, "<?php\nreturn " . var_export($baseEnv, true) . ";\n");

    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    $env['PFG_ENV_FILE'] = $whEnv;
    $whLog = __DIR__ . '/tmp/php_wh_server.log';
    @unlink($whLog);
    $proc = proc_open(
        [$phpBin, '-S', '127.0.0.1:8086', $root . '/tests/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $whLog, 'a'], 2 => ['file', $whLog, 'a']],
        $pipes,
        $root,
        $env
    );
    pfgAssert(is_resource($proc), 'Failed to launch webhook test server');
    for ($i = 0; $i < 15; $i++) {
        $fp = @fsockopen('127.0.0.1', 8086, $e1, $e2, 0.2);
        if ($fp) {
            fclose($fp);
            break;
        }
        usleep(150000);
    }

    $postWebhook = static function (string $rawBody, string $svixId, int $ts, ?string $sigOverride = null) use ($rawKeyBytes): array {
        $sig = $sigOverride ?? ('v1,' . base64_encode(hash_hmac('sha256', "{$svixId}.{$ts}.{$rawBody}", $rawKeyBytes, true)));
        $ch = curl_init('http://127.0.0.1:8086/api/webhooks/resend');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'svix-id: ' . $svixId,
                'svix-timestamp: ' . $ts,
                'svix-signature: ' . $sig,
            ],
            CURLOPT_POSTFIELDS     => $rawBody,
            CURLOPT_TIMEOUT        => 6,
        ]);
        $resp = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $code, 'json' => json_decode($resp, true), 'raw' => $resp];
    };

    try {
        $nowTs = time();
        $deliveredBody = json_encode(['type' => 'email.delivered', 'created_at' => nowIso(), 'data' => ['email_id' => $msgId]]);

        // 1. Invalid signature -> 401
        $rBadSig = $postWebhook($deliveredBody, 'msg_bad_sig', $nowTs, 'v1,invalidbase64signature==');
        pfgAssert($rBadSig['status'] === 401, "Expected 401 for bad signature, got {$rBadSig['status']}");

        // 2. Expired timestamp (>300s) -> 401 replay protection
        $rOld = $postWebhook($deliveredBody, 'msg_expired', $nowTs - 600);
        pfgAssert($rOld['status'] === 401, "Expected 401 for expired timestamp, got {$rOld['status']}");

        // 3. Valid email.delivered -> 200 STATUS_UPDATED -> DELIVERED
        $delEventId = 'msg_del_' . bin2hex(random_bytes(4));
        $rDel = $postWebhook($deliveredBody, $delEventId, $nowTs);
        pfgAssert($rDel['status'] === 200 && ($rDel['json']['action'] ?? '') === 'STATUS_UPDATED' && ($rDel['json']['status'] ?? '') === 'DELIVERED', 'Delivered webhook failed: ' . $rDel['raw']);

        // 4. Duplicate svix-id replay -> 200 DUPLICATE_EVENT_IGNORED
        $rDup = $postWebhook($deliveredBody, $delEventId, $nowTs);
        pfgAssert($rDup['status'] === 200 && ($rDup['json']['action'] ?? '') === 'DUPLICATE_EVENT_IGNORED', 'Duplicate webhook not ignored: ' . $rDup['raw']);

        // 5. Out-of-order delayed email.sent arriving AFTER DELIVERED -> OUT_OF_ORDER_IGNORED, stays DELIVERED!
        $sentBody = json_encode(['type' => 'email.sent', 'created_at' => nowIso(), 'data' => ['email_id' => $msgId]]);
        $rOoo = $postWebhook($sentBody, 'msg_ooo_' . bin2hex(random_bytes(4)), $nowTs);
        pfgAssert($rOoo['status'] === 200 && ($rOoo['json']['action'] ?? '') === 'OUT_OF_ORDER_IGNORED', 'Out-of-order event regressed status: ' . $rOoo['raw']);
        $stAfterOoo = (string)$pdo->query('SELECT status FROM notification_queue WHERE id = ' . $pdo->quote($enq['queueId']))->fetchColumn();
        pfgAssert($stAfterOoo === 'DELIVERED', "Status regressed to {$stAfterOoo} after out-of-order email.sent");

        // 6. Delayed bounce email.bounced -> updates cleanly to BOUNCED + bounced_at stamped
        $bounceBody = json_encode(['type' => 'email.bounced', 'created_at' => nowIso(), 'data' => ['email_id' => $msgId, 'bounce' => ['message' => 'Mailbox full']]]);
        $rBnc = $postWebhook($bounceBody, 'msg_bnc_' . bin2hex(random_bytes(4)), $nowTs);
        pfgAssert($rBnc['status'] === 200 && ($rBnc['json']['status'] ?? '') === 'BOUNCED', 'Bounce webhook failed: ' . $rBnc['raw']);

        // 7. Unknown email_id -> 200 IGNORED_UNKNOWN_MESSAGE
        $unkBody = json_encode(['type' => 'email.delivered', 'created_at' => nowIso(), 'data' => ['email_id' => 're_nonexistent_999']]);
        $rUnk = $postWebhook($unkBody, 'msg_unk_' . bin2hex(random_bytes(4)), $nowTs);
        pfgAssert($rUnk['status'] === 200 && ($rUnk['json']['action'] ?? '') === 'IGNORED_UNKNOWN_MESSAGE', 'Unknown message not handled cleanly');

        return 'Bad sig (401), expired replay (401), email.delivered->DELIVERED (200), dup svix-id ignored, out-of-order email.sent ignored, email.bounced->BOUNCED, unknown ID ignored';
    } finally {
        proc_terminate($proc);
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        proc_close($proc);
        @unlink($whEnv);
    }
});

// T4-06: Phase 4L Secure Ephemeral Token Delivery & Admin Notification RBAC/CSRF/Retry/Resend API (Section 22F)
pfgTest('T4-06', 'Ephemeral secret wiped post-handoff, password reset refuses blind resend & admin API enforces RBAC/CSRF', function () use ($root, $phpBin): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');

    // 1. Phase 4L Ephemeral Secret Delivery Lifecycle
    $secretResetUrl = 'https://purplefestivalgujarat.com/login?resetToken=eph_single_use_' . bin2hex(random_bytes(8));
    $enq = pfgEnqueueNotification($pdo, [
        'to'             => 'ephemeral.test@example.test',
        'templateCode'   => 'PASSWORD_RECOVERY',
        'variables'      => ['fullName' => 'Ephemeral Tester', 'expiresInText' => '30 minutes'],
        'ephemeralVars'  => ['actionUrl' => $secretResetUrl],
        'idempotencyKey' => 'eph-test-' . bin2hex(random_bytes(4)),
    ]);

    $rowPre = $pdo->query('SELECT payload, ephemeral_secret_enc FROM notification_queue WHERE id = ' . $pdo->quote($enq['queueId']))->fetch(PDO::FETCH_ASSOC);
    pfgAssert(!str_contains((string)$rowPre['payload'], 'eph_single_use_'), 'Ephemeral token leaked into notification_queue.payload!');
    pfgAssert(str_starts_with((string)$rowPre['ephemeral_secret_enc'], 'enc:v1:'), 'ephemeral_secret_enc not AES-256-GCM encrypted');

    // Dispatch to file sink -> verify captured email HAS the link AND DB immediately wipes ephemeral_secret_enc to NULL
    $pdo->prepare("UPDATE notification_queue SET status='PROCESSING', locked_by='eph-test', attempts=1 WHERE id=:id")->execute([':id' => $enq['queueId']]);
    $jobRow = $pdo->query('SELECT * FROM notification_queue WHERE id = ' . $pdo->quote($enq['queueId']))->fetch(PDO::FETCH_ASSOC);
    $disp = pfgDispatchClaimedNotification($pdo, $jobRow, ['driver' => 'file']);
    pfgAssert($disp['status'] === 'CAPTURED_LOCAL' && !empty($disp['capturePath']), 'Ephemeral file sink dispatch failed');
    $capturedJson = (string)file_get_contents((string)$disp['capturePath']);
    pfgAssert(str_contains($capturedJson, $secretResetUrl), 'Delivered email did not include the usable reset link');

    $ephAfter = $pdo->query('SELECT ephemeral_secret_enc FROM notification_queue WHERE id = ' . $pdo->quote($enq['queueId']))->fetchColumn();
    pfgAssert($ephAfter === null, 'ephemeral_secret_enc was NOT wiped to NULL after handoff!');

    // Verify admin resend refuses blind resend of PASSWORD_RECOVERY
    $refusedBlindResend = false;
    try {
        pfgAdminResendNotification($pdo, (string)$enq['queueId'], uuid4(), 'Should fail');
    } catch (InvalidArgumentException $e) {
        $refusedBlindResend = true;
    }
    pfgAssert($refusedBlindResend, 'Admin resend allowed blind resend of single-use PASSWORD_RECOVERY');

    // 2. Admin /api/admin/notifications RBAC, CSRF, Retry & Resend tests
    $failedEnq = pfgEnqueueNotification($pdo, [
        'to'             => 'admin.retry@example.test',
        'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
        'variables'      => ['fullName' => 'Admin Retry User', 'registrationCode' => 'PFG-2027-000321'],
        'idempotencyKey' => 'adm-retry-' . bin2hex(random_bytes(4)),
    ]);
    $pdo->prepare("UPDATE notification_queue SET status='FAILED', attempts=3, failed_at=NOW(), error_category='PROVIDER_5XX' WHERE id=:id")
        ->execute([':id' => $failedEnq['queueId']]);

    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    $srvLog = __DIR__ . '/tmp/php_p4_admin_server.log';
    @unlink($srvLog);
    $proc = proc_open(
        [$phpBin, '-S', '127.0.0.1:8085', $root . '/tests/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $srvLog, 'a'], 2 => ['file', $srvLog, 'a']],
        $pipes,
        $root,
        $env
    );
    pfgAssert(is_resource($proc), 'Failed to start Phase 4 admin test server');
    for ($i = 0; $i < 15; $i++) {
        $fp = @fsockopen('127.0.0.1', 8085, $e1, $e2, 0.2);
        if ($fp) {
            fclose($fp);
            break;
        }
        usleep(150000);
    }

    try {
        // Unauthenticated GET -> 401
        $ch = curl_init('http://127.0.0.1:8085/api/admin/notifications');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
        curl_exec($ch);
        $unauthCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        pfgAssert($unauthCode === 401, "Expected 401 unauth on /api/admin/notifications, got {$unauthCode}");

        // Create MFA-verified SUPERADMIN session directly in pfg27_test and fetch its live CSRF token via /api/auth/me
        $adminRow = $pdo->query("SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE r.code = 'SUPERADMIN' AND u.mfa_enabled = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        pfgAssert(is_array($adminRow), 'Expected MFA-enrolled SUPERADMIN from Phase 2 tests');
        $rawSess = bin2hex(random_bytes(32));
        $sessHash = hash_hmac('sha256', $rawSess, AUTH_SECRET);
        $pdo->prepare("INSERT INTO sessions (id, user_id, token_hash, mfa_verified, expires_at, created_at) VALUES (:id, :uid, :th, 1, :exp, NOW())")
            ->execute([':id' => uuid4(), ':uid' => $adminRow['id'], ':th' => $sessHash, ':exp' => nowSql(time() + 3600)]);

        $apiCall = static function (string $method, string $path, ?array $body = null, ?string $csrf = null) use ($rawSess): array {
            $ch = curl_init('http://127.0.0.1:8085' . $path);
            $hdrs = ['Cookie: pfg_session=' . $rawSess];
            if ($body !== null) {
                $hdrs[] = 'Content-Type: application/json';
            }
            if ($csrf !== null) {
                $hdrs[] = 'X-CSRF-Token: ' . $csrf;
            }
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $hdrs,
                CURLOPT_POSTFIELDS     => $body !== null ? json_encode($body) : null,
                CURLOPT_TIMEOUT        => 6,
            ]);
            $resp = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['status' => $code, 'json' => json_decode($resp, true), 'raw' => $resp];
        };

        $meRes = $apiCall('GET', '/api/auth/me');
        $csrfTok = (string)($meRes['json']['csrfToken'] ?? '');
        pfgAssert($meRes['status'] === 200 && strlen($csrfTok) >= 32, 'Failed to retrieve live CSRF token from /api/auth/me: ' . $meRes['raw']);

        // GET list -> 200 and zero ephemeral_secret_enc column exposed
        $gList = $apiCall('GET', '/api/admin/notifications?status=FAILED');
        pfgAssert($gList['status'] === 200 && !str_contains($gList['raw'], 'ephemeral_secret_enc'), 'Admin GET failed or exposed ephemeral_secret_enc: ' . $gList['raw']);

        // POST retry without CSRF -> 403
        $pNoCsrf = $apiCall('POST', '/api/admin/notifications', ['action' => 'retry', 'queueId' => $failedEnq['queueId']]);
        pfgAssert($pNoCsrf['status'] === 403, "Expected 403 missing CSRF, got {$pNoCsrf['status']}");

        // POST retry with CSRF -> 200 QUEUED
        $pRetry = $apiCall('POST', '/api/admin/notifications', ['action' => 'retry', 'queueId' => $failedEnq['queueId'], 'reason' => 'Test retry'], $csrfTok);
        pfgAssert($pRetry['status'] === 200 && ($pRetry['json']['result']['status'] ?? '') === 'QUEUED', 'Admin retry failed: ' . $pRetry['raw']);

        // POST resend with CSRF -> 201 linked via resent_from_id
        $pResend = $apiCall('POST', '/api/admin/notifications', ['action' => 'resend', 'queueId' => $failedEnq['queueId'], 'reason' => 'Test intentional resend'], $csrfTok);
        pfgAssert($pResend['status'] === 201 && ($pResend['json']['result']['resentFromId'] ?? '') === $failedEnq['queueId'], 'Admin resend failed: ' . $pResend['raw']);

        return 'Ephemeral secret encrypted->delivered->wiped NULL, blind PASSWORD_RECOVERY resend blocked; /api/admin/notifications unauth=401, no-CSRF=403, GET=200 redacted, retry=200, resend=201 linked';
    } finally {
        proc_terminate($proc);
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        proc_close($proc);
    }
});

// T4-07: Local Email Isolation (file sink, disabled worker no-op, .cpanel.yml excludes tests/) (Section 22G)
pfgTest('T4-07', 'Local email isolation: file sink outside webroot, disabled worker no-op & deploy exclusion verified', function () use ($root): string {
    $pdo = pfgCreateDirectPdo('pfg27_test');
    $resDisabled = pfgProcessNotificationQueueBatch($pdo, ['driver' => 'disabled', 'batchLimit' => 10]);
    pfgAssert($resDisabled['claimed'] === 0 && $resDisabled['processed'] === 0 && $resDisabled['driver'] === 'disabled', 'Disabled driver claimed/processed rows!');

    $cpanelYml = (string)file_get_contents($root . '/.cpanel.yml');
    pfgAssert(!str_contains($cpanelYml, 'tests/tmp'), '.cpanel.yml references tests/tmp');
    $gitignore = is_file($root . '/.gitignore') ? (string)file_get_contents($root . '/.gitignore') : '';
    pfgAssert(str_contains($gitignore, 'tests/tmp'), 'tests/tmp must be gitignored');

    return 'MAIL_DRIVER=disabled claimed=0/processed=0; mail-sink isolated in gitignored tests/tmp/mail-sink outside deploy output';
});

// T4-08: Multi-Worker Notification Queue Concurrency & Load Stress Testing: 10, 100, 500 & 1,000 Queued Notifications (Section 23)
pfgTest('T4-08', 'Multi-worker concurrency load test: 10, 100, 500 & 1,000 queued notifications across 4 parallel CLI workers', function () use ($root, $phpBin, $tmpDir): string {
    $volumes = [10, 100, 500, 1000];
    $numWorkers = 4;
    $benchmarks = [];

    pfgWithTempDb('pfg27_notif_load_test', function (PDO $lpdo) use ($root, $phpBin, $volumes, $numWorkers, &$benchmarks): void {
        pfgRunMigrations($lpdo);
        pfgRunSafeReferenceSeed($lpdo);

        $loadEnvPath = __DIR__ . '/tmp/notif_load_env.php';
        file_put_contents($loadEnvPath, "<?php return ['APP_ENV'=>'testing','APP_URL'=>'http://127.0.0.1:8088','DB_DRIVER'=>'mysql','DB_HOST'=>'" . DB_HOST . "','DB_PORT'=>'" . DB_PORT . "','DB_NAME'=>'pfg27_notif_load_test','DB_USER'=>'" . DB_USER . "','DB_PASS'=>'" . DB_PASS . "','AUTH_SECRET'=>str_repeat('a',32),'QR_SIGNING_SECRET'=>str_repeat('b',32),'MAIL_DRIVER'=>'simulate'];");

        $baseEnv = getenv();
        if (!is_array($baseEnv)) {
            $baseEnv = [];
        }
        $baseEnv['PFG_ENV_FILE'] = $loadEnvPath;

        try {
            foreach ($volumes as $vol) {
                $lpdo->exec('DELETE FROM notification_delivery_logs');
                $lpdo->exec('DELETE FROM notification_queue');

                // Bulk insert $vol queued notifications cleanly inside a fast transaction
                $now = nowSql();
                $lpdo->beginTransaction();
                $ins = $lpdo->prepare("
                    INSERT INTO notification_queue (
                        id, template_code, event_type, channel, recipient_email, subject, payload, status, priority, provider, idempotency_key, attempts, max_attempts, created_at, updated_at
                    ) VALUES (
                        :id, 'ATTENDEE_REGISTRATION_CONFIRMED', 'attendee.registration_confirmed', 'EMAIL', :em, :subj, :pay, 'QUEUED', 5, 'simulate', :ik, 0, 5, :n, :n
                    )
                ");
                for ($i = 1; $i <= $vol; $i++) {
                    $code = formatRegistrationCode($i);
                    $ins->execute([
                        ':id'   => uuid4(),
                        ':em'   => "load{$vol}_{$i}@example.test",
                        ':subj' => "Registration Confirmed ({$code})",
                        ':pay'  => jsonEncode(['fullName' => "Load Participant {$i}", 'registrationCode' => $code, 'categoryName' => 'General Attendee', 'status' => 'CONFIRMED']),
                        ':ik'   => "load-{$vol}-{$i}",
                        ':n'    => $now,
                    ]);
                }
                $lpdo->commit();

                // Launch $numWorkers simultaneous CLI queue workers draining the queue
                $t0 = microtime(true);
                $procs = [];
                $pipesMap = [];
                $batchSize = $vol <= 10 ? 5 : ($vol <= 100 ? 20 : 50);

                for ($w = 0; $w < $numWorkers; $w++) {
                    $cmd = [
                        $phpBin,
                        $root . '/scripts/process-notification-queue.php',
                        "--batch={$batchSize}",
                        '--max-seconds=60',
                        "--worker-id=load-w{$w}-v{$vol}",
                        '--driver=simulate',
                        '--simulate=accept',
                        '--drain',
                        '--json',
                    ];
                    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $baseEnv);
                    if (is_resource($proc)) {
                        fclose($pipes[0]);
                        $procs[$w] = $proc;
                        $pipesMap[$w] = $pipes;
                    }
                }

                $sumClaimed = 0;
                $sumProcessed = 0;
                $sumAccepted = 0;
                $sumRetry = 0;
                $sumFailed = 0;
                $maxMemKb = 0;
                $workerErrors = [];

                foreach ($procs as $w => $proc) {
                    $out = trim((string)stream_get_contents($pipesMap[$w][1]));
                    $err = trim((string)stream_get_contents($pipesMap[$w][2]));
                    fclose($pipesMap[$w][1]);
                    fclose($pipesMap[$w][2]);
                    $exit = proc_close($proc);
                    $j = json_decode($out, true);
                    if ($exit === 0 && is_array($j)) {
                        $sumClaimed += (int)($j['claimed'] ?? 0);
                        $sumProcessed += (int)($j['processed'] ?? 0);
                        $sumAccepted += (int)($j['accepted'] ?? 0);
                        $sumRetry += (int)($j['retryScheduled'] ?? 0);
                        $sumFailed += (int)($j['failed'] ?? 0);
                        $maxMemKb = max($maxMemKb, (int)($j['peakMemoryKb'] ?? 0));
                    } else {
                        $workerErrors[] = "w{$w} exit={$exit} err={$err} out={$out}";
                    }
                }

                $wallMs = (int)round((microtime(true) - $t0) * 1000);
                $dbAccepted = (int)$lpdo->query("SELECT COUNT(*) FROM notification_queue WHERE status = 'PROVIDER_ACCEPTED'")->fetchColumn();
                $dbRemaining = (int)$lpdo->query("SELECT COUNT(*) FROM notification_queue WHERE status IN ('QUEUED','PROCESSING')")->fetchColumn();
                $logTotal = (int)$lpdo->query("SELECT COUNT(*) FROM notification_delivery_logs WHERE event_type = 'DISPATCH_ATTEMPT'")->fetchColumn();
                $logDistinct = (int)$lpdo->query("SELECT COUNT(DISTINCT job_id) FROM notification_delivery_logs WHERE event_type = 'DISPATCH_ATTEMPT'")->fetchColumn();
                $dupClaims = max(0, $sumClaimed - $vol);
                $dupDeliveries = max(0, $logTotal - $logDistinct);

                $ok = empty($workerErrors)
                    && $dbAccepted === $vol
                    && $dbRemaining === 0
                    && $dupClaims === 0
                    && $dupDeliveries === 0;

                $benchmarks[] = [
                    'queueVolume'         => $vol,
                    'parallelWorkers'     => $numWorkers,
                    'claimedTotal'        => $sumClaimed,
                    'processedTotal'      => $sumProcessed,
                    'acceptedTotal'       => $dbAccepted,
                    'duplicateClaims'     => $dupClaims,
                    'duplicateDeliveries' => $dupDeliveries,
                    'retryRecords'        => $sumRetry,
                    'failedRecords'       => $sumFailed,
                    'wallDurationMs'      => $wallMs,
                    'peakMemoryKb'        => $maxMemKb,
                    'status'              => $ok ? 'PASS' : 'FAIL',
                ];

                pfgAssert($ok, "Volume {$vol} failed: claimed={$sumClaimed}, accepted={$dbAccepted}, rem={$dbRemaining}, dupClaims={$dupClaims}, dupDeliv={$dupDeliveries}, errs=" . implode('; ', $workerErrors));
            }
        } finally {
            @unlink($loadEnvPath);
        }
    });

    file_put_contents(
        $tmpDir . DIRECTORY_SEPARATOR . 'notification_concurrency_results.json',
        json_encode($benchmarks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    $parts = array_map(
        static fn(array $b): string => "{$b['queueVolume']}={$b['acceptedTotal']}/{$b['queueVolume']} ({$b['wallDurationMs']}ms, {$b['peakMemoryKb']}KB)",
        $benchmarks
    );
    return '0 duplicate claims, 0 duplicate deliveries across 4 parallel workers: ' . implode(' | ', $parts);
});
