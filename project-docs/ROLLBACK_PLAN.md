# Rollback & Emergency Recovery Plan — Purple Festival Gujarat 2027

**Scope:** Code deployment rollback, database schema & data recovery (`000`–`011`), and outbound notification emergency pause on Bluehost Shared / cPanel hosting.  
**Verification Status:** Local `mysqldump` backup & full restore verified on `MariaDB 10.4.32` (`T3-13`, [BACKUP_RESTORE_VERIFICATION.md](BACKUP_RESTORE_VERIFICATION.md)); `MAIL_DRIVER=disabled` queue pause verified (`T4-07`).

---

## 1. Emergency Switch 1 — Instant Outbound Email Pause (< 30 Seconds, Zero Data Loss)

If an email template copy issue, Resend domain/rate-limit error, or unexpected notification broadcast occurs in production:

1. Open `/home1/<cpanel_user>/.pfg27_env.php` (outside `public_html`) and change:
   ```php
   'MAIL_DRIVER' => 'disabled',
   ```
2. *(Optional)* Comment out or pause the notification worker Cron Job in **Bluehost cPanel -> Cron Jobs**.
3. **System Behavior During Pause (`T4-07` verified):**
   - `scripts/process-notification-queue.php` immediately exits as a safe no-op (`claimed: 0, processed: 0`).
   - All participant signups, registrations, status reviews, duty assignments, and accommodation updates continue committing normally to MySQL and writing `QUEUED` rows to `notification_queue` plus `in_app_notifications`.
   - Once the issue is resolved and `'MAIL_DRIVER' => 'resend'` is restored, the cron worker automatically drains the queued backlog in priority/FIFO order without duplicate sends.

---

## 2. Git Tag Checkpoints & Application Code Rollback

### 2.1 Local Verified Git Checkpoints
| Checkpoint Tag / Commit | Phase Baseline | Verified State |
|---|---|---|
| `phase1-verified-baseline` | Phase 1 | Environment isolation, `.htaccess` containment, `verify-bluehost-env.php` (`11/11` PHP + `26/26` Apache PASS) |
| `phase2-verified-baseline` | Phase 2 | P0 security controls, session CSRF, D-20 TOTP MFA, AES-256-GCM encryption, `D-01` QR retirement (`32/32` PHP + `26/26` Apache PASS) |
| `phase3-verified-baseline` (`140f2a0`) | Phase 3 | Versioned migration engine (`000`–`005`), atomic `PFG-2027-NNNNNN` numbering, 62 tables, 62 FKs (`45/45` PHP + `26/26` Apache PASS) |
| `phase4-verified-baseline` (`6e2061b`) | Phase 4 | Centralized transactional outbox, 29 WCAG templates, CLI cron worker, Svix webhooks (`53/53` PHP + `29/29` Apache PASS) |
| `swarm/phases-5-18` (`HEAD`) | Phases 5–18 | Full participant auth, 3-category registration & state machines, RBAC permission profiles, manual `/rgst` desk, dashboards, admin portal, programme/venues/duty assignments, WCAG 2.2 AA fixes, independent security review remediations (`000`–`011`, `70` tables, `79` FKs) |

### 2.2 Fast Application Code Rollback on Bluehost (`public_html`)
Before every deployment on Bluehost, archive the current live `public_html` directory outside webroot:
```bash
tar -czf $HOME/pfg27_releases/public_html_pre_deploy_$(date +%Y%m%d_%H%M%S).tar.gz -C $HOME/public_html .
chmod 600 $HOME/pfg27_releases/public_html_pre_deploy_*.tar.gz
```
To roll back application code immediately:
1. Set `'MAIL_DRIVER' => 'disabled'` in `/home1/<cpanel_user>/.pfg27_env.php`.
2. Restore the previous release archive into `public_html` (or check out the prior verified Git tag and run `php scripts/build-release-package.php`).
3. Verify `PFG_REQUIRED_SCHEMA_VERSION` in `includes/migrations.php` matches the database's highest applied migration in `schema_migrations`.

---

## 3. Database Migration Rollback Capabilities & Limitations (`000`–`011`)

Because all Phase 3–12 migrations (`001`–`011`) were engineered to be **strictly additive and non-destructive** (zero dropped tables, zero dropped columns, zero deleted historical rows), schema rollback has two distinct operating modes depending on whether new production participant data has been written after cutover:

### 3.1 Migration-by-Migration Rollback Inventory

| Migration Version | Forward File | Paired Rollback Script | Rollback Characteristics & Limitations |
|---|---|---|---|
| `000_baseline_schema` | `000_baseline_schema.sql` | Full pre-migration DB dump restore | 57-table baseline schema (`CREATE TABLE IF NOT EXISTS`). |
| `001_phase2_security_controls` | `001_phase2_security_controls.sql` | `001_phase2_security_controls_rollback.sql` | Drops ephemeral `security_rate_limits` table and deletes `001` from `schema_migrations`. Safe anytime (no participant data lost). |
| `002_phase3_atomic_registration_sequences` | `002_phase3_atomic_registration_sequences.sql` | `002_phase3_atomic_registration_sequences_rollback.sql` | Drops `registration_number_sequences`. **Limitation:** Do not run after new registrations allocate sequential codes unless restoring full DB dump. |
| `003_phase3_schema_evolution_and_separation` | `003_phase3_schema_evolution_and_separation.php` | `003_phase3_schema_evolution_and_separation_rollback.sql` | Drops `attendance_correction_logs` & `attendance_records` and removes Phase 3 additive columns. **Limitation:** Do not run after live `/rgst` attendance marking begins; use full DB backup instead. |
| `004_phase3_consolidate_duplicates_and_historical_encryption` | `004_phase3_consolidate_duplicates_and_historical_encryption.php` | **Full pre-migration DB dump restore** | One-way data consolidation (6 legacy duplicate tables -> canonical tables) and AES-256-GCM encryption of plaintext disclosures/MFA secrets. Legacy tables are preserved intact, but decrypting back to plaintext is intentionally unsupported for security. |
| `005_phase3_referential_integrity_and_indexes` | `005_phase3_referential_integrity_and_indexes.php` | `005_phase3_referential_integrity_and_indexes_rollback.sql` | Drops the 53 Phase 3 Foreign Keys and 14 performance indexes cleanly without touching table rows. |
| `006_phase4_notifications_and_delivery_tracking` | `006_phase4_notifications_and_delivery_tracking.php` | `006_phase4_notifications_and_delivery_tracking_rollback.sql` | Drops `in_app_notifications` FKs/table and removes Phase 4 queue lease/webhook columns. |
| `007_phase5_participant_auth_and_verification` | `007_phase5_participant_auth_and_verification.php` | `007_phase5_participant_auth_and_verification_rollback.sql` | Drops `legacy_registration_claims` and `email_verification_tokens`, and drops the 8 additive profile columns on `users`. |
| `008_phase6_7_three_category_registration_and_lifecycle` | `008_phase6_7_three_category_registration_and_lifecycle.php` | `008_phase6_7_three_category_registration_and_lifecycle_rollback.sql` | Drops `registration_drafts`, `registration_status_history`, `attendee_details`, removes additive Phase 6 columns, and sets `registration_categories.is_active = 1 WHERE category_tier = 'LEGACY'`. |
| `009_phase8_9_custom_permission_profiles_and_attendance` | `009_phase8_9_custom_permission_profiles_and_attendance.php` | `009_phase8_9_custom_permission_profiles_and_attendance_rollback.sql` | Drops `fk_users_perm_profile`, `users.permission_profile_id`, `admin_permission_profiles`, and `idx_attendance_edition_date_status`. |
| `010_phase12_programme_venues_and_duty_assignments` | `010_phase12_programme_venues_and_duty_assignments.php` | `010_phase12_programme_venues_and_duty_assignments_rollback.sql` | Drops `duty_assignments` FKs/table and removes additive columns on `venues` and `festival_sessions`. |
| `011_phase8_profile_permissions_alignment` | `011_phase8_profile_permissions_alignment.php` | `011_phase8_profile_permissions_alignment_rollback.sql` | Removes `programme.manage` and `communications.manage` from `programme_coordinator`/`communications_manager` JSON arrays, `role_permissions`, and `permissions`, and deletes `011` from `schema_migrations`. |

> [!WARNING]
> **Critical Checksum Invariant:** Never edit an already-applied migration file in `database/migrations/` to roll back or alter a column. `pfgGetMigrationStatus()` verifies the SHA-256 checksum of every applied migration and will immediately abort if any applied file is modified. Always apply either the paired `*_rollback.sql` script (in reverse numeric order `011` -> `007`) or restore the verified pre-migration `mysqldump` snapshot.

---

## 4. Full Database Snapshot Restore Procedure (`mysqldump` -> `mysql`)

When rolling back during a deployment window before opening public traffic:

1. **Pause Traffic & Queue Worker:**
   - Set `'MAIL_DRIVER' => 'disabled'` in `/home1/<cpanel_user>/.pfg27_env.php`.
   - Pause the cPanel Cron Job.
2. **Verify Backup File Checksum:**
   ```bash
   sha256sum $HOME/pfg27_backups/pre_deploy_<timestamp>.sql
   ```
3. **Restore Snapshot into Target Database:**
   ```bash
   mysql --host=localhost --user=<db_user> -p \
     --default-character-set=utf8mb4 \
     <db_name> < $HOME/pfg27_backups/pre_deploy_<timestamp>.sql
   ```
4. **Verify Post-Restore Integrity:**
   ```bash
   /usr/local/bin/ea-php82 database/migrate.php --status
   /usr/local/bin/ea-php82 scripts/verify-bluehost-env.php
   ```
5. **What If Live Participant Registrations Occurred After Cutover?**
   - Because migrations `007`–`011` are 100% additive, **do not drop or overwrite a live database** once real participants have submitted registrations after launch.
   - Instead, export any newly created rows from `users`, `registrations`, `attendee_details`, `volunteer_applications`, `sli_applications`, `participant_disability_disclosures`, `accommodation_requests`, `privacy_consent_records`, and `attendance_records` to a timestamped SQL backup before performing any surgical code or schema rollback.
