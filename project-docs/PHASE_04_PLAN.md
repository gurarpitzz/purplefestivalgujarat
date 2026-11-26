# Phase 4 Plan — Transactional Email Service, Notification Queue and Delivery Tracking

**Project:** Purple Festival Gujarat 2027  
**Repository:** `d:\purplefestgujarat`  
**Stack (Locked):** Native PHP 8 (`8.2.12` local XAMPP) + MySQL 8.0 / MariaDB `10.4.32` (InnoDB) + PDO + Apache `2.4.58` / Bluehost cPanel + Resend HTTPS API + PHP CLI / cPanel Cron Jobs (**Zero Docker, Zero Redis, Zero Node Backend, Zero RabbitMQ, Zero Persistent Daemons**).  
**Recovery Checkpoint:** Local Git tag `phase3-verified-baseline` at commit `140f2a0` (`45/45` PHP/MariaDB + `26/26` Apache checks independently re-verified before Phase 4 edits).

---

## 1. Objectives & Targeted Audit Findings

| Audit Finding | Phase 4 Objective | Honesty / Scope Boundary |
|---|---|---|
| **FND-19** | Replace ad-hoc synchronous sending with centralized transactional outbox, CLI cron queue worker, lease-based claiming, retries, failure classification & Svix webhook delivery tracking | Locally verified (`T4-01`–`T4-08`); live Resend DNS/cron remains `CONFIGURATION BLOCKED` until Bluehost deployment |
| **FND-42** | Build reusable WCAG 2.2 AA branded email template system (29 templates, `lang="en"`, `role="presentation"`, `>=8.9:1` contrast, multipart plain-text) & eliminate insecure PHP `mail()` fallback | `29/29` templates implemented & render-verified (`T4-02`) |
| **FND-06** | Guarantee zero participant QR pass images, zero `api.qrserver.com` references, and zero persisted raw reset/verification secrets (`ephemeral_secret_enc` wiped post-handoff) | Verified across all `29` templates & live ephemeral reset flow (`T4-02`, `T4-06`) |
| **FND-24** | Prepare account verification & password recovery templates and secure ephemeral token delivery architecture for Phase 5 | **Not closed** until Phase 5 participant verification UI/API is complete |
| **FND-30** | Connect existing registration, admin review/assignment, accommodation & password-reset events to the transactional outbox and catalog all 29 lifecycle templates | **Not closed** until remaining 9 future triggers (Phases 5/6/12/13) are connected |

---

## 2. Sub-Phase Execution (`4A`–`4R`)

1. **4A — Communication Audit:** Inventoried `includes/mailer.php`, `includes/database.php`, `api/registrations/index.php`, `api/admin/registrations.php`, `api/admin/accommodations.php`, `api/account/accommodations.php`, `api/auth/password-reset.php`, `api/auth/signup.php`; removed dead `pfgQueueNotificationCompat()` and consolidated onto one canonical service.
2. **4B–4F — Migration `006`, Centralized Service, Transactional Outbox, Queue Worker, Retries & Honest Statuses:** Added lease-locking/webhook/ephemeral columns + `in_app_notifications` table (`006_phase4_notifications_and_delivery_tracking.php`); built `includes/notification-service.php`, CLI `scripts/process-notification-queue.php`, and `api/admin/notifications.php`.
3. **4G–4I — Resend API, Local Isolation, Simulator & Svix Webhooks:** Hardened HTTPS Resend transport with `Idempotency-Key` header & fail-closed config (removed `mail()` fallback), kept `MAIL_DRIVER=file`/`disabled`, added dev/test-only `simulate` driver, and built `api/webhooks/resend.php` with Svix HMAC-SHA256 verification, ±300s replay guard, deduplication, and monotonic out-of-order state precedence.
4. **4J–4O — 29 Branded WCAG Templates, Ephemeral Secret Strategy, Outbox Integration & In-App Foundation:** Built `includes/notification-templates.php`, wired transactional outbox into registration/admin/accommodation/password-reset flows, and created `in_app_notifications` table decoupled from email delivery.
5. **4P–4R — SMS/WhatsApp Deferral, Bluehost Cron Guide, External Resend Checklist & Full Test Matrix:** Verified `53/53` PHP/MariaDB tests (incl. `10`/`100`/`500`/`1,000` 4-worker load tests) + `29/29` real Apache `2.4.58` checks.
