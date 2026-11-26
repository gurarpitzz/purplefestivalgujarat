<?php
declare(strict_types=1);

/**
 * POST /api/auth/verify-email   — consumes a single-use e-mail token (SIGNUP_VERIFY | EMAIL_CHANGE)
 * GET  /api/auth/verify-email   — NON-consuming validity peek (?token=...) for the confirmation UI
 *
 * - Rate-limited; tokens are looked up by HMAC-SHA256 hash and consumed atomically (`used_at`).
 * - Invalid / expired / already-used tokens all yield the same uniform 400.
 */

require_once __DIR__ . '/_participant.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!in_array($method, ['POST', 'GET'], true)) {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

const PFG_VERIFY_INVALID_MESSAGE = 'This verification link is invalid, has expired, or has already been used. Please request a new one.';

try {
    $pdo = getDb();
    $clientIp = getClientIp() ?: 'unknown';
    pfgRequireRateLimit($pdo, 'auth_verify_email_ip', $clientIp, 30, 900, 900);

    if ($method === 'GET') {
        $rawToken = trim((string)($_GET['token'] ?? ''));
        $valid = false;
        $purpose = null;
        if (strlen($rawToken) >= 20 && strlen($rawToken) <= 200) {
            $peek = $pdo->prepare('SELECT purpose FROM email_verification_tokens WHERE token_hash = :h AND used_at IS NULL AND expires_at > :now LIMIT 1');
            $peek->execute([':h' => hashToken($rawToken), ':now' => nowSql()]);
            $row = $peek->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $valid = true;
                $purpose = (string)$row['purpose'];
            }
        }
        jsonResponse(['valid' => $valid, 'purpose' => $purpose], 200);
    }

    $input = readRequestInput();
    $rawToken = trim((string)($input['token'] ?? ''));
    if (strlen($rawToken) < 20 || strlen($rawToken) > 200) {
        jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
    }

    $tokenHash = hashToken($rawToken);
    $now = nowSql();

    $sel = $pdo->prepare('SELECT id, user_id, purpose, target_email FROM email_verification_tokens WHERE token_hash = :h AND used_at IS NULL AND expires_at > :now LIMIT 1');
    $sel->execute([':h' => $tokenHash, ':now' => $now]);
    $tok = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$tok) {
        jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
    }

    $welcomeQueueId = null;
    $alertQueueId = null;
    $alertTo = null;
    $alertUserId = null;
    $alertName = '';
    $result = null;
    $conflict = false;

    $pdo->beginTransaction();
    try {
        // Atomic single-use consumption: exactly one concurrent request can win this UPDATE.
        $claim = $pdo->prepare('UPDATE email_verification_tokens SET used_at = :now WHERE id = :id AND used_at IS NULL AND expires_at > :now2');
        $claim->execute([':now' => $now, ':id' => $tok['id'], ':now2' => $now]);
        if ($claim->rowCount() !== 1) {
            $pdo->rollBack();
            jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
        }

        $uStmt = $pdo->prepare('SELECT id, email, pending_email, full_name, email_verified_at, account_status, is_active, deleted_at FROM users WHERE id = :id LIMIT 1');
        $uStmt->execute([':id' => $tok['user_id']]);
        $u = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || !empty($u['deleted_at']) || empty($u['is_active'])) {
            $pdo->commit();
            jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
        }

        $purpose = (string)$tok['purpose'];
        $target = pfgAuthNormalizeEmail($tok['target_email']);

        if ($purpose === 'SIGNUP_VERIFY') {
            if (pfgAuthNormalizeEmail($u['email']) !== $target) {
                $pdo->commit();
                jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
            }
            $status = strtoupper((string)($u['account_status'] ?? 'ACTIVE'));
            $newStatus = $status === 'PENDING_VERIFICATION' ? 'ACTIVE' : $status;
            $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, :now), account_status = :st, updated_at = :now2 WHERE id = :id')
                ->execute([':now' => $now, ':st' => $newStatus, ':now2' => $now, ':id' => $u['id']]);

            $welcomeQueueId = pfgAuthEnqueueEmail($pdo, (string)$u['email'], 'ACCOUNT_WELCOME_ACTIVATED', [
                'fullName'     => (string)($u['full_name'] ?: 'Participant'),
                'dashboardUrl' => APP_URL . '/dashboard',
            ], [], (string)$u['id']);

            $result = [
                'success'    => true,
                'purpose'    => 'SIGNUP_VERIFY',
                'message'    => 'Your email address is verified and your account is now active. You can sign in.',
                'redirectTo' => '/login?verified=1',
            ];
            $auditAction = 'EMAIL_VERIFIED';
            $auditMeta = ['purpose' => 'SIGNUP_VERIFY'];
        } elseif ($purpose === 'EMAIL_CHANGE') {
            $pending = pfgAuthNormalizeEmail($u['pending_email'] ?? '');
            if ($pending === '' || $pending !== $target) {
                // Superseded by a newer change request.
                $pdo->commit();
                jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
            }
            $dup = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = :e AND id <> :id LIMIT 1');
            $dup->execute([':e' => $target, ':id' => $u['id']]);
            if ($dup->fetchColumn()) {
                $pdo->prepare('UPDATE users SET pending_email = NULL, updated_at = :now WHERE id = :id')
                    ->execute([':now' => $now, ':id' => $u['id']]);
                $conflict = true;
            } else {
                $oldEmail = (string)$u['email'];
                $pdo->prepare('UPDATE users SET email = :e, pending_email = NULL, email_verified_at = :now, updated_at = :now2 WHERE id = :id')
                    ->execute([':e' => $target, ':now' => $now, ':now2' => $now, ':id' => $u['id']]);
                $alertTo = $oldEmail;
                $alertUserId = (string)$u['id'];
                $alertName = (string)$u['full_name'];
                $result = [
                    'success'    => true,
                    'purpose'    => 'EMAIL_CHANGE',
                    'message'    => 'Your new email address is confirmed. Please sign in again with it.',
                    'redirectTo' => '/login',
                ];
                $auditAction = 'EMAIL_CHANGED';
                $auditMeta = ['purpose' => 'EMAIL_CHANGE'];
            }
        } else {
            $pdo->commit();
            jsonResponse(['error' => PFG_VERIFY_INVALID_MESSAGE], 400);
        }

        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (pfgAuthIsDuplicateKey($inner) && ($tok['purpose'] ?? '') === 'EMAIL_CHANGE') {
            jsonResponse(['error' => 'This email address is no longer available.'], 409);
        }
        throw $inner;
    }

    if ($conflict) {
        jsonResponse(['error' => 'This email address is no longer available.'], 409);
    }

    if ($welcomeQueueId !== null) {
        pfgAuthDispatchLocal($pdo, $welcomeQueueId);
    }

    if (($result['purpose'] ?? '') === 'EMAIL_CHANGE') {
        // Keep the caller's own session (if it belongs to this user); revoke every other session.
        $current = getCurrentUser($pdo);
        $keep = ($current && $current['id'] === (string)$tok['user_id']) ? (string)$current['sessionId'] : null;
        revokeAllUserSessions((string)$tok['user_id'], $keep, $pdo, (string)$tok['user_id'], 'email_changed');
        if ($alertTo !== null) {
            pfgAuthSendSecurityAlert($pdo, $alertTo, (string)$alertUserId, $alertName, 'The email address on your account was changed');
        }
    }

    logAudit([
        'actorUserId'  => (string)$tok['user_id'],
        'action'       => $auditAction ?? 'EMAIL_VERIFIED',
        'resourceType' => 'user',
        'resourceId'   => (string)$tok['user_id'],
        'metadata'     => $auditMeta ?? [],
    ]);

    jsonResponse($result ?? ['success' => true], 200);
} catch (Throwable $e) {
    error_log('/api/auth/verify-email error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to verify your email right now. Please try again.'], 500);
}
