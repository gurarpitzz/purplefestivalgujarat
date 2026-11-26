<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 6/7 Migration 008
 * Three-Category Registration (ATTENDEE / VOLUNTEER / SLI), Registration Lifecycle History,
 * Autosave Drafts, Attendee Detail Separation & Additive Volunteer / SLI / Accommodation Columns.
 *
 * Non-destructive: LEGACY categories are only deactivated (is_active = 0) — every historical row
 * and every historical registration pointing at them is preserved untouched.
 * 100% portable across MySQL 8.0 and MariaDB 10.4+. Zero ON DELETE CASCADE on participant/audit data.
 */

return static function (PDO $pdo): array {
    $changes = [
        'columnsAdded'            => [],
        'indexesAdded'            => [],
        'tablesCreated'           => [],
        'fksAdded'                => [],
        'legacyCategoriesDeactivated' => 0,
        'volunteersBackfilled'    => 0,
        'accommodationTypesAdded' => 0,
    ];

    // 1. Deactivate (never delete) LEGACY registration categories so only the 3 PRIMARY ones are open.
    if (pfgDbColumnExists($pdo, 'registration_categories', 'category_tier')) {
        $changes['legacyCategoriesDeactivated'] = (int)$pdo->exec(
            "UPDATE registration_categories SET is_active = 0 WHERE category_tier = 'LEGACY' AND is_active <> 0"
        );
    }

    // 2. attendee_details — one row per ATTENDEE registration (non-sensitive logistics + encrypted dietary).
    if (!pfgDbTableExists($pdo, 'attendee_details')) {
        $pdo->exec("
            CREATE TABLE `attendee_details` (
              `id` CHAR(36) NOT NULL,
              `registration_id` CHAR(36) NOT NULL,
              `age_group` VARCHAR(32) DEFAULT NULL,
              `gender` VARCHAR(32) DEFAULT NULL,
              `self_identification_tags` LONGTEXT DEFAULT NULL,
              `guardian_name` VARCHAR(150) DEFAULT NULL,
              `guardian_relationship` VARCHAR(80) DEFAULT NULL,
              `guardian_phone` VARCHAR(40) DEFAULT NULL,
              `para_sports_athlete` TINYINT(1) NOT NULL DEFAULT 0,
              `para_sports_events` LONGTEXT DEFAULT NULL,
              `para_sports_other` VARCHAR(120) DEFAULT NULL,
              `outstation_travel_required` TINYINT(1) NOT NULL DEFAULT 0,
              `arrival_at` VARCHAR(40) DEFAULT NULL,
              `departure_at` VARCHAR(40) DEFAULT NULL,
              `travel_mode` VARCHAR(32) DEFAULT NULL,
              `lodging_needed` TINYINT(1) NOT NULL DEFAULT 0,
              `accessible_room_needed` TINYINT(1) NOT NULL DEFAULT 0,
              `accompanying_count` TINYINT UNSIGNED DEFAULT NULL,
              `food_preference` VARCHAR(32) DEFAULT NULL,
              `dietary_requirements_encrypted` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_attendee_details_reg` (`registration_id`),
              CONSTRAINT `fk_attendee_details_reg` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'attendee_details';
        $changes['fksAdded'][] = 'fk_attendee_details_reg';
    }

    // 3. registration_status_history — append-only lifecycle audit (actor kept as SET NULL on user removal).
    if (!pfgDbTableExists($pdo, 'registration_status_history')) {
        $pdo->exec("
            CREATE TABLE `registration_status_history` (
              `id` CHAR(36) NOT NULL,
              `registration_id` CHAR(36) NOT NULL,
              `previous_status` VARCHAR(40) DEFAULT NULL,
              `new_status` VARCHAR(40) NOT NULL,
              `actor_user_id` CHAR(36) DEFAULT NULL,
              `actor_role` VARCHAR(32) DEFAULT NULL,
              `reason` TEXT DEFAULT NULL,
              `public_message` TEXT DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_rsh_reg_created` (`registration_id`, `created_at`),
              KEY `idx_rsh_actor` (`actor_user_id`),
              CONSTRAINT `fk_rsh_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
              CONSTRAINT `fk_rsh_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'registration_status_history';
        $changes['fksAdded'][] = 'fk_rsh_registration';
        $changes['fksAdded'][] = 'fk_rsh_actor';
    }

    // 4. registration_drafts — per-user, per-category autosave payloads.
    if (!pfgDbTableExists($pdo, 'registration_drafts')) {
        $pdo->exec("
            CREATE TABLE `registration_drafts` (
              `id` CHAR(36) NOT NULL,
              `edition_id` CHAR(36) NOT NULL,
              `user_id` CHAR(36) NOT NULL,
              `category_code` VARCHAR(64) NOT NULL,
              `payload_json` LONGTEXT NOT NULL,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_draft_ed_user_cat` (`edition_id`, `user_id`, `category_code`),
              KEY `idx_draft_user` (`user_id`),
              CONSTRAINT `fk_draft_edition` FOREIGN KEY (`edition_id`) REFERENCES `festival_editions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
              CONSTRAINT `fk_draft_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $changes['tablesCreated'][] = 'registration_drafts';
        $changes['fksAdded'][] = 'fk_draft_edition';
        $changes['fksAdded'][] = 'fk_draft_user';
    }

    // 4b. Resilience: add any missing non-cascading FKs if a table pre-existed without them.
    foreach ([
        ['attendee_details',           'fk_attendee_details_reg', 'registration_id', 'registrations',     'RESTRICT'],
        ['registration_status_history','fk_rsh_registration',     'registration_id', 'registrations',     'RESTRICT'],
        ['registration_status_history','fk_rsh_actor',            'actor_user_id',   'users',             'SET NULL'],
        ['registration_drafts',        'fk_draft_edition',        'edition_id',      'festival_editions', 'RESTRICT'],
        ['registration_drafts',        'fk_draft_user',           'user_id',         'users',             'RESTRICT'],
    ] as [$child, $name, $col, $parent, $onDelete]) {
        $res = pfgDbEnsureForeignKey($pdo, $child, $name, $col, $parent, 'id', $onDelete, 'RESTRICT');
        if ($res['status'] === 'ADDED') {
            $changes['fksAdded'][] = $name;
        }
    }

    // 5. Additive columns / indexes on existing tables (information_schema guarded).
    $additions = [
        'registrations' => [
            'columns' => [
                'city_district'                  => 'VARCHAR(120) DEFAULT NULL',
                'state'                          => 'VARCHAR(100) DEFAULT NULL',
                'emergency_contact_name'         => 'VARCHAR(150) DEFAULT NULL',
                'emergency_contact_relationship' => 'VARCHAR(80) DEFAULT NULL',
                'emergency_contact_phone'        => 'VARCHAR(40) DEFAULT NULL',
                'public_review_note'             => 'TEXT DEFAULT NULL',
            ],
            'indexes' => [
                'idx_reg_edition_state' => '`edition_id`, `state`',
            ],
        ],
        'accommodation_requests' => [
            'columns' => [
                'escort_accompanying'   => 'TINYINT(1) NOT NULL DEFAULT 0',
                'escort_name_encrypted' => 'TEXT DEFAULT NULL',
            ],
            'indexes' => [],
        ],
        'volunteer_applications' => [
            'columns' => [
                'msu_affiliation_status' => "VARCHAR(32) NOT NULL DEFAULT 'EXTERNAL'",
                'msu_faculty_department' => 'VARCHAR(150) DEFAULT NULL',
                'primary_role'           => 'VARCHAR(64) DEFAULT NULL',
                'anchoring_experience'   => 'TEXT DEFAULT NULL',
                'languages_other'        => 'VARCHAR(80) DEFAULT NULL',
                'availability_shifts'    => 'LONGTEXT DEFAULT NULL',
            ],
            'indexes' => [
                'idx_vol_msu_status'  => '`msu_affiliation_status`',
                'idx_vol_primary_role' => '`primary_role`',
            ],
        ],
        'sli_applications' => [
            'columns' => [
                'age'                        => 'INT DEFAULT NULL',
                'qualification_category'     => 'VARCHAR(64) DEFAULT NULL',
                'experience_band'            => 'VARCHAR(32) DEFAULT NULL',
                'comfortable_public_stage'   => 'TINYINT(1) DEFAULT NULL',
                'availability_shifts'        => 'LONGTEXT DEFAULT NULL',
            ],
            'indexes' => [
                'idx_sli_based_in_gujarat'     => '`based_in_gujarat`',
                'idx_sli_qualification_category' => '`qualification_category`',
            ],
        ],
    ];
    foreach ($additions as $table => $spec) {
        foreach ($spec['columns'] as $col => $def) {
            if (pfgDbEnsureColumn($pdo, $table, $col, $def)) {
                $changes['columnsAdded'][] = $table . '.' . $col;
            }
        }
        foreach ($spec['indexes'] as $idx => $cols) {
            if (pfgDbEnsureIndex($pdo, $table, $idx, $cols, false)) {
                $changes['indexesAdded'][] = $table . '.' . $idx;
            }
        }
    }

    // 6. Backfill: pre-existing MSU student/alumni volunteers become 'MSU_LEGACY_UNSPECIFIED'
    //    (clean, reversible classification — original is_msu_student_or_alumni flag is untouched).
    if (pfgDbColumnExists($pdo, 'volunteer_applications', 'msu_affiliation_status')
        && pfgDbColumnExists($pdo, 'volunteer_applications', 'is_msu_student_or_alumni')) {
        $changes['volunteersBackfilled'] = (int)$pdo->exec(
            "UPDATE volunteer_applications
                SET msu_affiliation_status = 'MSU_LEGACY_UNSPECIFIED'
              WHERE is_msu_student_or_alumni = 1 AND msu_affiliation_status = 'EXTERNAL'"
        );
    }

    // 7. Seed 5 additional organizer accommodation types (idempotent; never touches existing rows/codes).
    //    Deterministic ids live in a dedicated 41000000-…-0000000001xx range that cannot collide with
    //    the index-based ids used by database/seed.php for the base catalogue.
    if (pfgDbTableExists($pdo, 'accommodation_types')) {
        $extra = [
            ['41000000-0000-4000-8000-000000000101', 'TACTILE_INTERPRETATION', 'Tactile Interpretation (Deafblind support)',  2],
            ['41000000-0000-4000-8000-000000000102', 'WHEELCHAIR_CHARGING',    'Wheelchair Charging Bay',                      7],
            ['41000000-0000-4000-8000-000000000103', 'SIGHTED_GUIDE',          'Sighted Guide',                                8],
            ['41000000-0000-4000-8000-000000000104', 'LIVE_CAPTIONS',          'Live Captions (CART / Speech-to-Text)',       10],
            ['41000000-0000-4000-8000-000000000105', 'DIETARY_ACCESS_SUPPORT', 'Dietary & Food-Access Support',               12],
        ];
        $exists = $pdo->prepare('SELECT COUNT(*) FROM accommodation_types WHERE code = :code');
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO accommodation_types (id, code, label, is_active, display_order) VALUES (:id, :code, :label, 1, :ord)'
        );
        foreach ($extra as [$id, $code, $label, $ord]) {
            $exists->execute([':code' => $code]);
            if ((int)$exists->fetchColumn() > 0) {
                continue; // code already present (possibly organizer-edited) — leave untouched
            }
            $ins->execute([':id' => $id, ':code' => $code, ':label' => $label, ':ord' => $ord]);
            $changes['accommodationTypesAdded'] += $ins->rowCount();
        }
    }

    return $changes;
};
