# Phase 1 Completion Report — Security Containment & Native PHP/MySQL Dev/Test Environment

**Phase:** Phase 1 — Security Containment and Safe Development Environment  
**Date:** 2026-11-26  
**Technology Compliance:** 100% Native PHP 8.2.12 + MariaDB 10.4.32 + Apache 2.4.58 / Bluehost cPanel (**Zero Docker**)

---

## 9.1 Summary
- **Phase Objective:** Establish a reproducible, Bluehost-compatible native PHP/MySQL development and automated testing environment without Docker, enforce fail-closed secret/DB/email isolation, and prepare Bluehost-safe Apache `.htaccess` / `.user.ini` / `.cpanel.yml` security containment.
- **Planned Scope:** Native local XAMPP PHP + MariaDB setup (`pfg27_dev`, `pfg27_test`), safe email sink (`MAIL_DRIVER=file`), Bluehost CLI environment verifier, `.htaccess` + `.user.ini` + `.cpanel.yml` containment, fail-closed `includes/config.php` / `includes/database.php` / `includes/qr-service.php`, and automated Phase 1 test suite.
- **Actual Completed Scope:** 100% of local native PHP/MySQL environment, fail-closed containment code, Bluehost CLI verifier, and 11 automated tests completed and verified passing (`11/11 PASS`).
- **Pending Work (External / Deployment-Gated):** Deploying the hardened `.htaccess`, `.user.ini`, `.cpanel.yml`, and PHP config files to live Bluehost staging (and optionally enabling cPanel Directory Privacy on staging + running `scripts/verify-bluehost-env.php` via cPanel Terminal) requires explicit deployment authorization.
- **Dependencies:** Local XAMPP MariaDB daemon (`C:\xampp\mysql\bin\mysqld.exe`); Bluehost cPanel access when authorized to deploy staging containment.
- **Approval Requirements:** Authorization to proceed to **Phase 2 (Core P0 Security & Authorization Remediation)** — and confirmation of **`D-20`** (staff MFA policy) and **`D-01`** (ceasing participant QR issuance & removing `api.qrserver.com`) + whether to deploy Phase 1 containment to staging now or after Phase 2.

---

## 9.2 Code Changes

| File | Change | Reason | Related Requirement / Finding |
|---|---|---|---|
| `.htaccess` | Modified | Block `/docs`, `/audit`, `/project-docs`, `/tests`, `/scripts`, `/data`, `/node_modules`, `README.md`, `*.md`, `*.yml`, `*.yaml`, backups; add non-localhost HTTP->HTTPS 301 redirect + HSTS, CSP, Permissions-Policy (Bluehost CGI/FPM-safe: zero bare `php_flag`) | `REQ-03`, `FND-10`, `FND-12` |
| `.user.ini` | Created | Bluehost/cPanel per-directory PHP hardening (`display_errors=Off`, `log_errors=On`, `expose_php=Off`, HttpOnly/strict session) | `REQ-03`, `FND-10` |
| `.cpanel.yml` | Modified | Deploy `.user.ini` and remove stale `/docs`, `/audit`, `/project-docs`, `/tests`, `/README.md` from `$DEPLOYPATH` | `REQ-03`, `FND-12`, `FND-36` |
| `.gitignore` | Modified | Ignore `tests/tmp/`, `.env.local.php`, `.env.test.php`, `*.bak`, `*.orig` | `REQ-02` |
| `includes/config.php` | Modified | Enforce `display_errors=0`, `$GLOBALS['pfgLoadedEnv']` (closure-scope fix), `PFG_ENV_FILE`, `APP_ENV`, fail-closed `pfgValidateSecret()`, disable `HTTP_HOST` fallback in staging/prod, add `MAIL_DRIVER` & `MAIL_SINK_DIR` | `REQ-02`, `REQ-03`, `FND-08` |
| `includes/database.php` | Modified | Disable silent SQLite fallback in staging/production; add `getDb(bool $reset = false)` | `REQ-02`, `FND-29` |
| `includes/qr-service.php` | Modified | Remove hardcoded `'dev-qr-secret-change-in-production'` fallback secret | `FND-08` |
| `includes/mailer.php` | Modified | Add `MAIL_DRIVER=file` local JSON sink (scrubbing `rawToken`/`resetUrl`) and `MAIL_DRIVER=disabled` guard so dev/test never calls Resend or `mail()` | `REQ-02`, `FND-06` |
| `scripts/verify-bluehost-env.php` | Created | CLI-only Bluehost hosting verifier (PHP version, 8 extensions, MySQL version/IST timezone, redacted secrets, parent dir, optional Resend TLS check) | `REQ-02` (Bluehost verification) |
| `tests/router.php` | Created | Local native PHP router mirroring `.htaccess` clean routes & 403 block rules | `REQ-02` |
| `tests/run.php` | Created | Zero-dependency native PHP CLI test runner with ephemeral `tests/tmp/test_env.php` targeting `pfg27_test` | `REQ-02` |
| `tests/phase1_containment_test.php` | Created | 11 automated tests verifying syntax lint, MariaDB 57-table init, fail-closed secrets/DB/APP_URL, safe mail sink, `.htaccess`, live HTTP routing, and Bluehost verifier | `REQ-02`, `REQ-03` |
| `project-docs/*` | Updated / Created | Removed Docker mandate; added `IMPLEMENTATION_MASTER_ROADMAP.md`, `LOCAL_DEV_SETUP.md`, `PHASE_01_PLAN.md`, `PHASE_01_IMPLEMENTATION_REPORT.md`, `PHASE_01_TEST_RESULTS.csv`, `SECURITY_REMEDIATION_TRACKER.csv`, `PHASE_01_COMPLETION_REPORT.md` | Governance (§8, §9) |

---

## 9.3 Database Changes
- **Production / Staging Databases:** **ZERO changes.** Live Bluehost `tstpllmy_pfg27` was not touched.
- **Local Isolated MariaDB (`127.0.0.1:3306`, `10.4.32-MariaDB`):** Created two isolated databases: `pfg27_dev` and `pfg27_test` (`utf8mb4_unicode_ci`). Initialized `pfg27_test` cleanly from existing `database/schema.sql` + `includes/database.php` (`57` tables, `1` active edition, `8` categories, `11` partners verified by `T1-02`).
- **Schema Migrations:** None in Phase 1 (versioned migrations begin in Phase 3).
- **Rollback:** Drop local `pfg27_dev`/`pfg27_test` if needed; revert tracked files via `git checkout -- .htaccess .cpanel.yml .gitignore includes/config.php includes/database.php includes/qr-service.php includes/mailer.php` and delete `.user.ini`, `scripts/`, `tests/`.

---

## 9.4 Test Results
- **Tests Planned:** `12` (`T1-01` – `T1-12`)
- **Tests Executed:** `11` (`T1-01` – `T1-11`)
- **Tests Passed:** `11` (**100% of executed tests passed**)
- **Tests Failed:** `0` *(initial run caught 1 real closure-scope bug in `includes/config.php` `$pfgLoadedEnv` + stopped MariaDB process; both resolved and re-run passed `11/11`)*
- **Tests Not Executed (`NOT RUN`):** `1` (`T1-12` — live Bluehost staging verification, intentionally deferred until deployment is authorized).
- **Evidence Location:** [PHASE_01_TEST_RESULTS.csv](file:///d:/purplefestgujarat/project-docs/PHASE_01_TEST_RESULTS.csv) & `tests/tmp/phase1_results.json`.

---

## 9.5 Security Findings Addressed in Phase 1 (Exact §17.6 Classifications)
- **`FND-08` (Hardcoded fallback signing secrets & Host Header Injection):** **`FIXED AND VERIFIED`** (verified locally by `T1-03`, `T1-04`, `T1-06`).
- **`FND-10` (Missing HTTPS enforcement, security headers & `display_errors=Off`):** **`FIXED BUT NOT RUNTIME VERIFIED`** on live Bluehost staging (verified locally by `T1-09` & `T1-11`; pending authorized staging deployment `T1-12`).
- **`FND-12` (Public exposure of `/README.md`, `/docs/*.md`, `/audit/`, `/project-docs/`):** **`FIXED BUT NOT RUNTIME VERIFIED`** on live Bluehost staging (verified locally HTTP 403 by `T1-09` & `T1-10`; pending authorized staging deployment `T1-12`).
- **`FND-29` (Silent SQLite fallback portion):** Partial remediation verified (`T1-05` blocks silent SQLite in prod/staging); full finding remains **`STILL OPEN`** until Phase 3 removes per-request DDL & reconciles duplicate tables/FKs.
- **Remaining P0 Findings (`FND-01`–`FND-07`, `FND-09`, `FND-11`):** **`STILL OPEN`** — scheduled for **Phase 2**. Full 46-finding tracker in [SECURITY_REMEDIATION_TRACKER.csv](file:///d:/purplefestgujarat/project-docs/SECURITY_REMEDIATION_TRACKER.csv).

---

## 9.6 Implementation Status (Updated)
- **Local Native PHP/MySQL Test Environment & Email Sink (`REQ-02`):** **`VERIFIED WORKING`** (PHP 8.2.12 + MariaDB 10.4.32 + 11/11 tests passing).
- **Fail-Closed Secrets, Env Isolation & Bluehost Verifier (`REQ-03` local):** **`VERIFIED WORKING`** locally; Bluehost live deployment **`CONFIGURATION BLOCKED`** pending deployment authorization.
- **All other application features (Auth, Registration, Admin, RGST, Dashboard, Notifications, WCAG):** Unchanged from Phase 0 baseline; scheduled for Phases 2–15.

---

## 9.7 Regression Review
- **Public website pages (`/`, `/apply`, `/login`, `/programme`, `/accessibility`):** Verified HTTP 200 locally (`T1-10`) — **zero regressions**.
- **Protected portal/API gates (`/admin` -> 302, `/rgst` -> 302, `/api/admin/overview` -> 401, `/api/auth/me` -> 200):** Verified locally (`T1-10`) — **zero regressions**.
- **Catalog API (`GET /api/registrations`):** Verified HTTP 200 returning seeded `gujarat-2027` edition + 8 categories on local MariaDB (`T1-10`).
- **Historical staging data:** Untouched (`0` remote writes).

---

## 9.8 Final Declaration
- **Working:** Native local Windows XAMPP PHP 8.2.12 + MariaDB 10.4.32 dev/test runtime (`pfg27_dev`, `pfg27_test`), automated PHP test harness (`tests/run.php`, `11/11 PASS`), fail-closed secret/DB/APP_URL validation, safe local file email sink (`MAIL_DRIVER=file`), Bluehost CLI environment verifier (`scripts/verify-bluehost-env.php`), and Bluehost-safe `.htaccess` / `.user.ini` / `.cpanel.yml` containment locally.
- **Implemented but untested on live Bluehost:** `.htaccess` HTTPS redirect, security headers, `.user.ini`, and `.cpanel.yml` stale-doc cleanup (pending authorized deployment + running `verify-bluehost-env.php` on Bluehost cPanel).
- **Incomplete / Unsafe:** Live Bluehost staging remains unpatched until deployment is authorized, and `FND-01`–`FND-07`, `FND-09`, `FND-11` (CSRF, RBAC bypasses, MFA overwrite/plaintext, Reflected & Stored XSS, GET Logout/Open Redirect, `api.qrserver.com` leak, unauthenticated email attachment, disability privacy controls) remain **`STILL OPEN`** for Phase 2.
- **Requires Owner Approval:**
  1. Authorization to proceed immediately into **Phase 2 (Core P0 Security & Authorization Remediation)** (plus `D-20` staff MFA policy and `D-01` ceasing participant QR issuance / removing `api.qrserver.com`).
  2. Whether to deploy Phase 1 containment to Bluehost staging now or batch-deploy after Phase 2 P0 fixes are verified locally (recommended: enable cPanel Directory Privacy on staging now, execute Phase 2 locally with full negative security tests, then deploy Phases 1 + 2 together).
- **Can Phase 2 Begin?** **YES — all Phase 1 local runtime & test prerequisites are met (`11/11 PASS`).**

