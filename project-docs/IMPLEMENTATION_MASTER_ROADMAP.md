# Implementation Master Roadmap — Purple Festival Gujarat 2027

**Architecture:** Native PHP 8 + MySQL/MariaDB (InnoDB `utf8mb4`) + PDO + Vanilla JavaScript + HTML5 + CSS3  
**Hosting:** Bluehost Shared/cPanel Apache + MySQL + Resend API + cPanel Cron Jobs (**Zero Docker, Zero Containers, Zero Node Backend, Zero Redis/PostgreSQL**)  
**Local Dev & Testing Strategy:** Native Windows XAMPP (`C:\xampp` — `PHP 8.2.12`, `MariaDB 10.4.32`, `Apache 2.4.58`) with strictly isolated `pfg27_dev` and `pfg27_test` databases and a local file-based email sink (`MAIL_DRIVER=file` -> `tests/tmp/mail-sink/`), plus optional dev-only Playwright/Node browser & axe-core accessibility tests.

---

## Development & Testing Environment Comparison (Phase 1 Decision)

| Option Evaluated | Bluehost Parity | Safety & Isolation | Local Speed & Cost | Selected Role |
|---|---|---|---|---|
| **1. Existing Bluehost Staging + Isolated Test DB** | Exact hosting parity (PHP, MySQL, Apache, cPanel Cron, Resend) | Medium — requires restricted HTTP access & separate staging/test DB to avoid touching real data | Requires remote upload per edit | **Pre-Release Staging Verification & Bluehost Env Audit** (`scripts/verify-bluehost-env.php`) |
| **2. Native Local XAMPP (`C:\xampp`: PHP 8.2.12 + MariaDB 10.4.32 + Apache 2.4.58)** | High — identical native PHP/PDO/MySQL/Apache `.htaccess` model; already installed on workstation | **Maximum** — isolated `pfg27_test` DB + `MAIL_DRIVER=file` sink (0 outbound emails, 0 staging impact) | **Instant local execution**, zero installs, zero Docker | **Primary Local Dev, Integration, Concurrency & Automated Test Runtime** |
| **3. Standalone Native PHP + MySQL Install** | High | High | Redundant since `C:\xampp` already provides native PHP 8.2.12 + MariaDB 10.4.32 + Apache 2.4.58 | Fallback only if XAMPP needs upgrade |

---

## 18-Phase Delivery Roadmap

- **Phase 0 (COMPLETE):** Baseline revalidation, 9 festival source documents & 14 audit artefacts reconciled, 14 conflicts logged, `D-01`–`D-27` register created.
- **Phase 1 (VERIFIED LOCALLY — 11/11 PASS; Awaiting Authorized Bluehost Deploy):** Native XAMPP MySQL/PHP/Apache local environment (`pfg27_dev`, `pfg27_test`), safe email sink (`MAIL_DRIVER=file`), Bluehost environment verifier (`scripts/verify-bluehost-env.php`), fail-closed secret & DB isolation (`includes/config.php`, `includes/database.php`, `includes/qr-service.php`), Apache `.htaccess` containment (verified live on real XAMPP Apache `2.4.58`: 26/26 PASS), `.cpanel.yml` hygiene with `$DEPLOYPATH` guard, and Phase 1 automated PHP test harness (`tests/run.php`).
- **Phase 2 (COMPLETE & VERIFIED LOCALLY — 32/32 PHP/MariaDB + 26/26 Apache PASS; Awaiting Authorized Bluehost Deploy):** Closed all P0 local code security controls across sub-phases `2A`–`2K`: session-bound CSRF enforcement across all mutating endpoints, CMS & Audit Logs & Overview & Registrations RBAC enforcement, D-20 mandatory TOTP MFA for `SUPERADMIN`/`ADMIN` (160-bit AES-256-GCM encrypted secrets, read-only GET, replay guard, 8 hashed recovery codes), persistent DB rate limiting (`security_rate_limits`), anti-enumeration login & sticky lockout fix, POST-only CSRF logout, script-context XSS escaping & safe URL validation, D-01 QR credential retirement (`api.qrserver.com` removed, `410 Gone` on retired QR endpoints), unauthenticated email attachment block, AES-256-GCM encryption + read auditing for sensitive disclosures, `/dashboard` fatal error fix (`FND-13`), CSV formula neutralization, and true SQL audit log pagination.
- **Phase 3 (NEXT — Needs `D-06`, `D-21`, `D-27`):** Versioned SQL migration runner (`database/migrations/`), remove per-request `ALTER TABLE`, consolidate 6 duplicate table pairs cleanly, enforce FKs & unique constraints, backfill encryption on legacy disclosures, replace `COUNT(*)+1` with atomic DB-backed `PFG-2027-000001` numbering decoupled from QR.
- **Phase 4 (Needs `D-01`, `D-11`):** Centralized transactional email service (`includes/notification-service.php`), fix `notification_queue` schema columns, build cPanel Cron queue worker (`scripts/process-notification-queue.php`) with worker locking, retries & honest delivery states (`CREATED -> QUEUED -> PROCESSING -> PROVIDER_ACCEPTED -> DELIVERED/FAILED/BOUNCED`).
- **Phase 5 (Needs `D-09`, `D-10`, `D-21`):** Participant Account Creation, Email Verification, Login, Logout, Password Recovery, Session Security, and Verified-Email Legacy Account Claim flow.
- **Phase 6 (Needs `D-05`–`D-09`, `D-12`–`D-14`):** Reconstruct `/apply` around strictly **3 primary categories** (`ATTENDEE`, `SLI`, `VOLUNTEER`) with cross-category accessibility & accommodations (no diagnosis required).
- **Phase 7 (Needs `D-16`, `D-17`):** Server-enforced application state machines separating approval status, assignment status, and physical attendance.
- **Phase 8 (Needs `D-03`, `D-20`):** 3-tier administrative RBAC (`SUPERADMIN`, `ADMIN` with Superadmin-managed operational permission profiles, `RGST`) with anti-escalation & last-Superadmin protections.
- **Phase 9 (Needs `D-01`–`D-04`):** Decouple QR scanning from `/rgst`; build accessible manual search & checkbox attendance marking keyed on `registration_id` + festival day (IST) with atomic duplicate prevention & audited corrections.
- **Phase 10 (Needs `D-09`, `D-16`):** Fix `/dashboard` fatal crash and deliver full authenticated Attendee, Volunteer, and SLI dashboards.
- **Phase 11 (Needs `D-17`, `D-27`):** Reconstruct `/admin` with accurate KPIs, paginated full-detail views, safe CSV exports, Communications Center, and un-truncated Audit Logs.
- **Phase 12 (Needs `D-18`, `D-19`, `D-23`):** Programme, venue (confirmed vs. tentative), and Volunteer/SLI duty assignment scheduling with overlap detection & 2-interpreter rotation awareness.
- **Phase 13 (Needs `D-01`, `D-11`):** Connect all approved lifecycle events to accessible email & in-app dashboard notifications.
- **Phase 14 (Needs `D-22`, `D-23`):** Reconcile public site navigation, verified organizer/partner consent copy, and CMS permissions while preserving the approved visual identity.
- **Phase 15 (Continuous + Final Gate; `D-26`):** Remediate all WCAG 2.2 AA defects (High Contrast, Yellow-on-Black, focus preservation, semantic HTML, target sizes, email templates) and perform automated + manual keyboard/screen-reader validation.
- **Phase 16 (Optional / Conditional):** Implement only explicitly approved modules (payments/Razorpay, private file uploads, SMS/WhatsApp, certificates, athlete/exhibitor hooks).
- **Phase 17:** Full automated PHP unit/integration, adversarial 46-finding security regression, concurrency, load (5,000-attendee scale), and Playwright + axe-core E2E verification.
- **Phase 18 (Needs `D-22` + Release Sign-Off):** Bluehost staging dry-run, verified production deployment, safe smoke tests, rollback plan, and Superadmin/Admin/RGST operations handbook.

