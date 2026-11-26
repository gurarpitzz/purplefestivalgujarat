<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 12 Migration 010
 * Programme Venues, Festival Sessions & Volunteer / SLI Duty Assignments.
 *
 * Additively evolves `venues` and `festival_sessions` (no column dropped, no data touched) and creates
 * `duty_assignments`. Participant-bearing FKs are ON DELETE RESTRICT; optional programme links are
 * ON DELETE SET NULL. 100% portable across MySQL 8.0 and MariaDB 10.4+. Zero ON DELETE CASCADE.
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsAdded'  => [],
        'indexesAdded'  => [],
        'tablesCreated' => [],
        'fksAdded'      => [],
    ];

    // 1. Additive evolution of existing programme tables.
    $evolve = [
        'venues' => [
            'operational_notes' => 'TEXT DEFAULT NULL',
            'updated_at'        => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ],
        'festival_sessions' => [
            'description'       => 'TEXT DEFAULT NULL',
            'volunteers_needed' => 'INT NOT NULL DEFAULT 0',
            'slis_needed'       => 'INT NOT NULL DEFAULT 0',
            'updated_at'        => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ],
    ];
    foreach ($evolve as $table => $cols) {
        foreach ($cols as $col => $def) {
            if (pfgDbEnsureColumn($pdo, $table, $col, $def)) {
                $changes['columnsAdded'][] = $table . '.' . $col;
            }
        }
    }

    // 2. duty_assignments
    if (!pfgDbTableExists($pdo, 'duty_assignments')) {
        $pdo->exec("
            CREATE TABLE `duty_assignments` (
              `id` CHAR(36) NOT NULL,
              `edition_id` CHAR(36) NOT NULL,
              `registration_id` CHAR(36) NOT NULL,
              `participant_category` VARCHAR(32) NOT NULL,
              `session_id` CHAR(36) DEFAULT NULL,
              `venue_id` CHAR(36) DEFAULT NULL,
              `role_title` VARCHAR(150) NOT NULL,
              `department` VARCHAR(120) DEFAULT NULL,
              `duty_date_ist` CHAR(10) NOT NULL,
              `shift_code` VARCHAR(32) NOT NULL,
              `start_time` CHAR(5) NOT NULL,
              `end_time` CHAR(5) NOT NULL,
              `reporting_supervisor` VARCHAR(150) DEFAULT NULL,
              `reporting_time` VARCHAR(80) DEFAULT NULL,
              `reporting_instructions` TEXT DEFAULT NULL,
              `pairing_group_id` CHAR(36) DEFAULT NULL,
              `status` VARCHAR(32) NOT NULL DEFAULT 'ASSIGNED',
              `assigned_by_user_id` CHAR(36) DEFAULT NULL,
              `override_reason` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_duty_reg_date` (`registration_id`, `duty_date_ist`, `status`),
              KEY `idx_duty_session` (`session_id`, `status`),
              KEY `idx_duty_venue_date` (`venue_id`, `duty_date_ist`),
              KEY `idx_duty_edition_date` (`edition_id`, `duty_date_ist`),
              KEY `idx_duty_assigned_by` (`assigned_by_user_id`),
              KEY `idx_duty_pairing` (`pairing_group_id`),
              CONSTRAINT `fk_duty_edition` FOREIGN KEY (`edition_id`) REFERENCES `festival_editions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
              CONSTRAINT `fk_duty_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
              CONSTRAINT `fk_duty_session` FOREIGN KEY (`session_id`) REFERENCES `festival_sessions` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
              CONSTRAINT `fk_duty_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
              CONSTRAINT `fk_duty_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'duty_assignments';
        foreach (['fk_duty_edition', 'fk_duty_registration', 'fk_duty_session', 'fk_duty_venue', 'fk_duty_assigned_by'] as $fk) {
            $changes['fksAdded'][] = $fk;
        }
    }

    // 3. Resilience: add any missing non-cascading FKs if the table pre-existed without them.
    foreach ([
        ['fk_duty_edition',      'edition_id',          'festival_editions', 'RESTRICT'],
        ['fk_duty_registration', 'registration_id',     'registrations',     'RESTRICT'],
        ['fk_duty_session',      'session_id',          'festival_sessions', 'SET NULL'],
        ['fk_duty_venue',        'venue_id',            'venues',            'SET NULL'],
        ['fk_duty_assigned_by',  'assigned_by_user_id', 'users',             'SET NULL'],
    ] as [$name, $col, $parent, $onDelete]) {
        $res = pfgDbEnsureForeignKey($pdo, 'duty_assignments', $name, $col, $parent, 'id', $onDelete, 'RESTRICT');
        if ($res['status'] === 'ADDED') {
            $changes['fksAdded'][] = $name;
        }
    }

    return $changes;
};
