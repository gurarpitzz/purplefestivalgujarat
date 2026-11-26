# Proposed Attendee Registration Form Specification (`ATTENDEE`)

**Status:** Pre-Phase 6 Reconciliation Proposal (**NOT YET IMPLEMENTED** — zero frontend or schema mutations until Phase 6 is authorized)  
**Target Category:** `ATTENDEE` (Primary Category 1 of 3 per `CAD-02`)  
**Sources Reconciled:** `Delegate Registration Form.docx`, `Purple Festival Gujarat 2027 Brochure (3).pdf`, `Purple_Festival_Gujarat_2027_Action_Plan.pptx`, `Purple_Festival_Gujarat_2027_Programme_Grid.xlsx`, `apply/index.php`, `assets/js/registration-wizard.js`, `api/registrations/index.php`, `database/schema.sql`, `CONFIRMED_ARCHITECTURAL_DECISIONS.md`, `REMAINING_OWNER_DECISIONS.md`.

---

## 1. Authority Distinction: Organizer-Confirmed vs. Technical Recommendations

| Classification | Rules & Scope |
| :--- | :--- |
| **Organizer-Confirmed Requirements** | • Strictly **3 primary categories** (`ATTENDEE`, `SLI`, `VOLUNTEER`); no separate `ACCESSIBLE` pathway (`CAD-02`, `CAD-07`).<br>• **Account-based participant experience** — participant signs in / creates account first (`CAD-03`); common identity is retrieved from the authenticated account, never re-typed per application.<br>• **Zero event-entry QR pass issuance** — official atomic Registration ID (`PFG-2027-NNNNNN`) only (`CAD-06`, `CAD-13`, `CAD-14`).<br>• **Cross-category optional accessibility & accommodations** without forcing a disability diagnosis (`CAD-07`).<br>• **Delegate Registration Form.docx baseline** for Attendee contact, age group, self-identification, festival days, programme interests, under-18 parent/guardian consent, and DPDP/media declarations.<br>• **Non-destructive preservation** of all historical `DELEGATE`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, and `ALLY` rows (`CAD-09`). |
| **Technical Recommendations (Pending Owner Sign-Off)** | • Consolidating optional legacy Delegate outstation travel/lodging/food fields (`D-06`, `D-08`) into a collapsible optional Attendee section.<br>• Absorbing legacy `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, and `ALLY` categories into Attendee multi-select **Self-Identification Tags** (`D-06`, `D-08`).<br>• Handling Para-Sports Athlete interest as an optional Attendee toggle + sports checklist (`D-24`) rather than a 4th category.<br>• Keeping Attendee registration **Free (`₹0`)** with coupon/payment UI completely removed until explicitly authorized (`D-05`, `D-07`).<br>• Storing Attendee-specific data in a dedicated canonical `attendee_details` table while keeping historical `delegate_details` intact. |

---

## 2. Entry Precondition & Account Identity Retrieval (No Repeated Entry)

Per `CAD-03`, participants authenticate via Phase 5 Account Creation / Login (`/login` or `/register`) before opening `/apply/attendee`.

### Section 0 — Authenticated Account Identity Card (Read-Only Summary)
Retrieved automatically from `users` (authenticated session). Never re-typed on the form; includes an accessible **"Edit Account Profile"** link to `/dashboard#profile`:

| Field Identifier | Displayed Label | Source Column | Notes |
| :--- | :--- | :--- | :--- |
| `account.full_name` | Full Name | `users.full_name` | Snapshotted into `registrations.full_name` on submit |
| `account.email` | Verified Email Address | `users.email` | Verified in Phase 5 (`D-10`); used for confirmation email |
| `account.phone` | Mobile / WhatsApp Number | `users.phone` | Required on profile before submitting; automated WhatsApp deferred (`D-11`) |
| `account.city_district` | City / Town & District | `users.city_district` | Consolidated from legacy 3-table `city_state` fields |
| `account.state` | State / Union Territory | `users.state` | Structured dropdown (no silent default); `account.country` shown if non-India |
| `account.preferred_language` | Preferred Communication Language | `users.preferred_language` | Optional (`Gujarati / Hindi / English / ISL`) |

> **Eliminated from Attendee Form:** Re-entering Full Name, Email, Phone, Country, Account Password (`account.password`), Category radio switching (`DELEGATE`/`STUDENT`/`PROFESSIONAL`/`INSTITUTIONAL`/`ALLY`), and Coupon/Fee inputs (`reg.coupon_code`).

---

## 3. Section-by-Section Proposed Attendee Form Layout

### Section 1 — About You & Minor Safeguarding
*Source: `Delegate Registration Form.docx` Sections A, B & F; replaces generic Step 2 `Occupation` dropdown (which misleadingly defaulted to `"Student"`).*

1. **Age Group** (`attendee.age_group`) — **Required** *(pending `D-08`)*  
   - **Control:** Radio group (unselected by default — no misleading default)  
   - **Options:** `Under 18` · `18–35` · `36–59` · `60 and above`  
   - **Conditional Logic:** Selecting **`Under 18`** automatically reveals the mandatory **Parent / Legal Guardian** block below.
2. **Gender** (`attendee.gender`) — **Optional**  
   - **Control:** Select dropdown (blank default: *"Select (Optional)"*)  
   - **Options:** `Female` · `Male` · `Non-binary` · `Other` · `Prefer not to say`
3. **Are you registering on someone else's behalf?** *(Optional checkbox, unchecked by default)* — Also triggers the Parent / Guardian / Representative block per `Delegate Registration Form.docx` Section F.
   - **Parent / Legal Guardian Full Name** (`attendee.minor_guardian_name`) — **Conditionally Required** when `Under 18` or assisted (`2–150` chars). *(Strictly separated from adult Personal Assistant / Escort, which lives in Section 4 Shared Accessibility.)*
   - **Relationship to Participant** (`attendee.minor_guardian_relationship`) — **Conditionally Required** when `Under 18` or assisted (`Parent`, `Legal Guardian`, `School Representative`, `Organisation Representative`, `Caregiver`, `Other`).
4. **I am attending as (select all that apply)** (`attendee.self_identification_tags`) — **Required (≥ 1)** *(pending `D-08`)*  
   - **Control:** Multi-select checkboxes (all unchecked by default)  
   - **Options (reconciled from `Delegate Registration Form.docx` Sec B & absorbs legacy DB categories):**
     - `Parent / Family Member / Caregiver`
     - `Student` *(absorbs legacy `STUDENT`)*
     - `Teacher / Special Educator`
     - `Professional (Therapist, Social Worker, Doctor, Lawyer, Researcher, Corporate)` *(absorbs legacy `PROFESSIONAL`)*
     - `Athlete / Coach`
     - `Representative of an Organisation, NGO, or DPO` *(absorbs legacy `INSTITUTIONAL`)*
     - `Government Official`
     - `Media`
     - `Ally / General Public` *(absorbs legacy `ALLY`)*
   - *(Note: "Person with disability" is intentionally omitted here and handled privately inside the restricted Shared Accessibility module; "Volunteer" is omitted because it has its own primary category `/apply/volunteer`.)*
5. **Organisation / Institution / School** (`reg.organization_name`) — **Optional** (`0–200` chars)
6. **Designation / Class or Course** (`reg.designation`) — **Optional** (`0–150` chars)

---

### Section 2 — Festival Days & Programme Interests
*Source: `Delegate Registration Form.docx` Section C; `Brochure (3).pdf` pp. 3–4; `Programme_Grid.xlsx`.*

1. **Festival Days I Will Attend** (`reg.attending_days`) — **Required (≥ 1)**  
   - **Control:** Checkboxes — **ALL UNCHECKED BY DEFAULT** *(eliminates existing bug where all 3 days are silently pre-checked)*  
   - **Options:**
     - `Day 1 — Thursday, 21 January 2027` (`DAY_1`)
     - `Day 2 — Friday, 22 January 2027` (`DAY_2`)
     - `Day 3 — Saturday, 23 January 2027` (`DAY_3`)
2. **Events & Sessions I Want to Attend** (`reg.interested_tracks`) — **Optional** *(populates existing unused `registrations.interested_tracks` column)*  
   - **Control:** Multi-select checkboxes (all unchecked by default)  
   - **Options:**
     - `Opening ceremony and cultural programme`
     - `Purple March, led by Paralympic medallists`
     - `We Care Film Festival screenings`
     - `Panel discussions and sessions`
     - `Para sports — as a spectator`
     - `Exhibition / stalls (Purple Fair)`
     - `Closing ceremony and cultural programme`
3. **Interested in taking part in Para-Sports as an Athlete?** (`attendee.para_sports_athlete_interest`) — **Optional** *(Pending `D-24`)*  
   - **Control:** Radio (`Yes` / `No`, unselected by default)  
   - **Notice:** *"Separate eligibility and classification rules apply; the MSU Department of Physical Education sports team will contact interested athletes separately."*  
   - **Conditional Sub-field (if `Yes`):** Multi-select checkboxes (`Athletics`, `Wheelchair Basketball`, `Blind Cricket`, `Deaf Sports`, `Special Olympics Events`, `Para Badminton`, `Boccia`, `Other` + optional text `≤120` chars).

> **Explicitly Excluded (`D-25`):** Legacy paper checkboxes *"Perform in the cultural programme"* and *"Put up a stall / exhibit"* are **NOT** included inside Attendee registration; a concise footer note directs performers, exhibitors, sponsors, and speakers to the Secretariat contact page.

---

### Section 3 — Optional Outstation Travel, Stay & Food Logistics *(Consolidated from Legacy Delegate — Pending `D-06` & `D-08`)*
*Source: `Delegate Registration Form.docx` Section E; `Brochure (3).pdf` p. 4; `Action_Plan.pptx` Slide 10.*

1. **Are you travelling from outside Vadodara?** (`attendee.outstation_travel`) — **Optional Checkbox** (unchecked by default)
   - **Disclaimer:** *"Travel and accommodation support, if any, is limited and confirmed separately by the organizers."*
   - **Conditional Sub-fields (shown only when checked):**
     - `Arriving on (date & approximate time)` (`attendee.arrival_departure_mode`, optional, Jan 2027 window)
     - `Departing on (date & approximate time)` (`attendee.arrival_departure_mode`, optional, `≥ arrival`)
     - `Mode of Travel` (`Train` / `Bus` / `Flight` / `Own vehicle`, unselected default)
     - `Do you need accommodation / stay guidance?` (`Yes` / `No`, unselected default)
     - *(If lodging = `Yes`)* `Accessible room needed?` (`Yes` / `No`, unselected default — **classified `SENSITIVE_ACCOMMODATION`**, gated behind `accommodations.read`)
     - `Number of people travelling with you` (`0–10`, blank default)
2. **Food Preference** (`attendee.food_and_dietary`) — **Optional** *(Pending `D-06` & `D-08`)*  
   - **Control:** Select dropdown — **BLANK DEFAULT** *(eliminates existing misleading `"Vegetarian"` default)*  
   - **Options:** `Vegetarian` · `Jain` · `Vegan` · `Non-Vegetarian`
3. **Dietary Needs or Allergies** (`attendee.food_and_dietary`) — **Optional** text (`≤300` chars; encrypted at rest via AES-256-GCM if medical allergy disclosed).

---

### Section 4 — Shared Accessibility & Accommodations Module *(Reusable Component)*
Identical across `ATTENDEE`, `VOLUNTEER`, and `SLI` — specified in full in [SHARED_ACCESSIBILITY_FORM_SPECIFICATION.md](SHARED_ACCESSIBILITY_FORM_SPECIFICATION.md):
- Privacy assurance banner (`accommodations.read` RBAC + AES-256-GCM + zero `/rgst` visibility).
- Voluntary `is_person_with_disability` (`Yes / No / Prefer not to say`, unselected default) -> conditionally reveals grouped **21 RPwD Act 2016** checklist + optional self-description (`≤1,000` chars).
- **Open-to-all** expanded **18-item Accommodation checklist** (13 existing + 5 missing from `Delegate Registration Form.docx` Sec D) + free-text support notes (`≤1,000` chars).
- Optional **Personal Assistant / Escort accompanying you** checkbox + optional Escort Name (`access.personal_assistant_escort`, separated from Under-18 Guardian).

---

### Section 5 — Emergency Contact *(Shared Component)*
*Source: `Delegate Registration Form.docx` Section A (`Emergency contact name and mobile*`); inherited from Account Profile if already provided on another category.*
- **Emergency Contact Name** (`shared.emergency_contact_name`) — Required *(confirm mandatory vs. optional for Attendee under `D-08`)*, `2–150` chars.
- **Emergency Contact Relationship** (`shared.emergency_contact_relationship`) — Optional/Recommended on Attendee (`Parent`, `Spouse/Partner`, `Sibling`, `Relative`, `Friend`, `Colleague`, `Caregiver`, `Other`).
- **Emergency Contact Phone Number** (`shared.emergency_contact_phone`) — Required *(per `D-08`)*, valid phone (`7–15` digits).

---

### Section 6 — Declarations & Consents *(Shared Component)*
*Source: `Delegate Registration Form.docx` Section F + DPDP Act 2023.* All checkboxes start **unchecked**:
1. **Code of Conduct & Zero-Tolerance Policy** (`consent.code_of_conduct`) — **Required**
2. **DPDP Act 2023 Data Processing Consent** (`consent.data_processing_dpdp`) — **Required** (with link to `/legal/privacy`)
3. **Information Accuracy Declaration** (`consent.accuracy_declaration`) — **Required**
4. **Photography & Filming Consent** (`consent.photography_media`) — **Optional** (*"Declining this does not affect your participation."*)
5. **Future Event Updates Opt-in** (`consent.future_updates`) — **Optional** *(exact organiser wording pending `D-23`)*

---

## 4. Submission Behavior & Confirmation Contract (Zero QR Issuance)
- **Fee Policy:** Free (`₹0`, `payment_status = 'NOT_REQUIRED'`) unless Owner explicitly approves fees under `D-05`. Zero coupon input shown while free.
- **Initial Status:** `CONFIRMED` immediately (free Attendee requires no admin review per `D-17`).
- **Official Identifier:** Atomic `PFG-2027-NNNNNN` allocated via `includes/registration-id.php`. **Zero QR credential issued** (`CAD-13`).
- **Confirmation Screen & Email:** Displays Registration ID, attending days, and instructions to quote the Registration ID or registered name at the Vadodara Registration Desk (`/rgst` manual attendance check-in), plus a link to the Participant Dashboard to view or update accommodations anytime.
