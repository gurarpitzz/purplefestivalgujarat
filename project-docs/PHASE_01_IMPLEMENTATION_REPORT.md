# Phase 1 Implementation Report — Security Containment & Native PHP/MySQL Environment

**Phase:** Phase 1 — Security Containment and Safe Development Environment  
**Date:** 2026-11-26  
**Stack:** Native PHP 8.2.12 + MariaDB 10.4.32 + Apache 2.4.58 (`C:\xampp`, zero Docker)

---

## 1. Summary of Changes Implemented

1. **Removed Mandatory Docker Requirement Across Governance Docs:**
   - Updated `project-docs/PROJECT_BASELINE.md`, `project-docs/CONFIRMED_ARCHITECTURAL_DECISIONS.md` (`CAD-01`, `CAD-11`), `project-docs/PHASE_DEPENDENCY_MAP.md`, `project-docs/REQUIREMENTS_TRACEABILITY_MATRIX.csv`, and created `project-docs/IMPLEMENTATION_MASTER_ROADMAP.md` & `project-docs/LOCAL_DEV_SETUP.md`.
2. **Native Local Development & Isolated Test Environment:**
   - Started local native XAMPP MariaDB 10.4.32 (`127.0.0.1:3306`) and created strictly isolated databases `pfg27_dev` and `pfg27_test` (`utf8mb4_unicode_ci`).
   - Created zero-dependency native PHP CLI test runner (`tests/run.php`), `.htaccess`-mirroring test router (`tests/router.php`), and 11-test Phase 1 verification suite (`tests/phase1_containment_test.php`).
   - Created CLI-only Bluehost environment verifier (`scripts/verify-bluehost-env.php`) checking PHP version, 8 required extensions, `display_errors`, secret length (redacted), PDO MySQL connectivity/timezone, private parent directory writability, and optional outbound HTTPS check to `api.resend.com`.
3. **Security Containment & Fail-Closed Hardening (Prepared & Verified Locally; Not Yet Deployed to Live Staging):**
   - **`.htaccess`:** Blocked `/docs/`, `/audit/`, `/project-docs/`, `/tests/`, `/scripts/`, `/data/`, `/node_modules/`, `README.md`, `*.md`, `*.yml`, `*.yaml`, `*.sql`, `*.sqlite*`, `.env*`, `.user.ini`, backups; added non-localhost HTTP -> HTTPS 301 redirect; added `HSTS`, `CSP`, `Permissions-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`; kept zero bare `php_flag` directives for Bluehost CGI/FPM/LSAPI safety.
   - **`.user.ini`:** Added Bluehost-compatible per-directory PHP hardening (`display_errors = Off`, `log_errors = On`, `expose_php = Off`, `session.cookie_httponly = 1`, `session.use_strict_mode = 1`).
   - **`includes/config.php`:** Enforced `ini_set('display_errors', '0')`, `PFG_ENV_FILE` isolated override, `$GLOBALS['pfgLoadedEnv']` (fixing a real closure-scope inclusion bug caught during test execution!), `APP_ENV` / `IS_LOCAL_DEV_ENV`, fail-closed `pfgValidateSecret()` rejecting missing/short/placeholder secrets, disabled `HTTP_HOST` fallback in staging/production (blocking Host Header Injection), and added `MAIL_DRIVER` (`file` default in dev/test, `resend` in staging/production).
   - **`includes/database.php`:** Disabled silent SQLite fallback in staging/production (throws `RuntimeException` if MySQL credentials are missing); added `getDb(bool $reset = false)` for clean test resets.
   - **`includes/qr-service.php`:** Removed hardcoded `'dev-qr-secret-change-in-production'` fallback.
   - **`includes/mailer.php`:** Added `MAIL_DRIVER=file` local JSON sink (`tests/tmp/mail-sink/`, scrubbing `rawToken`/`resetUrl` metadata) and `MAIL_DRIVER=disabled` guard so neither Resend nor PHP `mail()` can ever fire during dev/testing.
   - **`.cpanel.yml` & `.gitignore`:** Updated `.cpanel.yml` to deploy `.user.ini` and remove stale `/docs`, `/audit`, `/project-docs`, `/tests`, `/README.md` from `$DEPLOYPATH`; added `tests/tmp/` and `.env.*.php` to `.gitignore`.

