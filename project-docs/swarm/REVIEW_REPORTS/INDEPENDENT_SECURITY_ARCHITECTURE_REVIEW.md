# Independent Security & Architecture Review — Purple Festival Gujarat 2027 (Phases 5–15)

- **Reviewer:** Agent M (`reviewer`) — Independent Security & Architecture Reviewer
- **Repository & Branch:** `d:\purplefestgujarat` (`swarm/phases-5-18`, HEAD `d958022`)
- **Review Scope:** Phases 5–15 merged implementation across §19 Mandatory Audit Checklist (Authentication & Verification, Authorization & Permission Profiles, Database Migrations `007`–`010`, Three-Category Registration & Lifecycle, Manual Registration Desk, Participant Dashboard & Public Site)
- **Verification Environment:** Direct static code & SQL schema inspection across all merged files + CLI PHP syntax lint (`php -l`: **103 PHP files checked, 0 syntax errors**; no live HTTP/MySQL test harness executed)
- **Overall Verdict:** **PASS WITH FIXES REQUIRED** (Blockers: **1 CRITICAL**, **7 HIGH**, **7 MEDIUM**, **5 LOW**)

---

## Executive Summary

The Phases 5–15 implementation establishes strong foundational controls across authentication (`hash_hmac` email verification tokens with atomic `used_at` single-use consumption, constant-time login password checks before lockout/verification branching, D-20 mandatory TOTP MFA with slice replay prevention and AES-256-GCM encryption, staff/participant portal isolation, POST-only CSRF logout), non-destructive database migrations (`007`–`010` using `information_schema` guards and `ON DELETE RESTRICT` / `ON DELETE SET NULL`), 3-category registration hardening (`PRIMARY` tier enforcement, idempotent submission, zero QR generation in `/api/registrations`), data minimization in `/api/attendance` and `/api/account/dashboard`, and most XSS/URL protections (`pfgSafeJsonForScript`, `pfgValidateSafeUrl`, strict JS `esc()`/`escapeHtml()`).

However, **eight high-impact architectural and security defects** must be remediated before final acceptance:
1. **`CRIT-01` (Least-Privilege Bypass):** `getUserRolesAndPermissions()` merges `DEFAULT_ROLE_PERMISSIONS['ADMIN']` (17 broad permissions) *before* layering `permission_profile_id`, making all restricted Phase 8 operational permission profiles ineffective.
2. **`HIGH-01` (Broken Permissions Contract):** `programme.manage` and `communications.manage` are checked by `includes/admin-common.php` and `assets/js/admin-portal.js`, but are absent from `includes/config.php` `PERMISSIONS`, rejected (`400`) by profile/user APIs, and omitted from seeded profiles in migration `009`.
3. **`HIGH-02` (State-Machine Divergences & Actor Bug):** `api/account/registrations.php` fails to pass `'actorType' => 'PARTICIPANT'` to `pfgTransitionRegistrationStatus()`, breaking self-resubmission (`500`) and self-withdrawal (`400`) for `ADMIN`/`SUPERADMIN` participants; meanwhile `includes/admin-common.php` and `api/account/dashboard.php` diverge from `includes/application-lifecycle.php` on transitions and `canEdit`/`canWithdraw`.
4. **`HIGH-03` (Incomplete D-01 QR Discontinuation):** While `/api/registrations`, `/api/qr/verify`, and `/api/checkins/confirm` disable QR issuance/scanning, `GET /api/qr/pass.php` and `/dashboard/pass-print.php` still reconstruct and render scannable QR entry passes for historical `ACTIVE` credentials, and `assets/js/admin-portal.js` still renders `"Active QR credential"`, pass-print links, and `"Open RGST QR Scanner"` UI.
5. **`HIGH-04` (Attendance Concurrency & Override Scope):** `api/attendance/correct.php` and `api/attendance/mark.php` (reinstate path) lack row locking (`SELECT ... FOR UPDATE`), enabling duplicate `attendance_correction_logs` under race conditions; `CHECKINS_CONFIRM` grants D-03 override to every default `ADMIN`; `/rgst` UI and `/api/attendance` GET permissions disagree; `attendanceScope` is not allowlisted.
6. **`HIGH-05` (Silent SQL Failure):** `api/account/dashboard.php` queries `announcements.body` and `announcements.priority` while the canonical `announcements` schema defines `message` and `severity`, silently swallowing the `PDOException` and returning an empty announcements array.
7. **`HIGH-06` (Stored XSS in Public JSON-LD Output):** `includes/header.php` encodes `$structuredData` via raw `json_encode(..., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` without `JSON_HEX_TAG`, while `index.php` embeds database `faq_items` into `<script type="application/ld+json">`, enabling `</script>` breakout XSS.
8. **`HIGH-07` (Broken Dashboard Quick-Edit & Resubmission):** `assets/js/participant-dashboard.js` (`#dash-edit-form`) submits a partial `PATCH` payload to `api/account/registrations.php`, which validates against full required category/accessibility rules without merging existing DB child records, causing HTTP `400` failures and silently ignoring `resubmissionNote`.

---

## Findings Matrix

| ID | Severity | Area | Primary Location(s) | Summary |
|---|---|---|---|---|
| **CRIT-01** | **CRITICAL** | RBAC / Profiles | `includes/auth.php:870-906`, `includes/config.php:614-631` | Additive `DEFAULT_ROLE_PERMISSIONS['ADMIN']` overrides Phase 8 least-privilege profiles |
| **HIGH-01** | **HIGH** | RBAC / Programme | `includes/admin-common.php:96-106`, `includes/config.php:587-608`, `database/migrations/009_*.php:82-90` | `programme.manage` & `communications.manage` undefined in `PERMISSIONS` and rejected by API |
| **HIGH-02** | **HIGH** | State Machine | `includes/application-lifecycle.php:40-112`, `includes/admin-common.php:249-270`, `api/account/registrations.php:626-634,738-745`, `api/account/dashboard.php:439-440` | Divergent transition maps + missing `'actorType' => 'PARTICIPANT'` breaks staff participant self-service |
| **HIGH-03** | **HIGH** | D-01 QR Removal | `api/qr/pass.php:50-169`, `dashboard/pass-print.php:73-130,322-364`, `assets/js/admin-portal.js:1507,1710-1763,3105`, `admin/index.php:116,196` | `GET /api/qr/pass.php`, `pass-print.php`, and Admin Portal UI still serve/render QR entry passes & scanner links |
| **HIGH-04** | **HIGH** | Attendance Desk | `api/attendance/correct.php:43-57,267-337,428-464`, `api/attendance/mark.php:43-57,324-330,401-466`, `api/attendance/index.php:290-297`, `rgst/index.php:41-64` | Missing `FOR UPDATE` locking on revoke/reinstate, `CHECKINS_CONFIRM` grants D-03 override, gate mismatch, unallowlisted scope |
| **HIGH-05** | **HIGH** | Dashboard / DB | `api/account/dashboard.php:744-768`, `database/migrations/000_baseline_schema.sql:804-816` | Dashboard selects nonexistent `announcements.body`/`priority` columns and catches error silently |
| **HIGH-06** | **HIGH** | Public XSS | `includes/header.php:134-136,190`, `index.php:597-612,766-778` | JSON-LD `<script>` uses `json_encode` without `JSON_HEX_TAG` on DB `faq_items` (`</script>` breakout XSS) |
| **HIGH-07** | **HIGH** | Dashboard Edit | `assets/js/participant-dashboard.js:1394-1410`, `api/account/registrations.php:315,626-634`, `includes/registration-validators.php:755-845` | Dashboard `#dash-edit-form` PATCH omits category fields, causing unconditional 400 validation failure & ignores `resubmissionNote` |
| **MED-01** | **MEDIUM** | Auth Anti-Enum | `api/auth/signup.php:122,173,188` | Signup leaks account existence via HTTP `202` (existing) vs `201` (new) |
| **MED-02** | **MEDIUM** | Auth Anti-Enum | `api/account/profile.php:46,246-258,316` | Profile email-change response leaks target email availability via `pendingEmail` reflection |
| **MED-03** | **MEDIUM** | Auth Reset | `api/auth/password-reset.php:130-132,137-189` | Password reset lacks letter+digit policy and performs non-atomic token check/update before delete |
| **MED-04** | **MEDIUM** | Admin Validation | `api/admin/cms.php:536-537,593-615`, `api/admin/registrations.php:1059-1146` | CMS lacks edition scoping on PATCH/DELETE and status/severity enum validation; legacy duty assignments lack bounds checks |
| **MED-05** | **MEDIUM** | RBAC / Audit | `includes/auth.php:921-927`, `database/migrations/009_*.php:67-68` | `pfgProfileForbiddenPermissions()` omits `audit_logs.read` contrary to Migration `009` design contract |
| **MED-06** | **MEDIUM** | Data Integrity | `api/account/registrations.php:570-622` | Clearing all accommodation fields on registration `PATCH` leaves prior `accommodation_requests` active |
| **MED-07** | **MEDIUM** | Dashboard Auth UI | `dashboard/index.php:729-753,873-895`, `assets/js/participant-dashboard.js:1661,1769`, `api/account/profile.php:180,220`, `api/auth/mfa.php:78,287` | Dashboard email-change & MFA-disable forms omit required `currentPassword`; MFA enable ignores returned `recoveryCodes` |
| **LOW-01** | **LOW** | Legacy Claim Oracle | `api/auth/claim-legacy.php:126-135` | Explicit `403` vs `404` distinguishes staff-owned legacy registrations from nonexistent codes |
| **LOW-02** | **LOW** | URL Defense | `api/account/registrations.php:108` | Raw `video_demo_url` returned without `pfgValidateSafeUrl()` sanitization |
| **LOW-03** | **LOW** | Defense-in-Depth | `api/account/registrations.php:277-307` | Registration `PATCH` does not re-verify `email_verified_at` (unlike `POST`) |
| **LOW-04** | **LOW** | Legacy Artifact | `assets/js/registration-wizard.js:1602-1603` | Unreferenced legacy wizard JS still retains outdated Digital QR Entry Pass copy |
| **LOW-05** | **LOW** | Session Visibility | `api/account/dashboard.php:351-360` | Duty assignment response includes `sessionTitle`/`sessionTrack` even when linked session is `DRAFT`/`CANCELLED` |

---

## Detailed Technical Findings

### [CRIT-01] Additive `DEFAULT_ROLE_PERMISSIONS['ADMIN']` Defeats Operational Permission Profiles
- **Severity:** `CRITICAL`
- **Locations:**
  - `includes/auth.php:870-906`
  - `includes/config.php:614-631`
- **Evidence:**
  In `getUserRolesAndPermissions()` (`includes/auth.php:870-906`), base role permissions are applied unconditionally before checking `permission_profile_id`:
  ```php
  // includes/auth.php:870-906
  $basePerms = DEFAULT_ROLE_PERMISSIONS[$rCode] ?? [];
  foreach ($basePerms as $p) {
      $permMap[$p] = true;
  }
  ...
  if (in_array('ADMIN', $roles, true) && !in_array('SUPERADMIN', $roles, true)) {
      $profile = pfgResolvePermissionProfile($userId, $pdo);
      if ($profile !== null) {
          ...
          foreach ($profile['permissions'] as $pp) {
              $permMap[$pp] = true;
          }
      }
  }
  ```
  Meanwhile `DEFAULT_ROLE_PERMISSIONS['ADMIN']` (`includes/config.php:614-631`) contains 17 permissions, including `REGISTRATIONS_READ_ALL`, `REGISTRATIONS_WRITE`, `REGISTRATIONS_EXPORT`, `VOLUNTEERS_REVIEW`, `SLI_REVIEW`, `ACCOMMODATIONS_READ`, `ACCOMMODATIONS_MANAGE`, `CREDENTIALS_ISSUE`, `CREDENTIALS_REVOKE`, `QR_SCAN`, `CHECKINS_CONFIRM`, `ATTENDANCE_READ_FULL`, `COUPONS_MANAGE`, and `PAYMENTS_READ`.
- **Impact:**
  Assigning a narrow profile (e.g., `content_manager` with `['account.read.own', 'cms.manage']` or `volunteer_coordinator`) does **not** restrict an `ADMIN` user. Every `ADMIN` inherits sensitive disability/accommodation read & write (`accommodations.read`, `accommodations.manage`), payment data (`payments.read`), registration export/write, and D-03 attendance override capability (`checkins.confirm`, `registrations.write`) regardless of their profile.
- **Remediation:**
  1. When resolving an `ADMIN` user (without `SUPERADMIN`), if a `permission_profile_id` is assigned (`$profile !== null`), grant **only** `['account.read.own']` + `$profile['permissions']` + explicit `user_roles.custom_permissions` — do **not** merge `DEFAULT_ROLE_PERMISSIONS['ADMIN']`.
  2. Tighten `DEFAULT_ROLE_PERMISSIONS['ADMIN']` (or require explicit profile assignment for all non-legacy `ADMIN` accounts, defaulting unprofiled `ADMIN`s conservatively without `accommodations.read`/`accommodations.manage`/`checkins.confirm`/`credentials.issue`).
  3. Add regression tests proving a `content_manager` `ADMIN` receives `403` on `/api/admin/accommodations`, `/api/admin/registrations`, `/api/attendance/correct`, and `/api/admin/overview` payment/accommodation blocks.

---

### [HIGH-01] `programme.manage` & `communications.manage` Missing from `PERMISSIONS` Registry and Profiles
- **Severity:** `HIGH`
- **Locations:**
  - `includes/admin-common.php:96-106`
  - `assets/js/admin-portal.js:142-150`
  - `includes/config.php:587-608`
  - `database/migrations/009_phase8_9_custom_permission_profiles_and_attendance.php:82-90`
  - `api/admin/permission-profiles.php:57-66`
  - `api/admin/users.php:93-105`
- **Evidence:**
  ```php
  // includes/admin-common.php:96-106
  function pfgAdminCanManageProgramme(array $user): bool {
      return hasPermission($user, PERMISSIONS['CMS_MANAGE']) || hasPermission($user, 'programme.manage');
  }
  function pfgAdminCanManageCommunications(array $user): bool {
      return hasPermission($user, PERMISSIONS['CMS_MANAGE']) || hasPermission($user, 'communications.manage');
  }
  ```
  Neither `'programme.manage'` nor `'communications.manage'` exists in `PERMISSIONS` (`includes/config.php:587-608`). Both `api/admin/permission-profiles.php:57-66` and `api/admin/users.php:93-105` reject unknown codes with HTTP `400 ("Unknown permission code(s)")`, and migration `009:82-90` omits them from `programme_coordinator` (`['account.read.own', 'registrations.read.all', 'volunteers.review', 'sli.review', 'attendance.read.full']`) and `communications_manager` (`['account.read.own', 'registrations.read.all', 'registrations.export']`).
- **Impact:**
  Once `CRIT-01` is fixed, `programme_coordinator` and `communications_manager` cannot access `/api/admin/programme`, `/api/admin/venues`, or `/api/admin/announcements` unless elevated to `cms.manage` (granting full public website CMS edit access), and no Superadmin can add `programme.manage` or `communications.manage` via the Staff & Profiles UI.
- **Remediation:**
  1. Add `'PROGRAMME_MANAGE' => 'programme.manage'` and `'COMMUNICATIONS_MANAGE' => 'communications.manage'` to `PERMISSIONS` in `includes/config.php`.
  2. Create a corrective migration (`011`) to update system profiles `programme_coordinator` and `communications_manager` (and include them in `migration 009` for fresh installs).
  3. Reference `PERMISSIONS['PROGRAMME_MANAGE']` and `PERMISSIONS['COMMUNICATIONS_MANAGE']` in `includes/admin-common.php`.

---

### [HIGH-02] State-Machine Divergences & Actor-Type Resolution Bug on Staff Participant Self-Service
- **Severity:** `HIGH`
- **Locations:**
  - `includes/application-lifecycle.php:40-46, 63-112`
  - `includes/admin-common.php:249-270`
  - `api/admin/registrations.php:932-1024`
  - `api/account/registrations.php:173-175, 626-634, 738-745`
  - `api/account/dashboard.php:439-440`
  - `apply/attendee.php:81`, `apply/volunteer.php:82`, `apply/sli.php:82`
- **Evidence:**
  1. **Self-service actor bug:** `api/account/registrations.php:627-634` and `738-745` pass `$user` directly to `pfgTransitionRegistrationStatus($pdo, $reg, ...)`. Inside `includes/application-lifecycle.php:40-46`:
     ```php
     if (in_array('SUPERADMIN', $roles, true)) return 'SUPERADMIN';
     if (in_array('ADMIN', $roles, true)) return 'ADMIN';
     ```
     Because `actorType` is not forced to `'PARTICIPANT'`, an `ADMIN` or `SUPERADMIN` who holds a participant registration is treated as `'ADMIN'`:
     - Resubmitting after `ADDITIONAL_INFO_REQUIRED` (`api/account/registrations.php:626-634`) attempts `ADDITIONAL_INFO_REQUIRED -> RESUBMITTED` as `'ADMIN'`, which is disallowed (`application-lifecycle.php:85-87`), throwing an **uncaught** `PfgInvalidTransitionException` and failing with HTTP `500` after mutating DB rows if not inside a single rollback (here it is inside the outer transaction so it rolls back with a `500` error).
     - Calling `POST /api/account/registrations` with `action: "withdraw"` (`738-745`) fails with HTTP `400` because `'ADMIN'` cannot transition to `WITHDRAWN` (`application-lifecycle.php:79-96`).
  2. **Admin vs Canonical lifecycle drift:** `api/admin/registrations.php` uses `pfgAdminAllowedTransitions()` (`includes/admin-common.php:249-270`) rather than `includes/application-lifecycle.php`. Key mismatches:
     - `admin-common.php:261` allows `RESUBMITTED -> ['UNDER_REVIEW', 'WITHDRAWN', 'CANCELLED']`, blocking direct `$decisions` (`APPROVED`/`REJECTED`/`WAITLISTED`/`ADDITIONAL_INFO_REQUIRED`) even though `api/admin/registrations.php:998-1000` explicitly contains fast-track history logic for `RESUBMITTED`!
     - `admin-common.php:260` allows `SUBMITTED -> $decisions` directly whereas `application-lifecycle.php:79-83` forbids fast-track from `SUBMITTED`.
     - `admin-common.php:260-266` allows `ADMIN -> WITHDRAWN` (forbidding participant-semantic confusion) and omits `ADDITIONAL_INFO_REQUIRED` as a source state completely (whereas `application-lifecycle.php:87` allows `ADMIN` -> `['UNDER_REVIEW', 'CANCELLED']`).
  3. **Dashboard vs API gating drift:** `api/account/dashboard.php:439-440` computes:
     ```php
     $canEdit = !$isLegacy && in_array($status, ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true);
     $canWithdraw = !$isLegacy && in_array($status, ['SUBMITTED', 'UNDER_REVIEW', 'CONFIRMED', 'APPROVED', 'WAITLISTED'], true);
     ```
     This blocks editing `ATTENDEE` `CONFIRMED` registrations on the dashboard even though `api/account/registrations.php:174-175, 301-302` permits it; and marks `canWithdraw = false` for `ADDITIONAL_INFO_REQUIRED` / `RESUBMITTED` even though `application-lifecycle.php:85-90` permits withdrawal/cancellation from both states. Meanwhile `apply/attendee.php:81` lacks a status check when entering edit mode, whereas `apply/volunteer.php:82` and `apply/sli.php:82` check `['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED']`.
- **Remediation:**
  1. Pass `array_merge($user, ['actorType' => 'PARTICIPANT'])` in `api/account/registrations.php:631` and `:742`, and catch `PfgInvalidTransitionException` cleanly in `PATCH`.
  2. Unify `includes/admin-common.php` with `includes/application-lifecycle.php` (one single authoritative state machine; decide unambiguously whether fast-track `SUBMITTED`/`RESUBMITTED -> decision` is allowed and reflect it in both transition list and `UNDER_REVIEW` auto-history, add `ADDITIONAL_INFO_REQUIRED -> CANCELLED`/`UNDER_REVIEW` for Admin, and restrict `WITHDRAWN` to `PARTICIPANT` or document Admin `WITHDRAWN` consistently).
  3. Derive `canEdit` and `canWithdraw` in `api/account/dashboard.php` and `apply/*.php` directly from `pfgGetAllowedStatusTransitions($catCode, $status, 'PARTICIPANT')` + one shared helper.

---

### [HIGH-03] Incomplete D-01 QR Discontinuation in Pass Retrieval, Printable Pass, and Admin Portal UI
- **Severity:** `HIGH`
- **Locations:**
  - `api/qr/pass.php:50-169`
  - `dashboard/pass-print.php:73-130, 322-364`
  - `assets/js/admin-portal.js:1507, 1710-1715, 1750, 1761, 3105`
  - `admin/index.php:116, 196`
- **Evidence:**
  While `POST /api/registrations` sets `'qrPass' => null` (`api/registrations/index.php:1048`) and `POST /api/qr/verify.php` + `POST /api/checkins/confirm.php` + `POST /api/qr/pass.php` (`issue`/`reissue`) return `410 Gone`:
  - `GET /api/qr/pass.php:82-168` queries `qr_credentials WHERE status = 'ACTIVE'`, calls `reconstructQrTokenForCredential()` + `generateQrSvgDataUrl()`, and returns live `qrPayload` + `qrDataUrl` + `printUrl`.
  - `dashboard/pass-print.php:82-84, 323-351` renders `<div id="pass-qr-container" data-qr-payload="...">` with `qrcode-gen.js` and prints *"Present this QR pass or quote your Credential ID"*.
  - `assets/js/admin-portal.js` still renders `"QR / Check-in"` (`line 1507`), `"Active QR credential"` + `data-qr-payload` + link to `/dashboard/pass-print.php` (`lines 1710-1715`), `"Saved. QR credential ... issued."` (`line 1750`), `"Revoke QR pass"` (`line 1761`), and `"Open RGST QR Scanner"` (`line 3105`, linking to `/rgst`, which is now the manual Registration Desk). `admin/index.php:196` still loads `/assets/js/qrcode-gen.js`.
- **Remediation:**
  1. Deprecate `GET /api/qr/pass.php` (return `410 Gone` for QR pass payloads, or Registration ID confirmation summary without `qrPayload`/`qrDataUrl`) and replace `/dashboard/pass-print.php` with a Registration ID confirmation sheet (zero QR payload/canvas) or `410` notice.
  2. Remove QR credential canvas, `pass-print.php` link, `"QR / Check-in"` column header, `"QR credential issued"` toast, and `"Open RGST QR Scanner"` label from `assets/js/admin-portal.js` (rename to `"Open Registration Desk"`). Keep `qrcode-gen.js` on `/admin` strictly scoped to TOTP MFA setup (`line 3460`), or render MFA QR with a dedicated MFA-only utility.
  3. Optionally archive/delete legacy `assets/js/registration-wizard.js` (`LOW-04`).

---

### [HIGH-04] Attendance Desk Concurrency Race, Over-Broad D-03 Override, Gate Mismatch & Scope Acceptance
- **Severity:** `HIGH`
- **Locations:**
  - `api/attendance/correct.php:43-57, 262-264, 267-337, 428-464`
  - `api/attendance/mark.php:43-57, 324-330, 401-466`
  - `api/attendance/index.php:290-297`
  - `rgst/index.php:41-64`
- **Evidence:**
  1. **Concurrency race / duplicate correction logs:**
     - `api/attendance/correct.php` reads `$record` *outside* the transaction (`267-337`), evaluates `$previousStatus`, and inside `$pdo->beginTransaction()` (`428-446`) executes `UPDATE attendance_records SET status = :new_status ... WHERE id = :id` **without** `SELECT ... FOR UPDATE` and without `AND status = :prev_status` / `rowCount()` verification. Concurrent calls (e.g., double-click or two actors) can both update and write **two** rows into `attendance_correction_logs` (`448-462`).
     - `api/attendance/mark.php:401-466` (`REVOKED -> PRESENT` reinstate path) similarly calls `pfgAttMarkFetchRecord()` inside a transaction without `FOR UPDATE`, allowing concurrent reinstate requests to emit duplicate `ATTENDANCE_REINSTATED` `attendance_correction_logs`. (Fresh insert in Case 3 is protected by `UNIQUE KEY uq_attendance_reg_date_scope`.)
  2. **Over-broad D-03 override privilege:** `pfgAttMarkIsOverrideActor()` and `pfgAttCorrIsOverrideActor()` (`mark.php:51-56`, `correct.php:51-56`, `rgst/index.php:58-64`) grant anytime-override to any `ADMIN` with `PERMISSIONS['CHECKINS_CONFIRM']` (`'checkins.confirm'`) or `PERMISSIONS['REGISTRATIONS_WRITE']`. Combined with `CRIT-01` (every `ADMIN` has `checkins.confirm` and `registrations.write` by default), every `ADMIN` account gets full D-03 override authority regardless of profile; furthermore, `checkins.confirm` conceptually represents normal desk marking, not override authority.
  3. **Desk UI vs API read-gate mismatch:** `rgst/index.php:43-51` admits `ADMIN` users holding `PERMISSIONS['CHECKINS_CONFIRM']` or `PERMISSIONS['QR_SCAN']`, whereas `api/attendance/index.php:291-296` only checks `'checkins.read'`, `'registrations.read'`, `PERMISSIONS['ATTENDANCE_READ_FULL']`, or `PERMISSIONS['REGISTRATIONS_READ_ALL']` — causing authorized `/rgst` staff without full read permissions to load the page and immediately receive HTTP `403`.
  4. **Client-controlled `attendanceScope`:** `api/attendance/mark.php:325-330` and `api/attendance/correct.php:262-264` accept any regex-matching string (`^[A-Z0-9_-]{2,64}$`) rather than restricting to configured `$cfg['defaultScope']` (or configured scope list), allowing fragmented attendance rows invisible to default desk queries.
- **Remediation:**
  1. Lock the `attendance_records` row inside the transaction with `SELECT ... FOR UPDATE` in both `correct.php` and `mark.php` (plus `WHERE id = :id AND status = :prev_status` checking `rowCount() === 1`).
  2. Restrict D-03 anytime-override exclusively to `SUPERADMIN` or `ADMIN` with `registrations.write` / dedicated `attendance.override` permission (remove `PERMISSIONS['CHECKINS_CONFIRM']` from `IsOverrideActor`).
  3. Align `rgst/index.php` and `api/attendance/index.php` permission checks cleanly (`ATTENDANCE_READ_FULL` or `REGISTRATIONS_READ_ALL` for `ADMIN` desk access, `RGST` role for desk operators).
  4. Enforce an allowlist on `attendanceScope` (accepting only `$cfg['defaultScope']` or explicit configured session scopes).

---

### [HIGH-05] Participant Dashboard Announcements Query Fails Silently Due to Column Mismatch
- **Severity:** `HIGH`
- **Locations:**
  - `api/account/dashboard.php:744-768`
  - `database/migrations/000_baseline_schema.sql:804-816`
  - `api/admin/announcements.php:35-38`, `index.php:467`
- **Evidence:**
  ```sql
  -- database/migrations/000_baseline_schema.sql:804-816
  CREATE TABLE IF NOT EXISTS `announcements` (
    `id` CHAR(36) NOT NULL, `edition_id` CHAR(36) NOT NULL,
    `title` VARCHAR(200) NOT NULL, `message` TEXT NOT NULL,
    `severity` VARCHAR(32) NOT NULL DEFAULT 'INFO', ...
  ```
  Yet `api/account/dashboard.php:744-762` executes:
  ```php
  $annSql = "SELECT id, title, body, priority, published_at, expires_at
             FROM announcements WHERE status = 'PUBLISHED' ...";
  ...
  'body' => (string)$ann['body'], 'priority' => (string)($ann['priority'] ?? 'NORMAL'),
  ```
  And catches the resulting `PDOException` (`SQLSTATE[42S22]: Unknown column 'body'`) silently at lines `766-768`.
- **Impact:**
  Published festival announcements never appear on the Participant Dashboard (always returns `[]`), despite working on `index.php` (`line 467` selects `message, severity`).
- **Remediation:**
  Update `api/account/dashboard.php:744-762` to query `message AS body, severity AS priority` (matching `000_baseline_schema.sql` and `api/admin/announcements.php`) and log unexpected query failures via `error_log()`.

---

### [HIGH-06] Stored XSS via Unescaped JSON-LD `<script type="application/ld+json">` Output
- **Severity:** `HIGH`
- **Locations:**
  - `includes/header.php:134-136, 190`
  - `index.php:597-612, 766-778`
- **Evidence:**
  ```php
  // includes/header.php:134-136, 190
  $schemaJsonOutput = isset($structuredData) && is_array($structuredData)
      ? json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      : json_encode($defaultSchemaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  ...
  <script type="application/ld+json"><?= $schemaJsonOutput ?></script>
  ```
  On `index.php:597-612, 766-778`, `$structuredData` embeds published `faq_items.question` and `faq_items.answer` from the database without `JSON_HEX_TAG`.
- **Impact:**
  Any `</script><script>...</script>` sequence in an FAQ entry (or future CMS-driven `$structuredData`) breaks out of `<script type="application/ld+json">` and executes arbitrary JavaScript in visitors' and logged-in admins'/participants' browsers (enabling privilege escalation from `content_manager` to Superadmin).
- **Remediation:**
  Use `pfgSafeJsonForScript(...)` (or add `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`) in `includes/header.php:134-136`.

---

### [HIGH-07] Dashboard Quick-Edit & Resubmit Modal (`#dash-edit-form`) Fails Validation Unconditionally
- **Severity:** `HIGH`
- **Locations:**
  - `assets/js/participant-dashboard.js:1394-1410`
  - `api/account/registrations.php:315, 626-634`
  - `includes/registration-validators.php:755-845`
- **Evidence:**
  `#dash-edit-form` (`participant-dashboard.js:1394-1407`) submits `{ registrationId, attendingDays, phone, organizationName, cityDistrict, state, emergencyContactName, emergencyContactPhone, resubmissionNote }` via `PATCH /api/account/registrations`. On the server (`api/account/registrations.php:315`), `pfgValidateRegistrationPayload($pdo, $categoryCode, $body, $userRow, true)` still enforces category-specific required fields (`ageGroup` for `ATTENDEE`, MSU/availability fields for `VOLUNTEER`, qualifications/availability/language fields for `SLI`), `isPersonWithDisability`, and `emergencyContactRelationship` without loading or merging the existing child table row. Additionally, `api/account/registrations.php:626-634` ignores `resubmissionNote`.
- **Impact:**
  Participants cannot edit or resubmit applications from the Dashboard modal — every save fails with HTTP `400` (`Please review the highlighted fields`).
- **Remediation:**
  In `api/account/registrations.php` `PATCH`, merge omitted fields from the existing `registrations`, `attendee_details`/`volunteer_applications`/`sli_applications`, `participant_disability_disclosures`, and `accommodation_requests` rows before validation (or support partial dashboard profile-logistics patching cleanly) and persist/forward `resubmissionNote` to status history `public_message` when resubmitting from `ADDITIONAL_INFO_REQUIRED`.

---

### [MED-01] Signup Endpoint Leaks Account Existence via HTTP Status Code (`202` vs `201`)
- **Severity:** `MEDIUM`
- **Location:** `api/auth/signup.php:122, 173, 188`
- **Evidence:** Existing emails (`line 122`) and concurrent duplicates (`line 173`) return `$genericBody` with HTTP `202`, whereas newly created accounts (`line 188`) return the identical `$genericBody` with HTTP `201`.
- **Impact:** Unauthenticated callers can trivially enumerate registered participant/staff emails by observing `202` vs `201` status codes despite uniform response bodies and dummy bcrypt hashing.
- **Remediation:** Return HTTP `202` on all successful/duplicate signup paths (`lines 122, 173, 188`).

---

### [MED-02] Email-Change Availability Oracle via `pendingEmail` Reflection
- **Severity:** `MEDIUM`
- **Location:** `api/account/profile.php:46, 246-258, 316`
- **Evidence:** When `emailChangeRequested` is true, lines `246-258` only update `users.pending_email` if `$newEmail` is not taken (`!$taken`), then line `316` returns `pfgProfileLoad(...)` which exposes `'pendingEmail'` (`line 46`).
- **Impact:** Any authenticated user can probe whether arbitrary email addresses exist in `users` by requesting an email change and checking whether `response.user.pendingEmail === submittedEmail` (free) or remains `null`/previous (taken).
- **Remediation:** Avoid returning the newly checked collision state in `PATCH` output (or store/echo a generic pending verification indicator without confirming uniqueness collision).

---

### [MED-03] Password Reset Lacks Complexity Policy & Non-Atomic Token Consumption
- **Severity:** `MEDIUM`
- **Location:** `api/auth/password-reset.php:130-132, 137-189`
- **Evidence:**
  1. Lines `130-132` only validate `strlen($newPassword) >= 8 && strlen($newPassword) <= 128`, skipping `pfgAuthPasswordPolicyError()` (letters + digits requirement enforced in `signup.php` and `profile.php`).
  2. Lines `137-189` read the token outside a transaction, update `users.password_hash` at `168-179`, and delete reset tokens later at `185-188` without atomic `rowCount()` checking or `FOR UPDATE`.
- **Impact:** Allows weak resets (`"12345678"`) and creates a race condition where concurrent completion requests could both consume the same reset token before deletion (triggering duplicate password-change alerts/session resets).
- **Remediation:** Call `pfgAuthPasswordPolicyError($newPassword)`, wrap token consumption in a DB transaction, atomically consume the token (`UPDATE password_reset_tokens SET used_at = :now WHERE id = :id AND used_at IS NULL AND expires_at > :now` / `DELETE FROM auth_accounts WHERE id = :id AND ...` with `rowCount() === 1`) *before* updating `users.password_hash`.

---

### [MED-04] CMS Edition Scoping / Enum Validation Gaps (`cms.php`) & Unbounded Legacy Duty Fields (`registrations.php`)
- **Severity:** `MEDIUM`
- **Locations:**
  - `api/admin/cms.php:536-537, 593-615` (and sibling PATCH/DELETE handlers)
  - `api/admin/registrations.php:1059-1146`
- **Evidence:**
  - `api/admin/cms.php` accepts arbitrary uppercase `severity` / `status` strings without allowlists (`536-537, 603`), looks up `PATCH`/`DELETE` targets by `id` alone without enforcing `edition_id = :eid` on edition-scoped entities, and performs hard SQL `DELETE` on CMS records.
  - `api/admin/registrations.php:1059-1146` writes legacy `volunteerAssignment` and `sliAssignment` strings (`assignedDepartment`, `assignedResponsibility`, `assignedSupervisor`, `assignedVenue`, `assignedShift`, `reportingTime`, `assignmentNotes`, `dutyTimes`) without length or type validation.
- **Impact:** Possible persistence of invalid CMS statuses (`PUBLISHED` typo/arbitrary values), cross-edition modification if multiple editions coexist, irrecoverable accidental CMS deletions, and SQL `Data too long` `500` errors on oversized legacy assignment inputs.
- **Remediation:** Allowlist `status` and `severity` values in `api/admin/cms.php`, enforce `edition_id = :eid` on edition-owned CMS tables, and validate string types/lengths on `volunteerAssignment` / `sliAssignment` in `api/admin/registrations.php`.

---

### [MED-05] `pfgProfileForbiddenPermissions()` Omits `audit_logs.read` Despite Migration `009` Contract
- **Severity:** `MEDIUM`
- **Locations:**
  - `includes/auth.php:921-927`
  - `api/admin/permission-profiles.php:10, 44`
  - `api/admin/users.php:86`
  - `database/migrations/009_phase8_9_custom_permission_profiles_and_attendance.php:67-68`
- **Evidence:** Migration `009:67-68` documents *"Superadmin-only authorities (admin_accounts.manage, settings.manage, audit_logs.read) are intentionally never included"*, whereas `pfgProfileForbiddenPermissions()` (`includes/auth.php:921-927`) forbids only `ADMIN_ACCOUNTS_MANAGE` and `SETTINGS_MANAGE`.
- **Impact:** A permission profile or custom `ADMIN` permission array can grant `audit_logs.read`, exposing IP addresses, user agents, and metadata across all accounts/actions to non-`SUPERADMIN` staff.
- **Remediation:** Include `PERMISSIONS['AUDIT_LOGS_READ']` in `pfgProfileForbiddenPermissions()` if `audit_logs.read` is intended to remain strictly Superadmin-only.

---

### [MED-06] Clearing Accommodation Preferences on Registration Edit Leaves Prior Request Row Active
- **Severity:** `MEDIUM`
- **Location:** `api/account/registrations.php:570-622`
- **Evidence:** Inside `PATCH /api/account/registrations`, `if ($hasAccomm)` updates `accommodation_requests` (`574-622`), so when a participant edits their registration and clears all accommodation checkboxes, `accommodationFreeText`, and `escortAccompanying` (`$hasAccomm === false`), the update block is skipped entirely.
- **Impact:** Previously requested accommodations stay `REQUESTED` in coordinator queues even after the participant removes them.
- **Remediation:** On `PATCH`, if accessibility payload keys are submitted and `$hasAccomm === false`, explicitly clear/withdraw existing `REQUESTED` rows (`selected_types = '[]'`, decrypted/cleared free text, or status `'CANCELLED'`/`'WITHDRAWN'`) when permitted.

---

### [MED-07] Dashboard Email-Change & MFA-Disable Forms Omit Required `currentPassword`; MFA Enable Drops Recovery Codes
- **Severity:** `MEDIUM`
- **Locations:**
  - `dashboard/index.php:729-753, 873-895`
  - `assets/js/participant-dashboard.js:1661, 1769-1785`
  - `api/account/profile.php:180-182, 220-223`
  - `api/auth/mfa.php:78-87, 287`
- **Evidence:**
  - `#dash-email-form` (`dashboard/index.php:729-753`) collects only `#prof-new-email` and sends `{ email: newEmail }` (`participant-dashboard.js:1661`), while `api/account/profile.php:180-182, 220-223` rejects email changes without `currentPassword` (HTTP `400`).
  - `#dash-mfa-disable-box` (`dashboard/index.php:873-895`) collects only `#mfa-disable-code` and sends `{ action: 'disable', code: code }` (`participant-dashboard.js:1769`), while `api/auth/mfa.php:78-87, 287` requires `currentPassword` (HTTP `403`).
  - On `action: 'enable'`, `participant-dashboard.js:1769-1785` discards `res.data.recoveryCodes` instead of displaying the single-use backup codes.
- **Impact:** Participants with passwords cannot initiate email changes or disable optional MFA from their dashboard, and newly MFA-enrolled participants never see their one-time recovery codes.
- **Remediation:** Add `currentPassword` inputs to `#dash-email-form` and `#dash-mfa-disable-box`, pass `currentPassword` in `participant-dashboard.js`, and display `res.data.recoveryCodes` once after MFA activation.

---

### [LOW-01..LOW-05] Hygiene & Defense-in-Depth Findings
- **`LOW-01` (Legacy Staff-Owned Claim Status Oracle — `api/auth/claim-legacy.php:126-135`):** `POST /api/auth/claim-legacy` returns `403` when a requested `registrationId`/`registrationCode` belongs to a staff user vs `404` otherwise, allowing authenticated users to probe whether a given Registration ID belongs to staff. *Fix:* Return uniform `404` or generic refusal without distinguishing staff ownership to arbitrary callers.
- **`LOW-02` (API-Level URL Validation on Legacy `video_demo_url` — `api/account/registrations.php:108`):** `videoDemoUrl` is returned verbatim from `sli_applications.video_demo_url` (client JS validates with `safeUrl`/`safeHref`, and `dashboard.php:253` uses `pfgDashboardSafeUrl`, but `/api/account/registrations.php` does not sanitize legacy rows). *Fix:* Filter `$sli['video_demo_url']` through `pfgValidateSafeUrl(..., false)`.
- **`LOW-03` (Verification Parity on Registration `PATCH` — `api/account/registrations.php:277-307`):** Unlike `POST /api/registrations:336-341`, `PATCH` does not explicitly verify `email_verified_at` is non-null. *Fix:* Add the same `!empty($userRow['email_verified_at'])` guard on `PATCH` as defense-in-depth.
- **`LOW-04` (Dead Legacy Wizard Asset — `assets/js/registration-wizard.js:1602-1603`):** Unlinked legacy file still contains `"Digital QR Entry Pass will be generated on screen"`. Verified via cross-file search that no active `.php` page includes `registration-wizard.js`. *Fix:* Delete or archive this file to prevent accidental re-inclusion.
- **`LOW-05` (Draft/Cancelled Session Title Exposure in Duty Details — `api/account/dashboard.php:351-360`):** Duty assignment mapping computes `$sessionIsPublic` (`CONFIRMED`/`PUBLISHED`), yet still populates `sessionTitle`, `sessionTrack`, and `sessionDayNumber` when the linked `festival_sessions.status` is `DRAFT` or `CANCELLED`. *Fix:* Gate or mask `sessionTitle`/`sessionTrack` when `!$sessionIsPublic` (or omit unpublished session titles cleanly).

---

## §19 Mandatory Audit Checklist Verification Matrix

### §19.1 Authentication & Verification
| Control | Status | Verified Evidence |
|---|---|---|
| Signup/login/recovery timing & uniform responses | **PARTIAL (`MED-01`, `MED-02`)** | `api/auth/login.php:72-91` performs `pfgDummyPasswordVerify()` & password check *before* lockout (`423` at `138-148`) or verification check (`403` at `176-187`), returning uniform `401` (`81, 102, 134`). `resend-verification.php` & `password-reset` request return uniform `200`. **Defect:** `signup.php:122,173` vs `188` leaks `202` vs `201`; `profile.php:46,248-250,316` leaks email availability via `pendingEmail`. |
| Email verification token entropy, HMAC, expiry, single-use atomicity, email-change safety | **PASS** | `api/auth/_participant.php:22,86-98` uses `random_bytes(32)` (256-bit), 30m TTL, HMAC-SHA256 via `hashToken()`, and invalidates prior unused tokens. `verify-email.php:30-44` GET is non-consuming peek; POST `72-78` performs atomic `UPDATE email_verification_tokens SET used_at = :now WHERE id = :id AND used_at IS NULL AND expires_at > :now2` (`rowCount() === 1`). `EMAIL_CHANGE` re-checks uniqueness inside transaction (`121-126`), clears `pending_email`, revokes other sessions (`169-171`), and alerts old email (`172-174`). |
| D-20 mandatory MFA & Staff portal isolation | **PASS** | `login.php:158-214` isolates `staff` vs `participant` portals cleanly. `login.php:258-274` enforces TOTP window with `consumeTotpSliceIfFresh()` replay protection and single-use recovery codes (`consumeMfaRecoveryCode`). `enforceStaffMfaGate()` in `includes/auth.php` locks un-enrolled `SUPERADMIN`/`ADMIN` staff onto `/admin?mfa_setup=1` (`403` on API calls). `mfa.php` blocks `disable` for `SUPERADMIN`/`ADMIN`. |
| Legacy claim safety (non-staff only, verified email, CSRF) | **PASS (`LOW-01`)** | `claim-legacy.php:39-50, 90` requires verified `PARTICIPANT`-only account (blocks staff callers) + CSRF + rate limit. SQL (`53-67`) restricts claimable rows to matching lowercase email, `(lu.password_hash IS NULL OR lu.password_hash = '')`, zero staff roles (`NOT EXISTS`), and zero prior claims (`uq_lrc_reg`). Minor `LOW-01`: `403` oracle for requested staff-owned codes. |

### §19.2 Authorization & Permission Profiles
| Control | Status | Verified Evidence |
|---|---|---|
| `RGST` & `PARTICIPANT` blocked from profiles & `customPermissions` | **PASS** | `includes/auth.php:875, 897` ignores `custom_permissions` unless `ADMIN`/`SUPERADMIN` and resolves `permission_profile_id` strictly when `ADMIN` without `SUPERADMIN`. `api/admin/users.php:483-488` rejects profile/custom-permission assignment to `RGST`/`PARTICIPANT` with `400`. |
| `ADMIN` blocked from escalating to `SUPERADMIN` / `admin_accounts.manage` / `settings.manage` | **FAIL (`CRIT-01`, `HIGH-01`, `MED-05`)** | `permission-profiles.php:51-55` and `users.php:97-99` enforce `pfgProfileForbiddenPermissions()` (`admin_accounts.manage`, `settings.manage`), **however** `CRIT-01` grants all 17 `DEFAULT_ROLE_PERMISSIONS['ADMIN']` additively regardless of profile, `HIGH-01` breaks `programme.manage`/`communications.manage`, and `MED-05` omits `audit_logs.read` from forbidden profile permissions. |
| Session revocation on role/profile/password/active-status change | **PASS** | `api/admin/users.php` & `permission-profiles.php` call `revokeAllUserSessions()` on profile updates, role/profile/active-status/password changes; `profile.php:270-276` & `password-reset.php:182` revoke sessions on password change/reset. |
| CSRF enforcement BEFORE every DB mutation | **PASS** | `requireCsrfToken($user)` runs before state-changing queries across `api/auth/logout.php`, `claim-legacy.php`, `mfa.php`, `account/*`, `admin/*`, `attendance/mark.php`, `attendance/correct.php`, `registrations/index.php`. |
| Sensitive disability & accommodation data encrypted (`enc:v1:`), gated (`accommodations.read`), audited (`SENSITIVE_DATA_READ`) | **PASS** | `includes/config.php` enforces AES-256-GCM (`enc:v1:`). `api/admin/registrations.php:594-641` & `api/admin/accommodations.php:63, 130-132, 226-230` gate decryption behind `PERMISSIONS['ACCOMMODATIONS_READ']`, encrypt coordinator notes, and log `SENSITIVE_DATA_READ`. (Remediating `CRIT-01` is essential so non-accommodation Admins cannot hold `accommodations.read` via default `ADMIN` role permissions.) |

### §19.3 Database Migrations `007`–`010`
| Control | Status | Verified Evidence |
|---|---|---|
| MariaDB 10.4+ & MySQL 8.0 portability & idempotency | **PASS** | Migrations `007`–`010` (`database/migrations/007_*..010_*`) and `includes/migrations.php` use `GET_LOCK`, `pfgDbTableExists()`, `pfgDbColumnExists()`, `pfgDbEnsureIndex()`, `pfgDbEnsureForeignKey()` via `information_schema`, `INSERT IGNORE`, and compatible `InnoDB` `utf8mb4` types. |
| Non-destructive legacy preservation (0 dropped rows/tables) | **PASS** | `008:26-30` sets `is_active = 0` on `category_tier = 'LEGACY'` without deleting rows or historical registrations; `007` and `010` only add nullable columns/tables cleanly; rollback scripts stored separately. |
| Zero `ON DELETE CASCADE` on participant/audit records | **PASS** | Verified across `007`–`010` (`grep ON DELETE CASCADE`): zero occurrences in `007`–`010`; all participant & audit foreign keys (`fk_evt_user`, `fk_lrc_user`, `fk_lrc_registration`, `fk_attendee_details_reg`, `fk_rsh_registration`, `fk_duty_edition`, `fk_duty_registration`) use `ON DELETE RESTRICT`, while optional user/session/venue links (`fk_rsh_actor`, `fk_app_created_by`, `fk_users_perm_profile`, `fk_duty_session`, `fk_duty_venue`, `fk_duty_assigned_by`) use `ON DELETE SET NULL`. |

### §19.4 Three-Category Registration & Lifecycle
| Control | Status | Verified Evidence |
|---|---|---|
| Legacy categories blocked server-side (`category_tier='PRIMARY' AND is_active=1`) | **PASS** | `api/registrations/index.php:354-365` enforces `in_array($categoryCode, PFG_PRIMARY_CATEGORIES, true)` and queries `WHERE ... category_tier = 'PRIMARY' AND is_active = 1`. |
| Zero QR credentials issued (`D-01`), atomic Reg ID, CSRF, verified-email | **PARTIAL (`HIGH-03`, `LOW-03`)** | `api/registrations/index.php:328, 336-341, 1048` enforces CSRF, verified email, atomic Registration ID sequence (`includes/registration-id.php`), idempotency keys, and issues zero QR credentials (`qrPass => null`). **Gap:** `GET /api/qr/pass.php`, `dashboard/pass-print.php`, and Admin Portal UI (`HIGH-03`) still reconstruct/render legacy QR credentials. |
| State-machine consistency (`application-lifecycle.php` vs `admin-common.php`) | **FAIL (`HIGH-02`)** | Significant transition divergence between `admin-common.php:249-270` and `application-lifecycle.php:63-112`, `RESUBMITTED` fast-track mismatch, missing `'actorType' => 'PARTICIPANT'` in `api/account/registrations.php`, and dashboard action mismatches. |
| XSS & safe URL validation | **FAIL (`HIGH-06`, `LOW-02`)** | Most views use `pfgSafeJsonForScript()` and `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, and venues/partners/notifications/dashboard validate `http(s)` / safe relative URLs (`admin-portal.js:34`, `participant-dashboard.js:73`). **Defect (`HIGH-06`):** `includes/header.php:134-136,190` outputs `$structuredData` (including DB `faq_items` on `index.php`) via `json_encode` without `JSON_HEX_TAG` inside `<script type="application/ld+json">`. Minor `LOW-02`: `/api/account/registrations.php:108` returns raw `videoDemoUrl`. |

### §19.5 Manual Registration Desk
| Control | Status | Verified Evidence |
|---|---|---|
| Zero QR scanner dependency & atomic duplicate prevention (new marks) | **PASS (`HIGH-04` race on revoke/reinstate)** | `rgst/index.php` and `assets/js/rgst-desk.js` operate exclusively on Registration ID/name search + checkboxes without any camera/QR scanner (`HIGH-03` notes only that Admin Portal still mislabels the link as `"Open RGST QR Scanner"`). `attendance_records` enforces `UNIQUE KEY uq_attendance_reg_date_scope` (`migration 003:78`) preventing duplicate new marks. **Defect (`HIGH-04`):** `correct.php` & `mark.php` reinstate lack `FOR UPDATE` locking. |
| Eligibility (`CONFIRMED`/`APPROVED`), D-03 15-min RGST undo window vs Admin override, immutable `attendance_correction_logs` | **PARTIAL (`HIGH-04`)** | `mark.php:368-379` & `correct.php:385-393` enforce `CONFIRMED`/`APPROVED`. `correct.php:347-382` enforces original marker + `elapsedSeconds <= 900` for non-override RGST operators and appends to `attendance_correction_logs` (with zero UPDATE/DELETE endpoints on correction logs). **Defect (`HIGH-04`):** `CHECKINS_CONFIRM` grants Admin override; `attendanceScope` unallowlisted; `/rgst` UI vs `/api/attendance` GET gate mismatch. |
| Strict minimum-necessary data (zero medical/disability/emergency/review notes leaked to RGST) | **PASS** | `api/attendance/index.php:435-446` selects strictly `id, registration_code, full_name, city_district, state, phone, category_code, status, attending_days` — zero `review_notes`, emergency contact, disability disclosures, or accommodation fields are queried or serialized. |

### §19.6 Participant Dashboard & Public Site
| Control | Status | Verified Evidence |
|---|---|---|
| Owner-only IDOR scoping | **PASS** | `api/account/dashboard.php`, `registrations.php`, `drafts.php`, `accommodations.php`, `notifications.php` derive `$userId = (string)$user['id']` exclusively from session auth (`getCurrentUser` / `requireAuth`) and filter every query/mutation with `WHERE ... user_id = :uid`. |
| Zero `review_notes` / `override_reason` exposure | **PASS** | `api/account/dashboard.php:266, 289-319, 448-451` & `api/account/registrations.php` project strictly `public_review_note` and `public_message`, excluding internal `review_notes`, `reason`, and `duty_assignments.override_reason`. |
| Partner `consent_verified=1`, `DRAFT`/`CANCELLED` exclusion, POST-only CSRF logout, dashboard UI & announcements | **PARTIAL (`HIGH-05`, `HIGH-07`, `MED-07`, `LOW-05`)** | `index.php:524-525` gates partners with `p.status = 'PUBLISHED' AND p.consent_verified = 1`. `programme.php:252-253, 320` and `dashboard.php:639-640, 694` require `status IN ('CONFIRMED', 'PUBLISHED')`, excluding `DRAFT`/`CANCELLED` programme entries. `includes/header.php` + `includes/footer.php:593-616` + `api/auth/logout.php:19-30` enforce POST-only + CSRF logout (`405` on GET). **Defects:** Dashboard announcements (`HIGH-05`), `#dash-edit-form` quick-edit (`HIGH-07`), email-change/MFA-disable UI (`MED-07`), and duty session title masking when `!$sessionIsPublic` (`LOW-05`). |

---

## Recommended Fix Sequence Before Final Acceptance

1. **Patch `HIGH-06` (Immediate Stored XSS):** Switch `includes/header.php:134-136` JSON-LD serialization to `pfgSafeJsonForScript(...)` (adding `JSON_HEX_TAG`).
2. **Patch `CRIT-01` & `HIGH-01` together:** Fix `getUserRolesAndPermissions()` so profiled `ADMIN` accounts do not inherit `DEFAULT_ROLE_PERMISSIONS['ADMIN']`, register `PROGRAMME_MANAGE` and `COMMUNICATIONS_MANAGE` in `PERMISSIONS`, update `admin-common.php` and `009` profiles (via migration `011`), and decide `audit_logs.read` profile restriction (`MED-05`).
3. **Patch `HIGH-02`, `HIGH-07` & `MED-06`:** Pass `'actorType' => 'PARTICIPANT'` in `api/account/registrations.php`, unify `admin-common.php` with `includes/application-lifecycle.php` (handling `RESUBMITTED`, `ADDITIONAL_INFO_REQUIRED`, `CONFIRMED`, and `WITHDRAWN` consistently), merge existing category/child records on dashboard `PATCH` (and persist `resubmissionNote`), align `dashboard.php` + `apply/*.php` edit/withdraw flags, and handle clearing accommodations on `PATCH`.
4. **Patch `HIGH-04`:** Add `SELECT ... FOR UPDATE` + conditional `status` check to `api/attendance/correct.php` and `api/attendance/mark.php` reinstate path, remove `CHECKINS_CONFIRM` from `IsOverrideActor`, align `rgst/index.php` and `api/attendance/index.php` read permissions, and allowlist `attendanceScope`.
5. **Patch `HIGH-03`, `HIGH-05`, `MED-07` & `LOW-05`:** Retire QR payload reconstruction in `GET /api/qr/pass.php` and `/dashboard/pass-print.php`, clean up Admin Portal QR/scanner UI strings/renders (`assets/js/admin-portal.js`, `admin/index.php`), fix `announcements.message AS body, severity AS priority` in `api/account/dashboard.php`, wire `currentPassword` + `recoveryCodes` display into dashboard security UI, and mask non-public duty session titles.
6. **Patch Auth Hygiene (`MED-01`, `MED-02`, `MED-03`) & Admin Validation (`MED-04`, `LOW-01..LOW-04`)**, then re-run PHP syntax lint and end-to-end integration verification (including profile-scoped `ADMIN` negative RBAC tests, JSON-LD XSS regression tests, concurrency tests on attendance mark/correct, dashboard edit/email/MFA tests, and staff-participant self-service resubmission/withdrawal tests).
