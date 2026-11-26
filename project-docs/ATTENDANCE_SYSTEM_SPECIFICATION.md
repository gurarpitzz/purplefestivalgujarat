# Manual Registration Desk & Attendance System Specification (Phase 9)

**Authoritative Implementation:** `rgst/index.php`, `assets/js/rgst-desk.js`, `api/attendance/index.php`, `api/attendance/mark.php`, `api/attendance/correct.php`, `database/migrations/003_phase3_schema_evolution_and_separation.php`, `database/migrations/009_phase8_9_custom_permission_profiles_and_attendance.php`  
**Governance Decisions:** `CAD-05` (Registration Desk Retained for Manual Marking), `CAD-06` (Event-Day QR Scanning Discontinued), `CAD-13` / `D-01` (Participant QR Credentials Discontinued), `D-02` (Daily Attendance Granularity), `D-03` (15-Minute Desk Undo & Audited Admin Override), `D-04` (Check-In Only Desk Scope), `HIGH-04` (Concurrency Locking & Scope Allowlist)

---

## 1. Architectural Purpose & Complete QR Decoupling (`D-01`, `CAD-05`, `CAD-06`)

The Registration Desk portal (`/rgst`) provides a fast, WCAG 2.2 AA keyboard- and screen-reader-accessible operational interface for venue desk operators (`RGST`), coordinators (`ADMIN`), and `SUPERADMIN` staff to look up registered **Attendees**, **Volunteers**, and **Sign Language Interpreters (SLIs)** and record physical attendance across the 3 festival days (**21, 22, and 23 January 2027**).

### What Was Permanently Retired
- Live camera QR scanning (`assets/js/html5-qrcode.min.js` is never loaded on `/rgst` and is excluded from the production release package).
- USB hardware QR scanner listeners and token verification flows.
- Legacy QR endpoints (`POST /api/qr/verify`, `POST /api/checkins/confirm`, `GET/POST /api/qr/pass`), which return `HTTP 410 Gone`.
- Foreign-key coupling between attendance records and `qr_credentials` (historical `qr_credentials` and `checkins` tables remain preserved read-only for historical data integrity).

---

## 2. Database Schema & Atomic Uniqueness (`attendance_records` & `attendance_correction_logs`)

### 2.1 `attendance_records`
Created in Migration `003` with performance composite index `idx_attendance_edition_date_status` added in Migration `009`:
- `id` (`CHAR(36)` PK)
- `edition_id` (`CHAR(36)` NOT NULL, `ON DELETE RESTRICT`)
- `registration_id` (`CHAR(36)` NOT NULL, `ON DELETE RESTRICT` -> `registrations.id`)
- `participant_category` (`VARCHAR(64)` NOT NULL: `ATTENDEE`, `VOLUNTEER`, `SLI`, or preserved legacy code)
- `attendance_date_ist` (`DATE` NOT NULL: `2027-01-21`, `2027-01-22`, `2027-01-23`)
- `attendance_scope` (`VARCHAR(64)` NOT NULL DEFAULT `'FESTIVAL_DAY'`)
- `station_id` (`CHAR(36)` NULL, `ON DELETE SET NULL` -> `checkin_stations.id`)
- `status` (`VARCHAR(32)` NOT NULL DEFAULT `'PRESENT'`: `'PRESENT'` or `'REVOKED'`)
- `marked_by_user_id` (`CHAR(36)` NULL, `ON DELETE SET NULL` -> `users.id`)
- `marked_at` (`DATETIME` NOT NULL)
- `corrected_by_user_id` (`CHAR(36)` NULL, `ON DELETE SET NULL` -> `users.id`)
- `corrected_at` (`DATETIME` NULL)
- `correction_reason` (`TEXT` NULL)
- **Atomic Composite Unique Key:** `UNIQUE KEY uq_attendance_reg_date_scope (edition_id, registration_id, attendance_date_ist, attendance_scope)`

### 2.2 `attendance_correction_logs` (Immutable Append-Only Audit Ledger)
- `id` (`CHAR(36)` PK)
- `attendance_record_id` (`CHAR(36)` NOT NULL, `ON DELETE RESTRICT` -> `attendance_records.id`)
- `registration_id` (`CHAR(36)` NOT NULL, `ON DELETE RESTRICT` -> `registrations.id`)
- `actor_user_id` (`CHAR(36)` NULL, `ON DELETE SET NULL` -> `users.id`)
- `actor_role` (`VARCHAR(32)` NOT NULL: `RGST`, `ADMIN`, `SUPERADMIN`)
- `action` (`VARCHAR(64)` NOT NULL: `ATTENDANCE_REVOKED`, `ATTENDANCE_REINSTATED`, `ATTENDANCE_CORRECTED`)
- `previous_status` (`VARCHAR(32)` NOT NULL)
- `new_status` (`VARCHAR(32)` NOT NULL)
- `reason` (`TEXT` NOT NULL)
- `created_at` (`DATETIME` NOT NULL)
- **Zero `UPDATE` or `DELETE` paths exist anywhere in the codebase against `attendance_correction_logs`.**

---

## 3. Strict Desk Data Minimization (`FND-25`, `§19.5`)

`GET /api/attendance` is engineered so Registration Desk staff see **only** the minimum identification fields required to verify a participant at a physical venue gate:

| Exposed to `/rgst` (`GET /api/attendance`) | Strictly Excluded (Never Queried or Returned by `/api/attendance`) |
|---|---|
| `registrationId` (UUID), `registrationCode` (`PFG-2027-NNNNNN`), `fullName`, `cityDistrict`, `state`, `phoneLast4` (masked last 4 digits only), `categoryCode`, `categoryName`, `status`, `isEligible` (`status IN ('CONFIRMED', 'APPROVED')`), `attendingDays`, and the 3-day `attendance` status map (`2027-01-21`, `2027-01-22`, `2027-01-23`) | `is_person_with_disability`, `participant_disability_disclosures` (`self_description`, `notes`, RPwD categories), `accommodation_requests` (`selected_types`, `free_text_details`, `coordinator_notes`, `escort_name_encrypted`), `attendee_details.dietary_requirements_encrypted`, `emergency_contact_*`, full `email`, full `phone`, and internal `registrations.review_notes` |

---

## 4. Atomic Marking, Concurrency Locking & Correction Rules (`D-02`, `D-03`, `HIGH-04`)

### 4.1 Eligibility & Scope Validation
1. **Eligibility Enforcement:** Only registrations with `registrations.status IN ('CONFIRMED', 'APPROVED')` and `deleted_at IS NULL` can be marked `PRESENT`. Attempting to mark a `DRAFT`, `SUBMITTED`, `UNDER_REVIEW`, `WAITLISTED`, `ADDITIONAL_INFO_REQUIRED`, `REJECTED`, `WITHDRAWN`, or `CANCELLED` record returns `HTTP 400 Bad Request`.
2. **Date & Scope Allowlisting (`HIGH-04`):**
   - `attendanceDateIst` must match one of the configured festival dates (`['2027-01-21', '2027-01-22', '2027-01-23']` by default, configurable via `system_settings` key `attendance.festival_dates`).
   - `attendanceScope` must match the allowlisted scope (`['FESTIVAL_DAY']` by default, configurable via `system_settings` key `attendance.scope` / `attendance.mode`). Arbitrary client-supplied scope strings are rejected with `HTTP 400`.

### 4.2 Three-Case Atomic Marking (`POST /api/attendance/mark`)
Inside a database transaction with `SELECT ... FOR UPDATE` row locking (`HIGH-04`):
1. **Case 1 — New Mark (No existing row for `(edition_id, registration_id, attendance_date_ist, attendance_scope)`):**
   - Inserts `status = 'PRESENT'`, `marked_by_user_id = currentUser.id`, `marked_at = NOW()`.
   - Writes `ATTENDANCE_MARKED` to `audit_logs`.
   - Returns **`HTTP 201 Created`** (`{ok: true, alreadyMarked: false, reinstated: false, record: {...}}`).
   - If two concurrent requests race on a new insert, the database `UNIQUE KEY uq_attendance_reg_date_scope` catches the duplicate (`SQLSTATE 23000` / `1062`) and returns an idempotent **`HTTP 200 OK`** (`{ok: true, alreadyMarked: true}`) with zero duplicate rows.
2. **Case 2 — Already `PRESENT` (Idempotent Replay / Double-Click):**
   - Returns **`HTTP 200 OK`** (`{ok: true, alreadyMarked: true, record: {...}}`) without mutating timestamps or writing duplicate logs.
3. **Case 3 — Reinstating a `REVOKED` Record (`REVOKED -> PRESENT`):**
   - Locks the row with `SELECT ... FOR UPDATE`.
   - Enforces `D-03` permission check: allowed if caller is an override actor (`SUPERADMIN` or `ADMIN` with `registrations.write`) OR the original `RGST` operator within 15 minutes (`900` seconds) of `marked_at`.
   - Updates `status = 'PRESENT'` with conditional `WHERE id = :id AND status = 'REVOKED'` (`rowCount() === 1`), appends an immutable `ATTENDANCE_REINSTATED` row to `attendance_correction_logs`, logs `ATTENDANCE_REINSTATED` in `audit_logs`, and returns **`HTTP 200 OK`** (`{ok: true, reinstated: true}`).

### 4.3 Audited Corrections & 15-Minute Desk Undo (`POST /api/attendance/correct` — `D-03`)
1. **Mandatory Reason:** Every correction request (`PRESENT -> REVOKED` or `REVOKED -> PRESENT`) requires a non-empty `reason` of at least 5 characters (`HTTP 400` if omitted or shorter).
2. **Role-Differentiated Correction Window (`D-03` & `HIGH-04`):**
   - **`RGST` Desk Operator:** May undo (`PRESENT -> REVOKED`) or reinstate (`REVOKED -> PRESENT`) **only** records where `marked_by_user_id === currentUser.id` AND `NOW() - marked_at <= 900 seconds` (15 minutes). Attempting to modify another operator's mark or a mark older than 15 minutes returns **`HTTP 403 Forbidden`**.
   - **`SUPERADMIN` or `ADMIN` with `registrations.write`:** May correct any attendance record at any time with a mandatory reason. (Normal desk permission `checkins.confirm` does **not** grant override authority per `HIGH-04`).
3. **Concurrency-Safe Execution:** Acquires `SELECT ... FOR UPDATE` on the `attendance_records` row inside the transaction and executes `UPDATE attendance_records SET status = :new_status ... WHERE id = :id AND status = :prev_status`, ensuring concurrent requests cannot write duplicate rows to `attendance_correction_logs`.

---

## 5. Accessible Desk UI & Real-Time SQL Metrics (`rgst/index.php`, `assets/js/rgst-desk.js`)

- **Keyboard-First Search & Filter Bar:** Instant debounced search by Registration ID (`PFG-2027-000001` or numeric suffix `1`), participant full name, or phone number, plus Category (`ALL`, `ATTENDEE`, `VOLUNTEER`, `SLI`), Eligibility (`ELIGIBLE`, `ALL`, `INELIGIBLE`), and Day filters.
- **WCAG 2.2 AA 3-Day Checkbox Matrix:** Each row renders accessible Day 1 (`21 Jan`), Day 2 (`22 Jan`), and Day 3 (`23 Jan`) checkboxes (`>= 24×24px` target area inside `>= 44×44px` interactive label wrapper) with explicit `aria-label="Mark Day 1 (21 Jan 2027) attendance for <Full Name> (<Registration Code>)"`, busy state locking during network requests, an accessible modal prompt for mandatory correction reasons when unchecking a `PRESENT` box, and polite/assertive screen-reader status announcements via `window.pfgAnnounce()`.
- **Accurate SQL Metrics (Excluding Soft-Deleted Rows):** `GET /api/attendance` computes `totalRegistrations`, `eligibleRegistrations`, `uniqueParticipantsPresent`, `totalAttendanceEvents`, `byCategory` (`ATTENDEE`, `VOLUNTEER`, `SLI`), and `byDay` (`2027-01-21`, `2027-01-22`, `2027-01-23`) directly in SQL (`WHERE r.deleted_at IS NULL`).
