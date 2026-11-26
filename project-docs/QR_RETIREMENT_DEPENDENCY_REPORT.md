# QR Retirement & Dependency Report — Purple Festival Gujarat 2027 (Decision D-01)

**Decision Reference:** `D-01` (Approved by Project Owner for Phase 2)  
**Date:** 2026-11-26  
**Objective:** Discontinue new participant event-entry QR credential issuance and external QR image leaks while preserving all non-entry dependencies and historical records.

---

## 1. Retired & Contained Participant Entry QR Flows

| Component / File | Phase 2 Action Taken | Verified Behavior |
|---|---|---|
| [includes/mailer.php](file:///d:/purplefestgujarat/includes/mailer.php) | Removed `https://api.qrserver.com/v1/create-qr-code/...` completely; replaced `sendRegistrationQrPassEmail()` body with `sendRegistrationConfirmationEmail()` containing Registration ID + optional duty schedule; scrubbed `rawToken` from all metadata & `notification_queue.payload` | Verified in `T2-17`: 0 `qrserver.com` refs, 0 `PFG27.` tokens in email sink or DB queue |
| [api/registrations/index.php](file:///d:/purplefestgujarat/api/registrations/index.php) | Stopped calling `issueCredentialForRegistration()`; sends `sendRegistrationConfirmationEmail()` with Registration ID (`qrPass: null`) | Verified in `T2-17`: 0 new `qr_credentials` rows created on registration |
| [api/admin/registrations.php](file:///d:/purplefestgujarat/api/admin/registrations.php) | Stopped auto-issuing QR credentials on `APPROVED`/`CONFIRMED`; returns **`410 Gone`** on `credentialAction=ISSUE` / `REISSUE`; sends clean confirmation email on approval; removed raw `qrPayload` reconstruction from admin detail GET | Verified in `T2-19`: `credentialAction=ISSUE` -> `410 Gone` |
| [api/qr/pass.php](file:///d:/purplefestgujarat/api/qr/pass.php) | Returns **`410 Gone`** on `POST`/`PATCH` `issue` & `reissue` (CSRF + MFA gated); preserves read-only historical lookup (`GET`) and authorized legacy revocation (`revoke`) | Verified in `T2-19`: `action=reissue` -> `410 Gone` |
| [api/qr/verify.php](file:///d:/purplefestgujarat/api/qr/verify.php) | Enforces `requireRole(['RGST','ADMIN','SUPERADMIN'])`, MFA gate, CSRF, and returns **`410 Gone`** (`QR_ENTRY_DISCONTINUED`) | Verified in `T2-19`: `POST /api/qr/verify` -> `410 Gone` |
| [api/checkins/confirm.php](file:///d:/purplefestgujarat/api/checkins/confirm.php) | Enforces `requireRole(['RGST','ADMIN','SUPERADMIN'])`, MFA gate, CSRF, and returns **`410 Gone`** (`QR_CHECKIN_DISCONTINUED`) | Verified in `T2-19`: `POST /api/checkins/confirm` -> `410 Gone` |
| [assets/js/admin-portal.js](file:///d:/purplefestgujarat/assets/js/admin-portal.js) | Removed `"Reissue QR Pass"` button and `"(Auto-issues QR Pass)"` copy; retained read-only credential history & legacy revocation | Verified in source & UI inspection |
| [rgst/index.php](file:///d:/purplefestgujarat/rgst/index.php) | Added explicit D-01 deprecation banner explaining QR scanning is discontinued and manual checkbox attendance is scheduled for Phase 9 | Verified in `T2-19`: HTTP `200` with banner for RGST operator |
| [apply/index.php](file:///d:/purplefestgujarat/apply/index.php) | Updated Attendee badge to `"Instant Registration ID"` and removed unused `qrcode-gen.js` script tag from public `/apply` | Verified in `T2-15` & `A-02` |

## 2. Explicitly Preserved Dependencies (NOT Removed)

| Dependency | Location | Preservation Status & Verification |
|---|---|---|
| **Human-Readable Registration IDs** | `generateUniqueRegistrationCode()` in [includes/qr-service.php](file:///d:/purplefestgujarat/includes/qr-service.php) (to be migrated to atomic DB sequence in Phase 3) | **PRESERVED & VERIFIED** (`T2-17` generated `PFG-2027-000001-...`) |
| **Admin TOTP MFA Enrollment QR Generator** | [assets/js/qrcode-gen.js](file:///d:/purplefestgujarat/assets/js/qrcode-gen.js) loaded on `/admin` & `/dashboard` | **PRESERVED & VERIFIED** (`T2-19`, `A-07` HTTP `200`) |
| **Historical Database Tables & Records** | `qr_credentials`, `credential_events`, `checkins`, `registrations` | **PRESERVED INTACT** (zero tables dropped; zero historical rows deleted) |
| **Legacy Printable Pass Route (Read-Only)** | [dashboard/pass-print.php](file:///d:/purplefestgujarat/dashboard/pass-print.php) | **PRESERVED READ-ONLY** with clear D-01 notice when no legacy pass exists |
| **Public Static Website / Brochure QR Links** | Public website assets & informational materials | **UNTOUCHED** |

## 3. Remaining Phase 3 & Phase 9 Decoupling Tasks
- **Phase 3:** Extract `generateUniqueRegistrationCode()` out of `includes/qr-service.php` into an atomic DB sequence service (`PFG-2027-000001`) independent of QR code infrastructure.
- **Phase 9:** Retire `assets/js/html5-qrcode.min.js` and rebuild `/rgst` around accessible manual participant search + checkbox attendance keyed on `(registration_id, festival_day)` instead of `credential_id`.

