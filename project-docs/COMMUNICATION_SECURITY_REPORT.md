# Communication Security & Privacy Report — Purple Festival Gujarat 2027

**Scope:** Phase 4 Transactional Email Service, Queue Storage, Ephemeral Secret Handling, Svix Webhooks & Admin Queue Controls  
**Verification Basis:** `T4-01`–`T4-08` (`tests/phase4_notification_test.php`), `T1-07`–`T1-08`, `T2-05`, `T2-17`, `T2-19`, `A-27`–`A-29` (`Apache 2.4.58`).

---

## 1. Prohibited Sensitive Data Guard (`pfgStripProhibitedEmailVariables`)

Before any payload is serialized into `notification_queue.payload` or rendered into HTML/text templates, `includes/notification-templates.php` strips prohibited sensitive fields so they can **never** leak into emails or queue storage:

- Plaintext passwords, password hashes, session tokens, CSRF tokens (`password`, `passwordHash`, `sessionToken`, `csrfToken`).
- TOTP MFA secrets & recovery codes (`mfaSecret`, `mfa_secret`, `recoveryCodes`).
- Raw sensitive disability disclosures & medical notes (`disabilityDiagnosis`, `medicalNotes`, `disabilityDetails`, `accommodationPrivateNotes`).
- Emergency contact phone numbers (`emergencyContactPhone`, `emergency_contact_phone`).
- Internal administrative review notes (`internalAdminNotes`, `adminInternalNotes`, `reviewerPrivateNotes`).

**Verification (`T4-02`):** Tested across all 6 prohibited field classes; verified **0 occurrences** in rendered HTML, rendered plain text, and persisted `notification_queue.payload`.

---

## 2. Ephemeral Secret Lifecycle (`PASSWORD_RECOVERY` & Phase 5 Verification)

Reset and verification links cannot be stored in plaintext inside `notification_queue.payload` (`FND-06`), yet asynchronous queue delivery needs the action URL at handoff time:

1. **Enqueue:** `pfgEnqueueNotification()` strips `actionUrl` / `rawToken` from the JSON `payload` column and encrypts the ephemeral secret payload using **AES-256-GCM** (`pfgEncryptSensitiveValue()`, `enc:v1:...` keyed from `AUTH_SECRET`) into `notification_queue.ephemeral_secret_enc`.
2. **Handoff:** During worker dispatch (`pfgDispatchClaimedNotification()`), the secret is decrypted strictly in memory to render the single email payload.
3. **Immediate Wipe:** Immediately upon `PROVIDER_ACCEPTED` (or `CAPTURED_LOCAL` / permanent `FAILED`), `notification_queue.ephemeral_secret_enc` is overwritten to `NULL` in MariaDB/MySQL.
4. **Blind Resend Block:** `pfgAdminResendNotification()` explicitly blocks resending secret-bearing templates (`PASSWORD_RECOVERY`, `ACCOUNT_VERIFICATION`, `EMAIL_VERIFICATION_RESEND`, `EMAIL_CHANGE_VERIFICATION`) so an operator can never trigger a broken or stale secret email; the user/admin must initiate a fresh token issuance instead.
5. **Redacted Admin API:** `GET /api/admin/notifications` omits `ephemeral_secret_enc` completely and redacts any sensitive metadata keys.

**Verification (`T4-06`):** Confirmed `ephemeral_secret_enc` starts as `enc:v1:...`, delivers the link cleanly during dispatch, is immediately wiped to `NULL` in DB after handoff, and rejects blind resend on `PASSWORD_RECOVERY`.

---

## 3. QR Discontinuation & External Leakage Elimination (`D-01`, `FND-06`)

- Zero references to `api.qrserver.com` anywhere in `includes/notification-templates.php`, `includes/notification-service.php`, or `includes/mailer.php`.
- Zero participant event-entry QR pass images generated or embedded in any of the 29 templates (`T4-02`, `T2-17`).
- Human-readable Registration IDs (`PFG-2027-000001`) remain prominently displayed in all participant confirmation, review, assignment, and accessibility emails.

---

## 4. Webhook Authentication & Replay Protection (`api/webhooks/resend.php`)

- **Fail-Closed Secret Check:** Returns `HTTP 503` if `RESEND_WEBHOOK_SECRET` is not configured.
- **Method Restriction:** Rejects `GET`/non-`POST` with `HTTP 405` (verified on Apache `2.4.58` via `A-28`).
- **Cryptographic Signature Verification:** Validates Svix `v1` HMAC-SHA256 signature over `"<svix-id>.<svix-timestamp>.<rawBody>"` using constant-time `hash_equals()`. Invalid signatures return `HTTP 401` (`T4-05`).
- **Replay Window:** Rejects timestamps older/newer than `±300s` with `HTTP 401` (`T4-05`).
- **Idempotency & Out-of-Order Guard:** Duplicate `svix-id` events are ignored via `uq_ndl_provider_event`; out-of-order `email.sent` events cannot downgrade `DELIVERED` or `BOUNCED` states (`T4-05`).

---

## 5. Admin Queue Control Hardening (`api/admin/notifications.php`)

- Enforces authenticated `ADMIN` / `SUPERADMIN` session with verified D-20 MFA (`HTTP 401` unauthenticated verified on Apache `2.4.58` via `A-27`; `HTTP 403` for unauthorized roles).
- Enforces session-bound CSRF token validation on `POST` retry/resend actions (`HTTP 403` without token verified in `T4-06`).
- All manual retries and resends write immutable entries to `audit_logs` and never alter application approval states.

---

## 6. Local Development Isolation & Zero Accidental Outbound Delivery

- Local development defaults to `MAIL_DRIVER=file` (writes JSON captures to gitignored `tests/tmp/mail-sink/` outside webroot and excluded from `.cpanel.yml` deploy) or `MAIL_DRIVER=disabled` (worker claims `0` rows, `T4-07`).
- `MAIL_DRIVER=simulate` is hard-blocked outside `development`/`test` environments.
- Insecure PHP `mail()` fallback has been completely removed (`T4-04`).
