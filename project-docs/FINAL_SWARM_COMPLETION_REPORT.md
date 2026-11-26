# Final Swarm Completion Report — Purple Festival Gujarat 2027 (Phases 0–18)

**Project:** Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat, India)  
**Repository & Integration Branch:** `d:\purplefestgujarat` (`swarm/phases-5-18`)  
**Stack (Preserved 100%):** Native PHP `8.2.12` + MySQL/MariaDB `10.4.32` (InnoDB `utf8mb4`) + PDO + Vanilla JavaScript + HTML5 + CSS3 + Apache `2.4.58` + Bluehost cPanel + Resend HTTPS API + PHP CLI Cron Jobs (**Zero Docker, Zero Next.js/React runtime, Zero Node backend, Zero Redis/RabbitMQ, Zero Frameworks**)  
**Report Authority:** Part XIV (§63 A–J) of the Autonomous Multi-Agent Execution Directive

---

## A. Executive Summary

- **What Was Completed:** All approved application engineering, database architecture, security controls, accessible frontend/portal reconstruction, independent second-model security review & full remediation of all findings, end-to-end automated verification (PHP/MariaDB, native Apache `2.4.58`, and real Playwright Chrome E2E/WCAG/Visual testing), clean Bluehost release packaging, and operational handover documentation across **Phases 0–18**.
- **What Is Operational Locally (`107 / 107` automated checks PASS, `0` failed):**
  - Participant account creation, cryptographic email ownership verification, login, profile/email-change management, atomic password recovery, and verified-email legacy registration claim.
  - Strictly three primary participant categories (**`ATTENDEE`**, **`VOLUNTEER`**, **`SLI`**) with shared WCAG 2.2 AA accessibility & accommodation module (no diagnosis required), atomic Registration ID allocation (`PFG-2027-NNNNNN`), and unified server-enforced application state machines.
  - Three-tier administrative hierarchy (**`SUPERADMIN`**, **`ADMIN`** with 7 Superadmin-managed least-privilege operational permission profiles, **`RGST`**) with mandatory D-20 TOTP MFA for `SUPERADMIN`/`ADMIN`.
  - Accessible manual Registration Desk (`/rgst`, zero QR scanner dependency) with paginated lookup, atomic 3-day checkbox attendance, duplicate prevention, and audited D-03 corrections.
  - Full authenticated Participant Dashboard (`/dashboard`), complete operational Admin Portal (`/admin`), Programme/Venue/Volunteer & SLI Duty Assignment management (with overlap detection and 2-SLI pairing), transactional email outbox + 29 WCAG templates + cPanel cron worker + Svix webhooks, and public website CMS/partner consent integration preserving the existing visual identity.
- **What Remains Blocked Externally (`CONFIGURATION BLOCKED`):**
  - Live Resend API key, verified production domain (`D-22`), SPF/DKIM/DMARC DNS records, and live Svix webhook callback verification.
  - Authorized Bluehost staging/production SSH/cPanel credentials, live migration execution, HTTPS/TLS live verification, and cPanel Cron activation.
  - Final Project Owner policy sign-offs consolidated in [FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md](FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md) (e.g., `D-05` fee policy, `D-22` domain, `D-23` partner logo consent, `D-27` retention window).
  - Manual assistive-technology screen-reader validation (NVDA/JAWS/VoiceOver on physical devices).
- **Readiness Classification:** **`DEVELOPMENT COMPLETE` · `LOCALLY VERIFIED` · `CONFIGURATION BLOCKED` (for Live Bluehost & Live Resend)** — NOT yet `STAGING VERIFIED` or `PRODUCTION VERIFIED`.

---

## B. Agent Execution Report (Actual Invoked Swarm Workers Only)

> **Honesty & Platform Note:** Native Antigravity Multi-Agent Swarm (`swarm` tool + shared board + isolated git worktrees) was used across **Gemini** (`Gemini 4 Neon`, `Gemini 4 Hydrogen`, `Gemini 4 Argon`) and **Claude** (`Claude Sonnet 5.5`, `Claude Opus 5.5`) model families. When Claude hit third-party quota (`429 RESOURCE_EXHAUSTED`) during independent review initialization, the coordinator cleanly migrated the review workers via `swarm(action="model")` to `Gemini 4 Hydrogen (XHigh)` and `Gemini 4 Argon (High)` without losing task state. No GPT-family execution was claimed where not invoked.

| Worker ID | Logical Role (§13) | Model Executed | Assigned Scope | Outcome & Commit / Evidence |
|---|---|---|---|---|
| `coordinator` | Master Orchestrator | Gemini 4 Neon (XHigh) | M0 bootstrap, Phase 4 completion, contracts, routing, merges, `auth-pages.css` WCAG fix, final 107-check run | `ACCEPTED` (`6e2061b`, `3a1dbcc`, `fe16641`, all clean conflict-free merges) |
| `db-architect` | Agent B — Database | Claude Sonnet 5.5 (High) | Migrations `007`–`010` (+ rollbacks), idempotent seeder | `ACCEPTED` (`549d4db` — 70 tables, 79 FKs, 11 migrations, rollbacks verified) |
| `a11y-core` | Agent K — Accessibility | Gemini 4 Neon (High) | WCAG 2.2 AA High Contrast, Yellow-on-Black, focus, target sizes, `<h3><button>`, FND-46 live-region | `ACCEPTED` (`c77ebc3` — verified in Chrome `E2E-05`) |
| `auth-eng` | Agent E — Participant Auth | Claude Sonnet 5.5 (High) | Phase 5 signup, email verify, login, `/staff/login`, recovery UI, profile, legacy claim | `ACCEPTED` (`4ebc72e` — 16 files, verified in `T5-01`, `T5-02`, `E2E-01`) |
| `reg-eng` | Agent F — Registration & Lifecycle | Gemini 4 Neon (High) | Phases 6–7 3-category hub, Attendee/Volunteer/SLI forms, shared components, validators, drafts, state machine | `ACCEPTED` (`f1faf29` — 16 files, verified in `T5-03`, `T5-04`, `E2E-01`, `E2E-03`) |
| `rgst-eng` | Agent I — Registration Desk | Gemini 4 Neon (High) | Phase 9 manual `/rgst` desk, paginated search, atomic checkbox mark, D-03 corrections | `ACCEPTED` (`1b8c7dc` — 5 files, verified in `T5-06`, `E2E-02`) |
| `admin-eng` + helper `admin-ui` | Agent H & J — Admin & Ops | Claude Sonnet 5.5 (High) | Phases 8, 11, 12 permission profiles, full Admin Portal, venues, programme, duty assignments | `ACCEPTED` (`6de16f0` — 13 files, verified in `T5-05`, `T5-08`) |
| `portal-eng` | Agent G — Participant Dashboard | Gemini 4 Neon (High) | Phase 10 authenticated 5-tab Participant Dashboard & in-app notifications API | `ACCEPTED` (`013106e` — 4 files, verified in `T5-07`, `E2E-01`, `E2E-03`) |
| `public-eng` | Agent Public/CMS | Gemini 4 Neon (High) | Phase 14 role-aware nav, pre-paint a11y init, announcements, partner consent, programme draft filter, legal copy | `ACCEPTED` (`4fdd722` — 8 files, verified in `T5-08`, `E2E-04`) |
| `reviewer` (+ `rev-registration`, `rev-desk`, `rev-dash`) | Agent M — Independent Reviewer | Claude Opus 5.5 -> switched to Gemini 4 Hydrogen (XHigh) / Argon (High) on 429 quota | Independent static security, RBAC, migration, state-machine, desk & dashboard audit | `ACCEPTED` (`c2d0475` — surfaced 1 CRIT, 7 HIGH, 7 MED, 5 LOW real bugs!) |
| `fix-security-auth` | Remediation Engineer 1 | Gemini 4 Neon (High) | Fixed `CRIT-01`, `HIGH-01..03`, `MED-01..05`, `LOW-01` + Migration `011` | `ACCEPTED` (`6095221` — 17 files, verified in `T5-01..08`) |
| `fix-portal-desk` | Remediation Engineer 2 | Gemini 4 Neon (High) | Fixed `HIGH-04..07`, `MED-06..07`, `LOW-02..05` | `ACCEPTED` (`0a6fdff` — 10 files, verified in `T5-04..08`) |
| `qa-eng` | Agent L — QA & E2E | Gemini 4 Neon (High) | Phase 17 PHP (`62`), Apache (`40`), concurrency & Playwright Chrome E2E/WCAG (`5`) test suites | `ACCEPTED` (`fed995f` — caught genuine `auth-pages.css` contrast bug in `E2E-05`, fixed by coordinator -> `107/107` PASS) |
| `release-docs` | Agent N & O — DevOps & Governance | Gemini 4 Argon (High) | Phase 16 matrix, `scripts/build-release-package.php`, handover handbook & 15 authoritative docs | `ACCEPTED` (`eb56dba` — 184-file clean package, `0` excluded leaks) |

---

## C. Phase Completion Matrix (Phases 0–18)

| Phase | Scope | Implementation Status | Verification Status | Deployment Status | Remaining Limitations / Blockers |
|---:|---|---|---|---|---|
| **0** | Forensic Audit & Governance Baseline | `VERIFIED WORKING LOCALLY` | Verified (14 audit docs + 9 source docs) | N/A | None |
| **1** | Native XAMPP Dev/Test Env & Security Containment | `VERIFIED WORKING LOCALLY` | `T1-01`..`T1-11` (`11/11`) + Apache (`A-15`..`A-26`) | `NOT DEPLOYED` | Live Bluehost HTTPS/TLS awaits deploy |
| **2** | Core Security, RBAC, D-20 MFA & D-01 QR Retirement | `VERIFIED WORKING LOCALLY` | `T2-01`..`T2-20` (`20/20`) + `A-40` (`410 Gone`) | `NOT DEPLOYED` | Live Bluehost deploy pending |
| **3** | Versioned Migrations, Atomic IDs & Data Integrity | `VERIFIED WORKING LOCALLY` | `T3-01`..`T3-14` (`14/14`, 100-worker IDs, dump/restore) | `NOT DEPLOYED` | Live MySQL 8.0 Bluehost migration pending |
| **4** | Transactional Email, Queue Worker & Svix Webhooks | `VERIFIED WORKING LOCALLY` | `T4-01`..`T4-08` (`8/8`, 1,000-msg 4-worker load, 29 WCAG templates) | `CONFIGURATION BLOCKED` | Needs `D-22` domain, Resend API/DNS/Svix & cPanel cron |
| **5** | Participant Auth, Email Verify, Recovery & Legacy Claim | `VERIFIED WORKING LOCALLY` | `T5-01`, `T5-02`, `E2E-01`, `A-30`..`A-34` | `NOT DEPLOYED` | Live Resend delivery needed for real verification emails |
| **6** | Three-Category Registration (`ATTENDEE`/`VOLUNTEER`/`SLI`) | `VERIFIED WORKING LOCALLY` | `T5-03`, `T5-09`, `E2E-01`, `E2E-03`, `A-35` | `NOT DEPLOYED` | Configurable defaults await final `D-05`–`D-15` sign-off |
| **7** | Server-Enforced Application State Machines | `VERIFIED WORKING LOCALLY` | `T5-04` (unified `application-lifecycle.php` + `admin-common.php`) | `NOT DEPLOYED` | Confirm `D-16`/`D-17` before public open |
| **8** | Superadmin Custom Operational Permission Profiles | `VERIFIED WORKING LOCALLY` | `T5-05` (`CRIT-01`/`HIGH-01`/`MED-05` verified), `A-38` | `NOT DEPLOYED` | Production staff provisioning via `bootstrap-superadmin.php` |
| **9** | Accessible Manual Registration Desk (`/rgst`, Zero QR) | `VERIFIED WORKING LOCALLY` | `T5-06`, `E2E-02`, `A-36` (`FOR UPDATE` + D-03 verified) | `NOT DEPLOYED` | Confirm `D-02`/`D-03`/`D-04` event-day policy |
| **10** | Complete Authenticated Participant Dashboard | `VERIFIED WORKING LOCALLY` | `T5-07`, `E2E-01`, `E2E-03`, `A-37` | `NOT DEPLOYED` | None locally |
| **11** | Complete Operational Admin Portal | `VERIFIED WORKING LOCALLY` | `T5-05`, `T5-08`, `T2-12`..`T2-21` | `NOT DEPLOYED` | None locally |
| **12** | Programme, Venues & Volunteer/SLI Duty Assignments | `VERIFIED WORKING LOCALLY` | `T5-08`, `E2E-03`, `A-39` (overlap `409`, 2-SLI pairing) | `NOT DEPLOYED` | Draft venues/sessions remain unpublished until `D-23` |
| **13** | Complete Lifecycle Notification Workflows | `VERIFIED WORKING LOCALLY` | `T4-01`..`T4-08`, `T5-01`..`T5-08` | `CONFIGURATION BLOCKED` | Live Resend activation blocked on `D-22` |
| **14** | Public Website & CMS Integration (Design Preserved) | `VERIFIED WORKING LOCALLY` | `T5-08`, `E2E-04`, `A-01`..`A-09` (baseline vs post screenshots) | `NOT DEPLOYED` | Partner logo consent (`D-23`) keeps unconfirmed partners hidden |
| **15** | WCAG 2.2 AA Accessibility Remediation | `VERIFIED WORKING LOCALLY` | `E2E-05` (`8/8` routes Chrome HC `21:1`, YB `17:1`, `390px` mobile) | `NOT DEPLOYED` | Live assistive-tech testing (NVDA/JAWS/VoiceOver) pending |
| **16** | Conditional & Optional Modules Classification | `DEFERRED WITH APPROVAL` / `CONFIGURABLE AND INACTIVE` | [PHASE_16_CONDITIONAL_FEATURES_MATRIX.md](PHASE_16_CONDITIONAL_FEATURES_MATRIX.md) | N/A | Payments, uploads, SMS/WhatsApp, B2B inactive pending Owner sign-off |
| **17** | Full-System E2E, Security, Concurrency & Visual QA | `VERIFIED WORKING LOCALLY` | **`107 / 107` PASS** (`62` PHP + `40` Apache + `5` Chrome E2E/WCAG) | N/A | Manual AT & live staging smoke test pending |
| **18** | Bluehost Readiness, Clean Packager & Handover | `VERIFIED WORKING LOCALLY` (Live Deploy: `CONFIGURATION BLOCKED`) | `scripts/build-release-package.php` (`184` files, `0` leaks, `95/95` PHP lint) | `NOT DEPLOYED` | Requires `D-22`, Bluehost creds & explicit release authorization |

---

## D. Feature Completion Matrix (Launch-Critical Workflows)

- **Participant Onboarding & Recovery (`/register`, `/verify-email`, `/login`, `/forgot-password`, `/reset-password`):** `VERIFIED WORKING LOCALLY` (`T5-01`, `E2E-01`, `A-30`–`A-33`)
- **Verified-Email Legacy Claim (`/api/auth/claim-legacy`):** `VERIFIED WORKING LOCALLY` (`T5-02`)
- **3-Category Registration Hub & Independent Forms (`/apply`, `/apply/attendee`, `/apply/volunteer`, `/apply/sli`):** `VERIFIED WORKING LOCALLY` (`T5-03`, `T5-09`, `E2E-01`, `E2E-03`)
- **Shared Accessibility & Encrypted Disclosures (No Diagnosis Required):** `VERIFIED WORKING LOCALLY` (`T2-17`, `T5-03`, `T5-07`)
- **Application Lifecycle State Machines & Self-Service Edit/Resubmit/Withdraw:** `VERIFIED WORKING LOCALLY` (`T5-04`, `E2E-03`)
- **Superadmin Staff & 7 Operational Permission Profiles (`/admin`, `/api/admin/permission-profiles`):** `VERIFIED WORKING LOCALLY` (`T5-05`, `A-38`)
- **Manual Registration Desk & Atomic 3-Day Attendance (`/rgst`, `/api/attendance/*`, Zero QR):** `VERIFIED WORKING LOCALLY` (`T5-06`, `E2E-02`, `A-36`)
- **Participant Dashboard & In-App Notifications (`/dashboard`, `/api/account/dashboard`):** `VERIFIED WORKING LOCALLY` (`T5-07`, `E2E-01`, `E2E-03`, `A-37`)
- **Venues, Programme Draft Filtering & Volunteer/SLI Duty Scheduling (`/api/admin/venues|programme|assignments`):** `VERIFIED WORKING LOCALLY` (`T5-08`, `E2E-03`, `A-39`)
- **Transactional Email Queue, CLI Cron Worker, 29 WCAG Templates & Svix Webhooks:** `VERIFIED WORKING LOCALLY` / `PROVIDER SIMULATION VERIFIED` (`T4-01`–`T4-08`; Live Resend: `CONFIGURATION BLOCKED`)

---

## E. Test Execution Summary (`107 / 107` Executed Checks PASSED, `0` FAILED)

| Suite | Executed | Passed | Failed | Runtime & Key Evidence |
|---|---:|---:|---:|---|
| **PHP / MariaDB Full-System Suite** (`tests/run.php`) | **62** | **62** | **0** | PHP `8.2.12` + MariaDB `10.4.32`: `105` PHP files linted (`0` errors), 100-worker atomic Reg IDs (`0` dups), 10-worker concurrent full HTTP registrations (`10/10` HTTP `201`), 4-worker `1,000`-msg notification load (`0` dups, `571 ms`), `mysqldump` backup/restore (`70` tables, `79` FKs, `12` migrations) |
| **Native Apache 2.4.58 Suite** (`tests/apache_verification.php`) | **40** | **40** | **0** | `Apache/2.4.58 (Win64) PHP/8.2.12`: clean routes, `.htaccess` `403` blocks on all internal/doc/script paths, 5 security headers, auth gates (`401`/`302`), retired `/api/qr/pass` (`410 Gone`) |
| **Playwright Chrome E2E, WCAG & Visual Suite** (`tests/phase17_browser_e2e.mjs`) | **5** | **5** | **0** | Playwright `1.65.1` + Chrome Headless: Attendee, Volunteer, SLI, Admin approval/assignment, Dashboard, RGST desk reload persistence, `8/8` routes WCAG (`HC=21.0:1`, `YB=17.0:1`, `mobW=390px`, `0` `<button><h*>`, Tab focus), `6` desktop screenshots (`0` JS pageerrors) |
| **Not Run / Blocked Tests** | — | — | — | Live Resend inbox delivery & live Svix callback (`CONFIGURATION BLOCKED`), live Bluehost MySQL 8.0/HTTPS/cron smoke test (`CONFIGURATION BLOCKED`), manual physical screen-reader tests (NVDA/JAWS/VoiceOver) |

Full machine-readable test logs: [TEST_EXECUTION_RESULTS.csv](TEST_EXECUTION_RESULTS.csv) · [REGRESSION_TEST_RESULTS.csv](REGRESSION_TEST_RESULTS.csv).

---

## F. Security Report (All 46 Original Forensic Audit Findings + Independent Review)

- **All 46 Original Audit Findings (`FND-01`–`FND-46`)** are reconciled in [SECURITY_REMEDIATION_TRACKER.csv](SECURITY_REMEDIATION_TRACKER.csv):
  - **`38` `FIXED LOCALLY`** (including all 12 P0 findings, `FND-13`..`FND-16`, `FND-19`..`FND-21`, `FND-25`..`FND-33`, `FND-36`..`FND-46`)
  - **`5` `ELIMINATED THROUGH VERIFIED DECOMMISSIONING`** (`FND-17`, `FND-18`, `FND-34`, `FND-35`, QR scanner/pass pathways)
  - **`1` `SAFELY CONTAINED`** (`FND-22` coupons hidden on free test path)
  - **`1` `PARTIALLY REMEDIATED`** (`FND-24`: cryptographic email verification complete locally; live Resend `CONFIGURATION BLOCKED`; SMS/WhatsApp deferred per `D-11`)
  - **`1` `DEFERRED WITH APPROVAL`** (`FND-23`: payment gateway deferred pending `D-05`)
  - **`0` `STILL OPEN` launch-critical security vulnerabilities locally.**
- **Independent Review Remediation (`INDEPENDENT_SECURITY_ARCHITECTURE_REVIEW.md`):** `1` CRITICAL (`CRIT-01`), `7` HIGH (`HIGH-01`–`07`), `7` MEDIUM (`MED-01`–`07`), and `5` LOW (`LOW-01`–`05`) findings were ALL remediated and verified in automated regression tests, plus the Playwright-discovered `auth-pages.css` contrast defect was fixed and re-verified (`HC=21.0:1`, `YB=17.0:1`).

---

## G. Database Report

- **Migrations Applied & Verified:** **`12 / 12` (`000`–`011`)** via CLI-only `database/migrate.php` with normalized SHA-256 checksums and `GET_LOCK` serialization ([DATABASE_MIGRATION_HISTORY.md](DATABASE_MIGRATION_HISTORY.md)).
- **Schema Totals:** **`70` InnoDB `utf8mb4` tables**, **`79` foreign key constraints** (`0` `ON DELETE CASCADE` on participant/audit records — strictly `ON DELETE RESTRICT` / `ON DELETE SET NULL`), zero per-request DDL in `includes/database.php`.
- **Legacy Preservation:** All 6 legacy duplicate tables preserved read-only; all 5 legacy registration categories deactivated non-destructively (`is_active = 0`, 0 deleted rows); legacy upgrade test (`T3-03`) & real `mysqldump` backup/restore (`T3-13`) verified cleanly on `70` tables / `79` FKs / `12` migrations.

---

## H. Release Report

- **Integration Branch:** `swarm/phases-5-18` (local only — **not pushed to `origin`** to prevent accidental remote/cPanel triggers).
- **Local Recovery Checkpoints:** `phase2-verified-baseline`, `phase3-verified-baseline`, `phase4-verified-baseline`, `phases-5-15-review-fixed`, `phases-5-18-verified-locally`.
- **Clean Release Packager:** `scripts/build-release-package.php --json` verified **`184` production runtime files (`8.56 MB`)**, **`95 / 95` packaged PHP files linted**, **`26,406` excluded dev/audit/Next.js/raw-doc/scratch files**, and **`0` leaked files**, with `RELEASE_MANIFEST.json` written strictly outside `public_html`.
- **Deployment & Rollback Guides:** [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) · [ROLLBACK_PLAN.md](ROLLBACK_PLAN.md) · [BLUEHOST_COMPATIBILITY_REPORT.md](BLUEHOST_COMPATIBILITY_REPORT.md) · [OPERATIONS_HANDBOOK.md](OPERATIONS_HANDBOOK.md).

---

## I. Outstanding Project Owner Actions (Single Consolidated List)

All remaining unresolved organizer & infrastructure items are consolidated in [FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md](FINAL_LAUNCH_CONFIGURATION_AND_OWNER_DECISIONS.md):
1. **Confirm Authoritative Production Domain (`D-22`)** (`purplefestivalgujarat.com` vs `purplefestivalvadodara.com`).
2. **Provision Live Resend Credentials & DNS:** Publish SPF, DKIM, DMARC records, set `RESEND_API_KEY`, `RESEND_WEBHOOK_SECRET`, `MAIL_FROM_ADDRESS`, and supply 2–3 pilot test inbox addresses.
3. **Approve Launch Business Policies:** Confirm `D-05` (Free vs Paid Attendee), `D-09` (1 active per category), `D-12`–`D-14` (SLI Gujarat priority / optional CRR), `D-02`–`D-04` (daily attendance scope & 15-min correction policy), `D-21` (existing staging DB disposition), `D-23` (confirmed partner logos & confirmed venues/sessions), and `D-27` (DPDP post-festival retention window).
4. **Authorize Remote Push & Bluehost Staging/Production Deployment:** Approve merging `swarm/phases-5-18` into `main`, pushing to GitHub, running `scripts/verify-bluehost-env.php` on Bluehost, applying non-destructive migrations `000`–`011`, configuring cPanel Cron (`scripts/process-notification-queue.php`), and running a controlled staging smoke test before public go-live.

---

## J. Final Conclusion

The Purple Festival Gujarat 2027 platform is officially classified as:

- **`DEVELOPMENT COMPLETE`**
- **`LOCALLY VERIFIED`** (`107 / 107` automated checks PASS across PHP/MariaDB, native Apache `2.4.58`, and Playwright Chrome E2E/WCAG/Visual)
- **`CONFIGURATION BLOCKED`** *(for Live Resend Email Delivery and Live Bluehost Staging/Production Deployment pending Owner Decisions & Hosting Credentials)*

It is **not yet** `STAGING VERIFIED` or `PRODUCTION VERIFIED` until the authorized Bluehost deployment gate in [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) is executed.
