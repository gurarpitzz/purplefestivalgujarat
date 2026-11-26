<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Canonical Mailer Facade (Phase 4 Refactored)
 *
 * Delegates ALL transactional email creation, WCAG 2.2 AA template rendering, local file capture,
 * and Resend API delivery through `includes/notification-service.php` and `includes/notification-templates.php`.
 *
 * Guarantees:
 * - Zero direct Resend cURL duplication.
 * - Zero fallback to insecure PHP `mail()`.
 * - Zero participant QR entry pass images or `api.qrserver.com` URLs (Decision D-01).
 * - Zero raw credentials, MFA secrets, disability diagnoses, or reset tokens stored in `notification_queue.payload`.
 * - Preserves full backward compatibility for existing callers (`sendFestivalEmail`,
 *   `sendRegistrationConfirmationEmail`, `sendApplicationReceivedEmail`).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notification-templates.php';
require_once __DIR__ . '/notification-service.php';

if (!function_exists('sendFestivalEmail')) {
    /**
     * Enqueues a notification through the centralized service and, when `dispatchImmediately` is true
     * (default for legacy synchronous callers & local file-sink testing), claims & dispatches that single job.
     * Pass `'queueOnly' => true` for strict transactional-outbox deferred processing by the CLI cron worker.
     */
    function sendFestivalEmail(PDO $pdo, array $options): array
    {
        $to = strtolower(trim((string)($options['to'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent'      => false,
                'provider'  => 'none',
                'messageId' => null,
                'status'    => 'FAILED',
                'error'     => 'Invalid recipient email address.',
            ];
        }

        $templateCode = trim((string)($options['templateCode'] ?? 'CUSTOM_EMAIL'));
        $catalog = pfgGetEmailTemplateCatalog();
        $resolved = pfgResolveTemplateCode($templateCode);
        $useTemplate = isset($catalog[$resolved]) && !empty($options['variables']);

        $enqueued = pfgEnqueueNotification($pdo, [
            'to'              => $to,
            'templateCode'    => $useTemplate ? $resolved : 'CUSTOM_EMAIL',
            'eventType'       => (string)($options['eventType'] ?? ($useTemplate ? $catalog[$resolved]['triggerEvent'] : $templateCode)),
            'subject'         => (string)($options['subject'] ?? 'Purple Festival Gujarat 2027'),
            'html'            => (string)($options['html'] ?? ''),
            'text'            => (string)($options['text'] ?? strip_tags((string)($options['html'] ?? ''))),
            'variables'       => $useTemplate ? (array)$options['variables'] : (is_array($options['metadata'] ?? null) ? $options['metadata'] : []),
            'ephemeralVars'   => is_array($options['ephemeralVars'] ?? null) ? $options['ephemeralVars'] : [],
            'recipientUserId' => $options['recipientUserId'] ?? null,
            'entityType'      => $options['entityType'] ?? null,
            'entityId'        => $options['entityId'] ?? null,
            'priority'        => (int)($options['priority'] ?? 5),
            'idempotencyKey'  => $options['idempotencyKey'] ?? null,
            'createInApp'     => !empty($options['createInApp']),
            'inAppTitle'      => $options['inAppTitle'] ?? null,
            'inAppMessage'    => $options['inAppMessage'] ?? null,
            'inAppActionUrl'  => $options['inAppActionUrl'] ?? null,
        ]);

        if (!empty($enqueued['deduped'])) {
            return [
                'sent'      => in_array((string)$enqueued['status'], ['PROVIDER_ACCEPTED', 'DELIVERED', 'CAPTURED_LOCAL'], true),
                'provider'  => (string)($enqueued['provider'] ?? MAIL_DRIVER),
                'messageId' => $enqueued['providerMessageId'] ?? null,
                'status'    => (string)$enqueued['status'],
                'queueId'   => (string)$enqueued['queueId'],
                'error'     => null,
                'deduped'   => true,
            ];
        }

        if (!empty($options['queueOnly']) || MAIL_DRIVER === 'disabled') {
            return [
                'sent'      => false,
                'provider'  => MAIL_DRIVER,
                'messageId' => null,
                'status'    => 'QUEUED',
                'queueId'   => (string)$enqueued['queueId'],
                'error'     => MAIL_DRIVER === 'disabled' ? 'Email delivery is disabled in this environment.' : null,
            ];
        }

        // Claim this exact newly enqueued job and dispatch cleanly
        $jobId = (string)$enqueued['queueId'];
        $now = nowSql();
        $pdo->prepare("
            UPDATE notification_queue
            SET status = 'PROCESSING',
                locked_by = 'inline-dispatch',
                locked_until = :lu,
                attempts = attempts + 1,
                last_attempt_at = :now,
                updated_at = :now2
            WHERE id = :id AND status = 'QUEUED'
        ")->execute([
            ':lu'   => nowSql(time() + 120),
            ':now'  => $now,
            ':now2' => $now,
            ':id'   => $jobId,
        ]);

        $jobStmt = $pdo->prepare('SELECT * FROM notification_queue WHERE id = :id LIMIT 1');
        $jobStmt->execute([':id' => $jobId]);
        $jobRow = $jobStmt->fetch(PDO::FETCH_ASSOC);
        if (!$jobRow) {
            return [
                'sent'      => false,
                'provider'  => MAIL_DRIVER,
                'messageId' => null,
                'status'    => 'FAILED',
                'queueId'   => $jobId,
                'error'     => 'Queued job could not be loaded for dispatch.',
            ];
        }

        $res = pfgDispatchClaimedNotification($pdo, $jobRow);
        $isAccepted = in_array((string)$res['status'], ['PROVIDER_ACCEPTED', 'CAPTURED_LOCAL'], true);

        return [
            'sent'      => $isAccepted,
            'provider'  => (string)$res['provider'],
            'messageId' => $res['providerMessageId'] ?? null,
            'status'    => (string)$res['status'],
            'queueId'   => $jobId,
            'error'     => $res['error'] ?? null,
        ];
    }
}

if (!function_exists('sendRegistrationConfirmationEmail')) {
    /**
     * Dispatches the WCAG 2.2 AA confirmation / approval email via the centralized template system
     * (Attendee, Volunteer, or SLI approval). Zero QR pass images, zero external QR URLs (D-01).
     */
    function sendRegistrationConfirmationEmail(PDO $pdo, array $params): array
    {
        $categoryCode = strtoupper(trim((string)($params['categoryCode'] ?? $params['categoryName'] ?? 'ATTENDEE')));
        $templateCode = match ($categoryCode) {
            'VOLUNTEER' => 'VOLUNTEER_APPLICATION_APPROVED',
            'SLI'       => 'SLI_APPLICATION_APPROVED',
            default     => 'ATTENDEE_REGISTRATION_CONFIRMED',
        };

        $vars = [
            'fullName'         => trim((string)($params['fullName'] ?? 'Participant')),
            'registrationCode' => trim((string)($params['registrationCode'] ?? '')),
            'categoryName'     => trim((string)($params['categoryName'] ?? 'Festival Participant')),
            'status'           => trim((string)($params['status'] ?? 'CONFIRMED')),
            'dutyDetails'      => isset($params['dutyDetails']) && is_array($params['dutyDetails']) ? $params['dutyDetails'] : null,
        ];

        return sendFestivalEmail($pdo, [
            'to'              => (string)($params['to'] ?? ''),
            'templateCode'    => $templateCode,
            'variables'       => $vars,
            'recipientUserId' => $params['recipientUserId'] ?? null,
            'entityType'      => 'registration',
            'entityId'        => $params['registrationId'] ?? null,
            'idempotencyKey'  => $params['idempotencyKey'] ?? null,
            'queueOnly'       => !empty($params['queueOnly']),
            'createInApp'     => !empty($params['createInApp']),
            'inAppTitle'      => 'Registration Confirmed (' . $vars['registrationCode'] . ')',
            'inAppMessage'    => "Your {$vars['categoryName']} registration ({$vars['registrationCode']}) status is {$vars['status']}.",
        ]);
    }
}

if (!function_exists('sendRegistrationQrPassEmail')) {
    /**
     * Deprecated wrapper preserved during QR retirement (D-01).
     * Never generates QR tokens or images.
     */
    function sendRegistrationQrPassEmail(PDO $pdo, array $params): array
    {
        unset($params['rawToken'], $params['credentialCode']);
        return sendRegistrationConfirmationEmail($pdo, $params);
    }
}

if (!function_exists('sendApplicationReceivedEmail')) {
    /**
     * Dispatches the WCAG 2.2 AA application acknowledgment email for Volunteer / SLI applications
     * awaiting review (never implies confirmed approval).
     */
    function sendApplicationReceivedEmail(PDO $pdo, array $params): array
    {
        $categoryCode = strtoupper(trim((string)($params['categoryCode'] ?? $params['categoryName'] ?? 'VOLUNTEER')));
        $templateCode = $categoryCode === 'SLI' ? 'SLI_APPLICATION_RECEIVED' : 'VOLUNTEER_APPLICATION_RECEIVED';

        $vars = [
            'fullName'         => trim((string)($params['fullName'] ?? 'Applicant')),
            'registrationCode' => trim((string)($params['registrationCode'] ?? '')),
            'categoryName'     => trim((string)($params['categoryName'] ?? 'Festival Role')),
            'status'           => 'SUBMITTED',
        ];

        return sendFestivalEmail($pdo, [
            'to'              => (string)($params['to'] ?? ''),
            'templateCode'    => $templateCode,
            'variables'       => $vars,
            'recipientUserId' => $params['recipientUserId'] ?? null,
            'entityType'      => 'registration',
            'entityId'        => $params['registrationId'] ?? null,
            'idempotencyKey'  => $params['idempotencyKey'] ?? null,
            'queueOnly'       => !empty($params['queueOnly']),
            'createInApp'     => !empty($params['createInApp']),
            'inAppTitle'      => 'Application Received (' . $vars['registrationCode'] . ')',
            'inAppMessage'    => "Your {$vars['categoryName']} application ({$vars['registrationCode']}) has been submitted for coordinator review.",
        ]);
    }
}
