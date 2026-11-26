# Shared Integration Contracts — Purple Festival Gujarat 2027 (Phases 5–18)

**Authority:** Master Orchestrator (`swarm/phases-5-18`)  
**Rule:** Every agent MUST obey these exact routes, database tables, column names, status enums, permission codes, and JSON shapes so parallel workstreams integrate cleanly with zero contract drift.

---

## 1. Centrally Allocated Database Migrations (`database/migrations/`)

| Version | File | Scope |
|---|---|---|
| `007` | `007_phase5_participant_auth_and_verification.php` (+ `_rollback.sql`) | `email_verification_tokens` table; `users` additive columns (`city_district`, `state`, `preferred_language`, `pending_email`, `account_consent_at`, `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`); `legacy_registration_claims` audit table |
| `008` | `008_phase6_7_three_category_registration_and_lifecycle.php` (+ `_rollback.sql`) | Non-destructively set `registration_categories.is_active = 0 WHERE category_tier = 'LEGACY'`; create `attendee_details`, `registration_status_history`, `registration_drafts`; add columns to `registrations`, `accommodation_requests`, `volunteer_applications`, `sli_applications` per `REGISTRATION_DATABASE_MAPPING.csv`; seed 5 additional `accommodation_types` (`INSERT IGNORE`) |
| `009` | `009_phase8_9_custom_permission_profiles_and_attendance.php` (+ `_rollback.sql`) | `admin_permission_profiles` table + `users.permission_profile_id` nullable FK (`ON DELETE SET NULL`) + 7 default operational profiles (within `ADMIN` tier only); verify `attendance_records` / `attendance_correction_logs` indexes |
| `010` | `010_phase12_programme_venues_and_duty_assignments.php` (+ `_rollback.sql`) | Evolve `venues` (`operational_notes`, `updated_at`) & `festival_sessions` (`description`, `volunteers_needed`, `slis_needed`, `updated_at`) non-destructively; create `duty_assignments` table (`edition_id`, `registration_id`, `participant_category`, `session_id`, `venue_id`, `role_title`, `department`, `duty_date_ist`, `shift_code`, `start_time`, `end_time`, `reporting_supervisor`, `reporting_time`, `reporting_instructions`, `pairing_group_id`, `status`, `assigned_by_user_id`, `override_reason`) |

> **Portability Rule:** Every migration MUST work cleanly on BOTH **MariaDB 10.4.32** and **MySQL 8.0** (InnoDB `utf8mb4_unicode_ci`, zero `SKIP LOCKED`, zero `ON DELETE CASCADE` on participant/audit records, idempotent column/index existence checks).

---

## 2. Authentication, Verification & Account Endpoints (Phase 5)

| Route | Method(s) | Auth / CSRF | Contract |
|---|---|---|---|
| `/api/auth/signup` | `POST` | Public + IP rate limit | Creates `PARTICIPANT` account (`fullName`, `email`, `phone`, `password`, `confirmPassword`, `consentAccepted`), hashes password (bcrypt cost 12), issues single-use 30-min `email_verification_tokens` (SHA-256 HMAC stored), enqueues `ACCOUNT_VERIFICATION` via `pfgEnqueueNotification()` with `ephemeralSecret` (`actionUrl`). Returns `201` (uniform anti-enumeration when email already exists -> returns `202` generic check-your-email response without creating duplicate or leaking account existence!). |
| `/api/auth/verify-email` | `POST` / `GET` (`POST` preferred for consumption, `GET` page renders confirm UI) | Public + rate limit | Consumes token atomically (`used_at = NOW()`), sets `users.email_verified_at = NOW()`, handles `EMAIL_CHANGE` purpose by promoting `pending_email -> email` if unique, enqueues `ACCOUNT_WELCOME_ACTIVATED` (on signup), audits `EMAIL_VERIFIED`. |
| `/api/auth/resend-verification` | `POST` | Public or auth + rate limit (`3/hr`) | Invalidates prior unused `SIGNUP_VERIFY` tokens for user, issues fresh token, enqueues `EMAIL_VERIFICATION_RESEND`. Uniform `200` response. |
| `/api/auth/login` | `POST` | Public + rate limit | Accepts BOTH staff (`SUPERADMIN`/`ADMIN`/`RGST` with mandatory D-20 MFA for SUPERADMIN/ADMIN) AND verified `PARTICIPANT` accounts! Unverified `PARTICIPANT` receives `403` with `emailVerificationRequired: true` ONLY after valid password check (never before password check so zero enumeration!). Optional `portal: 'staff'` rejects non-staff with `403`. `PARTICIPANT` redirects to `/dashboard` (or safe `next` URL). |
| `/api/auth/claim-legacy` | `GET` / `POST` | Auth `PARTICIPANT` (`email_verified_at IS NOT NULL`) + CSRF on `POST` | Lists/claims unlinked legacy registrations matching the verified user's exact lowercased email (strictly blocking staff-owned registrations), audits `LEGACY_REGISTRATION_CLAIMED`. |
| `/api/account/profile` | `GET` / `PATCH` | Auth + CSRF on `PATCH` | Updates `fullName`, `phone`, `cityDistrict`, `state`, `country`, `preferredLanguage`, `emergencyContact*`, optional password change (revokes other sessions + enqueues `SECURITY_ALERT`). Changing `email` sets `pending_email` and sends `EMAIL_CHANGE_VERIFICATION` (does NOT swap `email` until verified!). |
| UI Pages | `GET` | — | `/login` (Participant Login), `/register` (Participant Signup), `/verify-email`, `/forgot-password`, `/reset-password`, `/staff/login` (Staff Portal Login w/ MFA). Keep `/auth/login` redirecting cleanly to `/login`. |

---

## 3. Three-Category Registration & Lifecycle Endpoints (Phases 6–7)

| Route | Method(s) | Auth / CSRF | Contract |
|---|---|---|---|
| `/apply` | `GET` | Auth participant recommended (shows login/register CTA if guest) | Clean **3-card Primary Category Hub**: `ATTENDEE`, `VOLUNTEER`, `SLI` (ZERO legacy cards, ZERO `ACCESSIBLE` card). Shows live status badges if user already registered in a category. |
| `/apply/attendee`, `/apply/volunteer`, `/apply/sli` | `GET` | Auth `PARTICIPANT` (verified email; redirects unauth to `/login?next=...`) | Independent category forms using shared components in `includes/form-components/`. |
| `POST /api/registrations` | `POST` | Auth `PARTICIPANT` (verified email) + CSRF + `Idempotency-Key` | Accepts ONLY `category_tier = 'PRIMARY' AND is_active = 1` (`ATTENDEE`, `VOLUNTEER`, `SLI`). Rejects legacy categories (`400`). Enforces configurable max 1 active registration per primary category per account (`D-09` default). Uses `includes/registration-validators.php` & `includes/registration-id.php` (`PFG-2027-NNNNNN`), zero QR credentials, writes canonical child tables (`attendee_details` / `volunteer_applications` / `sli_applications` / `participant_disability_disclosures` / `accommodation_requests` / `privacy_consent_records` / `registration_status_history`), enqueues `ATTENDEE_REGISTRATION_CONFIRMED` / `VOLUNTEER_APPLICATION_RECEIVED` / `SLI_APPLICATION_RECEIVED` + `in_app_notifications`. |
| `GET / PATCH / POST /api/account/registrations` | `GET`, `PATCH`, `POST` | Auth owner + CSRF on write | Lists participant's own registrations; allows editing while `DRAFT`/`SUBMITTED`/`ADDITIONAL_INFO_REQUIRED` (triggers `RESUBMITTED` when responding to info request, or `ATTENDEE_REGISTRATION_UPDATED`); allows `action=withdraw`/`cancel` with audit & `ATTENDEE_REGISTRATION_CANCELLED` email + admin alert if assigned. |
| `GET / POST /api/account/drafts` | `GET`, `POST`, `DELETE` | Auth owner + CSRF on write | Saves/loads/clears category form drafts in `registration_drafts`. |

### Canonical Application Status Machine (`includes/application-lifecycle.php`)
- **ATTENDEE:** `DRAFT -> SUBMITTED -> CONFIRMED` (instant on free policy), `CONFIRMED -> CANCELLED`, `SUBMITTED -> CANCELLED`.
- **VOLUNTEER / SLI:** `DRAFT -> SUBMITTED -> UNDER_REVIEW -> {APPROVED | REJECTED | WAITLISTED | ADDITIONAL_INFO_REQUIRED}`, `ADDITIONAL_INFO_REQUIRED -> RESUBMITTED -> UNDER_REVIEW`, `WAITLISTED -> APPROVED | REJECTED`, `SUBMITTED/UNDER_REVIEW/APPROVED/WAITLISTED -> WITHDRAWN | CANCELLED`.
- **Decoupled Dimensions:** `registrations.status` (Approval) is strictly separate from `registrations.assignment_status` (`UNASSIGNED`, `ASSIGNED`, `COMPLETED`) and `attendance_records` (Physical Attendance). Approving NEVER marks attendance.

---

## 4. Administrative Roles, Permission Profiles & Manual RGST Desk (Phases 8–9)

| Route | Method(s) | Auth / CSRF | Contract |
|---|---|---|---|
| `/api/admin/permission-profiles` | `GET`, `POST`, `PATCH`, `DELETE` | `SUPERADMIN` + MFA + CSRF | CRUD for reusable operational permission profiles (`admin_permission_profiles`) assignable **only to `ADMIN` accounts** (never `RGST` or `PARTICIPANT`). |
| `/api/admin/users` | `GET`, `POST`, `PATCH` | `SUPERADMIN` (`users.manage`) + MFA + CSRF | Supports `permissionProfileId` + `customPermissions`, prevents self-demotion, last-Superadmin removal, RGST escalation, revokes sessions on role/profile/password/active change. |
| `/api/attendance` | `GET` | `RGST` / `ADMIN` (`checkins.read`) / `SUPERADMIN` | Paginated participant search by `q` (Registration ID, Name, Phone), `category`, `status`, `day` (`2027-01-21` / `2027-01-22` / `2027-01-23`), returns minimal desk-safe fields (**NEVER** disability details, medical notes, emergency contacts, or review notes!) + current 3-day attendance flags + accurate metrics (total registrations, eligible, unique people present, per-category, per-day, total events). |
| `/api/attendance/mark` | `POST` | `RGST` / `ADMIN` (`checkins.scan`) / `SUPERADMIN` + CSRF | Atomically marks attendance in `attendance_records` (`UNIQUE(edition_id, registration_id, attendance_date_ist, attendance_scope)`). Verifies eligibility (`CONFIRMED` or `APPROVED`). Idempotent on duplicate click (`alreadyMarked: true`, HTTP `200`, 0 duplicate rows). Zero QR dependency. |
| `/api/attendance/correct` | `POST` | `RGST` (own mark within 15 min w/ reason) OR `ADMIN`/`SUPERADMIN` (anytime w/ reason) + CSRF | Updates `attendance_records.status` (`REVOKED`/`PRESENT`) and appends immutable row to `attendance_correction_logs` + `audit_logs`. Never deletes rows. |
| `/rgst` | `GET` | Auth `RGST`/`ADMIN`/`SUPERADMIN` | Accessible manual search + Day 1 / Day 2 / Day 3 checkbox table (`assets/js/rgst-desk.js`). Zero `html5-qrcode.min.js` dependency. |

---

## 5. Participant Dashboard, Admin Portal & Festival Operations (Phases 10–12)

| Route | Method(s) | Auth / CSRF | Contract |
|---|---|---|---|
| `/api/account/dashboard` | `GET` | Auth `PARTICIPANT` | Returns profile, all registrations (Attendee/Volunteer/SLI + historical read-only), accommodation requests, duty assignments (read-only), unread/recent `in_app_notifications`, and confirmed public programme highlights. |
| `/api/account/notifications` | `GET`, `PATCH` | Auth owner + CSRF on `PATCH` | Lists `in_app_notifications` for current user; marks read (`read_at = NOW()`). |
| `/api/admin/venues` | `GET`, `POST`, `PATCH` | `ADMIN` (`cms.manage` or `programme.manage`) / `SUPERADMIN` + MFA + CSRF | Manage venues with explicit `confirmation_status` (`PENDING`/`PROPOSED`/`CONFIRMED`/`ARCHIVED`). |
| `/api/admin/programme` | `GET`, `POST`, `PATCH` | `ADMIN` (`cms.manage` or `programme.manage`) / `SUPERADMIN` + MFA + CSRF | Manage `festival_sessions` & `programme_items` (`DRAFT`/`PROPOSED`/`CONFIRMED`/`PUBLISHED`/`CANCELLED`); optionally notifies affected participants via `PROGRAMME_CHANGE_NOTICE`. |
| `/api/admin/assignments` | `GET`, `POST`, `PATCH`, `DELETE` | `ADMIN` (`volunteers.review` / `sli.review`) / `SUPERADMIN` + MFA + CSRF | Assigns `APPROVED` Volunteers/SLIs to venue/date/shift/session with server-side overlap detection, availability conflict warnings, unapproved-applicant block (`400`), 2-SLI pairing group support, updates `registrations.assignment_status = 'ASSIGNED'`, and enqueues `VOLUNTEER_ASSIGNMENT_*` / `SLI_ASSIGNMENT_*` + `in_app_notifications`. |
| `/api/admin/announcements` | `GET`, `POST`, `PATCH` | `ADMIN` (`cms.manage` / `communications.manage`) / `SUPERADMIN` + MFA + CSRF | Manages announcements & optional `FESTIVAL_IMPORTANT_INFO` / `FESTIVAL_REMINDER` broadcast enqueue (queued safely, never bypasses `MAIL_DRIVER`). |
