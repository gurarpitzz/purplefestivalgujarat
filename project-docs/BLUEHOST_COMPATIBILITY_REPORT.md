# Bluehost & Native Apache Compatibility Report — Purple Festival Gujarat 2027

**Date:** 2026-11-26  
**Local Runtime Verified:** `Apache/2.4.58 (Win64) PHP/8.2.12`, `MariaDB 10.4.32`, OpenSSL AES-256-GCM (`openssl`), PDO MySQL (`pdo_mysql`), `mbstring`, `curl`, `json`, `fileinfo`, `session`  
**Local Verification Status:** **`VERIFIED WORKING LOCALLY`** (`12/12` migrations applied, `70` tables, `79` non-cascading FKs, `scripts/verify-bluehost-env.php` `PASSED`, `scripts/build-release-package.php` `PASSED` with `0` syntax errors and `0` excluded file leaks)  
**Remote Bluehost Deployment Status:** **`CONFIGURATION BLOCKED` / NOT DEPLOYED** (strictly awaiting Project Owner credentials, `D-22` domain confirmation, and explicit deployment sign-off).

> [!IMPORTANT]
> **Honest Boundary Statement (§49–§50):** All compatibility tests below were executed on the local native Windows XAMPP stack (`PHP 8.2.12` + `MariaDB 10.4.32` + `Apache 2.4.58`). **Zero operations have been executed on a live Bluehost cPanel server.** Live Bluehost environment verification (`php scripts/verify-bluehost-env.php`), live AutoSSL/TLS & HSTS verification, live cPanel Cron execution, and live Resend email delivery remain pending until authorized staging/production deployment.

---

## 1. Local Native XAMPP Apache 2.4.58 & PHP 8.2.12 Verification Summary

| Verification Suite | Runtime / Tool | Verified Scope | Result |
|---|---|---|---|
| **CLI Environment Verifier** | `php scripts/verify-bluehost-env.php --skip-network` | PHP `8.2.12` (`>= 8.1`), all 8 required extensions (`pdo`, `pdo_mysql`, `mbstring`, `curl`, `openssl`, `json`, `fileinfo`, `session`), `display_errors=0`, fail-closed `AUTH_SECRET` & `QR_SIGNING_SECRET`, MariaDB `10.4.32` connection, `70` tables present, `12/12` migrations applied (`0` pending, `0` checksum mismatches), active `gujarat-2027` edition, `+05:30` IST session timezone, writable parent directory outside webroot | **`PASSED` (0 failures)** |
| **Clean Bluehost Release Packager** | `php scripts/build-release-package.php --json` | Packages only production runtime files into target build directory, runs `php -l` syntax check across all included PHP files, writes `RELEASE_MANIFEST.json` outside webroot, and audits `0` leaked excluded files | **`PASSED` (`0` syntax errors, `0` leaks)** |
| **Apache `.htaccess` Routing & Containment** | `C:\xampp\apache\bin\httpd.exe` (`Apache 2.4.58`) | Public extensionless routes (`/`, `/programme`, `/accessibility`, `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password`, `/staff/login`, `/apply`), auth redirects (`/apply/attendee`, `/dashboard`, `/rgst`, `/admin`), API routes (`/api/auth/me`, `/api/registrations`, `/api/attendance`, `/api/account/dashboard`, `/api/admin/*`), `410 Gone` retired QR routes (`/api/qr/pass`, `/api/qr/verify`, `/api/checkins/confirm`), `403 Forbidden` containment on `/database/*`, `/includes/*`, `/scripts/*`, `/project-docs/*`, `/tests/*`, `/.user.ini`, `/.cpanel.yml`, `/README.md`, and 5 mandatory security headers | **`PASSED` (`100%` containment)** |

---

## 2. Bluehost Shared / cPanel Hosting Architecture Compatibility Matrix

| Hosting Dimension | Implementation Design (Phases 1–18) | Local XAMPP Verification | Live Bluehost cPanel Requirement |
|---|---|---|---|
| **Zero Docker / Zero Daemons (`CAD-01`, `CAD-11`)** | 100% request-driven PHP + PDO MySQL + CLI cron script (`scripts/process-notification-queue.php`). Persistent rate limiting uses InnoDB table `security_rate_limits`. | `VERIFIED WORKING LOCALLY` | Standard Bluehost Shared / cPanel hosting plan with PHP 8.1/8.2 and MySQL 8.0 / MariaDB 10.4+. |
| **PHP SAPI & Handler Compatibility (EA-PHP / FPM / LSAPI / CGI)** | `.htaccess` contains **zero** bare `php_flag` or `php_value` directives (which cause HTTP `500` on Bluehost suPHP/FPM/CGI). PHP INI hardening resides in `.user.ini` and `@ini_set()` in `includes/config.php`. | `VERIFIED WORKING LOCALLY` | Select **PHP 8.2** in Bluehost cPanel *MultiPHP Manager*. |
| **MySQL 8.0 & MariaDB 10.4+ SQL Portability (`CAD-14`)** | All 12 migrations (`000`–`011`) use `InnoDB` `utf8mb4_unicode_ci`, `information_schema` existence checks (`pfgDbEnsureColumn`, `pfgDbEnsureIndex`, `pfgDbEnsureForeignKey`), `GET_LOCK` advisory locking, and atomic lease UPDATE claiming (zero MySQL-8-only `SKIP LOCKED`, zero MariaDB-only `ADD COLUMN IF NOT EXISTS`). | `VERIFIED WORKING LOCALLY` (`MariaDB 10.4.32`, `70` tables, `79` FKs) | Run `php database/migrate.php --up --seed` and `php database/migrate.php --verify` against Bluehost MySQL DB. |
| **Secrets Isolation Outside `public_html`** | `includes/config.php` loads `/home1/<cpanel_user>/.pfg27_env.php` (`dirname(__DIR__, 2) . '/.pfg27_env.php'`) outside `public_html` and fails closed if `AUTH_SECRET` or `QR_SIGNING_SECRET` is missing or weak. | `VERIFIED WORKING LOCALLY` | Create `/home1/<cpanel_user>/.pfg27_env.php` with `chmod 600` containing `DB_*`, `AUTH_SECRET`, `QR_SIGNING_SECRET`, `MFA_ENCRYPTION_KEY`, `DATA_ENCRYPTION_KEY`, `RESEND_API_KEY`, and `RESEND_WEBHOOK_SECRET`. |
| **Clean Deployment Payload (`scripts/build-release-package.php` & `.cpanel.yml`)** | Release packager copies only production runtime files and strips `.git/`, `docs/`, `audit/`, `project-docs/`, `tests/`, `*.md`, `*.docx`, `*.pdf`, `*.pptx`, `*.xlsx`, scratch PNGs, and retired `registration-wizard.js` / `html5-qrcode.min.js`. `.cpanel.yml` enforces `$DEPLOYPATH` guard. | `VERIFIED WORKING LOCALLY` | Deploy clean package contents to Bluehost staging/production `public_html` root. |
| **Asynchronous Notification Queue Cron (`CAD-15`)** | `scripts/process-notification-queue.php` is CLI-only (`403` over HTTP), supports `--batch=25 --max-seconds=45 --lock-ttl=300 --drain --json`, and recovers crashed leases automatically. | `VERIFIED WORKING LOCALLY` (`1,000` msgs across 4 workers, `0` duplicates) | Add cPanel Cron Job (`*/2 * * * *`) invoking `/usr/local/bin/ea-php82 .../scripts/process-notification-queue.php` logging outside `public_html`. |
| **HTTPS Redirect, HSTS & Resend Webhooks** | `.htaccess` enforces 301 HTTP->HTTPS redirect and `Strict-Transport-Security` on non-localhost hosts; `api/webhooks/resend.php` verifies Svix HMAC-SHA256 signatures. | `PROVIDER SIMULATION VERIFIED` | Enable Bluehost AutoSSL on final domain (`D-22`) and register `https://<domain>/api/webhooks/resend` in Resend dashboard. |

---

## 3. Remote-Only Verification Gates (To Execute During Authorized Bluehost Cutover)

1. Upload `.pfg27_env.php` (`chmod 600`) outside `public_html` and deploy the clean release package produced by `php scripts/build-release-package.php`.
2. Execute `php scripts/verify-bluehost-env.php` on the Bluehost cPanel SSH/terminal shell and confirm **`RESULT: PASSED`** (including live TLS check to `https://api.resend.com/`).
3. Take a pre-migration `mysqldump` snapshot outside `public_html`, run `php database/migrate.php --up --seed`, and verify `php database/migrate.php --verify` reports `Tables: 70 | Foreign Keys: 79 | Applied: 12/12 | Pending: 0`.
4. Verify live Apache `.htaccess` containment (`HTTP 403` on `/includes/config.php`, `/database/migrate.php`, `/scripts/process-notification-queue.php`, `/.user.ini`) and `HTTP 410 Gone` on `/api/qr/pass` over HTTPS.
5. Verify live Resend pilot delivery (`PROVIDER_ACCEPTED` -> Svix webhook `DELIVERED`) to organizer-designated test inboxes before enabling public traffic.
