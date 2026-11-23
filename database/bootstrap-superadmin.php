<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Superadmin Account Bootstrapper
 * Supports username-based login (`SUPERADMIN_BOOTSTRAP_USERNAME` or CLI args).
 *
 * Usage (CLI):
 *   SUPERADMIN_BOOTSTRAP_USERNAME="superadmin" SUPERADMIN_BOOTSTRAP_PASSWORD="StrongPassword123!" php database/bootstrap-superadmin.php
 *   or:
 *   php database/bootstrap-superadmin.php "superadmin" "StrongPassword123!" "Festival Superadmin" [--force]
 */

if (PHP_SAPI !== 'cli' && !defined('PFG_ALLOW_WEB_BOOTSTRAP')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Direct web execution of bootstrap-superadmin.php is forbidden. Run via CLI.']);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/auth.php';

try {
    $pdo = getDb();

    $cliArgs = isset($argv) && is_array($argv) ? array_slice($argv, 1) : [];
    $forceUpdate = in_array('--force', $cliArgs, true);
    $positional = array_values(array_filter($cliArgs, static fn($a) => strpos((string)$a, '--') !== 0));

    $rawIdent = trim((string)($positional[0] ?? pfgEnv('SUPERADMIN_BOOTSTRAP_USERNAME', pfgEnv('SUPERADMIN_BOOTSTRAP_EMAIL', 'superadmin'))));
    $explicitUsername = strtolower(trim(pfgEnv('SUPERADMIN_BOOTSTRAP_USERNAME', '')));
    $explicitEmail = strtolower(trim(pfgEnv('SUPERADMIN_BOOTSTRAP_EMAIL', '')));
    $password = (string)($positional[1] ?? pfgEnv('SUPERADMIN_BOOTSTRAP_PASSWORD', ''));
    $fullName = trim((string)($positional[2] ?? pfgEnv('SUPERADMIN_BOOTSTRAP_NAME', 'Festival Superadmin'))) ?: 'Festival Superadmin';

    if (filter_var($rawIdent, FILTER_VALIDATE_EMAIL)) {
        $email = strtolower($rawIdent);
        $prefix = strtolower((string)explode('@', $email)[0]);
        $username = $explicitUsername !== '' ? $explicitUsername : ($prefix !== '' ? $prefix : 'superadmin');
    } else {
        $username = $explicitUsername !== '' ? $explicitUsername : strtolower($rawIdent !== '' ? $rawIdent : 'superadmin');
        $email = filter_var($explicitEmail, FILTER_VALIDATE_EMAIL)
            ? $explicitEmail
            : ($username . '@staff.purplefestivalgujarat.com');
    }

    $username = preg_replace('/[^a-z0-9._-]/', '', $username) ?: 'superadmin';

    if ($password === '') {
        fwrite(STDERR, "ERROR: Set SUPERADMIN_BOOTSTRAP_PASSWORD (min 12 characters) before running bootstrap-superadmin.php.\n");
        exit(1);
    }

    if (strlen($password) < 12) {
        fwrite(STDERR, "ERROR: SUPERADMIN_BOOTSTRAP_PASSWORD must be at least 12 characters.\n");
        exit(1);
    }

    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'SUPERADMIN' LIMIT 1");
    $roleStmt->execute();
    $superadminRole = $roleStmt->fetch();

    if (!$superadminRole || empty($superadminRole['id'])) {
        fwrite(STDERR, "ERROR: Roles not seeded yet.\n");
        exit(1);
    }

    $superadminRoleId = (string)$superadminRole['id'];

    if (!$forceUpdate) {
        $existingStmt = $pdo->prepare("SELECT id, user_id FROM user_roles WHERE role_id = :role_id LIMIT 1");
        $existingStmt->execute([':role_id' => $superadminRoleId]);
        $existingRoleRow = $existingStmt->fetch();
        if ($existingRoleRow && !empty($existingRoleRow['user_id'])) {
            $uBackfill = $pdo->prepare("UPDATE users SET username = :username WHERE id = :id AND (username IS NULL OR username = '')");
            $uBackfill->execute([
                ':username' => $username,
                ':id'       => (string)$existingRoleRow['user_id'],
            ]);
            fwrite(STDOUT, "A SUPERADMIN account already exists. Bootstrap skipped for safety (pass --force to update).\n");
            exit(0);
        }
    }

    $passwordHash = hashPassword($password);
    $now = nowSql();

    $uStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = :uname OR LOWER(email) = :email LIMIT 1");
    $uStmt->execute([
        ':uname' => $username,
        ':email' => $email,
    ]);
    $existingUser = $uStmt->fetch();

    if ($existingUser && !empty($existingUser['id'])) {
        $userId = (string)$existingUser['id'];
        $upd = $pdo->prepare("
            UPDATE users
            SET username = :username,
                email = :email,
                password_hash = :hash,
                full_name = :full_name,
                is_active = 1,
                email_verified_at = :verified_at,
                failed_login_attempts = 0,
                locked_until = NULL,
                updated_at = :updated_at,
                deleted_at = NULL
            WHERE id = :id
        ");
        $upd->execute([
            ':username'    => $username,
            ':email'       => $email,
            ':hash'        => $passwordHash,
            ':full_name'   => $fullName,
            ':verified_at' => $now,
            ':updated_at'  => $now,
            ':id'          => $userId,
        ]);
    } else {
        $userId = uuid4();
        $ins = $pdo->prepare("
            INSERT INTO users
            (id, username, email, email_verified_at, password_hash, full_name, country, is_active, mfa_enabled, failed_login_attempts, created_at, updated_at)
            VALUES (:id, :username, :email, :verified_at, :hash, :full_name, 'India', 1, 0, 0, :created_at, :updated_at)
        ");
        $ins->execute([
            ':id'          => $userId,
            ':username'    => $username,
            ':email'       => $email,
            ':verified_at' => $now,
            ':hash'        => $passwordHash,
            ':full_name'   => $fullName,
            ':created_at'  => $now,
            ':updated_at'  => $now,
        ]);
    }

    $urIns = $pdo->prepare("
        INSERT IGNORE INTO user_roles (id, user_id, role_id, assigned_at)
        VALUES (:id, :user_id, :role_id, :assigned_at)
    ");
    $urIns->execute([
        ':id'          => uuid4(),
        ':user_id'     => $userId,
        ':role_id'     => $superadminRoleId,
        ':assigned_at' => $now,
    ]);

    logAudit([
        'actorUserId'  => $userId,
        'action'       => 'SUPERADMIN_BOOTSTRAPPED',
        'resourceType' => 'users',
        'resourceId'   => $userId,
        'metadata'     => ['username' => $username, 'email' => $email],
    ]);

    fwrite(STDOUT, "Superadmin initialized successfully (username: {$username}).\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Superadmin bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}
