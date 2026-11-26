# Swarm File Ownership Map (Conflict-Free Parallel Execution)

| Owner / Worker | Workspace Mode | Exclusive Owned Files / Directories | Shared Integration Owner |
|---|---|---|---|
| **Master Orchestrator (`coordinator`)** | Main working tree (`swarm/phases-5-18`) | `project-docs/swarm/*`, `.htaccess`, `tests/router.php`, branch merges, milestone tags | Merges all worktrees & resolves any shared config/test registration |
| **Agent B (`db-architect`)** | `share` | `database/migrations/007_*`..`010_*`, `includes/migrations.php`, `database/seed.php` | Coordinator merges first before Wave 2 feature workers |
| **Agent K (`a11y-core`)** | `share` | `assets/css/app.css`, `assets/js/accessibility.js`, `includes/disabilities-section.php` | Coordinator reviews header/footer hooks |
| **Agent E (`auth-eng`)** | `share` | `api/auth/signup.php`, `api/auth/verify-email.php`, `api/auth/resend-verification.php`, `api/auth/claim-legacy.php`, `api/auth/login.php`, `api/account/profile.php`, `login.php`, `register.php`, `verify-email.php`, `forgot-password.php`, `reset-password.php`, `staff/login.php`, `auth/login.php`, `assets/js/auth-pages.js` | Must NOT touch `includes/migrations.php` or `apply/*` |
| **Agent F (`reg-eng`)** | `share` | `includes/registration-validators.php`, `includes/application-lifecycle.php`, `includes/form-components/*`, `api/registrations/index.php`, `api/account/registrations.php`, `api/account/drafts.php`, `apply/*`, `assets/js/shared-accessibility.js`, `assets/js/category-forms.js` | Must NOT touch `api/auth/*` or `rgst/*` |
| **Agent I (`rgst-eng`)** | `share` | `api/attendance/*`, `rgst/index.php`, `assets/js/rgst-desk.js` | Must NOT touch `admin/*` or `apply/*` |
| **Agent H/J (`admin-eng`)** | `share` | `api/admin/permission-profiles.php`, `api/admin/users.php`, `api/admin/venues.php`, `api/admin/programme.php`, `api/admin/assignments.php`, `api/admin/announcements.php`, `api/admin/overview.php`, `api/admin/registrations.php`, `api/admin/accommodations.php`, `admin/index.php`, `assets/js/admin-portal.js` | Coordinates `includes/auth.php` profile helper cleanly |
| **Agent G (`portal-eng`)** | `share` | `dashboard/index.php`, `api/account/dashboard.php`, `api/account/notifications.php`, `assets/js/participant-dashboard.js` | Must NOT touch `admin/*` or public `index.php` |
| **Agent Public (`public-eng`)** | `share` | `index.php`, `programme.php`, `accessibility.php`, `includes/header.php`, `includes/footer.php`, `legal/*` | Preserves visual design (`CAD-08`); verified against baseline screenshots |
| **Agent M (`reviewer`)** | `inherit` (read-only) | `project-docs/swarm/REVIEW_REPORTS/*` | Independent second-model family (`Claude Opus 5.5 High`) |
| **Agent L (`qa-eng`)** | `share` | `tests/phase5_*`..`tests/phase17_*`, `tests/run.php`, `tests/apache_verification.php` | Runs full unified verification on isolated `pfg27_test` |
| **Agent N/O (`release-docs`)** | `share` | `scripts/build-release-package.php`, `project-docs/*.md`, `project-docs/*.csv` | Consolidates final launch register & operations handover |
