# Final Release Acceptance Report — Purple Festival Gujarat 2027

**Release Candidate Branch:** `swarm/phases-5-18` (local only — NOT pushed; `main` untouched at `140f2a0`)  
**Verified Release Candidate Commit:** `0ba63ff` (Local Freeze Tag: `v2027.0-rc1-locally-verified`)  
**Database Schema Version:** `012_final_release_hardening` (`70` tables, `79` foreign keys, `13 / 13` migrations applied & verified, `0` pending)  
**Verified Release Archive:** `d:\purplefestgujarat\tests\tmp\purplefestgujarat-rc-0ba63ff.zip` (`8,640,608` bytes, `186` runtime files, SHA-256 `456d6ec4c36a0da0a9151562d07a603655412a31e3bf4c5fad2a878025fbadbd`)

---

## 1. Final Verification Summary (`118 / 118` Automated Checks PASS)

| Verification Suite | Command Executed | Result | Evidence Artifact |
|---|---|---|---|
| **PHP / MariaDB Full-System & Security Suite** | `C:\xampp\php\php.exe tests/run.php` | **`63 / 63` PASS (`0` FAIL)** | `tests/tmp/phase17_php_results.json` |
| **Native Apache `2.4.58` Containment & Routing** | `C:\xampp\php\php.exe tests/apache_verification.php` | **`40 / 40` PASS (`0` FAIL)** | `tests/tmp/phase17_apache_results.json` |
| **Playwright Chrome + Microsoft Edge E2E & WCAG** | `node tests/phase17_browser_e2e.mjs` | **`6 / 6` PASS (`0` FAIL)** | `tests/tmp/phase17_e2e_results.json` |
| **Isolated Built Release Package Runtime** | `C:\xampp\php\php.exe tests/phase18_release_package_test.php` | **`9 / 9` PASS (`0` FAIL)** | `tests/tmp/phase18_package_verification.json` |
| **Bluehost Env Pre-Flight (Local)** | `C:\xampp\php\php.exe scripts/verify-bluehost-env.php --skip-network` | **`RESULT: PASSED`** | `70` tables, `13/13` migrations, `+05:30 IST` |
| **Migration Status & Checksum Verify** | `php database/migrate.php --status` & `--verify` | **`VERIFY PASSED`** | `13/13` applied, `0` pending, `0` mismatches |
| **Clean Release Package & Zip Builder** | `php scripts/build-release-package.php --zip --json` | **`PASSED`** | `186` files, `96/96` PHP lint, `0` leaks |

Full per-test audit log: [FINAL_REGRESSION_RESULTS.csv](FINAL_REGRESSION_RESULTS.csv).

---

## 2. Final Release Hardening Controls Implemented & Verified (`T5-10`, `E2E-06`)

1. **46-Finding Security Reconciliation (§4):** Resolved the 45-vs-46 count discrepancy and removed unsupported `DEFERRED WITH APPROVAL` labels in [FINAL_SECURITY_RECONCILIATION.csv](FINAL_SECURITY_RECONCILIATION.csv): exactly **46** findings accounted for (`38` `FIXED LOCALLY`, `4` `ELIMINATED THROUGH VERIFIED DECOMMISSIONING`, `1` `SAFELY CONTAINED`, `1` `PARTIALLY REMEDIATED`, `2` `CONFIGURABLE AND INACTIVE / REQUIRES OWNER DECISION`).
2. **Granular Least-Privilege Notification RBAC (§5.1):** Added `notifications.read`, `notifications.retry`, `notifications.resend`, `notifications.auth.manage` (Superadmin-only, forbidden in reusable profiles), and `attendance.override` via Migration `012`. Verified `content_manager` & `volunteer_coordinator` get `403`, `registration_manager` gets read-only (`200` GET / `403` POST), and `communications_manager` cannot view or act on auth-token notifications (`403` + filtered out). See [FINAL_ADMIN_PERMISSION_VERIFICATION.csv](FINAL_ADMIN_PERMISSION_VERIFICATION.csv).
3. **Legacy Unprofiled Admin Audit Visibility (§5.2):** Added `pfgIsLegacyUnprofiledAdmin()` + `isLegacyUnprofiledAdmin` flag in `getCurrentUser()` and documented the Superadmin profile migration procedure in [BLUEHOST_STAGING_CUTOVER_GUIDE.md](BLUEHOST_STAGING_CUTOVER_GUIDE.md).
4. **Staff Self-Approval Block (§5.3):** `PATCH`/`POST /api/admin/registrations.php` rejects any staff attempt to review, approve, or modify their own personal registration (`403 SELF_APPROVAL_BLOCKED` + immutable `SELF_APPROVAL_BLOCKED` audit entry).
5. **Centralized Registration Launch Activation Gate (§6):** Added `REGISTRATION_PUBLIC_OPEN` / `system_settings.registration_public_open` guard (`503 registrationClosed: true` when closed; defaults closed in staging/production until explicitly enabled after owner policy sign-off). Audited all **80** real implemented fields in [FINAL_REGISTRATION_FIELD_INVENTORY.csv](FINAL_REGISTRATION_FIELD_INVENTORY.csv).
6. **Assigned-Participant Withdrawal Coordinator Alerts (§7):** Withdrawing an `ASSIGNED` participant now marks active `duty_assignments` as `CANCELLED` (preserving history), resets `registrations.assignment_status = 'UNASSIGNED'`, and inserts `in_app_notifications` (`assigned_participant_withdrawn`) for Superadmins & matching coordinators.
7. **Configurable IST Event-Day Attendance Window (§8):** Added `ATTENDANCE_ENFORCE_EVENT_DAY_WINDOW` (default active in staging/production). Ordinary `RGST` operators are blocked (`403`) from marking future IST festival dates ahead of time; `SUPERADMIN` / `attendance.override` may execute an audited exception (`overrideFutureDate: true` + `overrideReason >= 10 chars` -> `ATTENDANCE_FUTURE_DATE_OVERRIDE`).
8. **Stale / Expired / Used / Superseded Auth Token Guard (§9):** `pfgDispatchClaimedNotification()` checks DB token freshness (`email_verification_tokens` / `password_reset_tokens`) before dispatch; expired/used/superseded auth jobs are never sent, immediately wipe `ephemeral_secret_enc = NULL`, mark `CANCELLED` (`TOKEN_EXPIRED_OR_SUPERSEDED`), and log `STALE_AUTH_CANCELLED`. Abandoned terminal failures also wipe `ephemeral_secret_enc = NULL`.
9. **Database Migration Classification (§10):** Accurately classified all `13` migrations (`000`–`012`) in [DATABASE_MIGRATION_HISTORY.md](DATABASE_MIGRATION_HISTORY.md), explicitly flagging Migration `004` (historical encryption/redaction) as requiring pre-migration `mysqldump` backup restoration rather than reverse SQL.
10. **Chrome + Microsoft Edge Accessibility & Reflow (`E2E-06`, §12):** Verified 200% (`640x900`) and 400% (`320x640`) reflow, keyboard Tab navigation, and modal focus trap + Escape focus restoration across BOTH Google Chrome and Microsoft Edge (`0` overflow, `8/8` routes `HC=21:1` & `YB=17:1`). Firefox and human AT (NVDA, JAWS, VoiceOver, TalkBack) remain honestly marked `NOT RUN` in [MANUAL_ACCESSIBILITY_ACCEPTANCE_CHECKLIST.md](MANUAL_ACCESSIBILITY_ACCEPTANCE_CHECKLIST.md).
11. **Public Website Visual Preservation (§13):** Direct side-by-side visual inspection of `baseline/` vs `post_phase15/` screenshots (`home_desktop.png`, `programme_desktop.png`, `accessibility_desktop.png`, `apply_hub_desktop.png`, `rgst_desk_desktop.png`) confirmed the approved public visual identity (Hero, Viksit Bharat@2047, Six Pillars, 21 Disabilities, `#5B2A86` Partners, Illustrated Crowd Footer) is 100% preserved.
12. **Account-Agnostic Bluehost Subdomain Env Loader & CI Sync (§14, §20):** Removed hardcoded `/home1/tstpllmy` path from `includes/config.php` in favor of dynamic `PFG_ENV_FILE` / parent / grandparent / `$HOME` discovery (proven live in `P18-04` with `PFG_ENV_FILE` unset!), updated `.github/workflows/deploy-bluehost-php.yml` to build via `scripts/build-release-package.php`, and verified pushing to `main` triggers the GitHub Release build workflow (no push executed).

