<?php
declare(strict_types=1);

/**
 * POST /api/auth/signup
 * Creates a new PARTICIPANT account and signs the user in immediately.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

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
    $input = readRequestInput();

    $fullName = trim((string)($input['fullName'] ?? $input['full_name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $phone = trim((string)($input['phone'] ?? ''));
    $country = trim((string)($input['country'] ?? 'India')) ?: 'India';
    $password = (string)($input['password'] ?? '');

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 200) {
        jsonResponse(['error' => 'Please enter your full name (at least 2 characters).'], 400);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Please enter a valid email address.'], 400);
    }
    if (strlen($password) < 8 || strlen($password) > 128) {
        jsonResponse(['error' => 'Password must be at least 8 characters.'], 400);
    }

    $chk = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1");
    $chk->execute([':email' => $email]);
    if ($chk->fetch()) {
        jsonResponse([
            'error' => 'An account with this email address already exists. Please sign in instead.',
        ], 409);
    }

    $userId = uuid4();
    $passwordHash = hashPassword($password);
    $now = nowSql();

    $ins = $pdo->prepare("
        INSERT INTO users
        (id, email, password_hash, full_name, phone, country, is_active, mfa_enabled, failed_login_attempts, last_login_at, created_at, updated_at)
        VALUES (:id, :email, :password_hash, :full_name, :phone, :country, 1, 0, 0, :now, :now, :now)
    ");
    $ins->execute([
        ':id'            => $userId,
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        ':full_name'     => $fullName,
        ':phone'         => $phone !== '' ? $phone : null,
        ':country'       => $country,
        ':now'           => $now,
    ]);

    // Ensure PARTICIPANT role exists and assign it
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'PARTICIPANT' LIMIT 1");
    $roleStmt->execute();
    $roleRow = $roleStmt->fetch();
    $participantRoleId = $roleRow['id'] ?? null;

    if (!$participantRoleId) {
        $participantRoleId = '10000000-0000-4000-8000-000000000004';
        $rIns = $pdo->prepare("INSERT IGNORE INTO roles (id, code, name, description, created_at) VALUES (:id, 'PARTICIPANT', 'Participant', 'Attendee, delegate, volunteer, or SLI professional account.', :now)");
        $rIns->execute([':id' => $participantRoleId, ':now' => $now]);
    }

    $urIns = $pdo->prepare("INSERT IGNORE INTO user_roles (id, user_id, role_id, assigned_at) VALUES (:id, :user_id, :role_id, :now)");
    $urIns->execute([
        ':id'      => uuid4(),
        ':user_id' => $userId,
        ':role_id' => $participantRoleId,
        ':now'     => $now,
    ]);

    createSession($userId, ['mfaVerified' => false], $pdo);

    $rp = getUserRolesAndPermissions($userId, $pdo);

    logAudit([
        'actorUserId'  => $userId,
        'action'       => 'ACCOUNT_SIGNUP',
        'resourceType' => 'user',
        'resourceId'   => $userId,
        'metadata'     => ['email' => $email],
    ]);

    jsonResponse([
        'user' => [
            'id'          => $userId,
            'email'       => $email,
            'fullName'    => $fullName,
            'phone'       => $phone !== '' ? $phone : null,
            'country'     => $country,
            'roles'       => $rp['roles'],
            'permissions' => $rp['permissions'],
            'mfaEnabled'  => false,
        ],
        'redirectTo' => '/dashboard',
    ], 201);
} catch (Throwable $e) {
    error_log('POST /api/auth/signup error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to create account right now. Please try again.'], 500);
}
