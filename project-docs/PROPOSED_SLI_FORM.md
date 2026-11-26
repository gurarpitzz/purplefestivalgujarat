# Proposed Sign Language Interpreter Application Form Specification (`SLI`)

**Status:** Pre-Phase 6 Reconciliation Proposal (**NOT YET IMPLEMENTED** — zero frontend or schema mutations until Phase 6 is authorized)  
**Target Category:** `SLI` (Primary Category 3 of 3 per `CAD-02`)  
**Sources Reconciled:** `VOLUNTEER AND SLI.docx` (Form 2 — primary organizer form), `VOLUNTEER AND SLI 1.docx` (Form 2), `Purple Festival Gujarat 2027 Brochure (3).pdf` (p. 6), `Purple_Festival_Gujarat_2027_Action_Plan.pptx` (Slides 6, 8, 10, 15), `Purple_Festival_Gujarat_2027_Programme_Grid.xlsx`, `assets/js/registration-wizard.js`, `api/registrations/index.php`, `database/schema.sql`, `CONFIRMED_ARCHITECTURAL_DECISIONS.md`, `REMAINING_OWNER_DECISIONS.md`.

---

## 1. Authority Distinction: Organizer-Confirmed vs. Technical Recommendations

| Classification | Rules & Scope |
| :--- | :--- |
| **Organizer-Confirmed Requirements** | • **Structured Qualification Categories** from `VOLUNTEER AND SLI.docx` & `1.docx` Form 2 S2 (4 exact tiers — replaces unrestricted free-text boxes currently in `registration-wizard.js`).<br>• **Organizer-Specified Years of Experience Bands** (`< 1 year`, `1–3 years`, `3–5 years`, `5+ years`) — replaces raw integer input currently in code.<br>• **Age / Date of Birth** (`sli.age_or_dob` — currently missing from existing SLI form & table).<br>• **Stage / Ceremony Comfort Question** (`Yes / No` for interpreting cultural performances, panel discussions, and official ceremonies — currently missing from existing SLI form).<br>• **Emergency Contact Name, Relationship & Phone Number** (currently completely missing from existing SLI form & table).<br>• **Code of Conduct & Consent** + **Verification & Coordinator Approval Workflow** (`SUBMITTED` -> Coordinator Review -> `APPROVED`, zero QR pass per `CAD-13`).<br>• **Cross-category Accessibility & Accommodations** (`CAD-07` — vital for Deaf Interpreters and interpreters with disabilities). |
| **Technical Recommendations (Pending Owner Sign-Off)** | • **Gujarat Residency Handling (`D-12`):** Collect structured `City / District` + `State` + explicit `Based in Gujarat? (Yes/No)` (NO silent pre-check `true`!) **without hard-blocking** outstation ISLRTC/national interpreters at submission (`Action_Plan.pptx` Slide 15 explicitly warns Gujarat supply may be insufficient and recommends booking via ISLRTC/deaf associations).<br>• **CRR Number Reconciliation (`D-13`):** Never assume Deaf Interpreters, Community Signers, or Student/Trainee Interpreters hold an RCI CRR number. Keep CRR **Optional** for non-certified tiers; prompt prominently (or make conditionally required ONLY if Owner approves under `D-13`) solely when qualification = `Certified Indian Sign Language (ISL) Interpreter (ISLRTC / RCI recognized)`. Validate safe alphanumeric format without inventing brittle prefix assumptions.<br>• **Day × Shift Availability Grid (`D-18`):** Adopt 3 days × 2 shifts (`08:00–14:30` / `14:00–20:30`) per `VOLUNTEER AND SLI 1.docx` & `Programme_Grid.xlsx` (174 pair interpreter-hours, 2-interpreter pairing with 20–30 min rotations).<br>• **Retain Optional Screening Aids** from `VOLUNTEER AND SLI 1.docx`: Training Institute, validated `https://` Video Demo URL, and Honorarium / Travel Support Notes (with non-binding disclaimer).<br>• **Retain Optional Matching Aids** from existing wizard: Areas of Expertise (incl. Tactile / Deafblind) & Language Pairs (`ISL ↔ Gujarati / Hindi / English`).<br>• **No Unapproved Document Uploads (`D-15`)** and **No Unapproved Fees/Coupons (`D-05`)**. |

---

## 2. Entry Precondition & Account Identity Retrieval (No Repeated Entry)

Per `CAD-03`, SLI applicants authenticate via Phase 5 Account Login / Signup before opening `/apply/sli`.

### Section 0 — Authenticated Account Identity Card (Read-Only Summary)
Retrieved from authenticated `users` record:
- `account.full_name` (Full Name)
- `account.email` (Verified Email Address)
- `account.phone` (Mobile / WhatsApp Contact Number)
- `account.city_district` & `account.state` (Current City / District & State)
- Link to **"Edit Account Profile"** (`/dashboard#profile`).

> **Eliminated from SLI Form:** Mandatory generic `Occupation` dropdown (with misleading `"Student"` default), duplicate Step 2 `Organization` + `Designation` inputs, misleading pre-checked `basedInGujarat = true` default, unrendered dead `sliAffiliation` JS field, `Coupon Code` widget, and unapproved file uploads (`D-15`).

---

## 3. Section-by-Section Proposed SLI Application Layout

### Section 1 — Interpreter Profile & Gujarat Availability Context
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 2 Section 1; `Action_Plan.pptx` Slide 15; `D-12`.*

1. **Age (Minimum 18 years)** (`sli.age_or_dob`) — **Required** *(or Date of Birth — fixes omission in current codebase)*  
   - **Control:** Numeric input (`18–85`) or Date of Birth.
2. **Are you currently based in Gujarat?** (`sli.based_in_gujarat`) — **Required** *(Pending `D-12`)*  
   - **Control:** Explicit Radio (`Yes` / `No`) — **UNSELECTED BY DEFAULT** *(eliminates misleading `basedInGujarat: true` default; can optionally pre-inform from Account `state === 'Gujarat'` while requiring confirmation)*.  
   - **Helper Text:** *"Priority is given to interpreters based in Gujarat; qualified interpreters from other states (e.g., ISLRTC / national network) may also apply for coordinator review."* *(Exact eligibility policy confirmed under `D-12`.)*
3. **Professional Affiliation / Deaf Association / Independent** (`sli.professional_affiliation`) — **Optional** (`0–200` chars; consolidates previously unrendered `sliAffiliation` and Step 2 `organizationName`).

---

### Section 2 — Professional Qualifications, CRR & Experience
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 2 Section 2; `CNF-01`, `CNF-07`, `D-13`, `D-15`.*

1. **Certification / Qualification Level in Sign Language** (`sli.qualification_category`) — **Required**  
   - **Control:** Radio group — **UNSELECTED BY DEFAULT** *(replaces unrestricted `sli-qual` & `sli-cert` text boxes)*  
   - **Exact 4 Organizer Categories:**
     1. `Certified Indian Sign Language (ISL) Interpreter (e.g., ISLRTC / RCI recognized)` (`CERTIFIED_ISL_INTERPRETER`)
     2. `Professional Practitioner / Deaf Interpreter / Fluent Community Signer` (`PRACTITIONER_DEAF_COMMUNITY`)
     3. `Student / Trainee Interpreter` (`STUDENT_TRAINEE`)
     4. `Other` (`OTHER`)
   - **Conditional Sub-field:**
     - If `OTHER` selected: **Required** *"Please specify your qualification or background"* (`2–200` chars).
     - If `CERTIFIED_ISL_INTERPRETER` or `STUDENT_TRAINEE` selected: **Optional** *"Specific Diploma / Course (e.g., DISLI, DTISL)"* (`0–200` chars, saved to `sli_applications.qualifications`).
2. **RCI CRR Number** (`sli.crr_number`) — **Conditionally Optional / Pending `D-13`**  
   - **Control:** Text input (`3–40` chars, alphanumeric / `-` / `/`, no unsupported single-prefix regex assumptions).  
   - **Conditional Framing:**
     - When `CERTIFIED_ISL_INTERPRETER` is selected: prominently prompted (*"Enter your RCI CRR Number if issued"* — or required ONLY if Owner selects mandatory-for-certified option in `D-13`).
     - When `PRACTITIONER_DEAF_COMMUNITY`, `STUDENT_TRAINEE`, or `OTHER` is selected: explicitly marked *"Optional — not required for Deaf interpreters, community signers, or trainees"*.
3. **Years of Interpretation Experience** (`sli.experience_band`) — **Required**  
   - **Control:** Radio group — **UNSELECTED BY DEFAULT** *(replaces raw integer input)*  
   - **Exact 4 Organizer Bands:**
     - `Less than 1 year` (`LT_1_YEAR`)
     - `1 – 3 years` (`1_3_YEARS`)
     - `3 – 5 years` (`3_5_YEARS`)
     - `5+ years` (`5_PLUS_YEARS`)
4. **Where did you complete your training or certification?** (`sli.training_institute`) — **Optional** (`0–200` chars; e.g., ISLRTC, AYJNISHD, or recognized centre — from `VOLUNTEER AND SLI 1.docx`).
5. **Link to a Short Video Demonstration** (`sli.video_demo_url`) — **Optional** (`https://` or `http://` validated via `pfgValidateSafeUrl`, `≤500` chars — from `VOLUNTEER AND SLI 1.docx` & preferred over unapproved file upload per `D-15`).

> **Explicitly Excluded (`D-15`):** File upload (`Upload Certification / Resume / Portfolio`) is **NOT** implemented in Phase 6 until explicitly authorized with private non-webroot storage controls in Phase 16.

---

### Section 3 — Availability, Interpreting Comfort & Logistics
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 2 Section 3; `Programme_Grid.xlsx`.*

1. **Are you comfortable interpreting for cultural performances, panel discussions, and official ceremonies?** (`sli.high_profile_comfort`) — **Required**  
   - **Control:** Radio group (`Yes` / `No`, unselected by default — matches organizer Form 2 S3 verbatim; currently missing from codebase).
2. **Preferred Dates & Time Slots During the Festival (`21–23 January 2027`)** (`sli.availability_shifts`) — **Required (≥ 1 shift slot)**  
   - **Control:** Accessible Day × Shift checkbox grid — **ALL UNCHECKED BY DEFAULT**:

| Festival Day | Shift 1 — Morning (`08:00 – 14:30 IST`) | Shift 2 — Afternoon & Evening (`14:00 – 20:30 IST`) |
| :--- | :---: | :---: |
| **Day 1 — Thu, 21 Jan 2027** | `[ ] DAY_1_SHIFT_1` | `[ ] DAY_1_SHIFT_2` |
| **Day 2 — Fri, 22 Jan 2027** | `[ ] DAY_2_SHIFT_1` | `[ ] DAY_2_SHIFT_2` |
| **Day 3 — Sat, 23 Jan 2027** | `[ ] DAY_3_SHIFT_1` | `[ ] DAY_3_SHIFT_2` |

   - **Operational Note (from `Programme_Grid.xlsx`):** *"Sessions use paired interpreters rotating every 20–30 minutes across two daily shifts. Selecting your available shifts helps coordinators plan sustainable rosters."* Auto-derives `registrations.attending_days`.
3. **Areas of Interpreting Expertise** (`sli.areas_of_expertise`) — **Optional** *(Technical Recommendation for session matching)*  
   - Checkboxes (all unchecked): `Plenary & Keynote Sessions`, `Panel Discussions & Policy`, `Workshops & Education`, `Cultural & Stage Performances`, `Tactile / Deafblind Interpretation`, `Para-Sports & Community Events`.
4. **Language Pairs & Working Preferences** (`sli.interpretation_preferences`) — **Optional** *(Technical Recommendation)*  
   - Checkboxes (all unchecked): `ISL ↔ Gujarati`, `ISL ↔ Hindi`, `ISL ↔ English`, `Team Interpreting (20–30 min rotations)`, `Platform / Stage Interpreting`.
5. **Honorarium / Travel Support Notes** (`sli.honorarium_travel_notes`) — **Optional** textarea (`≤500` chars, from `VOLUNTEER AND SLI 1.docx`)  
   - **Non-binding Disclaimer:** *"Please share any travel, accommodation (e.g., outstation stay), or honorarium expectations. Support is subject to organizer policy and prior written confirmation."*

---

### Section 4 — Shared Accessibility & Accommodations Module *(Reusable Component)*
Identical across all 3 categories — see [SHARED_ACCESSIBILITY_FORM_SPECIFICATION.md](SHARED_ACCESSIBILITY_FORM_SPECIFICATION.md). Essential so **Deaf Interpreters** and SLIs with disabilities can request team-relay partners, accessible seating, lighting, or mobility support with full privacy protection.

---

### Section 5 — Emergency Contact *(Required — Shared Component)*
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 2 Section 4 (completely missing in current codebase!).*
1. **Emergency Contact Name** (`shared.emergency_contact_name`) — **Required** (`2–150` chars)
2. **Emergency Contact Relationship** (`shared.emergency_contact_relationship`) — **Required** (`Parent`, `Spouse/Partner`, `Sibling`, `Relative`, `Friend`, `Colleague`, `Caregiver`, `Other`)
3. **Emergency Contact Number** (`shared.emergency_contact_phone`) — **Required** (valid phone `7–15` digits)

---

### Section 6 — Declarations & Consents *(Shared Component)*
All unchecked by default:
1. **Code of Conduct & Consent** (`consent.code_of_conduct`) — **Required**
2. **DPDP Act 2023 Data Processing Consent** (`consent.data_processing_dpdp`) — **Required**
3. **Information Accuracy Declaration** (`consent.accuracy_declaration`) — **Required**
4. **Photography & Media Consent** (`consent.photography_media`) — **Optional**

---

## 4. Operational Separation & Post-Submission Contract
- **Initial Status:** `SUBMITTED` (`assignment_status = 'UNASSIGNED'`). Requires coordinator verification (`sli.review`) before approval (`APPROVED`). Zero QR credential issued (`CAD-13`).
- **Admin Fields Strictly Excluded from Form:** `assigned_sessions`, `assigned_venue`, `duty_times`, and `assignment_notes` remain strictly admin-managed post-approval and render read-only in the SLI Participant Dashboard once assigned (Phase 10/12).
