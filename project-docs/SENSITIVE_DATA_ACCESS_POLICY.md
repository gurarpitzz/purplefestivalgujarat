# Sensitive Data Access & Protection Policy — Purple Festival Gujarat 2027

**Scope:** Disability disclosures, accessibility accommodation requests, coordinator notes, and participant contact metadata (`FND-11`, `FND-25`, `FND-28`).  
**Authoritative Implementation:** [includes/config.php](file:///d:/purplefestgujarat/includes/config.php), [api/registrations/index.php](file:///d:/purplefestgujarat/api/registrations/index.php), [api/admin/registrations.php](file:///d:/purplefestgujarat/api/admin/registrations.php), [api/admin/accommodations.php](file:///d:/purplefestgujarat/api/admin/accommodations.php), [api/admin/overview.php](file:///d:/purplefestgujarat/api/admin/overview.php), [api/account/accommodations.php](file:///d:/purplefestgujarat/api/account/accommodations.php)

---

## 1. Classification of Sensitive Participant Data
1. **Highly Sensitive Health / Disability Disclosures:** `is_person_with_disability`, RPwD disability category codes, and `self_description` / `notes` free-text fields (`participant_disability_disclosures`, `registration_disabilities`).
2. **Sensitive Accommodation Details:** Selected accommodation types, `free_text_details`, `notes`, and `coordinator_notes` (`accommodation_requests`, `registration_accommodations`, `accommodation_assignments`).
3. **Personal Contact & Emergency Data:** Participant email, phone, and emergency contact details.

## 2. Encryption at Rest (Phase 2 Scope vs. Phase 3 Backfill)
- All **new** free-text disability descriptions (`self_description`, `notes`) and accommodation free-text/coordinator notes (`free_text_details`, `notes`, `coordinator_notes`) are encrypted via `pfgEncryptValue(..., 'data')` using **AES-256-GCM** (`enc:v1:` prefix, random 96-bit IV, 128-bit authentication tag, domain-separated `DATA_ENCRYPTION_KEY`).
- `pfgDecryptValue(..., 'data')` transparently decrypts `enc:v1:` records for authorized readers while remaining backward-compatible with pre-Phase-2 legacy plaintext rows until the full database consolidation and backfill migration in Phase 3.
- **No Unnecessary Duplication:** `api/registrations/index.php` strips `isPersonWithDisability`, `disabilityCategoryCodes`, `disabilitySelfDescription`, `accommodationTypes`, and `accommodationFreeText` before writing `registration_responses.answers`.

## 3. Strict Role & Permission Minimization
- **`accommodations.read` Enforcement:**
  - `GET /api/admin/registrations` omits `hasAccommodationRequest`, `accommodationStatus`, `accommodationTypes`, and `accommodationFreeText` and masks `isPersonWithDisability` as `'RESTRICTED'` when the caller lacks `accommodations.read`.
  - Filtering via `?hasAccommodations=true|false` or exporting with `?includeAccommodations=true` returns `403 Forbidden` without `accommodations.read`.
  - `GET /api/admin/overview` returns `null` for `accommodationSummary` and `participantsRequestingAccommodations` unless `accommodations.read` is held.
  - `GET /api/admin/accommodations` enforces `accommodations.read`; `PATCH/POST` enforces `accommodations.manage` + CSRF.
- **Registration Desk (`RGST`) Strict Isolation:** `RGST` receives zero disability, accommodation, email, phone, or reviewer-note fields; `revocationReason` no longer falls back to admin `reviewNotes`.
- **Unauthenticated Protection (`FND-07`):** Unauthenticated `POST /api/registrations` is blocked (`409`) if the submitted email matches any existing `users` record, preventing record attachment and registration-code disclosure.

## 4. Mandatory Sensitive-Read & Export Auditing
- Every authorized read of accommodation lists (`GET /api/admin/accommodations`) or individual registration details containing disclosures/accommodations (`GET /api/admin/registrations?id=...`) records an immutable `SENSITIVE_DATA_READ` entry in `audit_logs` containing actor ID, resource ID, IP, user agent, and record counts (never raw clinical text).
- Every CSV export records `REGISTRATIONS_EXPORTED` with row count and `includeAccommodations` flag, and neutralizes spreadsheet formula injection (`=`, `+`, `-`, `@`, `\t`, `\r`) via leading `'`.
- `logAudit()` and `sendFestivalEmail()` automatically scrub secrets/passwords/tokens from metadata before persistence.

