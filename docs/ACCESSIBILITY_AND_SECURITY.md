# Accessibility & Security Architecture — Purple Festival Gujarat 2027

**Scope**: Public Festival Website, Registration Wizard, Participant Dashboard, RGST Scanner Portal, and Admin/Superadmin Portal  
**Standards**: WCAG 2.2 Level AA, Rights of Persons with Disabilities (RPwD) Act, 2016 (India), Digital Personal Data Protection (DPDP) Act, 2023 (India), OWASP ASVS Level 2

---

## 1. WCAG 2.2 Level AA Conformance Checklist

| Principle | Criterion | Status | Implementation in Platform |
|---|---|---|---|
| **Perceivable** | **1.1.1 Non-text Content** | Implemented | All images and partner logos carry descriptive `alt` text (`media_assets.alt_text`, `organizations.logo_alt_text`); decorative icons use `aria-hidden="true"`. |
| **Perceivable** | **1.3.1 Info and Relationships** | Implemented | Semantic HTML5 landmarks (`<header>`, `<nav>`, `<main id="main-content">`, `<footer>`), strict single-`<h1>` heading hierarchy, associated `<label htmlFor>` and `aria-describedby` hints. |
| **Perceivable** | **1.4.3 Contrast (Minimum)** | Implemented | Charcoal (`#17151C`) + Ivory (`#F9F7FC`) and Dusty Purple (`#59436D`) palette engineered for ≥ 4.5:1 normal text and ≥ 3:1 large text. Plus **High Contrast** and **Yellow on Black** modes in `AccessibilityPanel`. |
| **Perceivable** | **1.4.4 Resize Text / 1.4.12 Text Spacing** | Implemented | Built-in Text Scale control (`90%` to `200%`) and **Increased Text & Line Spacing** toggle (`wideSpacing`) in `AccessibilityProvider` persisted to `localStorage`. |
| **Perceivable** | **1.4.10 Reflow** | Implemented | Responsive mobile-first layouts without horizontal scrolling at 320px CSS viewport width or 400% zoom. |
| **Operable** | **2.1.1 Keyboard / 2.1.2 No Keyboard Trap** | Implemented | Full keyboard operability across navigation, modals, accordions, and forms. `AccessibilityPanel` implements a proper `Tab`/`Shift+Tab` focus trap that exits cleanly on `Escape` and restores focus to `[data-a11y-trigger="true"]`. |
| **Operable** | **2.2.2 Pause, Stop, Hide / 2.3.3 Animation** | Implemented | `Preloader` provides a visible, keyboard-focusable **Skip Intro** button, responds to `Escape`, auto-bypasses when `prefers-reduced-motion: reduce` or user `reducedMotion` preference is active, and includes a hard 4.4s failsafe timeout. |
| **Operable** | **2.4.1 Bypass Blocks** | Implemented | Skip-to-main-content link targeting `#main-content` and structured ARIA landmarks. |
| **Operable** | **2.4.7 Focus Visible / 2.4.11 Focus Not Obscured** | Implemented | High-visibility focus rings (`:focus-visible`) with strong contrast across both dark and light surfaces; sticky header height accounted for in scroll margins. |
| **Operable** | **2.5.8 Target Size (Minimum)** | Implemented | `.touch-target` utility enforces minimum interactive hit areas (`≥ 44×44px` on primary controls, exceeding WCAG 2.2 AA's `24×24px` minimum). |
| **Understandable** | **3.1.1 Language of Page** | Implemented | `<html lang="en">` declared in root layout; clear, plain-language festival copy. |
| **Understandable** | **3.3.1 Error Identification / 3.3.3 Error Suggestion** | Implemented | Zod-backed client & server validation with inline, actionable error messages (e.g., phone format examples) announced to assistive tech. |
| **Understandable** | **3.3.8 Accessible Authentication (Minimum)** | Implemented | Paste-friendly password and TOTP fields (no CAPTCHA puzzles or cognitive-function tests blocking login). |
| **Robust** | **4.1.2 Name, Role, Value / 4.1.3 Status Messages** | Implemented | Proper `role="dialog"`, `aria-modal="true"`, `aria-expanded`, `aria-current="page"`, and polite live regions (`role="status" aria-live="polite"`) for preloader completion, scan outcomes, and form feedback. |

Automated regression enforcement is wired via Playwright + `@axe-core/playwright` (`tests/e2e/public-and-a11y.spec.ts`) checking `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, and `wcag22aa` tags with zero critical/serious violations permitted.

---

## 2. RPwD Act, 2016 Design Notes

The platform operationalizes the **Rights of Persons with Disabilities (RPwD) Act, 2016** across data modeling, UX, and festival operations:

1. **Complete 21-Disability Taxonomy**: All 21 specified disabilities under the RPwD Act Schedule (`RPWD_21_DISABILITIES` in `src/lib/constants.ts`, seeded into `disability_categories`) are represented with respectful, accurate descriptions and concrete festival accommodation examples.
2. **Non-Discriminatory Category Independence**: Disability status (`YES` / `NO` / `PREFER_NOT_TO_SAY`) is strictly decoupled from registration category. A person with a disability can apply as an **Attendee**, **Delegate**, **Volunteer**, **SLI Professional**, **Student**, **Professional**, or **Institutional Representative** without restriction.
3. **Optional Disclosure & Self-Description**: Disability disclosure is optional; applicants may select one or multiple categories, choose *Prefer not to say*, or provide a self-description and request reasonable accommodations directly (`accommodation_requests`) without needing to upload medical certificates.
4. **Accessible Digital & Physical Bridging**:
   - Digital QR passes include a plain-text Credential ID fallback (`PFG-2027-...`) so participants who cannot use a smartphone screen or QR image can check in effortlessly at any RGST desk.
   - Programme items explicitly surface accessibility services (`ISL Interpretation`, `Live Captioning (CART)`, `Audio Description`, `Braille Programme`, `Step-Free Access`, `Sensory Sanctuary Nearby`).
   - Seizure-safe motion defaults (no flashing strobes, reduced-motion bypass, and Reading Guide Band for neurodivergent/low-vision readers).

---

## 3. DPDP Act, 2023 Privacy Controls

Aligned with India's **Digital Personal Data Protection (DPDP) Act, 2023**:

- **Granular & Unbundled Consent**: Registration separates mandatory operational consents (`consentZeroTolerance`, `consentDataProcessing`) from optional publicity consent (`consentMediaPhotography`, defaulting to `false`). Declining photography/media consent never blocks festival registration.
- **Strict Data Minimization at Check-in**:
  - QR codes contain **zero PII** (opaque `PFG27.<nonce>.<sig>` token only).
  - `/api/qr/verify` and the `/rgst` reception interface **never query or return** disability disclosures, accommodation requests, email addresses, or phone numbers. Reception staff see only Name, Category, Credential Code, and Valid/Duplicate/Revoked status.
- **Purpose Limitation & Need-to-Know Access**:
  - Sensitive disability disclosures (`participant_disability_disclosures`) and accommodation requests (`accommodation_requests`) are protected by the `accommodations.read` and `accommodations.manage` permissions — inaccessible to `RGST` staff and public routes.
- **Consent-Gated Partner Publicity**: Partner organizations and logos default to `DRAFT` (`consentVerified: false`) and are withheld from public pages until written consent is confirmed by a Superadmin.
- **Auditability & Data Principal Rights**:
  - Sensitive admin actions, staff provisioning, credential revocations, and exports are logged in `audit_logs`.
  - Participants can view and update their profile and accommodation requests at any time through `/dashboard` (`/api/account/profile`, `/api/account/accommodations`), and request withdrawal/erasure through the Festival Secretariat.
- **Data Residency**: Production deployment targets India region (`ap-south-1`, Mumbai) for managed PostgreSQL and private object storage with TLS in transit and AES-256 encryption at rest.

---

## 4. Known Limitations & Pre-Launch Remediation Plan

Automated accessibility tools (`axe-core`) detect ~30–40% of WCAG issues. Before public launch for Purple Festival Gujarat 2027, the following gaps and verification steps **must** be completed:

| Area | Current Limitation | Pre-Launch Remediation Plan |
|---|---|---|
| **Human Usability Testing with Persons with Disabilities (Mandatory)** | Automated Playwright/axe-core checks cannot validate real-world assistive-technology ergonomics. | Conduct structured usability testing prior to launch with: **(a)** Blind screen-reader users (NVDA + Firefox, JAWS + Chrome, VoiceOver iOS/macOS, TalkBack Android); **(b)** Deaf/Hard-of-Hearing Indian Sign Language users; **(c)** Low-vision users testing 200% zoom & high-contrast modes; **(d)** Mobility/switch/voice-control users; and **(e)** Neurodivergent users reviewing form cognitive load. Remediate all P0/P1 findings before opening public registration. |
| **PDF/UA Structural Tagging** | `generateAccessiblePassPdf` (`pdf-lib`) produces crisp, high-contrast vector text and metadata, but `pdf-lib` does not natively emit full PDF/UA structure trees. | Maintain the accessible HTML Participant Dashboard view as the primary screen-reader pass alongside the downloadable PDF, and evaluate a PDF/UA-tagged renderer (e.g., Playwright-printed tagged PDF or server-side PDF/UA post-processor) prior to mass delegate issuance. |
| **Multilingual / Gujarati & ISL Video Support** | Current UI copy is English-only. | Add Gujarati (`gu`) and Hindi (`hi`) localized key pages and embed captioned + ISL-interpreted welcome videos on the Home and Accessibility pages once organizer media assets are finalized. |
| **Venue Accessibility Verification** | MSU Baroda campus audit is marked *in progress* and GMERS Gotri is *PENDING* confirmation. | Update `venues` via Admin CMS once physical accessibility audits (ramp gradients, tactile paving, accessible washrooms, charging bays) are signed off. |
| **Email & Payment Live Verification** | Email defaults to local DB queue (`EMAIL_PROVIDER=none`) and payments default to disabled until organizer fee confirmation. | Complete end-to-end staging tests with live Resend domain DKIM/SPF and Razorpay test/production webhooks before enabling paid Delegate tiers. |

---

## 5. Security Threat Model (STRIDE Summary)

| Threat Category | Attack Vector | Mitigations Implemented |
|---|---|---|
| **Spoofing (Auth & Sessions)** | Credential stuffing, session hijacking, admin account takeover | `bcryptjs` (cost 12) password hashing; cryptographically random 256-bit session tokens stored only as HMAC-SHA256 hashes in `sessions`; `HttpOnly`, `SameSite=Lax`, `Secure` (prod) cookies; account lockout fields (`failedLoginAttempts`, `lockedUntil`); TOTP MFA (`otplib`) for Admin/Superadmin; no hardcoded default admin passwords (`db:bootstrap-superadmin` refuses to overwrite existing Superadmin). |
| **Tampering (QR & Payments)** | Forged QR passes, modified registration IDs, spoofed payment webhooks | Opaque QR tokens signed with `QR_SIGNING_SECRET` (`PFG27.<nonce>.<sig>`) verified with `crypto.timingSafeEqual`; DB stores only `token_hash`; Razorpay webhook verifies `x-razorpay-signature` HMAC before capturing payment; ineligible (`SUBMITTED`, `REJECTED`, unpaid) registrations are blocked server-side from QR issuance. |
| **Repudiation** | Disputed check-ins, unauthorized status changes, silent revocations | Immutable `credential_events` (`ISSUED`, `CHECKED_IN`, `DUPLICATE_ATTEMPT`, `REVOKED`, `REISSUED`) and `audit_logs` recording `actorUserId`, `action`, `resourceType`, `resourceId`, IP, user agent, and timestamp. |
| **Information Disclosure** | Leakage of disability/medical data to reception desks, public APIs, or QR scans | Zero PII inside QR payload; strict field projection in `verifyCredentialInput`; RBAC separation (`RGST` lacks `accommodations.read`, `registrations.read.all`, `cms.manage`); private object storage for uploaded documents. |
| **Denial of Service / Race Conditions** | Simultaneous multi-gate check-in replay (double entry) | Database-level unique index `checkins_credential_scope_unique(credential_id, entitlement_scope)` guarantees atomic single check-in per IST day/festival scope under concurrent load (verified in `tests/integration/platform.test.ts`). |
| **Elevation of Privilege** | Participant or RGST staff invoking `/api/admin/*` endpoints | Server-side session + role/permission checks (`hasPermission`) on every protected API route; `SUPERADMIN`-only guard on `admin_accounts.manage`, `cms.manage`, `audit_logs.read`, and `settings.manage`. |
