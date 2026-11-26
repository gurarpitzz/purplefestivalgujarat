# Phase 16 Conditional & Optional Features Classification Matrix (§38)

**Authority:** Section 38 & Section 49 of the Master Implementation Directive  
**Rule:** Every Phase 16 conditional, optional, or policy-dependent module is classified below using **only** the six mandated governance labels:
- `APPROVED AND IMPLEMENTED`
- `APPROVED BUT PENDING`
- `CONFIGURABLE AND INACTIVE`
- `DEFERRED`
- `MANAGED OUTSIDE WEBSITE`
- `REQUIRES OWNER DECISION`

---

## Complete 20-Module Phase 16 Classification Ledger

| # | Phase 16 Module / Capability (§38) | Related Decision(s) | Mandated Classification Label | Implementation / Technical State & Operational Handling |
|---:|---|---|---|---|
| **1** | **Cross-Category Shared Accessibility & Accommodation Requests (Zero Medical Diagnosis Required)** | `CAD-07`, `D-08` | `APPROVED AND IMPLEMENTED` | Uniformly integrated across `ATTENDEE`, `VOLUNTEER`, and `SLI` (`includes/form-components/shared-accessibility.php`, `assets/js/shared-accessibility.js`). Supports 13 accommodation types + AES-256-GCM encrypted free-text details (`enc:v1:`) with or without optional RPwD disability disclosure. |
| **2** | **Superadmin-Configurable Operational Permission Profiles within `ADMIN` Tier** | `CAD-04`, Phase 8 | `APPROVED AND IMPLEMENTED` | Implemented in Migrations `009` & `011`, `includes/auth.php`, and `/api/admin/permission-profiles`. Seeds 7 least-privilege operational profiles (`registration_manager`, `volunteer_coordinator`, `sli_coordinator`, `accessibility_coordinator`, `programme_coordinator`, `content_manager`, `communications_manager`) assignable strictly to `ADMIN` accounts while forbidding `admin_accounts.manage`, `settings.manage`, and `audit_logs.read`. |
| **3** | **Structured Programme, Venue Confirmation & Volunteer/SLI Duty Scheduling (Overlap Detection & 2-SLI Pairing)** | `D-18`, Phase 12 | `APPROVED AND IMPLEMENTED` | Implemented via Migration `010` (`duty_assignments`), `/api/admin/venues`, `/api/admin/programme`, and `/api/admin/assignments`. Enforces `APPROVED` applicant gate (`400`), shift time-overlap detection (`409 DUTY_OVERLAP`) with audited override, 2-interpreter rotation pairing (`pairing_group_id`), and live Participant Dashboard visibility. |
| **4** | **Decoupled In-Application Participant Notification Inbox** | `CAD-15`, Phases 4O, 10, 13 | `APPROVED AND IMPLEMENTED` | Implemented via Migration `006` (`in_app_notifications`), `pfgCreateInAppNotification()`, `/api/account/notifications`, and the `/dashboard` Notifications tab. Populated atomically inside business transactions independently of external email delivery. |
| **5** | **Verified-Email Legacy Account & Registration Claim Flow** | `D-21`, Phase 5 | `APPROVED AND IMPLEMENTED` | Implemented via Migration `007` (`legacy_registration_claims`), `/api/auth/claim-legacy`, and the `/dashboard` claim banner. Allows verified non-staff participants to claim pre-Phase-5 unlinked registrations matching their exact lowercased email with `UNIQUE(registration_id)` protection. |
| **6** | **Consolidated Outstation Travel, Lodging, Escort/Guardian & Dietary Logistics inside `ATTENDEE`** | `D-06`, `D-07`, `D-08` | `APPROVED AND IMPLEMENTED` | Implemented via Migration `008` (`attendee_details` and `accommodation_requests.escort_accompanying` / `escort_name_encrypted`) and `/apply/attendee`. Preserves all logistics fields from `Delegate Registration Form.docx` inside the primary `ATTENDEE` category while deactivating legacy category codes non-destructively. |
| **7** | **Para-Sports Athlete Interest & Event Selection Hook inside `ATTENDEE`** | `D-24` | `APPROVED AND IMPLEMENTED` | `attendee_details` captures `para_sports_athlete` (`0`/`1`), `para_sports_events` JSON array, and `para_sports_other` inside `/apply/attendee` and exports them via Admin CSV export for the sports committee without creating a 4th primary registration category. |
| **8** | **Live Resend Production HTTPS Email Transport & Svix Webhook Sync** | `CAD-15`, `D-22`, `BLK-01` | `APPROVED BUT PENDING` | Code (`includes/notification-service.php`), CLI cron worker (`scripts/process-notification-queue.php`), and Svix HMAC-SHA256 webhook receiver (`api/webhooks/resend.php`) are built and `PROVIDER SIMULATION VERIFIED` locally. Live external activation is `CONFIGURATION BLOCKED` pending `D-22` domain DNS (SPF/DKIM/DMARC), production `RESEND_API_KEY`, and `RESEND_WEBHOOK_SECRET`. |
| **9** | **Paid Attendee Registration & Payment Gateway Checkout (Razorpay / UPI / GST)** | `D-05` | `CONFIGURABLE AND INACTIVE` | Canonical schema (`payment_orders`, `payment_transactions`, `refunds`) and `system_settings` flag (`payments.enabled = false`) exist; public registration operates on the default **Free (`₹0`)** path (`CONFIRMED` immediately on submission). No live payment gateway checkout is active unless the Project Owner approves a paid fee and GST policy under `D-05`. |
| **10** | **Fee Concessions, Waivers & Coupon Redemption Engine** | `D-07`, `D-05` | `CONFIGURABLE AND INACTIVE` | Canonical `coupons` and `coupon_redemptions` tables plus `/api/coupons/validate` and `/api/admin/coupons` are implemented and RBAC-protected (`coupons.manage`), but remain inactive in the public registration flow while Attendee registration is Free (`₹0`). |
| **11** | **Assisted Walk-In Desk Registration Creation (`registrations.assisted_create`)** | `D-04` | `CONFIGURABLE AND INACTIVE` | By default (`D-04`), `/rgst` is strictly a check-in attendance portal (`RGST` operators cannot create or approve registrations). Walk-in visitors self-register on mobile/kiosk at `/apply` and are immediately searchable at `/rgst`. |
| **12** | **Session-Level / Multi-Checkpoint Attendance Scope Granularity** | `D-02` | `CONFIGURABLE AND INACTIVE` | `attendance_records` supports configurable allowlisted `attendance_scope` values via `system_settings` (`attendance.scope` / `attendance.mode`). Active launch configuration uses daily festival attendance (`FESTIVAL_DAY` across `2027-01-21`, `2027-01-22`, `2027-01-23`). |
| **13** | **Supporting Document & Certificate File Uploads (Volunteer / SLI Resume & RCI Certificate Files)** | `D-15` | `DEFERRED` | Intentionally deferred per `D-15`. Volunteer and SLI forms collect optional RCI `CRR Number` text and a validated `https://` video demo URL (`pfgValidateSafeUrl`), avoiding file-upload malware/webroot exposure and Bluehost disk quota exhaustion. |
| **14** | **Automated SMS & WhatsApp Notification Delivery (India DLT Templates)** | `D-11` | `DEFERRED` | Intentionally deferred per `D-11`. Forms capture WhatsApp/mobile contact numbers and `notification_queue.channel` is extensible, but live notifications use Email (Resend) + In-App Dashboard alerts until an SMS/WhatsApp vendor and India DLT templates are procured. |
| **15** | **Automated Volunteer / SLI Training LMS & PDF Certificate Generator** | `D-19` | `DEFERRED` | Intentionally deferred per `D-19`. Coordinators track duty status (`ASSIGNED` -> `COMPLETED`) in `/admin`, while certificate issuance remains an organizer-approved post-event process rather than an automated PDF generator tied to a single attendance checkbox. |
| **16** | **Multilingual UI Localization (Gujarati, Hindi, Easy-Read & Embedded ISL Video Translations)** | `D-26` | `DEFERRED` | Platform launches in WCAG 2.2 AA verified English with full `utf8mb4` Gujarati/Hindi database and email rendering support plus `preferred_language` capture. Full UI translations and ISL videos are deferred until organizer-approved human translations are supplied (machine translation is prohibited per `D-26`). |
| **17** | **Post-Festival DPDP Automated Data Retention & Sensitive Disclosure Purge Schedule** | `D-27` | `REQUIRES OWNER DECISION` | Technical safeguards (`AES-256-GCM` encryption at rest, `SENSITIVE_DATA_READ` audit logging, `privacy_consent_records`, and `data_retention_requests` table) are implemented. The exact post-festival retention duration (e.g., purging sensitive disability disclosures 90 days post-event while retaining anonymized aggregate counts) awaits Project Owner decision `D-27`. |
| **18** | **B2B Exhibitor, Purple Fair Stall, Corporate Pavilion & Sponsorship Booking Workflows** | `D-25` | `MANAGED OUTSIDE WEBSITE` | Out of participant portal scope per `D-25`. Public website displays informational programme details and Secretariat contact information (Brochure p.6); B2B stall allocation, MoUs, and sponsorship invoicing are managed offline by the Festival Secretariat. |
| **19** | **Disability Futures / Academic Conference Paper Submission & Peer-Review System** | `D-23`, `D-25` | `MANAGED OUTSIDE WEBSITE` | Managed offline by the academic conference committee (Praangan / CDSA-TISS / MSU). Confirmed sessions are published in the public `/programme` schedule once finalized. |
| **20** | **Offline Para-Sports Clinical/Functional Classification & Tournament Draw Management** | `D-24` | `MANAGED OUTSIDE WEBSITE` | While `/apply/attendee` captures athlete interest and event checklists (Module #7), clinical/functional classification, age/category verification, and match brackets are managed offline by the MSU Department of Physical Education sports team per `Delegate Registration Form.docx` Section C. |

---

## Summary Counts by Mandated Classification Label

| Mandated Classification Label | Module Count | Modules |
|---|---:|---|
| `APPROVED AND IMPLEMENTED` | **7** | #1, #2, #3, #4, #5, #6, #7 |
| `APPROVED BUT PENDING` | **1** | #8 (Live Resend DNS/API & Svix webhook activation — `CONFIGURATION BLOCKED`) |
| `CONFIGURABLE AND INACTIVE` | **4** | #9, #10, #11, #12 |
| `DEFERRED` | **4** | #13, #14, #15, #16 |
| `REQUIRES OWNER DECISION` | **1** | #17 (`D-27` post-festival retention window) |
| `MANAGED OUTSIDE WEBSITE` | **3** | #18, #19, #20 |
| **TOTAL CLASSIFIED MODULES** | **20** | **100% of §38 conditional modules accounted for** |
