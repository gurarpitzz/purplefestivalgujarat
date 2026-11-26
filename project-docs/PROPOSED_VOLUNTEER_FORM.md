# Proposed Festival Volunteer Application Form Specification (`VOLUNTEER`)

**Status:** Pre-Phase 6 Reconciliation Proposal (**NOT YET IMPLEMENTED** — zero frontend or schema mutations until Phase 6 is authorized)  
**Target Category:** `VOLUNTEER` (Primary Category 2 of 3 per `CAD-02`)  
**Sources Reconciled:** `VOLUNTEER AND SLI.docx` (Form 1 — primary organizer form), `VOLUNTEER AND SLI 1.docx` (Form 1), `Purple Festival Gujarat 2027 Brochure (3).pdf` (p. 6), `Purple_Festival_Gujarat_2027_Action_Plan.pptx` (Slides 6, 8, 11), `Purple_Festival_Gujarat_2027_Programme_Grid.xlsx`, `assets/js/registration-wizard.js`, `api/registrations/index.php`, `database/schema.sql`, `CONFIRMED_ARCHITECTURAL_DECISIONS.md`, `REMAINING_OWNER_DECISIONS.md`.

---

## 1. Authority Distinction: Organizer-Confirmed vs. Technical Recommendations

| Classification | Rules & Scope |
| :--- | :--- |
| **Organizer-Confirmed Requirements** | • **Volunteers with and without disabilities** are equally welcomed (`VOLUNTEER AND SLI.docx` Form 1 title & `CAD-07`).<br>• **Structured MSU Baroda Affiliation** (`Yes, currently enrolled` / `Yes, MSU Alumni` / `No`) + conditional **`Faculty / Department Name`** — replaces existing inadequate boolean checkbox.<br>• **Exact 5 Organizer Primary Roles** (`Stage Anchoring / Hosting`, `Event Logistics & Venue Management`, `Guest Registration & Hospitality`, `Accessibility Assistance / Peer Guide`, `Technical / Media Support`) — replaces inaccurate 6-role list currently in `registration-wizard.js`.<br>• **Conditional Public Speaking / Stage Hosting Experience** prompt when applying for `Stage Anchoring / Hosting`.<br>• **Language Proficiency** (`Gujarati`, `Hindi`, `English`, `Other` — eliminating misleading pre-selected `['English', 'Gujarati']` defaults).<br>• **Emergency Contact Name, Relationship & Phone Number** + **Code of Conduct & Consent** (`"I agree to maintain an inclusive, respectful environment aligned with the Purple Festival."`).<br>• **Coordinator Review Workflow** — submitting a Volunteer application never grants automatic approval (`SUBMITTED` -> Coordinator Review -> `APPROVED`, zero QR pass per `CAD-13`). |
| **Technical Recommendations (Pending Owner Sign-Off)** | • Keeping **RCI CRR Number** strictly **Optional** (`D-14`) to resolve the conflict between `VOLUNTEER AND SLI.docx` (includes `CRR No.`) and `VOLUNTEER AND SLI 1.docx` (removes `CRR No.`) without blocking ~645 student/NSS/NCC volunteers (`CNF-01`, `CNF-08`).<br>• Adopting a structured **Day × Shift Availability Grid** (Thu 21 / Fri 22 / Sat 23 Jan × Shift 1 Morning `08:00–14:30` / Shift 2 Afternoon–Evening `14:00–20:30`) from `VOLUNTEER AND SLI 1.docx` & `Programme_Grid.xlsx` (`D-18`).<br>• Allowing optional **Secondary Role** preferences and optional **External Institution / NSS / NCC Unit** text when MSU Affiliation = `No` (`Action_Plan.pptx` Slide 11).<br>• Retaining `Indian Sign Language (ISL)` and `Marathi` alongside the organizer's `Gujarati / Hindi / English / Other` language options.<br>• **Zero document uploads** in Phase 6 (`D-15`) and **zero coupon/fee widgets** (`D-05`). |

---

## 2. Entry Precondition & Account Identity Retrieval (No Repeated Entry)

Per `CAD-03`, participants authenticate first (`/login` or `/register`) before opening `/apply/volunteer`.

### Section 0 — Authenticated Account Identity Card (Read-Only Summary)
Retrieved from authenticated `users` record (same reusable Account Identity Summary component as Attendee & SLI):
- `account.full_name` (Full Name)
- `account.email` (Verified Email Address)
- `account.phone` (Mobile / WhatsApp Contact Number)
- `account.city_district` & `account.state` (Current City / District & State)
- Link to **"Edit Account Profile"** (`/dashboard#profile`) if any contact detail changed.

> **Eliminated from Volunteer Form:** Generic `Occupation` dropdown (with misleading `"Student"` default), duplicate `Organization / Institution` & `Designation` inputs, `Coupon Code` box, and legacy `Category` switcher.

---

## 3. Section-by-Section Proposed Volunteer Application Layout

### Section 1 — Volunteer Profile & Institutional Affiliation
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 1 Section 1.*

1. **Age (Minimum 16 years)** (`volunteer.age_or_dob`) — **Required**  
   - **Control:** Numeric input (`16–85`) *(or Date of Birth computing age `≥ 16` on 21 Jan 2027 — confirm under Owner Decision pack)*  
   - **Validation:** Enforced strictly on **both client and server** (fixes existing gap where server accepted `0` or negative ages).
2. **Gender** (`volunteer.gender`) — **Optional**  
   - **Control:** Select dropdown (blank default)  
   - **Options (reconciled verbatim with Form 1 S1):** `Male` · `Female` · `Non-binary` · `Prefer not to say` · `Other` *(fixes missing `"Other"` option in existing code)*.
3. **Are you a student or alumni of The Maharaja Sayajirao University of Baroda (MSU)?** (`volunteer.msu_affiliation`) — **Required**  
   - **Control:** Radio group — **UNSELECTED BY DEFAULT** *(replaces existing boolean checkbox `is_msu_student_or_alumni`)*  
   - **Options:**
     - `Yes, currently enrolled at MSU Baroda` (`MSU_CURRENT_STUDENT`)
     - `Yes, MSU Baroda Alumni` (`MSU_ALUMNI`)
     - `No` (`EXTERNAL`)
4. **MSU Faculty / Department Name** (`volunteer.msu_faculty_department`) — **Conditionally Required**  
   - **Visibility:** Shown and required (`2–150` chars) ONLY when MSU Affiliation = `MSU_CURRENT_STUDENT` or `MSU_ALUMNI` (e.g., *"Faculty of Social Work"*, *"Dept. of Physical Education"*).
5. **College / Institution / NSS / NCC Unit / Organisation** (`reg.organization_name`) — **Optional**  
   - **Visibility:** Shown ONLY when MSU Affiliation = `EXTERNAL` (`0–200` chars; supports `Action_Plan.pptx` Slide 11 recruitment of college, NCC, NSS, and community volunteers without duplicating MSU fields).
6. **RCI CRR Number (Optional — only if you are a registered rehabilitation professional)** (`volunteer.crr_number`) — **Optional** *(Pending `D-14`: Keep Optional vs. Remove)*  
   - **Control:** Text input (`3–40` alphanumeric / `-` / `/` chars when entered; blank by default).  
   - **Helper Text:** *"Only for volunteers registered with the Rehabilitation Council of India (RCI). Leave blank if not applicable — students, NSS/NCC, and community volunteers do not need a CRR number."*

---

### Section 2 — Role Preferences, Languages & Experience
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 1 Section 3; `Action_Plan.pptx` Slide 6 & 11.*

1. **Primary Role Interested In** (`volunteer.primary_role`) — **Required**  
   - **Control:** Radio group — **UNSELECTED BY DEFAULT**  
   - **Exact 5 Organizer Roles (replaces inaccurate 6-role list in existing `registration-wizard.js`):**
     1. `Stage Anchoring / Hosting` (`STAGE_ANCHORING`)
     2. `Event Logistics & Venue Management` (`EVENT_LOGISTICS`)
     3. `Guest Registration & Hospitality` (`GUEST_REGISTRATION_HOSPITALITY`)
     4. `Accessibility Assistance / Peer Guide` (`ACCESSIBILITY_PEER_GUIDE`)
     5. `Technical / Media Support` (`TECHNICAL_MEDIA`)
   - *(Technical Recommendation for Owner Confirmation: Optionally allow a separate multi-select "Other roles you are open to helping with" including Para-Sports Support per `Action_Plan.pptx` Slide 11, while preserving the 5 organizer primary roles cleanly.)*
2. **Prior Public Speaking or Stage Hosting Experience** (`volunteer.anchoring_experience`) — **Conditionally Required**  
   - **Visibility:** Shown and required (`10–1,000` chars) ONLY when `Stage Anchoring / Hosting` is selected.
   - **Prompt:** *"Since you selected Stage Anchoring / Hosting, please briefly describe any prior public speaking, emceeing, or stage hosting experience."*
3. **Relevant Volunteering or Inclusion Experience (Optional)** (`volunteer.anchoring_experience` / `relevant_experience`) — **Optional** (`0–1,000` chars) for non-anchoring roles.
4. **Language Proficiency** (`volunteer.languages_spoken`) — **Required (≥ 1)**  
   - **Control:** Checkboxes — **ALL UNCHECKED BY DEFAULT** *(eliminates existing misleading `['English', 'Gujarati']` pre-selection)*  
   - **Options:** `Gujarati` · `Hindi` · `English` · `Indian Sign Language (ISL)` · `Marathi` · `Other`  
   - **Conditional Sub-field:** Checking `Other` reveals optional text input (`2–80` chars) to specify language(s).

---

### Section 3 — Festival Day & Shift Availability (`21–23 January 2027`)
*Source: `VOLUNTEER AND SLI 1.docx` Form 1 Section 3 (`Availability during the festival days: Checkboxes / Grid for dates and shifts`); `Programme_Grid.xlsx` (`08:00–20:30` two-shift staffing model).*

- **Field:** `volunteer.availability_shifts` — **Required (≥ 1 shift slot)**  
- **Control:** Accessible semantic HTML `<table>` / `<fieldset>` grid — **ALL UNCHECKED BY DEFAULT**:

| Festival Day | Shift 1 — Morning (`08:00 – 14:30 IST`) | Shift 2 — Afternoon & Evening (`14:00 – 20:30 IST`) |
| :--- | :---: | :---: |
| **Day 1 — Thu, 21 Jan 2027** | `[ ] DAY_1_SHIFT_1` | `[ ] DAY_1_SHIFT_2` |
| **Day 2 — Fri, 22 Jan 2027** | `[ ] DAY_2_SHIFT_1` | `[ ] DAY_2_SHIFT_2` |
| **Day 3 — Sat, 23 Jan 2027** | `[ ] DAY_3_SHIFT_1` | `[ ] DAY_3_SHIFT_2` |

- **Derived Data:** Server automatically derives `registrations.attending_days` (`DAY_1`, `DAY_2`, `DAY_3`) from the selected shift slots so Volunteers are never asked for days twice. Exact shift hours remain configurable pending `D-18`.
- **Informational Note (from `Action_Plan.pptx` Slide 11 & `Brochure p. 6`):** *"Approved volunteers will be invited to a sensitisation workshop and venue walk-through before the festival; schedule details will be shared by email and in your Participant Dashboard."*

---

### Section 4 — Shared Accessibility & Accommodations Module *(Reusable Component)*
Identical across all 3 categories — see [SHARED_ACCESSIBILITY_FORM_SPECIFICATION.md](SHARED_ACCESSIBILITY_FORM_SPECIFICATION.md). Satisfies `VOLUNTEER AND SLI.docx` Form 1 Section 2 (*"Do you identify as a person with a disability?"*, optional disability types, and *"Do you require any specific accessibility support or assistive devices to volunteer comfortably?"*) using the privacy-protected, encrypted, RBAC-gated shared module.

---

### Section 5 — Emergency Contact *(Required — Shared Component)*
*Source: `VOLUNTEER AND SLI.docx` & `VOLUNTEER AND SLI 1.docx` Form 1 Section 4.*
1. **Emergency Contact Name** (`shared.emergency_contact_name`) — **Required** (`2–150` chars)
2. **Emergency Contact Relationship** (`shared.emergency_contact_relationship`) — **Required** (`Parent`, `Spouse/Partner`, `Sibling`, `Relative`, `Friend`, `Colleague`, `Caregiver`, `Other`) *(fixes missing relationship field in existing codebase)*
3. **Emergency Contact Number** (`shared.emergency_contact_phone`) — **Required** (valid phone `7–15` digits)

---

### Section 6 — Declarations & Consents *(Shared Component)*
All unchecked by default:
1. **Code of Conduct & Consent** (`consent.code_of_conduct`) — **Required**: *"I agree to maintain an inclusive, respectful, harassment-free environment aligned with Purple Festival Gujarat 2027."*
2. **DPDP Act 2023 Data Processing Consent** (`consent.data_processing_dpdp`) — **Required**
3. **Information Accuracy Declaration** (`consent.accuracy_declaration`) — **Required**
4. **Photography & Media Consent** (`consent.photography_media`) — **Optional**

---

## 4. Operational Separation & Post-Submission Contract
- **No Unapproved Uploads (`D-15`):** `volunteer_applications.document_url` remains unused until explicitly authorized.
- **No Unapproved Fees/Coupons (`D-05`):** Always Free (`₹0`).
- **Initial Status:** `SUBMITTED` (`assignment_status = 'UNASSIGNED'`). Does **not** auto-approve and does **not** issue a QR pass (`CAD-13`).
- **Admin Fields Strictly Excluded from Form:** `assigned_department`, `assigned_responsibility`, `assigned_supervisor`, `assigned_venue`, `assigned_shift`, `reporting_time`, and `assignment_notes` remain strictly admin-managed post-approval (`volunteers.review` permission) and display read-only on the Volunteer's Participant Dashboard once assigned.
