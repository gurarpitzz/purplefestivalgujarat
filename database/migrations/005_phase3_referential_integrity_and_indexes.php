<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 3 Migration 005
 * 1. Referential Integrity: Non-cascading Foreign Keys (RESTRICT / SET NULL) across all canonical tables,
 *    with upfront orphan detection (NEVER auto-deletes orphaned rows).
 * 2. Targeted Performance Indexes for 5,000+ registrations, desk search, admin pagination,
 *    accommodation coordination, notification queue polling, and audit log filtering.
 */

return static function (PDO $pdo): array {
    $fkSpecs = [
        // Core registrations & categories
        ['registrations', 'fk_reg_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['registrations', 'fk_reg_user', 'user_id', 'users', 'id', 'RESTRICT'],
        ['registrations', 'fk_reg_category', 'category_id', 'registration_categories', 'id', 'RESTRICT'],
        ['registrations', 'fk_reg_reviewer', 'reviewer_id', 'users', 'id', 'SET NULL'],
        ['registration_categories', 'fk_regcat_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['registration_responses', 'fk_regresp_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['registration_number_sequences', 'fk_regnumseq_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],

        // Role-specific application tables
        ['volunteer_applications', 'fk_volapp_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['sli_applications', 'fk_sliapp_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['delegate_details', 'fk_deldet_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],

        // Protected disability & accommodation tables (canonical)
        ['participant_disability_disclosures', 'fk_pdd_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['participant_disability_disclosures', 'fk_pdd_discat', 'disability_category_id', 'disability_categories', 'id', 'SET NULL'],
        ['accommodation_requests', 'fk_accreq_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['accommodation_requests', 'fk_accreq_user', 'user_id', 'users', 'id', 'SET NULL'],
        ['accommodation_requests', 'fk_accreq_assigned', 'assigned_to_user_id', 'users', 'id', 'SET NULL'],
        ['accommodation_assignments', 'fk_accass_req', 'request_id', 'accommodation_requests', 'id', 'RESTRICT'],
        ['accommodation_assignments', 'fk_accass_assigned', 'assigned_to_user_id', 'users', 'id', 'SET NULL'],

        // Privacy & Consent
        ['privacy_consent_records', 'fk_pcr_user', 'user_id', 'users', 'id', 'RESTRICT'],
        ['privacy_consent_records', 'fk_pcr_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],

        // Decoupled Manual Attendance (Decision D-01)
        ['attendance_records', 'fk_attrec_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['attendance_records', 'fk_attrec_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['attendance_records', 'fk_attrec_markedby', 'marked_by_user_id', 'users', 'id', 'RESTRICT'],
        ['attendance_records', 'fk_attrec_station', 'station_id', 'checkin_stations', 'id', 'SET NULL'],
        ['attendance_records', 'fk_attrec_correctedby', 'corrected_by_user_id', 'users', 'id', 'SET NULL'],
        ['attendance_correction_logs', 'fk_attcorr_record', 'attendance_record_id', 'attendance_records', 'id', 'RESTRICT'],
        ['attendance_correction_logs', 'fk_attcorr_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['attendance_correction_logs', 'fk_attcorr_actor', 'actor_user_id', 'users', 'id', 'RESTRICT'],

        // Preserved Legacy QR / Check-in Historical Tables (non-cascading RESTRICT)
        ['qr_credentials', 'fk_qrc_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['qr_credentials', 'fk_qrc_user', 'user_id', 'users', 'id', 'RESTRICT'],
        ['qr_credentials', 'fk_qrc_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['checkins', 'fk_chk_cred', 'credential_id', 'qr_credentials', 'id', 'RESTRICT'],
        ['checkins', 'fk_chk_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['checkins', 'fk_chk_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['checkins', 'fk_chk_operator', 'operator_user_id', 'users', 'id', 'RESTRICT'],
        ['credential_events', 'fk_credev_cred', 'credential_id', 'qr_credentials', 'id', 'RESTRICT'],
        ['credential_events', 'fk_credev_actor', 'actor_user_id', 'users', 'id', 'SET NULL'],

        // Festival & CMS Content
        ['checkin_stations', 'fk_chkst_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['checkin_stations', 'fk_chkst_venue', 'venue_id', 'venues', 'id', 'SET NULL'],
        ['programme_items', 'fk_prog_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['cms_pages', 'fk_cmspage_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['cms_sections', 'fk_cmssec_page', 'page_id', 'cms_pages', 'id', 'RESTRICT'],
        ['faq_items', 'fk_faq_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],
        ['announcements', 'fk_ann_edition', 'edition_id', 'festival_editions', 'id', 'RESTRICT'],

        // Notifications & Audit
        ['notification_queue', 'fk_nq_user', 'recipient_user_id', 'users', 'id', 'SET NULL'],
        ['notification_delivery_logs', 'fk_ndl_job', 'job_id', 'notification_queue', 'id', 'RESTRICT'],
        ['audit_logs', 'fk_audit_actor', 'actor_user_id', 'users', 'id', 'SET NULL'],
        ['admin_actions', 'fk_admact_admin', 'admin_user_id', 'users', 'id', 'RESTRICT'],
        ['admin_actions', 'fk_admact_target', 'target_user_id', 'users', 'id', 'SET NULL'],

        // Deferred Financial Canonical Tables (D-05)
        ['coupon_redemptions', 'fk_credemp_coupon', 'coupon_id', 'coupons', 'id', 'RESTRICT'],
        ['coupon_redemptions', 'fk_credemp_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['coupon_redemptions', 'fk_credemp_user', 'user_id', 'users', 'id', 'RESTRICT'],
        ['payment_orders', 'fk_payord_reg', 'registration_id', 'registrations', 'id', 'RESTRICT'],
        ['payment_orders', 'fk_payord_user', 'user_id', 'users', 'id', 'SET NULL'],
    ];

    $fkAdded = 0;
    $fkExisting = 0;
    $fkSkippedOrphans = [];

    foreach ($fkSpecs as [$child, $name, $col, $parent, $pcol, $onDelete]) {
        $res = pfgDbEnsureForeignKey($pdo, $child, $name, $col, $parent, $pcol, $onDelete, 'CASCADE');
        if ($res['status'] === 'ADDED') {
            $fkAdded++;
        } elseif ($res['status'] === 'ALREADY_EXISTS') {
            $fkExisting++;
        } elseif ($res['status'] === 'SKIPPED_ORPHANS') {
            $fkSkippedOrphans[] = $res;
        }
    }

    // Targeted Performance Indexes (Phase 3L — 5,000+ registration scale)
    $indexSpecs = [
        ['registrations', 'idx_reg_edition_deleted_created', '`edition_id`, `deleted_at`, `created_at`', false],
        ['registrations', 'idx_reg_edition_cat_status_del', '`edition_id`, `category_code`, `status`, `deleted_at`', false],
        ['registrations', 'idx_reg_deleted_email', '`deleted_at`, `email`', false],
        ['registrations', 'idx_reg_deleted_phone', '`deleted_at`, `phone`', false],
        ['registrations', 'idx_reg_deleted_fullname', '`deleted_at`, `full_name`(100)', false],
        ['registrations', 'idx_reg_user_edition_cat_del', '`user_id`, `edition_id`, `category_code`, `deleted_at`', false],
        ['registrations', 'idx_reg_assignment_status', '`assignment_status`, `category_code`', false],
        ['users', 'idx_users_account_status', '`account_status`, `is_active`', false],
        ['accommodation_requests', 'idx_accomm_req_status_updated', '`status`, `updated_at`', false],
        ['accommodation_requests', 'idx_accomm_req_reg_status', '`registration_id`, `status`', false],
        ['audit_logs', 'idx_audit_created_action', '`created_at`, `action`', false],
        ['audit_logs', 'idx_audit_resource_created', '`resource_type`, `resource_id`, `created_at`', false],
        ['admin_actions', 'idx_admin_actions_created', '`created_at`, `action_type`', false],
        ['sessions', 'idx_sessions_user_expires', '`user_id`, `expires_at`', false],
    ];

    $idxAdded = 0;
    foreach ($indexSpecs as [$tbl, $idxName, $colsSql, $unique]) {
        if (pfgDbEnsureIndex($pdo, $tbl, $idxName, $colsSql, $unique)) {
            $idxAdded++;
        }
    }

    return [
        'foreignKeysAdded'          => $fkAdded,
        'foreignKeysAlreadyPresent' => $fkExisting,
        'foreignKeysSkippedOrphans' => $fkSkippedOrphans,
        'performanceIndexesAdded'   => $idxAdded,
    ];
};
