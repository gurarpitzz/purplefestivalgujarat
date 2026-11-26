# Bluehost Staging & Production Deployment Checklist (§40.7–§40.9)

**Target Hosting:** Bluehost Shared / cPanel Apache 2.4 + PHP 8.2 + MySQL 8.0 / MariaDB 10.4 + Resend HTTPS API + cPanel Cron Jobs  
**Release Packager:** `php scripts/build-release-package.php`  
**Environment Verifier:** `php scripts/verify-bluehost-env.php`  
**Migration Runner:** `php database/migrate.php`  
**Current Live Deployment Status:** **`NOT DEPLOYED` (`CONFIGURATION BLOCKED` pending Project Owner sign-off on `D-21`, `D-22`, `D-23`, and Bluehost/Resend credentials)**

---

## 1. Pre-Deployment Gate (Must Be Completed Before Touching Bluehost)

- [ ] **1.1 Confirm Unresolved Owner Decisions (`FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md`):**
  - [ ] `D-21`: Inspect existing Bluehost staging database to confirm whether any real participant rows exist (preserve & use verified-email claim flow) or if staging contains QA data only.
  - [ ] `D-22`: Confirm authoritative production domain (`www.purplefestivalgujarat.com` vs. `purplefestivalvadodara.com`).
  - [ ] `D-23`: Confirm written partner logo consents (`partners.consent_verified = 1`), confirmed vs. proposed venue labels, and official Secretariat contact details.
- [ ] **1.2 Local Verification & Clean Package Generation:**
  - [ ] Confirm all local PHP/MariaDB (`tests/run.php`), Apache (`tests/apache_verification.php`), and Playwright E2E (`tests/phase17_browser_e2e.mjs`) suites pass with `0` failures.
  - [ ] Run the clean release packager locally:
    ```bash
    php scripts/build-release-package.php --out=tests/tmp/release-package --json
    ```
  - [ ] Confirm `RELEASE_MANIFEST.json` (written in `tests/tmp/RELEASE_MANIFEST.json`, outside the package root) reports:
    - `"status": "PASSED"`
    - `"excludedLeakCount": 0`
    - `"phpSyntaxLintFailedCount": 0`
    - `"manifestOutsideWebroot": true`
  - [ ] Archive the contents of `tests/tmp/release-package/` into `pfg27-release-<commit>.zip` for upload (never include `RELEASE_MANIFEST.json`, `.git/`, `project-docs/`, `tests/`, `docs/`, `audit/`, or root scratch files inside `public_html`).

---

## 2. Stage 1 — Bluehost Staging Environment Provisioning & Dry-Run (§40.7)

### 2.1 Private Directories & Secret Configuration Outside `public_html`
- [ ] Log in to Bluehost cPanel SSH / Terminal (or cPanel File Manager above `public_html`).
- [ ] Create private operational directories outside `public_html`:
  ```bash
  mkdir -p ~/pfg27_backups ~/pfg27_logs ~/pfg27_releases
  chmod 700 ~/pfg27_backups ~/pfg27_logs ~/pfg27_releases
  ```
- [ ] Create `/home1/<cpanel_user>/.pfg27_env.php` outside `public_html` with strict `chmod 600` permissions:
  ```php
  <?php
  return [
      'APP_ENV'               => 'staging', // set to 'production' on production cutover
      'APP_URL'               => 'https://staging.<confirmed-domain>',
      'DB_DRIVER'             => 'mysql',
      'DB_HOST'               => 'localhost',
      'DB_PORT'               => '3306',
      'DB_NAME'               => '<cpanel_user>_pfg27_staging',
      'DB_USER'               => '<cpanel_user>_pfg27_app',
      'DB_PASS'               => '<strong-random-db-password>',
      'AUTH_SECRET'           => '<64-hex-char-random-secret-1>',
      'QR_SIGNING_SECRET'     => '<64-hex-char-random-secret-2>',
      'MFA_ENCRYPTION_KEY'    => '<64-hex-char-random-secret-3>',
      'DATA_ENCRYPTION_KEY'   => '<64-hex-char-random-secret-4>',
      'MAIL_DRIVER'           => 'disabled', // keep 'disabled' during initial migration & smoke test!
      'RESEND_API_KEY'        => '',
      'RESEND_FROM_EMAIL'     => 'noreply@<confirmed-domain>',
      'RESEND_FROM_NAME'      => 'Purple Festival Gujarat 2027',
      'RESEND_WEBHOOK_SECRET' => '',
  ];
  ```
  *(Generate each 64-hex-character secret via `php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"` — never reuse local test secrets!)*

### 2.2 Mandatory Pre-Migration Database Backup (`D-21`)
- [ ] If the target staging/production database already contains tables or data, capture a full verified `mysqldump` snapshot **before** deploying code or running migrations:
  ```bash
  mysqldump --host=localhost --user=<db_user> -p \
    --single-transaction --routines --triggers --hex-blob \
    --default-character-set=utf8mb4 \
    <db_name> --result-file=$HOME/pfg27_backups/pre_deploy_$(date +%Y%m%d_%H%M%S).sql
  chmod 600 $HOME/pfg27_backups/pre_deploy_*.sql
  sha256sum $HOME/pfg27_backups/pre_deploy_*.sql
  ```

### 2.3 Upload Clean Release Package & Verify Hosting Environment
- [ ] Extract `pfg27-release-<commit>.zip` into the staging document root (or deploy via cPanel Git with `.cpanel.yml` after verifying `$DEPLOYPATH`).
- [ ] Verify PHP 8.2 is active for the domain in cPanel **MultiPHP Manager**.
- [ ] Run the CLI environment verifier:
  ```bash
  /usr/local/bin/ea-php82 scripts/verify-bluehost-env.php
  ```
- [ ] Confirm all required checks report `[PASS]` (PHP `>= 8.1`, all 8 extensions loaded, `display_errors=0`, secrets loaded & redacted, PDO MySQL connected, `+05:30` IST timezone, TLS connectivity to `api.resend.com`).

### 2.4 Execute Versioned Migrations (`000`–`012`) & Reference Seed
- [ ] Check current migration status:
  ```bash
  "$PFG_PHP" database/migrate.php --status
  ```
- [ ] Apply migrations `000` through `012` and idempotent reference seed data:
  ```bash
  "$PFG_PHP" database/migrate.php --up --seed
  "$PFG_PHP" database/migrate.php --verify
  ```
- [ ] Confirm output reports:
  `Tables: 70 | Foreign Keys: 79 | Applied: 13/13 | Pending: 0` and `VERIFY PASSED`.
- [ ] Re-run `"$PFG_PHP" scripts/verify-bluehost-env.php` and confirm `Versioned Schema Migrations Applied` is `[PASS]`.

### 2.5 Bootstrap Initial Superadmin & Verify Revised Auth Policy (`CAD-16`)
- [ ] Provision or verify the initial `SUPERADMIN` account via CLI (username + password authentication; TOTP MFA is discontinued under `CAD-16` and `/api/auth/mfa` returns `410 Gone`):
  ```bash
  SUPERADMIN_BOOTSTRAP_USERNAME="superadmin" \
  SUPERADMIN_BOOTSTRAP_PASSWORD="<strong-12+-char-password>" \
  "$PFG_PHP" database/bootstrap-superadmin.php
  ```
- [ ] Navigate to `https://purplefestgujarat.ajeetgraphics.com/staff/login`, sign in as `SUPERADMIN` with username + password, confirm direct access to `/admin`, and verify universal 5-attempt / 3-hour automatic lockout on an isolated test account.

---

## 3. Stage 2 — Staging HTTP Containment & Functional Smoke Tests (§40.8)

Execute all checks below against the staging HTTPS URL while `MAIL_DRIVER` is still `'disabled'` (or `'file'`):

- [ ] **3.1 Apache `.htaccess` Containment Verification (Must Return `HTTP 403 Forbidden`):**
  - `curl -I https://<domain>/includes/config.php` -> `403`
  - `curl -I https://<domain>/database/migrate.php` -> `403`
  - `curl -I https://<domain>/scripts/process-notification-queue.php` -> `403`
  - `curl -I https://<domain>/scripts/verify-bluehost-env.php` -> `403`
  - `curl -I https://<domain>/.user.ini` -> `403`
- [ ] **3.2 Retired QR Endpoints Verification (Must Return `HTTP 410 Gone`):**
  - `curl -i https://<domain>/api/qr/pass` -> `410 Gone`
  - `curl -i -X POST https://<domain>/api/qr/verify` -> `410 Gone`
  - `curl -i -X POST https://<domain>/api/checkins/confirm` -> `410 Gone`
- [ ] **3.3 Security Headers & TLS Verification:**
  - Confirm `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, and `Content-Security-Policy` headers are present on `GET https://<domain>/`.
  - Confirm `http://<domain>/` redirects via `301 Moved Permanently` to `https://<domain>/`.
- [ ] **3.4 Public Website & WCAG 2.2 AA Toolbar Verification:**
  - Verify `/`, `/programme`, `/accessibility`, `/legal/privacy`, `/legal/terms`, and `/legal/accessibility-statement` render with `0` console errors.
  - Confirm only partners with `consent_verified = 1 AND status = 'PUBLISHED'` appear on `/`.
  - Confirm `DRAFT` and `CANCELLED` sessions do not appear on `/programme`, and non-confirmed venues display `"Proposed / Subject to Confirmation"`.
  - Toggle High Contrast, Yellow-on-Black, Text Size, Dyslexia Font, and Reduced Motion in the Accessibility Toolbar and reload to verify zero FOUC.
- [ ] **3.5 End-to-End Portal Smoke Test (Using Organizer Test Accounts Only):**
  - Create a test participant account on `/register`, verify email, log in on `/login`, submit an `ATTENDEE` registration on `/apply/attendee`, confirm `PFG-2027-NNNNNN` ID generation and zero QR pass in `/dashboard` and `/dashboard/pass-print`.
  - Log in to `/rgst` as an `RGST` operator, search the test registration, mark Day 1 attendance checkbox, verify persistence on reload, and test 15-minute undo with reason.

---

## 4. Stage 3 — Resend Email, Svix Webhook & cPanel Cron Activation (§40.9)

Perform this stage **only** after DNS for the confirmed production domain (`D-22`) is verified in Resend:

- [ ] **4.1 Resend Domain DNS & Webhook Setup:**
  - Verify SPF, DKIM, and DMARC (`p=quarantine` or `p=reject`) records in DNS for `<confirmed-domain>`.
  - Register webhook endpoint `https://<confirmed-domain>/api/webhooks/resend` in Resend subscribed to `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.complained`, and `email.failed`.
  - Update `/home1/<cpanel_user>/.pfg27_env.php` with `RESEND_API_KEY`, `RESEND_WEBHOOK_SECRET`, and `'MAIL_DRIVER' => 'resend'`.
- [ ] **4.2 Pilot Delivery Test (Controlled Organizer Inboxes Only):**
  - Trigger a password reset or test signup for an organizer-owned inbox.
  - Run one manual CLI worker batch from SSH:
    ```bash
    /usr/local/bin/ea-php82 scripts/process-notification-queue.php --batch=5 --max-seconds=30 --json
    ```
  - Verify the message transitions `QUEUED -> PROCESSING -> PROVIDER_ACCEPTED` (and `ephemeral_secret_enc` is wiped to `NULL`), then transitions to `DELIVERED` upon receiving the signed Svix webhook at `/api/webhooks/resend`.
- [ ] **4.3 Enable Bluehost cPanel Cron Job:**
  - In **cPanel -> Advanced -> Cron Jobs**, configure the recurring worker (`*/2 * * * *`):
    ```bash
    /usr/bin/flock -n /home1/<cpanel_user>/pfg27_logs/notification_worker.lock /usr/local/bin/ea-php82 /home1/<cpanel_user>/public_html/scripts/process-notification-queue.php --batch=25 --max-seconds=45 --lock-ttl=300 --drain --json >> /home1/<cpanel_user>/pfg27_logs/notification_worker.log 2>&1
    ```
- [ ] **4.4 Post-Cutover Monitoring (First 60 Minutes):**
  - Inspect `/admin` -> **Communications Center** (Queue status & delivery logs) and **Audit Logs** (`CSRF_REJECTED`, `LOGIN_ACCOUNT_LOCKED`, `SENSITIVE_DATA_READ`).
  - Confirm `~/pfg27_logs/notification_worker.log` records clean JSON batch runs with `0` uncaught errors.
