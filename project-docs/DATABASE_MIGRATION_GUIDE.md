# Database Migration & Operations Guide — Purple Festival Gujarat 2027

**CLI Entrypoint:** `database/migrate.php` (strictly CLI-only; HTTP access returns `403 Forbidden` and `.htaccess` blocks `/database/*`)  
**Safe Reference Seeder:** `database/seed.php` (strictly non-overwriting `INSERT IGNORE` semantics)

---

## 1. Local Development & Testing Commands (Windows XAMPP)

```powershell
# Inspect current migration status, table count, FK count, and checksum verification
C:\xampp\php\php.exe d:\purplefestgujarat\database\migrate.php --status --verify

# Apply all pending migrations in strict numeric order (idempotent)
C:\xampp\php\php.exe d:\purplefestgujarat\database\migrate.php --up

# Apply safe, non-destructive reference seed data (never overwrites organizer edits)
C:\xampp\php\php.exe d:\purplefestgujarat\database\migrate.php --seed

# Combined migrate + seed
C:\xampp\php\php.exe d:\purplefestgujarat\database\migrate.php --up --seed

# Reset isolated testing database ONLY (hard-blocked unless APP_ENV=testing AND DB_NAME matches pfg27_*test*)
$env:PFG_ENV_FILE='d:\purplefestgujarat\tests\tmp\test_env.php'
C:\xampp\php\php.exe d:\purplefestgujarat\database\migrate.php --fresh-test-only
```

---

## 2. Production / Staging Upgrade Runbook (Bluehost cPanel SSH)

> **MANDATORY RULE:** Never run `--fresh-test-only` on staging or production (it is also hard-blocked in code when `APP_ENV !== 'testing'` or `DB_NAME` does not match `pfg27_*test*`). Always take a verified `mysqldump` backup FIRST.

### Step 1 — Pre-Migration Backup & Verification
```bash
TS=$(date +%Y%m%d_%H%M%S)
mkdir -p ~/pfg27_backups
mysqldump --single-transaction --routines --triggers --hex-blob \
  --default-character-set=utf8mb4 \
  -u <cpanel_db_user> -p <cpanel_db_name> > ~/pfg27_backups/pre_phase3_${TS}.sql
sha256sum ~/pfg27_backups/pre_phase3_${TS}.sql
ls -lh ~/pfg27_backups/pre_phase3_${TS}.sql
```

### Step 2 — Check Environment & Migration Plan
```bash
php ~/public_html/scripts/verify-bluehost-env.php
php ~/public_html/database/migrate.php --status
```

### Step 3 — Execute Migrations & Safe Reference Seed
```bash
php ~/public_html/database/migrate.php --up --seed --verify
```

### Step 4 — Inspect Migration Audit Notes (Orphans / Conflicts)
```bash
mysql -u <cpanel_db_user> -p <cpanel_db_name> -e \
  "SELECT version, status, applied_at, execution_ms, notes FROM schema_migrations ORDER BY version;"
```
- If Migration `004` logged any `conflicts` between legacy duplicate rows and canonical rows, review them manually before archiving legacy tables.
- If Migration `005` reported any `foreignKeysSkippedOrphans`, resolve the orphaned parent reference manually and re-run a targeted follow-up migration (Migration `005` **never** auto-deletes orphaned records).

---

## 3. Rollback Strategy

1. **Preferred Full Rollback (Point-in-Time Recovery):** Restore the verified pre-migration `mysqldump` backup (`~/pfg27_backups/pre_phase3_<TS>.sql`) and revert application code to Git tag `phase2-verified-baseline`.
2. **Targeted Reverse DDL Scripts (Schema Only):** Execute in strict reverse order ONLY if rolling back schema changes:
   1. `database/migrations/005_phase3_referential_integrity_and_indexes_rollback.sql`
   2. `database/migrations/003_phase3_schema_evolution_and_separation_rollback.sql`
   3. `database/migrations/002_phase3_atomic_registration_sequences_rollback.sql`
   4. Remove rolled-back rows from `schema_migrations`.
3. **Never Modify Already-Applied Migrations:** Once a migration has been applied to any shared environment, treat its file as immutable. Always create a new numbered migration (`006_...`) for subsequent changes.

