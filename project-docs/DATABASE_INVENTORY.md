# Database Inventory — Purple Festival Gujarat 2027 (62 Tables)

**Verified Against:** Isolated local MariaDB `10.4.32` (`pfg27_test`) post-Phase 3 migrations (`000`–`005`)  
**Engine / Collation:** `InnoDB` / `utf8mb4_unicode_ci` across all `62` tables  
**Enforced Foreign Keys:** `62` total (`9` baseline + `53` Phase 3)

---

## Table Classification Summary

| Classification | Count | Handling Policy |
|---|---:|---|
| **Active Canonical Tables** | 46 | Primary read/write tables with enforced FKs and indexes |
| **Phase 2 & 3 Infrastructure Tables** | 5 | `security_rate_limits`, `schema_migrations`, `registration_number_sequences`, `attendance_records`, `attendance_correction_logs` |
| **Preserved Historical QR/Check-in Tables (D-01)** | 4 | `qr_credentials`, `attendance_entitlements`, `checkins`, `credential_events` — retained non-destructively (read-only/audit) |
| **Archived Legacy Duplicate Tables (Phase 3E)** | 6 | `registration_accommodations`, `registration_disabilities`, `delegate_profiles`, `coupon_codes`, `payments`, `notification_jobs` — consolidated into canonical tables, zero active writers, **NOT dropped** |
| **Deferred / Reserved Module Tables** | 1 | `registration_form_versions` (plus deferred payment/coupon canonical tables pending `D-05`) |
| **TOTAL** | **62** | **0 tables dropped in Phase 3** |

---

## Complete 62-Table Inventory

### 1. Migration, Security & Core Authentication (`10` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `schema_migrations` | Phase 3 | **ACTIVE INFRA** | `version` | PK + status/applied indexes | Tracks applied versioned migrations, SHA-256 checksums, timing & reports |
| `security_rate_limits` | Phase 2 (`001`) | **ACTIVE INFRA** | `bucket_key` | PK + scope/blocked indexes | Persistent DB-backed rate limiter (`HTTP 429`) across PHP workers |
| `roles` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(code)` | 4 core roles: `SUPERADMIN`, `ADMIN`, `RGST`, `PARTICIPANT` |
| `permissions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(code)` | 20 granular permission codes |
| `role_permissions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(role_id, permission_id)`, 2 baseline FKs | Default permission grants per role |
| `users` | Baseline (`000`) + `003` | **ACTIVE CANONICAL** | `id` | `UNIQUE(email)`, `UNIQUE(username)` | Accounts; `account_status`, `mfa_secret_encrypted` (`mfa_secret` cleared) |
| `user_roles` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(user_id, role_id)`, 2 baseline FKs | User-to-role assignments |
| `auth_accounts` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | 1 baseline FK (`user_id`) | External/multi-provider identity linkage (reserved for Phase 5) |
| `sessions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(token_hash)`, 1 baseline FK, `idx_sessions_user_expires` | Active authenticated sessions (`mfa_verified`, revocation support) |
| `password_reset_tokens` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(token_hash)`, 1 baseline FK | Hashed single-use password reset tokens |

### 2. Festival Editions, Venues, Programme & Partners (`7` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `festival_editions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(slug)` | Active festival edition (`gujarat-2027`, 21–23 Jan 2027, Vadodara) |
| `venues` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | 1 baseline FK (`edition_id`) | Festival venues with `confirmation_status` (`CONFIRMED`/`COMMITTED`/`PENDING`) |
| `festival_sessions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `festival_sessions_edition_idx` | Structured scheduled sessions (Phase 12) |
| `checkin_stations` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_chkst_edition`, `fk_chkst_venue` | Physical Registration Desk stations across Vadodara venues |
| `programme_items` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_prog_edition` | Public 3-day festival programme entries |
| `organizations` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | PK | Organiser & partner institution directory |
| `partners` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | 1 baseline FK (`organization_id`) | Partner display records with `consent_verified` & `status` |

### 3. Registration, Numbering, Accessibility & Category Applications (`17` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `registration_number_sequences` | Phase 3 (`002`) | **ACTIVE INFRA** | `seq_number` (AUTO_INCREMENT) | `UNIQUE(registration_code)`, `fk_regnumseq_edition` | Atomic monotonic `PFG-2027-NNNNNN` sequence allocator |
| `registration_categories` | Baseline (`000`) + `003` | **ACTIVE CANONICAL** | `id` | `UNIQUE(edition_id, code)`, `fk_regcat_edition` | `category_tier`: `PRIMARY` (`ATTENDEE`, `SLI`, `VOLUNTEER`) vs `LEGACY` (5) |
| `registration_form_versions` | Baseline (`000`) | **RESERVED** | `id` | PK | Versioned form metadata (Phase 6) |
| `registrations` | Baseline (`000`) + `003` | **ACTIVE CANONICAL** | `id` | `UNIQUE(registration_code)`, `UNIQUE(edition_id, idempotency_key)`, 4 FKs, 7 perf indexes | Core participant applications/registrations (`status`, `assignment_status`, `deleted_at`) |
| `registration_responses` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_regresp_reg` | Sanitized non-sensitive form payload snapshot (zero passwords/health notes) |
| `participant_affiliations` | Baseline (`000`) | **RESERVED** | `id` | PK | Optional multi-affiliation linkage |
| `disability_categories` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(code)` | 21 benchmark disabilities under RPwD Act 2016 |
| `accommodation_types` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(code)` | 13 standardized accessibility accommodation options |
| `participant_disability_disclosures` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_pdd_reg`, `fk_pdd_discat` | Optional disability disclosures (AES-256-GCM encrypted `self_description`/`notes`) |
| `registration_disabilities` | Baseline (`000`) | **ARCHIVED LEGACY DUPLICATE** | `id` | Consolidated -> `participant_disability_disclosures` | Zero active writers; legacy free-text encrypted at rest; retained non-destructively |
| `accommodation_requests` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | 3 FKs, 2 perf indexes | Canonical participant accessibility requests (AES-256-GCM encrypted text) |
| `registration_accommodations` | Baseline (`000`) | **ARCHIVED LEGACY DUPLICATE** | `id` | Consolidated -> `accommodation_requests` | Zero active writers; legacy free-text encrypted at rest; retained non-destructively |
| `accommodation_assignments` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_accass_req`, `fk_accass_assigned` | Accessibility coordinator assignments & encrypted notes |
| `volunteer_applications` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(registration_id)`, `fk_volapp_reg` | Volunteer qualifications, availability, and duty assignments |
| `sli_applications` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(registration_id)`, `fk_sliapp_reg` | SLI qualifications, CRR, demo URL, and session assignments |
| `delegate_details` | Baseline (`000`) | **ACTIVE CANONICAL (DEFERRED D-06)** | `id` | `UNIQUE(registration_id)`, `fk_deldet_reg` | Canonical legacy delegate logistics data (pending `D-06` consolidation) |
| `delegate_profiles` | Baseline (`000`) | **ARCHIVED LEGACY DUPLICATE** | `id` | Consolidated -> `delegate_details` | Zero active writers; retained non-destructively |

### 4. Manual Attendance & Preserved Historical QR Tables (`6` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `attendance_records` | Phase 3 (`003`) | **ACTIVE INFRA (D-01)** | `id` | `UNIQUE(edition_id, registration_id, attendance_date_ist, attendance_scope)`, 5 FKs | Decoupled manual desk attendance (zero `qr_credentials` dependency) |
| `attendance_correction_logs` | Phase 3 (`003`) | **ACTIVE INFRA (D-01)** | `id` | 3 FKs (`attendance_record_id`, `registration_id`, `actor_user_id`) | Immutable audit history of manual attendance corrections/revocations |
| `qr_credentials` | Baseline (`000`) | **PRESERVED HISTORICAL (D-01)** | `id` | `UNIQUE(credential_code)`, `UNIQUE(token_hash)`, 3 FKs | Preserved legacy credentials; new participant issuance permanently discontinued |
| `attendance_entitlements` | Baseline (`000`) | **PRESERVED HISTORICAL (D-01)** | `id` | PK | Preserved legacy entitlement rules |
| `checkins` | Baseline (`000`) | **PRESERVED HISTORICAL (D-01)** | `id` | `UNIQUE(credential_id, entitlement_scope)`, 4 FKs | Preserved legacy QR check-ins; retained for historical reporting |
| `credential_events` | Baseline (`000`) | **PRESERVED HISTORICAL (D-01)** | `id` | 2 FKs | Preserved legacy credential issue/revoke audit log |

### 5. Coupons & Payments (Deferred Pending Decision `D-05`, `7` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `coupons` | Baseline (`000`) | **CANONICAL (DEFERRED D-05)** | `id` | `UNIQUE(code)` | Canonical coupon/waiver definitions |
| `coupon_codes` | Baseline (`000`) | **ARCHIVED LEGACY DUPLICATE** | `id` | Consolidated -> `coupons` | Zero active writers; retained non-destructively |
| `coupon_redemptions` | Baseline (`000`) | **CANONICAL (DEFERRED D-05)** | `id` | 3 FKs (`coupon_id`, `registration_id`, `user_id`) | Coupon redemption ledger |
| `payment_orders` | Baseline (`000`) | **CANONICAL (DEFERRED D-05)** | `id` | 2 FKs (`registration_id`, `user_id`) | Canonical payment order records |
| `payments` | Baseline (`000`) | **ARCHIVED LEGACY DUPLICATE** | `id` | Consolidated -> `payment_orders` | Zero active writers; retained non-destructively |
| `payment_transactions` | Baseline (`000`) | **CANONICAL (DEFERRED D-05)** | `id` | `UNIQUE(provider_payment_id)` | Gateway transaction ledger |
| `refunds` | Baseline (`000`) | **CANONICAL (DEFERRED D-05)** | `id` | PK | Payment refund ledger |

### 6. CMS & Public Content (`6` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `cms_pages` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(edition_id, slug)`, `fk_cmspage_edition` | CMS page containers (`home`) |
| `cms_sections` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(page_id, section_key)`, `fk_cmssec_page` | Structured CMS sections (`hero`, `about`, `viksit-bharat`, `contact`) |
| `faq_items` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_faq_edition` | Festival FAQs |
| `media_assets` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | PK | Media gallery & logo metadata |
| `content_revisions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | PK | CMS revision history |
| `announcements` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_ann_edition` | Festival announcements |

### 7. Notifications & Delivery (`4` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `notification_templates` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(code)` | Transactional notification templates |
| `notification_queue` | Baseline (`000`) + `003` | **ACTIVE CANONICAL** | `id` | `UNIQUE(idempotency_key)`, `fk_nq_user`, 3 perf indexes | Outbound notification queue with provider, message ID & honest lifecycle states |
| `notification_jobs` | Baseline (`000`) | **ARCHIVED LEGACY DUPLICATE** | `id` | Consolidated -> `notification_queue` | Zero active writers; retained non-destructively |
| `notification_delivery_logs` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_ndl_job` (`job_id -> notification_queue.id`) | Provider webhook/delivery attempt logs |

### 8. Audit, Settings & DPDP Privacy Governance (`5` tables)

| Table | Origin | Status | Primary Key | Key Constraints / FKs | Purpose |
|---|---|---|---|---|---|
| `audit_logs` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_audit_actor`, 2 perf indexes | Immutable security, auth & `SENSITIVE_DATA_READ` audit trail |
| `admin_actions` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_admact_admin`, `fk_admact_target`, `idx_admin_actions_created` | Before/after state trail of administrative changes |
| `system_settings` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `UNIQUE(key)` | Festival operational feature flags (`payments.enabled`, `sli.require_crr`, `attendance.mode`) |
| `privacy_consent_records` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | `fk_pcr_user`, `fk_pcr_reg` | DPDP Act 2023 explicit consent records per registration |
| `data_retention_requests` | Baseline (`000`) | **ACTIVE CANONICAL** | `id` | PK | DPDP participant export/deletion request queue (pending `D-27` retention window) |

