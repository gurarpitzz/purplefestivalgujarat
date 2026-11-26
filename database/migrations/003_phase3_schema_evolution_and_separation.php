<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 3 Migration 003
 * Schema Evolution, Architectural Concept Separation, Manual Attendance Foundation & Notification Queue Upgrade.
 *
 * Uses information_schema checks (pfgDbEnsureColumn / pfgDbEnsureIndex) for 100% compatibility
 * with BOTH MySQL 8.0 (which rejects ADD COLUMN IF NOT EXISTS) AND MariaDB 10.4+ (Bluehost/XAMPP).
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsAdded' => [],
        'indexesAdded' => [],
        'tablesCreated'=> [],
    ];

    // 1. Users table: ensure username + unique index (eliminates per-request ALTER TABLE — FND-29)
    //    and add explicit account_status separating user account state from registration/attendance state.
    if (pfgDbEnsureColumn($pdo, 'users', 'username', 'VARCHAR(100) DEFAULT NULL AFTER `email`')) {
        $changes['columnsAdded'][] = 'users.username';
    }
    if (pfgDbEnsureIndex($pdo, 'users', 'users_username_unique', '`username`', true)) {
        $changes['indexesAdded'][] = 'users.users_username_unique';
    }
    if (pfgDbEnsureColumn($pdo, 'users', 'account_status', "VARCHAR(32) NOT NULL DEFAULT 'ACTIVE' AFTER `is_active`")) {
        $changes['columnsAdded'][] = 'users.account_status';
        $pdo->exec("UPDATE users SET account_status = CASE WHEN deleted_at IS NOT NULL THEN 'ARCHIVED' WHEN is_active = 0 THEN 'SUSPENDED' ELSE 'ACTIVE' END");
    }

    // 2. Registration categories: explicitly classify 3 PRIMARY categories (ATTENDEE, SLI, VOLUNTEER)
    //    while preserving all historical LEGACY categories without deletion.
    if (pfgDbEnsureColumn($pdo, 'registration_categories', 'category_tier', "VARCHAR(16) NOT NULL DEFAULT 'LEGACY' AFTER `code`")) {
        $changes['columnsAdded'][] = 'registration_categories.category_tier';
    }
    $pdo->exec("
        UPDATE registration_categories
        SET category_tier = CASE
            WHEN UPPER(code) IN ('ATTENDEE', 'SLI', 'VOLUNTEER') THEN 'PRIMARY'
            ELSE 'LEGACY'
        END
    ");

    // 3. Registrations: separate administrative assignment_status from approval status & attendance,
    //    and add idempotency_key + unique index for safe retriable submissions.
    if (pfgDbEnsureColumn($pdo, 'registrations', 'assignment_status', "VARCHAR(32) NOT NULL DEFAULT 'UNASSIGNED' AFTER `payment_status`")) {
        $changes['columnsAdded'][] = 'registrations.assignment_status';
    }
    if (pfgDbEnsureColumn($pdo, 'registrations', 'idempotency_key', 'VARCHAR(128) DEFAULT NULL AFTER `registration_code`')) {
        $changes['columnsAdded'][] = 'registrations.idempotency_key';
    }
    if (pfgDbEnsureIndex($pdo, 'registrations', 'uq_registrations_idempotency', '`edition_id`, `idempotency_key`', true)) {
        $changes['indexesAdded'][] = 'registrations.uq_registrations_idempotency';
    }

    // 4. Decoupled Manual Attendance Tables (Decision D-01: ZERO dependency on qr_credentials)
    if (!pfgDbTableExists($pdo, 'attendance_records')) {
        $pdo->exec("
            CREATE TABLE `attendance_records` (
              `id` CHAR(36) NOT NULL,
              `edition_id` CHAR(36) NOT NULL,
              `registration_id` CHAR(36) NOT NULL,
              `attendance_date_ist` CHAR(10) NOT NULL,
              `attendance_scope` VARCHAR(64) NOT NULL DEFAULT 'FESTIVAL_DAY',
              `status` VARCHAR(32) NOT NULL DEFAULT 'PRESENT',
              `marked_by_user_id` CHAR(36) NOT NULL,
              `marked_at` DATETIME NOT NULL,
              `station_id` CHAR(36) DEFAULT NULL,
              `venue_label` VARCHAR(150) DEFAULT NULL,
              `notes` TEXT DEFAULT NULL,
              `corrected_by_user_id` CHAR(36) DEFAULT NULL,
              `corrected_at` DATETIME DEFAULT NULL,
              `correction_reason` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_attendance_reg_date_scope` (`edition_id`, `registration_id`, `attendance_date_ist`, `attendance_scope`),
              KEY `idx_attendance_edition_date` (`edition_id`, `attendance_date_ist`),
              KEY `idx_attendance_reg_id` (`registration_id`),
              KEY `idx_attendance_marked_by` (`marked_by_user_id`),
              KEY `idx_attendance_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'attendance_records';
    }

    if (!pfgDbTableExists($pdo, 'attendance_correction_logs')) {
        $pdo->exec("
            CREATE TABLE `attendance_correction_logs` (
              `id` CHAR(36) NOT NULL,
              `attendance_record_id` CHAR(36) NOT NULL,
              `registration_id` CHAR(36) NOT NULL,
              `actor_user_id` CHAR(36) NOT NULL,
              `previous_status` VARCHAR(32) NOT NULL,
              `new_status` VARCHAR(32) NOT NULL,
              `reason` TEXT NOT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_att_corr_record` (`attendance_record_id`),
              KEY `idx_att_corr_reg` (`registration_id`),
              KEY `idx_att_corr_actor` (`actor_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'attendance_correction_logs';
    }

    // 5. Notification Queue Upgrade (FND-19 + Phase 3K honest delivery lifecycle & idempotency)
    $nqCols = [
        'event_type'          => 'VARCHAR(100) DEFAULT NULL AFTER `template_code`',
        'channel'             => "VARCHAR(32) NOT NULL DEFAULT 'EMAIL' AFTER `event_type`",
        'provider'            => 'VARCHAR(64) DEFAULT NULL AFTER `status`',
        'provider_message_id' => 'VARCHAR(191) DEFAULT NULL AFTER `provider`',
        'idempotency_key'     => 'VARCHAR(128) DEFAULT NULL AFTER `provider_message_id`',
        'provider_accepted_at'=> 'DATETIME DEFAULT NULL AFTER `sent_at`',
        'delivered_at'        => 'DATETIME DEFAULT NULL AFTER `provider_accepted_at`',
        'failed_at'           => 'DATETIME DEFAULT NULL AFTER `delivered_at`',
    ];
    foreach ($nqCols as $col => $def) {
        if (pfgDbEnsureColumn($pdo, 'notification_queue', $col, $def)) {
            $changes['columnsAdded'][] = 'notification_queue.' . $col;
        }
    }
    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'uq_notification_queue_idempotency', '`idempotency_key`', true)) {
        $changes['indexesAdded'][] = 'notification_queue.uq_notification_queue_idempotency';
    }
    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'idx_nq_status_next_retry', '`status`, `next_retry_at`', false)) {
        $changes['indexesAdded'][] = 'notification_queue.idx_nq_status_next_retry';
    }
    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'idx_nq_provider_msg', '`provider`, `provider_message_id`', false)) {
        $changes['indexesAdded'][] = 'notification_queue.idx_nq_provider_msg';
    }
    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'idx_nq_event_type', '`event_type`', false)) {
        $changes['indexesAdded'][] = 'notification_queue.idx_nq_event_type';
    }

    return $changes;
};
