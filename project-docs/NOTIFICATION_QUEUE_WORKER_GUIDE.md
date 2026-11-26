# Notification Queue Worker Guide — Purple Festival Gujarat 2027

**CLI Entrypoint:** `scripts/process-notification-queue.php`  
**Core Service:** `includes/notification-service.php`  
**Hosting Model:** Short-lived PHP CLI invocation scheduled via Bluehost cPanel Cron Job (**zero persistent daemons, zero Redis, zero RabbitMQ**).  
**HTTP Access Guard:** Blocked with HTTP `403` by root `.htaccess` (verified live on Apache `2.4.58` via `A-29`) and refuses non-CLI SAPI at runtime (`PHP_SAPI !== 'cli'`).

---

## 1. Supported CLI Options

```bash
php /home/<cpanel_user>/public_html/scripts/process-notification-queue.php \
  --batch=25 \
  --max-seconds=45 \
  --lock-ttl=300 \
  --json
```

| Flag | Default | Purpose |
|---|---|---|
| `--batch=N` | `NOTIFICATION_BATCH_LIMIT` (`25`, clamped `1..200`) | Maximum rows claimed per single batch pass |
| `--max-seconds=S` | `NOTIFICATION_MAX_SECONDS` (`45`, clamped `5..240`) | Wall-clock time budget before graceful stop (prevents Bluehost cron overrun) |
| `--lock-ttl=T` | `NOTIFICATION_LOCK_TTL_SECONDS` (`300`, clamped `30..3600`) | Lease duration before a crashed worker's `PROCESSING` row becomes eligible for recovery |
| `--worker-id=ID` | Auto-generated (`worker-<pid>-<rand>`) | Explicit worker identifier recorded in `notification_queue.locked_by` |
| `--driver=NAME` | `MAIL_DRIVER` from `.pfg27_env.php` | Driver override (`file`, `disabled`, `resend`; `simulate` strictly restricted to `APP_ENV=development/test`) |
| `--simulate=MODE` | None | **Dev/test only** (`accept`, `transient_503`, `rate_limit_429`, `permanent_422`, `unverified_403`, `timeout`, `malformed_json`). Hard-blocked in `production`/`staging`. |
| `--drain` | Off | Loops batches until queue is empty or `--max-seconds` budget is reached |
| `--json` | Off | Outputs structured JSON run telemetry for cron logs / monitoring |

---

## 2. Atomic Lease Claiming & Portability (MariaDB 10.4 + MySQL 8.0)

MariaDB `10.4` (local XAMPP) does **not** support `SELECT ... FOR UPDATE SKIP LOCKED` (introduced in MariaDB `10.6`). To maintain 100% portability across both local MariaDB `10.4.32` and Bluehost MySQL `8.0`:

1. **Abandoned Lease Recovery (`pfgRecoverAbandonedNotifications`)**:
   - Finds rows where `status = 'PROCESSING'` AND `locked_until < NOW()`.
   - If `attempts < max_attempts`, transitions back to `RETRY_PENDING` with `next_attempt_at = NOW()`, clears `locked_by` / `locked_until`, and logs a `LEASE_RECOVERED` delivery event.
   - If `attempts >= max_attempts`, transitions to `FAILED` (`error_category = 'LEASE_EXPIRED'`).
2. **Atomic Lease Claim (`pfgClaimNotificationBatch`)**:
   - Executes a single atomic `UPDATE notification_queue SET status = 'PROCESSING', locked_by = :workerId, locked_until = :lockedUntil, attempts = attempts + 1, last_attempt_at = :now WHERE status IN ('QUEUED', 'RETRY_PENDING') AND (next_attempt_at IS NULL OR next_attempt_at <= :now) AND (locked_until IS NULL OR locked_until < :now) ORDER BY priority ASC, created_at ASC, id ASC LIMIT :batchLimit`.
   - Immediately selects only the rows stamped with `locked_by = :workerId AND status = 'PROCESSING'`.
   - Because InnoDB row locks serialize concurrent `UPDATE` writers on the exact claimed rows, two overlapping cron jobs can **never** claim or deliver the same row.

---

## 3. Retry Backoff & Failure Classification

| Failure Category | Example Cause | Next Status | Retry Delay Schedule |
|---|---|---|---|
| `TRANSIENT_HTTP` | Resend `5xx` (`500`/`502`/`503`/`504`) or network reset | `RETRY_PENDING` (until `max_attempts`, then `FAILED`) | `60s -> 300s -> 900s -> 3600s` |
| `RATE_LIMITED` | Resend HTTP `429` | `RETRY_PENDING` (honors `Retry-After` or backoff) | `60s -> 300s -> 900s -> 3600s` |
| `MALFORMED_RESPONSE` | Non-JSON or missing `id` from provider | `RETRY_PENDING` (until `max_attempts`, then `FAILED`) | `60s -> 300s -> 900s -> 3600s` |
| `PERMANENT_REJECT` | Resend HTTP `400` / `422` invalid recipient/payload | `FAILED` immediately (no retry loop) | None |
| `UNVERIFIED_DOMAIN` | Resend HTTP `403` domain not verified | `FAILED` immediately (alert admin) | None |
| `AUTH_CONFIG` | Missing/invalid `RESEND_API_KEY` or `MAIL_FROM_ADDRESS` | `FAILED` immediately (fail-closed; **zero `mail()` fallback**) | None |
| `NETWORK_TIMEOUT` | Curl timeout after request transmission | `DELIVERY_UNKNOWN` (never blindly double-sends without `Idempotency-Key`/operator review) | Manual inspection / idempotent check |

---

## 4. Measured Multi-Worker Concurrency Telemetry (`T4-08`)

Tested with **4 simultaneous PHP `8.2.12` CLI worker processes** against local `MariaDB 10.4.32` (`NOTIFICATION_CONCURRENCY_TEST_RESULTS.csv`):

| Queue Volume | Parallel Workers | Claimed | Accepted | Duplicate Claims | Duplicate Deliveries | Wall Time | Peak Memory | Status |
|---:|---:|---:|---:|---:|---:|---:|---:|---|
| 10 | 4 | 10 | 10 | **0** | **0** | `136 ms` | `2,048 KB` | `PASS` |
| 100 | 4 | 100 | 100 | **0** | **0** | `147 ms` | `2,048 KB` | `PASS` |
| 500 | 4 | 500 | 500 | **0** | **0** | `387 ms` | `2,048 KB` | `PASS` |
| 1,000 | 4 | 1,000 | 1,000 | **0** | **0** | `675 ms` | `2,048 KB` | `PASS` |

---

## 5. Operator Inspection, Retry & Resend API

Authenticated `ADMIN` / `SUPERADMIN` staff (with verified MFA + valid CSRF token) can inspect and operate the queue via `api/admin/notifications.php`:

- `GET /api/admin/notifications` — Redacted paginated view of `notification_queue` + `notification_delivery_logs` (never exposes `ephemeral_secret_enc` or raw secrets).
- `POST /api/admin/notifications` with `action=retry` — Safely requeues a `FAILED` / `DELIVERY_UNKNOWN` record without mutating registration approval state (audited in `audit_logs`).
- `POST /api/admin/notifications` with `action=resend` — Creates a linked new queue record (`resent_from_id`) for non-secret templates; **strictly refuses blind resend** for secret-bearing templates (`PASSWORD_RECOVERY`, `ACCOUNT_VERIFICATION`, `EMAIL_VERIFICATION_RESEND`, `EMAIL_CHANGE_VERIFICATION`) because their ephemeral token was wiped upon initial handoff (`T4-06`).
