# MFA Security Specification — Purple Festival Gujarat 2027 (Decision D-20)

**Decision Reference:** `D-20` (Approved by Project Owner for Phase 2)  
**Authoritative Implementation:** [includes/auth.php](file:///d:/purplefestgujarat/includes/auth.php), [api/auth/login.php](file:///d:/purplefestgujarat/api/auth/login.php), [api/auth/mfa.php](file:///d:/purplefestgujarat/api/auth/mfa.php), [assets/js/admin-portal.js](file:///d:/purplefestgujarat/assets/js/admin-portal.js)

---

## 1. Role-Based MFA Policy (D-20)

| Role | MFA Requirement | Behavior When Un-Enrolled | Self-Disable Allowed? |
|---|---|---|---|
| **SUPERADMIN** | **Mandatory TOTP MFA** | Login issues enrollment-only session (`mfa_verified = 0`, `mfaEnrollmentRequired = true`); all protected admin APIs return `403` until TOTP enrollment succeeds | **NO (`403 Forbidden`)** |
| **ADMIN** | **Mandatory TOTP MFA** | Login issues enrollment-only session (`mfa_verified = 0`, `mfaEnrollmentRequired = true`); all protected admin APIs return `403` until TOTP enrollment succeeds | **NO (`403 Forbidden`)** |
| **RGST** | Optional initially | Login succeeds with rate limiting, lockout, and CSRF; may optionally enroll or disable TOTP MFA with full re-authentication | Yes (requires password + current TOTP/recovery code) |
| **PARTICIPANT** | Optional | Deferred participant login (Phase 5); optional MFA management preserved | Yes (requires password + current TOTP/recovery code) |

## 2. Cryptographic & Storage Standards
- **Secret Entropy:** `generateMfaSecret()` generates **160 bits** (`random_bytes(20)`) encoded as 32 Base32 characters per RFC 6238 / RFC 4226 (remediating the legacy 80-bit/16-char defect).
- **Authenticated Encryption at Rest:** Both pending setup secrets (`auth_accounts.token_hash`, `provider = 'mfa_setup'`, 15-min TTL) and active secrets (`users.mfa_secret_encrypted`) are encrypted using **AES-256-GCM** (`enc:v1:<base64(iv[12] | tag[16] | ciphertext)>`) with AAD `pfg27:mfa` and key derived from `MFA_ENCRYPTION_KEY`.
- **Legacy Plaintext Elimination:** `users.mfa_secret` is ALWAYS set to `NULL` upon enrollment, replacement, disable, or legacy auto-migration during login.
- **QR Code Generation:** Client-side enrollment QR rendering retains [assets/js/qrcode-gen.js](file:///d:/purplefestgujarat/assets/js/qrcode-gen.js) strictly for `otpauth://totp/...` URIs (zero external QR APIs).

## 3. Enrollment, Replacement, Replay & Recovery Controls
- **Read-Only GET:** `GET /api/auth/mfa` never generates secrets or mutates `auth_accounts`; it returns status metadata only.
- **CSRF-Protected Setup & Replacement:** `POST /api/auth/mfa` (`action = setup`) requires a valid session-bound CSRF token. If MFA is already enabled on the account, initiating replacement requires `currentPassword` AND a valid current 6-digit TOTP code (or unused recovery code); active `users.mfa_secret_encrypted` is never overwritten until the new secret is verified via `action = enable`.
- **Replay Prevention:** `consumeTotpSliceIfFresh()` persists the highest accepted 30-second TOTP slice per user (`auth_accounts`, `provider = 'mfa_replay'`) and rejects any code from the same or earlier time slice within the ±1 window (`MFA_FAILED`, reason `totp_replay_blocked`).
- **Brute-Force Throttling:** Login MFA verification is throttled to 5 attempts per 15 minutes per user ID (`auth_mfa_verify`), returning `429` with `Retry-After` on abuse.
- **Single-Use Recovery Codes:** 8 random `XXXX-XXXX` recovery codes are issued upon enrollment/replacement, displayed **ONCE**, and stored strictly as HMAC-SHA256 digests (`auth_accounts`, `provider = 'mfa_recovery'`). Consuming a code atomically deletes its row (`MFA_RECOVERY_USED`); any reuse attempt fails with `401`.
- **Superadmin-Assisted Audited Recovery:** A `SUPERADMIN` may reset MFA for **another** locked-out staff user via `PATCH /api/admin/users` (`resetMfa: true`, CSRF-protected), which clears MFA columns/recovery codes, revokes all target sessions, logs `MFA_ADMIN_RESET`, and forces fresh enrollment on next login. Self-reset is explicitly blocked (`400`).

