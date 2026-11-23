-- ============================================================================
-- Purple Festival Gujarat 2027 — MySQL 8.0 / MariaDB Production Schema
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. AUTHENTICATION & RBAC
-- ============================================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(32) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` CHAR(36) NOT NULL,
  `role_id` CHAR(36) NOT NULL,
  `permission_id` CHAR(36) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_unique` (`role_id`, `permission_id`),
  KEY `role_permissions_perm_idx` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` CHAR(36) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` DATETIME DEFAULT NULL,
  `password_hash` TEXT DEFAULT NULL,
  `full_name` VARCHAR(200) NOT NULL,
  `phone` VARCHAR(40) DEFAULT NULL,
  `country` VARCHAR(100) NOT NULL DEFAULT 'India',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `mfa_secret_encrypted` TEXT DEFAULT NULL,
  `mfa_secret` TEXT DEFAULT NULL,
  `failed_login_attempts` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `role_id` CHAR(36) NOT NULL,
  `custom_permissions` LONGTEXT DEFAULT NULL,
  `assigned_by` CHAR(36) DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_unique` (`user_id`, `role_id`),
  KEY `user_roles_role_idx` (`role_id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_accounts` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `provider` VARCHAR(64) NOT NULL,
  `provider_account_id` VARCHAR(255) NOT NULL,
  `token_hash` VARCHAR(191) DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `metadata` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `auth_accounts_user_idx` (`user_id`),
  KEY `auth_accounts_token_idx` (`provider`, `token_hash`),
  CONSTRAINT `fk_auth_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `token_hash` VARCHAR(191) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_reset_tokens_hash_unique` (`token_hash`),
  KEY `password_reset_tokens_user_idx` (`user_id`),
  CONSTRAINT `fk_pwd_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `token_hash` VARCHAR(128) NOT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `mfa_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sessions_token_hash_unique` (`token_hash`),
  KEY `sessions_user_idx` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. FESTIVAL CORE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `festival_editions` (
  `id` CHAR(36) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `tagline` VARCHAR(300) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) NOT NULL,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `registration_opens_at` DATETIME DEFAULT NULL,
  `registration_closes_at` DATETIME DEFAULT NULL,
  `attendance_policy` VARCHAR(40) NOT NULL DEFAULT 'ONCE_PER_DAY',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `festival_editions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `venues` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `address` TEXT DEFAULT NULL,
  `confirmation_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  `accessibility_features` LONGTEXT DEFAULT NULL,
  `capacity` INT DEFAULT NULL,
  `map_url` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `venues_edition_idx` (`edition_id`),
  CONSTRAINT `fk_venues_edition` FOREIGN KEY (`edition_id`) REFERENCES `festival_editions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `festival_sessions` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `venue_id` CHAR(36) DEFAULT NULL,
  `title` VARCHAR(250) NOT NULL,
  `track` VARCHAR(100) NOT NULL,
  `day_number` INT NOT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `isl_required` TINYINT(1) NOT NULL DEFAULT 1,
  `captioning_available` TINYINT(1) NOT NULL DEFAULT 1,
  `wheelchair_accessible` TINYINT(1) NOT NULL DEFAULT 1,
  `status` VARCHAR(32) NOT NULL DEFAULT 'PUBLISHED',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `festival_sessions_edition_idx` (`edition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkin_stations` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `venue_id` CHAR(36) DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `gate_code` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `checkin_stations_edition_idx` (`edition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `programme_items` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `session_id` CHAR(36) DEFAULT NULL,
  `day_number` INT NOT NULL,
  `time_slot` VARCHAR(80) NOT NULL,
  `title` VARCHAR(250) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` VARCHAR(100) NOT NULL,
  `venue_name` VARCHAR(200) DEFAULT NULL,
  `speakers` LONGTEXT DEFAULT NULL,
  `accessibility_services` LONGTEXT DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'PUBLISHED',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `programme_items_edition_idx` (`edition_id`, `day_number`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organizations` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(250) NOT NULL,
  `short_name` VARCHAR(100) DEFAULT NULL,
  `website_url` TEXT DEFAULT NULL,
  `logo_url` TEXT DEFAULT NULL,
  `logo_alt_text` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `partners` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `organization_id` CHAR(36) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `consent_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `partners_edition_idx` (`edition_id`, `display_order`),
  CONSTRAINT `fk_partners_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. ACCESSIBILITY TAXONOMY & REGISTRATION
-- ============================================================================

CREATE TABLE IF NOT EXISTS `disability_categories` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `rpwd_group` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `accommodation_examples` LONGTEXT DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disability_categories_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accommodation_types` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `label` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accommodation_types_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registration_categories` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
  `requires_payment` TINYINT(1) NOT NULL DEFAULT 0,
  `fee_amount_inr` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT NOT NULL DEFAULT 0,
  `opens_at` DATETIME DEFAULT NULL,
  `closes_at` DATETIME DEFAULT NULL,
  `confirmation_message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reg_cat_edition_code_unique` (`edition_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registration_form_versions` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `category_code` VARCHAR(64) NOT NULL,
  `version_number` INT NOT NULL,
  `schema_definition` LONGTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reg_form_ver_unique` (`edition_id`, `category_code`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` CHAR(36) NOT NULL,
  `registration_code` VARCHAR(40) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `category_id` CHAR(36) NOT NULL,
  `category_code` VARCHAR(64) NOT NULL,
  `form_version_id` CHAR(36) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'SUBMITTED',
  `payment_status` VARCHAR(40) NOT NULL DEFAULT 'NOT_REQUIRED',
  `full_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `country` VARCHAR(100) NOT NULL DEFAULT 'India',
  `occupation` VARCHAR(120) DEFAULT NULL,
  `organization_name` VARCHAR(200) DEFAULT NULL,
  `designation` VARCHAR(150) DEFAULT NULL,
  `is_person_with_disability` VARCHAR(32) NOT NULL DEFAULT 'PREFER_NOT_TO_SAY',
  `attending_days` LONGTEXT DEFAULT NULL,
  `interested_tracks` LONGTEXT DEFAULT NULL,
  `coupon_code` VARCHAR(64) DEFAULT NULL,
  `fee_amount_inr` INT NOT NULL DEFAULT 0,
  `discount_inr` INT NOT NULL DEFAULT 0,
  `net_amount_inr` INT NOT NULL DEFAULT 0,
  `reviewer_id` CHAR(36) DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `registrations_code_unique` (`registration_code`),
  KEY `registrations_user_idx` (`user_id`),
  KEY `registrations_edition_cat_idx` (`edition_id`, `category_code`),
  KEY `registrations_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registration_responses` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `form_version_id` CHAR(36) DEFAULT NULL,
  `answers` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reg_responses_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `participant_affiliations` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `occupation` VARCHAR(120) DEFAULT NULL,
  `organization_name` VARCHAR(200) DEFAULT NULL,
  `designation` VARCHAR(150) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `part_aff_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registration_disabilities` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `disability_category_id` CHAR(36) DEFAULT NULL,
  `disability_code` VARCHAR(64) DEFAULT NULL,
  `category_code` VARCHAR(64) DEFAULT NULL,
  `self_description` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reg_dis_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `participant_disability_disclosures` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `disability_category_id` CHAR(36) DEFAULT NULL,
  `disability_code` VARCHAR(64) DEFAULT NULL,
  `category_code` VARCHAR(64) DEFAULT NULL,
  `self_description` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pdd_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registration_accommodations` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) DEFAULT NULL,
  `accommodation_type_id` CHAR(36) DEFAULT NULL,
  `accommodation_code` VARCHAR(64) DEFAULT NULL,
  `selected_types` LONGTEXT DEFAULT NULL,
  `free_text_details` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `coordinator_notes` TEXT DEFAULT NULL,
  `assigned_to_user_id` CHAR(36) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'REQUESTED',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `reg_accomm_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accommodation_requests` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) DEFAULT NULL,
  `accommodation_type_id` CHAR(36) DEFAULT NULL,
  `accommodation_code` VARCHAR(64) DEFAULT NULL,
  `selected_types` LONGTEXT DEFAULT NULL,
  `free_text_details` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `coordinator_notes` TEXT DEFAULT NULL,
  `assigned_to_user_id` CHAR(36) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'REQUESTED',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `accomm_req_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accommodation_assignments` (
  `id` CHAR(36) NOT NULL,
  `request_id` CHAR(36) NOT NULL,
  `assigned_to_user_id` CHAR(36) DEFAULT NULL,
  `coordinator_notes` TEXT DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'ASSIGNED',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `accomm_assign_req_idx` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `volunteer_applications` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `age` INT DEFAULT NULL,
  `gender` VARCHAR(50) DEFAULT NULL,
  `city_state` VARCHAR(150) DEFAULT NULL,
  `institution_name` VARCHAR(200) DEFAULT NULL,
  `is_msu_student_or_alumni` TINYINT(1) NOT NULL DEFAULT 0,
  `crr_number` VARCHAR(80) DEFAULT NULL,
  `preferred_roles` LONGTEXT DEFAULT NULL,
  `availability_days` LONGTEXT DEFAULT NULL,
  `languages_spoken` LONGTEXT DEFAULT NULL,
  `relevant_experience` TEXT DEFAULT NULL,
  `emergency_contact_name` VARCHAR(150) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(40) DEFAULT NULL,
  `document_url` TEXT DEFAULT NULL,
  `assigned_department` VARCHAR(120) DEFAULT NULL,
  `assigned_responsibility` TEXT DEFAULT NULL,
  `assigned_supervisor` VARCHAR(150) DEFAULT NULL,
  `assigned_venue` VARCHAR(200) DEFAULT NULL,
  `assigned_shift` VARCHAR(100) DEFAULT NULL,
  `reporting_time` VARCHAR(80) DEFAULT NULL,
  `assignment_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vol_app_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sli_applications` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `city_state` VARCHAR(150) DEFAULT NULL,
  `based_in_gujarat` TINYINT(1) NOT NULL DEFAULT 1,
  `crr_number` VARCHAR(80) DEFAULT NULL,
  `qualifications` TEXT DEFAULT NULL,
  `training_institute` VARCHAR(200) DEFAULT NULL,
  `certification_level` VARCHAR(120) DEFAULT NULL,
  `years_of_experience` INT DEFAULT NULL,
  `professional_affiliation` VARCHAR(200) DEFAULT NULL,
  `areas_of_expertise` LONGTEXT DEFAULT NULL,
  `interpretation_preferences` LONGTEXT DEFAULT NULL,
  `availability_days` LONGTEXT DEFAULT NULL,
  `video_demo_url` TEXT DEFAULT NULL,
  `document_url` TEXT DEFAULT NULL,
  `honorarium_or_travel_notes` TEXT DEFAULT NULL,
  `assigned_sessions` LONGTEXT DEFAULT NULL,
  `assigned_venue` VARCHAR(200) DEFAULT NULL,
  `duty_times` VARCHAR(150) DEFAULT NULL,
  `assignment_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sli_app_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delegate_profiles` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `gender` VARCHAR(50) DEFAULT NULL,
  `age` INT DEFAULT NULL,
  `city_state` VARCHAR(150) DEFAULT NULL,
  `para_sports_interests` LONGTEXT DEFAULT NULL,
  `outstation_travel_required` TINYINT(1) NOT NULL DEFAULT 0,
  `accommodation_needed` TINYINT(1) NOT NULL DEFAULT 0,
  `food_preference` VARCHAR(80) DEFAULT NULL,
  `dietary_requirements` TEXT DEFAULT NULL,
  `emergency_contact_name` VARCHAR(150) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(40) DEFAULT NULL,
  `guardian_name` VARCHAR(150) DEFAULT NULL,
  `arrival_date` VARCHAR(40) DEFAULT NULL,
  `departure_date` VARCHAR(40) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `del_prof_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delegate_details` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `gender` VARCHAR(50) DEFAULT NULL,
  `age` INT DEFAULT NULL,
  `city_state` VARCHAR(150) DEFAULT NULL,
  `para_sports_interests` LONGTEXT DEFAULT NULL,
  `outstation_travel_required` TINYINT(1) NOT NULL DEFAULT 0,
  `accommodation_needed` TINYINT(1) NOT NULL DEFAULT 0,
  `food_preference` VARCHAR(80) DEFAULT NULL,
  `dietary_requirements` TEXT DEFAULT NULL,
  `emergency_contact_name` VARCHAR(150) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(40) DEFAULT NULL,
  `guardian_name` VARCHAR(150) DEFAULT NULL,
  `arrival_date` VARCHAR(40) DEFAULT NULL,
  `departure_date` VARCHAR(40) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `del_det_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. QR CREDENTIALS & ATTENDANCE / CHECK-IN
-- ============================================================================

CREATE TABLE IF NOT EXISTS `qr_credentials` (
  `id` CHAR(36) NOT NULL,
  `credential_code` VARCHAR(40) NOT NULL,
  `token_hash` VARCHAR(128) NOT NULL,
  `raw_token` VARCHAR(191) DEFAULT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `category_code` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issued_by` CHAR(36) DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `revoked_by` CHAR(36) DEFAULT NULL,
  `revocation_reason` TEXT DEFAULT NULL,
  `replaced_by_credential_id` CHAR(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qr_credentials_code_unique` (`credential_code`),
  UNIQUE KEY `qr_credentials_token_hash_unique` (`token_hash`),
  KEY `qr_credentials_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_entitlements` (
  `id` CHAR(36) NOT NULL,
  `credential_id` CHAR(36) NOT NULL,
  `policy_type` VARCHAR(40) NOT NULL,
  `scope_key` VARCHAR(100) NOT NULL,
  `max_uses` INT NOT NULL DEFAULT 1,
  `used_count` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_entitlements_unique` (`credential_id`, `scope_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkins` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `credential_id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `operator_user_id` CHAR(36) NOT NULL,
  `station_id` CHAR(36) DEFAULT NULL,
  `venue_label` VARCHAR(150) NOT NULL DEFAULT 'Main Gate',
  `entitlement_scope` VARCHAR(100) NOT NULL,
  `checkin_date_ist` VARCHAR(20) NOT NULL,
  `method` VARCHAR(40) NOT NULL DEFAULT 'QR_CAMERA',
  `result` VARCHAR(40) NOT NULL DEFAULT 'CHECKED_IN',
  `notes` TEXT DEFAULT NULL,
  `checked_in_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkins_credential_scope_unique` (`credential_id`, `entitlement_scope`),
  KEY `checkins_reg_idx` (`registration_id`),
  KEY `checkins_operator_idx` (`operator_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `credential_events` (
  `id` CHAR(36) NOT NULL,
  `credential_id` CHAR(36) NOT NULL,
  `actor_user_id` CHAR(36) DEFAULT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `details` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cred_events_cred_idx` (`credential_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. FINANCE, PAYMENTS & COUPONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) DEFAULT NULL,
  `code` VARCHAR(64) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `discount_type` VARCHAR(32) NOT NULL DEFAULT 'PERCENTAGE',
  `discount_value` INT NOT NULL DEFAULT 0,
  `eligible_categories` LONGTEXT DEFAULT NULL,
  `valid_from` DATETIME DEFAULT NULL,
  `valid_until` DATETIME DEFAULT NULL,
  `max_uses` INT DEFAULT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `max_uses_per_user` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_codes` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) DEFAULT NULL,
  `code` VARCHAR(64) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `discount_type` VARCHAR(32) NOT NULL DEFAULT 'PERCENTAGE',
  `discount_value` INT NOT NULL DEFAULT 0,
  `eligible_categories` LONGTEXT DEFAULT NULL,
  `valid_from` DATETIME DEFAULT NULL,
  `valid_until` DATETIME DEFAULT NULL,
  `max_uses` INT DEFAULT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `max_uses_per_user` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_codes_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
  `id` CHAR(36) NOT NULL,
  `coupon_id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `discount_applied_inr` INT NOT NULL DEFAULT 0,
  `redeemed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coupon_redemptions_coupon_idx` (`coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) DEFAULT NULL,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'RAZORPAY',
  `provider_order_id` VARCHAR(120) DEFAULT NULL,
  `provider_payment_id` VARCHAR(120) DEFAULT NULL,
  `amount_inr` INT NOT NULL DEFAULT 0,
  `discount_inr` INT NOT NULL DEFAULT 0,
  `net_amount_inr` INT NOT NULL DEFAULT 0,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` VARCHAR(40) NOT NULL DEFAULT 'PENDING',
  `receipt_number` VARCHAR(80) DEFAULT NULL,
  `raw_payload` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payments_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) DEFAULT NULL,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'RAZORPAY',
  `provider_order_id` VARCHAR(120) DEFAULT NULL,
  `provider_payment_id` VARCHAR(120) DEFAULT NULL,
  `amount_inr` INT NOT NULL DEFAULT 0,
  `discount_inr` INT NOT NULL DEFAULT 0,
  `net_amount_inr` INT NOT NULL DEFAULT 0,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` VARCHAR(40) NOT NULL DEFAULT 'PENDING',
  `receipt_number` VARCHAR(80) DEFAULT NULL,
  `raw_payload` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_orders_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` CHAR(36) NOT NULL,
  `order_id` CHAR(36) NOT NULL,
  `provider_payment_id` VARCHAR(120) NOT NULL,
  `webhook_event_id` VARCHAR(120) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL,
  `amount_inr` INT NOT NULL DEFAULT 0,
  `raw_payload` LONGTEXT DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_tx_provider_id_unique` (`provider_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `refunds` (
  `id` CHAR(36) NOT NULL,
  `transaction_id` CHAR(36) NOT NULL,
  `provider_refund_id` VARCHAR(120) DEFAULT NULL,
  `amount_inr` INT NOT NULL DEFAULT 0,
  `reason` TEXT DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'INITIATED',
  `initiated_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. CMS & CONTENT MANAGEMENT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `cms_pages` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `meta_description` TEXT DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'PUBLISHED',
  `updated_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cms_pages_edition_slug_unique` (`edition_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cms_sections` (
  `id` CHAR(36) NOT NULL,
  `page_id` CHAR(36) NOT NULL,
  `section_key` VARCHAR(100) NOT NULL,
  `title` VARCHAR(250) DEFAULT NULL,
  `subtitle` TEXT DEFAULT NULL,
  `body` TEXT DEFAULT NULL,
  `data` LONGTEXT DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'PUBLISHED',
  `updated_by` CHAR(36) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cms_sections_page_key_unique` (`page_id`, `section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faq_items` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT 'General',
  `question` TEXT NOT NULL,
  `answer` TEXT NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'PUBLISHED',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `faq_items_edition_idx` (`edition_id`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media_assets` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) DEFAULT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `url` TEXT NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
  `size_bytes` INT DEFAULT NULL,
  `alt_text` TEXT NOT NULL,
  `category` VARCHAR(64) NOT NULL DEFAULT 'GALLERY',
  `source_attribution` TEXT DEFAULT NULL,
  `license_info` TEXT DEFAULT NULL,
  `uploaded_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_revisions` (
  `id` CHAR(36) NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` CHAR(36) NOT NULL,
  `version_number` INT NOT NULL,
  `snapshot` LONGTEXT NOT NULL,
  `changed_by` CHAR(36) DEFAULT NULL,
  `change_summary` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` CHAR(36) NOT NULL,
  `edition_id` CHAR(36) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `severity` VARCHAR(32) NOT NULL DEFAULT 'INFO',
  `status` VARCHAR(32) NOT NULL DEFAULT 'PUBLISHED',
  `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `created_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. NOTIFICATIONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id` CHAR(36) NOT NULL,
  `code` VARCHAR(80) NOT NULL,
  `subject` VARCHAR(250) NOT NULL,
  `body_html` TEXT NOT NULL,
  `body_text` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_templates_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_queue` (
  `id` CHAR(36) NOT NULL,
  `template_code` VARCHAR(80) NOT NULL,
  `recipient_user_id` CHAR(36) DEFAULT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(250) NOT NULL,
  `body_html` TEXT DEFAULT NULL,
  `body_text` TEXT DEFAULT NULL,
  `payload` LONGTEXT DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'QUEUED',
  `attempts` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `next_retry_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `last_error` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notification_queue_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_jobs` (
  `id` CHAR(36) NOT NULL,
  `template_code` VARCHAR(80) NOT NULL,
  `recipient_user_id` CHAR(36) DEFAULT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(250) NOT NULL,
  `body_html` TEXT DEFAULT NULL,
  `body_text` TEXT DEFAULT NULL,
  `payload` LONGTEXT DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'QUEUED',
  `attempts` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `next_retry_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `last_error` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notification_jobs_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_delivery_logs` (
  `id` CHAR(36) NOT NULL,
  `job_id` CHAR(36) NOT NULL,
  `provider` VARCHAR(64) NOT NULL,
  `provider_message_id` VARCHAR(200) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL,
  `response_details` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. SECURITY, PRIVACY & AUDIT LOGS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` CHAR(36) NOT NULL,
  `actor_user_id` CHAR(36) DEFAULT NULL,
  `action` VARCHAR(120) NOT NULL,
  `resource_type` VARCHAR(80) NOT NULL,
  `resource_id` VARCHAR(120) DEFAULT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `metadata` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_idx` (`actor_user_id`),
  KEY `audit_logs_action_idx` (`action`),
  KEY `audit_logs_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_actions` (
  `id` CHAR(36) NOT NULL,
  `admin_user_id` CHAR(36) NOT NULL,
  `target_user_id` CHAR(36) DEFAULT NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `before_state` LONGTEXT DEFAULT NULL,
  `after_state` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` CHAR(36) NOT NULL,
  `key` VARCHAR(120) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `description` TEXT DEFAULT NULL,
  `updated_by` CHAR(36) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `privacy_consent_records` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `registration_id` CHAR(36) DEFAULT NULL,
  `consent_type` VARCHAR(80) NOT NULL,
  `consent_version` VARCHAR(40) NOT NULL DEFAULT '1.0',
  `granted` TINYINT(1) NOT NULL DEFAULT 1,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `withdrawn_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `privacy_consent_reg_idx` (`registration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `data_retention_requests` (
  `id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `request_type` VARCHAR(60) NOT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'OPEN',
  `details` TEXT DEFAULT NULL,
  `handled_by` CHAR(36) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
