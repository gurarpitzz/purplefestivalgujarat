# Phase 3 Implementation Report — Purple Festival Gujarat 2027

**Date:** `2026-11-26`  
**Baseline Recovery Tag:** `phase2-verified-baseline` (`eb92e67`)  
**Verification Summary:** **`45 / 45` PHP/MariaDB tests PASS (`0` failed)** + **`26 / 26` real XAMPP Apache `2.4.58` checks PASS (`0` failed)** = **`71 / 71` PASS**; **`59` PHP files linted with `0` syntax errors**.

---

## 1. Summary of Files Created & Modified

### New Infrastructure, Migrations & Test Files Created (`13` files)
1. `includes/migrations.php` — Versioned migration engine (`schema_migrations` table, SHA-256 checksum drift/tamper detection, MySQL `GET_LOCK` concurrency guard, MySQL 8.0 + MariaDB 10.4 portable `information_schema` helpers, orphan-safe non-cascading FK installer).
2. `includes/registration-id.php` — Standalone atomic Registration ID allocator (`allocateNextRegistrationCode`, `formatRegistrationCode`, `extractRegistrationSequenceNumber`, `scanLegacyRegistrationSequence`, `syncRegistrationSequenceFloor`), completely decoupled from `includes/qr-service.php`.
3. `database/migrate.php` — Controlled CLI-only migration & seeding runner (`--status`, `--up`, `--seed`, `--verify`, `--fresh-test-only` hard-gated to `APP_ENV=testing` + `pfg27_*test*`).
4. `database/seed.php` — Safe, idempotent, non-overwriting CLI reference seeder (`INSERT IGNORE`, tags `ATTENDEE`/`SLI`/`VOLUNTEER` as `PRIMARY` and 5 legacy codes as `LEGACY`, D-01 compliant templates/FAQs, preserves all organizer edits).
5. `database/migrations/000_baseline_schema.sql` — Immutable 57-table baseline schema migration.
6. `database/migrations/002_phase3_atomic_registration_sequences.sql` (+ `002_..._rollback.sql`) — Dedicated InnoDB `AUTO_INCREMENT` `registration_number_sequences` table.
7. `database/migrations/003_phase3_schema_evolution_and_separation.php` (+ `003_..._rollback.sql`) — Adds `users.username` + unique index, `users.account_status`, `registration_categories.category_tier`, `registrations.assignment_status`, `registrations.idempotency_key` + unique index; creates `attendance_records` & `attendance_correction_logs`; upgrades `notification_queue` with Phase 4 columns & indexes.
8. `database/migrations/004_phase3_consolidate_duplicates_and_historical_encryption.php` — Non-destructive 6-pair duplicate consolidation, legacy sequence floor sync, historical AES-256-GCM encryption backfill (0 double-encryption), legacy `users.mfa_secret` migration, and legacy token/secret redaction in `notification_queue` & `registration_responses`.
9. `database/migrations/005_phase3_referential_integrity_and_indexes.php` (+ `005_..._rollback.sql`) — Orphan-checked installation of `53` non-cascading `RESTRICT`/`SET NULL` FKs (`62` total FKs) and `14` performance indexes.
10. `tests/phase3_database_test.php` & `tests/phase3_concurrency_worker.php` — 13 comprehensive Phase 3 automated tests including real `10`/`25`/`50`/`100` parallel-process concurrency benchmarks, `1,000`-row Gujarati/Hindi `EXPLAIN` plan checks, and real `mysqldump` backup/restore verification.

### Existing Application & Test Files Updated (`11` files)
1. `includes/database.php` — **Removed 100% of per-request DDL** (`ensureUsersUsernameColumn()`, `ensurePhase2SecurityTables()`, auto-`CREATE TABLE`); added read-only `pfgValidateSchemaReady()`; updated `queueNotification()` for canonical Phase 3K `notification_queue` columns.
2. `includes/qr-service.php` — Replaced broken `SELECT COUNT(*)+1` in `generateUniqueRegistrationCode()` with a deprecated delegate to `allocateNextRegistrationCode()` in `includes/registration-id.php`.
3. `includes/mailer.php` — Persists `event_type`, `channel`, `provider`, `provider_message_id`, `idempotency_key`, `provider_accepted_at`, `failed_at` directly to upgraded `notification_queue` with honest statuses (`QUEUED`, `CAPTURED_LOCAL`, `PROVIDER_ACCEPTED`, `FAILED`) and idempotency deduplication.
4. `api/registrations/index.php` — Uses `includes/registration-id.php`; wraps user creation + `PARTICIPANT` role + registration + sanitized response + role details + consents + canonical disclosures/accommodations + coupon redemption in **one single atomic PDO transaction** (`FND-21`); supports `Idempotency-Key` replay; eliminated all dual-writes to `registration_accommodations`, `registration_disabilities`, `delegate_profiles`, `coupon_codes`, `notification_jobs`.
5. `api/admin/overview.php` — Consolidated reads to canonical `accommodation_requests` & `payment_orders`; added `INNER JOIN registrations WHERE r.deleted_at IS NULL` across accommodations, check-ins, and payments (`FND-39`).
6. `api/admin/accommodations.php` & `api/account/accommodations.php` — Consolidated reads/writes strictly to canonical `accommodation_requests` (stopped dual-writing to `registration_accommodations`).
7. `api/admin/coupons.php` — Consolidated reads/writes strictly to canonical `coupons` (stopped dual-writing to `coupon_codes`).
8. `api/admin/registrations.php` & `dashboard/index.php` — Consolidated reads strictly to canonical `delegate_details`, `payment_orders`, `accommodation_requests`, `participant_disability_disclosures`.
9. `scripts/verify-bluehost-env.php` — Added live `schema_migrations` applied/pending/checksum verification check.
10. `tests/phase1_containment_test.php` & `tests/run.php` — Updated `T1-02` to invoke controlled `pfgRunMigrations()` + `pfgRunSafeReferenceSeed()` instead of removed per-request DDL (preserving zero-DDL integrity), and wired `phase3_database_test.php` into the master runner.

---

## 2. Issues Found & Resolved During Implementation Testing

1. **Column Name Alignment (`notification_queue`, `notification_delivery_logs`, `payment_orders`):** Initial run of Migration `003` caught `next_retry_at` (not `next_attempt_at`), `notification_delivery_logs.job_id` (not `queue_id`), and `payment_orders.raw_payload`/`provider_payment_id`/`receipt_number` — fixed immediately across migrations `003`–`005`.
2. **`programme_items.venue_name` in `database/seed.php`:** Caught during `T1-02` (`Unknown column 'venue_label'`); corrected to `venue_name`.
3. **AST Comment Stripping in `T3-02`:** Docblock comment mentioning `CREATE TABLE` matched a raw regex check; upgraded `T3-02` to tokenize PHP via `token_get_all()` and assert zero executable DDL outside comments.
4. **MySQL Buffered Query Cursor on `ANALYZE TABLE` (`T3-12`):** MariaDB returns result rows from `ANALYZE TABLE`; updated `T3-12` to consume `fetchAll()` + `closeCursor()` before issuing subsequent `EXPLAIN` queries.

