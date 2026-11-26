<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 5 Migration 007
 * Participant Authentication, Email Verification Tokens, Legacy Registration Claim Audit
 * & Additive Participant Profile Columns on `users`.
 *
 * 100% portable across MySQL 8.0 and MariaDB 10.4+ (information_schema guarded, idempotent).
 * Foreign keys on participant / audit records are strictly ON DELETE RESTRICT (zero cascades).
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsAdded'  => [],
        'indexesAdded'  => [],
        'tablesCreated' => [],
        'fksAdded'      => [],
    ];

    // 1. Additive nullable profile columns on `users` (never destructive).
    $userCols = [
        'city_district'                  => 'VARCHAR(120) DEFAULT NULL',
        'state'                          => 'VARCHAR(100) DEFAULT NULL',
        'preferred_language'             => 'VARCHAR(32) DEFAULT NULL',
        'pending_email'                  => 'VARCHAR(255) DEFAULT NULL',
        'account_consent_at'             => 'DATETIME DEFAULT NULL',
        'emergency_contact_name'         => 'VARCHAR(150) DEFAULT NULL',
        'emergency_contact_relationship' => 'VARCHAR(80) DEFAULT NULL',
        'emergency_contact_phone'        => 'VARCHAR(40) DEFAULT NULL',
    ];
    foreach ($userCols as $col => $def) {
        if (pfgDbEnsureColumn($pdo, 'users', $col, $def)) {
            $changes['columnsAdded'][] = 'users.' . $col;
        }
    }

    // 2. Email verification / email-change tokens (only the SHA-256 token hash is stored).
    if (!pfgDbTableExists($pdo, 'email_verification_tokens')) {
        $pdo->exec("
            CREATE TABLE `email_verification_tokens` (
              `id` CHAR(36) NOT NULL,
              `user_id` CHAR(36) NOT NULL,
              `purpose` VARCHAR(32) NOT NULL DEFAULT 'SIGNUP_VERIFY',
              `target_email` VARCHAR(255) NOT NULL,
              `token_hash` VARCHAR(191) NOT NULL,
              `expires_at` DATETIME NOT NULL,
              `used_at` DATETIME DEFAULT NULL,
              `created_ip` VARCHAR(64) DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_evt_token_hash` (`token_hash`),
              KEY `idx_evt_user_purpose` (`user_id`, `purpose`, `used_at`),
              CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'email_verification_tokens';
        $changes['fksAdded'][] = 'fk_evt_user';
    }

    // 3. Legacy (pre-Phase-5) registration claim audit trail — one claim per registration, ever.
    if (!pfgDbTableExists($pdo, 'legacy_registration_claims')) {
        $pdo->exec("
            CREATE TABLE `legacy_registration_claims` (
              `id` CHAR(36) NOT NULL,
              `user_id` CHAR(36) NOT NULL,
              `registration_id` CHAR(36) NOT NULL,
              `claimed_email` VARCHAR(255) NOT NULL,
              `previous_user_id` CHAR(36) DEFAULT NULL,
              `ip_address` VARCHAR(64) DEFAULT NULL,
              `claimed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_lrc_reg` (`registration_id`),
              KEY `idx_lrc_user` (`user_id`),
              CONSTRAINT `fk_lrc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
              CONSTRAINT `fk_lrc_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'legacy_registration_claims';
        $changes['fksAdded'][] = 'fk_lrc_user';
        $changes['fksAdded'][] = 'fk_lrc_registration';
    }

    // 4. Resilience: if either table pre-existed without its FKs, add them non-destructively.
    foreach ([
        ['email_verification_tokens', 'fk_evt_user', 'user_id', 'users'],
        ['legacy_registration_claims', 'fk_lrc_user', 'user_id', 'users'],
        ['legacy_registration_claims', 'fk_lrc_registration', 'registration_id', 'registrations'],
    ] as [$child, $name, $col, $parent]) {
        $res = pfgDbEnsureForeignKey($pdo, $child, $name, $col, $parent, 'id', 'RESTRICT', 'RESTRICT');
        if ($res['status'] === 'ADDED') {
            $changes['fksAdded'][] = $name;
        }
    }

    return $changes;
};
