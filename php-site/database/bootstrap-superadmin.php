<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Superadmin Account Bootstrapper
 * Equivalent of `src/db/bootstrap-superadmin.ts`.
 *
 * Usage (CLI):
 *   SUPERADMIN_BOOTSTRAP_EMAIL="admin@example.org" SUPERADMIN_BOOTSTRAP_PASSWORD="StrongPassword123!" php database/bootstrap-superadmin.php
 *   or:
 *   php database/bootstrap-superadmin.php "admin@example.org" "StrongPassword123!" "Festival Superadmin" [--force]
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

    $email = strtolower(trim($positional[0] ?? pfgEnv('SUPERADMIN_BOOTSTRAP_EMAIL', '')));
    $password = $positional[1] ?? pfgEnv('SUPERADMIN_BOOTSTRAP_PASSWORD', '');
    $fullName = trim($positional[2] ?? pfgEnv('SUPERADMIN_BOOTSTRAP_NAME', 'Festival Superadmin')) ?: 'Festival Superadmin';

    if ($email === '' || $password === '') {
        fwrite(STDERR, "ERROR: Set SUPERADMIN_BOOTSTRAP_EMAIL and SUPERADMIN_BOOTSTRAP_PASSWORD in your environment (or pass as CLI args) before running bootstrap-superadmin.php.\n");
        exit(1);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "ERROR: SUPERADMIN_BOOTSTRAP_EMAIL is not a valid email address.\n");
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
        $existingStmt = $pdo->prepare("SELECT id FROM user_roles WHERE role_id = :role_id LIMIT 1");
        $existingStmt->execute([':role_id' => $superadminRoleId]);
        if ($existingStmt->fetch()) {
            fwrite(STDOUT, "A SUPERADMIN account already exists. Bootstrap skipped for safety (pass --force to update).\n");
            exit(0);
        }
    }

    $passwordHash = hashPassword($password);
    $now = nowSql();

    $uStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1");
    $uStmt->execute([':email' => $email]);
    $existingUser = $uStmt->fetch();

    if ($existingUser && !empty($existingUser['id'])) {
        $userId = (string)$existingUser['id'];
        $upd = $pdo->prepare("
            UPDATE users
            SET password_hash = :hash,
                full_name = :full_name,
                is_active = 1,
                email_verified_at = :now,
                failed_login_attempts = 0,
                locked_until = NULL,
                updated_at = :now,
                deleted_at = NULL
            WHERE id = :id
        ");
        $upd->execute([
            ':hash'      => $passwordHash,
            ':full_name' => $fullName,
            ':now'       => $now,
            ':id'        => $userId,
        ]);
    } else {
        $userId = uuid4();
        $ins = $pdo->prepare("
            INSERT INTO users
            (id, email, email_verified_at, password_hash, full_name, country, is_active, mfa_enabled, failed_login_attempts, created_at, updated_at)
            VALUES (:id, :email, :now, :hash, :full_name, 'India', 1, 0, 0, :now, :now)
        ");
        $ins->execute([
            ':id'        => $userId,
            ':email'     => $email,
            ':now'       => $now,
            ':hash'      => $passwordHash,
            ':full_name' => $fullName,
        ]);
    }

    $urIns = $pdo->prepare("
        INSERT IGNORE INTO user_roles (id, user_id, role_id, assigned_at)
        VALUES (:id, :user_id, :role_id, :now)
    ");
    $urIns->execute([
        ':id'      => uuid4(),
        ':user_id' => $userId,
        ':role_id' => $superadminRoleId,
        ':now'     => $now,
    ]);

    logAudit([
        'actorUserId'  => $userId,
        'action'       => 'SUPERADMIN_BOOTSTRAPPED',
        'resourceType' => 'users',
        'resourceId'   => $userId,
        'metadata'     => ['email' => $email],
    ]);

    fwrite(STDOUT, "Superadmin initialized successfully for {$email}.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Superadmin bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}
