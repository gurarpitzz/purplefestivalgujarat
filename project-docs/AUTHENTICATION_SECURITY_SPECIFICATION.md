# Authentication & Session Security Specification — Purple Festival Gujarat 2027

**Effective Phase:** Phase 2 (Hardened Staff Authentication & Foundation for Phase 5 Participant Onboarding)  
**Authoritative Implementation:** [includes/auth.php](file:///d:/purplefestgujarat/includes/auth.php), [api/auth/login.php](file:///d:/purplefestgujarat/api/auth/login.php), [api/auth/logout.php](file:///d:/purplefestgujarat/api/auth/logout.php), [api/auth/password-reset.php](file:///d:/purplefestgujarat/api/auth/password-reset.php), [api/auth/signup.php](file:///d:/purplefestgujarat/api/auth/signup.php)

---

## 1. Password & Credential Handling
- Password hashing uses PHP native `PASSWORD_BCRYPT` (`cost = 12`).
- Unknown usernames, deactivated accounts, and non-staff accounts execute `pfgDummyPasswordVerify()` (constant-cost bcrypt) before returning a uniform `401 Invalid username or password.` response.
- Account status, lockout expiry timestamps (`lockedUntil`), and role classification are never disclosed prior to valid password verification.

## 2. Account Lockout & Persistent Bluehost-Compatible Rate Limiting
- **Account Lockout:** 5 consecutive failed password attempts set `locked_until = NOW() + 15 minutes` and write `LOGIN_ACCOUNT_LOCKED` to `audit_logs`.
- **Sticky Lockout Fix:** Once `locked_until <= NOW()`, the next failed attempt resets `failed_login_attempts` cleanly to `1` instead of incrementing past `5` and immediately re-locking.
- **Persistent Rate Limiter (`security_rate_limits`):** MySQL/MariaDB-backed fixed-window rate limiter with `Retry-After` and HTTP `429`:
  - `auth_login_ip`: 30 requests / 15 min per client IP.
  - `auth_login_ident`: 12 requests / 15 min per normalized username/email (reset on valid login).
  - `auth_mfa_verify`: 5 MFA verification attempts / 15 min per user ID.
  - `auth_mfa_ops`: 15 MFA management requests / 15 min per user ID.
  - `auth_pwreset_ip` / `auth_pwreset_email`: 10/IP and 5/email per 15 min.
  - `public_registration_ip`: 25 / 15 min per IP; `coupon_validate_ip`: 30 / 15 min per IP.

## 3. Session Lifecycle & Fixation Protection
- Opaque 256-bit random session token issued in `pfg_session` cookie (`HttpOnly`, `SameSite=Lax`, `Secure` derived strictly from `APP_URL` `https://` or `$_SERVER['HTTPS']` — never from spoofable `HTTP_X_FORWARDED_PROTO`).
- Database stores only `hash_hmac('sha256', $rawToken, AUTH_SECRET)` in `sessions.token_hash`.
- **Fixation Mitigation:** `createSession()` revokes any pre-existing `pfg_session` cookie in `sessions` and invokes `session_regenerate_id(true)`.
- **Mandatory Bulk Session Revocation (`revokeAllUserSessions`):** Triggered automatically on:
  - Password change (`api/account/profile.php` — keeps current session, revokes all others).
  - Password reset completion (`api/auth/password-reset.php` — revokes all sessions).
  - Superadmin password reset, deactivation, role change, custom permission update, or MFA reset (`api/admin/users.php` — revokes all target sessions).
  - MFA activation / replacement (`api/auth/mfa.php` — keeps current session, revokes all others).

## 4. Session-Bound CSRF Protection
- The legacy static `'pfg27-anon-csrf'` fallback has been permanently removed.
- Authenticated sessions derive `X-CSRF-Token` via `hash_hmac('sha256', 'pfg27:csrf:v2:' . hashToken($rawCookie), AUTH_SECRET)`, ensuring automatic invalidation upon logout or session rotation.
- Unauthenticated browser sessions use a random 256-bit token stored in `$_SESSION['pfg_csrf_token']`.
- `requireCsrfToken()` runs BEFORE any database mutation on all authenticated `POST`, `PATCH`, `PUT`, and `DELETE` endpoints; failures log `CSRF_REJECTED` and return HTTP `403`.

## 5. Logout & Contained Endpoints
- `GET /api/auth/logout` is rejected with `405 Method Not Allowed` (`Allow: POST`).
- `POST /api/auth/logout` requires a valid session-bound CSRF token, revokes the database session, clears cookies, destroys PHP session state, and validates any optional redirect strictly via `pfgValidateSafeRedirect()`.
- `POST /api/auth/signup` is safely contained (`403`) pending Phase 5 verified participant onboarding.
- `POST /api/auth/password-reset` delivers reset emails via `sendFestivalEmail()` without persisting plaintext `resetUrl` or `rawToken` in `notification_queue.payload`.

