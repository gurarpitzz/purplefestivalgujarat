# Historical Data Protection & Sensitive Field Migration Report

**Phase:** Phase 3H (Addressing `FND-11`, `FND-06`, `FND-03`, `D-01`, `D-20`)  
**Migration:** `database/migrations/004_phase3_consolidate_duplicates_and_historical_encryption.php`  
**Cryptographic Primitive:** Authenticated `AES-256-GCM` (`pfgEncryptValue()` / `pfgDecryptValue()` in `includes/config.php`, format `enc:v1:<base64(iv[12] || tag[16] || ciphertext)>`, domain-separated keys `DATA_ENCRYPTION_KEY` for participant disclosures and `MFA_ENCRYPTION_KEY` for TOTP secrets).

---

## 1. Historical Records Preserved Without Destruction

| Asset Class | Tables Protected | Preservation Guarantee |
|---|---|---|
| **Legacy Participant Categories** | `registration_categories`, `registrations` | All 5 legacy categories (`DELEGATE`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`) retained with `category_tier = 'LEGACY'`; zero historical rows deleted or overwritten. |
| **Legacy Registration Codes** | `registrations.registration_code` | Existing codes (including `PFG-2027-000099-A1B2` suffix format) preserved verbatim; sequence floor synced above max legacy number (`T3-03`). |
| **Historical QR Credentials & Check-ins (`D-01`)** | `qr_credentials`, `attendance_entitlements`, `checkins`, `credential_events` | Retained intact for audit & historical reporting; protected with `ON DELETE RESTRICT` FKs. |
| **Archived Legacy Duplicate Tables** | `registration_accommodations`, `registration_disabilities`, `delegate_profiles`, `coupon_codes`, `payments`, `notification_jobs` | Consolidated non-destructively into canonical tables; zero tables dropped; zero active writers. |

---

## 2. Historical Sensitive Data Remediation (Idempotent Migration `004`)

| Target Table & Columns | Remediation Action | Idempotency / Double-Encryption Safeguard | Verification (`T3-03`) |
|---|---|---|---|
| `accommodation_requests` (`free_text_details`, `notes`, `coordinator_notes`) | Encrypts any non-empty plaintext value with `pfgEncryptValue($val, 'data')` | Skips `NULL`, empty string, or any string already starting with `enc:v1:` | Plaintext encrypted -> decrypted exact match; rerun re-encrypted `0` rows |
| `accommodation_assignments` (`coordinator_notes`) | Encrypts legacy plaintext coordinator notes (`purpose = 'data'`) | Prefix check `str_starts_with($val, 'enc:v1:')` | Verified |
| `participant_disability_disclosures` (`self_description`, `notes`) | Encrypts legacy plaintext disability self-descriptions (`purpose = 'data'`) | Prefix check `enc:v1:` | Plaintext encrypted -> decrypted exact match |
| Archived `registration_accommodations` & `registration_disabilities` | Also encrypts legacy mirror free-text columns so zero plaintext sensitive disability/accommodation text remains at rest anywhere in DB | Prefix check `enc:v1:` | Verified |
| `users.mfa_secret` -> `users.mfa_secret_encrypted` | Migrates any legacy plaintext Base32 TOTP secret to `pfgEncryptValue($secret, 'mfa')`, then clears `users.mfa_secret = NULL` | Preserves existing `enc:v1:` `mfa_secret_encrypted` if already set | `mfa_secret = NULL`, `mfa_secret_encrypted` decrypted to exact Base32 secret |
| `notification_queue` (`payload`, `body_html`, `body_text`) | Redacts `rawToken`, `resetUrl`, `token`, `secret`, `PFG27.<nonce>.<sig>`, and `reset-password?token=...` via `pfgScrubSensitiveArray()` + regex without deleting rows | Preserves non-sensitive metadata (`"safe":"keep"`) and row history | Legacy QR token & reset URL scrubbed; row preserved |
| `registration_responses` (`answers`) | Strips legacy `password`, `csrfToken`, `isPersonWithDisability`, `disabilityCategoryCodes`, `disabilitySelfDescription`, `accommodationTypes`, `accommodationFreeText` keys from JSON | Preserves non-sensitive registration snapshot fields (`fullName`, etc.) | Legacy password & health note stripped; row preserved |

---

## 3. Remaining Owner Governance Dependency (`D-27`)

- All technical encryption-at-rest, read-access auditing (`SENSITIVE_DATA_READ`), and redaction controls are verified locally (`T2-17`, `T2-20`, `T3-03`, `T3-13`).
- Post-festival automated purge/retention schedules remain pending Project Owner sign-off on Decision **`D-27`** (Data Retention & Privacy Governance).

