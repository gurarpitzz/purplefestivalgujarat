# Phase 2 Plan — Core Security, Authorization and Architectural Decoupling

**Project:** Purple Festival Gujarat 2027  
**Phase:** Phase 2 (Sub-Phases 2A through 2K + Native Apache Verification)  
**Baseline Commit:** `748036d` (Phase 1 verified locally: 11/11 PASS)  
**Date:** 2026-11-26  
**Stack:** Native PHP 8 (`8.2.12`), MariaDB (`10.4.32`), PDO, Vanilla JS, HTML5, CSS3, Apache (`2.4.58`), Bluehost cPanel  

---

## 1. Phase 2 Scope & Sub-Phases

| Sub-Phase | Objective | Target Audit Findings |
|---|---|---|
| **2A** | Session-bound CSRF protection across all authenticated mutating endpoints; remove static `'pfg27-anon-csrf'` fallback | `FND-01` |
| **2B** | Server-side RBAC enforcement on CMS, Audit Logs, Overview, Registrations, and Superadmin User Management (incl. anti-self-demotion & last-Superadmin guard) | `FND-02`, `FND-16`, `FND-32` |
| **2C** | RFC 6238 TOTP MFA reconstruction per **D-20**: mandatory for `SUPERADMIN`/`ADMIN`, optional for `RGST`; 160-bit secrets encrypted with AES-256-GCM (`mfa_secret = NULL`); read-only `GET /api/auth/mfa`; re-auth on replacement; replay guard; single-use recovery codes | `FND-03` |
| **2D** | Authentication hardening: persistent Bluehost-compatible DB rate limiter (`security_rate_limits`), anti-enumeration constant-time login, expired lockout reset, POST-only CSRF-protected logout with safe redirect allowlist, session fixation & bulk session revocation | `FND-09`, `FND-26`, `FND-32` |
| **2E** | Script-context JSON escaping (`pfgSafeJsonForScript` with `JSON_HEX_TAG|AMP|APOS|QUOT`) and strict `http`/`https` URL validation across SLI `videoDemoUrl` and CMS URLs + frontend `safeHref()` | `FND-04`, `FND-05` |
| **2F** | Participant QR retirement & security containment per **D-01**: remove `api.qrserver.com`, stop issuing new QR credentials on registration/approval, return `410 Gone` on retired QR endpoints while preserving Registration IDs, historical DB tables, and `qrcode-gen.js` for MFA | `FND-06` |
| **2G** | Participant identity security: block unauthenticated registration attachment to existing participant or staff emails (`409` without leaking `existingRegistrationCode`); contain orphaned `/api/auth/signup` (`403`) pending Phase 5 | `FND-07` |
| **2H** | Sensitive disability & accommodation data protection: AES-256-GCM encryption on new free-text writes, strict `accommodations.read` gating, omit sensitive free-text from `registration_responses.answers`, `SENSITIVE_DATA_READ` audit logging | `FND-11` |
| **2I** | Fix `/dashboard` fatal HTTP 500 `TypeError` (`requireAuth()` signature bug), enforce staff MFA gate, add CSRF headers and safe JSON encoding | `FND-13` |
| **2J** | CSV export formula-injection neutralization (`'`), reject fake `?export=xlsx` (`400`), enforce `registrations.export` + `accommodations.read` and log `REGISTRATIONS_EXPORTED` | `FND-25` |
| **2K** | Security audit logging: replace hardcoded `LIMIT 500` in `/api/admin/audit-logs` with true SQL `COUNT(*)` + `LIMIT/OFFSET` pagination, audit CMS media updates and all security events, auto-scrub secrets from `audit_logs.metadata` | `FND-28` |
| **Sec 15** | Harden `.cpanel.yml` `$DEPLOYPATH` guard and run real native XAMPP Apache `2.4.58` verification of `.htaccess` rewrites, 403 blocks, and security headers | `FND-10`, `FND-12`, `FND-36` |

## 2. Explicit Out-of-Scope Boundaries (Deferred to Later Phases)

- Participant account signup, email verification, and participant login UI (Phase 5)
- Final 3-category `/apply` form reconstruction (Phase 6)
- Full participant dashboard redesign (Phase 10)
- Custom operational role profile management UI (Phase 8)
- Manual checkbox Registration Desk attendance portal (Phase 9)
- Atomic registration numbering replacement & 6 duplicate-table consolidation (Phase 3)
- Full cPanel cron email worker & `notification_queue` schema redesign (Phase 4)
- Bluehost staging/production deployment (explicitly gated behind separate owner authorization)

## 3. Rollback & Data Preservation Plan

- The only database schema addition in Phase 2 is the additive, standalone `security_rate_limits` table (`database/migrations/001_phase2_security_controls.sql`), with an exact rollback script (`database/migrations/001_phase2_security_controls_rollback.sql`).
- Zero existing tables (`registrations`, `users`, `qr_credentials`, `credential_events`, `checkins`, `accommodation_requests`, `participant_disability_disclosures`) are dropped or destructively altered.
- `pfgDecryptValue()` transparently reads both new `enc:v1:` AES-256-GCM ciphertexts and legacy plaintext rows so historical records remain readable without mandatory Phase 2 backfill.

