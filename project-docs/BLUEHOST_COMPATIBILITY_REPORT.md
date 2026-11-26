# Bluehost & Native Apache Compatibility Report — Purple Festival Gujarat 2027

**Date:** 2026-11-26  
**Local Runtime Tested:** `Apache/2.4.58 (Win64) PHP/8.2.12`, `MariaDB 10.4.32`, OpenSSL AES-256-GCM (`php_openssl`), PDO MySQL (`pdo_mysql`)  
**Remote Bluehost Deployment Status:** **NOT DEPLOYED** (awaiting separate explicit deployment authorization).

---

## 1. Real Native XAMPP Apache 2.4.58 Verification Results (`tests/apache_verification.php`)

Executed against real `C:\xampp\apache\bin\httpd.exe` on `127.0.0.1:8089` with `DocumentRoot d:/purplefestgujarat`, `AllowOverride All`, `mod_rewrite`, `mod_headers`, `mod_authz_core`, and `php8apache2_4.dll` evaluating the repository's actual `.htaccess` file:

| Check Group | Verified Checks | Result |
|---|---|---|
| Public Extensionless Rewrites | `GET /`, `/apply`, `/login`, `/programme`, `/accessibility` | **5/5 PASS (HTTP 200)** |
| Static Assets & MFA QR Library | `GET /assets/css/app.css`, `/assets/js/qrcode-gen.js` | **2/2 PASS (HTTP 200)** |
| API Extensionless Rewrites + MariaDB | `GET /api/auth/me` (200), `/api/registrations` (200, 8 categories), `/api/admin/overview` (401), `/api/admin/cms` (401) | **4/4 PASS** |
| Protected Route Auth Redirects | `GET /admin` (302), `/rgst` (302), `/dashboard` (302 — verifies `FND-13` fix under Apache!) | **3/3 PASS** |
| `.htaccess` 403 Directory & File Containment | `/README.md`, `/docs/WEBSITE_AUDIT.md`, `/audit/MASTER_CODEBASE_AUDIT.md`, `/project-docs/PROJECT_BASELINE.md`, `/database/schema.sql`, `/includes/config.php`, `/scripts/verify-bluehost-env.php`, `/tests/run.php`, `/.user.ini`, `/.cpanel.yml` | **10/10 PASS (HTTP 403)** |
| Error Handling | `GET /nonexistent-pfg27-route` -> clean HTTP 404 (no PHP stack trace) | **1/1 PASS** |
| Real Apache `.htaccess` Security Headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` | **1/1 PASS (All 5 present)** |
| **TOTAL APACHE CHECKS** | `A-01` through `A-26` | **26 / 26 PASSED (0 FAILED)** |

---

## 2. Bluehost Shared / cPanel Hosting Compatibility Audit

| Requirement | Phase 2 Implementation | Bluehost Compatibility |
|---|---|---|
| **Zero Docker / Zero Redis / Zero Daemons** | Persistent rate limiting uses native MySQL/MariaDB table `security_rate_limits` (`001_phase2_security_controls.sql`) | **100% Compatible** |
| **PHP Handler Safety (FPM / LSAPI / CGI / mod_php)** | Zero bare `php_flag`/`php_value` in `.htaccess`; PHP hardening in `.user.ini` + `@ini_set` in `includes/config.php` | **100% Compatible** |
| **Cryptography (`openssl_encrypt` AES-256-GCM)** | Uses standard PHP `openssl` extension (built into Bluehost PHP 8.x) | **100% Compatible** |
| **Dedicated Secrets Outside `public_html`** | `/home1/tstpllmy/.pfg27_env.php` supports `AUTH_SECRET`, `QR_SIGNING_SECRET`, and optional dedicated `MFA_ENCRYPTION_KEY` & `DATA_ENCRYPTION_KEY` (safely derives domain-separated keys via HMAC if dedicated env keys are not yet added) | **100% Compatible** (Provision dedicated `MFA_ENCRYPTION_KEY` & `DATA_ENCRYPTION_KEY` in `.pfg27_env.php` before staging/prod deploy!) |
| **cPanel Git Deployment Hygiene (`.cpanel.yml`)** | Hardened with `test -n "$DEPLOYPATH" && test "$DEPLOYPATH" = "/home1/tstpllmy/public_html/purplefestivalgujarat/"` before `rm -rf` and `cp -R` | **100% Compatible** |
| **HTTPS Redirect & HSTS** | `.htaccess` enforces 301 HTTPS redirect and HSTS outside localhost; requires Bluehost AutoSSL / TLS certificate on staging/production domain | **Verified in `.htaccess` locally; live TLS verification pending authorized Bluehost deploy** |

## 3. Remaining Remote-Only Checks (Cannot Be Claimed Until Authorized Bluehost Deploy)
- Live Bluehost staging HTTPS certificate & HSTS header verification on `purplefestivalgujarat.ajeetgraphics.com` / final production domain (`D-22`).
- Live execution of `php scripts/verify-bluehost-env.php` on the Bluehost cPanel server shell.
- Provisioning dedicated `MFA_ENCRYPTION_KEY` and `DATA_ENCRYPTION_KEY` values in `/home1/tstpllmy/.pfg27_env.php` outside `public_html`.
- Applying `database/migrations/001_phase2_security_controls.sql` (or verifying auto-creation) against the isolated Bluehost staging database.

