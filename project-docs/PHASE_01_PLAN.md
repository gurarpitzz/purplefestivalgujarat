# Phase 1 Plan — Security Containment & Native Bluehost-Compatible Local Environment

**Phase:** Phase 1 — Security Containment and Safe Development Environment  
**Status:** Executing (Authorized 2026-11-26)  
**Technology Constraint:** 100% Native PHP + MySQL/MariaDB + PDO + Apache + cPanel (**ZERO Docker / ZERO Containers**)

---

## 1. Entry Prerequisites
- Phase 0 baseline, source document reconciliation, and 14-conflict register completed (`PHASE_00_COMPLETION_REPORT.md`).
- Project Owner authorization granted for Phase 1 using native PHP/MySQL/Apache strategy.
- Verified local runtime on Windows workstation: `C:\xampp` (`PHP 8.2.12`, `MariaDB 10.4.32`, `Apache 2.4.58`).

## 2. Scope & Requirements Traceability
- **`REQ-02`:** Reproducible local native PHP + MySQL/MariaDB development & isolated testing environment (`pfg27_dev`, `pfg27_test`) with local file-based email sink (`MAIL_DRIVER=file`) and native PHP CLI test runner (`tests/run.php`).
- **`REQ-03` (`FND-08`, `FND-10`, `FND-12`, `FND-36`):**
  1. Harden `.htaccess` (block `/docs/`, `/audit/`, `/project-docs/`, `/tests/`, `/scripts/`, `/data/`, `/node_modules/`, `README.md`, `*.md`, `*.yml`, `*.yaml`, `*.bak`; enforce HTTP->HTTPS 301 on non-localhost hosts; add `HSTS`, `CSP`, `Permissions-Policy`; avoid bare `php_flag` so Bluehost CGI/FPM/LSAPI never throws HTTP 500).
  2. Harden `includes/config.php` (`APP_ENV` isolation, `PFG_ENV_FILE` test override, `ini_set('display_errors', '0')`, remove hardcoded fallback `AUTH_SECRET` & `QR_SIGNING_SECRET` with fail-closed validation in staging/production, fix `APP_URL` Host Header Injection fallback).
  3. Harden `includes/database.php` (block silent SQLite fallback in `production`/`staging`; allow explicit `DB_DRIVER=sqlite` only in local isolated test mode while defaulting to native MySQL/MariaDB).
  4. Harden `includes/qr-service.php` (remove secondary hardcoded `'dev-qr-secret-change-in-production'` fallback).
  5. Add safe dev/test email isolation (`MAIL_DRIVER=file|disabled`) in `includes/mailer.php` writing JSON/HTML captures to `tests/tmp/mail-sink/` so zero real emails can ever fire during dev/test.
  6. Update `.cpanel.yml` & `.gitignore` to prevent deploying internal/audit/doc/test artifacts and purge stale exposed files (`README.md`, `docs/`) upon authorized deployment.
  7. Create CLI-only Bluehost hosting capability verifier (`scripts/verify-bluehost-env.php`) + local setup guide (`project-docs/LOCAL_DEV_SETUP.md`).

## 3. Affected Files
- Modified: `.htaccess`, `.cpanel.yml`, `.gitignore`, `includes/config.php`, `includes/database.php`, `includes/qr-service.php`, `includes/mailer.php`
- Created: `scripts/verify-bluehost-env.php`, `tests/run.php`, `tests/phase1_containment_test.php`, `project-docs/LOCAL_DEV_SETUP.md`, `project-docs/PHASE_01_IMPLEMENTATION_REPORT.md`, `project-docs/PHASE_01_TEST_RESULTS.csv`, `project-docs/PHASE_01_COMPLETION_REPORT.md`
- **No staging/production deployment executed automatically** (all changes verified locally first; Bluehost deployment awaits explicit release approval).

## 4. Database & Security Implications
- Creates two local isolated MariaDB databases on `127.0.0.1:3306`: `pfg27_dev` and `pfg27_test` (zero impact on Bluehost `tstpllmy_pfg27`).
- Fail-closed checks in `includes/config.php` require valid `AUTH_SECRET` & `QR_SIGNING_SECRET` (>= 32 chars, not placeholder strings) whenever `APP_ENV` is `production` or `staging` (Bluehost `.pfg27_env.php` already stores secrets outside `public_html` — we verify existing `.pfg27_env.php` compatibility so live staging never unexpectedly breaks when deployed).

## 5. Acceptance Criteria
1. Native PHP 8.2.12 + MariaDB 10.4.32 run locally and initialize `pfg27_test` cleanly from `database/schema.sql`.
2. `php -l` passes on 100% of `.php` files in the repository (0 syntax errors).
3. Automated Phase 1 test suite (`tests/run.php`) executes and passes against local MariaDB `pfg27_test`:
   - Isolated MySQL PDO connection & 57-table schema verification
   - Fail-closed missing/placeholder secret rejection in `production`/`staging`
   - Silent SQLite fallback blocked in `production`/`staging`
   - Host-header injection blocked when `APP_ENV=production`
   - Local email sink captures test email to disk without hitting Resend or `mail()`
   - `.htaccess` containment rules verified against all sensitive paths
   - Live HTTP check via local PHP server verifying `/`, `/apply`, `/login`, `/api/auth/me`, `/api/registrations` return expected responses (including working `GET /api/registrations` HTTP 200 on local seeded MariaDB!)

## 6. Rollback Strategy
- All Phase 1 application edits are isolated to 7 tracked files (`git checkout -- .htaccess .cpanel.yml .gitignore includes/config.php includes/database.php includes/qr-service.php includes/mailer.php`) and newly added `scripts/` + `tests/` directories. Local `pfg27_dev`/`pfg27_test` databases can be dropped cleanly without affecting staging or production.

