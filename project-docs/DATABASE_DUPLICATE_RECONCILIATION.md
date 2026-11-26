# Database Duplicate Table Reconciliation Report

**Phase:** Phase 3E (Addressing Audit Findings `FND-21`, `FND-22`, `FND-29`, `FND-44`)  
**Policy:** **Non-destructive consolidation** — zero legacy tables dropped in Phase 3; zero silent overwrites on conflicting rows; zero ongoing dual-writes in PHP code.

---

## 1. Complete 6-Pair Reconciliation Matrix

| # | Legacy Duplicate Table | Canonical Table (Authoritative) | Pre-Phase 3 Defect | Migration `004` Action | Conflict Handling | PHP Application Cutover | Legacy Table Disposition |
|---|---|---|---|---|---|---|---|
| 1 | `registration_accommodations` | **`accommodation_requests`** | Dual-written with **distinct UUIDs** (`api/registrations/index.php`), causing drift & duplicate coordinator rows (`FND-21`) | Copies rows not present in `accommodation_requests` (by `id` or `registration_id`) | Logs status mismatch in `schema_migrations.notes` conflict list; preserves canonical row without overwrite | `api/registrations/index.php`, `api/admin/accommodations.php`, `api/account/accommodations.php`, `api/admin/registrations.php`, `api/admin/overview.php`, `dashboard/index.php` read/write **exclusively** `accommodation_requests` | **Archived read-only**; legacy free-text encrypted at rest |
| 2 | `registration_disabilities` | **`participant_disability_disclosures`** | Dual-written on every registration with distinct UUIDs | Copies rows missing for `(registration_id, COALESCE(disability_code, category_code))` | Preserves existing canonical row if already present | `api/registrations/index.php` & `api/admin/registrations.php` read/write **exclusively** `participant_disability_disclosures` | **Archived read-only**; legacy free-text encrypted at rest |
| 3 | `delegate_profiles` | **`delegate_details`** | Dual-written on `DELEGATE` registrations; reads fell back sequentially | Copies any `registration_id` missing from `delegate_details` | Preserves canonical `delegate_details` row | `api/registrations/index.php` & `api/admin/registrations.php` read/write **exclusively** `delegate_details` (pending `D-06`) | **Archived read-only** |
| 4 | `coupon_codes` | **`coupons`** | Dual-written in `api/admin/coupons.php` & dual-incremented in `api/registrations/index.php` (`FND-22`) | Copies any `UPPER(code)` missing from `coupons` | Flags `used_count` or `is_active` mismatch in conflict log; preserves canonical `coupons` row | `api/admin/coupons.php` & `api/registrations/index.php` read/write **exclusively** `coupons` (pending `D-05`) | **Archived read-only** |
| 5 | `payments` | **`payment_orders`** | Read-merged in `api/admin/overview.php` & `api/admin/registrations.php` | Copies any `id` missing from `payment_orders` (mapping `provider_payment_id`, `receipt_number`, `raw_payload`) | Preserves canonical `payment_orders` row on ID match | `api/admin/overview.php` & `api/admin/registrations.php` read **exclusively** `payment_orders` (pending `D-05`) | **Archived read-only** |
| 6 | `notification_jobs` | **`notification_queue`** | Dual-written in `pfgQueueNotificationCompat()`; zero consumers | Copies any `id` missing from `notification_queue` | Preserves canonical `notification_queue` row | `includes/mailer.php`, `includes/database.php`, `api/registrations/index.php` write **exclusively** `notification_queue` | **Archived read-only** |

---

## 2. Verification Evidence (`T3-03` & `T3-08`)

- **Legacy Upgrade Consolidation (`T3-03`):** On an isolated pre-Phase-3 MariaDB database populated with rows in all `6` legacy duplicate tables, Migration `004` copied all `6` records cleanly into their canonical tables, encrypted all sensitive legacy free-text, left all `6` legacy tables intact (`0` tables dropped), and completed with `62` active FKs.
- **Single-Write Verification (`T3-08`):** A live `POST /api/registrations` request with disability disclosure and accommodation request persisted `1` row in `accommodation_requests`, `0` rows in `registration_accommodations`, `1` row in `participant_disability_disclosures`, and `0` rows in `registration_disabilities`.
- **Future Cleanup Gate:** Dropping archived legacy duplicate tables is explicitly deferred until after production upgrade verification, a confirmed post-launch backup, and explicit Project Owner sign-off (Phase 18+).

