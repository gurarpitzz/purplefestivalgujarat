# Shared Accessibility & Accommodations Form Specification

**Status:** Pre-Phase 6 Reconciliation Proposal (**NOT YET IMPLEMENTED** — zero frontend or schema mutations until Phase 6 is authorized)  
**Architectural Mandate (`CAD-07`):** Accessibility is a **shared, reusable capability across all three primary categories (`ATTENDEE`, `VOLUNTEER`, `SLI`)** — NEVER a fourth registration category or separate `ACCESSIBLE` pathway.  
**Sources Reconciled:** `CAD-07`, `SENSITIVE_DATA_ACCESS_POLICY.md`, `Purple Festival Gujarat 2027 Brochure (3).pdf` (pp. 2, 5), `Purple_Festival_Gujarat_2027_Action_Plan.pptx` (Slide 7), `VOLUNTEER AND SLI.docx` (Form 1 S2), `VOLUNTEER AND SLI 1.docx` (Form 1 S2), `Delegate Registration Form.docx` (Sec B & Sec D), `includes/config.php` (`RPWD_21_DISABILITIES`, `ACCOMMODATION_TYPES_SEED`), `assets/js/registration-wizard.js`, `api/registrations/index.php`, `api/account/accommodations.php`, `api/admin/accommodations.php`.

---

## 1. Core Architectural & Rights-Based Principles

| Principle | Authority | Implementation Contract |
| :--- | :--- | :--- |
| **Not a Separate Pathway** | **Organizer-Confirmed (`CAD-07`)** | Remove the standalone `ACCESSIBLE` card from `/apply`. Persons with disabilities register directly as `ATTENDEE`, `VOLUNTEER`, or `SLI` with equal status and identical accessibility support. |
| **No Diagnosis Required for Accommodations** | **Organizer-Confirmed (`CAD-07`)** | Selecting *"No"* or *"Prefer not to say"* (or leaving the disability question untouched) **never hides, disables, or blocks** the Accommodations checklist or free-text support box. Anyone (older adults, pregnant visitors, parents, temporary injuries, Deaf community members, neurodivergent guests) may request accommodations without medical disclosure. |
| **Strictly Voluntary Disclosure** | **Organizer-Confirmed (`VOLUNTEER AND SLI.docx`, `Delegate Registration Form.docx`, `CAD-07`)** | Disability self-identification and specific RPwD category selections are 100% optional and never used to screen or restrict eligibility. |
| **Single Reusable Component** | **Technical Recommendation** | Render from one shared PHP partial (`includes/form-components/shared-accessibility.php`) + shared JS module across `/apply/attendee`, `/apply/volunteer`, `/apply/sli`, and `/dashboard` (for post-submission updates). |
| **Strict Privacy, Encryption & RBAC Isolation** | **Implemented in Phases 2–3 (`SENSITIVE_DATA_ACCESS_POLICY.md`)** | All free-text disability & accommodation fields encrypted at rest via AES-256-GCM (`enc:v1:`); stripped from `registration_responses.answers`; readable ONLY with `accommodations.read`; every authorized read logged as `SENSITIVE_DATA_READ`; **zero exposure** on `/rgst` Registration Desk or default CSV exports. |

---

## 2. Reconciling Disability Taxonomies (`CNF-11`: 6 vs. 11 vs. 21 RPwD Disabilities)

### The Source Conflict (`CNF-11`)
- `VOLUNTEER AND SLI.docx` Form 1 S2 lists **6 high-level checkboxes**: `Visual`, `Locomotor`, `Hearing`, `Speech`, `Neurodivergent`, `Other`.
- `Delegate Registration Form.docx` Sec B lists **11 checkboxes**: `Locomotor`, `Visual`, `Hearing`, `Speech and language`, `Intellectual`, `Autism`, `Specific learning disability`, `Mental illness / psychosocial`, `Cerebral palsy`, `Multiple disabilities`, `Other`.
- `Brochure (3).pdf` p. 2 and existing `includes/config.php` (`disability_categories`) commit to all **21 Specified Disabilities under the RPwD Act, 2016**.

### Recommended Reconciliation (Two-Tier Grouped Accessible UX)
Preserve the full **21 RPwD Act 2016 benchmark taxonomy** in `disability_categories` (never downgrading legal inclusivity), while organizing the UI under **8 plain-language umbrella groups** with live accessible search so participants can quickly scan simple categories without facing an overwhelming flat list:

| Plain-Language Group | Exact Seeded RPwD Act 2016 Codes (`disability_categories`) |
| :--- | :--- |
| **Visual** | `BLINDNESS`, `LOW_VISION` |
| **Hearing** | `HEARING_IMPAIRMENT` (Deaf and Hard of Hearing) |
| **Speech & Language** | `SPEECH_AND_LANGUAGE` |
| **Locomotor / Physical** | `LOCOMOTOR_DISABILITY`, `CEREBRAL_PALSY`, `MUSCULAR_DYSTROPHY`, `DWARFISM`, `LEPROSY_CURED`, `ACID_ATTACK_VICTIM` |
| **Neurodevelopmental / Intellectual** | `AUTISM_SPECTRUM_DISORDER`, `SPECIFIC_LEARNING_DISABILITIES`, `INTELLECTUAL_DISABILITY` |
| **Mental / Psychosocial** | `MENTAL_ILLNESS` |
| **Chronic Neurological** | `CHRONIC_NEUROLOGICAL`, `PARKINSONS_DISEASE`, `MULTIPLE_SCLEROSIS` |
| **Blood Disorders** | `THALASSEMIA`, `HEMOPHILIA`, `SICKLE_CELL_DISEASE` |
| **Multiple Disabilities** | `MULTIPLE_DISABILITIES` (including Deafblindness) |

- **Self-Describe / Additional Context (`access.disability_self_description`):** Optional textarea (`≤ 1,000` chars, AES-256-GCM encrypted) handles `"Other"` and participant-preferred terminology.
- **Server Validation Fix:** Reject unknown `disabilityCategoryCodes` instead of inserting arbitrary strings with `NULL` FK (fixes current gap in [api/registrations/index.php](file:///d:/purplefestgujarat/api/registrations/index.php#L1072-L1092)).

---

## 3. Reconciling Accessibility Accommodation Options (13 Existing vs. 12 in Delegate Form)

Comparing `ACCOMMODATION_TYPES_SEED` ([config.php](file:///d:/purplefestgujarat/includes/config.php#L850-L866)) against `Delegate Registration Form.docx` Section D & `Brochure (3).pdf` p. 5 reveals **5 organizer-specified accommodation options missing from the current database seed**:

| Group | Accommodation Code | Proposed Display Label | Status | Source |
| :--- | :--- | :--- | :--- | :--- |
| **Communication** | `ISL_INTERPRETER` | Indian Sign Language (ISL) Interpreter | **RETAIN** | Existing + Delegate Form Sec D + Brochure p.5 |
| | `TACTILE_INTERPRETATION` | Tactile Interpretation (Deafblind support) | **RETAIN** | Existing (`config.php`) |
| | `CAPTIONING` | Live Captioning (CART) on screens | **RETAIN** | Existing + Delegate Form Sec D + Brochure p.5 |
| | `AUDIO_DESCRIPTION` | Audio Description for films & stage programmes | **ADD** | Delegate Form Sec D + Programme Grid |
| **Accessible Formats** | `BRAILLE_SCHEDULE` | Braille Schedule & Programme | **RETAIN** | Existing + Delegate Form Sec D + Brochure p.5 |
| | `LARGE_PRINT` | Large-Print Material | **RETAIN** | Existing + Delegate Form Sec D + Brochure p.5 |
| | `EASY_READ_MATERIAL` | Easy-to-Read / Plain-Language Material | **ADD** | Delegate Form Sec D + Brochure p.5 |
| | `SCREEN_READER_DIGITAL` | Screen-Reader-Compatible Digital Material | **RETAIN** | Existing + Delegate Form Sec D |
| **Mobility & Venue** | `WHEELCHAIR_SEATING` | Wheelchair-Accessible Seating & Toilets | **RETAIN** | Existing + Delegate Form Sec D + Brochure p.5 |
| | `WHEELCHAIR_LOAN` | Wheelchair to Borrow at the Venue | **ADD** | Delegate Form Sec D |
| | `WHEELCHAIR_CHARGING` | Power Wheelchair Charging Bay | **RETAIN** | Existing (`config.php`) |
| | `MOBILITY_ASSISTANCE` | Mobility Assistance / On-Campus Shuttle | **RETAIN** | Existing (`config.php`) |
| | `ACCESSIBLE_STATION_TRANSPORT` | Accessible Transport from Railway Station / Bus Stand | **ADD** | Delegate Form Sec D + Action Plan Slide 10 |
| **Sensory & Support** | `SIGHTED_GUIDE` | Sighted Guide / Peer Orientation Support | **RETAIN** | Existing + Brochure p.5 |
| | `SENSORY_SANCTUARY` | Quiet Space / Sensory-Friendly Area Access | **RETAIN** | Existing + Delegate Form Sec D + Brochure p.5 |
| | `ONSITE_MEDICAL_SUPPORT` | On-Site Medical Support Awareness | **ADD** | Delegate Form Sec D |
| | `DIETARY` | Dietary Accommodation | **RETAIN** | Existing (`config.php`) |
| | `OTHER` | Other Accessibility Requirement (describe below) | **RETAIN** | Existing + Delegate Form Sec D |

> **Plus Personal Assistant / Escort (`access.personal_assistant_escort`):**
> - Checkbox: *"A personal assistant or escort will accompany me"* (`accommodation_requests.escort_accompanying TINYINT(1)`)
> - Conditional optional text: *"Personal Assistant / Escort Name"* (`accommodation_requests.escort_name_encrypted TEXT`, AES-256-GCM encrypted, `≤150` chars)
> - Strictly separated from **Under-18 Legal Guardian** (`attendee_details.guardian_name`), which governs minor consent.
> - Pending `D-07` for any companion pass/badge rules if fees are later introduced.

---

## 4. Field Behavior, Defaults & Conditional Rules

1. **Privacy Notice Banner (Always Top of Section):** Plain-language notice explaining that accessibility information is encrypted, visible only to authorized accessibility coordinators, never shown on Registration Desk check-in screens, and strictly optional.
2. **`access.is_person_with_disability`:** 3 radio buttons (`Yes`, `No`, `Prefer not to say`), **visually unselected by default** (maps to `PREFER_NOT_TO_SAY` if skipped — never pre-forces `Yes` or `No`).
3. **Conditional Disability Disclosure Block:** Expands via accessible disclosure region (`aria-expanded` / `hidden` toggle) ONLY when `Yes` is selected; clearing back to `No` or `Prefer not to say` hides the block and ensures the server persists zero disability rows.
4. **Accommodations Checklist & Free-Text Notes:** Always visible regardless of the radio answer above; all checkboxes start unchecked; `access.accommodation_free_text` enforces `maxlength=1000` on both client and server (replacing inconsistent silent truncation in `/api/account/accommodations.php`).
5. **Post-Registration Self-Service:** Authenticated participants can add or update accommodation requests anytime via `/dashboard#accommodations` (`POST /api/account/accommodations` + planned Phase 10 edit support), linked to any of their active registrations (`ATTENDEE`, `VOLUNTEER`, or `SLI`).

---

## 5. WCAG 2.2 AA Accessibility Contract (Mandatory for Phase 6 & Phase 15)

- **Semantic Markup:** Every radio group and checkbox group wrapped in `<fieldset>` with explicit `<legend>`; helper text and inline errors linked via `aria-describedby` and `aria-invalid="true"`.
- **Touch Targets & Spacing:** All checkboxes, radios, labels, and accordion buttons meet `≥ 44×44 px` minimum interactive target size (`SC 2.5.8`).
- **Focus Preservation:** Filtering the 21 RPwD disabilities updates only the checklist container DOM without replacing the search `<input>` (preserving keyboard focus and announcing match counts in an `aria-live="polite"` status region).
- **Theme & Contrast Safety:** Works cleanly in Default, High Contrast, and Yellow-on-Black modes (`≥ 4.5:1` text contrast, `≥ 3:1` focus ring & control border contrast, zero color-only state indicators).
- **Keyboard-Only Navigation:** Full `Tab`, `Shift+Tab`, `Space`, and Arrow-key radio navigation with visible `:focus-visible` outlines.
- **Data Retention & DPDP (`D-27`):** Supports post-festival purge of sensitive disability disclosures (`participant_disability_disclosures`) and free-text medical/accessibility notes after the owner-approved retention window while retaining anonymized aggregate accessibility service metrics.
