# Operations & Handover Handbook — Purple Festival Gujarat 2027 (§55)

**Audience:** Festival Superadministrators (`SUPERADMIN`), Operational Administrators (`ADMIN`), Registration Desk Operators (`RGST`), and Bluehost Technical Administrators.  
**Festival Dates:** 21–23 January 2027 · Vadodara, Gujarat (`Asia/Kolkata` / `+05:30` IST).

---

## 1. Superadmin Operations Guide (`SUPERADMIN`)

### 1.1 Signing In & Revised Authentication / Lockout Policy (`CAD-16`, Supersedes `D-20`)
1. Open the dedicated Staff Portal login at **`/staff/login`** (never use the participant `/login` page for staff duty).
2. Enter your Superadmin username (or email) and strong password (`>= 12` characters for bootstrapped Superadmin accounts). TOTP/authenticator MFA is discontinued under Project Owner policy `CAD-16`; existing staff accounts sign in directly with username and password and land on `/admin`.
3. **Universal 5-Attempt / 3-Hour Account Lockout:** Five consecutive failed credential attempts on **any** account (`SUPERADMIN`, `ADMIN`, custom profile, `RGST`, or `PARTICIPANT`) automatically lock the account for **exactly 3 hours (180 minutes / 10,800 seconds)** using atomic database row locking. During lockout, even valid credentials are rejected, additional failures do not extend expiry, IP/device/cookie changes and password recovery cannot bypass lockout, and the account unlocks automatically upon expiry with no Cron Job required. Successful login resets the failed-attempt counter.

### 1.2 Managing Staff Accounts & Least-Privilege Operational Profiles
Navigate to **`/admin` -> Staff & Profiles**:
1. **Assigning an Operational Profile to an `ADMIN` Account:**
   - Choose one of the 7 seeded least-privilege profiles (`registration_manager`, `volunteer_coordinator`, `sli_coordinator`, `accessibility_coordinator`, `programme_coordinator`, `content_manager`, `communications_manager`) or create a custom profile.
   - When an `ADMIN` is assigned a profile, they receive **only** `account.read.own` plus the permissions in that profile (and any explicit custom permissions). They do **not** inherit unrestricted access to disability/accommodation data (`accommodations.read`) or other modules outside their profile.
   - Superadmin-only authorities (`admin_accounts.manage`, `settings.manage`, `audit_logs.read`) can never be added to an operational profile.
2. **Creating Registration Desk (`RGST`) Accounts:**
   - Select role `RGST`. Desk operators cannot be assigned admin permission profiles or custom permissions and can only access `/rgst`.
3. **Emergency Staff Account Recovery & Deactivation:**
   - Changing a staff user's role, profile, active status, or password immediately revokes all of their active login sessions across all devices.
   - Safety guards prevent you from deactivating or demoting your own account or removing the last active `SUPERADMIN`.

### 1.3 Reviewing Security & Sensitive-Data Audit Logs
Navigate to **`/admin` -> Audit Logs**:
- Filter by action (`SENSITIVE_DATA_READ`, `REGISTRATIONS_EXPORTED`, `ATTENDANCE_CORRECTED`, `DUTY_ASSIGNMENT_OVERLAP_OVERRIDDEN`, `CSRF_REJECTED`, `LOGIN_ACCOUNT_LOCKED`), actor, resource type, or date range.
- Every decryption/view of a participant's disability disclosure or accommodation request is immutably logged under `SENSITIVE_DATA_READ`.

---

## 2. Operational Admin & Coordinator Guide (`ADMIN`)

### 2.1 Reviewing Volunteer & SLI Applications (`Volunteer Coordinator` / `SLI Coordinator`)
Navigate to **`/admin` -> Volunteers** or **`/admin` -> Sign Language Interpreters**:
1. Filter applications by status (`SUBMITTED`, `UNDER_REVIEW`, `RESUBMITTED`, `WAITLISTED`, `APPROVED`, `ADDITIONAL_INFO_REQUIRED`, `REJECTED`), MSU affiliation, primary role, Gujarat residency (`based_in_gujarat`), or qualification category.
2. Open an application to review qualifications, RCI CRR number (if provided), validated video demo link, and shift availability across Days 1–3.
3. **Recording a Decision:**
   - Select the target status (`UNDER_REVIEW`, `APPROVED`, `WAITLISTED`, `ADDITIONAL_INFO_REQUIRED`, `REJECTED`, or `CANCELLED`).
   - **Internal Review Note (`reason`):** Stored solely for staff audit (`registrations.review_notes` / `registration_status_history.reason`) — **never** sent to the applicant.
   - **Participant-Facing Message (`publicMessage`):** Included in the automated transactional email and in-app dashboard notification (required when requesting `ADDITIONAL_INFO_REQUIRED`).
4. When an applicant in `ADDITIONAL_INFO_REQUIRED` updates and saves their application from `/dashboard`, their status automatically transitions to `RESUBMITTED` for re-review.

### 2.2 Managing Accessibility & Accommodation Requests (`Accessibility Coordinator`)
Navigate to **`/admin` -> Accommodations** (requires `accommodations.read` and `accommodations.manage`):
1. View participant accommodation requests across all 3 categories (`ATTENDEE`, `VOLUNTEER`, `SLI`), including decrypted free-text support details and personal assistant/escort information.
2. Update fulfillment status (`REQUESTED -> UNDER_REVIEW -> APPROVED -> ASSIGNED -> FULFILLED` or `UNABLE_TO_FULFILL`) and save encrypted coordinator notes. Leaving the coordinator note box blank preserves the existing encrypted note (`FND-40`).
3. Every status change automatically notifies the participant via email (`ACCESSIBILITY_REQUEST_STATUS_UPDATED`) and in-app notification.

### 2.3 Scheduling Venues, Programme Sessions & Duty Assignments (`Programme Coordinator`)
Navigate to **`/admin` -> Programme & Venues** and **`/admin` -> Duty Roster**:
1. **Venues:** Set explicit `confirmationStatus` (`CONFIRMED`, `PROPOSED`, `PENDING`, `ARCHIVED`). Only `CONFIRMED` venues appear as confirmed on the public `/programme` page; `PROPOSED`/`PENDING` venues are automatically labeled *"Proposed / Subject to Confirmation"*.
2. **Programme Sessions:** Only sessions with status `CONFIRMED` or `PUBLISHED` appear on the public website and participant dashboard (`DRAFT` and `CANCELLED` are hidden from the public). Check *"Notify assigned participants"* when rescheduling a live session to enqueue `PROGRAMME_CHANGE_NOTICE`.
3. **Volunteer & SLI Duty Assignments:**
   - Only `APPROVED` Volunteers and SLIs can be assigned to a duty shift (`HTTP 400` if unapproved).
   - **Overlap Detection:** If an applicant is already assigned to an overlapping time window on the same date (`dutyDateIst`), the system blocks the assignment (`HTTP 409 DUTY_OVERLAP`) and displays the conflicting shift. If an emergency double-assignment is intentional, enter a mandatory **Override Reason** to proceed (audited under `DUTY_ASSIGNMENT_OVERLAP_OVERRIDDEN`).
   - **2-SLI Rotation Pairing:** For sessions requiring Indian Sign Language interpretation (`islRequired = true`), assign a pair of interpreters sharing a `pairingGroupId` to support 20–30 minute co-interpretation rotation.

### 2.4 Communications Center & Safe CSV Exports (`Communications Manager` / `Registration Manager`)
- **Announcements & Broadcasts (`/admin` -> Communications Center):** Publish website banners (`INFO`, `WARNING`, `CRITICAL`) and optionally enqueue `FESTIVAL_IMPORTANT_INFO` or `FESTIVAL_REMINDER` broadcasts to `ALL`, `ATTENDEE`, `VOLUNTEER`, or `SLI` audiences.
- **Email Queue Inspection & Retry:** Inspect `QUEUED`, `PROVIDER_ACCEPTED`, `DELIVERED`, `RETRY_PENDING`, `BOUNCED`, and `FAILED` messages. Use **Retry** for transient failures or **Resend** (with mandatory audit reason) to issue a fresh copy of non-secret transactional emails.
- **CSV Export:** Click **Export CSV** on the Registrations tab (requires `registrations.export`; including accommodation columns requires `accommodations.read`). All exported cells are automatically sanitized against spreadsheet formula injection.

---

## 3. Registration Desk Operator Guide (`RGST` — `/rgst`)

### 3.1 Event-Day Check-In Workflow (Zero QR Scanning)
1. Sign in at **`/staff/login`** with your `RGST` username and password to open **`/rgst`**.
2. **Search Participant:** Type the participant's Registration ID (e.g. `PFG-2027-000001` or `1`), full name, or phone digits into the search box (`Alt+S` / `/` shortcut), or filter by Category (`ATTENDEE`, `VOLUNTEER`, `SLI`) and Eligibility (`ELIGIBLE`).
3. **Verify Eligibility Badge:**
   - Only participants with status **`CONFIRMED`** (Attendees) or **`APPROVED`** (Volunteers / SLIs) have active attendance checkboxes.
   - For privacy protection, `/rgst` displays only desk-safe identification fields (`Registration ID`, `Full Name`, `City/State`, `Phone Last 4 Digits`, `Category`, `Status`, `Attending Days`) and never displays medical or disability records.
4. **Mark Daily Attendance (`Day 1: 21 Jan`, `Day 2: 22 Jan`, `Day 3: 23 Jan`):**
   - Check the box for the current festival day. The checkmark saves atomically to the server (`PRESENT`) and announces confirmation to screen readers.
   - Clicking an already-checked box from another terminal is safely deduplicated (`0` duplicate records).

### 3.2 Undoing an Accidental Mark (`D-03` 15-Minute Rule)
- **Within 15 Minutes of Your Own Mark:** Uncheck the box, enter a brief mandatory reason (`>= 5` characters, e.g. *"Clicked row above by mistake"*), and confirm. The record is marked `REVOKED` and logged in `attendance_correction_logs`.
- **After 15 Minutes or Marked by Another Desk Operator:** Desk operators cannot undo the mark (`HTTP 403`). Contact a Floor Supervisor (`ADMIN` with `registrations.write` or `SUPERADMIN`) to perform an audited administrative override.

### 3.3 Handling Walk-In Visitors (`D-04`)
- Desk operators (`RGST`) cannot create accounts or approve applications from `/rgst`.
- Direct walk-in General Attendees to register on their mobile phone (or the venue self-service registration kiosk) at **`/register`** -> **`/apply/attendee`**. Once submitted, their `PFG-2027-NNNNNN` record appears immediately in `/rgst` search as `CONFIRMED`.

---

## 4. Technical Administrator Guide (Bluehost cPanel, CLI & Incident Response)

### 4.1 Essential CLI Commands (Run via Bluehost SSH using `/usr/local/bin/ea-php82`)

| Task | Command |
|---|---|
| **Verify Hosting Environment & DB** | `php scripts/verify-bluehost-env.php` |
| **Check Migration & Checksum Status** | `php database/migrate.php --status --verify` |
| **Apply Pending Migrations & Seed** | `php database/migrate.php --up --seed` |
| **Process Notification Queue Batch Manually** | `php scripts/process-notification-queue.php --batch=25 --max-seconds=45 --lock-ttl=300 --drain --json` |
| **Build Clean Release Package (Local/CI)** | `php scripts/build-release-package.php --out=tests/tmp/release-package --json` |
| **Backup Database Outside Webroot** | `mysqldump --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 -u <user> -p <db> > ~/pfg27_backups/pfg27_$(date +%F_%H%M).sql` |

### 4.2 Monitoring & Troubleshooting the Notification Queue
- **Logs:** Tail `/home1/<cpanel_user>/pfg27_logs/notification_worker.log`.
- **Stuck `PROCESSING` Rows:** If a Bluehost PHP worker process is terminated mid-batch, its lease (`locked_until`, default 300s) expires automatically and the next cron run recovers it via `pfgRecoverAbandonedNotifications()`.
- **`UNVERIFIED_DOMAIN` / `AUTH_CONFIG` Failures:** Confirm `RESEND_API_KEY` and `RESEND_FROM_EMAIL` in `/home1/<cpanel_user>/.pfg27_env.php` match the domain verified in Resend DNS (`D-22`).
- **Emergency Outbound Email Pause:** Set `'MAIL_DRIVER' => 'disabled'` in `/home1/<cpanel_user>/.pfg27_env.php` (see [ROLLBACK_PLAN.md](ROLLBACK_PLAN.md)).
