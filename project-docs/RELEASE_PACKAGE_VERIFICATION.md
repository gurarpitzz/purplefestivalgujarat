# Release Package Verification Report — Purple Festival Gujarat 2027

**Release Candidate Commit:** `0ba63ff` (Integration Branch: `swarm/phases-5-18`, Local Freeze Tag: `v2027.0-rc1-locally-verified`)  
**Packager Script:** `scripts/build-release-package.php`  
**Isolated Runtime Verification Harness:** `tests/phase18_release_package_test.php` (**`9 / 9` PASS, `0` FAILED**)  
**Verification Classification:** **`VERIFIED WORKING LOCALLY`** *(Isolated runtime verified from actual built package; Bluehost live deployment remains `CONFIGURATION BLOCKED` until separately authorized)*

---

## 1. Verified Release Archive & Manifest

| Artifact | Location (Outside Webroot / Gitignored `tests/tmp/`) | Size / Count | Integrity |
|---|---|---:|---|
| **Release Archive (`.zip`)** | `d:\purplefestgujarat\tests\tmp\purplefestgujarat-rc-0ba63ff.zip` | `8,640,608` bytes (`186` entries) | SHA-256 `456d6ec4c36a0da0a9151562d07a603655412a31e3bf4c5fad2a878025fbadbd` (`PharData` PKZIP verified) |
| **Release Manifest** | `d:\purplefestgujarat\tests\tmp\RELEASE_MANIFEST.json` | `186` files (`8,607,826` raw bytes) | Per-file SHA-256 checksums, `manifestOutsideWebroot: true` |
| **Packaged PHP Syntax Lint** | All `96` `.php` runtime files inside package | `96 / 96` PASS | `0` syntax errors (`PHP 8.2.12`) |
| **Excluded Non-Runtime Items** | `docs/`, `audit/`, `project-docs/`, `tests/`, `.git/`, `.github/`, `.next/`, `node_modules/`, `data/`, raw `.docx`/`.pdf`/`.pptx`/`.xlsx`, `.env*`, root scratch PNGs, retired `html5-qrcode.min.js` & `registration-wizard.js` | `26,423` items audited & excluded | **`0` excluded leaks** (`leakedFiles: []`) |

---

## 2. Independent Isolated Runtime Verification (`tests/phase18_release_package_test.php` — `9 / 9` PASS)

Instead of testing only the development repository, the built `186`-file package was extracted into an isolated directory (`tests/tmp/rc-pkg`), paired with a simulated external parent `.pfg27_env.php` (`tests/tmp/.pfg27_env.php`, strictly **outside** the document root, with `PFG_ENV_FILE` unset to prove §14 dynamic parent discovery), and booted via an independent PHP `8.2.12` HTTP server:

1. **Clean Package Build & Archive Generation (`P18-01`):** `186` included files, `0` leaks, `96/96` PHP lint pass, `.zip` archive created and verified.
2. **Archive & Manifest Parity (`P18-02`):** `100%` match between `.zip` entries, extracted package files, and `RELEASE_MANIFEST.json` SHA-256 hashes; zero internal docs, audit reports, tests, secrets, DB dumps, or `.next`/`node_modules` entries inside the `.zip`.
3. **Required Runtime Asset Presence (`P18-03`):** Verified all public pages (`index.php`, `programme.php`, `accessibility.php`, `login.php`, `register.php`, `verify-email.php`, `forgot-password.php`, `reset-password.php`, `staff/login.php`), portals (`apply/`, `dashboard/`, `admin/`, `rgst/`), APIs (`api/`), migrations (`database/migrations/000_*`..`012_*`), CLI scripts (`scripts/process-notification-queue.php`, `scripts/verify-bluehost-env.php`), CSS/JS/images/assets, `.htaccess`, and `.user.ini`.
4. **Dynamic Parent `.pfg27_env.php` Discovery (`P18-04`):** Confirmed `includes/config.php` loaded `tests/tmp/.pfg27_env.php` automatically with `PFG_ENV_FILE` unset and zero hardcoded `/home1/tstpllmy` paths.
5. **Packaged Public Pages & Static Assets (`P18-05`):** Live HTTP `200` on `/`, `/programme.php`, `/accessibility.php`, `/login.php`, `/register.php`, `/forgot-password.php`, `/staff/login.php`, `/apply/index.php`, `/assets/css/app.css`, `/assets/css/auth-pages.css`, `/assets/js/accessibility.js`, `/assets/js/rgst-desk.js`.
6. **Packaged API 3-Category Contract (`P18-06`):** `GET /api/registrations/index.php` returned HTTP `200` with strictly the `3` active primary categories (`ATTENDEE`, `VOLUNTEER`, `SLI`) and zero legacy categories.
7. **Packaged Auth/Portal Protection (`P18-07`):** Unauthenticated `/dashboard/index.php`, `/admin/index.php`, `/rgst/index.php` returned HTTP `302`; `/api/attendance/index.php`, `/api/account/dashboard.php`, `/api/admin/notifications.php` returned HTTP `401`.
8. **Packaged QR Discontinuation (`P18-08`):** `/api/qr/pass.php` returned `401` unauth and `410 Gone` authenticated (`deprecated: true`).
9. **Clean Teardown (`P18-09`):** Temporary extracted directory and external test env file removed cleanly; verified `.zip` archive and `RELEASE_MANIFEST.json` retained outside webroot.

