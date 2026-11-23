<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Email & Resend API Delivery Service
 * Delivers participant QR entry passes and application confirmations via email
 * using Resend API (when RESEND_API_KEY is configured) or local PHP mail() fallback,
 * while logging every notification in `notification_queue`.
 */

require_once __DIR__ . '/config.php';

if (!function_exists('sendFestivalEmail')) {
    /**
     * Sends an email via Resend API (or PHP mail fallback) and logs into notification_queue.
     *
     * @param PDO $pdo
     * @param array{
     *   to:string,
     *   subject:string,
     *   html:string,
     *   text?:string,
     *   templateCode?:string,
     *   recipientUserId?:?string,
     *   metadata?:array
     * } $options
     * @return array{sent:bool, provider:string, messageId:?string, status:string, error:?string}
     */
    function sendFestivalEmail(PDO $pdo, array $options): array
    {
        $to = strtolower(trim((string)($options['to'] ?? '')));
        $subject = trim((string)($options['subject'] ?? 'Purple Festival Gujarat 2027'));
        $html = (string)($options['html'] ?? '');
        $text = (string)($options['text'] ?? strip_tags($html));
        $templateCode = trim((string)($options['templateCode'] ?? 'CUSTOM_EMAIL'));
        $recipientUserId = isset($options['recipientUserId']) && $options['recipientUserId'] !== ''
            ? (string)$options['recipientUserId']
            : null;
        $metadata = isset($options['metadata']) && is_array($options['metadata'])
            ? $options['metadata']
            : [];

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent'      => false,
                'provider'  => 'none',
                'messageId' => null,
                'status'    => 'FAILED',
                'error'     => 'Invalid recipient email address.',
            ];
        }

        $apiKey = defined('RESEND_API_KEY') ? trim((string)RESEND_API_KEY) : '';
        $fromAddress = defined('EMAIL_FROM') && EMAIL_FROM !== ''
            ? (string)EMAIL_FROM
            : 'Purple Festival Gujarat 2027 <noreply@purplefestivalgujarat.com>';

        $sent = false;
        $provider = 'queue';
        $messageId = null;
        $status = 'QUEUED';
        $errMsg = null;

        if ($apiKey !== '' && function_exists('curl_init')) {
            $provider = 'resend';
            $payload = json_encode([
                'from'    => $fromAddress,
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $ch = curl_init('https://api.resend.com/emails');
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 12,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $apiKey,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS     => $payload,
                ]);
                $resp = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($resp !== false && $httpCode >= 200 && $httpCode < 300) {
                    $decoded = json_decode((string)$resp, true);
                    $sent = true;
                    $status = 'SENT';
                    $messageId = is_array($decoded) && isset($decoded['id']) ? (string)$decoded['id'] : null;
                } else {
                    $errMsg = $curlErr !== '' ? $curlErr : "Resend HTTP {$httpCode}: " . (string)$resp;
                    error_log('[Mailer] Resend API failure: ' . $errMsg);
                }
            }
        }

        if (!$sent && $apiKey === '' && function_exists('mail')) {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $fromAddress,
            ];
            $mailOk = @mail($to, $subject, $html, implode("\r\n", $headers));
            if ($mailOk) {
                $provider = 'php_mail';
                $sent = true;
                $status = 'SENT';
            }
        }

        try {
            $now = nowSql();
            $stmt = $pdo->prepare(
                'INSERT INTO notification_queue
                (id, template_code, recipient_user_id, recipient_email, subject, payload, status, provider, provider_message_id, sent_at, created_at, updated_at)
                VALUES (:id, :template_code, :uid, :email, :subject, :payload, :status, :provider, :msg_id, :sent_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                ':id'            => uuid4(),
                ':template_code' => $templateCode,
                ':uid'           => $recipientUserId,
                ':email'         => $to,
                ':subject'       => $subject,
                ':payload'       => jsonEncode(array_merge($metadata, [
                    'provider'  => $provider,
                    'messageId' => $messageId,
                    'error'     => $errMsg,
                ])),
                ':status'        => $status,
                ':provider'      => $provider,
                ':msg_id'        => $messageId,
                ':sent_at'       => $sent ? $now : null,
                ':created_at'    => $now,
                ':updated_at'    => $now,
            ]);
        } catch (Throwable $e) {
            try {
                $now = nowSql();
                $fallbackStmt = $pdo->prepare(
                    'INSERT INTO notification_queue
                    (id, template_code, recipient_user_id, recipient_email, subject, payload, status, created_at, updated_at)
                    VALUES (:id, :template_code, :uid, :email, :subject, :payload, :status, :created_at, :updated_at)'
                );
                $fallbackStmt->execute([
                    ':id'            => uuid4(),
                    ':template_code' => $templateCode,
                    ':uid'           => $recipientUserId,
                    ':email'         => $to,
                    ':subject'       => $subject,
                    ':payload'       => jsonEncode(array_merge($metadata, [
                        'provider'  => $provider,
                        'messageId' => $messageId,
                        'error'     => $errMsg,
                    ])),
                    ':status'        => $status,
                    ':created_at'    => $now,
                    ':updated_at'    => $now,
                ]);
            } catch (Throwable $e2) {
                error_log('[Mailer] notification_queue insert skipped: ' . $e2->getMessage());
            }
        }

        return [
            'sent'      => $sent,
            'provider'  => $provider,
            'messageId' => $messageId,
            'status'    => $status,
            'error'     => $errMsg,
        ];
    }
}

if (!function_exists('sendRegistrationQrPassEmail')) {
    /**
     * Sends the official Digital QR Entry Pass email to an attendee, delegate, volunteer, or SLI professional.
     */
    function sendRegistrationQrPassEmail(PDO $pdo, array $params): array
    {
        $to = (string)($params['to'] ?? '');
        $fullName = trim((string)($params['fullName'] ?? 'Participant'));
        $registrationCode = trim((string)($params['registrationCode'] ?? ''));
        $credentialCode = trim((string)($params['credentialCode'] ?? $registrationCode));
        $categoryName = trim((string)($params['categoryName'] ?? 'Festival Participant'));
        $status = trim((string)($params['status'] ?? 'CONFIRMED'));
        $rawToken = trim((string)($params['rawToken'] ?? ''));
        $recipientUserId = isset($params['recipientUserId']) ? (string)$params['recipientUserId'] : null;
        $dutyDetails = isset($params['dutyDetails']) && is_array($params['dutyDetails']) ? $params['dutyDetails'] : null;

        $qrImgUrl = $rawToken !== ''
            ? 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=12&data=' . rawurlencode($rawToken)
            : '';

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $dutyHtml = '';
        $dutyText = '';
        if ($dutyDetails && count(array_filter($dutyDetails)) > 0) {
            $dutyRows = '';
            foreach ($dutyDetails as $label => $val) {
                if ($val === null || $val === '' || $val === []) {
                    continue;
                }
                $valStr = is_array($val) ? implode(', ', $val) : (string)$val;
                $dutyRows .= '<tr><td style="padding:4px 8px;color:#46537C;font-size:13px;">' . $esc((string)$label) . '</td><td style="padding:4px 8px;color:#202D59;font-weight:600;font-size:13px;">' . $esc($valStr) . '</td></tr>';
                $dutyText .= "\n- {$label}: {$valStr}";
            }
            if ($dutyRows !== '') {
                $dutyHtml = '<div style="margin-top:20px;padding:16px;background:#F5F0FF;border:1px solid #C7C7F4;border-radius:12px;">'
                    . '<h3 style="margin:0 0 8px 0;color:#202D59;font-size:15px;">Assigned Venue &amp; Duty Schedule</h3>'
                    . '<table style="width:100%;border-collapse:collapse;">' . $dutyRows . '</table></div>';
            }
        }

        $qrBlockHtml = $qrImgUrl !== ''
            ? '<div style="margin:24px 0;padding:20px;text-align:center;background:#F8F6FC;border:2px solid #A459FF;border-radius:16px;">'
                . '<img src="' . $esc($qrImgUrl) . '" alt="QR Entry Pass for ' . $esc($registrationCode) . '" width="240" height="240" style="display:block;margin:0 auto;border-radius:8px;background:#ffffff;padding:10px;" />'
                . '<p style="margin:12px 0 4px 0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#46537C;">Credential ID</p>'
                . '<p style="margin:0;font-family:monospace;font-size:18px;font-weight:700;color:#202D59;">' . $esc($credentialCode) . '</p>'
                . '<p style="margin:10px 0 0 0;font-size:12px;color:#46537C;">This QR contains an opaque verification token with zero personal or medical disclosures.</p>'
                . '</div>'
            : '';

        $html = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#F2E9FE;font-family:Arial,Helvetica,sans-serif;color:#202D59;">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #C7C7F4;">'
            . '<div style="background:#202D59;color:#ffffff;padding:24px 28px;">'
            . '<p style="margin:0;font-size:12px;letter-spacing:0.15em;text-transform:uppercase;color:#DDDDF9;">21–23 January 2027 · Vadodara, Gujarat</p>'
            . '<h1 style="margin:8px 0 0 0;font-size:24px;line-height:1.25;">Purple Festival Gujarat 2027</h1>'
            . '</div>'
            . '<div style="padding:28px;">'
            . '<p style="margin:0 0 14px 0;font-size:16px;">Dear <strong>' . $esc($fullName) . '</strong>,</p>'
            . '<p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;color:#46537C;">Your registration is <strong>' . $esc($status) . '</strong> under the category <strong>' . $esc($categoryName) . '</strong>. Please present this email QR code or your Registration Code at the reception desk in Vadodara for check-in.</p>'
            . '<div style="padding:14px 16px;background:#DDDDF9;border-radius:10px;">'
            . '<p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#46537C;">Registration Code</p>'
            . '<p style="margin:4px 0 0 0;font-family:monospace;font-size:20px;font-weight:700;color:#202D59;">' . $esc($registrationCode) . '</p>'
            . '</div>'
            . $qrBlockHtml
            . $dutyHtml
            . '<p style="margin:24px 0 0 0;font-size:13px;line-height:1.5;color:#46537C;">If you require any accessibility assistance at the venue, write to <a href="mailto:purplefestivalvadodara@gmail.com" style="color:#A459FF;">purplefestivalvadodara@gmail.com</a>.</p>'
            . '</div>'
            . '</div></body></html>';

        $text = "Purple Festival Gujarat 2027 (21–23 January 2027 · Vadodara, Gujarat)\n\n"
            . "Dear {$fullName},\n"
            . "Your registration ({$registrationCode}) for {$categoryName} is {$status}.\n"
            . "Credential ID: {$credentialCode}\n"
            . ($rawToken !== '' ? "Verification Token: {$rawToken}\n" : '')
            . ($qrImgUrl !== '' ? "QR Pass Image: {$qrImgUrl}\n" : '')
            . $dutyText
            . "\nPresent this email or Registration ID at any festival reception desk.\n";

        $subject = "Your QR Entry Pass: Purple Festival Gujarat 2027 ({$registrationCode})";

        return sendFestivalEmail($pdo, [
            'to'              => $to,
            'subject'         => $subject,
            'html'            => $html,
            'text'            => $text,
            'templateCode'    => 'REGISTRATION_QR_PASS',
            'recipientUserId' => $recipientUserId,
            'metadata'        => [
                'registrationCode' => $registrationCode,
                'credentialCode'   => $credentialCode,
                'category'         => $categoryName,
                'status'           => $status,
                'rawToken'         => $rawToken,
            ],
        ]);
    }
}

if (!function_exists('sendApplicationReceivedEmail')) {
    /**
     * Sends an acknowledgment email for applications awaiting coordinator review (Volunteer / SLI).
     */
    function sendApplicationReceivedEmail(PDO $pdo, array $params): array
    {
        $to = (string)($params['to'] ?? '');
        $fullName = trim((string)($params['fullName'] ?? 'Applicant'));
        $registrationCode = trim((string)($params['registrationCode'] ?? ''));
        $categoryName = trim((string)($params['categoryName'] ?? 'Festival Role'));
        $recipientUserId = isset($params['recipientUserId']) ? (string)$params['recipientUserId'] : null;

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#F2E9FE;font-family:Arial,Helvetica,sans-serif;color:#202D59;">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #C7C7F4;">'
            . '<div style="background:#202D59;color:#ffffff;padding:24px 28px;">'
            . '<p style="margin:0;font-size:12px;letter-spacing:0.15em;text-transform:uppercase;color:#DDDDF9;">21–23 January 2027 · Vadodara, Gujarat</p>'
            . '<h1 style="margin:8px 0 0 0;font-size:24px;">Purple Festival Gujarat 2027</h1>'
            . '</div>'
            . '<div style="padding:28px;">'
            . '<p style="margin:0 0 14px 0;font-size:16px;">Dear <strong>' . $esc($fullName) . '</strong>,</p>'
            . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#46537C;">Thank you for submitting your <strong>' . $esc($categoryName) . '</strong> application. Our festival coordinators are reviewing your details.</p>'
            . '<div style="padding:14px 16px;background:#DDDDF9;border-radius:10px;margin-bottom:16px;">'
            . '<p style="margin:0;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#46537C;">Application Reference</p>'
            . '<p style="margin:4px 0 0 0;font-family:monospace;font-size:20px;font-weight:700;color:#202D59;">' . $esc($registrationCode) . '</p>'
            . '</div>'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#46537C;">Once approved, your official <strong>Digital QR Entry Pass</strong> and venue duty schedule will be sent straight to this email address.</p>'
            . '</div></div></body></html>';

        $text = "Dear {$fullName},\n\n"
            . "Thank you for submitting your {$categoryName} application for Purple Festival Gujarat 2027 (Ref: {$registrationCode}).\n"
            . "Once our coordinators approve your application, your official Digital QR Entry Pass and duty schedule will be sent to this email address.\n";

        return sendFestivalEmail($pdo, [
            'to'              => $to,
            'subject'         => "Application Received: Purple Festival Gujarat 2027 ({$registrationCode})",
            'html'            => $html,
            'text'            => $text,
            'templateCode'    => 'APPLICATION_SUBMITTED',
            'recipientUserId' => $recipientUserId,
            'metadata'        => [
                'registrationCode' => $registrationCode,
                'category'         => $categoryName,
            ],
        ]);
    }
}
