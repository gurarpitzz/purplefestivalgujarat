# Atomic Registration Numbering Specification — Purple Festival Gujarat 2027

**Authoritative Module:** `includes/registration-id.php`  
**Backing Table:** `registration_number_sequences` (Migration `002`)  
**Decision Alignment:** `D-01` (permanently decoupled from `qr_credentials` and `includes/qr-service.php`), `FND-21` (eliminates `SELECT COUNT(*) + 1` race condition).

---

## 1. Format & Monotonic Scaling Rules

| Rule | Specification | Verified Example |
|---|---|---|
| **Prefix** | `PFG-2027` (`PFG_REGISTRATION_CODE_PREFIX`) | `PFG-2027-000001` |
| **Minimum Zero-Padding** | 6 digits (`PFG_REGISTRATION_CODE_MIN_DIGITS = 6`) | `000001` .. `999999` |
| **Natural Expansion Above 999,999** | Unbounded integer digits (no truncation/wraparound) | `PFG-2027-1000000` |
| **Boundary Transitions (Verified `T3-05`)** | `99 -> 100`, `999 -> 1000`, `9999 -> 10000` | `PFG-2027-000099` -> `PFG-2027-000100`, `PFG-2027-000999` -> `PFG-2027-001000`, `PFG-2027-009999` -> `PFG-2027-010000` |
| **Legacy ID Compatibility (Verified `T3-03`)** | Legacy codes (e.g., `PFG-2027-000099-A1B2`) are **never renumbered**; `syncRegistrationSequenceFloor()` seeds a physical floor marker row so next allocation begins strictly above the highest legacy sequence (`PFG-2027-000100`) | `PFG-2027-000099-A1B2` preserved; next = `PFG-2027-000100` |
| **Deleted & Rolled-Back Registrations** | Allocated sequence numbers are **never reused** even when a registration is soft-deleted or a registration transaction rolls back (`T3-05`) | Rollback after `000003` -> next is `000004` |
| **QR Independence** | Works with `0` rows in `qr_credentials` and zero dependency on `includes/qr-service.php`. `generateUniqueRegistrationCode()` in `qr-service.php` is retained only as a thin deprecated delegate calling `allocateNextRegistrationCode()`. | Verified in `T3-05` & `T3-08` |

---

## 2. Concurrency & Restart-Safety Mechanism

```sql
CREATE TABLE IF NOT EXISTS `registration_number_sequences` (
  `seq_number` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `edition_id` CHAR(36) NOT NULL,
  `registration_code` VARCHAR(64) DEFAULT NULL,
  `allocated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`seq_number`),
  KEY `idx_reg_num_seq_edition` (`edition_id`),
  UNIQUE KEY `uq_reg_num_seq_code` (`registration_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

1. **Allocation Flow (`allocateNextRegistrationCode`):**
   - Executed **immediately before** `$pdo->beginTransaction()` in `api/registrations/index.php` so the sequence row is durably committed on its own auto-commit statement.
   - InnoDB acquires its lightweight internal auto-increment mutex, assigns a strictly unique `seq_number`, and returns it via `$pdo->lastInsertId()` — zero deadlocks and zero retry storms even at 100 concurrent processes.
   - `UPDATE registration_number_sequences SET registration_code = :code WHERE seq_number = :seq` records the formatted ID for auditability.
   - A defensive `SELECT 1 FROM registrations WHERE registration_code = :code LIMIT 1` check prevents collision even if a manual SQL import inserted a conflicting code without calling `syncRegistrationSequenceFloor()`.
2. **MariaDB 10.4 & MySQL 8.0 Server Restart Safety:**
   - MySQL 8.0 persists `AUTO_INCREMENT` in the redo log; MariaDB 10.4 recomputes `MAX(seq_number) + 1` from the table after restart.
   - Because every allocated sequence row (and any legacy floor marker row) stays permanently stored in `registration_number_sequences`, **both MariaDB 10.4 AND MySQL 8.0 preserve the exact monotonic high-water mark across restarts and dump/restores** (verified in `T3-13`).

---

## 3. Verified Multi-Process Concurrency Benchmark (`T3-06`)

Executed with real parallel PHP 8.2.12 CLI processes synchronized via a microsecond start barrier against local MariaDB `10.4.32` InnoDB (`tests/phase3_concurrency_worker.php`; full CSV in `REGISTRATION_CONCURRENCY_TEST_RESULTS.csv`):

| Parallel Processes | Requested | Succeeded | Unique IDs | Duplicates | Failures | Range Allocated | Wall Duration | Status |
|---:|---:|---:|---:|---:|---:|---|---:|---|
| **10** | 10 | 10 | **10** | **0** | **0** | `PFG-2027-000001` – `PFG-2027-000010` | `369 ms` | **PASS** |
| **25** | 25 | 25 | **25** | **0** | **0** | `PFG-2027-000011` – `PFG-2027-000035` | `511 ms` | **PASS** |
| **50** | 50 | 50 | **50** | **0** | **0** | `PFG-2027-000036` – `PFG-2027-000085` | `744 ms` | **PASS** |
| **100** | 100 | 100 | **100** | **0** | **0** | `PFG-2027-000086` – `PFG-2027-000185` | `1,264 ms` | **PASS** |

