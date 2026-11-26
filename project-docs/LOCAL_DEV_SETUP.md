# Local Native PHP / MySQL Development & Bluehost Verification Guide

**Stack:** Native PHP 8.2+ · MariaDB / MySQL (InnoDB `utf8mb4`) · Apache 2.4 · Zero Docker  
**Verified Local Workstation Runtime:** `C:\xampp` (`PHP 8.2.12`, `MariaDB 10.4.32`, `Apache 2.4.58`)

---

## 1. Environment Isolation Matrix

| Environment | `APP_ENV` | Database | Email Driver (`MAIL_DRIVER`) | Secrets Location |
|---|---|---|---|---|
| **Local Development** | `development` | Local MariaDB `pfg27_dev` (`127.0.0.1:3306`) | `file` (`tests/tmp/mail-sink/`) | `.env.local.php` (gitignored) |
| **Automated Testing** | `testing` | Local MariaDB `pfg27_test` (`127.0.0.1:3306`) | `file` (`tests/tmp/mail-sink/`) | `tests/tmp/test_env.php` (ephemeral, gitignored) |
| **Bluehost Staging** | `staging` | Bluehost MySQL (`tstpllmy_pfg27` or dedicated staging DB) | `disabled` or `resend` (test domain only) | `/home1/tstpllmy/.pfg27_env.php` (outside `public_html`) |
| **Bluehost Production** | `production` | Bluehost Production MySQL | `resend` (verified production domain only) | Outside `public_html` (`.pfg27_env.php`) |

> **Fail-Closed Guarantees:**
> - Missing or placeholder `AUTH_SECRET` / `QR_SIGNING_SECRET` immediately throws a `RuntimeException` instead of signing with public defaults.
> - Missing `DB_NAME`/`DB_USER` in `staging`/`production` throws a `RuntimeException` (silent SQLite fallback is permanently disabled outside `development`/`testing`).
> - Missing `APP_URL` in `staging`/`production` throws a `RuntimeException` (prevents Host Header Injection).
> - `MAIL_DRIVER=file` captures all emails locally to JSON envelopes in `tests/tmp/mail-sink/` and scrubs `rawToken`/`resetUrl` metadata — zero outbound emails can ever reach real participants during development or testing.

---

## 2. Running Local Native PHP / MySQL Development (Windows XAMPP)

### Start Local MariaDB
```powershell
Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini" -WindowStyle Hidden
```

### Create Isolated Local Databases (One-Time)
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS pfg27_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS pfg27_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Run the Automated Native PHP Test Suite
```powershell
C:\xampp\php\php.exe tests/run.php
```

### Start Local PHP Dev Server (Mirrors `.htaccess` Routing)
1. Create a local gitignored `.env.local.php` with strong random secrets (`DB_NAME => 'pfg27_dev'`, `MAIL_DRIVER => 'file'`, `APP_ENV => 'development'`).
2. Start server:
```powershell
$env:PFG_ENV_FILE = "d:\purplefestgujarat\.env.local.php"
C:\xampp\php\php.exe -S 127.0.0.1:8088 tests/router.php
```

---

## 3. Verifying Bluehost Hosting Capabilities (cPanel SSH / Terminal)

Before deploying to Bluehost staging or production, run the read-only CLI verifier on Bluehost (never via web — web requests return HTTP 403):
```bash
php /home1/tstpllmy/public_html/purplefestivalgujarat/scripts/verify-bluehost-env.php
```
*(Or keep `scripts/` outside `public_html` and run `php ~/scripts/verify-bluehost-env.php`.)*

### Optional Staging Access Restriction (cPanel Directory Privacy / HTTP Basic Auth)
While Phases 2–17 remediation is underway, restrict the staging subdomain (`purplefestivalgujarat.ajeetgraphics.com`) via **cPanel → Directory Privacy** (Password Protect `/home1/tstpllmy/public_html/purplefestivalgujarat`) so search engines and real participants cannot submit sensitive data on staging before Phase 2 security fixes are deployed.

