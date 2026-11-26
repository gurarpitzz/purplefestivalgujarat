# Resend Integration & External Configuration Guide — Purple Festival Gujarat 2027

**Transport Code:** `includes/notification-service.php` (`pfgDeliverViaProvider()`)  
**Webhook Endpoint:** `api/webhooks/resend.php`  
**Local Integration Status:** `PROVIDER SIMULATION VERIFIED` (`T4-04`, `T4-05`, `T4-08`)  
**Live Resend API & Delivery Status:** **`CONFIGURATION BLOCKED`** — No live Resend API key, verified production domain (`D-22`), DNS records, or live Svix webhook signing secret exist in this workspace. No real external emails have been sent.

---

## 1. Implemented Resend HTTPS Contract

- **Endpoint:** `POST https://api.resend.com/emails` (TLS certificate verification enforced via cURL `CURLOPT_SSL_VERIFYPEER = true`, `CURLOPT_SSL_VERIFYHOST = 2`, timeout `NOTIFICATION_PROVIDER_TIMEOUT = 15s`).
- **Authentication:** `Authorization: Bearer <RESEND_API_KEY>` loaded strictly from `.pfg27_env.php` **outside `public_html`**. Never committed to Git, never logged, never returned in API responses.
- **Provider-Side Idempotency:** Sends Resend's official `Idempotency-Key: <idempotency_key>` header (max 256 chars) so retries of an identical outbox message within Resend's 24-hour window cannot generate duplicate recipient emails.
- **Fail-Closed Guarantee:** If `MAIL_DRIVER=resend` and `RESEND_API_KEY` or `MAIL_FROM_ADDRESS` is empty, delivery fails closed with `error_category = 'AUTH_CONFIG'`. **The legacy insecure PHP `mail()` fallback has been permanently removed.**
- **Multipart Payload:** Sends `from`, `to`, `reply_to`, `subject`, `html`, `text`, and structured `tags` (`template`, `event_type`, `queue_id`).

---

## 2. Svix Webhook Verification (`api/webhooks/resend.php`)

Resend signs every delivery webhook using Svix headers:
- `svix-id` (unique event ID)
- `svix-timestamp` (Unix epoch seconds)
- `svix-signature` (`v1,<base64-signature>` space-delimited list)

Verification flow implemented in `pfgVerifySvixWebhookSignature()` & `pfgProcessResendWebhook()`:
1. Rejects non-`POST` requests with `HTTP 405` (verified on Apache `2.4.58` via `A-28`).
2. Fails closed with `HTTP 503` if `RESEND_WEBHOOK_SECRET` is unset (no unsigned acceptance).
3. Enforces a `±300 second` timestamp replay tolerance.
4. Decodes the `whsec_` base64 secret, computes `HMAC-SHA256("<svix-id>.<svix-timestamp>.<rawBody>")`, and compares using `hash_equals()` across all space-delimited `v1,` signatures.
5. Deduplicates deliveries via `UNIQUE KEY uq_ndl_provider_event (provider_event_id)` on `notification_delivery_logs`.
6. Applies **monotonic state precedence** so delayed/out-of-order `email.sent` or `email.delivery_delayed` events can **never** downgrade an already `DELIVERED`, `BOUNCED`, or `COMPLAINED` notification:
   - `email.sent` -> `PROVIDER_ACCEPTED`
   - `email.delivery_delayed` -> `DELAYED`
   - `email.delivered` -> `DELIVERED` (sets `delivered_at`)
   - `email.bounced` -> `BOUNCED` (sets `bounced_at`)
   - `email.complained` -> `COMPLAINED`
   - `email.failed` / `email.suppressed` -> `FAILED`

---

## 3. External Configuration Checklist (Required Before Live Activation)

> **Status:** All items below are **`CONFIGURATION BLOCKED`** awaiting Project Owner authorization & `D-22` domain sign-off.

| # | Prerequisite | Owner / System | Required Action | Current Status |
|---:|---|---|---|---|
| 1 | Authoritative Domain (`D-22`) | Project Owner | Confirm `purplefestivalgujarat.com` vs `purplefestivalvadodara.com` | `REQUIRES OWNER APPROVAL` |
| 2 | Resend Sending Domain | Resend Dashboard + DNS | Add sending domain (or dedicated subdomain e.g. `mail.<domain>`) in Resend | `NOT CONFIGURED` |
| 3 | SPF Record | Domain DNS | Publish Resend TXT/MX SPF record for bounce/return-path subdomain | `NOT CONFIGURED` |
| 4 | DKIM Keys | Domain DNS | Publish Resend DKIM CNAME/TXT records and confirm `Verified` status in Resend | `NOT CONFIGURED` |
| 5 | DMARC Policy | Domain DNS | Publish `_dmarc.<domain>` TXT record (`v=DMARC1; p=quarantine; rua=...`) | `NOT CONFIGURED` |
| 6 | Sender & Reply-To | Organizer + `.pfg27_env.php` | Set `MAIL_FROM_NAME`, `MAIL_FROM_ADDRESS`, `MAIL_REPLY_TO`, `NOTIFICATION_SUPPORT_EMAIL` | `NOT CONFIGURED` |
| 7 | Production API Key | Resend Dashboard -> `.pfg27_env.php` | Create sending-scoped Resend API key and store **outside `public_html`** | `NOT CONFIGURED` |
| 8 | Webhook Registration | Resend Dashboard -> Bluehost | Register `https://<domain>/api/webhooks/resend` for `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced`, `email.complained`, `email.failed`; store `whsec_...` as `RESEND_WEBHOOK_SECRET` | `NOT CONFIGURED` |
| 9 | Safe Pilot Recipients | Project Owner | Supply 2–3 internal test email addresses (Gmail + Outlook/Apple Mail) for initial live smoke test; **never test against real participant addresses** | `NOT CONFIGURED` |
| 10 | Live End-to-End Smoke Verification | Post-Deploy QA | Run 1 controlled test notification -> verify `LIVE PROVIDER ACCEPTANCE VERIFIED` -> confirm webhook callback `email.delivered` (`ACTUAL DELIVERY VERIFIED`) | `CONFIGURATION BLOCKED` |
