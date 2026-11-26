<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 4 Migration 006
 * Transactional Notification Queue Locking, Retry Classification, Resend Webhook Deduplication,
 * Ephemeral Encrypted Secret Delivery & In-Application Notification Foundation (Phase 4C–4O).
 *
 * 100% portable across MySQL 8.0 and MariaDB 10.4+ (Bluehost cPanel & XAMPP).
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsAdded'  => [],
        'indexesAdded'  => [],
        'tablesCreated' => [],
        'fksAdded'      => [],
    ];

    // 1. Upgrade `notification_queue` for lease-based claiming, failure classification,
    //    out-of-order webhook protection, admin resend tracking, and ephemeral token delivery.
    $nqCols = [
        'entity_type'            => 'VARCHAR(64) DEFAULT NULL AFTER `channel`',
        'entity_id'              => 'CHAR(36) DEFAULT NULL AFTER `entity_type`',
        'priority'               => 'TINYINT NOT NULL DEFAULT 5 AFTER `status`',
        'locked_by'              => 'VARCHAR(100) DEFAULT NULL AFTER `next_retry_at`',
        'locked_until'           => 'DATETIME DEFAULT NULL AFTER `locked_by`',
        'last_attempt_at'        => 'DATETIME DEFAULT NULL AFTER `locked_until`',
        'last_provider_event'    => 'VARCHAR(64) DEFAULT NULL AFTER `provider_message_id`',
        'last_provider_event_at' => 'DATETIME DEFAULT NULL AFTER `last_provider_event`',
        'bounced_at'             => 'DATETIME DEFAULT NULL AFTER `failed_at`',
        'resent_from_id'         => 'CHAR(36) DEFAULT NULL AFTER `idempotency_key`',
        'error_category'         => 'VARCHAR(64) DEFAULT NULL AFTER `last_error`',
        'ephemeral_secret_enc'   => 'LONGTEXT DEFAULT NULL AFTER `payload`',
    ];
    foreach ($nqCols as $col => $def) {
        if (pfgDbEnsureColumn($pdo, 'notification_queue', $col, $def)) {
            $changes['columnsAdded'][] = 'notification_queue.' . $col;
        }
    }

    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'idx_nq_claim', '`status`, `next_retry_at`, `locked_until`, `priority`, `created_at`', false)) {
        $changes['indexesAdded'][] = 'notification_queue.idx_nq_claim';
    }
    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'idx_nq_locked_by', '`locked_by`, `status`', false)) {
        $changes['indexesAdded'][] = 'notification_queue.idx_nq_locked_by';
    }
    if (pfgDbEnsureIndex($pdo, 'notification_queue', 'idx_nq_entity', '`entity_type`, `entity_id`', false)) {
        $changes['indexesAdded'][] = 'notification_queue.idx_nq_entity';
    }

    // 2. Upgrade `notification_delivery_logs` for attempt tracking & Svix webhook replay deduplication
    $ndlCols = [
        'attempt_number'    => 'INT NOT NULL DEFAULT 1 AFTER `job_id`',
        'event_type'        => 'VARCHAR(64) DEFAULT NULL AFTER `provider_message_id`',
        'provider_event_id' => 'VARCHAR(191) DEFAULT NULL AFTER `event_type`',
        'http_status'       => 'INT DEFAULT NULL AFTER `status`',
        'error_category'    => 'VARCHAR(64) DEFAULT NULL AFTER `http_status`',
    ];
    foreach ($ndlCols as $col => $def) {
        if (pfgDbEnsureColumn($pdo, 'notification_delivery_logs', $col, $def)) {
            $changes['columnsAdded'][] = 'notification_delivery_logs.' . $col;
        }
    }

    if (pfgDbEnsureIndex($pdo, 'notification_delivery_logs', 'uq_ndl_provider_event', '`provider`, `provider_event_id`', true)) {
        $changes['indexesAdded'][] = 'notification_delivery_logs.uq_ndl_provider_event';
    }
    if (pfgDbEnsureIndex($pdo, 'notification_delivery_logs', 'idx_ndl_job_created', '`job_id`, `created_at`', false)) {
        $changes['indexesAdded'][] = 'notification_delivery_logs.idx_ndl_job_created';
    }

    // 3. Create `in_app_notifications` foundation (Phase 4O — clean separation from email queue)
    if (!pfgDbTableExists($pdo, 'in_app_notifications')) {
        $pdo->exec("
            CREATE TABLE `in_app_notifications` (
              `id` CHAR(36) NOT NULL,
              `user_id` CHAR(36) NOT NULL,
              `event_type` VARCHAR(100) NOT NULL,
              `title` VARCHAR(200) NOT NULL,
              `message` TEXT NOT NULL,
              `action_url` VARCHAR(255) DEFAULT NULL,
              `entity_type` VARCHAR(64) DEFAULT NULL,
              `entity_id` CHAR(36) DEFAULT NULL,
              `source_queue_id` CHAR(36) DEFAULT NULL,
              `idempotency_key` VARCHAR(128) DEFAULT NULL,
              `is_read` TINYINT(1) NOT NULL DEFAULT 0,
              `read_at` DATETIME DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_inapp_idempotency` (`idempotency_key`),
              KEY `idx_inapp_user_read_created` (`user_id`, `is_read`, `created_at`),
              KEY `idx_inapp_entity` (`entity_type`, `entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'in_app_notifications';
    }

    // Add non-cascading FKs for in_app_notifications
    foreach ([
        ['in_app_notifications', 'fk_inapp_user', 'user_id', 'users', 'id', 'RESTRICT'],
        ['in_app_notifications', 'fk_inapp_queue', 'source_queue_id', 'notification_queue', 'id', 'SET NULL'],
    ] as [$child, $name, $col, $parent, $pcol, $onDelete]) {
        $res = pfgDbEnsureForeignKey($pdo, $child, $name, $col, $parent, $pcol, $onDelete, 'CASCADE');
        if ($res['status'] === 'ADDED') {
            $changes['fksAdded'][] = $name;
        }
    }

    return $changes;
};

