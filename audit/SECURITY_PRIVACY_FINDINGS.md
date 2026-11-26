# Prioritized Security, Privacy & WCAG 2.2 AA Accessibility Findings Register

**Project:** Purple Festival Gujarat 2027 (`d:\purplefestgujarat`)  
**Audit Scope:** Part J (Sections 30, 31, 32) & Part N (Report 15)  
**Severity Scale:** `P0` (Critical security, privacy or data-loss), `P1` (Core functionality broken or missing), `P2` (Architectural or substantial operational limitation), `P3` (Secondary functional deficiency), `P4` (Code quality, maintainability or cosmetic).

---

## Summary Counts by Severity

- **P0 (Critical):** 12 findings (`FND-01` – `FND-12`)
- **P1 (High / Core Broken):** 12 findings (`FND-13` – `FND-24`)
- **P2 (Medium / Architectural):** 12 findings (`FND-25` – `FND-36`)
- **P3 (Low / Secondary):** 6 findings (`FND-37` – `FND-42`)
- **P4 (Quality / Maintainability):** 4 findings (`FND-43` – `FND-46`)

---

## P0 — Critical Security, Privacy & Data-Loss Findings

### FND-01 — Site-Wide Missing CSRF Protection Across All Mutating Endpoints
- **Severity:** P0 | **Component:** `includes/auth.php:95-118`, all 21 `api/**/*.php` files
- **Description:** `verifyCsrfToken()` is implemented in `includes/auth.php` and `window.PFG_CSRF_TOKEN` is injected in `admin/index.php:189`, but **`verifyCsrfToken()` has ZERO callers anywhere in the repository**. Furthermore, `readRequestInput()` (`includes/config.php:253-266`) accepts `$_POST` form bodies and `text/plain` JSON (bypassing CORS preflight).
- **Evidence:** Repository-wide grep for `verifyCsrfToken` yields 1 definition (`includes/auth.php:95`), 0 invocations. Live staging `GET /api/auth/logout` returned `HTTP 200 {"success":true}`.
- **Impact:** Cross-site request forgery against staff admin actions (creating Superadmin accounts, altering CMS/fees, revoking passes, approving applications), MFA disable/overwrite, profile changes, and forced logout.
- **Remediation:** Require `verifyCsrfToken()` on all non-GET authenticated mutations, enforce `Content-Type: application/json`, regenerate CSRF token on login, and reject `GET` on `/api/auth/logout`.
- **Dependencies:** `admin/index.php`, `rgst/index.php`, `dashboard/index.php`, `login.php`.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-02 — Critical RBAC Bypass on Admin CMS & Audit Logs APIs
- **Severity:** P0 | **Component:** `api/admin/cms.php:70`, `api/admin/audit-logs.php:23`, `assets/js/admin-portal.js:124,128`
- **Description:** `DEFAULT_ROLE_PERMISSIONS['ADMIN']` explicitly excludes `cms.manage` and `audit_logs.read` (`includes/config.php:317-334`). However, `api/admin/cms.php:70` and `api/admin/audit-logs.php:23` only call `requireRole(['ADMIN', 'SUPERADMIN'])` and **never call `requirePermission()`**. `admin-portal.js:124,128` also hardcodes `visible: true` for both tabs.
- **Evidence:** Direct code inspection of `api/admin/cms.php:70`, `api/admin/audit-logs.php:23`, `includes/config.php:317-334`.
- **Impact:** Any regular `ADMIN` can edit/delete all public CMS content, change registration fees/active flags in `registration_categories`, and read full system audit logs without authorization.
- **Remediation:** Add `requirePermission(PERMISSIONS['CMS_MANAGE'], $user)` to `cms.php`, `requirePermission(PERMISSIONS['AUDIT_LOGS_READ'], $user)` to `audit-logs.php`, and fix `admin-portal.js` tab visibility checks.
- **Dependencies:** None.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-03 — Active TOTP MFA Secret Overwrite / Bypass & Plaintext Secret Storage
- **Severity:** P0 | **Component:** `api/auth/mfa.php:24-53,102-140`, `includes/auth.php:188`, `api/auth/login.php:120-145`
- **Description:** (1) `GET /api/auth/mfa` writes a new pending secret to `auth_accounts` even when `mfa_enabled = 1`, and `POST /api/auth/mfa` (`action=enable`) overwrites the active MFA secret **without requiring the user's password or current TOTP code**. (2) Raw Base32 secrets are stored unencrypted in `users.mfa_secret_encrypted`, `users.mfa_secret`, and `auth_accounts.token_hash`. (3) Entropy is 80-bit (`random_bytes(10)`) despite docblock claiming 160-bit. (4) Unlimited TOTP guessing is allowed on login and MFA endpoints (no lockout/rate limit) with 90s code replay.
- **Evidence:** `api/auth/mfa.php:36,42,102-140`, `includes/auth.php:182-188,224-249`, `api/auth/login.php:124-145`.
- **Impact:** Any brief session hijack or XSS permanently replaces a Superadmin's MFA device; any DB read compromises all TOTP seeds; weak brute-force resistance on 6-digit codes.
- **Remediation:** Block `GET`/`POST enable` when `mfa_enabled = 1` without current password + current TOTP verification; encrypt TOTP secrets with authenticated encryption (`sodium_crypto_aead_*` / AES-256-GCM); use `random_bytes(20)` (160-bit); enforce lockout/rate limit + single-use time-slice tracking; add recovery codes.
- **Dependencies:** Dedicated MFA encryption key in env.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-04 — Reflected XSS in `/login`, `/auth/login`, and `/apply` (`JSON_HEX_TAG` Omission)
- **Severity:** P0 | **Component:** `login.php:11-13,228`, `auth/login.php:4`, `apply/index.php:155-167,275-277` (plus inline script breakout in `admin/index.php:188` and `dashboard/index.php:940`)
- **Description:** User-controlled query parameters (`?next=`, `?redirect=`, `?category=`, `?pathway=`) are serialized into inline `<script>` blocks via `json_encode(..., JSON_UNESCAPED_SLASHES)` **without `JSON_HEX_TAG`**. Supplying `</script>` closes the script element and injects arbitrary HTML/JavaScript.
- **Reproduction (Non-Destructive Static Proof):** `login.php:228` renders `const nextParam = <?= json_encode($nextParam, JSON_UNESCAPED_SLASHES) ?>;`; `apply/index.php:275` renders `window.__PFG_WIZARD_BOOT__ = <?= json_encode($wizardBootData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;`.
- **Impact:** Account takeover of authenticated `SUPERADMIN`/`ADMIN`/`RGST` users via crafted phishing links (chaining directly with `FND-03` MFA overwrite and `FND-01` missing CSRF).
- **Remediation:** Add `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` to every inline `<script>` `json_encode()` call (or move boot data to `<script type="application/json">` parsed with `JSON.parse()`), and deploy a strict `Content-Security-Policy`.
- **Dependencies:** None.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-05 — Stored XSS in Admin Portal SLI Tab via `videoDemoUrl`
- **Severity:** P0 | **Component:** `assets/js/admin-portal.js:1317`, `api/registrations/index.php:480-485`
- **Description:** Unauthenticated public SLI registration validates `videoDemoUrl` via `FILTER_VALIDATE_URL` (which does NOT restrict scheme to `http`/`https`). The Admin SLI tab renders `<a href="' + esc(sd.videoDemoUrl) + '" target="_blank">` — `esc()` only HTML-encodes characters and does not block `javascript:` URIs.
- **Evidence:** `api/registrations/index.php:482`; `assets/js/admin-portal.js:1317`.
- **Impact:** Any anonymous attacker submitting an SLI application can execute arbitrary JS inside an `ADMIN`/`SUPERADMIN` browser session when the reviewer clicks "View ISL Video Demo".
- **Remediation:** Enforce `preg_match('#^https?://#i', $url)` on backend (`parse_url($url, PHP_URL_SCHEME) in ['http', 'https']`) AND validate URL scheme in `admin-portal.js` before rendering any `href`. Apply same rule to CMS `websiteUrl`, `logoUrl`, and `media.url`.
- **Dependencies:** None.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-06 — Live QR Bearer Token Leak to Third-Party `api.qrserver.com` & Plaintext DB Storage
- **Severity:** P0 | **Component:** `includes/mailer.php:130-160,196-198,223,254,272`
- **Description:** Every QR pass email embeds `<img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=12&data=<rawToken>">`, sending live bearer entry tokens (`PFG27.<nonce>.<sig>`) in GET query strings to an external third-party server (captured in third-party access logs, email image proxies, and browser history). In addition, `mailer.php:272` writes `'rawToken' => $rawToken` into `notification_queue.payload` JSON in plaintext (directly violating the "only token hashes stored" claim).
- **Evidence:** `includes/mailer.php:196-198,272`; `api/auth/password-reset.php:88` (also stores plaintext `resetUrl` in `notification_queue.payload`).
- **Impact:** Compromise of QR entry credentials via third-party logging or DB read; broken email QR images if `api.qrserver.com` is blocked or email clients block remote images.
- **Remediation:** Remove `api.qrserver.com` immediately; either discontinue QR pass emails (per Owner Decision) or generate QR PNGs server-side in PHP and attach inline (`cid:`); strip `rawToken` and `resetUrl` from `notification_queue.payload`.
- **Dependencies:** Owner Decision on QR decommissioning (`OD-01` / `OD-02`).
- **Necessary Before Architectural Transition?** **YES — Mandatory before sending real participant emails.**

### FND-07 — Unauthenticated Account Attachment / Impersonation via Email on `POST /api/registrations`
- **Severity:** P0 | **Component:** `api/registrations/index.php:529-543,621-625`
- **Description:** Any unauthenticated caller submitting `POST /api/registrations` with an existing user's email address (including another participant or staff account) silently attaches the new registration, disability disclosures, accommodation requests, and coupon redemptions to that victim's `user_id` **without verifying ownership**. Furthermore, if a registration already exists in that category, `HTTP 409` leaks the victim's `existingRegistrationCode` (`L622-625`).
- **Evidence:** `api/registrations/index.php:531-543,621-625`.
- **Impact:** Account/record poisoning, unauthorized consumption of another user's coupon limits, registration code enumeration, and severe identity-hijacking risk when transitioning to an account-based participant portal!
- **Remediation:** Require authenticated session or verified email ownership before attaching any registration to an existing `user_id`; remove `existingRegistrationCode` from unauthenticated 409 responses.
- **Dependencies:** New authenticated participant flow.
- **Necessary Before Architectural Transition?** **YES — Critical blocker for account-based transition.**

### FND-08 — Hardcoded Fallback Signing Secrets (`AUTH_SECRET` & `QR_SIGNING_SECRET`) & Host Header Injection
- **Severity:** P0 | **Component:** `includes/config.php:80-93`, `includes/qr-service.php:31-37`, `api/auth/password-reset.php:80`
- **Description:** If `.pfg27_env.php` is missing or unreadable, `AUTH_SECRET` silently falls back to `'pfg2027-prod-auth-secret-change-in-env'` and `QR_SIGNING_SECRET` falls back to `'pfg2027-prod-qr-signing-secret-change-in-env'` (and `qr-service.php:36` uses `'dev-qr-secret-change-in-production'`). Simultaneously, `APP_URL` falls back to untrusted `$_SERVER['HTTP_HOST']`, enabling Host Header Injection in password-reset links.
- **Evidence:** `includes/config.php:80-93`, `includes/qr-service.php:36`, `api/auth/password-reset.php:80`.
- **Impact:** Total authentication/QR forgery if env file fails to load; password-reset token theft via Host header poisoning.
- **Remediation:** Fail closed (throw fatal error if secrets or `APP_URL` are unset in production); never fall back to public repo strings or `HTTP_HOST`.
- **Dependencies:** `.pfg27_env.php` verified on host.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-09 — Unvalidated Open Redirect & GET Logout CSRF in `/api/auth/logout`
- **Severity:** P0 | **Component:** `api/auth/logout.php:11-30`
- **Description:** Accepts `GET` without CSRF validation and passes raw `$_GET['redirect']` directly to `header('Location: ...')`. Confirmed live on staging (`GET /api/auth/logout -> HTTP 200 {"success":true}`).
- **Evidence:** `api/auth/logout.php:25-28`; Staging probe `task-1416`.
- **Impact:** Phishing redirect via trusted festival domain (`https://.../api/auth/logout?redirect=https://evil.example`) combined with forced logout CSRF.
- **Remediation:** Restrict logout to `POST` with CSRF token; allow only strictly validated relative paths (`^/[a-zA-Z0-9/_-]*$`) if redirects are needed.
- **Dependencies:** Update logout callers if any used `GET`.
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-10 — Missing HTTPS Enforcement & Missing Core Security Headers on Staging
- **Severity:** P0 | **Component:** `.htaccess:24-36`, `includes/auth.php:58,264,285`
- **Description:** Live staging responds `HTTP 200` on **both** `http://` and `https://` — there is **no HTTP -> HTTPS redirect**. `.htaccess` omits `Strict-Transport-Security`, `Content-Security-Policy`, `Permissions-Policy`, and `php_flag display_errors Off`. Plaintext HTTP exposes login credentials, `pfg_session` cookies (`Secure` flag disabled on HTTP), disability disclosures, and blocks browser `getUserMedia()` camera scanning. Additionally, `includes/auth.php` trusts spoofable `X-Forwarded-Proto`.
- **Evidence:** Live staging probes (`task-1406`): `GET http://.../ -> 200`, `GET https://.../ -> 200`, zero HSTS/CSP/Permissions-Policy headers.
- **Impact:** Man-in-the-middle credential & sensitive disability data interception; broken camera scanner.
- **Remediation:** Enforce 301 HTTP->HTTPS redirect in `.htaccess`, add HSTS/CSP/Permissions-Policy headers, force `Secure` cookie flag in production, and disable `display_errors`.
- **Dependencies:** Valid SSL cert on target domain (already working on staging!).
- **Necessary Before Architectural Transition?** **YES — Mandatory.**

### FND-11 — Unencrypted Storage & Unaudited Access to Sensitive Disability / Health Disclosures
- **Severity:** P0 (Privacy) | **Component:** `database/schema.sql:362-422`, `api/registrations/index.php:935-1052`, `api/admin/registrations.php:318-376,554-574`, `api/admin/accommodations.php:41-187`
- **Description:** (1) RPwD 2016 disability disclosures and free-text medical/accessibility notes are stored in plaintext across 5 tables (`registration_disabilities`, `participant_disability_disclosures`, `registration_accommodations`, `accommodation_requests`, and raw `registration_responses.answers`). (2) `GET` reads of sensitive disability/accommodation data are **never logged** in `audit_logs`. (3) When an admin lacks `accommodations.read`, `api/admin/registrations.php:554-574` still leaks `hasAccommodationRequest` & `accommodationStatus` and allows filtering by `?hasAccommodations=true`. (4) `privacy_consent_records` is write-only (no consent withdrawal or admin visibility), `data_retention_requests` is completely unused (no deletion/export mechanism), and no retention policy exists.
- **Evidence:** Direct code inspection across the 4 listed files.
- **Impact:** High privacy/DPDP exposure for vulnerable PwD participants; legal privacy statement claims (`legal/privacy.php:94`) are not backed by working technical controls.
- **Remediation:** Consolidate duplicate tables, remove disability disclosures from raw `registration_responses`, encrypt sensitive free-text disclosures at rest, log all `GET` reads of sensitive records, mask `hasAccommodationRequest` when unauthorized, and implement consent withdrawal + deletion request workflows.
- **Dependencies:** Owner Decision on data retention & DPDP governance.
- **Necessary Before Architectural Transition?** **YES — Mandatory before public launch.**

### FND-12 — Public Exposure of Internal Audit/Architecture Documentation on Staging (`/README.md`, `/docs/*.md`)
- **Severity:** P0 (Information Disclosure) | **Component:** `.htaccess:34-35`, `README.md`, `docs/*.md`
- **Description:** Live staging serves `/README.md`, `/docs/WEBSITE_AUDIT.md`, `/docs/ACCESSIBILITY_AND_SECURITY.md`, and `/docs/CONTENT_INVENTORY.md` with **HTTP 200** (publicly downloadable). If `audit/` is ever copied to webroot, `.htaccess` does **not** block `/audit/` either.
- **Evidence:** Live staging probes (`task-1406`, `task-1416`): all 4 returned HTTP 200.
- **Impact:** Exposes internal architecture, security notes, partner consent status, and file paths to attackers.
- **Remediation:** Add `RewriteRule ^(docs|audit|data|node_modules)(/|$) - [F,L,NC]` and block `*.md` in `.htaccess`; ensure `.cpanel.yml` / hosting webroot removes `README.md` and `docs/`.
- **Dependencies:** None.
- **Necessary Before Architectural Transition?** **YES — Immediate quick win.**

---

## P1 — Core Functionality Broken or Missing

### FND-13 — `/dashboard` Crashes with Fatal HTTP 500 `TypeError` for ALL Users (Verified Live on Staging)
- **Severity:** P1 | **Component:** `dashboard/index.php:14`, `includes/auth.php:565`
- **Description:** `dashboard/index.php:14` calls `requireAuth('/login?next=/dashboard')` passing a `string`, while `includes/auth.php:565` declares `requireAuth(?PDO $pdo = null): array` under `declare(strict_types=1)`. Confirmed returning **HTTP 500** on live staging (`GET /dashboard` and `GET /dashboard/`).
- **Impact:** Participant dashboard, profile editing UI, accommodation request UI, and participant MFA UI are 100% inoperable.
- **Remediation:** Change call to `requireAuth()` (or check `getCurrentUser()` and redirect manually).
- **Necessary Before Architectural Transition?** **YES.**

### FND-14 — Live Staging Registration Broken (`HTTP 404: No active festival edition configured.`)
- **Severity:** P1 | **Component:** Live staging DB (`tstpllmy_pfg27`), `api/registrations/index.php:31-44,192-195,488-491`, `includes/database.php:215-221`
- **Description:** Live staging `GET /api/registrations` returns `HTTP 404 {"error":"No active festival edition configured."}` and `/apply` falls back to 4 hardcoded categories (`id="ATTENDEE"`). Because `POST /api/registrations` calls the exact same `pfgGetActiveEdition($pdo)`, all registration submissions fail with 404 on staging right now.
- **Evidence:** Live staging probes (`task-1406` & `/apply` boot inspection).
- **Impact:** Core registration workflow is completely non-functional on staging.
- **Remediation:** Audit and seed `festival_editions` and `registration_categories` in staging MySQL; fix `ensureDatabaseInitialized()` not to rely solely on `COUNT(*) FROM roles`.
- **Necessary Before Architectural Transition?** **YES.**

### FND-15 — Participants Cannot Log In + Orphaned Signup & Broken Password Reset Endpoints
- **Severity:** P1 | **Component:** `api/auth/login.php:109-117`, `api/auth/signup.php`, `api/auth/password-reset.php`, `includes/header.php:264`
- **Description:** `api/auth/login.php` hard-blocks `PARTICIPANT` with `HTTP 403`, yet header still displays "Participant & Staff Login", `/api/auth/signup` is live on staging creating locked-out accounts, and `/api/auth/password-reset` is live with zero UI, missing `PASSWORD_RESET` template (`NULL` body), and no email queue worker.
- **Impact:** Contradictory auth architecture; dead-end accounts; broken links.
- **Remediation:** Align auth system to Owner's chosen account-based 3-category architecture (`OD-03`).
- **Necessary Before Architectural Transition?** **YES — Core part of the transition.**

### FND-16 — Missing Granular Permission Enforcement in `api/admin/registrations.php` & `overview.php`
- **Severity:** P1 | **Component:** `api/admin/registrations.php:199,659-913`, `api/admin/overview.php:23`, `api/admin/users.php:271,311-358`
- **Description:** (1) `registrations.php` skips `registrations.read.all`, `registrations.write`, `volunteers.review`, `sli.review`, and auto-QR `credentials.issue`, AND commits `UPDATE registrations` before checking `403` on manual QR actions. (2) `overview.php` skips `payments.read`, `accommodations.read`, `attendance.read.full`. (3) `users.php` allows `SUPERADMIN` self-demotion via `roleCode`, allows assigning admin `customPermissions` to `RGST`/`PARTICIPANT`, and does not revoke sessions on password reset.
- **Remediation:** Enforce explicit `requirePermission()` before any DB mutation; block self-demotion and last-Superadmin demotion; revoke sessions on password reset.
- **Necessary Before Architectural Transition?** **YES.**

### FND-17 — RGST Default Scope `"GENERAL_ENTRY"` Blocks Day 2 & Day 3 Check-Ins
- **Severity:** P1 | **Component:** `rgst/index.php:159,525-532`, `includes/qr-service.php:471-496`
- **Description:** RGST sends literal `"GENERAL_ENTRY"` instead of `null`/`todayIst`, overriding `ONCE_PER_DAY` IST scoping and blocking returning attendees on Day 2 and Day 3 as duplicates.
- **Necessary Before Architectural Transition?** Only if QR/RGST is retained (`OD-01`).

### FND-18 — Manual Registration Code Lookup Falsely Returns `REVOKED` After Pass Reissue
- **Severity:** P1 | **Component:** `includes/qr-service.php:567-588`
- **Description:** Typing `PFG-2027-XXXXXX` matches the old revoked `credential_code` before reaching the `registrations.registration_code` fallback to locate `-R2`, falsely denying valid attendees entry.
- **Necessary Before Architectural Transition?** Only if QR/RGST is retained (`OD-01`).

### FND-19 — Notification Queue Has Zero Worker/Cron & `mailer.php` Primary INSERT Fails on Every Send
- **Severity:** P1 | **Component:** `includes/mailer.php:117-168`, `database/schema.sql:834-885`, `api/registrations/index.php:1097,1120`
- **Description:** (1) No background worker/cron drains `notification_queue`, so all `QUEUED` emails (password resets, failed Resend sends) are never delivered. (2) `mailer.php:120-122` references non-existent `provider` and `provider_message_id` columns, failing every primary INSERT and silently dropping `sent_at`, `body_html`, `body_text`, and `last_error`. (3) `api/registrations/index.php:1120` hardcodes `qrEmailed = true` even when delivery fails, while confirmation screen renders no QR.
- **Necessary Before Architectural Transition?** **YES.**

### FND-20 — Severe WCAG 2.2 AA Failures in Accessibility Contrast Modes & Registration Wizard Focus
- **Severity:** P1 (Accessibility) | **Component:** `assets/css/app.css`, `viksit-section.css`, `partners-section.css`, `disabilities-section.css`, `assets/js/registration-wizard.js:1993-2002`
- **Description:**
  1. **High Contrast Mode (`data-contrast="high"`):** Makes white text inside dark sections (Viksit, Partners, Programme hero, Accessibility hero, Footer) **white-on-white (1.0:1 invisible!)**.
  2. **Yellow-on-Black Mode (`data-contrast="yellow-black"`):** Leaves custom pill/card backgrounds white with `#FFE600` text (**~1.25:1 unreadable!**).
  3. **Default Theme Contrast Failures (SC 1.4.3):** `#FFFFFF` on `#A459FF` (3.85:1), `#A459FF` on `#FFFFFF`/`#F2E9FE`/`#DDDDF9` (2.90–3.85:1), `#099F9F` on `#FFFFFF` (3.24:1), focus ring `#A459FF` on `#DDDDF9` (2.90:1).
  4. **Wizard Keyboard Focus Loss (SC 2.4.3):** Every radio/checkbox/pill click calls `render()` replacing `root.innerHTML`, destroying keyboard focus for screen-reader and keyboard-only users!
  5. **Invalid HTML5 (`includes/disabilities-section.php:325-353`):** `<h3>` nested inside `<button>`.
  6. **Missing Error Associations & Target Sizes:** Wizard inputs lack `aria-describedby` / `aria-invalid`; footer legal links & Step 5 Edit buttons are ~16px height (fails WCAG 2.2 SC 2.5.8 24×24px minimum).
- **Necessary Before Architectural Transition?** **YES — Essential for an inclusion-focused disability festival.**

### FND-21 — Non-Atomic Registration Creation, `COUNT(*)+1` Concurrency Race & Duplicate Accommodations Bug
- **Severity:** P1 | **Component:** `api/registrations/index.php:532-730,1038`, `includes/qr-service.php:228-247`
- **Description:** `users` + `user_roles` insert outside transaction (orphaned rows on rollback); `COUNT(*)+1` numbering outside transaction without lock or retry (duplicate sequence prefixes, 1/65536 HTTP 500 collision); duplicate same-category registration TOCTOU race (no DB unique constraint); accommodation dual-write generates 2 different UUIDs causing duplicate rows in UI.
- **Necessary Before Architectural Transition?** **YES.**

### FND-22 — Coupon Usage TOCTOU Race Condition & Silent Admin Write Error Swallowing
- **Severity:** P1 | **Component:** `api/registrations/index.php:633-706,1055-1082`, `api/admin/coupons.php:142-172,246-264`
- **Description:** Coupon `max_uses` and `max_uses_per_user` are checked outside transactions without `FOR UPDATE` locks and incremented without atomic guard; `api/admin/coupons.php` wraps dual-table writes in empty `catch (Throwable)` blocks, returning `201`/`200` on failed writes.
- **Necessary Before Architectural Transition?** Recommended before enabling paid delegate fees.

### FND-23 — Zero Payment Gateway Implementation Despite Paid Delegate Schema & Status Branches
- **Severity:** P1 | **Component:** `database/schema.sql:663-727`, `api/registrations/index.php:710-723`
- **Description:** If CMS sets `fee_amount_inr > 0` and `requires_payment = 1`, delegate registrations enter `SUBMITTED` / `PENDING` with zero payment checkout UI, zero Razorpay webhook, zero email sent, and permanently consumed coupons.
- **Necessary Before Architectural Transition?** Depends on Owner Decision (`OD-04` Delegate/Fee strategy).

### FND-24 — Zero Email Verification, Mobile Verification, OTP, SMS, or WhatsApp Infrastructure
- **Severity:** P1 | **Component:** Entire repository
- **Description:** No email verification (`users.email_verified_at` is always `NULL` for participants), no mobile verification, no OTP lifecycle, and zero SMS/WhatsApp code or schema support.
- **Necessary Before Architectural Transition?** **YES — Email (or mobile) verification is mandatory before linking existing registrations to new participant accounts.**

---

## P2 — Architectural Issues & Substantial Operational Limitations

- **FND-25 (CSV Formula Injection & Fake XLSX):** `api/admin/registrations.php:187-194,634-635` does not neutralize leading `=,+,-,@,\t,\r` cells, and `?export=xlsx` actually outputs `.csv`.
- **FND-26 (Zero IP Rate Limiting & Login Enumeration Oracles):** No IP rate limiting on any endpoint; `api/auth/login.php` leaks valid accounts via bcrypt timing delta, pre-password 423 lockout, and post-password 403 vs 401; sticky lockout counter re-locks immediately on 1st typo after 15m; `api/coupons/validate.php` allows coupon brute-forcing; `api/registrations/index.php` allows unlimited spam/email bombing.
- **FND-27 (Duplicate Check-In Bypasses):** `UNIQUE(credential_id, entitlement_scope)` (`schema.sql:590`) allows a 2nd same-day check-in after QR reissue; `api/checkins/confirm.php:57-59` allows unbounded unvalidated `scopeOverride`; no undo/override or zone/session check-in.
- **FND-28 (Audit Logs Permanently Truncated at 500 Rows & Unaudited Sensitive Reads):** `api/admin/audit-logs.php:66` hardcodes `LIMIT 500` before PHP pagination; `admin_actions` table is write-only; `PATCH /api/admin/cms` media skips audit & revisions; sensitive disability/accommodation reads are unaudited.
- **FND-29 (Per-Request DDL Execution & Missing FKs & 6 Duplicate Table Pairs):** `includes/database.php:187-221` runs `ALTER TABLE` DDL on every HTTP request; 48/57 tables have zero FKs; 6 duplicate table pairs waste writes and cause drift; `PDO::ATTR_EMULATE_PREPARES => true` enabled globally; silent SQLite fallback strips all FKs.
- **FND-30 (Missing Notifications Across 20+ Lifecycle Events):** Zero emails on application rejection, waitlist, info requested, QR revocation, `/api/qr/pass` reissue, volunteer/SLI duty changes on already-approved applicants, accommodation status updates, cancellation, or standalone admin resend (without revoking QR).
- **FND-31 (Unvalidated Registration Inputs):** Backend omits validation for Volunteer 16+ age (`api/registrations/index.php:850`), empty `attendingDays`, max lengths on `organizationName`/`designation`/role fields (MySQL 500 risk), `sli.require_crr` setting, and phone regex accepts punctuation `"-------"`.
- **FND-32 (Admin Operational Caps & Missing Capabilities):** Volunteers & SLI tabs hard-capped at 100 rows with no pagination/search; detail modal discards 6 fetched sections (delegate/vol/sli details, checkins, payments, disabilities); no participant edit/delete/bulk actions; no staff MFA reset; cleartext `window.prompt()` password reset; password change in `profile.php` does not revoke active sessions.
- **FND-33 (CMS Discrepancies & Dead Announcements):** `announcements` table is never rendered publicly; `hero.title`, `hero.subtitle`, `contact.subtitle`, `partner.website_url` ignored on `index.php`; `index.php:459-466` omits `consent_verified = 1` filter; gallery `LIMIT 6` lacks `ORDER BY`; `DELETE partner` orphans `organizations`.
- **FND-34 (HTTP Staging Breaks Camera Scanner & `stopCamera()` Null Bug):** `rgst/index.php:935-1015` lacks `window.isSecureContext` diagnostic and sets `cameraScanner = null` before async `.clear()`.
- **FND-35 (Fake Unscannable `generateQrSvgDataUrl()` Placeholder):** `includes/qr-service.php:129-144` returns a dummy SVG with raw token text instead of a scannable QR code, returned as `qrDataUrl` by `api/qr/pass.php` and `GET /api/registrations`.
- **FND-36 (Deployment Hygiene & Zero Automated Tests):** `.cpanel.yml` uses `cp -R` (never removes stale files), deploys `database/` inside `public_html`, hardcodes cPanel user; GitHub Actions only uploads a tarball (no auto-deploy, zero unit/integration/a11y tests).

---

## P3 — Secondary Functional Deficiencies

- **FND-37:** `dashboard/index.php` (once crash fixed) lacks accommodation edit/cancel, registration edit/withdrawal, check-in history list display, session list, and PDF/PNG pass download.
- **FND-38:** `api/checkins/index.php` caps operator `OWN` count at 100 (and `rgst/index.php` SSR caps at 50) while `FULL` loads entire `registrations` and `checkins` tables into PHP memory (OOM hazard at scale).
- **FND-39:** `api/admin/overview.php` counts soft-deleted registrations in `accommodationSummary` and `checkins`/`recentCheckins`, and risks double-counting payments across `payments`/`payment_orders`.
- **FND-40:** `api/admin/accommodations.php:234` overwrites existing coordinator notes when JS sends empty string `""`; collapses multiple requests per registration into one row.
- **FND-41:** `assets/js/registration-wizard.js` misses `sliAffiliation` input in Step 2 UI, loads `qrcode-gen.js` without ever using it, scrolls smoothly ignoring `prefers-reduced-motion`, and sets `showHub = false` on invalid `?category=` param.
- **FND-42:** Email templates (`includes/mailer.php`) fail WCAG (`<html>` missing `lang="en"`, `#A459FF` 3.4:1 link contrast, skipped `<h2>`, table missing `<th scope="row">`), and PHP `mail()` fallback drops plain-text part completely.

---

## P4 — Code Quality, Maintainability & Cosmetic Issues

- **FND-43:** ~809 MB of legacy Next.js/PostgreSQL/raw media bloat (`node_modules/`, `.next/`, `data/pgdata/`, 98 MB raw docs) sits untracked/ignored in workspace root alongside obsolete Next.js `README.md` and `docs/*.md` (actual PHP site is ~7.3 MB).
- **FND-44:** Duplicate login entry points (`login.php` and `auth/login.php`), dead helper functions (`pfgCreateUserSessionCompat`, `pfgQueueNotificationCompat`), and 8 completely unused DB tables.
- **FND-45:** Missing `OPTIONS` handlers on `api/registrations/index.php`, `api/account/*`, `api/qr/pass.php`, `api/coupons/validate.php`; inconsistent `.php` extension usage across JS `fetch()` calls; `.htaccess` lacks rewrite for `/api/admin/registrations/<uuid>`.
- **FND-46:** `assets/js/accessibility.js` loads at bottom of `<body>` (causing contrast/scale FOUC), is absent on `/login`, repeats identical live-region strings, and resets `reducedMotion: false` even when OS prefers reduced motion; `programme.php` `.ics` UID uses `.org` instead of `.com`; `bootstrap-superadmin.php` accepts password via CLI `$argv[2]`.

