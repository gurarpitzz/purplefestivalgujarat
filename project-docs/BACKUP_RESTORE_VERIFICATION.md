# Backup & Restore Verification Report — Purple Festival Gujarat 2027

**Phase:** Phase 3M / Section 14 (`T3-13`)  
**Verification Date:** `2026-11-26`  
**Isolated Test Database:** `pfg27_backup_test` (created specifically for verification and dropped immediately afterward; **zero** live staging or dev databases touched).  
**Tools Executed:** Native XAMPP `C:\xampp\mysql\bin\mysqldump.exe` & `C:\xampp\mysql\bin\mysql.exe` against `MariaDB 10.4.32`.

---

## 1. Verified Backup & Restore Command Procedure

### Backup Command Executed
```bash
mysqldump --host=127.0.0.1 --port=3306 --user=root \
  --single-transaction --routines --triggers --hex-blob \
  --default-character-set=utf8mb4 \
  pfg27_backup_test --result-file=<dump_path>.sql
```

### Restore Command Executed
```bash
mysql --host=127.0.0.1 --port=3306 --user=root \
  --default-character-set=utf8mb4 \
  pfg27_backup_test < <dump_path>.sql
```

---

## 2. Measured Verification Results (`T3-13`)

| Verification Check | Pre-Backup State | Post-Restore State | Outcome |
|---|---|---|---|
| **Backup Capture (`mysqldump`)** | `131,890` bytes, SHA-256 `5d94b115ce79f98a...` (`837 ms`) | Non-empty consistent InnoDB snapshot (`--single-transaction`) | **PASS** |
| **Full Drop + Recreate + Restore (`mysql`)** | Fresh empty `pfg27_backup_test` | Restored cleanly in `1,237 ms` (exit code `0`) | **PASS** |
| **Table Count Parity** | `62` tables | `62` tables | **PASS** |
| **Foreign Key Constraint Parity** | `62` FKs | `62` FKs | **PASS** |
| **Encrypted Sensitive Data + Gujarati UTF-8 Round-Trip** | `enc:v1:` ciphertext of `"Wheelchair ramp & Gujarati interpreter needed — અમદાવાદ"` | Decrypted identically after restore via `pfgDecryptValue(..., 'data')` | **PASS** |
| **Atomic Registration ID Sequence Continuity** | `PFG-2027-000001` allocated pre-backup | Next allocation post-restore = `PFG-2027-000002` (monotonic, zero reset/collision) | **PASS** |
| **Post-Restore Migration Integrity** | `6/6` applied (`schema_migrations`) | `pfgRunMigrations()` reported `0` pending migrations & `0` checksum mismatches | **PASS** |
| **Cleanup Hygiene** | Temporary DB & `.sql` dump created in `tests/tmp/` | Temporary DB dropped & `.sql` dump unlinked cleanly | **PASS** |

---

## 3. Bluehost Production Backup Guidance

- On Bluehost shared hosting, operators can execute the exact same `mysqldump --single-transaction --hex-blob --default-character-set=utf8mb4` command via cPanel SSH (or cPanel Backup Wizard / phpMyAdmin SQL export if SSH is unavailable), storing dumps strictly **outside `public_html`** (e.g., `~/pfg27_backups/` with `chmod 600`).
- **Live Bluehost Backup/Restore Status:** `PENDING AUTHORIZED STAGING/PRODUCTION ACCESS` — local MariaDB backup/restore is verified; Bluehost live backup verification will be executed immediately prior to any authorized staging/production migration.

