# Bluehost Restricted Staging & Production Cutover Guide — Purple Festival Gujarat 2027

**Scope:** Account-agnostic Bluehost cPanel/SSH runtime discovery for the **`purplefestgujarat.ajeetgraphics.com`** development, staging, and functional testing subdomain (NOT the final public production domain), the **14-Step Restricted Staging Deployment Sequence (§15)**, the **12-Item Resend Activation Checklist (§16)**, Legacy Unprofiled Admin Migration (§5.2), Corrected `.cpanel.yml` + `scripts/deploy-staging.sh`, and the **Ongoing `main` -> Staging Deployment Workflow**.  
**Current Environment Role:** **`https://purplefestgujarat.ajeetgraphics.com`** = protected Bluehost development/staging/functional testing environment (`REGISTRATION_PUBLIC_OPEN = 0` + Apache Basic Auth + `X-Robots-Tag: noindex, nofollow` + isolated staging database). Final public production domain deployment remains **deferred** until Project Owner sign-off.

> [!IMPORTANT]
> **Zero Credential Collection in Chat or Git (§16):** Never paste live Bluehost cPanel passwords, SSH keys, database passwords, `RESEND_API_KEY`, `RESEND_WEBHOOK_SECRET`, or encryption keys into AI chat transcripts, issues, or Git commits. All secrets are provisioned directly by the Technical Administrator inside `.pfg27_env.php` (`chmod 600`) outside the web document root on the Bluehost server.

---

## 1. Account-Agnostic Bluehost cPanel / SSH Discovery Procedure (§14)

Bluehost shared and cloud cPanel servers vary across provisioning generations (`/home/<user>`, `/home1/<user>`..`/home4/<user>`, EasyApache `/usr/local/bin/ea-php82` vs `/opt/cpanel/ea-php82/root/usr/bin/php`, primary `public_html` vs addon/subdomain document roots, and MySQL 8.0 vs MariaDB 10.4–10.6). **Never assume or hardcode server paths.**

Before touching any files or databases on Bluehost, run the following read-only discovery commands in the Bluehost SSH terminal (or cPanel *Terminal*):

```bash
# 1. Discover exact account home directory ($HOME) and cPanel username ($USER)
export PFG_HOME="$(cd ~ && pwd)"
echo "cPanel User: $(whoami) | Home Directory: ${PFG_HOME}"

# 2. Discover exact PHP 8.2+ CLI binary (never assume /usr/local/bin/ea-php82 without checking)
for candidate in \
  "$(command -v ea-php82 2>/dev/null)" \
  "/usr/local/bin/ea-php82" \
  "/opt/cpanel/ea-php82/root/usr/bin/php" \
  "$(command -v ea-php83 2>/dev/null)" \
  "$(command -v php 2>/dev/null)"; do
  if [ -n "$candidate" ] && [ -x "$candidate" ]; then
    echo "Candidate: $candidate -> $($candidate -v | head -n 1)"
  fi
done
# Export the verified PHP 8.2+ CLI binary for the session:
export PFG_PHP="/usr/local/bin/ea-php82" # Replace with the verified 8.2+ binary discovered above
"$PFG_PHP" -m | grep -E '^(pdo|pdo_mysql|mbstring|curl|openssl|json|fileinfo|session)$'

# 3. Discover actual document root for the target staging/production domain or subdomain
#    (In cPanel -> Domains, check the "Document Root" column, or inspect via uapi if available)
uapi DomainInfo domains_data --output=json 2>/dev/null | "$PFG_PHP" -r '
  $d = json_decode(stream_get_contents(STDIN), true);
  foreach (($d["result"]["data"] ?? []) as $type => $list) {
    if (!is_array($list)) $list = [$list];
    foreach ($list as $item) {
      if (isset($item["domain"], $item["documentroot"])) {
        echo $item["domain"] . " => " . $item["documentroot"] . PHP_EOL;
      }
    }
  }
'
# Export the verified target document root (e.g., $PFG_HOME/staging.purplefestivalgujarat.com or $PFG_HOME/public_html):
export PFG_DOCROOT="${PFG_HOME}/<discovered-docroot-relative-path>"

# 4. Discover MySQL / MariaDB server version, character set defaults, and InnoDB availability
mysql --version
mysql -u "<discovered_db_user>" -p -e "SELECT VERSION() AS engine_version, @@character_set_server, @@collation_server, @@time_zone;"

# 5. Discover flock availability and cPanel Cron frequency limits
command -v flock && flock --version
crontab -l 2>/dev/null || echo "No existing cron entries"
```

### Dynamic `.pfg27_env.php` Lookup Order (`includes/config.php`)
`includes/config.php` automatically resolves `.pfg27_env.php` without hardcoded `/home1/tstpllmy` paths in the following deterministic order:
1. `getenv('PFG_ENV_FILE')` or `$_SERVER['PFG_ENV_FILE']` (explicit path override via `.htaccess` `SetEnv PFG_ENV_FILE` or CLI export)
2. `dirname(__DIR__, 2) . '/.pfg27_env.php'` (immediate parent directory outside `$PFG_DOCROOT`, e.g., `$PFG_HOME/.pfg27_env.php` when deployed in `$PFG_HOME/public_html` or `$PFG_HOME/staging.example.com`)
3. `dirname(__DIR__, 3) . '/.pfg27_env.php'` (account home `$PFG_HOME/.pfg27_env.php` when deployed in a nested subdirectory such as `$PFG_HOME/public_html/staging`)
4. `($_SERVER['HOME'] ?? getenv('HOME')) . '/.pfg27_env.php'`
5. `dirname(__DIR__) . '/.env.php'` (local development only)

---

## 2. Exact 14-Step Restricted Staging Deployment Sequence (§15)

Execute these 14 steps in strict sequential order. **Do not skip any step or enable public registration during restricted staging cutover.**

### Step 1 — Access Verification & Account-Agnostic Runtime Discovery
- [ ] Log in to Bluehost SSH / cPanel Terminal and execute the Section 1 discovery commands to record `$PFG_HOME`, `$PFG_PHP` (`PHP >= 8.1`, recommended `8.2`), `$PFG_DOCROOT`, and MySQL/MariaDB version.
- [ ] In **cPanel -> MultiPHP Manager**, verify the target staging domain/subdomain is bound to the matching **PHP 8.2** (`ea-php82`) handler.
- [ ] Create private operational directories outside `$PFG_DOCROOT`:
  ```bash
  mkdir -p "${PFG_HOME}/pfg27_backups" "${PFG_HOME}/pfg27_logs" "${PFG_HOME}/pfg27_releases" "${PFG_HOME}/pfg27_auth"
  chmod 700 "${PFG_HOME}/pfg27_backups" "${PFG_HOME}/pfg27_logs" "${PFG_HOME}/pfg27_releases" "${PFG_HOME}/pfg27_auth"
  ```

### Step 2 — Read-Only Database Inspection (`D-21`)
- [ ] Before running any migration or modifying any table, inspect the target Bluehost database in read-only mode to inventory existing tables and determine whether historical rows are QA test data or real early participant records (`D-21`):
  ```bash
  mysql -u "<db_user>" -p "<db_name>" -e "
    SELECT TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY TABLE_NAME;
  "
  mysql -u "<db_user>" -p "<db_name>" -e "
    SELECT category_code, status, COUNT(*) AS row_count, MIN(created_at) AS earliest, MAX(created_at) AS latest
    FROM registrations
    GROUP BY category_code, status;
  " 2>/dev/null || echo "Fresh database (no registrations table yet)"
  ```
- [ ] Record the output in the deployment log for Project Owner sign-off on `D-21` (note: migrations `000`–`012` preserve all existing rows either way).

### Step 3 — Verified Pre-Migration Database & Docroot Backup
- [ ] Capture a full transactional `mysqldump` snapshot and a full tarball of any pre-existing `$PFG_DOCROOT` files outside the document root, and record their SHA-256 checksums:
  ```bash
  export PFG_TS="$(date +%Y%m%d_%H%M%S)"
  mysqldump --host=localhost --user="<db_user>" -p \
    --single-transaction --routines --triggers --hex-blob \
    --default-character-set=utf8mb4 \
    "<db_name>" --result-file="${PFG_HOME}/pfg27_backups/pre_cutover_db_${PFG_TS}.sql"
  chmod 600 "${PFG_HOME}/pfg27_backups/pre_cutover_db_${PFG_TS}.sql"

  if [ -d "${PFG_DOCROOT}" ]; then
    tar -czf "${PFG_HOME}/pfg27_backups/pre_cutover_docroot_${PFG_TS}.tar.gz" -C "${PFG_DOCROOT}" .
    chmod 600 "${PFG_HOME}/pfg27_backups/pre_cutover_docroot_${PFG_TS}.tar.gz"
  fi

  sha256sum "${PFG_HOME}/pfg27_backups/pre_cutover_"*"_${PFG_TS}".*
  ```
- [ ] **Gate:** Confirm the `.sql` dump is non-empty and readable before proceeding to Step 4. (Required for Migration `004` rollback safety; see [DATABASE_MIGRATION_HISTORY.md](DATABASE_MIGRATION_HISTORY.md).)

### Step 4 — Isolated Staging Credentials Outside Document Root (`.pfg27_env.php`)
- [ ] Generate four distinct, cryptographically random 64-hex-character secrets on the server:
  ```bash
  "$PFG_PHP" -r 'foreach (["AUTH_SECRET","QR_SIGNING_SECRET","MFA_ENCRYPTION_KEY","DATA_ENCRYPTION_KEY"] as $k) { echo "$k => " . bin2hex(random_bytes(32)) . PHP_EOL; }'
  ```
- [ ] Create `${PFG_HOME}/.pfg27_env.php` (or `.pfg27_staging_env.php` pointed to by `PFG_ENV_FILE` if staging and production share the same cPanel account) outside `$PFG_DOCROOT` with `chmod 600`:
  ```php
  <?php
  return [
      'APP_ENV'                             => 'staging',
      'APP_URL'                             => 'https://purplefestgujarat.ajeetgraphics.com',
      'DB_DRIVER'                           => 'mysql',
      'DB_HOST'                             => 'localhost',
      'DB_PORT'                             => '3306',
      'DB_NAME'                             => '<staging_db_name>',
      'DB_USER'                             => '<staging_db_user>',
      'DB_PASS'                             => '<staging_db_password>',
      'AUTH_SECRET'                         => '<64-hex-char-random-secret-1>',
      'QR_SIGNING_SECRET'                   => '<64-hex-char-random-secret-2>',
      'MFA_ENCRYPTION_KEY'                  => '<64-hex-char-random-secret-3>',
      'DATA_ENCRYPTION_KEY'                 => '<64-hex-char-random-secret-4>',
      'REGISTRATION_PUBLIC_OPEN'            => '0',        // Closed by default during restricted staging verification!
      'ATTENDANCE_ENFORCE_EVENT_DAY_WINDOW' => '1',        // Blocks RGST future-date marks before Jan 21-23, 2027 IST
      'MAIL_DRIVER'                         => 'disabled', // Keep 'disabled' until Step 12 Resend pilot test!
      'RESEND_API_KEY'                      => '',
      'RESEND_FROM_EMAIL'                   => 'noreply@<confirmed-domain>',
      'RESEND_FROM_NAME'                    => 'Purple Festival Gujarat 2027 (Staging)',
      'MAIL_REPLY_TO'                       => 'support@<confirmed-domain>',
      'RESEND_WEBHOOK_SECRET'               => '',
  ];
  ```
- [ ] Enforce strict file permissions:
  ```bash
  chmod 600 "${PFG_HOME}/.pfg27_env.php"
  ```

### Step 5 — Restricted Staging Access Gate (`.htaccess` Basic Auth + `X-Robots-Tag: noindex, nofollow`)
- [ ] Create an Apache password file outside `$PFG_DOCROOT` so search engines and unauthorized external visitors cannot access or index the staging site:
  ```bash
  htpasswd -c "${PFG_HOME}/pfg27_auth/.htpasswd_staging" pfg27_staging_reviewer
  chmod 640 "${PFG_HOME}/pfg27_auth/.htpasswd_staging"
  ```
- [ ] Prepare the staging-only `.htaccess` header and Basic Auth stanza (to prepend or activate via cPanel *Directory Privacy* on `$PFG_DOCROOT`, allowing `/api/webhooks/resend` through for Step 14 Svix webhook delivery):
  ```apache
  # Restricted Staging Gate + Search Engine De-Indexing
  <IfModule mod_headers.c>
      Header always set X-Robots-Tag "noindex, nofollow, noarchive"
  </IfModule>
  AuthType Basic
  AuthName "Purple Festival Gujarat 2027 — Restricted Staging"
  AuthUserFile /ABSOLUTE/PATH/TO/pfg27_auth/.htpasswd_staging
  Require valid-user
  SetEnvIf Request_URI "^/api/webhooks/resend$" ALLOW_RESEND_WEBHOOK=1
  <RequireAny>
      Require valid-user
      Require env ALLOW_RESEND_WEBHOOK
  </RequireAny>
  ```

### Step 6 — Deploy Exact Verified Release Archive (`build-release-package.php --zip`)
- [ ] On the local build machine or CI artifact, generate the verified release zip archive and manifest:
  ```bash
  php scripts/build-release-package.php \
    --out=tests/tmp/release-package \
    --zip=tests/tmp/pfg27-release.zip \
    --json
  ```
- [ ] Verify `tests/tmp/RELEASE_MANIFEST.json` reports `"status": "PASSED"`, `"excludedLeakCount": 0`, and `"phpSyntaxLintFailedCount": 0`.
- [ ] Upload `pfg27-release.zip` and `RELEASE_MANIFEST.json` to `${PFG_HOME}/pfg27_releases/` (never upload `RELEASE_MANIFEST.json` inside `$PFG_DOCROOT`), verify the zip SHA-256 hash, extract into `$PFG_DOCROOT`, and re-apply the Step 5 staging gate block at the top of `$PFG_DOCROOT/.htaccess`.

### Step 7 — Execute Versioned CLI Database Migrations (`000`–`012`) & Reference Seed
- [ ] From `$PFG_DOCROOT`, inspect current migration status and apply migrations `000`–`012` plus reference seed data:
  ```bash
  cd "${PFG_DOCROOT}"
  "$PFG_PHP" database/migrate.php --status
  "$PFG_PHP" database/migrate.php --up --seed
  ```

### Step 8 — Verify Schema Totals, Foreign Keys, Checksums & Hosting Environment
- [ ] Run the schema verifier and environment verifier:
  ```bash
  "$PFG_PHP" database/migrate.php --verify
  "$PFG_PHP" scripts/verify-bluehost-env.php --skip-network
  ```
- [ ] **Gate:** Confirm `database/migrate.php --verify` outputs **`Tables: 70 | Foreign Keys: 79 | Applied: 13/13 | Pending: 0`** and `VERIFY PASSED`, and `verify-bluehost-env.php` reports `RESULT: PASSED`.

### Step 9 — Verify HTTPS, Security Headers, `X-Robots-Tag` & `.htaccess` Containment
- [ ] Run `curl` checks against `https://<staging-host>` (supplying `-u pfg27_staging_reviewer:<pass>` for Basic Auth):
  - Confirm `http://<staging-host>/` returns `301 Moved Permanently` to `https://<staging-host>/`.
  - Confirm `Strict-Transport-Security`, `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, and `X-Robots-Tag: noindex, nofollow, noarchive` are present.
  - Confirm internal paths return **`HTTP 403 Forbidden`**:
    - `/includes/config.php` -> `403`
    - `/database/migrate.php` -> `403`
    - `/scripts/process-notification-queue.php` -> `403`
    - `/scripts/verify-bluehost-env.php` -> `403`
    - `/.user.ini` -> `403`
  - Confirm retired QR endpoints return **`HTTP 410 Gone`**:
    - `GET /api/qr/pass` -> `410 Gone`
    - `POST /api/qr/verify` -> `410 Gone`
    - `POST /api/checkins/confirm` -> `410 Gone`

### Step 10 — Superadmin Verification, Revised Auth Policy (`CAD-16`) & Legacy Unprofiled Admin Migration (§5.2)
- [ ] Provision or verify the initial `SUPERADMIN` account via CLI (note: TOTP/authenticator MFA is discontinued under `CAD-16`; staff authenticate via **username + password** with universal 5-attempt / 3-hour atomic account lockout and automatic unlock):
  ```bash
  SUPERADMIN_BOOTSTRAP_USERNAME="superadmin" \
  SUPERADMIN_BOOTSTRAP_PASSWORD="<strong-12+-char-password>" \
  "$PFG_PHP" database/bootstrap-superadmin.php
  ```
- [ ] Sign in at `https://purplefestgujarat.ajeetgraphics.com/staff/login` as `SUPERADMIN` using **username + password** (confirm direct landing on `/admin` with zero MFA prompts, and confirm `GET /api/auth/mfa` returns `HTTP 410 Gone`).
- [ ] **Execute the Legacy Unprofiled Admin Migration Procedure (Section 4 below / §5.2)** for any pre-existing `ADMIN` accounts in `users`.

### Step 11 — Restricted Staging Smoke Tests (Launch Gate, Self-Approval Block, Future-Date Attendance Override)
- [ ] **Public Registration Closed Gate (`§6`):** While `REGISTRATION_PUBLIC_OPEN=0`, verify `POST /api/registrations` returns **`HTTP 503`** (`"Festival registration is not currently open"`, `registrationClosed: true`).
- [ ] Temporarily toggle `registration_public_open = 1` in `system_settings` (or `.pfg27_env.php`) for controlled QA testing with organizer-owned accounts:
  - Test `/apply/attendee`, `/apply/volunteer`, and `/apply/sli` submissions and `/dashboard` rendering (confirming `PFG-2027-NNNNNN` IDs and zero QR codes).
  - **Staff Self-Approval Block (`§5.3`):** Attempt `PATCH /api/admin/registrations` on a staff user's own personal registration and verify **`HTTP 403`** (`SELF_APPROVAL_BLOCKED` logged in `audit_logs`).
  - **Least-Privilege Notification Split (`§5.1`):** Log in as a narrow coordinator (e.g. `volunteer_coordinator` or `content_manager`) and verify `GET /api/admin/notifications` returns **`HTTP 403`**; log in as `registration_manager` and verify `GET` succeeds (with auth templates hidden) while `POST action=retry/resend` returns **`HTTP 403`**.
  - **IST Event-Day Attendance Window (`§8`):** Log in to `/rgst` as `RGST` before `2027-01-21` IST and verify marking attendance for `2027-01-21` is blocked with **`HTTP 403`**; then verify `SUPERADMIN` or `registration_manager` (`attendance.override`) can record an audited exception (`ATTENDANCE_FUTURE_DATE_OVERRIDE`) with `overrideFutureDate: true` and `overrideReason >= 10` characters.
- [ ] Reset `registration_public_open = 0` immediately after completing the registration smoke test until full launch sign-off.

### Step 12 — Controlled Resend Pilot Delivery (Organizer Test Inboxes Only)
- [ ] Complete items `1`–`9` of the **12-Item Resend Activation Checklist** (Section 3 below), update `${PFG_HOME}/.pfg27_env.php` with `'MAIL_DRIVER' => 'resend'` and `'RESEND_API_KEY' => '<live_key>'`, and run `$PFG_PHP scripts/verify-bluehost-env.php` (without `--skip-network`) to verify TLS connectivity to `https://api.resend.com/`.
- [ ] Trigger a test verification or password recovery email strictly to an **organizer-owned test inbox** (never to real participant addresses) and dispatch one batch manually:
  ```bash
  "$PFG_PHP" scripts/process-notification-queue.php --batch=5 --max-seconds=30 --json
  ```
- [ ] Confirm the row transitions `QUEUED -> PROCESSING -> PROVIDER_ACCEPTED`, `ephemeral_secret_enc` is wiped to `NULL`, and the email arrives in the organizer test inbox with intact multipart HTML + plain text.
- [ ] Also verify that an already-used or superseded verification/reset token queued in `notification_queue` is automatically transitioned to `CANCELLED` (`error_category = 'TOKEN_EXPIRED_OR_SUPERSEDED'`) with `ephemeral_secret_enc = NULL` without calling Resend (`§9`).

### Step 13 — cPanel Cron Worker Activation
- [ ] In **Bluehost cPanel -> Advanced -> Cron Jobs**, configure the recurring worker (`*/2 * * * *` or the minimum interval permitted by the hosting plan, using the discovered `$PFG_PHP`, `$PFG_HOME`, and `$PFG_DOCROOT` paths):
  ```bash
  /usr/bin/flock -n ${PFG_HOME}/pfg27_logs/notification_worker.lock ${PFG_PHP} ${PFG_DOCROOT}/scripts/process-notification-queue.php --batch=25 --max-seconds=45 --lock-ttl=300 --drain --json >> ${PFG_HOME}/pfg27_logs/notification_worker.log 2>&1
  ```
- [ ] Monitor `${PFG_HOME}/pfg27_logs/notification_worker.log` across two cron intervals to confirm clean JSON execution and lock release.

### Step 14 — Signed Svix Webhooks & Operational Acceptance Sign-Off
- [ ] In the Resend Dashboard, register `https://<confirmed-host>/api/webhooks/resend` subscribed to `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.complained`, and `email.failed`, and save the `whsec_...` signing secret as `RESEND_WEBHOOK_SECRET` in `${PFG_HOME}/.pfg27_env.php`.
- [ ] Send a controlled pilot email and confirm `/api/webhooks/resend` verifies the Svix HMAC-SHA256 signature (`200 OK`), logs the event in `notification_delivery_logs`, and transitions the `notification_queue` row to `DELIVERED`.
- [ ] Execute the manual Assistive Technology checks in [MANUAL_ACCESSIBILITY_ACCEPTANCE_CHECKLIST.md](MANUAL_ACCESSIBILITY_ACCEPTANCE_CHECKLIST.md) and obtain Project Owner sign-off on Section A of [FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md](FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md) before removing Basic Auth and setting `REGISTRATION_PUBLIC_OPEN=1` for public production launch.

---

## 3. Concise 12-Item Resend Activation Checklist (§16)

> **Security Rule:** Complete all 12 items directly in the DNS provider, Resend Dashboard, and Bluehost `${PFG_HOME}/.pfg27_env.php` (`chmod 600`). Never transmit API keys or webhook secrets via chat or Git.

| # | Checklist Item | Target System | Verification Criterion | Status |
|---:|---|---|---|---|
| **1** | **Confirm Final Sending Domain (`D-22`)** | Project Owner | Written confirmation of authoritative domain (`purplefestivalgujarat.com` vs `purplefestivalvadodara.com`) | `PENDING OWNER` |
| **2** | **Register Domain / Subdomain in Resend** | Resend Dashboard -> Domains | Domain added in expected region | `NOT CONFIGURED` |
| **3** | **Publish SPF DNS Records** | Domain DNS Zone | Resend MX & SPF TXT records published on bounce/return-path subdomain | `NOT CONFIGURED` |
| **4** | **Publish DKIM DNS Records** | Domain DNS Zone | Resend `resend._domainkey` CNAME/TXT records published and marked `Verified` in Resend | `NOT CONFIGURED` |
| **5** | **Publish DMARC Policy Record** | Domain DNS Zone | `_dmarc.<domain>` TXT record (`v=DMARC1; p=quarantine; rua=...`) published | `NOT CONFIGURED` |
| **6** | **Configure Verified Sender & Reply-To** | `${PFG_HOME}/.pfg27_env.php` | `RESEND_FROM_EMAIL`, `RESEND_FROM_NAME`, `MAIL_REPLY_TO`, and `NOTIFICATION_SUPPORT_EMAIL` match verified domain | `NOT CONFIGURED` |
| **7** | **Provision Sending-Scoped `RESEND_API_KEY`** | Resend -> `.pfg27_env.php` | Stored strictly in `${PFG_HOME}/.pfg27_env.php` (`chmod 600`) outside `$PFG_DOCROOT` | `NOT CONFIGURED` |
| **8** | **Register HTTPS Webhook Endpoint** | Resend Dashboard -> Webhooks | `https://<host>/api/webhooks/resend` subscribed to `email.sent`, `delivered`, `delivery_delayed`, `bounced`, `complained`, `failed` | `NOT CONFIGURED` |
| **9** | **Provision `RESEND_WEBHOOK_SECRET` (`whsec_...`)** | Resend -> `.pfg27_env.php` | Stored in `${PFG_HOME}/.pfg27_env.php`; `GET /api/webhooks/resend` returns `405` and unsigned `POST` returns `401` | `NOT CONFIGURED` |
| **10** | **Verify Outbound TLS from Bluehost CLI** | Bluehost SSH (`verify-bluehost-env.php`) | `"$PFG_PHP" scripts/verify-bluehost-env.php` passes TLS connectivity check to `https://api.resend.com/` | `NOT CONFIGURED` |
| **11** | **Verify Stale Auth Token Guard & Ephemeral Wipe** | Bluehost SSH (`process-notification-queue.php`) | Dispatched auth email wipes `ephemeral_secret_enc = NULL`; expired/superseded auth token cancels with `TOKEN_EXPIRED_OR_SUPERSEDED` | `VERIFIED LOCALLY` |
| **12** | **Controlled Pilot Delivery & Svix `DELIVERED` Callback** | Organizer Test Inboxes (Gmail/Outlook) | Pilot email reaches `PROVIDER_ACCEPTED` -> Svix webhook transitions row to `DELIVERED` -> zero HTML/plain-text rendering defects | `CONFIGURATION BLOCKED` |

---

## 4. Legacy Unprofiled Admin Migration Procedure (§5.2)

To prevent breaking existing pre-Phase-8 `ADMIN` accounts during database upgrade while enforcing Phase 8/18 least-privilege governance:

1. **How Legacy Unprofiled Admins Work:**
   - Any `users` row holding the `ADMIN` role with `permission_profile_id IS NULL` is flagged by `includes/auth.php` (`pfgIsLegacyUnprofiledAdmin()`) and `/api/admin/users` with `"isLegacyUnprofiledAdmin": true` and `"permissionProfileSlug": null`.
   - Note that even legacy unprofiled Admins are **strictly blocked** from Superadmin-only permissions (`admin_accounts.manage`, `settings.manage`, `audit_logs.read`, `notifications.auth.manage`, `cms.manage`, `programme.manage`, `communications.manage`) and from self-approving their own personal registration (`SELF_APPROVAL_BLOCKED`). However, they retain legacy default access to registrations, accommodations, and non-auth notification queue operations until assigned an explicit operational profile.
2. **Step-by-Step Migration in `/admin` (During Step 10 of Cutover):**
   - Sign in as `SUPERADMIN` and open **`/admin` -> Staff & Profiles**.
   - Filter or inspect the Staff table for any `ADMIN` row displaying `permissionProfileSlug: null` / `isLegacyUnprofiledAdmin: true` (or check via CLI: `"$PFG_PHP" -r 'require "includes/config.php"; require "includes/database.php"; $pdo=getDb(); print_r($pdo->query("SELECT u.id, u.email, u.full_name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code=\"ADMIN\" AND u.permission_profile_id IS NULL AND u.deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC));'`).
   - For each legacy `ADMIN` account, assign the appropriate least-privilege operational profile (`registration_manager`, `volunteer_coordinator`, `sli_coordinator`, `accessibility_coordinator`, `programme_coordinator`, `content_manager`, or `communications_manager`) via `PATCH /api/admin/users` (the **Edit Staff** modal).
   - Saving the profile assignment automatically revokes all active login sessions for that administrator (`sessionsRevoked`), immediately enforcing the narrow profile permissions (e.g., removing `accommodations.read` and `notifications.retry`/`resend` from coordinators who do not need them).
   - Confirm zero active `ADMIN` accounts remain with `permission_profile_id IS NULL` before opening public registration.

---

## 5. `.github/workflows/deploy-bluehost-php.yml`, Corrected `.cpanel.yml` & Ongoing Staging Workflow (§20)

| File | Trigger Condition | What It Executes | Cutover Risk Analysis & Operational Rule |
|---|---|---|---|
| **`.github/workflows/deploy-bluehost-php.yml`** | Triggered on `push` to `branches: [main]` or manual `workflow_dispatch` on GitHub. | Checks out the repository on `ubuntu-latest`, runs `php -l` syntax checks, invokes `php scripts/build-release-package.php` to build a clean runtime release archive (`bluehost-php-site.tar.gz` / `.zip`), and uploads it to the `latest-php-build` GitHub Release. | **Safe Build Artifact Generator (Zero Direct Production Push):** This workflow **never connects to Bluehost or any unrelated production website** (no SSH/FTP/SFTP push step exists). Pushing verified commits to `main` safely refreshes the downloadable `latest-php-build` GitHub Release assets. |
| **`.cpanel.yml` + `scripts/deploy-staging.sh`** | Triggered by **Bluehost cPanel Git Version Control** or manually via SSH (`sh scripts/deploy-staging.sh`). | Resolves canonical `$REPO_DIR` (`pwd -P`) and reads `$HOME/.pfg27_staging_docroot`. **Safe no-op (exit 0, zero files modified/deleted)** if `$HOME/.pfg27_staging_docroot` is unset or overlaps `$REPO_DIR`. Only when the Git clone and staging docroot are strictly separate directories does it invoke `scripts/build-release-package.php` into `$HOME/pfg27_releases/cpanel_stage_*`, prepend `$HOME/pfg27_auth/staging_htaccess_header.conf` (if present), and sync strictly allowlisted runtime files into the staging docroot without `rm -rf`. | **Self-Copy & Deletion Hazard Eliminated:** Previous `.cpanel.yml` hardcoded `/home1/tstpllmy/public_html/purplefestivalgujarat/` and ran `rm -rf` + `cp -R`, which failed and deleted tracked files when the Bluehost Git repo and docroot shared one folder. The corrected `.cpanel.yml` is 100% non-destructive and safe even if an older in-place cPanel Git repo pulls `main`. |

### Straightforward Ongoing Development -> Staging Workflow (`main` -> `purplefestgujarat.ajeetgraphics.com`)

Once the initial cutover onto `purplefestgujarat.ajeetgraphics.com` is complete, set up a clean separate Git checkout outside the webroot (never inside `$PFG_DOCROOT`) so every verified push to `main` deploys cleanly in two commands:

```bash
# One-time setup on Bluehost SSH (outside public_html / subdomain docroot):
export PFG_HOME="$(cd ~ && pwd -P)"
export PFG_DOCROOT="<verified-docroot-of-purplefestgujarat.ajeetgraphics.com>"
git clone -b main https://github.com/gurarpitzz/purplefestivalgujarat.git "${PFG_HOME}/pfg27_repo"
printf '%s\n' "${PFG_DOCROOT}" > "${PFG_HOME}/.pfg27_staging_docroot"
chmod 600 "${PFG_HOME}/.pfg27_staging_docroot"

# Save the staging Basic Auth + X-Robots-Tag header outside webroot so every deploy preserves it automatically:
#   ${PFG_HOME}/pfg27_auth/staging_htaccess_header.conf

# Ongoing deploy after verified changes are pushed to GitHub `main`:
cd "${PFG_HOME}/pfg27_repo" && git pull --ff-only origin main && sh scripts/deploy-staging.sh
cd "${PFG_DOCROOT}" && "$PFG_PHP" database/migrate.php --up --seed && "$PFG_PHP" database/migrate.php --verify
```

*(Optionally, point Bluehost cPanel -> Git Version Control at `${PFG_HOME}/pfg27_repo` on branch `main`; clicking **Update from Remote** -> **Deploy HEAD Commit** will automatically execute `.cpanel.yml` -> `scripts/deploy-staging.sh` safely without overwriting `.pfg27_env.php`, staging `.htaccess` Basic Auth, or database data.)*
