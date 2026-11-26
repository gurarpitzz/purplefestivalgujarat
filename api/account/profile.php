<?php
declare(strict_types=1);

/**
 * GET / PATCH / POST /api/account/profile (php-site/api/account/profile.php)
 * Exact PHP port of src/app/api/account/profile/route.ts.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo jsonEncode(['error' => 'Authentication required.']);
        exit;
    }

    $pdo = getDb();

    if ($method === 'GET') {
        http_response_code(200);
        echo jsonEncode([
            'user' => [
                'id'         => $user['id'],
                'email'      => $user['email'],
                'fullName'   => $user['fullName'] ?? $user['full_name'] ?? '',
                'phone'      => $user['phone'] ?? null,
                'country'    => $user['country'] ?? 'India',
                'mfaEnabled' => (bool)($user['mfaEnabled'] ?? $user['mfa_enabled'] ?? false),
            ],
        ]);
        exit;
    }

    if (!in_array($method, ['PATCH', 'POST', 'PUT'], true)) {
        http_response_code(405);
        echo jsonEncode(['error' => 'Method not allowed. Use PATCH or POST.']);
        exit;
    }

    enforceStaffMfaGate($user);
    requireCsrfToken($user);

    $body = readRequestInput();
    $setClauses = ['updated_at = :updated_at'];
    $params = [
        ':updated_at' => nowSql(),
        ':id'         => $user['id'],
    ];
    $updatedFields = [];

    if (array_key_exists('fullName', $body)) {
        $fullName = trim((string)$body['fullName']);
        if (strlen($fullName) < 2 || strlen($fullName) > 200) {
            http_response_code(400);
            echo jsonEncode(['error' => 'Full name must be between 2 and 200 characters.']);
            exit;
        }
        $setClauses[] = 'full_name = :full_name';
        $params[':full_name'] = $fullName;
        $updatedFields[] = 'fullName';
    }

    if (array_key_exists('phone', $body)) {
        $phone = trim((string)$body['phone']);
        if ($phone !== '' && !preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone)) {
            http_response_code(400);
            echo jsonEncode(['error' => 'Please enter a valid phone number.']);
            exit;
        }
        $setClauses[] = 'phone = :phone';
        $params[':phone'] = $phone !== '' ? $phone : null;
        $updatedFields[] = 'phone';
    }

    if (array_key_exists('country', $body)) {
        $country = trim((string)$body['country']);
        if ($country !== '' && (strlen($country) < 2 || strlen($country) > 100)) {
            http_response_code(400);
            echo jsonEncode(['error' => 'Country must be between 2 and 100 characters.']);
            exit;
        }
        $setClauses[] = 'country = :country';
        $params[':country'] = $country !== '' ? $country : 'India';
        $updatedFields[] = 'country';
    }

    $passwordChanged = false;
    $newPassword = isset($body['newPassword']) ? (string)$body['newPassword'] : '';
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8 || strlen($newPassword) > 128) {
            http_response_code(400);
            echo jsonEncode(['error' => 'New password must be at least 8 characters.']);
            exit;
        }

        $dbUserStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $dbUserStmt->execute([':id' => $user['id']]);
        $dbUser = $dbUserStmt->fetch(PDO::FETCH_ASSOC);

        if ($dbUser && !empty($dbUser['password_hash'])) {
            $currentPassword = (string)($body['currentPassword'] ?? '');
            if ($currentPassword === '') {
                http_response_code(400);
                echo jsonEncode(['error' => 'Please enter your current password to set a new password.']);
                exit;
            }
            $validCurrent = function_exists('verifyPassword')
                ? verifyPassword($currentPassword, (string)$dbUser['password_hash'])
                : password_verify($currentPassword, (string)$dbUser['password_hash']);

            if (!$validCurrent) {
                http_response_code(400);
                echo jsonEncode(['error' => 'Current password is incorrect.']);
                exit;
            }
        }

        $newHash = function_exists('hashPassword')
            ? hashPassword($newPassword)
            : password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $setClauses[] = 'password_hash = :password_hash';
        $params[':password_hash'] = $newHash;
        $passwordChanged = true;
    }

    $updateSql = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $updStmt = $pdo->prepare($updateSql);
    $updStmt->execute($params);

    if ($passwordChanged) {
        revokeAllUserSessions(
            (string)$user['id'],
            !empty($user['sessionId']) ? (string)$user['sessionId'] : null,
            $pdo,
            (string)$user['id'],
            'password_changed'
        );
    }

    $selStmt = $pdo->prepare('SELECT id, email, full_name, phone, country, mfa_enabled FROM users WHERE id = :id LIMIT 1');
    $selStmt->execute([':id' => $user['id']]);
    $updated = $selStmt->fetch(PDO::FETCH_ASSOC);

    if (function_exists('logAudit')) {
        logAudit(
            (string)$user['id'],
            $passwordChanged ? 'PASSWORD_CHANGED' : 'PROFILE_UPDATED',
            'user',
            (string)$user['id'],
            [
                'updatedFields'   => $updatedFields,
                'passwordChanged' => $passwordChanged,
            ]
        );
    }

    http_response_code(200);
    echo jsonEncode([
        'success' => true,
        'user'    => [
            'id'         => (string)$updated['id'],
            'email'      => (string)$updated['email'],
            'fullName'   => (string)$updated['full_name'],
            'phone'      => $updated['phone'],
            'country'    => (string)$updated['country'],
            'mfaEnabled' => (bool)$updated['mfa_enabled'],
        ],
        'message' => $passwordChanged
            ? 'Your password and profile have been updated.'
            : 'Your profile has been updated.',
    ]);
} catch (Throwable $err) {
    error_log('[/api/account/profile] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Unable to update profile right now.']);
}
