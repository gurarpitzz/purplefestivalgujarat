# Bluehost Database Compatibility Matrix (MySQL 8.0 & MariaDB 10.4+)

**Purpose:** Document every compatibility rule enforced across Phase 3 migrations, atomic numbering, and runtime PDO code so the codebase runs identically on **local XAMPP (`MariaDB 10.4.32`)** AND **Bluehost shared hosting (`MySQL 8.0` or `MariaDB 10.3–10.6+`)** without engine-specific breakage.

---

## 1. Compatibility Safeguards Enforced in Phase 3

| Database Feature / Pitfall | MariaDB `10.4.32` Behavior | MySQL `8.0` Behavior | Phase 3 Portable Design (Works on BOTH) | Verification Status |
|---|---|---|---|---|
| **Conditional `ALTER TABLE ADD COLUMN` / `ADD INDEX`** | Supports `ADD COLUMN IF NOT EXISTS` | **Syntax Error!** MySQL 8.0 rejects `IF NOT EXISTS` on `ADD COLUMN`/`ADD INDEX` | **Never uses `ADD COLUMN IF NOT EXISTS`.** All conditional columns, indexes, and FKs check `information_schema.COLUMNS`, `STATISTICS`, and `TABLE_CONSTRAINTS` via `includes/migrations.php` (`pfgDbEnsureColumn`, `pfgDbEnsureIndex`, `pfgDbEnsureForeignKey`). | **Verified locally; MySQL 8.0 syntax compliant** |
| **`AUTO_INCREMENT` Persistence Across Restarts** | Recomputes `MAX(id)+1` from stored rows on restart | Persists counter in redo log | Every allocated sequence row (and legacy floor marker) stays permanently in `registration_number_sequences` — **both engines preserve the high-water mark across restarts & restores**. | **Verified (`T3-05`, `T3-13`)** |
| **Migration Concurrency Lock** | `GET_LOCK(name, timeout)` supported | `GET_LOCK(name, timeout)` supported (max 64-char name) | Lock name `pfg27_schema_migration_<16-hex>` is strictly `39` chars (`<= 64`), released in `finally`. | **Verified (`T3-01`)** |
| **Privileges on Shared Hosting (No `SUPER` / No Triggers / No Events)** | Shared DB users lack `SUPER`, `TRIGGER`, `EVENT` privileges | Shared DB users lack `SUPER`, `SYSTEM_VARIABLES_ADMIN` | **Zero stored triggers, zero MySQL `EVENT`s, zero `DEFINER=` views, zero `SET GLOBAL` statements.** Pure standard InnoDB tables, indexes, FKs, and prepared PDO queries. | **Verified across all 6 migrations** |
| **Character Set & Collation (`utf8mb4`)** | Default `utf8mb4_unicode_ci` (does NOT support MySQL 8-only `utf8mb4_0900_ai_ci`) | Supports `utf8mb4_unicode_ci` | Explicitly declares `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` on every table and `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` on every PDO connection — **zero `utf8mb4_0900_ai_ci` references**. | **Verified with Gujarati & Hindi UTF-8 (`T3-12`, `T3-13`)** |
| **Index Prefix Length (`767` / `3072` byte limit)** | InnoDB `DYNAMIC` row format (`3072` bytes), safe with `VARCHAR(191)` / `full_name(100)` | Same (`3072` bytes) | All indexed `VARCHAR` keys stay `<= 191` chars or use explicit prefix `full_name(100)` (`400` bytes in `utf8mb4`). | **Verified (`T3-12`)** |
| **Session Timezone (`+05:30` IST)** | Numeric offset `+05:30` works even when MySQL tz tables are unpopulated | Same | Uses numeric `SET time_zone = '+05:30'` (never named `'Asia/Kolkata'` which fails if tz tables are absent on shared hosting). | **Verified (`T1-11`)** |
| **Unbuffered PDO Query Error (`SQLSTATE HY000 2014`)** | `ANALYZE TABLE` returns result set | Same | Consumes `fetchAll()` + `closeCursor()` whenever issuing utility queries returning result rows. | **Verified (`T3-12`)** |
| **JSON Columns** | `JSON` is an alias for `LONGTEXT` with check constraint | Native binary `JSON` type | Uses portable `LONGTEXT` with PHP `jsonEncode()`/`jsonDecode()` so `mysqldump` and cross-engine restores never clash on MariaDB vs MySQL JSON binary format. | **Verified (`T3-13`)** |

---

## 2. Bluehost Live Environment Verification Status

- **Local Native XAMPP (`PHP 8.2.12`, `MariaDB 10.4.32`, `Apache 2.4.58`):** `VERIFIED` (`45/45` PHP/MariaDB + `26/26` Apache checks PASS).
- **Live Bluehost Server Engine Version, Privileges, `max_connections` & Cron Capability:** `PENDING VERIFICATION` — must be confirmed by running `php scripts/verify-bluehost-env.php` on Bluehost cPanel SSH when authorized to connect to staging/production.

