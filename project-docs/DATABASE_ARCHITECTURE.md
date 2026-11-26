# Database Architecture — Purple Festival Gujarat 2027

**Engine Standard:** InnoDB (`utf8mb4`, `utf8mb4_unicode_ci`), Session Timezone `+05:30` (IST / `Asia/Kolkata`)  
**Compatibility Target:** MySQL 8.0 and MariaDB 10.4+ (Bluehost cPanel Shared Hosting & Local XAMPP `MariaDB 10.4.32`)  
**Total Schema Tables (Post-Phase 3):** `62` (`57` baseline core + `1` Phase 2 `security_rate_limits` + `4` Phase 3 infrastructure tables: `schema_migrations`, `registration_number_sequences`, `attendance_records`, `attendance_correction_logs`)  
**Total Enforced Foreign Keys:** `62` (`9` baseline + `53` added in Phase 3 Migration `005`; all Phase 3 FKs use non-cascading `ON DELETE RESTRICT` or `ON DELETE SET NULL`)

---

## 1. Architectural Separation of Concerns (11 Independent Concepts)

Phase 3 formally separates 11 domain concepts so operational workflows never conflate identity, eligibility, assignment, or physical attendance:

| # | Domain Concept | Authoritative Table(s) | Key State / Separating Columns |
|---|---|---|---|
| 1 | **User Account** | `users`, `auth_accounts`, `sessions`, `password_reset_tokens` | `users.account_status` (`ACTIVE`, `SUSPENDED`, `ARCHIVED`), `users.is_active`, `users.mfa_enabled`, `users.mfa_secret_encrypted` |
| 2 | **Participant Identity** | `users` + `registrations` (`full_name`, `email`, `phone`, `country`) | Distinct from staff credentials; unauthenticated attachment to existing `users.email` blocked (`FND-07`) |
| 3 | **Festival Edition** | `festival_editions`, `venues`, `checkin_stations`, `festival_sessions`, `programme_items` | `festival_editions.slug = 'gujarat-2027'`, `attendance_policy = 'ONCE_PER_DAY'` |
| 4 | **Registration / Application** | `registrations`, `registration_responses`, `registration_number_sequences`, `volunteer_applications`, `sli_applications`, `delegate_details` | Atomic monotonic `registration_code` (`PFG-2027-000001`), `idempotency_key`, soft-delete `deleted_at` |
| 5 | **Registration Category** | `registration_categories` | `category_tier`: `PRIMARY` (`ATTENDEE`, `SLI`, `VOLUNTEER`) vs preserved `LEGACY` (`DELEGATE`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`) |
| 6 | **Application Approval Status** | `registrations.status`, `registrations.reviewer_id`, `registrations.reviewed_at` | `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `ADDITIONAL_INFO_REQUIRED`, `CONFIRMED`, `APPROVED`, `WAITLISTED`, `REJECTED`, `WITHDRAWN`, `CANCELLED` |
| 7 | **Accessibility Requests & Disclosures** | `disability_categories`, `accommodation_types`, `participant_disability_disclosures`, `accommodation_requests`, `accommodation_assignments` | Optional disclosure (never blocks category); AES-256-GCM (`enc:v1:`) encrypted free-text; `SENSITIVE_DATA_READ` audited |
| 8 | **Administrative Assignments** | `registrations.assignment_status`, `volunteer_applications` (`assigned_*`), `sli_applications` (`assigned_*`) | `assignment_status` (`UNASSIGNED`, `ASSIGNED`, `COMPLETED`) tracked independently from approval `status` |
| 9 | **Physical Attendance** | `attendance_records`, `attendance_correction_logs` (legacy `checkins`/`qr_credentials` preserved read-only) | Keyed on `(edition_id, registration_id, attendance_date_ist, attendance_scope)` — **zero `qr_credentials` dependency** (`D-01`) |
| 10 | **Notifications & Delivery** | `notification_templates`, `notification_queue`, `notification_delivery_logs` | `event_type`, `channel`, `provider`, `provider_message_id`, `idempotency_key` (UNIQUE), honest statuses (`QUEUED`, `CAPTURED_LOCAL`, `PROVIDER_ACCEPTED`, `DELIVERED`, `FAILED`, `BOUNCED`) |
| 11 | **Administrative Permissions & Audit** | `roles`, `permissions`, `role_permissions`, `user_roles`, `audit_logs`, `admin_actions`, `security_rate_limits` | Strictly 3 admin roles (`SUPERADMIN`, `ADMIN`, `RGST`) + `PARTICIPANT`; immutable audit history |

---

## 2. Core Data-Layer Rules Enforced in Phase 3

1. **Zero Per-Request DDL (`FND-29`):** `includes/database.php` (`getDb()`) executes **zero** `CREATE TABLE`, `ALTER TABLE`, or `DROP TABLE` statements. It runs a lightweight read-only check (`pfgValidateSchemaReady()`) verifying `schema_migrations` has applied rows and fails cleanly if the database is unmigrated.
2. **Versioned Migrations (`includes/migrations.php`, `database/migrate.php`):** All schema evolution runs via ordered, SHA-256 checksummed migrations under a MySQL advisory lock (`GET_LOCK`). Modifying an already-applied migration file causes an immediate checksum-mismatch abort.
3. **Single Canonical Write Path:** All 6 legacy duplicate tables (`registration_accommodations`, `registration_disabilities`, `delegate_profiles`, `coupon_codes`, `payments`, `notification_jobs`) are archived read-only (never dropped in Phase 3); PHP application code reads and writes exclusively to the 6 canonical counterparts.
4. **Non-Cascading Referential Integrity:** All 53 Phase 3 foreign keys use `ON DELETE RESTRICT` (for required parents) or `ON DELETE SET NULL` (for nullable reviewer/actor/station references) — preventing accidental bulk loss of participant, consent, attendance, financial, or audit data.
5. **Atomic Registration Numbering:** `includes/registration-id.php` allocates sequential `PFG-2027-000001` IDs via InnoDB `AUTO_INCREMENT` on `registration_number_sequences`, completely decoupled from `includes/qr-service.php` and `qr_credentials`.
6. **Transactional Registration Atomicity (`FND-21`):** `api/registrations/index.php` wraps user creation, `PARTICIPANT` role assignment, registration insertion, sanitized response snapshot, category detail record, DPDP consent records, encrypted disclosures/accommodations, and coupon redemption inside a single PDO transaction, with `Idempotency-Key` replay safety and post-commit email dispatch.

