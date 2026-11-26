<?php
declare(strict_types=1);

/**
 * POST / PUT /api/auth/password-reset
 * Phase 2 Hardened:
 * - Persistent DB-backed rate limiting (IP + email)
 * - Zero plaintext resetUrl or rawToken stored in notification_queue.payload
 * - Delivers reset email via sendFestivalEmail() (safe local file sink in dev/testing)
 * - Revokes all active sessions via revokeAllUserSessions() upon password completion
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/mailer.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = getDb();
    $input = readRequestInput();
    $clientIp = getClientIp() ?: 'unknown';

    $isCompleteReset = ($method === 'PUT')
        || ($method === 'PATCH')
        || (!empty($input['token']) && !empty($input['newPassword']));

    if (!$isCompleteReset && $method === 'POST') {
        pfgRequireRateLimit($pdo, 'auth_pwreset_ip', $clientIp, 10, 900, 900);

        $email = strtolower(trim((string)($input['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Please enter a valid email address.'], 400);
        }

        pfgRequireRateLimit($pdo, 'auth_pwreset_email', $email, 5, 900, 900);

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE LOWER(email) = :email
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['is_active'])) {
            $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $tokenHash = hashToken($rawToken);
            $now = nowSql();
            $expiresAt = nowSql(time() + 3600); // 1 hour

            $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider = 'password_reset'")
                ->execute([':uid' => $user['id']]);
            $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = :uid")
                ->execute([':uid' => $user['id']]);

            $pdo->prepare("
                INSERT INTO auth_accounts (id, user_id, provider, provider_account_id, token_hash, expires_at, created_at)
                VALUES (:id, :uid, 'password_reset', :email, :token_hash, :expires_at, :now)
            ")->execute([
                ':id'         => uuid4(),
                ':uid'        => $user['id'],
                ':email'      => $user['email'],
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
                ':now'        => $now,
            ]);

            $pdo->prepare("
                INSERT INTO password_reset_tokens (id, user_id, email, token_hash, expires_at, created_at)
                VALUES (:id, :uid, :email, :token_hash, :expires_at, :now)
            ")->execute([
                ':id'         => uuid4(),
                ':uid'        => $user['id'],
                ':email'      => $user['email'],
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
                ':now'        => $now,
            ]);

            $resetUrl = APP_URL . '/reset-password?token=' . rawurlencode($rawToken);

            // Phase 4L Secure Ephemeral Delivery: actionUrl encrypted in ephemeral_secret_enc & wiped immediately upon handoff;
            // NEVER persisted in notification_queue.payload or notification_delivery_logs.
            sendFestivalEmail($pdo, [
                'to'              => (string)$user['email'],
                'templateCode'    => 'PASSWORD_RECOVERY',
                'variables'       => [
                    'fullName'      => (string)($user['full_name'] ?: 'Participant'),
                    'expiresInText' => '1 hour',
                ],
                'ephemeralVars'   => [
                    'actionUrl' => $resetUrl,
                ],
                'recipientUserId' => (string)$user['id'],
                'entityType'      => 'user',
                'entityId'        => (string)$user['id'],
                'priority'        => 1,
            ]);

            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'PASSWORD_RESET_REQUESTED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
            ]);
        }

        // Uniform response to prevent email enumeration
        jsonResponse([
            'success' => true,
            'message' => 'If an account exists for that email address, password reset instructions have been processed.',
        ], 200);
    }

    if ($isCompleteReset) {
        pfgRequireRateLimit($pdo, 'auth_pwreset_complete_ip', $clientIp, 10, 900, 900);

        $rawToken = trim((string)($input['token'] ?? ''));
        $newPassword = (string)($input['newPassword'] ?? $input['new_password'] ?? '');

        if (strlen($rawToken) < 10) {
            jsonResponse(['error' => 'Invalid password reset token.'], 400);
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
            jsonResponse(['error' => 'New password must be between 8 and 128 characters.'], 400);
        }
        if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            jsonResponse(['error' => 'New password must include at least one letter and one number.'], 400);
        }

        $tokenHash = hashToken($rawToken);
        $now = nowSql();
        $newHash = hashPassword($newPassword);

        // Atomic token lookup + single-use consumption BEFORE updating users.password_hash (MED-03)
        $targetUserId = null;
        $pdo->beginTransaction();
        try {
            $stmtPrt = $pdo->prepare("
                SELECT id, user_id
                FROM password_reset_tokens
                WHERE token_hash = :token_hash
                  AND used_at IS NULL
                  AND expires_at > :now
                LIMIT 1
            ");
            $stmtPrt->execute([':token_hash' => $tokenHash, ':now' => $now]);
            $prtRecord = $stmtPrt->fetch(PDO::FETCH_ASSOC);

            if ($prtRecord && !empty($prtRecord['user_id'])) {
                $consumePrt = $pdo->prepare("
                    UPDATE password_reset_tokens
                    SET used_at = :now
                    WHERE id = :id
                      AND used_at IS NULL
                      AND expires_at > :now2
                ");
                $consumePrt->execute([
                    ':now'  => $now,
                    ':id'   => $prtRecord['id'],
                    ':now2' => $now,
                ]);
                if ($consumePrt->rowCount() !== 1) {
                    $pdo->rollBack();
                    jsonResponse(['error' => 'This password reset link is invalid or has expired.'], 400);
                }
                $targetUserId = (string)$prtRecord['user_id'];
            } else {
                $stmtAa = $pdo->prepare("
                    SELECT id, user_id
                    FROM auth_accounts
                    WHERE provider = 'password_reset'
                      AND token_hash = :token_hash
                      AND expires_at > :now
                    LIMIT 1
                ");
                $stmtAa->execute([':token_hash' => $tokenHash, ':now' => $now]);
                $aaRecord = $stmtAa->fetch(PDO::FETCH_ASSOC);

                if (!$aaRecord || empty($aaRecord['user_id'])) {
                    $pdo->rollBack();
                    jsonResponse(['error' => 'This password reset link is invalid or has expired.'], 400);
                }

                $consumeAa = $pdo->prepare("
                    DELETE FROM auth_accounts
                    WHERE id = :id
                      AND provider = 'password_reset'
                      AND expires_at > :now
                ");
                $consumeAa->execute([
                    ':id'  => $aaRecord['id'],
                    ':now' => $now,
                ]);
                if ($consumeAa->rowCount() !== 1) {
                    $pdo->rollBack();
                    jsonResponse(['error' => 'This password reset link is invalid or has expired.'], 400);
                }
                $targetUserId = (string)$aaRecord['user_id'];
            }

            $pdo->prepare("
                UPDATE users
                SET password_hash = :hash,
                    failed_login_attempts = 0,
                    locked_until = NULL,
                    updated_at = :now
                WHERE id = :uid
            ")->execute([
                ':hash' => $newHash,
                ':now'  => $now,
                ':uid'  => $targetUserId,
            ]);

            // Invalidate any remaining reset tokens for this user across both tables
            $pdo->prepare("UPDATE password_reset_tokens SET used_at = :now WHERE user_id = :uid AND used_at IS NULL")
                ->execute([':now' => $now, ':uid' => $targetUserId]);
            $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider = 'password_reset'")
                ->execute([':uid' => $targetUserId]);

            $pdo->commit();
        } catch (Throwable $txErr) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txErr;
        }

        // Revoke all active sessions for security
        revokeAllUserSessions($targetUserId, null, $pdo, $targetUserId, 'password_reset_completed');

        logAudit([
            'actorUserId'  => $targetUserId,
            'action'       => 'PASSWORD_RESET_COMPLETED',
            'resourceType' => 'user',
            'resourceId'   => $targetUserId,
        ]);

        // Send password reset confirmation email (Phase 4K Template #5)
        try {
            $uStmt = $pdo->prepare('SELECT email, full_name FROM users WHERE id = :uid LIMIT 1');
            $uStmt->execute([':uid' => $targetUserId]);
            $uInfo = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uInfo && !empty($uInfo['email'])) {
                sendFestivalEmail($pdo, [
                    'to'              => (string)$uInfo['email'],
                    'templateCode'    => 'PASSWORD_RESET_CONFIRMATION',
                    'variables'       => [
                        'fullName'     => (string)($uInfo['full_name'] ?: 'Participant'),
                        'changedAtIst' => formatIstTimestamp($now),
                    ],
                    'recipientUserId' => $targetUserId,
                    'entityType'      => 'user',
                    'entityId'        => $targetUserId,
                    'priority'        => 2,
                ]);
            }
        } catch (Throwable $mailErr) {
            error_log('/api/auth/password-reset confirmation email warning: ' . $mailErr->getMessage());
        }

        jsonResponse([
            'success' => true,
            'message' => 'Your password has been updated. Please sign in with your new password.',
        ], 200);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/auth/password-reset error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process password reset request.'], 500);
}
