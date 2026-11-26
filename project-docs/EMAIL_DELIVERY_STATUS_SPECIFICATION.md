# Email Delivery Status Specification — Purple Festival Gujarat 2027

**Authoritative Table:** `notification_queue` & `notification_delivery_logs`  
**Core Integrity Rule:** A message is **NEVER** marked `DELIVERED` merely because HTML rendered, a queue row was inserted, a worker executed, or Resend returned HTTP 200 with a message ID. `DELIVERED` is set **exclusively** upon cryptographically verified provider webhook confirmation (`email.delivered`).

---

## 1. Truthful Delivery Lifecycle & Monotonic Precedence

| Status | Precedence Rank | Meaning & Evidence Required | Timestamp Column(s) |
|---|---:|---|---|
| `CREATED` | 5 | Initial logical creation state prior to queueing | `created_at` |
| `QUEUED` | 10 | Persisted in transactional outbox; awaiting worker claim (or held when `MAIL_DRIVER=disabled`) | `created_at` |
| `RETRY_PENDING` | 15 | Transient error (`TEMP_NETWORK`, `RATE_LIMITED`, `PROVIDER_5XX`, `INVALID_PROVIDER_RESPONSE`, `ABANDONED_LEASE_RECOVERED`); scheduled for exponential backoff retry | `next_retry_at`, `last_attempt_at` |
| `PROCESSING` | 20 | Exclusively claimed by an active worker (`locked_by`, `locked_until`) | `last_attempt_at`, `locked_until` |
| `DELIVERY_UNKNOWN` | 30 | Request timed out after network dispatch OR abandoned lease exhausted `max_attempts`; provider acceptance uncertain — never misrepresented as `DELIVERED` | `last_attempt_at` |
| `CAPTURED_LOCAL` | 35 | Local development/testing only (`MAIL_DRIVER=file`): JSON envelope written to `tests/tmp/mail-sink/` | `sent_at`, `provider_accepted_at` |
| `PROVIDER_ACCEPTED` | 40 | Provider (Resend or `resend_simulated`) returned HTTP `2xx` with a validated `provider_message_id` | `sent_at`, `provider_accepted_at` |
| `DELIVERED` | 60 | Confirmed by Svix-verified `email.delivered` webhook event | `delivered_at`, `last_provider_event_at` |
| `FAILED` | 70 | Permanent rejection (`INVALID_RECIPIENT`, `AUTH_CONFIG`, `UNVERIFIED_DOMAIN`, `TEMPLATE_ERROR`, `PERMANENT_REJECT`), exhausted retries, or `email.failed` webhook | `failed_at` |
| `SUPPRESSED` | 75 | Suppressed by provider suppression list (`email.suppressed` webhook) | `failed_at` |
| `BOUNCED` | 80 | Hard bounce or spam complaint (`email.bounced` / `email.complained` webhook); overrides earlier `PROVIDER_ACCEPTED` or `DELIVERED` on delayed bounce | `bounced_at` |
| `CANCELLED` | 90 | Explicitly cancelled before dispatch | `updated_at` |

---

## 2. Out-of-Order & Edge-Case Webhook Rules (`T4-05` Verified)

| Scenario | Handled Behaviour |
|---|---|
| **Duplicate Webhook (`svix-id` replay)** | `uq_ndl_provider_event (provider, provider_event_id)` rejects duplicate log insert; endpoint returns HTTP `200` with `action: DUPLICATE_EVENT_IGNORED`. |
| **Delayed Out-of-Order Event** (`email.sent` arriving AFTER `email.delivered`) | Precedence check (`40 < 60`) ignores regression (`OUT_OF_ORDER_IGNORED`); status remains `DELIVERED` and event is recorded in `notification_delivery_logs`. |
| **Delayed Bounce After Acceptance/Delivery** (`email.bounced` after `PROVIDER_ACCEPTED` or `DELIVERED`) | Precedence (`80 > 60 > 40`) transitions cleanly to `BOUNCED` and stamps `bounced_at`. |
| **Unknown `provider_message_id`** | Returns HTTP `200` (`IGNORED_UNKNOWN_MESSAGE`) so Resend does not retry endlessly for messages from another environment. |
| **Invalid Svix Signature / Expired Timestamp (>300s)** | Immediately rejected with HTTP `401 Unauthorized` before touching the database. |
| **Delivery After Previous Temporary Failure** | A job in `RETRY_PENDING` succeeds on attempt 2 -> `PROVIDER_ACCEPTED` -> later `email.delivered` -> `DELIVERED`; full attempt history preserved in `notification_delivery_logs`. |
