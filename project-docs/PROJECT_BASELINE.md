# Project Baseline — Purple Festival Gujarat 2027 (Phase 0)

**Document Status:** Phase 0 Baseline (Revalidated 2026-11-26)  
**Repository Root:** `d:\purplefestgujarat`  
**Stack (Retained by Directive):** Native PHP 8.3 + MySQL (InnoDB, `utf8mb4`) + PDO + Vanilla JavaScript + HTML5 + CSS3 on Bluehost Apache / cPanel  
**Zero Application Code Modified in Phase 0:** Verified via `git status` & `git diff`

---

## 1. Repository & Git Baseline (Revalidated vs. Audit Commit)

| Attribute | Current Verified State (2026-11-26) | Change Since Forensic Audit? |
|---|---|---|
| **Git Branch** | `main` | Unchanged |
| **HEAD Commit Hash** | `5740391643b6730db9d7731d2b73370689aa2748` | Unchanged (same commit as forensic audit) |
| **Working Tree (`git status --short`)** | `M includes/mailer.php` (1 trailing blank line only); `?? bg1.png`; `?? "Inclusive Viksit Bharat 2047 Vision.png"`; `?? "Understanding Disability Access Guide.png"`; `?? audit/`; `?? project-docs/` | Only non-public `audit/` & `project-docs/` documentation directories added; **zero application code edits** |
| **Tracked Files** | 124 files (`44` `.php`, `8` `.js`, `4` `.css`, `2` `.sql`, `8` config/deploy/docs, `58` static assets; ~7.3 MB) | Unchanged |
| **Untracked / Ignored Legacy Workspace Bloat** | ~809 MB (`node_modules/` 595 MB, `.next/` 67.7 MB, `data/pgdata/` 29 MB, 98 MB raw organizer documents, 37 raw images, `.env.local` [unread]) | Unchanged (excluded from `.cpanel.yml`, must never deploy) |

---

## 2. Runtime, Database & Tooling Baseline

| Component | Local Workstation (`Windows` — Native XAMPP) | Bluehost Staging (`purplefestivalgujarat.ajeetgraphics.com`) | Production (Target Main Domain) |
|---|---|---|---|
| **PHP Runtime** | **Native `PHP 8.2.12`** (`C:\xampp\php\php.exe`) with `pdo_mysql`, `pdo_sqlite`, `mysqli`, `mbstring`, `curl`, `openssl`, `gd`, `fileinfo`, `json`, `session` | Apache + PHP 8.x (CI lints PHP 8.3) | Not yet deployed |
| **MySQL / MariaDB** | **Native `MariaDB 10.4.32`** (`C:\xampp\mysql\bin\mysqld.exe`, InnoDB `utf8mb4_unicode_ci`) — supports isolated `pfg27_dev` and `pfg27_test` databases | MySQL (`tstpllmy_pfg27`) reachable (`GET /api/auth/me` -> 200), **BUT missing active `festival_editions` row** (`GET /api/registrations` -> `HTTP 404`) | Not yet deployed |
| **Web Server** | **Native `Apache 2.4.58`** (`C:\xampp\apache\bin\httpd.exe`) + PHP built-in CLI server for fast isolated API tests (**ZERO Docker / containers required**) | Bluehost Apache + cPanel | Bluehost Apache + cPanel |
| **Test Tooling (Dev-Only)** | `node v22.18.0`, `npm 11.9.0`, `python 3.11.9`, native PHP CLI test runner (`tests/run.php`) — zero production hosting dependencies | N/A | N/A |
| **Database Schema & Migrations** | Declarative `database/schema.sql` (57 InnoDB tables, 9 FKs, 0 ENUM/CHECK, 6 duplicate table pairs) + `database/seed.sql` (drifts from `includes/database.php`). **0 versioned migrations.** | Live DB reachable but unseeded/mismatched `festival_editions` -> registrations broken (`404`) | Not yet provisioned |
| **HTTPS & Security Headers** | N/A | HTTPS responds `200`, **HTTP is NOT redirected** (`HTTP 200`); omits `HSTS`, `CSP`, `Permissions-Policy`; exposes `/README.md` & `/docs/*.md` (`HTTP 200`) | Pending domain confirmation (`D-22`) |
| **External Integrations** | None active locally | Resend API (`RESEND_API_KEY` set in external `.pfg27_env.php` per user, sending domain unverified); `api.qrserver.com` (P0 bearer token leak); **0 SMS / 0 WhatsApp / 0 Razorpay** | Pending verified production domain |
| **Automated Tests** | **0 automated tests** in repository | None | None |

---

## 3. Verification of Accessed Documents (Sections 2.1 & 2.2)

### 3.1 Festival & Operational Source Documents — ALL Accessed & Extracted in Full
1. `Purple Festival Gujarat 2027 Brochure (3).pdf` — **Accessed (all 6 pages extracted & read)**
2. `Purple_Festival_Gujarat_2027_Action_Plan.pptx` — **Accessed (all 17 slides + all 17 speaker notes extracted & read)**
3. `Purple_Festival_Gujarat_2027_Programme_Grid.xlsx` — **Accessed (all 8 worksheets, all 32 proposed sessions, all 10 venues, all formulas, `Grid Day 1/2/3`, and `Interpreters & Volunteers` hourly calculations extracted & read)**
4. `Red and white General Sport Activities Presentations.pdf` — **Accessed (all 27 pages extracted & read — verified as MSU Dept. of Physical Education general institutional sports infrastructure brochure, NOT festival para-sports programme)**
5. `VOLUNTEER AND SLI.docx` — **Accessed (all 52 paragraphs / Form 1 & Form 2 read verbatim)**
6. `WEBSITE PFV.docx` — **Accessed (all 29 lines read verbatim)**
7. *Supplementary workspace documents also inspected for conflict detection:* `VOLUNTEER AND SLI 1.docx` (55 lines — diverges materially from `VOLUNTEER AND SLI.docx`!), `Delegate Registration Form.docx` (115 lines — full 6-section general registration form + office-use table), `LIST OF ACTIVITIES.docx` (23 lines — Goa SCPwD/DEPwD 4-pillar component proposal).

### 3.2 Previous Forensic Audit Deliverables — ALL 14 Accessed & Verified in `d:\purplefestgujarat\audit\`
1. `MASTER_CODEBASE_AUDIT.md` · 2. `FEATURE_IMPLEMENTATION_MATRIX.csv` (42 features) · 3. `API_ENDPOINT_INVENTORY.csv` (21 PHP APIs) · 4. `REGISTRATION_FIELD_INVENTORY.csv` (56 fields) · 5. `ROLE_PERMISSION_MATRIX.csv` (25 resources) · 6. `NOTIFICATION_TRIGGER_MATRIX.csv` (26 events) · 7. `DATABASE_INVENTORY.md` (57 tables) · 8. `WORKFLOW_DIAGRAMS.md` (5 pathways + 6 state machines) · 9. `TEST_EXECUTION_RESULTS.csv` (38 cases: 3 PASS, 7 FAIL, 28 NOT RUN) · 10. `PREVIOUS_CLAIMS_VERIFICATION.csv` (30 core + 4 doc claims) · 11. `SECURITY_PRIVACY_FINDINGS.md` (46 findings: 12 P0, 12 P1, 12 P2, 6 P3, 4 P4) · 12. `QR_DEPENDENCY_ANALYSIS.md` · 13. `FUTURE_ARCHITECTURE_GAP_ANALYSIS.md` · 14. `OWNER_DECISIONS_REQUIRED.md`.

