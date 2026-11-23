<?php
declare(strict_types=1);

/**
 * POST / PUT /api/auth/password-reset
 * - POST (with email): Generates a 1-hour password reset token, stores its HMAC hash in
 *   `password_reset_tokens` & `auth_accounts`, and queues a notification job.
 * - PUT or POST (with token & newPassword): Validates token hash, updates bcrypt password,
 *   revokes active sessions, and consumes the token.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = getDb();
    $input = readRequestInput();

    $isCompleteReset = ($method === 'PUT')
        || ($method === 'PATCH')
        || (!empty($input['token']) && !empty($input['newPassword']));

    if (!$isCompleteReset && $method === 'POST') {
        $email = strtolower(trim((string)($input['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Please enter a valid email address.'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE LOWER(email) = :email
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && !empty($user['is_active'])) {
            $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $tokenHash = hashToken($rawToken);
            $now = nowSql();
            $expiresAt = nowSql(time() + 3600); // 1 hour

            // Clear old tokens in both auth_accounts and password_reset_tokens
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

            $resetUrl = APP_URL . '/login?resetToken=' . rawurlencode($rawToken);

            queueNotification([
                'templateCode'    => 'PASSWORD_RESET',
                'recipientUserId' => $user['id'],
                'recipientEmail'  => $user['email'],
                'variables'       => [
                    'fullName' => $user['full_name'],
                    'resetUrl' => $resetUrl,
                ],
                'fallbackSubject' => 'Reset your Purple Festival Gujarat 2027 password',
            ], $pdo);

            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'PASSWORD_RESET_REQUESTED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
            ]);
        }

        // Generic response to prevent email enumeration
        jsonResponse([
            'success' => true,
            'message' => 'If an account exists for that email address, password reset instructions have been queued.',
        ], 200);
    }

    if ($isCompleteReset) {
        $rawToken = trim((string)($input['token'] ?? ''));
        $newPassword = (string)($input['newPassword'] ?? $input['new_password'] ?? '');

        if (strlen($rawToken) < 10) {
            jsonResponse(['error' => 'Invalid password reset token.'], 400);
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
            jsonResponse(['error' => 'New password must be at least 8 characters.'], 400);
        }

        $tokenHash = hashToken($rawToken);
        $now = nowSql();

        // Check auth_accounts first, then password_reset_tokens
        $stmt = $pdo->prepare("
            SELECT id, user_id
            FROM auth_accounts
            WHERE provider = 'password_reset'
              AND token_hash = :token_hash
              AND expires_at > :now
            LIMIT 1
        ");
        $stmt->execute([':token_hash' => $tokenHash, ':now' => $now]);
        $resetRecord = $stmt->fetch();

        if (!$resetRecord) {
            $stmt2 = $pdo->prepare("
                SELECT id, user_id
                FROM password_reset_tokens
                WHERE token_hash = :token_hash
                  AND used_at IS NULL
                  AND expires_at > :now
                LIMIT 1
            ");
            $stmt2->execute([':token_hash' => $tokenHash, ':now' => $now]);
            $resetRecord = $stmt2->fetch();
        }

        if (!$resetRecord || empty($resetRecord['user_id'])) {
            jsonResponse(['error' => 'This password reset link is invalid or has expired.'], 400);
        }

        $targetUserId = (string)$resetRecord['user_id'];
        $newHash = hashPassword($newPassword);

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

        // Revoke all existing sessions for security
        $pdo->prepare("UPDATE sessions SET revoked_at = :now WHERE user_id = :uid")
            ->execute([':now' => $now, ':uid' => $targetUserId]);

        // Consume reset token in both tables
        $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :uid AND provider = 'password_reset'")
            ->execute([':uid' => $targetUserId]);
        $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = :uid")
            ->execute([':uid' => $targetUserId]);

        logAudit([
            'actorUserId'  => $targetUserId,
            'action'       => 'PASSWORD_RESET_COMPLETED',
            'resourceType' => 'user',
            'resourceId'   => $targetUserId,
        ]);

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
