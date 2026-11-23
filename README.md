# Purple Festival Gujarat 2027 — Accessible Web Platform & Festival Management Ecosystem

> **21–23 January 2027 · Vadodara, Gujarat, India**
> *Celebrating Accessibility, Inclusion, Dignity and Diversity across all 21 benchmark disabilities under the Rights of Persons with Disabilities (RPwD) Act, 2016.*

---

## Table of Contents

1. [Architecture](#1-architecture)
2. [Tech Stack](#2-tech-stack)
3. [Prerequisites](#3-prerequisites)
4. [Installation](#4-installation)
5. [Environment Variables](#5-environment-variables)
6. [PostgreSQL & Docker Setup](#6-postgresql--docker-setup)
7. [Migrations & Seeding](#7-migrations--seeding)
8. [Superadmin Initialization](#8-superadmin-initialization)
9. [Adding Admin & RGST Staff](#9-adding-admin--rgst-staff)
10. [Registration Configuration](#10-registration-configuration)
11. [QR Scanning & RGST Operations](#11-qr-scanning--rgst-operations)
12. [CMS Management](#12-cms-management)
13. [Testing](#13-testing)
14. [Production Deployment](#14-production-deployment)
15. [Database Backup & Recovery](#15-database-backup--recovery)

---

## 1. Architecture

Purple Festival Gujarat 2027 is a unified, server-rendered Next.js 15 App Router platform backed by PostgreSQL (via Drizzle ORM). It serves four strictly isolated user personas with server-enforced RBAC:

| Portal / Surface | Route | Target Role(s) | Capabilities |
|---|---|---|---|
| **Public Festival Website** | `/`, `/programme`, `/accessibility`, `/apply`, `/legal/*` | Public / Anonymous | Festival info, 21 RPwD disability explorer, 3-day programme, multi-category registration wizard, WCAG 2.2 AA accessibility preferences panel |
| **Participant Dashboard** | `/dashboard` | `PARTICIPANT` | Registration status tracking, accessible QR entry pass & PDF download, accommodation request updates, volunteer/SLI duty assignment view |
| **RGST Reception Scanner** | `/rgst` | `RGST` (and `ADMIN`/`SUPERADMIN`) | Camera/USB/Manual QR verification, explicit check-in confirmation with IST day scoping, own-station attendance log. **Zero access to disability/medical disclosures, CMS, or admin data.** |
| **Admin & Superadmin Portal** | `/admin` | `ADMIN`, `SUPERADMIN` | Registration review, Volunteer & SLI approval/assignment, accommodation coordination, QR issuance/revocation, attendance analytics, XLSX/CSV exports, coupons, CMS management, audit logs, and staff role administration (`SUPERADMIN` only) |

### Key Architectural Guarantees
- **Zero-PII Opaque QR Credentials**: QR codes encode `PFG27.<24-byte-base64url-nonce>.<16-char-HMAC-SHA256-sig>`. Only the HMAC-SHA256 hash (`token_hash`) is stored in PostgreSQL. No name, email, or disability disclosure is embedded in the QR payload or returned to RGST scanners.
- **Database-Level Concurrency Safety**: A unique composite index `checkins_credential_scope_unique(credential_id, entitlement_scope)` prevents race-condition duplicate check-ins even when two gates scan the same pass simultaneously.
- **Independent Disability Disclosure**: Disability status (`YES` / `NO` / `PREFER_NOT_TO_SAY`) is completely decoupled from registration category (`ATTENDEE`, `DELEGATE`, `VOLUNTEER`, `SLI`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`).
- **Dual PostgreSQL Driver**: Connects to standard PostgreSQL (`pg`) when `DATABASE_URL` is configured, and seamlessly falls back to embedded PostgreSQL (`@electric-sql/pglite`) for zero-daemon local development and isolated in-memory (`memory://`) automated testing.

---

## 2. Tech Stack

- **Framework**: Next.js 15 (App Router, React 19, TypeScript 5.8)
- **Styling & Motion**: Tailwind CSS 4, GSAP 3 (choreographed preloader with mandatory **Skip Intro** & `prefers-reduced-motion` bypass), Framer Motion, Lucide Icons
- **Database & ORM**: PostgreSQL 16 (`pg` / `@electric-sql/pglite`) + Drizzle ORM & `drizzle-kit`
- **Authentication & Security**: `bcryptjs` (cost 12), HTTP-only `SameSite=Lax` HMAC-hashed DB sessions, `otplib` TOTP MFA for Admin/Superadmin accounts, Zod schema validation
- **QR & Documents**: `qrcode`, `html5-qrcode`, `pdf-lib` (accessible PDF passes), `xlsx` (export)
- **Testing & Accessibility**: Vitest (integration & security tests), Playwright + `@axe-core/playwright` (E2E & WCAG 2.2 AA audits)

---

## 3. Prerequisites

- **Node.js**: `>= 20.11.0` (LTS recommended)
- **npm**: `>= 10.0.0`
- **PostgreSQL 16** (optional for local dev thanks to embedded PGlite; required for staging/production) or **Docker Desktop** with Docker Compose

---

## 4. Installation

```bash
git clone <repository-url> purplefestgujarat
cd purplefestgujarat
npm install
cp .env.example .env.local
```

Generate cryptographically strong secrets for `AUTH_SECRET` and `QR_SIGNING_SECRET`:

```bash
openssl rand -hex 32
```

---

## 5. Environment Variables

Configure `.env.local` (development) or your hosting provider's secret manager (production). Never commit real secrets.

| Variable | Required | Description |
|---|---|---|
| `DATABASE_URL` | Production | PostgreSQL connection URI (e.g., `postgresql://user:pass@host:5432/purple_festival_gujarat_2027?sslmode=require`). Leave empty locally to use embedded PGlite. |
| `PGLITE_DATA_DIR` | Local only | Directory for embedded PGlite storage (default: `./data/pgdata`). Set to `memory://` for ephemeral test runs. |
| `NEXT_PUBLIC_APP_URL` | Yes | Canonical public URL (e.g., `https://purplefestivalgujarat.com` or `http://localhost:3000`). |
| `NODE_ENV` | Yes | `development`, `test`, or `production`. |
| `AUTH_SECRET` | **Yes** | 64-char hex secret for HMAC hashing session tokens. |
| `QR_SIGNING_SECRET` | **Yes** | 64-char hex secret for signing & hashing opaque QR credentials. |
| `SUPERADMIN_BOOTSTRAP_EMAIL` | One-time | Email for initial Superadmin bootstrap script. Remove after bootstrapping. |
| `SUPERADMIN_BOOTSTRAP_PASSWORD` | One-time | Strong password (`>= 12` chars) for initial Superadmin bootstrap. Remove after bootstrapping. |
| `SUPERADMIN_BOOTSTRAP_NAME` | Optional | Display name for initial Superadmin (default: `Festival Superadmin`). |
| `EMAIL_PROVIDER` | Optional | `resend` or `none` (queues notifications in PostgreSQL when `none`). |
| `RESEND_API_KEY` | If Resend | API key from Resend for transactional confirmation & approval emails. |
| `EMAIL_FROM` | If Resend | Verified sender address, e.g. `Purple Festival Gujarat 2027 <noreply@purplefestivalgujarat.com>`. |
| `RAZORPAY_KEY_ID` | Optional | Razorpay API Key ID. Paid flows remain disabled until all 3 Razorpay keys are set. |
| `RAZORPAY_KEY_SECRET` | Optional | Razorpay API Key Secret. |
| `RAZORPAY_WEBHOOK_SECRET` | Optional | Razorpay webhook HMAC secret for `/api/payments/webhook`. |
| `S3_ENDPOINT`, `S3_BUCKET`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_REGION` | Optional | Private S3-compatible object storage for uploaded volunteer/SLI documents (falls back to local private storage). |

---

## 6. PostgreSQL & Docker Setup

### Option A: Zero-Daemon Local Development (PGlite)
Leave `DATABASE_URL` empty in `.env.local` and keep `PGLITE_DATA_DIR=./data/pgdata`. No external database daemon is required.

### Option B: Local PostgreSQL 16 via Docker Compose
```bash
docker compose up -d postgres
```

Then set `DATABASE_URL` in `.env.local`:
```env
DATABASE_URL=postgresql://pfg_admin:pfg2027_change_me@localhost:5432/purple_festival_gujarat_2027
```

Check health:
```bash
docker compose ps
```

---

## 7. Migrations & Seeding

Apply Drizzle SQL migrations and seed festival reference data (roles, permissions, Gujarat 2027 edition, venues, check-in stations, all 21 RPwD disabilities, accommodation types, registration categories, programme schedule, CMS home sections, FAQs, and notification templates):

```bash
npm run db:migrate
npm run db:seed
```

Both commands are **idempotent** and safe to re-run.

---

## 8. Superadmin Initialization

No default admin credentials are hardcoded in the codebase or seed script. To initialize the first `SUPERADMIN` account safely:

1. Set `SUPERADMIN_BOOTSTRAP_EMAIL` and `SUPERADMIN_BOOTSTRAP_PASSWORD` (minimum 12 characters) in `.env.local` or your shell environment.
2. Run:
   ```bash
   npm run db:bootstrap-superadmin
   ```
3. The script creates the Superadmin account, assigns the `SUPERADMIN` role, writes a `SUPERADMIN_BOOTSTRAPPED` audit log entry, and exits. If a `SUPERADMIN` already exists, the script refuses to overwrite it and exits safely.
4. **Immediately remove** `SUPERADMIN_BOOTSTRAP_PASSWORD` from your environment and enable **TOTP MFA** after signing in at `/login`.

---

## 9. Adding Admin & RGST Staff

Only a `SUPERADMIN` (with `admin_accounts.manage`) can create or modify staff accounts:

1. Sign in at `/login` and navigate to `/admin` → **Staff & Roles**.
2. Create a new staff user with role:
   - **`ADMIN`**: Full operational access (registrations, Volunteer/SLI review, accommodation management, credential issuance/revocation, full attendance reports, coupons, exports). Cannot manage Superadmin/Admin accounts, CMS settings, or system configuration unless granted custom overrides.
   - **`RGST`**: Strictly limited to `qr.scan`, `checkins.confirm`, and `attendance.read.own`. Redirected automatically to `/rgst`.
3. Require all `ADMIN` and `SUPERADMIN` users to enroll in TOTP MFA via `/api/auth/mfa`.

---

## 10. Registration Configuration

Registration categories are stored in `registration_categories` and managed via `/admin`:
- **Instant Confirmation (`requiresApproval: false`)**: `ATTENDEE`, `DELEGATE`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`. Eligible registrations receive an active QR credential automatically upon confirmation (and payment capture if a fee is enabled).
- **Approval Required (`requiresApproval: true`)**: `VOLUNTEER` and `SLI`. Applications start in `SUBMITTED` status with **no active QR pass**. Once reviewed and moved to `APPROVED` by an Admin, the system issues an active QR credential and displays assigned shift/venue duties on the participant's dashboard.
- **Fees & Coupons**: Delegate and category fees default to `₹0` (`requiresPayment: false`) until organizers confirm final pricing and configure Razorpay credentials. Coupons (`PERCENTAGE`, `FIXED_AMOUNT`, `FULL_WAIVER`) are managed in `/admin` and validated server-side via `/api/coupons/validate`.

---

## 11. QR Scanning & RGST Operations

1. Reception staff log in at `/login` and are routed to `/rgst`.
2. Select the active **Check-in Station / Gate** (e.g., *Sama Indoor Stadium — Main Gate A*).
3. Scan the participant's digital or printed PDF pass using:
   - **Device Camera** (`html5-qrcode`),
   - **USB/Bluetooth Barcode Scanner** (keyboard wedge input), or
   - **Manual Registration / Credential ID Fallback** (`PFG-2027-...`).
4. `/api/qr/verify` displays only minimal verification details: Participant Name, Category, Credential ID, and Eligibility Status (`VALID`, `ALREADY_CHECKED_IN`, `REVOKED`, `PAYMENT_PENDING`, or `NOT_FOUND`).
5. Click **Confirm Check-in** (`/api/checkins/confirm`) to record attendance scoped to the festival attendance policy (`ONCE_PER_DAY` in IST `Asia/Kolkata`, or `ONCE_PER_FESTIVAL`).
6. Duplicate or concurrent scans are blocked deterministically at the database unique constraint and logged as `DUPLICATE_ATTEMPT` in `credential_events`.

---

## 12. CMS Management

Superadmins (with `cms.manage`) manage public content through `/admin` → **CMS** (`/api/admin/cms`):
- **Home Page Sections**: Hero headline/dates, About & impact metrics, Viksit Bharat@2047 statement, Contact Secretariat details.
- **Programme Schedule**: Add/edit/publish Day 1–3 sessions with accessibility service tags (ISL, CART, Braille, Audio Description, Wheelchair Seating).
- **Partners & Written Consent Governance**: Partner logos default to `DRAFT` (`consentVerified: false`) and are only rendered publicly once written publicity consent is verified and status is set to `PUBLISHED`.
- **FAQs & Media Assets**: Every media asset requires meaningful `altText` and source attribution.

---

## 13. Testing

### Integration & Security Suite (Vitest)
Runs against an isolated in-memory PostgreSQL (`PGLITE_DATA_DIR=memory://`) so local/production databases are never touched:

```bash
npm test
```

Covers registration validation, password & TOTP MFA cryptography, RBAC permission boundaries, zero-PII QR token signing/tamper rejection, concurrent check-in race-condition protection, revocation/reissuance, accessible PDF generation, and Volunteer/SLI approval lifecycles.

### End-to-End & WCAG 2.2 AA Accessibility Audits (Playwright + axe-core)
Ensure the development or production server is running (`npm run dev` or `npm run build && npm start`), then execute:

```bash
npx playwright install chromium
npm run test:e2e
```

Set `E2E_BASE_URL` if testing against a staging URL:
```bash
E2E_BASE_URL=https://staging.purplefestivalgujarat.com npm run test:e2e
```

### Static Verification
```bash
npm run typecheck
npm run lint
npm run build
```

---

## 14. Production Deployment

Recommended production topology: **Vercel** (Next.js App Router) + **Managed PostgreSQL 16** (Neon / Supabase / AWS RDS `ap-south-1` Mumbai) + **Resend** + **Razorpay**.

1. **Provision PostgreSQL 16** in `ap-south-1` (India region for low latency & DPDP Act data residency alignment) with SSL enforced (`?sslmode=require`).
2. **Configure Environment Variables** in Vercel Project Settings:
   - `DATABASE_URL`, `NEXT_PUBLIC_APP_URL`, `AUTH_SECRET`, `QR_SIGNING_SECRET`
   - `EMAIL_PROVIDER=resend`, `RESEND_API_KEY`, `EMAIL_FROM`
   - `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` (when paid registration is enabled)
3. **Run Database Migrations & Seed** against production:
   ```bash
   DATABASE_URL="postgresql://..." npm run db:migrate
   DATABASE_URL="postgresql://..." npm run db:seed
   ```
4. **Bootstrap Initial Superadmin** (one-time) from a secure operator workstation:
   ```bash
   DATABASE_URL="postgresql://..." \
   SUPERADMIN_BOOTSTRAP_EMAIL="lead@purplefestivalgujarat.com" \
   SUPERADMIN_BOOTSTRAP_PASSWORD="<strong-16+-char-password>" \
   npm run db:bootstrap-superadmin
   ```
5. **Configure Razorpay Webhook** pointing to `https://<domain>/api/payments/webhook` with `payment.captured` and `payment.failed` events using `RAZORPAY_WEBHOOK_SECRET`.
6. **Verify Post-Deploy**: Run `E2E_BASE_URL=https://<domain> npm run test:e2e` and confirm security headers, TLS, MFA login, and RGST scanner camera permissions over HTTPS.

---

## 15. Database Backup & Recovery

### Automated & Manual Backups (`pg_dump`)
Schedule daily encrypted backups (and hourly backups during the 21–23 January 2027 festival window):

```bash
# Create compressed custom-format backup
pg_dump "$DATABASE_URL" \
  --format=custom \
  --no-owner \
  --no-privileges \
  --file="pfg2027_backup_$(date +%Y%m%d_%H%M%S).dump"
```

Encrypt backup archives at rest (e.g., KMS/AES-256) because they contain participant PII and sensitive accommodation disclosures.

### Point-in-Time Restore (`pg_restore`)
```bash
# Restore into a freshly created target database
createdb purple_festival_gujarat_2027_restore
pg_restore \
  --dbname="postgresql://user:password@host:5432/purple_festival_gujarat_2027_restore" \
  --clean \
  --if-exists \
  --no-owner \
  --no-privileges \
  pfg2027_backup_20270121_060000.dump
```

Always test restores in a staging environment prior to festival opening day and verify row counts for `users`, `registrations`, `qr_credentials`, and `checkins`.

---

## Further Documentation
- [Accessibility & Security Architecture (`docs/ACCESSIBILITY_AND_SECURITY.md`)](./docs/ACCESSIBILITY_AND_SECURITY.md)
