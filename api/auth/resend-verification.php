<?php
declare(strict_types=1);

/**
 * POST /api/auth/resend-verification
 * Public (e-mail in body) or authenticated (defaults to the caller's own e-mail).
 * - Rate-limited: 3/hour per e-mail AND 10/hour per IP (counted for every request, existing account or not).
 * - Invalidates prior unused SIGNUP_VERIFY tokens, issues a fresh single-use 30-minute token and enqueues
 *   EMAIL_VERIFICATION_RESEND with an encrypted ephemeral actionUrl.
 * - Always answers with the same uniform 200 (zero account-existence leakage).
 */

require_once __DIR__ . '/_participant.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

try {
    $pdo = getDb();
    $clientIp = getClientIp() ?: 'unknown';
    $input = readRequestInput();

    $email = pfgAuthNormalizeEmail($input['email'] ?? '');
    if ($email === '') {
        $current = getCurrentUser($pdo);
        if ($current) {
            $email = pfgAuthNormalizeEmail($current['email']);
        }
    }
    if (!pfgAuthIsValidEmail($email)) {
        jsonResponse(['error' => 'Please enter a valid email address.'], 400);
    }

    pfgRequireRateLimit($pdo, 'auth_resend_ip', $clientIp, 10, 3600, 3600);
    pfgRequireRateLimit($pdo, 'auth_resend_email', $email, 3, 3600, 3600);

    $uniform = [
        'success' => true,
        'message' => 'If this email is eligible, a new verification link has been sent.',
    ];

    $stmt = $pdo->prepare('SELECT id, full_name, email, email_verified_at, account_status, is_active, deleted_at FROM users WHERE LOWER(email) = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $eligible = $user
        && empty($user['deleted_at'])
        && !empty($user['is_active'])
        && empty($user['email_verified_at'])
        && strtoupper((string)($user['account_status'] ?? '')) === 'PENDING_VERIFICATION'
        && !pfgAuthHasStaffRole(pfgAuthLoadRoles($pdo, (string)$user['id']));

    if ($eligible) {
        $queueId = null;
        $pdo->beginTransaction();
        try {
            $raw = pfgAuthIssueEmailToken($pdo, (string)$user['id'], 'SIGNUP_VERIFY', $email);
            $queueId = pfgAuthEnqueueEmail($pdo, $email, 'EMAIL_VERIFICATION_RESEND', [
                'fullName'      => (string)($user['full_name'] ?: 'Participant'),
                'expiresInText' => '30 minutes',
            ], ['actionUrl' => pfgAuthVerificationUrl($raw)], (string)$user['id']);
            $pdo->commit();
        } catch (Throwable $inner) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $inner;
        }
        pfgAuthDispatchLocal($pdo, $queueId);
        logAudit([
            'actorUserId'  => (string)$user['id'],
            'action'       => 'EMAIL_VERIFICATION_RESENT',
            'resourceType' => 'user',
            'resourceId'   => (string)$user['id'],
        ]);
    }

    jsonResponse($uniform, 200);
} catch (Throwable $e) {
    error_log('/api/auth/resend-verification error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process request right now.'], 500);
}
