# Registration Architecture Owner Decisions Pack

**Status:** Decision Pack for Project Owner Sign-Off **Prior to Phase 6 Implementation**  
**Rule:** Every item below clearly separates **Organizer-Confirmed Requirements** (already locked) from **Technical Recommendations** (proposed defaults that **MUST NOT** be silently implemented without explicit Owner approval).  
**Cross-Reference:** Synchronized with [CONFIRMED_ARCHITECTURAL_DECISIONS.md](CONFIRMED_ARCHITECTURAL_DECISIONS.md) (`CAD-01`–`CAD-14`), [REMAINING_OWNER_DECISIONS.md](REMAINING_OWNER_DECISIONS.md) (`D-01`–`D-27`), [SOURCE_DOCUMENT_CONFLICTS.md](SOURCE_DOCUMENT_CONFLICTS.md) (`CNF-01`–`CNF-14`), and [REGISTRATION_FIELD_RECONCILIATION.csv](REGISTRATION_FIELD_RECONCILIATION.csv).

---

## 1. Already Confirmed Decisions (Locked — Do Not Re-Ask)

| ID | Confirmed Decision | Impact on Phase 6 Registration |
| :--- | :--- | :--- |
| **`CAD-01` / `CAD-11`** | Native PHP + MySQL/MariaDB + PDO + Vanilla JS + HTML5/CSS3 on Bluehost cPanel (zero Docker/Next.js) | All Phase 6 forms & shared components built as native PHP partials + Vanilla JS |
| **`CAD-02`** | Strictly **3 primary categories**: `ATTENDEE`, `SLI`, `VOLUNTEER` | Replace 5-pathway hub & 8-category selector with 3 independent category forms |
| **`CAD-03`** | **Account-based participant experience** | Require authenticated participant account first; retrieve Name/Email/Phone from `users` |
| **`CAD-06` / `CAD-13` (`D-01`)** | **Discontinue participant QR pass issuance**; retain atomic Registration IDs (`PFG-2027-NNNNNN`) | Remove all QR pass copy/hooks from registration & confirmation flows |
| **`CAD-07`** | **Accessibility across all 3 categories** (not a 4th pathway); no diagnosis required for accommodations | Remove `ACCESSIBLE` card; embed Shared Accessibility component in all 3 forms |
| **`CAD-08` / `CAD-09`** | Preserve public visual identity; **never hardcode planning caps**; **never delete historical data** | Deactivate legacy categories non-destructively (`is_active=0`); preserve all historical rows |

---

## 2. Decisions Directly Blocking Phase 6 Registration Implementation

### `D-05` & `D-07` — Attendee Registration Fees, Concessions & Escort Policy
- **Source Conflict (`CNF-03`):** `Action_Plan.pptx` Slides 3 & 9 propose a `₹1,500` 3-day Delegate fee + GST + PwD/Student/Escort concessions, whereas `Brochure (3).pdf`, `Delegate Registration Form.docx`, and the current DB show `₹0` fees and zero payment gateway integration.
- **Options:**
  - **Option A (Technical Recommendation):** Launch Attendee registration **Free (`₹0`)**, hide all coupon/payment fields in Phase 6, and retain the optional *"Personal Assistant / Escort accompanying me"* accessibility field. Defer payment gateway/GST/coupons to Phase 16 if ever approved later.
  - **Option B:** Charge a confirmed Attendee fee (requires Owner to supply exact fee amount, GST treatment confirmed by CA, PwD/Student/Escort concession rules, refund policy, and Razorpay production keys before checkout can be built).
- **Phase 6 Impact:** Determines whether `reg.coupon_code` and payment status workflows stay removed from Phase 6 forms.

### `D-06` & `D-08` — Legacy Delegate Consolidation & Final Attendee Field Set
- **Source Conflict (`CNF-02`):** `Delegate Registration Form.docx` contains outstation travel, lodging, accessible room, food preference, age group, 11 self-identification personas, and mandatory emergency contact, whereas the current `ATTENDEE` flow collects none of these (only `DELEGATE` had a partial subset).
- **Options:**
  - **Option A (Technical Recommendation):** Deactivate legacy `DELEGATE`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, and `ALLY` categories (`is_active=0`, preserving all historical rows); consolidate optional **Outstation Travel / Stay / Food** logistics into a collapsible optional section in `ATTENDEE`; replace the generic `Occupation` dropdown with multi-select **Attendee Self-Identification Tags**; include **Age Group** (with conditional Under-18 Guardian block), **Festival Days**, **Programme Interests**, **Shared Accessibility** (expanded to 18 items), **Emergency Contact**, and **Consents**.
  - **Option B:** Lean Attendee form (drop Outstation Travel, Lodging, and Food Preference entirely; collect only City/State, Age Group, Self-Identification Tags, Festival Days, Shared Accessibility, Emergency Contact, and Consents).
- **Sub-question (`RD-01` under `D-08`):** Should **Emergency Contact** on the **Attendee** form be **Required** (matching `Delegate Registration Form.docx` Sec A*) or **Optional** (while remaining **Required** for Volunteer and SLI per `VOLUNTEER AND SLI.docx`)? *(Technical Recommendation: Optional for general day-visitor Attendees, Required for Under-18 Attendees, Volunteers, and SLIs — OR Required for all if organizers mandate safety contact for every attendee.)*

### `D-09` — Multiple Category Registrations Under One Participant Account
- **Options:**
  - **Option A (Technical Recommendation):** Allow **1 authenticated participant account** to hold up to **1 active registration per primary category** (e.g., an individual can register as both `VOLUNTEER` and `ATTENDEE`, or `SLI` and `ATTENDEE`), displayed as separate cards in the shared Dashboard.
  - **Option B:** Restrict each account to strictly 1 active category total across the festival.
- **Phase 6 Impact:** Controls duplicate-category guard in `POST /api/registrations` and category-hub button states (*"Already Applied"* vs *"Apply"*).

### `D-12` — SLI Gujarat Residency / Practice Eligibility
- **Source Conflict (`CNF-01`, `CNF-07`):** `VOLUNTEER AND SLI.docx` titles Form 2 *"Sign Language Interpreters (Gujarat only)"*, `VOLUNTEER AND SLI 1.docx` softens it to *"Sign Language Interpreters (Gujarat)"*, and `Action_Plan.pptx` Slide 15 explicitly warns *"Not enough interpreters in Gujarat — book through ISLRTC and deaf associations"*.
- **Options:**
  - **Option A (Technical Recommendation):** Collect structured `City / District` + `State` + explicit `Based in Gujarat? (Yes / No)` (no silent default `true`), **without hard-blocking** non-Gujarat ISLRTC/national interpreters at submission. Give SLI Coordinators a *"Gujarat Priority"* filter in `/admin`.
  - **Option B:** Hard-block non-Gujarat applicants at submission on `/apply/sli`.
- **Phase 6 Impact:** Determines whether `sli.based_in_gujarat = false` triggers a server-side rejection or a review-queue tag.

### `D-13` — CRR Number Requirement for SLI Applicants
- **Source Conflict (`CNF-01`, `CNF-07`):** `VOLUNTEER AND SLI.docx` Form 2 includes `CRR No.` alongside qualification options that explicitly invite *"Professional Practitioner / Deaf Interpreter / Fluent Community Signer"* and *"Student / Trainee Interpreter"*, whereas `VOLUNTEER AND SLI 1.docx` removes `CRR No.` entirely.
- **Options:**
  - **Option A (Technical Recommendation):** Keep CRR **Optional** for Deaf Interpreters, Fluent Community Signers, and Student/Trainee Interpreters; **prompt prominently (optional or conditionally required per Owner preference)** ONLY when qualification = *"Certified Indian Sign Language (ISL) Interpreter (ISLRTC / RCI recognized)"*. Never enforce unverified regex prefix assumptions.
  - **Option B:** Keep CRR **Optional** across all 4 qualification tiers (mirrors `VOLUNTEER AND SLI 1.docx` + optional field).
  - **Option C:** Remove CRR from the SLI form entirely (pure `VOLUNTEER AND SLI 1.docx`).

### `D-14` — CRR Number Requirement for Volunteer Applicants
- **Source Conflict (`CNF-01`, `CNF-08`):** `VOLUNTEER AND SLI.docx` Form 1 includes `CRR No.`, while `VOLUNTEER AND SLI 1.docx` Form 1 removes it. Most target volunteers (~645 college/NSS/NCC/community volunteers) do not hold RCI CRR numbers.
- **Options:**
  - **Option A (Technical Recommendation):** Keep CRR **Optional** on the Volunteer form with plain-language helper text (*"Only if you are an RCI-registered rehabilitation professional / special educator; students & general volunteers leave blank"*).
  - **Option B:** Remove CRR completely from the Volunteer form (matching `VOLUNTEER AND SLI 1.docx`).

### `D-15` — Supporting Document Uploads (SLI / Volunteer)
- **Source Conflict (`CNF-01`):** `VOLUNTEER AND SLI 1.docx` mentions optional/required *"Upload Certification / Resume / Portfolio"* for SLIs; `VOLUNTEER AND SLI.docx` omits uploads.
- **Options:**
  - **Option A (Technical Recommendation — Locked Default for Phase 6):** **Defer document uploads to Phase 16.** Launch Phase 6 with structured Qualification tier + CRR text + validated `https://` Video Demo URL.
  - **Option B:** Authorize a dedicated private non-webroot upload pipeline (MIME sniffing, size/quota caps, AV/quarantine, authenticated streaming download) in Phase 16.

### `D-24` — Para-Sports Athlete Interest Scope
- **Source Conflict (`CNF-02`, `CNF-10`):** `Delegate Registration Form.docx` Sec C asks *"I want to take part in para sports as an athlete (Yes/No)"* + 8 sports checkboxes with *"separate eligibility and classification rules apply; the sports team will contact you"*.
- **Options:**
  - **Option A (Technical Recommendation):** Include as an optional Yes/No toggle + 8-sport checklist inside **Attendee** (exportable roster for MSU Physical Education to run offline classification), preserving strictly 3 primary categories (`CAD-02`).
  - **Option B:** Exclude Para-Sports athlete interest from the web registration portal and handle exclusively offline via MSU Dept. of Physical Education.

---

## 3. Related Micro-Decisions Surfaced by Field Reconciliation

| Ref | Linked ID | Topic | Technical Recommendation (Pending Approval) |
| :--- | :--- | :--- | :--- |
| **`RD-02`** | `D-14` / `D-18` | **Volunteer Minimum Age & Age vs. DOB** | Collect numeric **Age** (`16–85` for Volunteer, `18–85` for SLI) rather than full Date of Birth to minimize sensitive PII, or confirm if exact DOB is required for certificates/insurance. |
| **`RD-03`** | `D-18` | **Volunteer & SLI Shift Grid Hours** | Use 2 shifts per day across 21–23 Jan 2027 (`Shift 1: 08:00–14:30 IST`, `Shift 2: 14:00–20:30 IST`) aligned with `Programme_Grid.xlsx` (`08:00–20:30` operating hours); confirm if a 3-slot grid (Morning / Afternoon / Evening) is preferred. |
| **`RD-04`** | `D-18` | **Volunteer Secondary Roles & Para-Sports Support** | Keep the exact **5 organizer primary roles** from `VOLUNTEER AND SLI.docx` as a required single-choice radio, plus an optional multi-select *"Additional areas you can help with"* (including `Para-Sports Support` from `Action_Plan.pptx` Slide 11). |
| **`RD-05`** | `D-23` / `D-27` | **Optional Future-Updates Consent Wording** | Keep `"Future event updates"` strictly optional and use neutral wording (*"Purple Festival Gujarat and partner inclusion events"*) until official organiser names are locked under `D-23`. |
| **`RD-06`** | `D-10` / `D-21` | **Participant Verification & Legacy Staging Accounts** | Required in Phase 5 before Phase 6: confirm Email + Password + Email Verification (`D-10`) and staging DB preservation / verified-email account claim (`D-21`). |

---

## 4. Sign-Off Summary Table (Blocking Phase 6 Only)

| Decision ID | Decision Topic | Blocks | Recommended Option | Owner Sign-Off (`APPROVED` / `MODIFIED` / `DEFERRED`) |
| :--- | :--- | :--- | :--- | :--- |
| **`D-10`** | Participant Auth & Email Verification | Phase 5 -> Phase 6 | Email + Password + Verified Email Link/OTP | `[ PENDING ]` |
| **`D-21`** | Existing Staging Data Disposition | Staging / Phase 5 | Inspect staging DB; preserve + verified-email claim | `[ PENDING ]` |
| **`D-09`** | Multiple Categories per Account | Phase 5 / Phase 6 | Allow up to 1 active registration per primary category | `[ PENDING ]` |
| **`D-05` / `D-07`** | Attendee Fee & Escort Policy | Phase 6 | Free (`₹0`), hide coupons; optional Escort in Accessibility | `[ PENDING ]` |
| **`D-06` / `D-08`** | Delegate Consolidation & Attendee Fields | Phase 6 | Consolidate optional Delegate logistics into Attendee; deactivate 5 legacy categories | `[ PENDING ]` |
| **`D-12`** | SLI Gujarat-Only vs. Priority Filter | Phase 6 | Collect State + Based in Gujarat without hard-blocking ISLRTC outstation SLIs | `[ PENDING ]` |
| **`D-13`** | SLI CRR Requirement | Phase 6 | Optional for Deaf/Community/Trainees; prompted (or conditional) for Certified tier only | `[ PENDING ]` |
| **`D-14`** | Volunteer CRR Requirement | Phase 6 | Optional (never block student/NSS/NCC volunteers) | `[ PENDING ]` |
| **`D-15`** | Volunteer / SLI File Uploads | Phase 6 | Defer to Phase 16 (use CRR + `https://` Video Demo URL) | `[ PENDING ]` |
| **`D-24`** | Para-Sports Athlete Interest | Phase 6 | Optional toggle + 8-sport checklist inside Attendee | `[ PENDING ]` |
| **`D-16` / `D-17`** | Edit/Withdrawal & Approval State Machine | Phase 7 | Attendee instant `CONFIRMED`; Vol/SLI coordinator review + separate `assignment_status` | `[ PENDING ]` |
| **`D-18`** | Day × Shift Grid & Assignment Scope | Phase 6 / Phase 12 | 3 days × 2 shifts (`08:00–14:30` / `14:00–20:30`) | `[ PENDING ]` |
| **`D-27`** | Sensitive Disclosure Retention Window | Phase 6 / Phase 11 | Post-festival purge window for disability disclosures + DPDP export/delete | `[ PENDING ]` |
