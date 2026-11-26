# Proposed 3-Category Account-Based Architecture — Gap, Dashboard & Migration Analysis

**Project:** Purple Festival Gujarat 2027 (`d:\purplefestgujarat`)  
**Audit Scope:** Part M (Sections 36, 37, 39 — Report 13)  
**Context Only (No Changes Implemented):** Transitioning toward an authenticated participant-centric model centered on **1. Attendee**, **2. Sign Language Interpreter (SLI)**, and **3. Festival Volunteer**, potentially without QR registration desk scanning.

---

## 1. Architectural Gap Analysis Across All 26 Dimensions (Section 36)

| # | System Dimension | Current Verified State | Required Change for 3-Category Account Model | Effort / Complexity |
|---|---|---|---|---|
| 1 | **Public Navigation** (`includes/header.php`, `footer.php`) | Shows "Login" (mobile says "Participant & Staff Login") pointing to staff-only `/login`, plus "Apply Now" -> `/apply`. | Unify or clearly separate Participant Sign In / Create Account vs Staff Portal Login; add authenticated header state (Dashboard / My Applications / Sign Out). | Low |
| 2 | **Login Page** (`login.php`, `auth/login.php`) | Staff-only username+password+MFA form; blocks `PARTICIPANT` (`403`); reflected XSS on `?next=`; duplicates `/auth/login`; no forgot-password or signup link; lacks `accessibility.js`. | Support participant email/mobile login (or separate `/login` for participants and `/staff/login` for staff); fix XSS; add forgot-password & signup flows; include accessibility toolbar. | Medium |
| 3 | **Account Creation** (`api/auth/signup.php`) | Live orphaned API with zero UI, no email/mobile verification, no rate limit/CAPTCHA, no CSRF, redirects to crashed `/dashboard`. | Build accessible account registration UI + verification step + rate limiting + CSRF + password strength + transaction safety. | Medium |
| 4 | **Category Selection** (`apply/index.php`, `registration-wizard.js`) | 5 public Hub cards (`ATTENDEE`, `ACCESSIBLE`, `DELEGATE`, `VOLUNTEER`, `SLI`) + 4 hidden DB categories (`STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`) exposed in Step 2. | Streamline to 3 primary categories (`ATTENDEE`, `SLI`, `VOLUNTEER`) per Owner Decisions on Delegate & Accessible options; decide whether category is selected before or after account creation and whether 1 account can hold multiple categories. | Medium |
| 5 | **Participant Identity** (`users` vs `registrations`) | Unauthenticated `/apply` silently creates passwordless `users` rows (`password_hash = NULL`) or attaches to existing email without auth. | Require authenticated identity before registration submission (or verified claim flow); eliminate silent unauthenticated email attachment (`FND-07`). | High |
| 6 | **Email Verification** | **NOT IMPLEMENTED.** `users.email_verified_at` is always `NULL` for participants. | Build signed verification token/OTP + Resend delivery + expiry/resend throttling + verified status gating before linking legacy records. | High |
| 7 | **Mobile Verification** | **NOT IMPLEMENTED.** Zero SMS/WhatsApp provider or OTP schema. | Optional per Owner Decision (`OD-05`); requires SMS/WhatsApp gateway integration (e.g., MSG91/Twilio/Meta) + DLT registration in India. | High (if chosen) |
| 8 | **Existing Registrations** | Linked by `user_id` + `email` with `password_hash = NULL` and unverified emails. | Requires safe claim/activation migration workflow (see Section 4 below). | High |
| 9 | **User Account Schema** (`database/schema.sql`) | `users` has `username`, `email`, `email_verified_at`, `password_hash`, `mfa_*`; lacks `phone_verified_at`, `account_status`, proper FKs, and atomic registration sequence. | Add verification/OTP table (or clean `auth_accounts`), enforce FKs, deduplicate 6 duplicate table pairs, remove `mfa_secret` plaintext duplication. | Medium |
| 10 | **Password Management** | `api/auth/password-reset.php` is orphaned/broken (no UI, missing template, no queue worker); `api/account/profile.php` doesn't revoke sessions on change. | Wire reset flow to direct transactional email (no plaintext token in DB), build forgot/reset UI, revoke all other sessions on password change/reset. | Medium |
| 11 | **Role-Based Authorization** | 4 auth roles (`SUPERADMIN`, `ADMIN`, `RGST`, `PARTICIPANT`); `ATTENDEE`/`SLI`/`VOLUNTEER` are registration category codes, NOT auth roles; `cms.php` & `audit-logs.php` have RBAC bypasses. | Keep `PARTICIPANT` as the single participant auth role (with capabilities derived from owned `registrations.category_code` + `status`), avoiding role explosion when one person holds multiple categories; fix all admin RBAC bypasses & CSRF first. | Medium |
| 12 | **Participant Dashboards** (`dashboard/index.php`) | **Crashes HTTP 500** (`requireAuth(string)` `TypeError`); unreachable by participants; missing ~11 of 19 capabilities (see Section 3). | Fix fatal crash, redesign around authenticated 3-category experience (Attendee / SLI / Volunteer views, application status, editing, cancellation, notifications). | High |
| 13 | **Attendee Applications** | Instant `CONFIRMED`; collects basic profile + days + optional accessibility. | Retain streamlined flow inside authenticated account; decide whether Delegate fields are optionally folded in (`OD-04`). | Low |
| 14 | **SLI Applications** | Collects qualifications, CRR, expertise, video URL (XSS bug), `sliAffiliation` missing from UI, no document upload, `sli.require_crr` ignored. | Fix XSS, add missing affiliation field, add secure document/certificate upload if required, wire approval/rejection/request-info/assignment notifications & dashboard status view. | Medium |
| 15 | **Volunteer Applications** | Collects roles, languages, MSU alumni, emergency contact; 16+ age unenforced; no document upload; no shift view/ack. | Enforce 16+ validation, add secure ID/document upload if required, expose status & duty schedule in authenticated dashboard, send all lifecycle notifications. | Medium |
| 16 | **Approval Processes** | Unrestricted 10-status allowlist; no state-transition validation; no applicant notification on Reject/Waitlist/Info-Required; no applicant response loop for `ADDITIONAL_INFO_REQUIRED`. | Enforce valid state-machine transitions per category; add rejection/waitlist/info-request emails; allow applicants to edit & resubmit when `ADDITIONAL_INFO_REQUIRED`. | Medium |
| 17 | **Accessibility Support** | Cross-category Step 3 already works for ALL categories, but `ACCESSIBLE` hub card is a duplicate entry point; accommodation requests duplicate due to distinct UUIDs; broken High Contrast & Yellow-Black CSS modes; wizard destroys focus on render. | Make accessibility & accommodations a first-class cross-category option across Attendee, SLI & Volunteer; fix WCAG contrast & focus defects; fix dual-table duplication bug; enable edit/cancel & status notifications. | High |
| 18 | **Notifications** | Only 2 email templates exist (`mailer.php`); no queue worker; primary DB insert fails; `api.qrserver.com` token leak; zero SMS/WhatsApp/in-app notifications. | Build clean transactional email service + retry worker + in-app dashboard notification feed; add templates for all 15+ lifecycle events (account verify, reset, approval, rejection, info-needed, duty assigned/changed, accommodation updated). | High |
| 19 | **Administrative Workflows** | 11 tabs in `/admin`; Volunteers/SLI capped at 100 rows; detail modal drops role/disability data; no edit/delete/bulk ops; RBAC & CSRF gaps. | Harden RBAC/CSRF, remove 100-row & 500-audit-row caps, render full role/disability/consent details in modal, add bulk actions & search/pagination on Volunteer/SLI tabs. | Medium |
| 20 | **Duty Assignment** | Volunteer dept/venue/shift/supervisor & SLI venue/dutyTimes/append-only sessions exist in DB/admin; never emailed on update; invisible to participants. | Allow removing/editing SLI sessions; email + display live duty schedules & updates in participant dashboard; optionally add shift acceptance/acknowledgment. | Medium |
| 21 | **Existing QR Credentials** | Stored in `qr_credentials` + `credential_events`; coupled to `includes/qr-service.php`. | Archive read-only; extract `generateUniqueRegistrationCode()` before disconnecting QR issuance (`OD-01`). | Low |
| 22 | **Registration Desk Infrastructure** | `/rgst` + `html5-qrcode.min.js` + `api/qr/verify.php` + `api/checkins/*`. | Disconnect/deprecate per Owner Decision (`OD-01` / `OD-02`). | Low |
| 23 | **Attendance Analytics** | Driven exclusively by `checkins` table in `overview.php` & `admin-portal.js` Attendance tab. | If QR check-in is retired, either hide Attendance tab, keep read-only archive, or replace with manual/self check-in (`OD-02`). | Low |
| 24 | **Coupons & Payments** | Coupons work (with TOCTOU race); payments are `DATABASE ONLY` (0 PHP endpoints); all seeded fees are ₹0. | If all 3 categories (Attendee/SLI/Volunteer) are free, disconnect coupons UI & keep payment tables archived; if paid tiers remain, fix coupon race and build payment gateway (`OD-04`). | Low–High (depends on `OD-04`) |
| 25 | **Existing Data** | Passwordless `users`, dual-written duplicate tables, unverified emails. | Migrate/deduplicate tables cleanly; require verified email claim before account takeover (Section 4). | Medium |
| 26 | **Audit Logs** | Truncated at 500 rows; missing `audit_logs.read` gate; sensitive disability reads unaudited; `admin_actions` write-only. | Fix RBAC & SQL pagination; log sensitive PII/disability reads, account claims, role changes, and password/MFA events. | Low |

---

## 2. Architectural Options: Delegate & Accessible Pathways

### Option Analysis A — Conference Delegate (`DELEGATE`)
**Current Delegate-Specific Functionality Found in Codebase:**
- Dedicated tables `delegate_details` & `delegate_profiles` (10 collected fields: age, gender, city/state, food preference, 7 para-sports interests, outstation travel flag, lodging guidance flag, emergency contact name/phone, escort/guardian name + 3 unused columns: `dietary_requirements`, `arrival_date`, `departure_date`).
- Coupon eligibility default (`eligibleCategories: ["DELEGATE"]` hardcoded in `admin-portal.js:2261`) and fee/payment status branching (currently ₹0 in seed).
- Note: Delegate details are **currently invisible in the Admin detail modal** (`admin-portal.js` discards `delegateDetails`!).

| Option | Architectural Impact | Trade-Offs |
|---|---|---|
| **Option A1: Merge Delegate into Attendee (with optional Conference / Outstation / Para-Sports toggle)** | Consolidates UI to 3 categories (`Attendee`, `SLI`, `Volunteer`) while preserving optional logistics fields (food, travel, lodging, para-sports, emergency contact, guardian) inside Attendee registration or profile. | Preserves existing `delegate_details` data and organizer logistics visibility without a confusing 4th/5th category. Slightly longer optional Attendee form. |
| **Option A2: Retire Delegate Pathway Completely** | Deactivates `DELEGATE` in `registration_categories` (`is_active = 0`), archives `delegate_details`/`delegate_profiles` read-only, removes coupon UI if all remaining categories are free. | Simplest 3-category UX, **but loses** structured collection of outstation travel, lodging, dietary/food, guardian, and para-sports interests unless re-added elsewhere. Historical delegates remain viewable in Admin under archived filter. |

### Option Analysis B — Accessible Registration (`ACCESSIBLE`)
**Current Verified Implementation:** `ACCESSIBLE` is **already** a UI shortcut (`registration-wizard.js:94-100`) that selects `ATTENDEE` + `isPersonWithDisability = "YES"`. Step 3 (21 RPwD disabilities + 13 accommodations + free-text notes) is **already available across ALL categories** (Attendee, Delegate, Volunteer, SLI).

| Option | Architectural Impact | Trade-Offs |
|---|---|---|
| **Option B1: Retain as Cross-Category Accessibility Option (Recommended Option)** | Remove the misleading separate "Accessible Registration" card that implies PwDs can only be Attendees; instead prominently offer *"Do you need accessibility accommodations / PwD support?"* inside **every** category (Attendee, SLI, and Volunteer — Deaf SLIs and PwD Volunteers also need accommodations!). | Aligns with universal design & inclusion; eliminates artificial separation of PwD visitors from Volunteers/SLIs; reuses existing Step 3 & `accommodation_requests` schema cleanly. |
| **Option B2: Keep Prominent "Accessible Entry" Quick-Start CTA Routing into Unified Account Flow** | Keep a prominent accessible-language banner/CTA on homepage/apply that deep-links into the unified 3-category flow with the Accessibility Needs panel auto-expanded. | Gives PwD visitors immediate reassurance while keeping backend data model cleanly 3-category. |

---

## 3. Proposed Participant Dashboard Capability Assessment (Section 37)

*(Note: `dashboard/index.php` currently crashes with HTTP 500 `TypeError` at line 14 and cannot be reached after logout because `api/auth/login.php` blocks `PARTICIPANT`.)*

| # | Dashboard Capability | Current Codebase Status | Classification | Exact Reuse / Gap Notes |
|---|---|---|---|---|
| 1 | **Participant profile** (view & edit) | Exists in `dashboard/index.php:747-828` & `api/account/profile.php` (unreachable due to crash) | **MODIFY** | Fix crash; add CSRF; sync name/phone/country updates or display email + verification badge. |
| 2 | **Registration information** | Exists in `dashboard/index.php:379-437` | **MODIFY** | Fix crash; display category-specific details (currently omitted); adapt to 3 categories. |
| 3 | **Application status** | Exists (`pfgStatusBadgeClasses`, `dashboard/index.php:173-197`) | **MODIFY** | Add human-readable status timeline, reviewer feedback (when `ADDITIONAL_INFO_REQUIRED`), and rejection/waitlist guidance. |
| 4 | **Personal identification number** | Exists (`registration_code` `PFG-2027-*`, `dashboard/index.php:405`) | **REUSE / MODIFY** | Keep `registration_code` independent of QR; upgrade generator from `COUNT(*)+1` to atomic sequence. |
| 5 | **Category-specific information** | Only Volunteer/SLI **duty** cards rendered; submitted application answers (qualifications, roles, languages, delegate info) NOT shown | **BUILD FROM SCRATCH** | Query & display submitted category-specific fields per Attendee / SLI / Volunteer. |
| 6 | **Application editing** | **NOT IMPLEMENTED** (no `PATCH /api/registrations` for participants) | **BUILD FROM SCRATCH** | Build owner-scoped edit endpoint gated by allowed statuses (`SUBMITTED`, `ADDITIONAL_INFO_REQUIRED`, `CONFIRMED` for logistics) + audit log. |
| 7 | **Supporting document uploads** | **NOT IMPLEMENTED** (`document_url` columns exist in `volunteer_applications` & `sli_applications` but zero upload code/storage) | **BUILD FROM SCRATCH** | Needs private non-webroot file storage, MIME/magic-byte validation, size limits, AV/sanitization, and authenticated download endpoint. |
| 8 | **Approval notifications** | Email-only (domain unverified, no rejection/waitlist/info emails); zero in-app display | **BUILD FROM SCRATCH** | Add in-app notification list + complete email templates for all status changes. |
| 9 | **Assigned venue** | Exists in `dashboard/index.php:528,574` (`assigned_venue`) | **REUSE / MODIFY** | Fix crash; add venue address & accessibility details joined from `venues` table. |
| 10 | **Assigned duties** | Exists (`assigned_department`, `assigned_responsibility`, `assigned_supervisor` for Volunteer; `assigned_sessions` for SLI) | **REUSE / MODIFY** | Fix crash; add reporting instructions & optional participant acknowledgment button. |
| 11 | **Shift schedules** | Partial (`assigned_shift`, `reporting_time`, `duty_times` rendered as flat strings) | **MODIFY** | Structure multi-day shifts/sessions cleanly with `.ics` calendar export & schedule-change alerts. |
| 12 | **Event information** | Hardcoded dates/venue in `pass-print.php`; `/programme` & `/accessibility` exist as separate public pages | **MODIFY** | Surface personalized attending-days schedule & venue access info inside dashboard. |
| 13 | **Accessibility requests** | Create-only exists in `dashboard/index.php:606-737` & `api/account/accommodations.php` (duplicates rows from registration) | **MODIFY** | Fix dual-table UUID duplication bug; add edit/cancel (`PATCH`/`DELETE`), CSRF, allowlist validation. |
| 14 | **Accommodation status** | Exists (`REQUESTED`/`IN_PROGRESS`/`ARRANGED`/`DECLINED` + `coordinator_notes`) | **REUSE / MODIFY** | Fix crash & notes overwrite bug; add email/in-app notification when coordinator updates status. |
| 15 | **Account settings** | Partial (name/phone/country + optional MFA in `dashboard/index.php:747-930`) | **MODIFY** | Fix MFA overwrite/plaintext bugs; add email verification status, consent preferences, active sessions / sign-out-all, data export/deletion request UI. |
| 16 | **Password changes** | Exists in `api/account/profile.php:94-135` | **MODIFY** | Add confirm-password input, brute-force lockout, CSRF, session revocation of other devices, and re-auth guard for passwordless accounts. |
| 17 | **Notification history** | **NOT IMPLEMENTED** (`notification_queue` is write-only & stores secret `rawToken`!) | **BUILD FROM SCRATCH** | Build sanitized user-scoped notifications feed (never exposing `rawToken` or `resetUrl`). |
| 18 | **Help and support** | Static footer contact email/phone only | **BUILD FROM SCRATCH** | Add dashboard support card / inquiry link referencing user's `registration_code`. |
| 19 | **Application cancellation** | **NOT IMPLEMENTED** (`WITHDRAWN` exists in allowlist only) | **BUILD FROM SCRATCH** | Build participant withdrawal/cancellation endpoint + confirmation dialog + audit log + email + cleanup of active assignments/credentials. |

---

## 4. Existing Data Migration Assessment (Section 39)

### 4.1 Segment-by-Segment Risk Matrix

| Data Segment | Current Schema State | Migration Risk & Key Implications |
|---|---|---|
| **Users without passwords** (`password_hash IS NULL`) | All `/apply` browser registrants have `password_hash = NULL`. | Cannot log in until they complete a verified password-set / magic-link claim flow. |
| **Users without verified email** (`email_verified_at IS NULL`) | 100% of participant `users` rows have `email_verified_at = NULL`. | **CRITICAL ACCOUNT TAKEOVER RISK:** Because unauthenticated `/apply` allowed anyone to register with any email (`FND-07`), auto-linking an existing registration to a new signup *without* proving email ownership would let an attacker hijack a victim's registration, disability disclosures, and accommodation requests! |
| **Multiple registrations per email** | Allowed across different `category_code`s under one `user_id` (and possibly same category due to TOCTOU race). | One `users` account must cleanly support 1-to-many `registrations` (or a clear multi-category view) without collapsing records. |
| **Duplicate identities** | Possible if same person registered with two emails or typo'd email; `users.email` is unique case-sensitively in some collations (`utf8mb4_unicode_ci` is CI). | Requires dedup audit by normalized `(phone, full_name)` before/during migration; never auto-merge solely on phone without user verification. |
| **Existing confirmed Attendees** | `status = 'CONFIRMED'`, `category_code = 'ATTENDEE'`. | Preserve `registration_code`, `status = 'CONFIRMED'`, and `submitted_at` verbatim when user activates account. |
| **Pending Volunteer & SLI applications** | `status = 'SUBMITTED'`, linked to `volunteer_applications` / `sli_applications`. | Preserve all submitted fields and review state; once account is activated, show live status and allow editing if `ADDITIONAL_INFO_REQUIRED`. |
| **Delegate registrations** | `category_code = 'DELEGATE'` + dual-written `delegate_details`/`delegate_profiles`. | Deduplicate into canonical `delegate_details` (or merge into Attendee logistics per `OD-04`); never drop existing delegate records. |
| **Accessibility disclosures & Accommodation requests** | Dual-written across 4 tables with distinct UUIDs on accommodations (`registration_accommodations` vs `accommodation_requests`). | Run read-verified deduplication matching `(registration_id, selected_types, free_text_details, created_at)` into single canonical tables before exposing in new dashboard so users don't see duplicate requests. |
| **Issued QR credentials & Duty assignments** | Stored in `qr_credentials` & `volunteer_applications`/`sli_applications`. | Retain `qr_credentials` read-only for audit; preserve all assigned Volunteer/SLI duties intact and surface them in the new dashboard. |

### 4.2 Safe Migration Strategies (Options Only — Not Executed)

1. **Strategy 1 — Verified Email Claim ("Activate Your Existing Registration") [Recommended for Safety]:**
   - When an existing registrant visits Signup/Login with an email that already exists (`password_hash IS NULL`), do **NOT** immediately log them in or expose existing registration data.
   - Instead, send a single-use, time-limited (15–30 min) signed verification link or OTP to that email address (requiring verified Resend domain first!).
   - Only after clicking the link / verifying the OTP does the user set a password (`email_verified_at = NOW()`), unlocking their pre-existing registrations, disclosures, and assignments.
2. **Strategy 2 — Clean Cutoff on Main Production Domain (If Staging Has Only Test Data):**
   - If live staging (`purplefestivalgujarat.ajeetgraphics.com`) contains only internal QA/test rows (and note staging currently has no active `festival_editions` row), launch the new 3-category authenticated system cleanly on the final production domain with zero legacy passwordless participant migration needed.
   - If real participants already registered before cutoff, combine Strategy 1 for those verified records.
3. **Strategy 3 — Dual-Table Consolidation Migration (Pre-Launch SQL Migration Script):**
   - Consolidate the 6 duplicate table pairs (`delegate_profiles` -> `delegate_details`, `registration_disabilities` -> `participant_disability_disclosures`, `registration_accommodations` -> `accommodation_requests`, `coupon_codes` -> `coupons`, `payments` -> `payment_orders`, `notification_jobs` -> `notification_queue`) via an explicit, reversible, versioned migration script with verified row counts before deprecating duplicate tables.

