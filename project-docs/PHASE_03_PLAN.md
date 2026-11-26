# Phase 3 Plan — Database Architecture, Versioned Migrations, Data Integrity and Atomic Registration Numbering

**Project:** Purple Festival Gujarat 2027  
**Repository:** `d:\purplefestgujarat`  
**Stack (Locked):** Native PHP 8 (`8.2.12` local XAMPP) + MySQL 8.0 / MariaDB `10.4.32` (InnoDB `utf8mb4_unicode_ci`) + PDO + Vanilla JS + HTML5 + CSS3 + Apache 2.4 / Bluehost cPanel  
**Recovery Checkpoint:** Local Git tag `phase2-verified-baseline` at commit `eb92e67` (Phase 2 verified baseline: `32/32` PHP/MariaDB + `26/26` Apache `2.4.58` checks passing).

---

## 1. Objectives & Targeted Audit Findings

Phase 3 replaces ad-hoc per-request DDL, non-atomic `COUNT(*)+1` registration numbering, duplicated write paths, and missing foreign keys with a disciplined, Bluehost-portable relational architecture:

| Audit Finding | Phase 3 Objective | Target Outcome |
|---|---|---|
| **FND-29** | Remove per-request `ALTER TABLE`/`CREATE TABLE` DDL; enforce FKs; reconcile 6 duplicate table pairs | Versioned CLI migration runner (`schema_migrations`), `0` per-request DDL in `includes/database.php`, `62` non-cascading FKs, `6` legacy duplicates consolidated & archived |
| **FND-21** | Eliminate `COUNT(*)+1` numbering race, non-atomic user creation, and distinct-UUID dual writes | Atomic InnoDB `AUTO_INCREMENT` sequence (`includes/registration-id.php`), single-transaction registration writes, canonical-only persistence |
| **FND-11** | Protect legacy plaintext disability & accommodation disclosures without double-encryption | Idempotent migration `004` encrypts legacy plaintext free-text (`enc:v1:` AES-256-GCM) & scrubs legacy `registration_responses.answers` |
| **FND-14** | Guarantee active `gujarat-2027` edition via controlled seeding | Safe, idempotent CLI seeder (`database/seed.php`) using `INSERT IGNORE` |
| **FND-19** | Resolve `notification_queue` column mismatch (`provider`, `provider_message_id`) & prepare Phase 4 worker schema | Added `event_type`, `channel`, `provider`, `provider_message_id`, `idempotency_key` (UNIQUE), `provider_accepted_at`, `delivered_at`, `failed_at` |
| **FND-22** | Consolidate duplicate `coupons` / `coupon_codes` and eliminate silent write error swallowing | Single canonical `coupons` table read/write path (deferred business decision `D-05` preserved) |
| **FND-27** | Decouple attendance data model from `qr_credentials` | New `attendance_records` + `attendance_correction_logs` keyed on `(edition_id, registration_id, attendance_date_ist, attendance_scope)` |
| **FND-39** | Exclude soft-deleted registrations from admin overview metrics | `INNER JOIN registrations WHERE deleted_at IS NULL` in `api/admin/overview.php` |
| **FND-44** | Reconcile legacy/unused duplicate tables cleanly without destructive drops | All 6 legacy duplicate tables retained read-only/archived; application code consolidated onto canonical tables |

---

## 2. Sub-Phase Execution Sequence (`3A`–`3N`)

1. **3A — Schema & Live Baseline Inspection:** Re-verify Phase 2 baseline (`32/32` + `26/26`), tag `phase2-verified-baseline`, inventory all `58` baseline tables and `9` existing FKs.
2. **3B — Versioned Migration System & Zero Per-Request DDL:** Implement `includes/migrations.php` (`schema_migrations`, SHA-256 tamper detection, `GET_LOCK`, MySQL 8.0 + MariaDB 10.4 portable `information_schema` helpers), CLI `database/migrate.php`, and strip all DDL from `includes/database.php`.
3. **3C — Safe Idempotent Seeding:** Build `database/seed.php` (`INSERT IGNORE` only, tagging `ATTENDEE`/`SLI`/`VOLUNTEER` as `PRIMARY` and legacy codes as `LEGACY`, D-01 compliant copy, preserving all organizer edits).
4. **3D — Atomic Registration Numbering:** Create `registration_number_sequences` (Migration `002`) and standalone `includes/registration-id.php` decoupled from `includes/qr-service.php`.
5. **3E — Duplicate Table Reconciliation:** Consolidate all 6 legacy duplicate pairs non-destructively (Migration `004`), flag conflicts without silent overwrite, stop all dual-writes across PHP APIs.
6. **3F — Referential Integrity:** Run orphan checks before adding `53` new non-cascading `RESTRICT` / `SET NULL` FKs (Migration `005`, bringing total enforced FKs from `9` to `62`).
7. **3G — Architectural Concept Separation & Category Taxonomy:** Separate user account state (`users.account_status`), primary vs legacy categories (`registration_categories.category_tier`), approval status (`registrations.status`), assignment status (`registrations.assignment_status`), and physical attendance (`attendance_records`).
8. **3H — Historical Sensitive Data Protection:** Backfill AES-256-GCM encryption on legacy disability/accommodation free-text, migrate legacy `users.mfa_secret`, and redact legacy tokens/secrets from `notification_queue` and `registration_responses` (Migration `004`).
9. **3I — Transactional Atomicity & Idempotency:** Wrap user creation, role assignment, registration, consents, and canonical child records inside one PDO transaction in `api/registrations/index.php` with `Idempotency-Key` replay safety.
10. **3J — Manual Attendance Foundation:** Create `attendance_records` and `attendance_correction_logs` (Migration `003`) with zero `qr_credentials` dependency.
11. **3K — Notification Queue Schema Upgrade:** Add Phase 4 provider/idempotency/lifecycle columns to `notification_queue` and align `includes/mailer.php`.
12. **3L — Performance Indexing & 5,000+ Scale Verification:** Add `14` targeted composite/prefix indexes (Migration `005`) and verify via `EXPLAIN` on `1,000+` synthetic rows including Gujarati & Hindi UTF-8.
13. **3M — Comprehensive Automated Testing, Concurrency Benchmarks & Backup/Restore:** Execute `tests/phase3_database_test.php` (`10`/`25`/`50`/`100` parallel PHP concurrency workers, `mysqldump`/restore verification) plus full Phase 1, Phase 2, and real XAMPP Apache `2.4.58` regression suites.
14. **3N — Documentation & Deliverables:** Produce all 18 Phase 3 documentation/evidence artefacts and update the 4 living project governance documents.

---

## 3. Explicit Scope Boundaries (Preserved for Subsequent Phases)

- **Not in Phase 3:** Resend cPanel cron worker & webhook processing (Phase 4), participant signup/login/claim UI (Phase 5), 3-category `/apply` wizard rebuild (Phase 6), full application review state machine (Phase 7), Superadmin custom role profile UI (Phase 8), manual `/rgst` checkbox desk UI/API (Phase 9), full participant dashboard rebuild (Phase 10), payment gateways/SMS/WhatsApp/certificates (Phase 16), and live Bluehost deployment (Phase 18 / separate authorization).

