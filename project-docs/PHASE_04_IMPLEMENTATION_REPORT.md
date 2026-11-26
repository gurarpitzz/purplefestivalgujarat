# Phase 4 Implementation Report — Transactional Email Service, Notification Queue & Delivery Tracking

**Phase:** Phase 4 (`4A`–`4R`)  
**Repository:** `d:\purplefestgujarat` (Baseline tag: `phase3-verified-baseline` @ `140f2a0`)  
**Runtime Verified:** Native Windows XAMPP (`PHP 8.2.12`, `MariaDB 10.4.32`, `Apache/2.4.58`) — **Zero Docker, Zero Redis, Zero RabbitMQ, Zero Node Backend, Zero Persistent Daemons**  
**Automated Verification Result:** **`82 / 82` PASS (`0` FAILED)** — `53 / 53` PHP/MariaDB tests (`tests/run.php`) + `29 / 29` real Apache `2.4.58` checks (`tests/apache_verification.php`).

---

## 1. Files Created & Modified

### Database & Migrations
- **Created:** `database/migrations/006_phase4_notifications_and_delivery_tracking.php` (+ `_rollback.sql`) — Adds 12 columns & 3 indexes to `notification_queue` (`entity_type`, `entity_id`, `priority`, `locked_by`, `locked_until`, `last_attempt_at`, `last_provider_event`, `last_provider_event_at`, `bounced_at`, `resent_from_id`, `error_category`, `ephemeral_secret_enc`), 5 columns & 2 indexes (`uq_ndl_provider_event`, `idx_ndl_job_created`) to `notification_delivery_logs`, and creates `in_app_notifications` with 2 non-cascading FKs (`fk_inapp_user`, `fk_inapp_queue`). Total schema: **`63` tables, `64` FKs, `7` versioned migrations**.
- **Modified:** `includes/migrations.php` — Updated `PFG_REQUIRED_SCHEMA_VERSION` to `006_phase4_notifications_and_delivery_tracking` and added `pfgComputeMigrationChecksum()` normalizing `\r\n`/`\r` -> `\n` and trimming trailing newlines for 100% deterministic SHA-256 checksums across Windows XAMPP and Bluehost Linux.

### Core Configuration, Templates & Notification Service
- **Modified:** `includes/config.php` — Added `RESEND_WEBHOOK_SECRET`, `MAIL_FROM_NAME`, `MAIL_FROM_ADDRESS`, `MAIL_REPLY_TO`, `NOTIFICATION_BATCH_LIMIT`, `NOTIFICATION_MAX_SECONDS`, `NOTIFICATION_LOCK_TTL_SECONDS`, `NOTIFICATION_PROVIDER_TIMEOUT`, `NOTIFICATION_MAX_ATTEMPTS`, `NOTIFICATION_SUPPORT_EMAIL`.
- **Created:** `includes/notification-templates.php` — Reusable WCAG 2.2 AA/AAA branded multipart template engine covering all **29** lifecycle templates (`20` connected, `9` future-phase), strict HTML escaping, unsafe URL blocking, Gujarati/Hindi UTF-8 preservation, prohibited-field stripping, and zero QR/`qrserver.com` references.
- **Created:** `includes/notification-service.php` — Canonical transactional outbox (`pfgEnqueueNotification`), `in_app_notifications` creation (`pfgCreateInAppNotification`), lease recovery & atomic InnoDB batch claiming (`pfgRecoverAbandonedNotifications`, `pfgClaimNotificationBatch`), provider transport (`file`, `disabled`, dev/test-only `simulate`, fail-closed HTTPS `resend` with `Idempotency-Key` and zero `mail()` fallback), exponential retry backoff, ephemeral AES-256-GCM secret wipe post-handoff, Svix HMAC-SHA256 webhook processing (`pfgVerifySvixWebhookSignature`, `pfgProcessResendWebhook`), and audited admin retry/resend helpers.
- **Refactored:** `includes/mailer.php` — Converted into a clean backward-compatible facade delegating to `notification-service.php` and `notification-templates.php`.

### Worker, Webhook, Admin API & Outbox Integrations
- **Created:** `scripts/process-notification-queue.php` — CLI-only Bluehost cPanel Cron worker supporting `--batch`, `--max-seconds`, `--lock-ttl`, `--worker-id`, `--driver`, `--simulate` (dev/test only), `--drain`, `--json`. Blocked over HTTP via `.htaccess` (`403`).
- **Created:** `api/webhooks/resend.php` — Svix-authenticated POST webhook endpoint (fail-closed `503` when secret unset, `405` on non-POST, `401` on invalid signature/replay).
- **Created:** `api/admin/notifications.php` — MFA-verified `ADMIN`/`SUPERADMIN` redacted queue inspection (`GET`) and CSRF-protected audited `retry` / `resend` (`POST`).
- **Modified:** `api/registrations/index.php` (wired transactional outbox, removed dead `pfgQueueNotificationCompat()`), `api/admin/registrations.php` (wired approval/rejection/waitlist/info-requested/cancellation/assignment triggers), `api/account/accommodations.php` (wired `ACCESSIBILITY_REQUEST_RECEIVED`), `api/admin/accommodations.php` (wired `ACCESSIBILITY_ARRANGEMENT_UPDATED`), `api/auth/password-reset.php` (wired `PASSWORD_RECOVERY` with ephemeral secret wipe & `PASSWORD_RESET_CONFIRMATION`).

### Tests & Verification Harnesses
- **Created:** `tests/phase4_notification_test.php` (`T4-01`–`T4-08`, plus JSON telemetry exports to `tests/tmp/template_rendering_results.json` and `tests/tmp/notification_concurrency_results.json`).
- **Modified:** `tests/run.php` (added Phase 4 suite, `53/53` total), `tests/phase1_containment_test.php` (updated `T1-02` assertions to `63` tables / `64` FKs / `7` migrations), `tests/apache_verification.php` (added `A-27`–`A-29`, `29/29` total).

---

## 2. Issues Discovered & Resolved During Verification

1. **Duplicate Confirmation Email & Unrelated Queue Sweep (`T2-17`)**: Initial registration briefly enqueued both registration confirmation and accessibility confirmation, and inline local-sink dispatch swept unrelated `QUEUED` rows left by `T1-08` (`MAIL_DRIVER=disabled`). Resolved cleanly by scoping `ACCESSIBILITY_REQUEST_RECEIVED` to `/api/account/accommodations.php` and using targeted `pfgDispatchQueuedIdNow($pdo, $qid)` for local-sink dispatch.
2. **Migration Checksum Line-Ending Sensitivity (`T3-01`)**: Editor trailing newline/CRLF differences caused `hash_file()` to report a 1-byte `CHECKSUM_MISMATCH` on migration `006`. Resolved permanently via `pfgComputeMigrationChecksum()` normalizing `\r\n`/`\r` -> `\n` and trimming trailing newlines.
3. **CSRF Fetch in `T4-06` Test Harness**: Test initially guessed the internal CSRF prefix instead of fetching the live session CSRF token from `GET /api/auth/me`; updated to assert the authentic live CSRF workflow.

---

## 3. Explicit Honesty & Scope Boundaries

- **Local/Simulated Verification Only:** Live Resend API acceptance, live DNS verification (SPF/DKIM/DMARC), live inbox delivery, and live Bluehost cPanel cron execution are **`CONFIGURATION BLOCKED`** pending `D-22` and an authorized Bluehost deployment window.
- **`FND-24` & `FND-30` Kept Open:** Neither finding is falsely closed — `FND-24` awaits Phase 5 participant email verification UI/API, and `FND-30` awaits the 9 future-phase business triggers (Phases 5, 6, 12, 13).
