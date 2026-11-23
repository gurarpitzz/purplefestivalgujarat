<?php
declare(strict_types=1);

/**
 * GET / POST /api/auth/mfa
 * Initializes or verifies RFC 6238 TOTP Multi-Factor Authentication setup / disablement.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = getDb();
    $user = getCurrentUser($pdo);
    if (!$user) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }

    if ($method === 'GET') {
        $mfa = generateMfaSecret((string)$user['email']);
        $secret = (string)$mfa['secret'];
        $otpauthUrl = (string)$mfa['otpauthUrl'];

        // Clear previous pending MFA setup tokens for this user
        $del = $pdo->prepare("DELETE FROM auth_accounts WHERE user_id = :user_id AND provider = 'mfa_setup'");
        $del->execute([':user_id' => $user['id']]);

        $ins = $pdo->prepare("
            INSERT INTO auth_accounts
            (id, user_id, provider, provider_account_id, token_hash, expires_at, created_at)
            VALUES (:id, :user_id, 'mfa_setup', :email, :secret, :expires_at, :now)
        ");
        $ins->execute([
            ':id'         => uuid4(),
            ':user_id'    => $user['id'],
            ':email'      => $user['email'],
            ':secret'     => $secret,
            ':expires_at' => nowSql(time() + 15 * 60),
            ':now'        => nowSql(),
        ]);

        jsonResponse([
            'mfaEnabled' => (bool)$user['mfaEnabled'],
            'secret'     => $secret,
            'otpauthUrl' => $otpauthUrl,
            'qrDataUrl'  => null, // Rendered client-side via qrcode-gen.js from otpauthUrl
        ], 200);
    }

    if ($method === 'POST') {
        $input = readRequestInput();
        $rawCode = trim((string)($input['code'] ?? ''));
        $action = strtolower(trim((string)($input['action'] ?? 'enable'))) ?: 'enable';

        $cleanedCode = preg_replace('/\s+/', '', $rawCode) ?? '';
        if (strlen($cleanedCode) < 6 || strlen($cleanedCode) > 10) {
            jsonResponse(['error' => 'Please enter the 6-digit authenticator code.'], 400);
        }

        if ($action === 'disable') {
            $uStmt = $pdo->prepare("SELECT mfa_enabled, mfa_secret_encrypted, mfa_secret FROM users WHERE id = :id LIMIT 1");
            $uStmt->execute([':id' => $user['id']]);
            $dbUser = $uStmt->fetch();

            $activeSecret = !empty($dbUser['mfa_secret_encrypted'])
                ? (string)$dbUser['mfa_secret_encrypted']
                : (!empty($dbUser['mfa_secret']) ? (string)$dbUser['mfa_secret'] : '');

            if (empty($dbUser['mfa_enabled']) || $activeSecret === '') {
                jsonResponse(['error' => 'MFA is not currently enabled.'], 400);
            }

            if (!verifyMfaToken($activeSecret, $cleanedCode)) {
                jsonResponse(['error' => 'Invalid authenticator code. Could not disable MFA.'], 400);
            }

            $upd = $pdo->prepare("
                UPDATE users
                SET mfa_enabled = 0,
                    mfa_secret_encrypted = NULL,
                    mfa_secret = NULL,
                    updated_at = :now
                WHERE id = :id
            ");
            $upd->execute([':now' => nowSql(), ':id' => $user['id']]);

            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'MFA_DISABLED',
                'resourceType' => 'user',
                'resourceId'   => $user['id'],
            ]);

            jsonResponse(['success' => true, 'mfaEnabled' => false], 200);
        }

        // Enable MFA
        $pStmt = $pdo->prepare("
            SELECT *
            FROM auth_accounts
            WHERE user_id = :user_id
              AND provider = 'mfa_setup'
              AND expires_at > :now
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $pStmt->execute([
            ':user_id' => $user['id'],
            ':now'     => nowSql(),
        ]);
        $pending = $pStmt->fetch();

        if (!$pending || empty($pending['token_hash'])) {
            jsonResponse(['error' => 'MFA setup session expired. Please generate a new QR code.'], 400);
        }

        $pendingSecret = (string)$pending['token_hash'];
        if (!verifyMfaToken($pendingSecret, $cleanedCode)) {
            jsonResponse(['error' => 'Invalid verification code. Please check your authenticator app.'], 400);
        }

        $now = nowSql();
        $upd = $pdo->prepare("
            UPDATE users
            SET mfa_enabled = 1,
                mfa_secret_encrypted = :secret,
                mfa_secret = :secret,
                updated_at = :now
            WHERE id = :id
        ");
        $upd->execute([
            ':secret' => $pendingSecret,
            ':now'    => $now,
            ':id'     => $user['id'],
        ]);

        if (!empty($user['sessionId'])) {
            $sUpd = $pdo->prepare("UPDATE sessions SET mfa_verified = 1 WHERE id = :sid");
            $sUpd->execute([':sid' => $user['sessionId']]);
        }

        $del = $pdo->prepare("DELETE FROM auth_accounts WHERE id = :id");
        $del->execute([':id' => $pending['id']]);

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'MFA_ENABLED',
            'resourceType' => 'user',
            'resourceId'   => $user['id'],
        ]);

        jsonResponse([
            'success'    => true,
            'mfaEnabled' => true,
            'message'    => 'Multi-factor authentication is now active on your account.',
        ], 200);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/auth/mfa error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process MFA request.'], 500);
}
