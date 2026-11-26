# Bluehost cPanel Cron Setup Guide — Purple Festival Gujarat 2027

**Worker Script:** `scripts/process-notification-queue.php`  
**Live Deployment Status:** **`NOT DEPLOYED / NOT ACTIVATED`** — Documented and verified locally on PHP `8.2.12` CLI + MariaDB `10.4.32`; no live Bluehost cron job has been created or enabled.

---

## 1. Prerequisites on Bluehost Shared / cPanel Hosting

Before enabling the cron job during an authorized Bluehost deployment window:
1. Run `php scripts/verify-bluehost-env.php` over SSH or one-off cron to confirm:
   - Exact PHP 8.2+ CLI binary path (Bluehost commonly places EA-PHP at `/usr/local/bin/ea-php82` or `/opt/cpanel/ea-php82/root/usr/bin/php` — confirm `php -v` matches web PHP version and has `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`).
   - `.pfg27_env.php` is stored **outside `public_html`** (e.g. `/home/<cpanel_user>/.pfg27_env.php`) with `chmod 600`.
   - `database/migrate.php --status --verify` reports `0` pending migrations (`006_phase4_notifications_and_delivery_tracking` applied).
   - Writable private log directory outside webroot: `/home/<cpanel_user>/pfg27_logs/` (`chmod 750`).
2. Confirm `scripts/process-notification-queue.php` returns HTTP `403` when requested over HTTPS (verified locally on Apache `2.4.58` via `A-29`).

---

## 2. Recommended cPanel Cron Schedule & Command

In **Bluehost cPanel -> Advanced -> Cron Jobs**, add **one** recurring job:

- **Frequency:** Every `2` minutes (`*/2 * * * *`) — or Every `1` minute (`* * * * *`) during peak festival registration windows if permitted by the hosting plan.
- **Command (with `flock` overlap prevention + bounded batch/runtime + outside-webroot logging):**

```bash
/usr/bin/flock -n /home/<cpanel_user>/pfg27_logs/notification_worker.lock \
  /usr/local/bin/ea-php82 /home/<cpanel_user>/public_html/scripts/process-notification-queue.php \
  --batch=25 --max-seconds=45 --lock-ttl=300 --drain --json \
  >> /home/<cpanel_user>/pfg27_logs/notification_worker.log 2>&1
```

> **Note if `flock` is unavailable on a specific Bluehost container:** The command can invoke `ea-php82 ...` directly without `flock` because `pfgClaimNotificationBatch()` uses atomic InnoDB lease locking (proven at **0 duplicate claims and 0 duplicate deliveries across 4 simultaneous overlapping workers** in `T4-08`). `flock` simply avoids redundant PHP startup overhead.

---

## 3. Operational Tuning & Log Rotation

- **Time Budget (`--max-seconds=45`)**: Keeps each invocation well below Bluehost shared CPU/process timeouts and below a 1–2 minute cron cadence.
- **Lease TTL (`--lock-ttl=300`)**: If a PHP process is killed mid-run by Bluehost resource limits, its claimed rows are automatically recovered after 5 minutes by `pfgRecoverAbandonedNotifications()`.
- **Throughput Headroom**: Local benchmark (`T4-08`) processed `1,000` queued rows in `675 ms` with `2,048 KB` peak RAM. Under live Resend HTTPS latency (~`200–400 ms` per API call), a single worker processes ~`100–150` emails per 45-second window (~`3,000–4,500` emails/hour on a 2-minute cadence), comfortably exceeding peak festival bursts.
- **Weekly Log Truncation Cron (optional):** Keep `notification_worker.log` bounded (e.g., retain last 10,000 lines weekly via a simple maintenance cron) and confirm cron logs never contain passwords, API keys, or reset URLs.

---

## 4. Safe Rollback / Emergency Pause

To halt all outbound email processing immediately without touching the web application:
1. Set `'MAIL_DRIVER' => 'disabled'` in `/home/<cpanel_user>/.pfg27_env.php` (worker immediately exits as a no-op with `claimed=0, processed=0`, verified in `T4-07`), **and/or**
2. Delete/pause the Cron Job in Bluehost cPanel.
3. All new registration/admin events continue writing safely to `notification_queue` (`status = 'QUEUED'`) and `in_app_notifications` with zero data loss, ready to drain once re-enabled.
