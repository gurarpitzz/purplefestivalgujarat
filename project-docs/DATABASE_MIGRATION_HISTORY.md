# Database Migration History — Purple Festival Gujarat 2027

**Tracking Table:** `schema_migrations` (`InnoDB`, `utf8mb4_unicode_ci`)  
**Advisory Lock:** `GET_LOCK('pfg27_schema_migration_<dbhash>', 15)`  
**Integrity Guard:** SHA-256 checksum verified on every run; modifying any already-applied migration aborts immediately (`T3-01`).

---

## Ordered Applied Migrations Registry

| Order | Version | Filename | Type | Rollback Script | SHA-256 Checksum (Verified) | Purpose & Summary |
|---:|---|---|---|---|---|---|
| 0 | `000_baseline_schema` | `000_baseline_schema.sql` | SQL | Full backup restore | `72df10e8214749f76665b9cc07ad9ee75415b5cf00e821eb909657641b7c0ed9` | Immutable 57-table baseline schema (`CREATE TABLE IF NOT EXISTS`, `InnoDB`, `utf8mb4_unicode_ci`). Safely adopted on both fresh and pre-Phase-3 legacy DBs. |
| 1 | `001_phase2_security_controls` | `001_phase2_security_controls.sql` | SQL | `001_phase2_security_controls_rollback.sql` | `41c1a0819e08cade4f3c257e277d59c35c8a2e9d497e7bb2834f8a4fc3123c23` | Creates persistent DB-backed `security_rate_limits` table (`FND-26`). |
| 2 | `002_phase3_atomic_registration_sequences` | `002_phase3_atomic_registration_sequences.sql` | SQL | `002_phase3_atomic_registration_sequences_rollback.sql` | `730ab3ff030d17c899854e3727cfa5d506eb4eb4cddacdc0e76829d95e455cbf` | Creates dedicated InnoDB `AUTO_INCREMENT` `registration_number_sequences` table decoupled from QR (`FND-21`, `D-01`). |
| 3 | `003_phase3_schema_evolution_and_separation` | `003_phase3_schema_evolution_and_separation.php` | PHP | `003_phase3_schema_evolution_and_separation_rollback.sql` | `8e93d3b9a431f63a60e2662cc1cfae219985e57c94fbc40de3b3cdac45c37c11` | Adds `users.username` + unique index, `users.account_status`, `registration_categories.category_tier`, `registrations.assignment_status`, `registrations.idempotency_key` + unique index; creates `attendance_records` & `attendance_correction_logs`; adds `notification_queue` Phase 4 columns & indexes (`FND-19`, `FND-27`, `FND-29`). |
| 4 | `004_phase3_consolidate_duplicates_and_historical_encryption` | `004_phase3_consolidate_duplicates_and_historical_encryption.php` | PHP | Restore pre-migration backup (data migration is non-destructive & idempotent) | `0e299d6684f654d817b4160c80b3f0532c509108ace85d391ae61efc13881a35` | Consolidates all 6 legacy duplicate pairs non-destructively (0 legacy tables dropped, conflicts logged); syncs atomic sequence floor above legacy max; encrypts legacy plaintext disability/accommodation free-text (`enc:v1:` AES-256-GCM, 0 double-encryption); migrates `users.mfa_secret` -> `mfa_secret_encrypted`; redacts legacy secrets from `notification_queue` & `registration_responses` (`FND-11`, `FND-21`, `FND-22`, `FND-44`). |
| 5 | `005_phase3_referential_integrity_and_indexes` | `005_phase3_referential_integrity_and_indexes.php` | PHP | `005_phase3_referential_integrity_and_indexes_rollback.sql` | `f852e91945bd761d686ce10203905859f29f48f573eec7891b892c9356bc77af` | Performs upfront orphan check before adding `53` non-cascading `RESTRICT`/`SET NULL` Foreign Keys (`62` total FKs) and `14` composite/prefix performance indexes for 5,000+ scale (`FND-29`). |

---

## Verified Local Execution History (`2026-11-26`)

- **Fresh Install (`T1-02`, `pfg27_test`):** `6/6` migrations applied cleanly -> `62` tables, `62` FKs, `0` errors.
- **Legacy Pre-Phase-3 Upgrade (`T3-03`, `pfg27_legacy_upgrade_test`):** `6/6` migrations applied cleanly over populated 57-table legacy schema -> `62` tables, `62` FKs, all `6` legacy pairs consolidated, legacy `PFG-2027-000099-A1B2` preserved and next allocated `PFG-2027-000100`, all legacy plaintext sensitive fields encrypted & legacy tokens scrubbed.
- **Idempotency & Tamper Check (`T3-01`):** Repeat run applied `0` new migrations; tampering with a test migration file aborted loudly with checksum mismatch error.
- **Bluehost Staging / Production:** `NOT YET APPLIED` (strictly awaits authorized maintenance window and verified pre-migration backup).

