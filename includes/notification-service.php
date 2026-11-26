<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Centralized Transactional Notification Service (Phase 4B–4O)
 *
 * Single Canonical Communication Architecture:
 * - Transactional outbox insertion (`pfgEnqueueNotification`) safe inside caller PDO transactions.
 * - Atomic lease-based queue claiming (`pfgClaimNotificationBatch`) portable across MySQL 8.0 & MariaDB 10.4+.
 * - Abandoned lease recovery (`pfgRecoverAbandonedNotifications`) so crashed workers never strand jobs.
 * - Resend API integration with HTTPS, `Idempotency-Key` header, strict credential redaction, and fail-closed
 *   config validation (ZERO fallback to insecure PHP `mail()`).
 * - Safe local file capture sink (`MAIL_DRIVER=file`), disabled mode (`MAIL_DRIVER=disabled`),
 *   and dev/test-only deterministic provider simulator (`MAIL_DRIVER=simulate`).
 * - Honest delivery lifecycle: CREATED -> QUEUED -> PROCESSING -> PROVIDER_ACCEPTED -> DELIVERED,
 *   plus RETRY_PENDING, FAILED, BOUNCED, SUPPRESSED, CANCELLED, DELIVERY_UNKNOWN, CAPTURED_LOCAL.
 * - Official Resend/Svix webhook HMAC-SHA256 verification, ±300s replay window, event deduplication,
 *   and monotonic state-precedence protection against out-of-order webhooks.
 * - Ephemeral AES-256-GCM delivery for single-use verification/recovery links, wiped immediately upon handoff.
 * - In-application notification foundation (`in_app_notifications`) decoupled cleanly from email delivery.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notification-templates.php';

if (!defined('PFG_NOTIFICATION_STATUS_PRECEDENCE')) {
    define('PFG_NOTIFICATION_STATUS_PRECEDENCE', [
        'CREATED'           => 5,
        'QUEUED'            => 10,
        'RETRY_PENDING'     => 15,
        'PROCESSING'        => 20,
        'DELIVERY_UNKNOWN'  => 30,
        'CAPTURED_LOCAL'    => 35,
        'PROVIDER_ACCEPTED' => 40,
        'DELIVERED'         => 60,
        'FAILED'            => 70,
        'SUPPRESSED'        => 75,
        'BOUNCED'           => 80,
        'CANCELLED'         => 90,
    ]);
}

if (!function_exists('pfgComputeRetryBackoffSeconds')) {
    /**
     * Exponential backoff schedule for transient failures: 60s, 300s, 900s, 3600s.
     */
    function pfgComputeRetryBackoffSeconds(int $attemptNumber): int
    {
        $schedule = [1 => 60, 2 => 300, 3 => 900, 4 => 3600];
        return $schedule[$attemptNumber] ?? 3600;
    }
}

if (!function_exists('pfgLogDeliveryAttempt')) {
    function pfgLogDeliveryAttempt(PDO $pdo, array $entry): bool
    {
        $provider = substr(trim((string)($entry['provider'] ?? 'unknown')), 0, 64);
        $providerEventId = isset($entry['providerEventId']) && trim((string)$entry['providerEventId']) !== ''
            ? substr(trim((string)$entry['providerEventId']), 0, 191)
            : null;
        $details = is_array($entry['responseDetails'] ?? null)
            ? pfgScrubSensitiveArray($entry['responseDetails'])
            : ['detail' => (string)($entry['responseDetails'] ?? '')];

        try {
            $stmt = $pdo->prepare('
                INSERT INTO notification_delivery_logs
                (id, job_id, attempt_number, provider, provider_message_id, event_type, provider_event_id, status, http_status, error_category, response_details, created_at)
                VALUES (:id, :job_id, :attempt, :provider, :msg_id, :event_type, :peid, :status, :http_status, :err_cat, :details, :now)
            ');
            $stmt->execute([
                ':id'          => uuid4(),
                ':job_id'      => (string)$entry['jobId'],
                ':attempt'     => max(1, (int)($entry['attemptNumber'] ?? 1)),
                ':provider'    => $provider,
                ':msg_id'      => isset($entry['providerMessageId']) && $entry['providerMessageId'] !== '' ? (string)$entry['providerMessageId'] : null,
                ':event_type'  => isset($entry['eventType']) ? (string)$entry['eventType'] : 'DISPATCH_ATTEMPT',
                ':peid'        => $providerEventId,
                ':status'      => (string)$entry['status'],
                ':http_status' => isset($entry['httpStatus']) ? (int)$entry['httpStatus'] : null,
                ':err_cat'     => isset($entry['errorCategory']) && $entry['errorCategory'] !== '' ? (string)$entry['errorCategory'] : null,
                ':details'     => jsonEncode($details),
                ':now'         => nowSql(),
            ]);
            return true;
        } catch (PDOException $e) {
            // Duplicate provider_event_id (SQLSTATE 23000) -> already processed webhook event
            if ($e->getCode() === '23000' && $providerEventId !== null) {
                return false;
            }
            error_log('[NotificationService] notification_delivery_logs insert error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('pfgCreateInAppNotification')) {
    /**
     * Persists a decoupled in-application notification row for the future Phase 10 participant dashboard.
     */
    function pfgCreateInAppNotification(PDO $pdo, array $params): ?string
    {
        $userId = trim((string)($params['userId'] ?? ''));
        if ($userId === '') {
            return null;
        }
        $ikey = isset($params['idempotencyKey']) && trim((string)$params['idempotencyKey']) !== ''
            ? substr(trim((string)$params['idempotencyKey']), 0, 128)
            : null;

        if ($ikey !== null) {
            $chk = $pdo->prepare('SELECT id FROM in_app_notifications WHERE idempotency_key = :ik LIMIT 1');
            $chk->execute([':ik' => $ikey]);
            $existing = $chk->fetchColumn();
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }

        $id = uuid4();
        $stmt = $pdo->prepare('
            INSERT INTO in_app_notifications
            (id, user_id, event_type, title, message, action_url, entity_type, entity_id, source_queue_id, idempotency_key, is_read, created_at)
            VALUES (:id, :uid, :etype, :title, :msg, :url, :ent_type, :ent_id, :qid, :ik, 0, :now)
        ');
        $stmt->execute([
            ':id'       => $id,
            ':uid'      => $userId,
            ':etype'    => substr(trim((string)($params['eventType'] ?? 'NOTIFICATION')), 0, 100),
            ':title'    => substr(trim((string)($params['title'] ?? 'Festival Update')), 0, 200),
            ':msg'      => trim((string)($params['message'] ?? '')),
            ':url'      => isset($params['actionUrl']) && trim((string)$params['actionUrl']) !== '' ? substr(trim((string)$params['actionUrl']), 0, 255) : null,
            ':ent_type' => isset($params['entityType']) ? (string)$params['entityType'] : null,
            ':ent_id'   => isset($params['entityId']) ? (string)$params['entityId'] : null,
            ':qid'      => isset($params['sourceQueueId']) ? (string)$params['sourceQueueId'] : null,
            ':ik'       => $ikey,
            ':now'      => nowSql(),
        ]);

        return $id;
    }
}

if (!function_exists('pfgEnqueueNotification')) {
    /**
     * Persists a transactional notification outbox entry in `notification_queue`.
     * Safe to invoke inside the caller's database transaction (commits/rolls back atomically with business state).
     *
     * Supports:
     * - Template validation against the 29-template catalog (`includes/notification-templates.php`)
     * - Recipient email validation
     * - Prohibited sensitive variable stripping
     * - Idempotent deduplication by `idempotencyKey`
     * - Encrypted ephemeral secret delivery (`ephemeralVars`, e.g. single-use reset/verification URL)
     * - Optional coupled `in_app_notifications` row (`createInApp => true`)
     *
     * @throws InvalidArgumentException on invalid email, unknown template, or missing required variables.
     */
    function pfgEnqueueNotification(PDO $pdo, array $event): array
    {
        $to = strtolower(trim((string)($event['to'] ?? $event['recipientEmail'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || strlen($to) > 255) {
            throw new InvalidArgumentException('Invalid recipient email address.');
        }

        $rawTemplate = trim((string)($event['templateCode'] ?? ''));
        $isCustomRaw = ($rawTemplate === '' || strtoupper($rawTemplate) === 'CUSTOM_EMAIL');
        $resolvedTemplate = $isCustomRaw ? 'CUSTOM_EMAIL' : pfgResolveTemplateCode($rawTemplate);

        $variables = is_array($event['variables'] ?? null) ? pfgStripProhibitedEmailVariables($event['variables']) : [];
        $ephemeralVars = is_array($event['ephemeralVars'] ?? null) ? $event['ephemeralVars'] : [];

        // Validate template & required variables upfront before persisting to queue
        if (!$isCustomRaw) {
            $renderedPreview = pfgRenderEmailTemplate($resolvedTemplate, $variables, $ephemeralVars);
            $subject = trim((string)($event['subject'] ?? $renderedPreview['subject']));
            $eventType = trim((string)($event['eventType'] ?? $renderedPreview['eventType']));
        } else {
            $subject = trim((string)($event['subject'] ?? 'Purple Festival Gujarat 2027'));
            $eventType = trim((string)($event['eventType'] ?? 'CUSTOM_EMAIL'));
        }

        $idempotencyKey = isset($event['idempotencyKey']) && trim((string)$event['idempotencyKey']) !== ''
            ? substr(trim((string)$event['idempotencyKey']), 0, 128)
            : null;

        if ($idempotencyKey !== null) {
            $dup = $pdo->prepare('SELECT id, status, provider, provider_message_id FROM notification_queue WHERE idempotency_key = :ik LIMIT 1');
            $dup->execute([':ik' => $idempotencyKey]);
            $existing = $dup->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return [
                    'queueId'           => (string)$existing['id'],
                    'status'            => (string)$existing['status'],
                    'provider'          => $existing['provider'] ?? null,
                    'providerMessageId' => $existing['provider_message_id'] ?? null,
                    'deduped'           => true,
                ];
            }
        }

        $recipientUserId = isset($event['recipientUserId']) && trim((string)$event['recipientUserId']) !== ''
            ? trim((string)$event['recipientUserId'])
            : null;
        $entityType = isset($event['entityType']) && trim((string)$event['entityType']) !== ''
            ? substr(trim((string)$event['entityType']), 0, 64)
            : null;
        $entityId = isset($event['entityId']) && trim((string)$event['entityId']) !== ''
            ? trim((string)$event['entityId'])
            : null;
        $priority = max(1, min(9, (int)($event['priority'] ?? 5)));
        $maxAttempts = max(1, min(10, (int)($event['maxAttempts'] ?? NOTIFICATION_MAX_ATTEMPTS)));
        $resentFromId = isset($event['resentFromId']) && trim((string)$event['resentFromId']) !== ''
            ? trim((string)$event['resentFromId'])
            : null;

        // Scrub payload strictly: NEVER persist actionUrl, rawToken, resetUrl, password, MFA secret, or diagnoses in payload
        $safePayload = pfgScrubSensitiveArray($variables);
        unset($safePayload['actionUrl'], $safePayload['resetUrl'], $safePayload['rawToken'], $safePayload['token']);

        $ephemeralEnc = null;
        if (!empty($ephemeralVars)) {
            // Only encrypt allowed single-use actionUrl in ephemeral_secret_enc (wiped upon provider handoff)
            $allowedEph = [];
            if (isset($ephemeralVars['actionUrl']) && is_string($ephemeralVars['actionUrl']) && trim($ephemeralVars['actionUrl']) !== '') {
                $safeUrl = pfgValidateSafeUrl($ephemeralVars['actionUrl'], false);
                if ($safeUrl === null) {
                    throw new InvalidArgumentException('Invalid ephemeral actionUrl.');
                }
                $allowedEph['actionUrl'] = $safeUrl;
            }
            if (!empty($allowedEph)) {
                $ephemeralEnc = pfgEncryptValue(jsonEncode($allowedEph), 'data');
            }
        }

        // For non-template custom raw emails (e.g. Phase 1 tests), store body_html/body_text cleanly (never for ephemeral secrets)
        $storedBodyHtml = ($isCustomRaw && empty($ephemeralVars)) ? (string)($event['html'] ?? '') : null;
        $storedBodyText = ($isCustomRaw && empty($ephemeralVars)) ? (string)($event['text'] ?? strip_tags((string)($event['html'] ?? ''))) : null;

        $queueId = uuid4();
        $now = nowSql();

        $ins = $pdo->prepare("
            INSERT INTO notification_queue (
                id, template_code, event_type, channel, entity_type, entity_id,
                recipient_user_id, recipient_email, subject, body_html, body_text,
                payload, ephemeral_secret_enc, status, priority, provider,
                idempotency_key, resent_from_id, attempts, max_attempts,
                created_at, updated_at
            ) VALUES (
                :id, :tpl, :etype, 'EMAIL', :ent_type, :ent_id,
                :uid, :email, :subject, :bhtml, :btext,
                :payload, :eph, 'QUEUED', :prio, :prov,
                :ikey, :resent_from, 0, :max_att,
                :now, :now
            )
        ");
        $ins->execute([
            ':id'          => $queueId,
            ':tpl'         => $resolvedTemplate,
            ':etype'       => $eventType,
            ':ent_type'    => $entityType,
            ':ent_id'      => $entityId,
            ':uid'         => $recipientUserId,
            ':email'       => $to,
            ':subject'     => substr($subject, 0, 250),
            ':bhtml'       => $storedBodyHtml,
            ':btext'       => $storedBodyText,
            ':payload'     => jsonEncode($safePayload),
            ':eph'         => $ephemeralEnc,
            ':prio'        => $priority,
            ':prov'        => MAIL_DRIVER,
            ':ikey'        => $idempotencyKey,
            ':resent_from' => $resentFromId,
            ':max_att'     => $maxAttempts,
            ':now'         => $now,
        ]);

        // Optional decoupled in-application notification record (Phase 4O)
        $inAppId = null;
        if (!empty($event['createInApp']) && $recipientUserId !== null) {
            $inAppTitle = trim((string)($event['inAppTitle'] ?? $subject));
            $inAppMsg = trim((string)($event['inAppMessage'] ?? ($event['inAppSummary'] ?? $subject)));
            $inAppId = pfgCreateInAppNotification($pdo, [
                'userId'         => $recipientUserId,
                'eventType'      => $eventType,
                'title'          => $inAppTitle,
                'message'        => $inAppMsg,
                'actionUrl'      => $event['inAppActionUrl'] ?? '/dashboard',
                'entityType'     => $entityType,
                'entityId'       => $entityId,
                'sourceQueueId'  => $queueId,
                'idempotencyKey' => $idempotencyKey !== null ? ('inapp:' . $idempotencyKey) : null,
            ]);
        }

        return [
            'queueId'           => $queueId,
            'inAppId'           => $inAppId,
            'templateCode'      => $resolvedTemplate,
            'eventType'         => $eventType,
            'status'            => 'QUEUED',
            'provider'          => MAIL_DRIVER,
            'providerMessageId' => null,
            'deduped'           => false,
        ];
    }
}

if (!function_exists('pfgRecoverAbandonedNotifications')) {
    /**
     * Recovers queue rows stuck in `PROCESSING` after their worker lease (`locked_until`) expired.
     * - If attempts < max_attempts -> schedules `RETRY_PENDING` cleanly.
     * - If attempts >= max_attempts -> marks `DELIVERY_UNKNOWN` (honest uncertainty; never marks DELIVERED).
     */
    function pfgRecoverAbandonedNotifications(PDO $pdo): int
    {
        $now = nowSql();
        $stmt = $pdo->prepare("
            SELECT id, attempts, max_attempts, provider
            FROM notification_queue
            WHERE status = 'PROCESSING'
              AND locked_until IS NOT NULL
              AND locked_until < :now
        ");
        $stmt->execute([':now' => $now]);
        $stuck = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $recovered = 0;
        foreach ($stuck as $row) {
            $attempts = (int)$row['attempts'];
            $maxAttempts = max(1, (int)$row['max_attempts']);
            $nextStatus = $attempts < $maxAttempts ? 'RETRY_PENDING' : 'DELIVERY_UNKNOWN';
            $nextRetryAt = $attempts < $maxAttempts ? nowSql(time() + pfgComputeRetryBackoffSeconds($attempts)) : null;

            $upd = $pdo->prepare("
                UPDATE notification_queue
                SET status = :st,
                    locked_by = NULL,
                    locked_until = NULL,
                    next_retry_at = :nra,
                    error_category = 'ABANDONED_LEASE_RECOVERED',
                    last_error = 'Worker lease expired before completion; recovered automatically.',
                    updated_at = :now
                WHERE id = :id AND status = 'PROCESSING'
            ");
            $upd->execute([
                ':st'  => $nextStatus,
                ':nra' => $nextRetryAt,
                ':now' => $now,
                ':id'  => $row['id'],
            ]);

            if ($upd->rowCount() > 0) {
                $recovered++;
                pfgLogDeliveryAttempt($pdo, [
                    'jobId'           => (string)$row['id'],
                    'attemptNumber'   => max(1, $attempts),
                    'provider'        => (string)($row['provider'] ?: MAIL_DRIVER),
                    'eventType'       => 'LEASE_RECOVERED',
                    'status'          => $nextStatus,
                    'errorCategory'   => 'ABANDONED_LEASE_RECOVERED',
                    'responseDetails' => ['recoveredAt' => $now, 'nextStatus' => $nextStatus],
                ]);
            }
        }

        return $recovered;
    }
}

if (!function_exists('pfgClaimNotificationBatch')) {
    /**
     * Atomically claims up to `$limit` eligible queued/retry-pending notifications for `$workerId`.
     * Portable across MySQL 5.7, MySQL 8.0, and MariaDB 10.4+ (avoids MariaDB 10.6-only SKIP LOCKED).
     */
    function pfgClaimNotificationBatch(PDO $pdo, string $workerId, int $limit = 25, int $lockTtlSeconds = 120): array
    {
        $limit = max(1, min(200, $limit));
        $lockTtlSeconds = max(30, min(900, $lockTtlSeconds));
        $now = nowSql();
        $lockUntil = nowSql(time() + $lockTtlSeconds);

        // Retry briefly if InnoDB row-lock contention occurs under heavy simultaneous worker fan-out
        $claimedCount = 0;
        for ($retry = 0; $retry < 5; $retry++) {
            try {
                $sql = "
                    UPDATE notification_queue
                    SET status = 'PROCESSING',
                        locked_by = :worker_id,
                        locked_until = :lock_until,
                        attempts = attempts + 1,
                        last_attempt_at = :now1,
                        updated_at = :now2
                    WHERE status IN ('QUEUED', 'RETRY_PENDING')
                      AND (next_retry_at IS NULL OR next_retry_at <= :now3)
                      AND (locked_until IS NULL OR locked_until < :now4)
                    ORDER BY priority ASC, created_at ASC, id ASC
                    LIMIT {$limit}
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':worker_id'  => $workerId,
                    ':lock_until' => $lockUntil,
                    ':now1'       => $now,
                    ':now2'       => $now,
                    ':now3'       => $now,
                    ':now4'       => $now,
                ]);
                $claimedCount = $stmt->rowCount();
                break;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if ($retry < 4 && (str_contains($msg, '1213') || str_contains($msg, '1205') || str_contains($msg, 'Deadlock'))) {
                    usleep(random_int(15000, 60000));
                    continue;
                }
                throw $e;
            }
        }

        if ($claimedCount === 0) {
            return [];
        }

        $sel = $pdo->prepare("
            SELECT *
            FROM notification_queue
            WHERE status = 'PROCESSING'
              AND locked_by = :worker_id
            ORDER BY priority ASC, created_at ASC, id ASC
        ");
        $sel->execute([':worker_id' => $workerId]);
        return $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('pfgDeliverViaProvider')) {
    /**
     * Low-level provider transport supporting:
     * - `file`: safe local JSON capture outside webroot (MAIL_SINK_DIR)
     * - `disabled`: no-op disabled mode
     * - `simulate`: deterministic dev/test-only provider simulator (blocked in staging/production)
     * - `resend`: HTTPS Resend API with Idempotency-Key header & fail-closed credential check (no mail() fallback)
     */
    function pfgDeliverViaProvider(array $envelope, ?string $driverOverride = null, ?string $simScenario = null): array
    {
        $driver = strtolower(trim($driverOverride ?? (defined('MAIL_DRIVER') ? (string)MAIL_DRIVER : 'file')));
        $to = (string)$envelope['to'];
        $subject = (string)$envelope['subject'];
        $html = (string)$envelope['html'];
        $text = (string)$envelope['text'];
        $templateCode = (string)$envelope['templateCode'];
        $eventType = (string)$envelope['eventType'];
        $queueId = (string)$envelope['queueId'];
        $idempotencyKey = (string)($envelope['idempotencyKey'] ?? ('pfg-job-' . $queueId));
        $fromAddress = defined('EMAIL_FROM') ? (string)EMAIL_FROM : 'Purple Festival Gujarat 2027 <noreply@purplefestivalgujarat.com>';
        $replyTo = defined('MAIL_REPLY_TO') && MAIL_REPLY_TO !== '' ? (string)MAIL_REPLY_TO : null;

        // 1. Safe Local File Sink
        if ($driver === 'file') {
            $sinkDir = defined('MAIL_SINK_DIR') && MAIL_SINK_DIR !== ''
                ? (string)MAIL_SINK_DIR
                : dirname(__DIR__) . '/tests/tmp/mail-sink';
            if (!is_dir($sinkDir)) {
                @mkdir($sinkDir, 0775, true);
            }
            $captureId = 'local-' . uuid4();
            $capturePayload = [
                'captureId'      => $captureId,
                'queueId'        => $queueId,
                'capturedAt'     => nowIso(),
                'to'             => $to,
                'from'           => $fromAddress,
                'replyTo'        => $replyTo,
                'subject'        => $subject,
                'templateCode'   => $templateCode,
                'eventType'      => $eventType,
                'idempotencyKey' => $idempotencyKey,
                'html'           => $html,
                'text'           => $text,
                'metadata'       => pfgScrubSensitiveArray(is_array($envelope['metadata'] ?? null) ? $envelope['metadata'] : []),
                'simulatedState' => 'CAPTURED_LOCAL',
            ];
            $filePath = rtrim($sinkDir, '/\\') . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . $captureId . '.json';
            $written = @file_put_contents($filePath, json_encode($capturePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($written === false) {
                return [
                    'outcome'       => 'TEMP_FAIL',
                    'status'        => 'RETRY_PENDING',
                    'provider'      => 'file_sink',
                    'messageId'     => null,
                    'httpStatus'    => null,
                    'errorCategory' => 'LOCAL_SINK_IO',
                    'error'         => 'Failed to write local email capture file.',
                    'capturePath'   => null,
                ];
            }
            return [
                'outcome'       => 'CAPTURED_LOCAL',
                'status'        => 'CAPTURED_LOCAL',
                'provider'      => 'file_sink',
                'messageId'     => $captureId,
                'httpStatus'    => 200,
                'errorCategory' => null,
                'error'         => null,
                'capturePath'   => $filePath,
            ];
        }

        // 2. Disabled Mode
        if ($driver === 'disabled') {
            return [
                'outcome'       => 'DISABLED',
                'status'        => 'QUEUED',
                'provider'      => 'disabled',
                'messageId'     => null,
                'httpStatus'    => null,
                'errorCategory' => 'DELIVERY_DISABLED',
                'error'         => 'Email delivery is disabled in this environment (MAIL_DRIVER=disabled).',
            ];
        }

        // 3. Deterministic Dev/Test Provider Simulator (strictly prohibited in staging/production!)
        if ($driver === 'simulate') {
            if (!defined('IS_LOCAL_DEV_ENV') || !IS_LOCAL_DEV_ENV) {
                return [
                    'outcome'       => 'PERM_FAIL',
                    'status'        => 'FAILED',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 403,
                    'errorCategory' => 'AUTH_CONFIG',
                    'error'         => 'Provider simulator is strictly disabled outside local development/testing.',
                ];
            }
            $scenario = strtolower(trim($simScenario ?? pfgEnv('NOTIFICATION_SIMULATOR_SCENARIO', 'accept')));
            return match ($scenario) {
                'temp_fail', 'provider_5xx' => [
                    'outcome'       => 'TEMP_FAIL',
                    'status'        => 'RETRY_PENDING',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 503,
                    'errorCategory' => 'PROVIDER_5XX',
                    'error'         => 'Simulated Resend HTTP 503 temporary service unavailable.',
                ],
                'rate_limit', '429' => [
                    'outcome'       => 'TEMP_FAIL',
                    'status'        => 'RETRY_PENDING',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 429,
                    'errorCategory' => 'RATE_LIMITED',
                    'error'         => 'Simulated Resend HTTP 429 rate limit exceeded.',
                ],
                'perm_reject', '422' => [
                    'outcome'       => 'PERM_FAIL',
                    'status'        => 'FAILED',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 422,
                    'errorCategory' => 'INVALID_RECIPIENT',
                    'error'         => 'Simulated Resend HTTP 422 permanent recipient rejection.',
                ],
                'auth_fail', '401' => [
                    'outcome'       => 'PERM_FAIL',
                    'status'        => 'FAILED',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 401,
                    'errorCategory' => 'AUTH_CONFIG',
                    'error'         => 'Simulated Resend HTTP 401 invalid API credentials.',
                ],
                'unverified_domain' => [
                    'outcome'       => 'PERM_FAIL',
                    'status'        => 'FAILED',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 403,
                    'errorCategory' => 'UNVERIFIED_DOMAIN',
                    'error'         => 'Simulated Resend HTTP 403 sending domain not verified.',
                ],
                'timeout', 'delivery_unknown' => [
                    'outcome'       => 'DELIVERY_UNKNOWN',
                    'status'        => 'DELIVERY_UNKNOWN',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 0,
                    'errorCategory' => 'DELIVERY_UNKNOWN',
                    'error'         => 'Simulated network timeout after dispatch; provider acceptance uncertain.',
                ],
                'invalid_response' => [
                    'outcome'       => 'TEMP_FAIL',
                    'status'        => 'RETRY_PENDING',
                    'provider'      => 'resend_simulated',
                    'messageId'     => null,
                    'httpStatus'    => 200,
                    'errorCategory' => 'INVALID_PROVIDER_RESPONSE',
                    'error'         => 'Simulated malformed JSON response missing provider message id.',
                ],
                default => [
                    'outcome'       => 'PROVIDER_ACCEPTED',
                    'status'        => 'PROVIDER_ACCEPTED',
                    'provider'      => 'resend_simulated',
                    'messageId'     => 'sim_' . str_replace('-', '', uuid4()),
                    'httpStatus'    => 200,
                    'errorCategory' => null,
                    'error'         => null,
                ],
            };
        }

        // 4. Live Resend API (HTTPS, Idempotency-Key header, fail-closed — ZERO fallback to insecure PHP mail())
        $apiKey = defined('RESEND_API_KEY') ? trim((string)RESEND_API_KEY) : '';
        if ($apiKey === '') {
            return [
                'outcome'       => 'PERM_FAIL',
                'status'        => 'FAILED',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => null,
                'errorCategory' => 'AUTH_CONFIG',
                'error'         => 'RESEND_API_KEY is not configured. Fail-closed: insecure PHP mail() fallback is disabled.',
            ];
        }
        if (!function_exists('curl_init')) {
            return [
                'outcome'       => 'PERM_FAIL',
                'status'        => 'FAILED',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => null,
                'errorCategory' => 'AUTH_CONFIG',
                'error'         => 'PHP cURL extension is required for Resend HTTPS delivery.',
            ];
        }

        $body = [
            'from'    => $fromAddress,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $body['reply_to'] = $replyTo;
        }
        $payloadJson = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init('https://api.resend.com/emails');
        if ($ch === false) {
            return [
                'outcome'       => 'TEMP_FAIL',
                'status'        => 'RETRY_PENDING',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => null,
                'errorCategory' => 'TEMP_NETWORK',
                'error'         => 'Failed to initialize cURL session.',
            ];
        }

        $timeout = defined('NOTIFICATION_PROVIDER_TIMEOUT') ? (int)NOTIFICATION_PROVIDER_TIMEOUT : 12;
        $safeIdemHeader = substr(preg_replace('/[^A-Za-z0-9_\-:.]/', '', $idempotencyKey) ?? $queueId, 0, 250);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Idempotency-Key: ' . $safeIdemHeader,
            ],
            CURLOPT_POSTFIELDS     => $payloadJson,
        ]);

        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Network timeout after dispatch -> outcome is genuinely uncertain (DELIVERY_UNKNOWN)
        if ($curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === 28) {
            return [
                'outcome'       => 'DELIVERY_UNKNOWN',
                'status'        => 'DELIVERY_UNKNOWN',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => 0,
                'errorCategory' => 'DELIVERY_UNKNOWN',
                'error'         => 'Resend request timed out after dispatch; acceptance uncertain (retry safely with Idempotency-Key).',
            ];
        }
        if ($resp === false || $curlErrno !== 0) {
            return [
                'outcome'       => 'TEMP_FAIL',
                'status'        => 'RETRY_PENDING',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => $httpCode,
                'errorCategory' => 'TEMP_NETWORK',
                'error'         => 'Resend network error: ' . ($curlErr !== '' ? $curlErr : "cURL errno {$curlErrno}"),
            ];
        }

        $decoded = json_decode((string)$resp, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $msgId = is_array($decoded) && !empty($decoded['id']) ? (string)$decoded['id'] : null;
            if ($msgId === null) {
                return [
                    'outcome'       => 'TEMP_FAIL',
                    'status'        => 'RETRY_PENDING',
                    'provider'      => 'resend',
                    'messageId'     => null,
                    'httpStatus'    => $httpCode,
                    'errorCategory' => 'INVALID_PROVIDER_RESPONSE',
                    'error'         => 'Resend returned 2xx without message id.',
                ];
            }
            return [
                'outcome'       => 'PROVIDER_ACCEPTED',
                'status'        => 'PROVIDER_ACCEPTED',
                'provider'      => 'resend',
                'messageId'     => $msgId,
                'httpStatus'    => $httpCode,
                'errorCategory' => null,
                'error'         => null,
            ];
        }

        $errMsg = is_array($decoded) && !empty($decoded['message'])
            ? substr((string)$decoded['message'], 0, 300)
            : "Resend HTTP {$httpCode}";

        if ($httpCode === 429) {
            return [
                'outcome'       => 'TEMP_FAIL',
                'status'        => 'RETRY_PENDING',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => 429,
                'errorCategory' => 'RATE_LIMITED',
                'error'         => $errMsg,
            ];
        }
        if ($httpCode >= 500) {
            return [
                'outcome'       => 'TEMP_FAIL',
                'status'        => 'RETRY_PENDING',
                'provider'      => 'resend',
                'messageId'     => null,
                'httpStatus'    => $httpCode,
                'errorCategory' => 'PROVIDER_5XX',
                'error'         => $errMsg,
            ];
        }

        // Permanent 4xx classification (do NOT retry indefinitely)
        $cat = 'PERMANENT_REJECT';
        if ($httpCode === 401) {
            $cat = 'AUTH_CONFIG';
        } elseif ($httpCode === 403 && stripos($errMsg, 'domain') !== false) {
            $cat = 'UNVERIFIED_DOMAIN';
        } elseif ($httpCode === 400 || $httpCode === 422) {
            $cat = 'INVALID_RECIPIENT';
        }

        return [
            'outcome'       => 'PERM_FAIL',
            'status'        => 'FAILED',
            'provider'      => 'resend',
            'messageId'     => null,
            'httpStatus'    => $httpCode,
            'errorCategory' => $cat,
            'error'         => $errMsg,
        ];
    }
}

if (!function_exists('pfgDispatchClaimedNotification')) {
    /**
     * Renders and dispatches a single already-claimed (`status = 'PROCESSING'`) queue record,
     * updates its truthful delivery status, schedules retry backoff when appropriate,
     * logs the redacted attempt, and wipes `ephemeral_secret_enc` upon handoff or terminal failure.
     */
    function pfgDispatchClaimedNotification(PDO $pdo, array $job, array $options = []): array
    {
        $jobId = (string)$job['id'];
        $attempts = max(1, (int)($job['attempts'] ?? 1));
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? NOTIFICATION_MAX_ATTEMPTS));
        $templateCode = (string)($job['template_code'] ?? 'CUSTOM_EMAIL');
        $eventType = (string)($job['event_type'] ?? $templateCode);
        $now = nowSql();

        // 1. Decrypt ephemeral delivery secret in memory only (if present)
        $ephemeralVars = [];
        if (!empty($job['ephemeral_secret_enc']) && is_string($job['ephemeral_secret_enc'])) {
            $decryptedJson = pfgDecryptValue($job['ephemeral_secret_enc'], 'data');
            if (is_string($decryptedJson) && $decryptedJson !== '') {
                $parsed = json_decode($decryptedJson, true);
                if (is_array($parsed)) {
                    $ephemeralVars = $parsed;
                }
            }
        }

        // 2. Render template (or use pre-rendered custom body for CUSTOM_EMAIL)
        try {
            if ($templateCode === 'CUSTOM_EMAIL') {
                $subject = (string)$job['subject'];
                $html = (string)($job['body_html'] ?? '');
                $text = (string)($job['body_text'] ?? strip_tags($html));
                $vars = jsonDecode($job['payload'] ?? '{}', []);
            } else {
                $vars = jsonDecode($job['payload'] ?? '{}', []);
                if (!is_array($vars)) {
                    $vars = [];
                }
                $rendered = pfgRenderEmailTemplate($templateCode, $vars, $ephemeralVars);
                $subject = $rendered['subject'];
                $html = $rendered['html'];
                $text = $rendered['text'];
                $eventType = $rendered['eventType'];
            }
        } catch (Throwable $renderErr) {
            // Permanent template error — do NOT retry indefinitely; wipe ephemeral secret
            $pdo->prepare("
                UPDATE notification_queue
                SET status = 'FAILED',
                    locked_by = NULL,
                    locked_until = NULL,
                    ephemeral_secret_enc = NULL,
                    error_category = 'TEMPLATE_ERROR',
                    last_error = :err,
                    failed_at = :now,
                    updated_at = :now2
                WHERE id = :id
            ")->execute([
                ':err'  => substr($renderErr->getMessage(), 0, 500),
                ':now'  => $now,
                ':now2' => $now,
                ':id'   => $jobId,
            ]);

            pfgLogDeliveryAttempt($pdo, [
                'jobId'           => $jobId,
                'attemptNumber'   => $attempts,
                'provider'        => (string)($options['driver'] ?? MAIL_DRIVER),
                'eventType'       => 'TEMPLATE_ERROR',
                'status'          => 'FAILED',
                'errorCategory'   => 'TEMPLATE_ERROR',
                'responseDetails' => ['error' => $renderErr->getMessage()],
            ]);

            return ['jobId' => $jobId, 'status' => 'FAILED', 'errorCategory' => 'TEMPLATE_ERROR', 'error' => $renderErr->getMessage()];
        }

        // 3. Deliver through configured provider
        $result = pfgDeliverViaProvider(
            [
                'queueId'        => $jobId,
                'to'             => (string)$job['recipient_email'],
                'subject'        => $subject,
                'html'           => $html,
                'text'           => $text,
                'templateCode'   => $templateCode,
                'eventType'      => $eventType,
                'idempotencyKey' => $job['idempotency_key'] ?: ('pfg-job-' . $jobId),
                'metadata'       => is_array($vars) ? $vars : [],
            ],
            $options['driver'] ?? null,
            $options['simScenario'] ?? null
        );

        $outcome = (string)$result['outcome'];
        $provider = (string)$result['provider'];
        $msgId = $result['messageId'] ?? null;
        $errCat = $result['errorCategory'] ?? null;
        $errText = $result['error'] ?? null;

        // 4. Compute truthful final state + retry schedule + ephemeral secret wipe policy
        $finalStatus = (string)$result['status'];
        $nextRetryAt = null;
        $sentAt = null;
        $acceptedAt = null;
        $failedAt = null;
        $wipeEphemeral = false;

        if ($outcome === 'PROVIDER_ACCEPTED' || $outcome === 'CAPTURED_LOCAL') {
            $sentAt = $now;
            $acceptedAt = $now;
            $wipeEphemeral = true; // Wipe single-use link immediately upon provider acceptance/capture
        } elseif ($outcome === 'TEMP_FAIL') {
            if ($attempts < $maxAttempts) {
                $finalStatus = 'RETRY_PENDING';
                $backoff = pfgComputeRetryBackoffSeconds($attempts);
                $nextRetryAt = nowSql(time() + $backoff);
            } else {
                $finalStatus = 'FAILED';
                $failedAt = $now;
                $wipeEphemeral = true;
            }
        } elseif ($outcome === 'DELIVERY_UNKNOWN') {
            $finalStatus = 'DELIVERY_UNKNOWN';
            $wipeEphemeral = true;
        } elseif ($outcome === 'DISABLED') {
            $finalStatus = 'QUEUED';
        } else {
            // Permanent failure
            $finalStatus = 'FAILED';
            $failedAt = $now;
            $wipeEphemeral = true;
        }

        $updSql = "
            UPDATE notification_queue
            SET status = :status,
                provider = :provider,
                provider_message_id = COALESCE(:msg_id, provider_message_id),
                locked_by = NULL,
                locked_until = NULL,
                next_retry_at = :next_retry_at,
                sent_at = COALESCE(:sent_at, sent_at),
                provider_accepted_at = COALESCE(:accepted_at, provider_accepted_at),
                failed_at = COALESCE(:failed_at, failed_at),
                error_category = :err_cat,
                last_error = :last_error,
                updated_at = :now"
            . ($wipeEphemeral ? ", ephemeral_secret_enc = NULL" : "")
            . " WHERE id = :id";

        $pdo->prepare($updSql)->execute([
            ':status'        => $finalStatus,
            ':provider'      => $provider,
            ':msg_id'        => $msgId,
            ':next_retry_at' => $nextRetryAt,
            ':sent_at'       => $sentAt,
            ':accepted_at'   => $acceptedAt,
            ':failed_at'     => $failedAt,
            ':err_cat'       => $errCat,
            ':last_error'    => $errText !== null ? substr((string)$errText, 0, 500) : null,
            ':now'           => $now,
            ':id'            => $jobId,
        ]);

        pfgLogDeliveryAttempt($pdo, [
            'jobId'             => $jobId,
            'attemptNumber'     => $attempts,
            'provider'          => $provider,
            'providerMessageId' => $msgId,
            'eventType'         => 'DISPATCH_ATTEMPT',
            'status'            => $finalStatus,
            'httpStatus'        => $result['httpStatus'] ?? null,
            'errorCategory'     => $errCat,
            'responseDetails'   => [
                'outcome'     => $outcome,
                'nextRetryAt' => $nextRetryAt,
                'error'       => $errText,
            ],
        ]);

        return [
            'jobId'             => $jobId,
            'status'            => $finalStatus,
            'provider'          => $provider,
            'providerMessageId' => $msgId,
            'errorCategory'     => $errCat,
            'error'             => $errText,
            'nextRetryAt'       => $nextRetryAt,
            'capturePath'       => $result['capturePath'] ?? null,
        ];
    }
}

if (!function_exists('pfgDispatchQueuedIdNow')) {
    /**
     * Atomically claims and dispatches ONE specific QUEUED job by ID (used strictly for
     * local file-sink immediate capture without sweeping unrelated queue records).
     */
    function pfgDispatchQueuedIdNow(PDO $pdo, string $queueId, array $options = []): ?array
    {
        $now = nowSql();
        $upd = $pdo->prepare("
            UPDATE notification_queue
            SET status = 'PROCESSING',
                locked_by = 'targeted-inline',
                locked_until = :lu,
                attempts = attempts + 1,
                last_attempt_at = :now,
                updated_at = :now2
            WHERE id = :id AND status = 'QUEUED'
        ");
        $upd->execute([
            ':lu'   => nowSql(time() + 120),
            ':now'  => $now,
            ':now2' => $now,
            ':id'   => $queueId,
        ]);
        if ($upd->rowCount() === 0) {
            return null;
        }
        $sel = $pdo->prepare('SELECT * FROM notification_queue WHERE id = :id LIMIT 1');
        $sel->execute([':id' => $queueId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        return $row ? pfgDispatchClaimedNotification($pdo, $row, $options) : null;
    }
}

if (!function_exists('pfgProcessNotificationQueueBatch')) {
    /**
     * Runs a bounded queue worker pass: recovers expired leases, claims up to `batchLimit` items,
     * processes each within `maxSeconds`, and exits cleanly with full telemetry.
     */
    function pfgProcessNotificationQueueBatch(PDO $pdo, array $options = []): array
    {
        $batchLimit = max(1, min(200, (int)($options['batchLimit'] ?? NOTIFICATION_BATCH_LIMIT)));
        $maxSeconds = max(1, min(300, (int)($options['maxSeconds'] ?? NOTIFICATION_MAX_SECONDS)));
        $lockTtl = max(30, min(900, (int)($options['lockTtlSeconds'] ?? NOTIFICATION_LOCK_TTL_SECONDS)));
        $workerId = trim((string)($options['workerId'] ?? ('worker-' . getmypid() . '-' . bin2hex(random_bytes(4)))));
        $driver = isset($options['driver']) ? (string)$options['driver'] : null;
        $simScenario = isset($options['simScenario']) ? (string)$options['simScenario'] : null;

        $t0 = microtime(true);
        $effectiveDriver = strtolower(trim($driver ?? MAIL_DRIVER));

        // Do not claim & burn attempts when delivery is explicitly disabled
        if ($effectiveDriver === 'disabled') {
            return [
                'workerId'       => $workerId,
                'driver'         => 'disabled',
                'recovered'      => 0,
                'claimed'        => 0,
                'processed'      => 0,
                'accepted'       => 0,
                'capturedLocal'  => 0,
                'retryScheduled' => 0,
                'failed'         => 0,
                'unknown'        => 0,
                'stoppedByTime'  => false,
                'durationMs'     => (int)round((microtime(true) - $t0) * 1000),
                'items'          => [],
            ];
        }

        $recovered = pfgRecoverAbandonedNotifications($pdo);
        $claimedRows = pfgClaimNotificationBatch($pdo, $workerId, $batchLimit, $lockTtl);

        $stats = [
            'workerId'       => $workerId,
            'driver'         => $effectiveDriver,
            'recovered'      => $recovered,
            'claimed'        => count($claimedRows),
            'processed'      => 0,
            'accepted'       => 0,
            'capturedLocal'  => 0,
            'retryScheduled' => 0,
            'failed'         => 0,
            'unknown'        => 0,
            'stoppedByTime'  => false,
            'durationMs'     => 0,
            'items'          => [],
        ];

        $processedIds = [];
        foreach ($claimedRows as $job) {
            if ((microtime(true) - $t0) >= $maxSeconds) {
                $stats['stoppedByTime'] = true;
                break;
            }
            $res = pfgDispatchClaimedNotification($pdo, $job, [
                'driver'      => $driver,
                'simScenario' => $simScenario,
            ]);
            $processedIds[(string)$job['id']] = true;
            $stats['processed']++;
            $stats['items'][] = $res;

            switch ($res['status']) {
                case 'PROVIDER_ACCEPTED':
                    $stats['accepted']++;
                    break;
                case 'CAPTURED_LOCAL':
                    $stats['capturedLocal']++;
                    break;
                case 'RETRY_PENDING':
                    $stats['retryScheduled']++;
                    break;
                case 'DELIVERY_UNKNOWN':
                    $stats['unknown']++;
                    break;
                case 'FAILED':
                    $stats['failed']++;
                    break;
            }
        }

        // If stopped early by maxSeconds, cleanly release any still-claimed unprocessed jobs back to QUEUED
        if ($stats['stoppedByTime']) {
            foreach ($claimedRows as $job) {
                $jid = (string)$job['id'];
                if (!isset($processedIds[$jid])) {
                    $pdo->prepare("
                        UPDATE notification_queue
                        SET status = 'QUEUED',
                            attempts = GREATEST(0, attempts - 1),
                            locked_by = NULL,
                            locked_until = NULL,
                            updated_at = :now
                        WHERE id = :id AND locked_by = :wid AND status = 'PROCESSING'
                    ")->execute([
                        ':now' => nowSql(),
                        ':id'  => $jid,
                        ':wid' => $workerId,
                    ]);
                }
            }
        }

        $stats['durationMs'] = (int)round((microtime(true) - $t0) * 1000);
        return $stats;
    }
}

if (!function_exists('pfgAdminRetryNotification')) {
    /**
     * Re-queues an eligible FAILED / RETRY_PENDING / DELIVERY_UNKNOWN notification for retry
     * without changing underlying registration/application approval status.
     */
    function pfgAdminRetryNotification(PDO $pdo, string $queueId, string $actorUserId, string $reason = 'Admin manual retry'): array
    {
        $stmt = $pdo->prepare('SELECT * FROM notification_queue WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $queueId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Notification record not found.');
        }

        $retryable = ['FAILED', 'RETRY_PENDING', 'DELIVERY_UNKNOWN'];
        if (!in_array((string)$row['status'], $retryable, true)) {
            throw new InvalidArgumentException("Notification in status {$row['status']} is not eligible for retry; use resend if intentional re-delivery is needed.");
        }

        $now = nowSql();
        $pdo->prepare("
            UPDATE notification_queue
            SET status = 'QUEUED',
                attempts = 0,
                next_retry_at = NULL,
                locked_by = NULL,
                locked_until = NULL,
                failed_at = NULL,
                error_category = NULL,
                last_error = NULL,
                updated_at = :now
            WHERE id = :id
        ")->execute([':now' => $now, ':id' => $queueId]);

        pfgLogDeliveryAttempt($pdo, [
            'jobId'           => $queueId,
            'attemptNumber'   => max(1, (int)$row['attempts']),
            'provider'        => (string)($row['provider'] ?: MAIL_DRIVER),
            'eventType'       => 'ADMIN_RETRY_SCHEDULED',
            'status'          => 'QUEUED',
            'responseDetails' => ['actorUserId' => $actorUserId, 'reason' => $reason, 'previousStatus' => $row['status']],
        ]);

        return ['queueId' => $queueId, 'previousStatus' => $row['status'], 'status' => 'QUEUED'];
    }
}

if (!function_exists('pfgAdminResendNotification')) {
    /**
     * Creates a distinct, audited resend job linked via `resent_from_id` without altering application state.
     * Refuses to resend ephemeral token-bearing templates (verification/password reset must issue a fresh token).
     */
    function pfgAdminResendNotification(PDO $pdo, string $originalQueueId, string $actorUserId, string $reason = 'Admin intentional resend'): array
    {
        $stmt = $pdo->prepare('SELECT * FROM notification_queue WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $originalQueueId]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orig) {
            throw new RuntimeException('Original notification record not found.');
        }

        $tplCode = (string)$orig['template_code'];
        $catalog = pfgGetEmailTemplateCatalog();
        if (isset($catalog[$tplCode]) && !empty($catalog[$tplCode]['ephemeralVars'])) {
            throw new InvalidArgumentException("Template {$tplCode} requires a fresh single-use security token and cannot be blindly resent from queue history.");
        }

        $vars = jsonDecode($orig['payload'] ?? '{}', []);
        if (!is_array($vars)) {
            $vars = [];
        }

        $newKey = 'admin-resend:' . $originalQueueId . ':' . uuid4();
        $enqueued = pfgEnqueueNotification($pdo, [
            'to'              => (string)$orig['recipient_email'],
            'templateCode'    => $tplCode,
            'eventType'       => (string)($orig['event_type'] ?? $tplCode),
            'subject'         => (string)$orig['subject'],
            'html'            => $orig['body_html'] ?? null,
            'text'            => $orig['body_text'] ?? null,
            'variables'       => $vars,
            'recipientUserId' => $orig['recipient_user_id'] ?? null,
            'entityType'      => $orig['entity_type'] ?? null,
            'entityId'        => $orig['entity_id'] ?? null,
            'priority'        => 3,
            'idempotencyKey'  => $newKey,
            'resentFromId'    => $originalQueueId,
        ]);

        pfgLogDeliveryAttempt($pdo, [
            'jobId'           => (string)$enqueued['queueId'],
            'attemptNumber'   => 1,
            'provider'        => MAIL_DRIVER,
            'eventType'       => 'ADMIN_RESEND_CREATED',
            'status'          => 'QUEUED',
            'responseDetails' => ['actorUserId' => $actorUserId, 'resentFromId' => $originalQueueId, 'reason' => $reason],
        ]);

        return [
            'newQueueId'      => (string)$enqueued['queueId'],
            'resentFromId'    => $originalQueueId,
            'status'          => (string)$enqueued['status'],
            'idempotencyKey'  => $newKey,
        ];
    }
}

if (!function_exists('pfgVerifySvixWebhookSignature')) {
    /**
     * Verifies official Resend (Svix) webhook headers using HMAC-SHA256 and ±300s timestamp replay guard.
     * Signed content format per Svix specification: "<svix-id>.<svix-timestamp>.<raw-body>"
     * Secret format: "whsec_<base64-key>" (or raw string if prefix absent).
     */
    function pfgVerifySvixWebhookSignature(
        string $rawBody,
        array $headers,
        string $webhookSecret,
        int $toleranceSeconds = 300,
        ?int $nowTimestamp = null
    ): array {
        $normHeaders = [];
        foreach ($headers as $k => $v) {
            $normHeaders[strtolower(trim((string)$k))] = trim((string)$v);
        }

        $svixId = $normHeaders['svix-id'] ?? $normHeaders['webhook-id'] ?? '';
        $svixTs = $normHeaders['svix-timestamp'] ?? $normHeaders['webhook-timestamp'] ?? '';
        $svixSig = $normHeaders['svix-signature'] ?? $normHeaders['webhook-signature'] ?? '';

        if ($svixId === '' || $svixTs === '' || $svixSig === '') {
            return ['valid' => false, 'error' => 'Missing required Svix signature headers (svix-id, svix-timestamp, svix-signature).'];
        }
        if (!ctype_digit($svixTs)) {
            return ['valid' => false, 'error' => 'Invalid svix-timestamp format.'];
        }

        $tsInt = (int)$svixTs;
        $now = $nowTimestamp ?? time();
        if (abs($now - $tsInt) > $toleranceSeconds) {
            return ['valid' => false, 'error' => 'Webhook timestamp outside permitted replay tolerance window.'];
        }

        $secretTrim = trim($webhookSecret);
        if ($secretTrim === '') {
            return ['valid' => false, 'error' => 'Webhook signing secret is not configured.'];
        }
        if (str_starts_with($secretTrim, 'whsec_')) {
            $keyBytes = base64_decode(substr($secretTrim, 6), true);
            if ($keyBytes === false || $keyBytes === '') {
                return ['valid' => false, 'error' => 'Invalid base64 webhook signing secret.'];
            }
        } else {
            $keyBytes = $secretTrim;
        }

        $signedPayload = "{$svixId}.{$svixTs}.{$rawBody}";
        $expectedSig = base64_encode(hash_hmac('sha256', $signedPayload, $keyBytes, true));

        // svix-signature may contain multiple space-separated entries like "v1,<b64> v1,<b64>"
        $candidates = preg_split('/\s+/', $svixSig) ?: [];
        foreach ($candidates as $cand) {
            $parts = explode(',', $cand, 2);
            if (count($parts) === 2 && $parts[0] === 'v1' && hash_equals($expectedSig, $parts[1])) {
                return [
                    'valid'     => true,
                    'svixId'    => $svixId,
                    'timestamp' => $tsInt,
                ];
            }
        }

        return ['valid' => false, 'error' => 'Invalid Svix webhook signature.'];
    }
}

if (!function_exists('pfgProcessResendWebhook')) {
    /**
     * Processes a signature-verified Resend webhook JSON payload idempotently with monotonic state ordering.
     */
    function pfgProcessResendWebhook(PDO $pdo, string $svixId, array $eventPayload): array
    {
        $eventType = strtolower(trim((string)($eventPayload['type'] ?? '')));
        $data = is_array($eventPayload['data'] ?? null) ? $eventPayload['data'] : [];
        $emailId = trim((string)($data['email_id'] ?? $data['id'] ?? ''));

        if ($eventType === '' || $emailId === '') {
            return ['ok' => false, 'httpStatus' => 400, 'error' => 'Malformed Resend webhook payload (missing type or data.email_id).'];
        }

        // Locate matching notification_queue row by provider_message_id
        $stmt = $pdo->prepare('SELECT * FROM notification_queue WHERE provider_message_id = :mid LIMIT 1');
        $stmt->execute([':mid' => $emailId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            return [
                'ok'         => true,
                'httpStatus' => 200,
                'action'     => 'IGNORED_UNKNOWN_MESSAGE',
                'eventType'  => $eventType,
                'emailId'    => $emailId,
            ];
        }

        $jobId = (string)$job['id'];
        $currentStatus = (string)$job['status'];

        // Map official Resend webhook event types to honest delivery states
        $mappedStatus = match ($eventType) {
            'email.sent'             => 'PROVIDER_ACCEPTED',
            'email.delivery_delayed' => 'PROVIDER_ACCEPTED',
            'email.delivered'        => 'DELIVERED',
            'email.bounced'          => 'BOUNCED',
            'email.complained'       => 'BOUNCED',
            'email.failed'           => 'FAILED',
            'email.suppressed'       => 'SUPPRESSED',
            default                  => $currentStatus,
        };

        // Deduplicate strictly by (provider='resend', provider_event_id=svixId)
        $insertedLog = pfgLogDeliveryAttempt($pdo, [
            'jobId'             => $jobId,
            'attemptNumber'     => max(1, (int)$job['attempts']),
            'provider'          => 'resend',
            'providerMessageId' => $emailId,
            'eventType'         => $eventType,
            'providerEventId'   => $svixId,
            'status'            => $mappedStatus,
            'httpStatus'        => 200,
            'responseDetails'   => [
                'type'      => $eventType,
                'createdAt' => $eventPayload['created_at'] ?? null,
                'bounce'    => $data['bounce'] ?? null,
            ],
        ]);

        if (!$insertedLog) {
            return [
                'ok'         => true,
                'httpStatus' => 200,
                'action'     => 'DUPLICATE_EVENT_IGNORED',
                'queueId'    => $jobId,
                'status'     => $currentStatus,
            ];
        }

        // Monotonic state precedence: prevent out-of-order older events (e.g. email.sent after email.delivered)
        // from ever regressing a newer delivery state!
        $prec = PFG_NOTIFICATION_STATUS_PRECEDENCE;
        $curRank = $prec[$currentStatus] ?? 0;
        $newRank = $prec[$mappedStatus] ?? 0;

        $eventTimeStr = !empty($eventPayload['created_at']) ? date('Y-m-d H:i:s', strtotime((string)$eventPayload['created_at']) ?: time()) : nowSql();
        $now = nowSql();

        if ($newRank < $curRank) {
            return [
                'ok'             => true,
                'httpStatus'     => 200,
                'action'         => 'OUT_OF_ORDER_IGNORED',
                'queueId'        => $jobId,
                'currentStatus'  => $currentStatus,
                'ignoredEvent'   => $eventType,
            ];
        }

        $deliveredAt = $mappedStatus === 'DELIVERED' ? $eventTimeStr : null;
        $bouncedAt = $mappedStatus === 'BOUNCED' ? $eventTimeStr : null;
        $failedAt = in_array($mappedStatus, ['FAILED', 'SUPPRESSED'], true) ? $eventTimeStr : null;

        $pdo->prepare('
            UPDATE notification_queue
            SET status = :status,
                last_provider_event = :evt,
                last_provider_event_at = :evt_at,
                delivered_at = COALESCE(:del_at, delivered_at),
                bounced_at = COALESCE(:bnc_at, bounced_at),
                failed_at = COALESCE(:fail_at, failed_at),
                updated_at = :now
            WHERE id = :id
        ')->execute([
            ':status'  => $mappedStatus,
            ':evt'     => $eventType,
            ':evt_at'  => $eventTimeStr,
            ':del_at'  => $deliveredAt,
            ':bnc_at'  => $bouncedAt,
            ':fail_at' => $failedAt,
            ':now'     => $now,
            ':id'      => $jobId,
        ]);

        return [
            'ok'             => true,
            'httpStatus'     => 200,
            'action'         => 'STATUS_UPDATED',
            'queueId'        => $jobId,
            'previousStatus' => $currentStatus,
            'newStatus'      => $mappedStatus,
            'eventType'      => $eventType,
        ];
    }
}
