# Email Template Inventory — Purple Festival Gujarat 2027 (29 Templates)

**Authoritative Registry:** `includes/notification-templates.php` (`pfgGetEmailTemplateCatalog()`)  
**Machine-Readable Trigger Matrix:** [NOTIFICATION_TRIGGER_MATRIX.csv](NOTIFICATION_TRIGGER_MATRIX.csv)  
**Implemented & Render-Verified:** `29 / 29` (`T4-02`)  
**Triggers Currently Connected:** `20 / 29` (`CONNECTED`) · **Future-Phase Triggers Not Yet Connected:** `9 / 29` (`FUTURE_PHASE_5`: `5`, `FUTURE_PHASE_6`: `1`, `FUTURE_PHASE_12`: `1`, `FUTURE_PHASE_13`: `2`)

---

## Complete 29-Template Classification

| # | Template Code | Group | Trigger Event | Required Vars | Trigger Status | Live Resend Status |
|---:|---|---|---|---|---|---|
| 1 | `ACCOUNT_VERIFICATION` | Account | `account.verification_requested` | `fullName`, `actionUrl` (ephemeral) | `FUTURE_PHASE_5` | `CONFIGURATION BLOCKED` |
| 2 | `EMAIL_VERIFICATION_RESEND` | Account | `account.verification_resent` | `fullName`, `actionUrl` (ephemeral) | `FUTURE_PHASE_5` | `CONFIGURATION BLOCKED` |
| 3 | `ACCOUNT_WELCOME_ACTIVATED` | Account | `account.activated` | `fullName` | `FUTURE_PHASE_5` | `CONFIGURATION BLOCKED` |
| 4 | `PASSWORD_RECOVERY` | Account | `auth.password_recovery_requested` | `fullName`, `actionUrl` (ephemeral) | **`CONNECTED`** (`api/auth/password-reset.php`) | `CONFIGURATION BLOCKED` |
| 5 | `PASSWORD_RESET_CONFIRMATION` | Account | `auth.password_reset_completed` | `fullName` | **`CONNECTED`** (`api/auth/password-reset.php`) | `CONFIGURATION BLOCKED` |
| 6 | `EMAIL_CHANGE_VERIFICATION` | Account | `account.email_change_requested` | `fullName`, `actionUrl` (ephemeral) | `FUTURE_PHASE_5` | `CONFIGURATION BLOCKED` |
| 7 | `SECURITY_ALERT` | Account | `auth.security_notification` | `fullName`, `securityEventSummary` | `FUTURE_PHASE_5` | `CONFIGURATION BLOCKED` |
| 8 | `ATTENDEE_REGISTRATION_CONFIRMED` | Attendee | `attendee.registration_confirmed` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/registrations/index.php`, `api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 9 | `ATTENDEE_REGISTRATION_UPDATED` | Attendee | `attendee.registration_updated` | `fullName`, `registrationCode` | `FUTURE_PHASE_6` | `CONFIGURATION BLOCKED` |
| 10 | `ATTENDEE_REGISTRATION_CANCELLED` | Attendee | `attendee.registration_cancelled` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 11 | `VOLUNTEER_APPLICATION_RECEIVED` | Volunteer | `volunteer.application_received` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/registrations/index.php`) | `CONFIGURATION BLOCKED` |
| 12 | `VOLUNTEER_APPLICATION_APPROVED` | Volunteer | `volunteer.application_approved` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 13 | `VOLUNTEER_APPLICATION_REJECTED` | Volunteer | `volunteer.application_rejected` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 14 | `VOLUNTEER_APPLICATION_WAITLISTED` | Volunteer | `volunteer.application_waitlisted` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 15 | `VOLUNTEER_INFO_REQUESTED` | Volunteer | `volunteer.info_requested` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 16 | `VOLUNTEER_ASSIGNMENT_CREATED` | Volunteer | `volunteer.assignment_created` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 17 | `VOLUNTEER_ASSIGNMENT_UPDATED` | Volunteer | `volunteer.assignment_updated` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 18 | `SLI_APPLICATION_RECEIVED` | SLI | `sli.application_received` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/registrations/index.php`) | `CONFIGURATION BLOCKED` |
| 19 | `SLI_APPLICATION_APPROVED` | SLI | `sli.application_approved` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 20 | `SLI_APPLICATION_REJECTED` | SLI | `sli.application_rejected` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 21 | `SLI_APPLICATION_WAITLISTED` | SLI | `sli.application_waitlisted` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 22 | `SLI_INFO_REQUESTED` | SLI | `sli.info_requested` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 23 | `SLI_ASSIGNMENT_CREATED` | SLI | `sli.assignment_created` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 24 | `SLI_ASSIGNMENT_UPDATED` | SLI | `sli.assignment_updated` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/admin/registrations.php`) | `CONFIGURATION BLOCKED` |
| 25 | `ACCESSIBILITY_REQUEST_RECEIVED` | Accessibility | `accessibility.request_received` | `fullName`, `registrationCode` | **`CONNECTED`** (`api/account/accommodations.php`) | `CONFIGURATION BLOCKED` |
| 26 | `ACCESSIBILITY_ARRANGEMENT_UPDATED` | Accessibility | `accessibility.arrangement_updated` | `fullName`, `registrationCode`, `arrangementStatus` | **`CONNECTED`** (`api/admin/accommodations.php`) | `CONFIGURATION BLOCKED` |
| 27 | `FESTIVAL_IMPORTANT_INFO` | Operational | `festival.important_information` | `fullName`, `announcementTitle`, `announcementBody` | `FUTURE_PHASE_13` | `CONFIGURATION BLOCKED` |
| 28 | `PROGRAMME_CHANGE_NOTICE` | Operational | `festival.programme_changed` | `fullName`, `changeSummary` | `FUTURE_PHASE_12` | `CONFIGURATION BLOCKED` |
| 29 | `FESTIVAL_REMINDER` | Operational | `festival.event_reminder` | `fullName`, `registrationCode` | `FUTURE_PHASE_13` | `CONFIGURATION BLOCKED` |

> **Honesty Note:** Creating a template does **not** mean its future-phase trigger is active. Templates `#1, #2, #3, #6, #7, #9, #27, #28, #29` are rendered and verified locally (`T4-02`), while their business-event triggers remain cleanly scoped to Phases 5, 6, 12, and 13.
