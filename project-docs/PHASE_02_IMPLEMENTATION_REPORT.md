# Phase 2 Implementation Report — Core Security, Authorization & Architectural Decoupling

**Date:** 2026-11-26  
**Runtime Verified:** Native XAMPP `PHP 8.2.12`, `MariaDB 10.4.32`, `Apache 2.4.58` (`127.0.0.1`)  
**Deployment Status:** Local verification complete; **NOT deployed to Bluehost staging or production** (awaiting separate authorization).

---

## 1. Complete Inventory of Modified & Created Files

### Created Files
- [001_phase2_security_controls.sql](file:///d:/purplefestgujarat/database/migrations/001_phase2_security_controls.sql) — Additive MySQL/MariaDB-compatible migration creating `security_rate_limits` for persistent DB-backed rate limiting without Redis.
- [001_phase2_security_controls_rollback.sql](file:///d:/purplefestgujarat/database/migrations/001_phase2_security_controls_rollback.sql) — Clean rollback dropping only `security_rate_limits`.
- [phase2_security_test.php](file:///d:/purplefestgujarat/tests/phase2_security_test.php) — 21-test live end-to-end automated security test suite covering auth, lockout, rate limiting, MFA lifecycle, CSRF, 6-role RBAC matrix, XSS, URL validation, QR retirement, privacy, CSV neutralization, and audit pagination.
- [apache_verification.php](file:///d:/purplefestgujarat/tests/apache_verification.php) — Real XAMPP Apache `2.4.58` verification harness validating `.htaccess` rewrites, 403 blocks, security headers, and PHP 8.2 execution.

### Modified Core Libraries & Deployment Config
- [config.php](file:///d:/purplefestgujarat/includes/config.php) — Added `MFA_ENCRYPTION_KEY` / `DATA_ENCRYPTION_KEY` (with HKDF-style domain-separated fallback + dedicated-key status flags), `pfgSafeJsonForScript()`, `pfgValidateSafeUrl()`, `pfgValidateSafeRedirect()`, `pfgEncryptValue()` / `pfgDecryptValue()` (AES-256-GCM with 12-byte random IV & 16-byte tag), and `pfgScrubSensitiveArray()`.
- [database.php](file:///d:/purplefestgujarat/includes/database.php) — Added idempotent `ensurePhase2SecurityTables()` for `security_rate_limits` and secret scrubbing in `queueNotification()`.
- [auth.php](file:///d:/purplefestgujarat/includes/auth.php) — Rebuilt CSRF to be strictly session-bound (zero `'pfg27-anon-csrf'` fallback) with `requireCsrfToken()`; added persistent DB rate limiter (`pfgCheckRateLimit`, `pfgRequireRateLimit`, `pfgResetRateLimit`); upgraded TOTP MFA to 160-bit (32 Base32 chars), AES-256-GCM encryption, slice replay prevention (`consumeTotpSliceIfFresh`), single-use hashed recovery codes (`generateMfaRecoveryCodes`, `consumeMfaRecoveryCode`), and mandatory D-20 staff MFA gate (`enforceStaffMfaGate`); added session fixation mitigation, `revokeAllUserSessions()`, `pfgIsHttpsRequest()` (ignoring spoofable `X-Forwarded-Proto`), `pfgDummyPasswordVerify()`, and secret-scrubbing `logAudit()`.
- [mailer.php](file:///d:/purplefestgujarat/includes/mailer.php) — Completely removed `api.qrserver.com` and QR entry pass image/token delivery per **D-01**; introduced `sendRegistrationConfirmationEmail()` (clean Registration ID + optional duty schedule); scrubbed sensitive tokens from `notification_queue.payload` across all mail drivers.
- [.cpanel.yml](file:///d:/purplefestgujarat/.cpanel.yml) — Guarded destructive `rm -rf` and `cp -R` tasks with explicit non-empty & exact `$DEPLOYPATH` verification.

### Modified Authentication & Account Endpoints
- [login.php (API)](file:///d:/purplefestgujarat/api/auth/login.php) — Persistent IP + identifier rate limits, constant-time dummy bcrypt check, uniform `401` errors, sticky lockout counter reset after expiry, TOTP replay + single-use recovery verification, and mandatory D-20 MFA enrollment-only session issuance for `SUPERADMIN`/`ADMIN`.
- [mfa.php](file:///d:/purplefestgujarat/api/auth/mfa.php) — Made `GET` strictly read-only; moved setup/replacement to `POST` (`action=setup`) with CSRF + re-auth (`currentPassword` + valid current TOTP/recovery code) when already enabled; stores only `enc:v1:` ciphertext in `mfa_secret_encrypted` with `mfa_secret = NULL`; blocks `disable` (`403`) for `SUPERADMIN`/`ADMIN`; returns 8 single-use recovery codes once.
- [logout.php](file:///d:/purplefestgujarat/api/auth/logout.php) — Rejects `GET` (`405`), requires `POST` + `requireCsrfToken()`, sanitizes optional redirect via `pfgValidateSafeRedirect()`.
- [signup.php](file:///d:/purplefestgujarat/api/auth/signup.php) — Rate-limited and contained with `403` pending Phase 5 verified participant onboarding.
- [password-reset.php](file:///d:/purplefestgujarat/api/auth/password-reset.php) — Added IP + email rate limits, eliminated plaintext `resetUrl` from `notification_queue.payload`, and revokes all user sessions on completion.
- [me.php](file:///d:/purplefestgujarat/api/auth/me.php) — Exposes session-bound `csrfToken` alongside authenticated user state.
- [profile.php](file:///d:/purplefestgujarat/api/account/profile.php) — Enforces `enforceStaffMfaGate`, `requireCsrfToken`, and revokes other sessions on password change.
- [accommodations.php (Account)](file:///d:/purplefestgujarat/api/account/accommodations.php) — Enforces `enforceStaffMfaGate`, `requireCsrfToken`, and AES-256-GCM encrypts/decrypts free-text details.

### Modified Admin, Registration, QR & Portal Files
- [audit-logs.php](file:///d:/purplefestgujarat/api/admin/audit-logs.php) — Enforces `AUDIT_LOGS_READ` (`FND-02`) and true SQL `COUNT(*)` + `LIMIT/OFFSET` pagination (`FND-28`).
- [cms.php](file:///d:/purplefestgujarat/api/admin/cms.php) — Enforces `CMS_MANAGE` (`FND-02`), CSRF on mutations, safe URL validation (`websiteUrl`, `logoUrl`, `media.url`), and `CMS_MEDIA_UPDATED` audit logging.
- [overview.php](file:///d:/purplefestgujarat/api/admin/overview.php) — Permission-gates `accommodationSummary`, `paymentReconciliation`, and `attendance*`/`recentCheckins`.
- [accommodations.php (Admin)](file:///d:/purplefestgujarat/api/admin/accommodations.php) — Enforces CSRF on writes, decrypts/encrypts sensitive notes, and logs `SENSITIVE_DATA_READ`.
- [coupons.php](file:///d:/purplefestgujarat/api/admin/coupons.php) & [validate.php](file:///d:/purplefestgujarat/api/coupons/validate.php) — Enforces CSRF on admin writes and persistent IP rate limiting on public validation.
- [users.php](file:///d:/purplefestgujarat/api/admin/users.php) — Enforces CSRF, blocks self-demotion and last-active-Superadmin removal, blocks admin `customPermissions` on `RGST`/`PARTICIPANT`, adds audited Superadmin-assisted MFA recovery (`resetMfa` for other users only), and revokes target sessions on password reset, deactivation, role change, or permission change.
- [registrations.php (Admin)](file:///d:/purplefestgujarat/api/admin/registrations.php) — Enforces `REGISTRATIONS_READ_ALL` on GET, granular write permissions & CSRF BEFORE any PATCH mutation, hides/blocks accommodation indicators without `accommodations.read`, neutralizes CSV formula injection (`'`), rejects `?export=xlsx` (`400`), logs `SENSITIVE_DATA_READ` & `REGISTRATIONS_EXPORTED`, stops QR issuance/reissuance (`410 Gone`), and sends clean confirmation email on approval.
- [index.php (Public Registrations)](file:///d:/purplefestgujarat/api/registrations/index.php) — Enforces rate limiting, blocks unauthenticated attachment to existing emails (`409`, zero `existingRegistrationCode` leak), validates SLI `videoDemoUrl` (`http`/`https` only), encrypts disability & accommodation free-text, strips sensitive fields from `registration_responses.answers`, and stops calling `issueCredentialForRegistration()` per D-01.
- [pass.php](file:///d:/purplefestgujarat/api/qr/pass.php), [verify.php](file:///d:/purplefestgujarat/api/qr/verify.php), [confirm.php](file:///d:/purplefestgujarat/api/checkins/confirm.php), [index.php (Checkins)](file:///d:/purplefestgujarat/api/checkins/index.php) — Enforces staff MFA gate + CSRF; returns `410 Gone` on retired QR issue/reissue/verify/confirm endpoints while preserving historical read-only data & legacy revocation.
- [login.php](file:///d:/purplefestgujarat/login.php), [apply/index.php](file:///d:/purplefestgujarat/apply/index.php), [admin/index.php](file:///d:/purplefestgujarat/admin/index.php), [admin-portal.js](file:///d:/purplefestgujarat/assets/js/admin-portal.js), [dashboard/index.php](file:///d:/purplefestgujarat/dashboard/index.php), [dashboard/pass-print.php](file:///d:/purplefestgujarat/dashboard/pass-print.php), [rgst/index.php](file:///d:/purplefestgujarat/rgst/index.php) — Fixed `dashboard/index.php` fatal TypeError (`FND-13`), replaced inline `json_encode` with `pfgSafeJsonForScript()`, injected `X-CSRF-Token` across all client mutations, added `safeHref()` in admin portal, fixed CMS/Audit tab visibility, implemented mandatory MFA lock/enrollment/replacement/recovery-code UI, removed new QR pass controls, and added D-01 transition notice to `/rgst`.

## 2. Verification Summary

- **Native PHP + MariaDB Automated Suite (`tests/run.php`):** **32 / 32 PASSED** (`11` Phase 1 regression checks + `21` Phase 2 security & authorization checks; 49 PHP files linted with 0 syntax errors).
- **Real Native XAMPP Apache `2.4.58` Verification (`tests/apache_verification.php`):** **26 / 26 PASSED** (all extensionless rewrites, static assets, 10 `.htaccess` 403 blocks, `/dashboard` 302 redirect, and all 5 Apache security headers).

