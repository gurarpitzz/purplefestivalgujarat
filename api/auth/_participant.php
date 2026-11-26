<?php
declare(strict_types=1);

/**
 * Shared Phase 5 participant-auth helpers (email verification tokens, notification hand-off, validation).
 * Library file only: never routable (direct HTTP requests are answered with 404).
 */

$pfgAuthReqPath = strtolower((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
if (str_ends_with($pfgAuthReqPath, '/_participant') || str_ends_with($pfgAuthReqPath, '/_participant.php')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n";
    exit;
}
unset($pfgAuthReqPath);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/notification-templates.php';
require_once dirname(__DIR__, 2) . '/includes/notification-service.php';

const PFG_AUTH_EMAIL_TOKEN_TTL_SECONDS = 1800; // 30 minutes (single use)
const PFG_AUTH_GENERIC_VERIFY_MESSAGE = 'If this email is eligible, a verification link has been sent.';

if (!function_exists('pfgAuthNormalizeEmail')) {
    function pfgAuthNormalizeEmail($value): string
    {
        return strtolower(trim((string)$value));
    }
}

if (!function_exists('pfgAuthIsValidEmail')) {
    function pfgAuthIsValidEmail(string $email): bool
    {
        return $email !== '' && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('pfgAuthIsValidPhone')) {
    function pfgAuthIsValidPhone(string $phone): bool
    {
        return (bool)preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone);
    }
}

if (!function_exists('pfgAuthBoolish')) {
    function pfgAuthBoolish($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        return is_string($value) && in_array(strtolower(trim($value)), ['true', '1', 'on', 'yes'], true);
    }
}

if (!function_exists('pfgAuthPasswordPolicyError')) {
    /**
     * Participant password policy: 8-128 characters containing at least one letter and one number.
     */
    function pfgAuthPasswordPolicyError(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (strlen($password) > 128) {
            return 'Password must not exceed 128 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password must include at least one letter and one number.';
        }
        return null;
    }
}

if (!function_exists('pfgAuthHasStaffRole')) {
    function pfgAuthHasStaffRole(array $roles): bool
    {
        return (bool)array_intersect($roles, ['SUPERADMIN', 'ADMIN', 'RGST']);
    }
}

if (!function_exists('pfgAuthGenerateRawToken')) {
    function pfgAuthGenerateRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

if (!function_exists('pfgAuthIssueEmailToken')) {
    /**
     * Invalidates prior unused tokens of the same purpose for the user, then issues a fresh single-use
     * 30-minute token. Only the HMAC-SHA256 hash is persisted; the raw token is returned to the caller
     * solely for ephemeral e-mail delivery and must NEVER be returned in an HTTP response.
     */
    function pfgAuthIssueEmailToken(PDO $pdo, string $userId, string $purpose, string $targetEmail): string
    {
        $now = nowSql();
        $pdo->prepare('UPDATE email_verification_tokens SET used_at = :now WHERE user_id = :uid AND purpose = :p AND used_at IS NULL')
            ->execute([':now' => $now, ':uid' => $userId, ':p' => $purpose]);

        $raw = pfgAuthGenerateRawToken();
        $pdo->prepare('
            INSERT INTO email_verification_tokens (id, user_id, purpose, target_email, token_hash, expires_at, created_ip, created_at)
            VALUES (:id, :uid, :p, :te, :th, :exp, :ip, :now)
        ')->execute([
            ':id'  => uuid4(),
            ':uid' => $userId,
            ':p'   => $purpose,
            ':te'  => $targetEmail,
            ':th'  => hashToken($raw),
            ':exp' => nowSql(time() + PFG_AUTH_EMAIL_TOKEN_TTL_SECONDS),
            ':ip'  => getClientIp(),
            ':now' => $now,
        ]);
        return $raw;
    }
}

if (!function_exists('pfgAuthVerificationUrl')) {
    function pfgAuthVerificationUrl(string $rawToken): string
    {
        return APP_URL . '/verify-email?token=' . urlencode($rawToken);
    }
}

if (!function_exists('pfgAuthEnqueueEmail')) {
    /**
     * Enqueues a transactional account e-mail via the Phase 4 outbox. Safe inside a DB transaction.
     * Returns the queue id (or null when enqueueing failed and $swallow is true).
     */
    function pfgAuthEnqueueEmail(
        PDO $pdo,
        string $to,
        string $templateCode,
        array $variables,
        array $ephemeralVars,
        ?string $userId,
        int $priority = 2
    ): ?string {
        $event = [
            'to'              => $to,
            'templateCode'    => $templateCode,
            'variables'       => $variables,
            'recipientUserId' => $userId,
            'entityType'      => 'user',
            'entityId'        => $userId,
            'priority'        => $priority,
        ];
        if (!empty($ephemeralVars)) {
            $event['ephemeralVars'] = $ephemeralVars;
        }
        $res = pfgEnqueueNotification($pdo, $event);
        return isset($res['queueId']) ? (string)$res['queueId'] : null;
    }
}

if (!function_exists('pfgAuthDispatchLocal')) {
    /**
     * In local `MAIL_DRIVER=file` mode, deliver the specific queued message immediately so dev/tests can
     * capture it in the mail sink. Production relies on the queue worker (cron). Never throws.
     */
    function pfgAuthDispatchLocal(PDO $pdo, ?string $queueId): void
    {
        if ($queueId === null || $queueId === '' || strtolower((string)MAIL_DRIVER) !== 'file') {
            return;
        }
        try {
            pfgDispatchQueuedIdNow($pdo, $queueId);
        } catch (Throwable $e) {
            error_log('pfgAuthDispatchLocal warning: ' . $e->getMessage());
        }
    }
}

if (!function_exists('pfgAuthSendSecurityAlert')) {
    /**
     * Best-effort SECURITY_ALERT (never includes secrets). Never throws.
     */
    function pfgAuthSendSecurityAlert(PDO $pdo, string $to, string $userId, string $fullName, string $summary): void
    {
        try {
            $qid = pfgAuthEnqueueEmail($pdo, $to, 'SECURITY_ALERT', [
                'fullName'             => $fullName !== '' ? $fullName : 'Participant',
                'securityEventSummary' => $summary,
                'occurredAtIst'        => formatIstTimestamp(),
            ], [], $userId, 1);
            pfgAuthDispatchLocal($pdo, $qid);
        } catch (Throwable $e) {
            error_log('pfgAuthSendSecurityAlert warning: ' . $e->getMessage());
        }
    }
}

if (!function_exists('pfgAuthIsDuplicateKey')) {
    function pfgAuthIsDuplicateKey(Throwable $e): bool
    {
        if ($e instanceof PDOException) {
            $state = (string)($e->errorInfo[0] ?? $e->getCode());
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            return $state === '23000' || $driverCode === 1062 || $driverCode === 19;
        }
        return false;
    }
}

if (!function_exists('pfgAuthLoadRoles')) {
    function pfgAuthLoadRoles(PDO $pdo, string $userId): array
    {
        return getUserRolesAndPermissions($userId, $pdo)['roles'];
    }
}
