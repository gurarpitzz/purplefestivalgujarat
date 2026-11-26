# PURPLE FESTIVAL GUJARAT 2027
## Master Comprehensive Forensic Codebase, Registration, Authentication & Administrative Workflow Audit

**Audit Mode:** Strict Audit-Only (Zero code edits, zero schema changes, zero emails/SMS/WhatsApp sent, zero deployments, zero secret disclosure)  
**Audit Date:** 2026-11-26  
**Repository Root:** `d:\purplefestgujarat`  
**Git Branch / HEAD Commit:** `main` @ `5740391643b6730db9d7731d2b73370689aa2748`  
**Companion Audit Artefacts (14 Total in `d:\purplefestgujarat\audit\`):**
- [MASTER_CODEBASE_AUDIT.md](file:///d:/purplefestgujarat/audit/MASTER_CODEBASE_AUDIT.md) (this document)
- [FEATURE_IMPLEMENTATION_MATRIX.csv](file:///d:/purplefestgujarat/audit/FEATURE_IMPLEMENTATION_MATRIX.csv) (42 features)
- [API_ENDPOINT_INVENTORY.csv](file:///d:/purplefestgujarat/audit/API_ENDPOINT_INVENTORY.csv) (21 PHP endpoints)
- [REGISTRATION_FIELD_INVENTORY.csv](file:///d:/purplefestgujarat/audit/REGISTRATION_FIELD_INVENTORY.csv) (56 field entries)
- [ROLE_PERMISSION_MATRIX.csv](file:///d:/purplefestgujarat/audit/ROLE_PERMISSION_MATRIX.csv) (25 resources × roles)
- [NOTIFICATION_TRIGGER_MATRIX.csv](file:///d:/purplefestgujarat/audit/NOTIFICATION_TRIGGER_MATRIX.csv) (26 events)
- [DATABASE_INVENTORY.md](file:///d:/purplefestgujarat/audit/DATABASE_INVENTORY.md) (57 tables + ER diagram + numbering/concurrency)
- [WORKFLOW_DIAGRAMS.md](file:///d:/purplefestgujarat/audit/WORKFLOW_DIAGRAMS.md) (5 registration flows + 6 state machines)
- [TEST_EXECUTION_RESULTS.csv](file:///d:/purplefestgujarat/audit/TEST_EXECUTION_RESULTS.csv) (38 scenarios)
- [PREVIOUS_CLAIMS_VERIFICATION.csv](file:///d:/purplefestgujarat/audit/PREVIOUS_CLAIMS_VERIFICATION.csv) (30 core + 4 doc claims)
- [SECURITY_PRIVACY_FINDINGS.md](file:///d:/purplefestgujarat/audit/SECURITY_PRIVACY_FINDINGS.md) (46 prioritized P0–P4 findings)
- [QR_DEPENDENCY_ANALYSIS.md](file:///d:/purplefestgujarat/audit/QR_DEPENDENCY_ANALYSIS.md) (QR lifecycle + 7 decommissioning decisions)
- [FUTURE_ARCHITECTURE_GAP_ANALYSIS.md](file:///d:/purplefestgujarat/audit/FUTURE_ARCHITECTURE_GAP_ANALYSIS.md) (3-category gap + dashboard + migration)
- [OWNER_DECISIONS_REQUIRED.md](file:///d:/purplefestgujarat/audit/OWNER_DECISIONS_REQUIRED.md) (16 decisions requiring owner sign-off)

---

## Audit Baseline (Section 5)

| Baseline Attribute | Verified State |
|---|---|
| **Project Root Directory** | `d:\purplefestgujarat` (124 Git-tracked files; ~7.3 MB PHP site + ~809 MB untracked/ignored legacy Next.js/PostgreSQL/raw docs bloat) |
| **Git Branch & Commit** | `main` @ `5740391643b6730db9d7731d2b73370689aa2748` |
| **Working Tree Status (Pre-Audit)** | `M includes/mailer.php` (1 trailing blank line only); `?? bg1.png`, `?? "Inclusive Viksit Bharat 2047 Vision.png"`, `?? "Understanding Disability Access Guide.png"` |
| **Relevant Recent Commits** | `5740391` Viksit redesign; `2aa198b`/`813c978` Partners redesign; `0f1eec5`/`b4b1e6b` 21 Disabilities; `29e06db` staff username login & Resend mailer; `d0f922b`/`00b1967` native PHP + MySQL migration |
| **Local Runtime Environment** | Windows OS; **PHP CLI: NOT FOUND**; **MySQL/MariaDB/SQLite3 CLI: NOT FOUND**; **Composer: NOT FOUND**; Python 3.11.9, Node v22.18.0, npm 11.9.0, Git 2.50.1 present |
| **Staging Environment** | `http://purplefestivalgujarat.ajeetgraphics.com` (Bluehost Apache; PHP 8.3 lint target in CI; HTTPS returns HTTP 200 but HTTP is **not** redirected). **Parity:** Serves latest commit `5740391` (`viksit-section.css`, `partners-section.css`, `disabilities-section.css` verified live). |
| **Production Environment** | Not yet live on final main domain (unavailable / unaccessible during audit) |
| **Database Schema & Migration Status** | Single declarative `database/schema.sql` (57 InnoDB tables, `utf8mb4_unicode_ci`) + `database/seed.sql` + PHP auto-init in `includes/database.php`. **Zero versioned migration framework.** Live staging DB is reachable (`GET /api/auth/me` -> 200) **but missing an active `festival_editions` row** (`GET /api/registrations` -> `HTTP 404`). |
| **External Dependencies** | Bundled JS: `gsap.min.js`, `html5-qrcode.min.js` (v2.3.8), custom `qrcode-gen.js`. Outbound HTTP: Resend API (`api.resend.com`), third-party QR image service (`api.qrserver.com` — **P0 privacy leak**). Zero Composer/PHP vendor packages. |
| **Environment Config Files** | PHP reads `/home1/tstpllmy/.pfg27_env.php` -> `../../.pfg27_env.php` -> `.env.php` (not present in local repo; `.env.local` in workspace root is an ignored legacy Next.js file and was **NOT** read). |
| **Automated Tests & Safe Test DB** | **0 automated tests** exist in repository (despite obsolete `README.md` claiming Vitest/Playwright/axe). **No local test DB** available. |

---

## Report 1 — Executive Summary

### What Currently Exists
Purple Festival Gujarat 2027 is a **native PHP 8 + MySQL/SQLite PDO + Vanilla JS + HTML5/CSS3** web platform (44 tracked PHP files, 8 JS files, 4 CSS files, 57 SQL tables) deployed on Bluehost staging (`http://purplefestivalgujarat.ajeetgraphics.com`). It includes public informational pages (`/`, `/programme`, `/accessibility`, `/legal/*`), a 5-pathway multi-step registration wizard (`/apply`), a staff-only login portal (`/login` & `/auth/login`), an 11-tab administrative portal (`/admin`), a QR check-in registration desk (`/rgst`), an inoperable participant dashboard (`/dashboard`), 21 PHP API endpoints, and a Resend/PHP-`mail()` mailer.

### What Actually Works (Verified Within Audit Limits)
- Public informational pages (`/`, `/programme`, `/accessibility`, `/legal/*`), login UI (`/login`, `/auth/login`), and `/apply` render HTTP 200 on live staging with the latest commit (`5740391`).
- Unauthenticated access controls properly return HTTP 302 on `/admin`, `/rgst`, `/dashboard/pass-print.php` and HTTP 401 on all 11 protected GET APIs on live staging; `.htaccess` returns HTTP 403 on `/database/*`, `/includes/*`, `/.env.local`, `/.htaccess`, and `/.cpanel.yml`.
- **Zero complex write/auth workflows are classified `VERIFIED WORKING`** because local PHP/MySQL are absent and strict audit-only mode prohibits mutating live staging.

### What Is Broken or Critically Flawed Right Now
1. **Live Staging Registration Is Broken (`HTTP 404`):** `GET /api/registrations` returns `{"error":"No active festival edition configured."}` on staging and `/apply` falls back to 4 hardcoded categories — meaning `POST /api/registrations` cannot succeed on staging today (`FND-14`).
2. **Live Staging `/dashboard` Crashes (`HTTP 500`):** `dashboard/index.php:14` passes a `string` to `requireAuth(?PDO $pdo = null)` under `strict_types=1`, causing an immediate fatal `TypeError` for **all** users (`FND-13`).
3. **12 P0 Security & Privacy Vulnerabilities:** Zero CSRF enforcement site-wide (`verifyCsrfToken()` has 0 callers); RBAC bypass letting any `ADMIN` edit CMS/fees (`api/admin/cms.php:70`) and read audit logs (`api/admin/audit-logs.php:23`); active TOTP MFA overwrite bypass without password/current TOTP + plaintext TOTP secrets (`api/auth/mfa.php`); Reflected XSS in `/login`, `/auth/login`, `/apply` (`JSON_HEX_TAG` omitted); Stored XSS in Admin SLI tab (`videoDemoUrl` `javascript:` URI); live QR bearer token leak to third-party `api.qrserver.com` + plaintext `rawToken` stored in `notification_queue.payload`; unauthenticated email-based account attachment on `POST /api/registrations`; unvalidated Open Redirect + GET Logout CSRF (`/api/auth/logout`); hardcoded fallback signing secrets; missing HTTPS redirect & HSTS/CSP; publicly downloadable `/README.md` and `/docs/*.md` (HTTP 200 on staging); unencrypted & unaudited disability disclosures.
4. **Severe WCAG 2.2 AA Failures:** High-Contrast mode produces **white-on-white invisible text** in dark sections; Yellow-on-Black mode produces **yellow-on-white** in custom pills/cards; registration wizard `innerHTML` re-render destroys keyboard focus on every radio/checkbox toggle; `#FFFFFF` on `#A459FF` (3.85:1) fails AA contrast.
5. **Broken/Contradictory Operational Logic:** RGST default scope literal `"GENERAL_ENTRY"` blocks Day 2 & Day 3 check-ins; manual lookup after QR reissue falsely returns `REVOKED`; duplicate-check-in unique key is bypassed by pass reissue; dual-written accommodations generate 2 distinct UUIDs causing duplicate rows; `notification_queue` has no worker and `mailer.php`'s primary INSERT fails on every send due to schema column drift; `COUNT(*)+1` numbering + user creation + coupon checks run outside transactions with concurrency races.

### What Is Incomplete or Blocked
- **Email delivery:** `CONFIGURATION BLOCKED` (unverified production sending domain in Resend + schema/privacy/queue defects in `includes/mailer.php`).
- **Participant accounts & self-service:** `PARTICIPANT` login is hard-blocked (`403`), `/api/auth/signup` and `/api/auth/password-reset` are live orphaned APIs with no UI, and email/mobile verification + OTP are `NOT IMPLEMENTED`.
- **Payments, SMS, WhatsApp, Queue Worker, Document Uploads:** Payments & notification worker are `DATABASE ONLY`; SMS, WhatsApp, OTP, and document uploads are `NOT IMPLEMENTED`.

---

## Report 2 — Implementation Status Dashboard

**Methodology & Denominator:** 42 discrete platform features inventoried in [FEATURE_IMPLEMENTATION_MATRIX.csv](file:///d:/purplefestgujarat/audit/FEATURE_IMPLEMENTATION_MATRIX.csv) (unweighted count, classified strictly using Section 4 definitions; untested features are **never** counted as `VERIFIED WORKING`).

| Standardized Status | Feature Count (n = 42) | Share of 42 Features | Key Features |
|---|---:|---:|---|
| `VERIFIED WORKING` | **0** | 0.0% | None (end-to-end write/auth tests not executable under audit-only + missing local PHP/MySQL) |
| `IMPLEMENTED — UNTESTED` | **3** | 7.1% | `F-23` Admin Overview, `F-33` Browser QR Generator, `F-41` Superadmin Bootstrap CLI |
| `PARTIALLY IMPLEMENTED` | **25** | 59.5% | Public pages (`F-01`–`F-04`), Registration pathways & coupons (`F-06`, `F-08`–`F-13`), Staff login/session (`F-15`, `F-17`), Pass print (`F-22`), Admin modules (`F-24`–`F-27`, `F-29`–`F-30`), QR service/API/check-ins (`F-32`, `F-34`, `F-36`), DB/Apache (`F-40`, `F-42`) |
| `BROKEN` | **9** | 21.4% | `F-05` A11y contrast modes, `F-07` Catalog API (staging 404), `F-16` Participant login/access, `F-18` TOTP MFA, `F-19` Password reset, `F-21` Dashboard (staging 500), `F-28` CMS (RBAC bypass), `F-31` Audit logs (RBAC bypass + 500 cap), `F-35` RGST scanner |
| `DATABASE ONLY` | **2** | 4.8% | `F-14` Payments/Razorpay, `F-38` Notification queue worker |
| `CONFIGURATION BLOCKED` | **1** | 2.4% | `F-37` Email delivery (Resend) |
| `NOT IMPLEMENTED` | **2** | 4.8% | `F-20` Email/Mobile verification & OTP, `F-39` SMS & WhatsApp |
| `FRONTEND ONLY` / `BACKEND ONLY` / `MOCK` / `DEAD` / `UNVERIFIABLE` | **0** | 0.0% | *(Individual orphaned/dead sub-components such as `/api/auth/signup`, `announcements`, `generateQrSvgDataUrl()`, `pfgCreateUserSessionCompat()` are flagged inside their parent features and CSVs)* |

---

## Report 3 — Complete Codebase and Route Inventory

### Directory Structure (124 Tracked Files)
- **Public Pages (6):** `index.php`, `programme.php`, `accessibility.php`, `apply/index.php`, `legal/privacy.php`, `legal/terms.php`, `legal/accessibility-statement.php` *(7 files including 3 legal pages)*
- **Auth Pages (2 — duplicate):** `login.php`, `auth/login.php` (1-line wrapper requiring `../login.php`)
- **Participant Portals (2):** `dashboard/index.php` (**crashes HTTP 500**), `dashboard/pass-print.php`
- **Staff Portals (2):** `admin/index.php`, `rgst/index.php`
- **API Endpoints (21 PHP files):**
  - `api/auth/` (6): `login.php`, `logout.php`, `me.php`, `mfa.php`, `password-reset.php` (**orphaned**), `signup.php` (**orphaned**)
  - `api/account/` (2): `profile.php`, `accommodations.php` (UI unreachable due to `/dashboard` crash)
  - `api/registrations/` (1): `index.php`
  - `api/coupons/` (1): `validate.php`
  - `api/qr/` (2): `pass.php` (no active JS caller for POST/PATCH), `verify.php`
  - `api/checkins/` (2): `confirm.php`, `index.php`
  - `api/admin/` (7): `overview.php`, `registrations.php`, `users.php`, `accommodations.php`, `cms.php`, `coupons.php`, `audit-logs.php`
- **PHP Includes (9):** `includes/{auth,config,database,disabilities-section,footer,header,hero-background,mailer,qr-service}.php`
- **Database (3):** `database/schema.sql`, `database/seed.sql`, `database/bootstrap-superadmin.php` (CLI-only)
- **JavaScript (8):** `assets/js/{accessibility,admin-portal,crowd-footer,preloader,qrcode-gen,registration-wizard}.js` + vendor `gsap.min.js`, `html5-qrcode.min.js` (v2.3.8)
- **CSS (4):** `assets/css/{app,disabilities-section,partners-section,viksit-section}.css` *(note: `styles.css` does not exist)*
- **Static Assets (58):** `assets/footer/` (5), `assets/gallery/` (28), `assets/illustrations/` (12), `assets/logos/` (12), `assets/login-bg.webp` (1)
- **Config / Deployment / Docs (8):** `.htaccess`, `.cpanel.yml`, `.gitignore`, `.github/workflows/deploy-bluehost-php.yml`, `README.md` (**obsolete Next.js docs, exposed 200 on staging**), `docs/{ACCESSIBILITY_AND_SECURITY,CONTENT_INVENTORY,WEBSITE_AUDIT}.md` (**exposed 200 on staging**)
- **Scheduled Jobs / Workers / Tests / Uploads:** **NONE** (0 cron scripts, 0 workers, 0 test suites, 0 upload directories).
- **Untracked / Ignored Legacy Workspace Bloat (~809 MB):** `node_modules/` (595 MB), `.next/` (67.7 MB), `data/pgdata/` (29 MB), `next-env.d.ts`, `tsconfig.tsbuildinfo`, `.env.local` (NOT read), 98 MB raw PDF/DOCX/PPTX/XLSX docs, 37 raw images, 3 untracked PNGs in root.

### Route Summary Table (Full 21-Endpoint API Matrix in [API_ENDPOINT_INVENTORY.csv](file:///d:/purplefestgujarat/audit/API_ENDPOINT_INVENTORY.csv))

| Route | Actual File | Purpose | Access Level | Connected Backend | Status | Evidence |
|---|---|---|---|---|---|---|
| `/` | `index.php` | Homepage & CMS showcase | Public | Inline PDO | `PARTIALLY IMPLEMENTED` | Staging GET -> 200 |
| `/programme` | `programme.php` | 3-day schedule & `.ics` | Public | Inline PDO | `PARTIALLY IMPLEMENTED` | Staging GET -> 200 |
| `/accessibility` | `accessibility.php` | 21 RPwD + accommodations + venues | Public | Inline PDO | `PARTIALLY IMPLEMENTED` | Staging GET -> 200 |
| `/apply` | `apply/index.php` | 5-pathway registration wizard | Public | `/api/registrations`, `/api/coupons/validate` | `PARTIALLY IMPLEMENTED` | Staging GET -> 200 (fallback categories); catalog API -> 404 |
| `/login` & `/auth/login` | `login.php` & `auth/login.php` | Duplicate staff-only login | Public (staff only) | `/api/auth/login` | `PARTIALLY IMPLEMENTED` | Staging GET -> 200 identical HTML; Reflected XSS at L228 |
| `/dashboard` | `dashboard/index.php` | Participant dashboard | Auth (crashes all) | `/api/account/*`, `/api/auth/mfa` | `BROKEN` | Staging GET -> **HTTP 500** (`TypeError` L14) |
| `/dashboard/pass-print.php` | `dashboard/pass-print.php` | Printable A4 QR pass | Owner or ADMIN/SUPERADMIN | Inline PDO + `qr-service.php` | `PARTIALLY IMPLEMENTED` | Staging GET -> 302 `/login` |
| `/admin` | `admin/index.php` | 11-tab Admin/Superadmin portal | ADMIN / SUPERADMIN | 7 `/api/admin/*` + `/api/auth/mfa` | `PARTIALLY IMPLEMENTED` | Staging GET -> 302 `/login` |
| `/rgst` | `rgst/index.php` | QR registration desk scanner | RGST / ADMIN / SUPERADMIN | `/api/qr/verify`, `/api/checkins/*` | `BROKEN` | Staging GET -> 302 `/login`; Day 2/3 & reissue bugs |
| `/legal/*` (3 routes) | `legal/*.php` | Privacy, Terms, A11y Statement | Public | None | `PARTIALLY IMPLEMENTED` | Staging GET -> 200 |

**Frontend/Backend Contract Mismatches Identified:**
- `api/admin/registrations.php:87` parses path `/api/admin/registrations/<uuid>`, which `.htaccess` does not rewrite (returns 404; only `?id=` works).
- `apply/index.php:278` loads `qrcode-gen.js`, which `registration-wizard.js` never calls.
- `api/auth/signup.php`, `api/auth/password-reset.php`, and `api/qr/pass.php` (POST/PATCH) exist live with **zero** active frontend callers.
- `api/admin/registrations.php:634` accepts `?format=xlsx` but serves `text/csv`.

---

## Report 4 — Existing Registration Workflows

Complete separate Mermaid diagrams for all 5 pathways are in [WORKFLOW_DIAGRAMS.md](file:///d:/purplefestgujarat/audit/WORKFLOW_DIAGRAMS.md), and all 56 fields are inventoried in [REGISTRATION_FIELD_INVENTORY.csv](file:///d:/purplefestgujarat/audit/REGISTRATION_FIELD_INVENTORY.csv).

### Key Pathway Findings
- **5 Public Hub Pathways (`apply/index.php:174-200`):** `ATTENDEE`, `ACCESSIBLE`, `DELEGATE`, `VOLUNTEER`, `SLI`.
- **`ACCESSIBLE` is NOT a separate backend category:** `registration-wizard.js:94-100,352-363` maps it to `categoryCode = 'ATTENDEE'` + `isPersonWithDisability = 'YES'`.
- **4 Hidden Extra DB Categories:** `database/seed.sql:112-120` also seeds `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, and `ALLY` as active categories (exposed in Step 2 whenever DB is seeded).
- **Delegate vs. Attendee:** Differs by 10 extra logistics fields written to BOTH `delegate_details` and `delegate_profiles`, default coupon eligibility (`["DELEGATE"]`), and conditional payment branching (`SUBMITTED`/`PENDING` if fee > 0 — dead-end because zero payment gateway exists).

### Registration Category Comparison Matrix (Section 9 — Verified Implementation Status)

| Capability | Attendee | Accessible Registration | Delegate | Volunteer | SLI |
|---|---|---|---|---|---|
| **Account creation** | Silent passwordless `users` row (outside txn) | Same as Attendee | Same as Attendee | Same as Attendee | Same as Attendee |
| **Login required** | No | No | No | No | No |
| **Email verification** | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` |
| **Mobile verification** | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` |
| **Instant confirmation** | Yes (`CONFIRMED`, blocked on staging by 404) | Yes (maps to `ATTENDEE`) | Yes if fee=0 or 100% coupon; else dead-end `PENDING` | No (`SUBMITTED`) | No (`SUBMITTED`) |
| **Manual approval** | No | No | No | Yes (Admin PATCH) | Yes (Admin PATCH) |
| **Document verification** | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` (`document_url` unused) | `NOT IMPLEMENTED` (manual text CRR + unsafe URL) |
| **Payment** | Not required (₹0) | Not required (₹0) | `DATABASE ONLY` (0 checkout/gateway code) | Not required | Not required |
| **Coupons** | Accepted by API (fee=0) | Accepted by API (fee=0) | Implemented (TOCTOU race on limits) | Accepted by API | Accepted by API |
| **QR generation** | Immediate on confirm (no on-screen display; email-only) | Same as Attendee | Immediate when free/waived; blocked if paid pending | Deferred until Admin approval | Deferred until Admin approval |
| **Registration ID** | `PFG-2027-XXXXXX-XXXX` (`COUNT(*)+1`, race-prone) | Same | Same | Same | Same |
| **Email confirmation** | `CONFIGURATION BLOCKED` (Resend + `api.qrserver.com` leak; lies `qrEmailed=true`) | Same | Same (no email if paid pending!) | Ack email on submit; pass email on approve; **none** on reject/waitlist | Same as Volunteer |
| **SMS / WhatsApp confirmation** | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` | `NOT IMPLEMENTED` |
| **Participant dashboard** | `BROKEN` (HTTP 500 + login 403) | `BROKEN` | `BROKEN` | `BROKEN` | `BROKEN` |
| **Profile editing** | Unreachable (`dashboard` crashed; API doesn't update `registrations`) | Same | Same | Same | Same |
| **Application tracking** | `NOT IMPLEMENTED` (no login/status lookup) | Same | Same | Same | Same |
| **Duty allocation** | N/A | N/A | N/A | Admin-only (dept/venue/shift/supervisor; not emailed on update) | Admin-only (venue/times/append-only sessions; not emailed on update) |
| **Accommodation requests** | Step 3 + API (duplication bug on dual UUIDs; create-only) | Pre-expanded Step 3 (same bug) | Step 3 (same bug) | Step 3 (same bug) | Step 3 (same bug) |
| **Withdrawal / cancellation** | `NOT IMPLEMENTED` for participants (Admin CANCELLED only) | Same | Same | Same | Same |

---

## Report 5 — Authentication and Account Architecture

Full resource-by-role permissions are in [ROLE_PERMISSION_MATRIX.csv](file:///d:/purplefestgujarat/audit/ROLE_PERMISSION_MATRIX.csv).

- **Participant Identity vs. Authenticated Account:** Every `/apply` submission creates (or silently reuses by email without auth!) a row in `users` with `password_hash = NULL`, `username = NULL`, `email_verified_at = NULL`, and assigns role `PARTICIPANT` in `user_roles`. It does **not** create a session (`sessionCreated: false`). **This is an unverified identity record, NOT a usable login account.**
- **Genuine Auth Roles (4 in `roles` & `DEFAULT_ROLE_PERMISSIONS`):** `SUPERADMIN`, `ADMIN`, `RGST`, `PARTICIPANT`. Only the 3 staff roles can authenticate via `POST /api/auth/login` (`api/auth/login.php:109-117` hard-blocks `PARTICIPANT` with HTTP 403). `ATTENDEE`, `DELEGATE`, `VOLUNTEER`, and `SLI` are **registration category codes**, NOT authentication roles.
- **Auth Feature Audit Summary:**
  - *Implemented (with defects):* Staff login (bcrypt cost 12, 5-attempt/15m lockout with sticky counter & timing oracle), DB HMAC-hashed `pfg_session` cookie (7-day TTL, `HttpOnly`, `SameSite=Lax`, weak fallback secret), TOTP MFA (**CRITICAL active overwrite bypass**, plaintext storage, 80-bit entropy, no lockout/replay guard/backup codes).
  - *Orphaned / Broken:* `/api/auth/signup` (live API, zero UI, creates locked-out accounts), `/api/auth/password-reset` (live API, zero UI, unseeded template, no email worker, Host Header Injection), `/api/auth/logout` (GET Logout CSRF + unvalidated Open Redirect).
  - *Missing (`NOT IMPLEMENTED`):* Participant login, email verification, mobile verification, OTP generation/expiry/resend, remember-me, active session list, account recovery, CSRF verification.

---

## Report 6 — Administrative Operations

- **11 Tabs in `/admin` (`admin/index.php` + `assets/js/admin-portal.js`):** Overview, Registrations, Volunteers, SLI, Accommodations, Attendance, Programme & CMS, Coupons, Staff & Roles, Security & MFA, Audit Logs. All Overview KPIs are live SQL (zero mocked numbers).
- **Superadmin vs. Admin vs. RGST:**
  - `SUPERADMIN`: Exclusive access to `api/admin/users.php` (`Staff & Roles`), bypasses all permission checks. **Defects:** Can demote themselves (`PATCH roleCode`), can grant admin `customPermissions` to `RGST`/`PARTICIPANT`, password reset doesn't revoke sessions, cannot reset another staff member's MFA, uses cleartext `window.prompt()` for password reset.
  - `ADMIN`: Intended to hold 16 operational permissions excluding `cms.manage`, `audit_logs.read`, `admin_accounts.manage`, `settings.manage`. **CRITICAL RBAC BYPASSES:** `api/admin/cms.php:70` and `api/admin/audit-logs.php:23` never check `cms.manage` / `audit_logs.read`, and `api/admin/registrations.php` / `overview.php` skip granular permission checks.
  - `RGST`: Strictly restricted to `/rgst`, `api/qr/verify.php`, `api/checkins/confirm.php`, and `OWN` scope of `api/checkins/index.php`; blocked from `/admin`, all 7 `/api/admin/*`, `/api/qr/pass`, and `/dashboard/pass-print.php`.
- **Where Volunteer & SLI Workflows End:** Both workflows end immediately after Admin clicks Approve & Save Duty (`PATCH /api/admin/registrations`), which writes duty columns, auto-issues a QR pass, and attempts one pass email. Subsequent duty edits send **no email**, SLI sessions are append-only (cannot be removed), both tabs cap at **100 rows**, there is **no rejection/waitlist/info-needed email**, **no RCI external verification**, **no document upload/review**, **no participant self-service view**, and **no certificate issuance**.

---

## Report 7 — Communication Infrastructure

Full 26-event matrix in [NOTIFICATION_TRIGGER_MATRIX.csv](file:///d:/purplefestgujarat/audit/NOTIFICATION_TRIGGER_MATRIX.csv).

- **Email (`includes/mailer.php`):** Synchronous cURL POST to Resend API (`https://api.resend.com/emails`) when `RESEND_API_KEY` is non-empty, else `@mail()` fallback (which drops the plain-text alternative!). **No SMTP library exists.**
- **Delivery Chain Truth:** Code reaches at most Step 3–4 (API request attempted / provider 2xx accepted) — **zero webhooks, zero bounce/complaint handling, zero delivery receipts**, and `notification_delivery_logs` is completely unused. On failure, the registration remains committed in MySQL, **but `api/registrations/index.php:1120` falsely returns `qrEmailed: true`** and **no background worker/cron exists** to retry `QUEUED` rows.
- **Critical Mailer Defects:** (1) Primary `notification_queue` INSERT (`mailer.php:120-122`) references non-existent `provider`/`provider_message_id` columns and fails on every call, silently falling back to an insert that drops `sent_at`, `body_html`, `body_text`, and `last_error`. (2) Leaks live `rawToken` to third-party `api.qrserver.com` and stores plaintext `rawToken` in `notification_queue.payload`. (3) Only 2 templates exist (`REGISTRATION_QR_PASS`, `APPLICATION_SUBMITTED`); `PASSWORD_RESET` is unseeded (`NULL` body). (4) Templates fail WCAG (`<html>` missing `lang="en"`, `#A459FF` 3.4:1 contrast).
- **SMS, WhatsApp, Push, In-App, Scheduled Reminders:** **100% `NOT IMPLEMENTED`.**

---

## Report 8 — QR and Registration Desk Infrastructure

Full dependency graph, component classification matrix, and 7 decommissioning decisions are in [QR_DEPENDENCY_ANALYSIS.md](file:///d:/purplefestgujarat/audit/QR_DEPENDENCY_ANALYSIS.md).

- **Token & Storage:** `PFG27.<nonce>.<sig>` uses HMAC-SHA256 with zero PII inside the token, **but is NOT truly hash-only** because `reconstructQrTokenForCredential()` deterministically recomputes tokens from DB `(id, credential_code)` + `QR_SIGNING_SECRET`, AND `mailer.php:272` persists plaintext `rawToken` in `notification_queue.payload`.
- **Rendering:** `assets/js/qrcode-gen.js` is a real zero-dependency browser QR generator (ECC M only; **essential for Admin TOTP MFA!**). Backend `generateQrSvgDataUrl()` (`qr-service.php:129-144`) is a **fake unscannable placeholder SVG**. Emails leak raw tokens to external `api.qrserver.com`.
- **RGST Desk (`rgst/index.php`, `html5-qrcode` v2.3.8):** Backend data minimization passes (`verify.php` returns 17 non-sensitive fields; caveat: `revocation_reason` defaults to `reviewNotes`). However, RGST has **4 critical operational bugs**: (1) default `"GENERAL_ENTRY"` blocks Day 2 & Day 3 check-ins; (2) manual registration code lookup falsely returns `REVOKED` after pass reissue; (3) reissuing a credential bypasses `UNIQUE(credential_id, entitlement_scope)` allowing a 2nd same-day check-in; (4) HTTP staging blocks camera `getUserMedia()` + `stopCamera()` throws null `TypeError`. Zero expiry, zero offline mode, zero undo/override.

---

## Report 9 — Database and Data Integrity

Complete 57-table catalog, ER diagram, duplicate-table matrix, and transaction audit are in [DATABASE_INVENTORY.md](file:///d:/purplefestgujarat/audit/DATABASE_INVENTORY.md).

- **57 InnoDB Tables:** 48 of 57 lack `FOREIGN KEY` constraints; 0 `ENUM`/`CHECK` constraints; **6 exact-duplicate table pairs** (`delegate_profiles`/`delegate_details`, `registration_disabilities`/`participant_disability_disclosures`, `registration_accommodations`/`accommodation_requests`, `coupons`/`coupon_codes`, `payments`/`payment_orders`, `notification_queue`/`notification_jobs`); **8 completely unused tables** + **8 write-only/seed-only tables**.
- **Seed & Connection Hazards:** `database/seed.sql` (5 partners, 0 `role_permissions`) drifts from `includes/database.php` (11 partners); `getDb()` executes `SELECT COUNT(*) FROM roles` + `ALTER TABLE` DDL on **every HTTP request**; `PDO::ATTR_EMULATE_PREPARES => true`; silent SQLite fallback strips all FKs.
- **Registration Numbering (`COUNT(*) + 1`):** `generateUniqueRegistrationCode()` (`includes/qr-service.php:228-247`) runs **outside** `$pdo->beginTransaction()` with no lock and no retry on unique violation. Existing rows **cannot be overwritten** (`UNIQUE` index + `INSERT`), **but** concurrent submissions receive duplicate numeric sequence prefixes (`000100-XXXX` & `000100-YYYY`), carry a $1/65{,}536$ collision HTTP 500 crash risk per concurrent pair, and reuse numbers if rows are hard-deleted.
- **Transaction Failures:** User creation (`users` + `user_roles`), duplicate checks, and coupon checks run **outside** `beginTransaction()` (orphaned users on rollback + TOCTOU duplicate registrations + TOCTOU coupon over-redemption). Accommodation dual-write generates 2 distinct UUIDs causing duplicate rows in UI.

---

## Report 10 — Security, Privacy and Accessibility

All 46 prioritized findings (12 P0, 12 P1, 12 P2, 6 P3, 4 P4) with exact file paths, line numbers, impact, remediation, and pre-transition necessity are documented in [SECURITY_PRIVACY_FINDINGS.md](file:///d:/purplefestgujarat/audit/SECURITY_PRIVACY_FINDINGS.md).

- **Security:** Zero CSRF verification; RBAC bypasses (`cms.php`, `audit-logs.php`, `registrations.php`, `overview.php`); active MFA overwrite bypass & plaintext TOTP secrets; Reflected XSS (`/login`, `/auth/login`, `/apply`); Stored XSS (Admin SLI `videoDemoUrl`); Open Redirect + GET Logout CSRF; hardcoded fallback secrets + Host Header Injection; no HTTPS redirect/HSTS/CSP; public `/README.md` & `/docs/*.md`; CSV formula injection; zero IP rate limiting.
- **Privacy (Technical vs. Legal):** Disability disclosures are optional in UI, stored unencrypted across 5 tables (including raw `registration_responses`), read without audit logging, partially leaked via `hasAccommodationRequest` to unauthorized admins, and lack working consent withdrawal, data export, or deletion controls (`privacy_consent_records` is write-only; `data_retention_requests` is unused). Legal DPDP compliance claims in `legal/privacy.php` are **not** backed by technical enforcement.
- **WCAG 2.2 AA Accessibility:** High-Contrast mode causes white-on-white invisible text; Yellow-on-Black mode causes yellow-on-white in custom components; default `#FFFFFF`/`#A459FF` (3.85:1) fails AA; wizard `innerHTML` re-render destroys keyboard focus on every toggle; `<h3>` inside `<button>` in disabilities explorer; missing `aria-describedby` error links; `<24px` targets on footer/Edit links; accessibility toolbar absent on `/login` and suffers FOUC.

---

## Report 11 — Test Execution Results

Complete 38-case test matrix is in [TEST_EXECUTION_RESULTS.csv](file:///d:/purplefestgujarat/audit/TEST_EXECUTION_RESULTS.csv).

- **Executed:** **10 tests** (9 safe read-only HTTP GET staging probes + 1 non-destructive static XSS/open-redirect verification `T-38`) -> **3 PASS** (`T-01`, `T-02`, `T-08`), **7 FAIL** (`T-03` staging catalog 404, `T-04` staging `/dashboard` HTTP 500, `T-05` `/README.md` & `/docs/*.md` exposed 200, `T-06` no HTTP->HTTPS redirect & missing HSTS/CSP, `T-07` `GET /api/auth/logout` 200 without CSRF, `T-09` orphaned `/api/auth/signup` & `/api/auth/password-reset` live on staging, `T-38` reflected XSS & open redirect confirmed statically).
- **Not Executed (`NOT RUN`):** **28 tests** (`T-10`–`T-37`), explicitly blocked by absence of local PHP/MySQL binaries, strict audit-only prohibition against mutating staging DB / creating accounts / sending emails, and live staging's missing `festival_editions` row.

---

## Report 12 — Previous Claims Verification

All 30 core claims from Section 35 plus 4 documentation claims are verified line-by-line in [PREVIOUS_CLAIMS_VERIFICATION.csv](file:///d:/purplefestgujarat/audit/PREVIOUS_CLAIMS_VERIFICATION.csv).

| Verdict | Core 30 Claims | All 34 Claims (incl. Docs) | Examples of Overstated / Contradicted Claims |
|---|---:|---:|---|
| `TRUE — RUNTIME VERIFIED` | **0** | **0** | None (mutating/auth runtime flows not executed) |
| `TRUE — STATIC IMPLEMENTATION VERIFIED` | **9** | **9** | Claims #2 (HMAC-SHA256), #3 (no PII in token), #9 (browser QR gen), #14 (USB input), #16 (minimized verify fields), #20 (bcrypt), #27 (RGST blocked from admin), #28 (no overwrite), #29 (`COUNT(*)+1`) |
| `PARTIALLY TRUE` | **15** | **15** | Claims #1, #5–#8, #10–#13, #15, #18–#19, #21, #24–#25 (implemented statically with staging breakage, races, XSS, or missing guards) |
| `MISLEADING` | **4** | **4** | **#17** (duplicate check-in constraint bypassed by reissue/scope & Day 2/3 broken), **#22** (MFA overwrite bypass & plaintext secrets), **#23** (no CSRF, GET logout open redirect, hardcoded fallback secret), **#26** (admin RBAC bypasses + dead announcements + 500-row audit cap) |
| `FALSE` | **2** | **6** | **#4** ("Only token hashes stored" — `mailer.php:272` stores plaintext `rawToken` in `notification_queue.payload` + deterministic reconstruction), **#30** ("Resend complete except domain verification" — primary INSERT fails, `api.qrserver.com` leak, no worker, lies `qrEmailed=true`), plus **#31–#34** (`README.md` Next.js stack, partner consent gate, WCAG 2.2 AA compliance, 160-bit TOTP entropy) |
| `NOT VERIFIABLE` | **0** | **0** | — |

---

## Report 13 — Proposed Architecture Gap Analysis

Full 26-dimension gap analysis, Delegate/Accessible architectural options, 19-capability Participant Dashboard assessment (`REUSE`/`MODIFY`/`BUILD FROM SCRATCH`), and 13-segment Data Migration Assessment are detailed in [FUTURE_ARCHITECTURE_GAP_ANALYSIS.md](file:///d:/purplefestgujarat/audit/FUTURE_ARCHITECTURE_GAP_ANALYSIS.md).

- **Key Takeaway:** Transitioning to an authenticated 3-category (`Attendee`, `SLI`, `Volunteer`) platform can **reuse/modify** ~12 existing foundations (`users`, `sessions`, `registrations`, `volunteer_applications`, `sli_applications`, `accommodation_requests`, `dashboard/index.php` after fixing line 14, `api/account/*`, `admin/*`), **but requires building from scratch** email/mobile verification + OTP, secure participant signup/login/password-reset UI, application editing & withdrawal, sanitized in-app notification history, document uploads (if desired), and a verified-email account-claim migration flow to prevent account takeover of existing passwordless registrations.

---

## Report 14 — Dependency and Decommissioning Assessment

Full Mermaid dependency graph, 13-component classification table, and separation of all 7 decommissioning decisions are in [QR_DEPENDENCY_ANALYSIS.md](file:///d:/purplefestgujarat/audit/QR_DEPENDENCY_ANALYSIS.md).

- **Two Critical "Do Not Accidentally Break" Couplings:**
  1. **`generateUniqueRegistrationCode()` lives inside `includes/qr-service.php:228-247`** — MUST be extracted (and replaced with an atomic sequence) before removing `includes/qr-service.php`, or `POST /api/registrations` will break.
  2. **`assets/js/qrcode-gen.js` renders Admin TOTP MFA enrollment QR codes** (`admin-portal.js:1938`) — MUST be retained even if participant QR passes are completely retired.
- **Immediate Safe Removal:** Remove `api.qrserver.com` and plaintext `rawToken` logging from `includes/mailer.php` immediately regardless of the QR desk decision. Do **NOT** drop `qr_credentials`, `credential_events`, or `checkins` tables if historical data exists.

---

## Report 15 — Prioritized Findings

All 46 findings (`FND-01` through `FND-46`) categorized across **P0 (12)**, **P1 (12)**, **P2 (12)**, **P3 (6)**, and **P4 (4)** — complete with Finding ID, severity, affected component, reproduction/evidence, impact, suggested remediation, dependencies, and pre-transition necessity — are catalogued in [SECURITY_PRIVACY_FINDINGS.md](file:///d:/purplefestgujarat/audit/SECURITY_PRIVACY_FINDINGS.md).

---

## Report 16 — Decisions Required From Project Owner

All **16 architectural, governance, security, and product decisions (`OD-01` through `OD-16`)** requiring explicit owner sign-off before any implementation work begins are documented with options and trade-offs in [OWNER_DECISIONS_REQUIRED.md](file:///d:/purplefestgujarat/audit/OWNER_DECISIONS_REQUIRED.md).

---

## Section 42 — Final Integrity Declaration

| Integrity Metric | Verified Count / Statement |
|---|---|
| **Number of files inspected** | **66** source, config, SQL, and documentation files inspected line-by-line (plus all **58** tracked static image assets verified present on disk = **124** total Git-tracked files accounted for) |
| **Number of PHP files inspected** | **44** (100% of tracked `.php` files in repository) |
| **Number of JavaScript files inspected** | **8** (6 custom `.js` files inspected line-by-line + 2 vendor bundles `html5-qrcode.min.js` v2.3.8 verified via SHA-256 and `gsap.min.js`) |
| **Number of database tables inspected** | **57** (100% of `CREATE TABLE` definitions in `database/schema.sql`) |
| **Number of API endpoints identified** | **21** distinct PHP API endpoint files (22 rewrite routes including `/api/qr/<id>`) |
| **Number of registration pathways identified** | **5** public hub pathways (`ATTENDEE`, `ACCESSIBLE` [UI modifier -> `ATTENDEE`+`PwD=YES`], `DELEGATE`, `VOLUNTEER`, `SLI`) + **4** additional DB-seeded categories (`STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`) = **8** backend category codes |
| **Number of authentication roles identified** | **4** genuine RBAC roles (`SUPERADMIN`, `ADMIN`, `RGST`, `PARTICIPANT`, of which 3 staff roles can authenticate; `ATTENDEE`/`DELEGATE`/`VOLUNTEER`/`SLI` are category codes, not auth roles) |
| **Number of tests executed** | **10** (9 safe read-only HTTP GET staging probes + 1 non-destructive static proof `T-38`) |
| **Number of tests passed** | **3** (`T-01`, `T-02`, `T-08`) |
| **Number of tests failed** | **7** (`T-03`, `T-04`, `T-05`, `T-06`, `T-07`, `T-09`, `T-38`) |
| **Number of tests not executed (`NOT RUN`)** | **28** (`T-10` – `T-37`) |
| **Number of features verified at runtime** | **0** end-to-end `VERIFIED WORKING` (26 features had partial read-only staging HTTP GET/auth-gate probes; 16 static inspection only) |
| **Number of features verified through static inspection only** | **16** purely static (+ **26** static + partial read-only staging HTTP probe = **42** total features) |
| **Number of incomplete / broken / blocked features** | **39 of 42** (`25 PARTIALLY IMPLEMENTED` + `9 BROKEN` + `2 DATABASE ONLY` + `1 CONFIGURATION BLOCKED` + `2 NOT IMPLEMENTED`; remaining `3` are `IMPLEMENTED — UNTESTED`) |
| **Number of unsupported / overstated previous claims** | **21 of 30** core claims (`15 PARTIALLY TRUE` + `4 MISLEADING` + `2 FALSE`), or **25 of 34** including 4 `FALSE` documentation claims (`README.md`, partner consent, WCAG AA, 160-bit TOTP) |
| **Files or directories that could not be inspected** | Secret files intentionally **NOT** inspected per security rules: local `.env.local` and server-side `/home1/tstpllmy/.pfg27_env.php`. Ignored legacy `node_modules/` (24,858 files), `.next/`, `data/pgdata/`, and raw reference PDF/DOCX binaries were inventoried by size/count only and not treated as active application code. |
| **Environments that could not be accessed** | (1) **Production environment** (not yet live on final domain). (2) **Bluehost cPanel / SSH / MySQL server console** (no direct SQL read access to `tstpllmy_pfg27` or server error logs). (3) **Resend dashboard / DNS records**. |
| **Dependencies that prevented verification** | (1) No local `php`, `mysql`, `mariadb`, or `sqlite3` runtime installed on Windows workstation. (2) Strict audit-only rules prohibiting mutating HTTP POST/PATCH/DELETE calls, account creation, or email dispatch on staging. (3) Live staging `festival_editions` missing active row (`HTTP 404`). (4) Unverified Resend sending domain. |
| **Whether the application source code remained unchanged** | **YES — 100% UNCHANGED.** Zero tracked application files were modified, added, or deleted. Only the 14 audit documentation artefacts were created inside `d:\purplefestgujarat\audit\`. |

