# Festival Requirements Master — Purple Festival Gujarat 2027

**Compiled From:** Direct line-by-line inspection of all 6 mandatory festival source documents + 3 supplementary workspace documents + Project Owner Directives (Section 3).  
**Authority Rule (Section 2.3):** Owner Directives > Formally Approved Organizer Requirements > Organizer Forms > Brochure > Draft Action Plan / Programme Grid > Forensic Audit > Existing Code > Technical Recommendations.

---

## 1. Festival Identity, Dates & Mission
- **Event Name:** Purple Festival Gujarat 2027 (*"First Edition in Gujarat — Celebrating ability. Building an inclusive Gujarat."*)
- **Dates:** Thursday 21 January – Saturday 23 January 2027 (3 days; proposed operating hours 08:00–20:30 IST).
- **Location:** Vadodara, Gujarat, India.
- **Core Purpose (Brochure p.2 & Action Plan Slide 2):** Bring persons with and without disabilities together as equals across sport, film, culture, policy, employment, and education; make host venues (especially MSU Baroda) **permanently accessible** as a lasting legacy beyond the 3-day event.
- **Scale Planning Estimates (NOT hardcoded system caps):**
  - ~5,000 expected attendees (`Brochure p.4`)
  - ~500 participants with and without disabilities (`Brochure p.4`)
  - ~100 outstation guests (`Brochure p.4`)
  - ~25 para-sports games & events (`Brochure p.1,3,4`)
  - 32 proposed sessions across 8 programme strands (`Programme_Grid.xlsx`, `Action_Plan.pptx Slide 5`)
  - ~645 volunteers to recruit (peak 430 on duty × 1.5 shift/no-show factor; `Programme_Grid.xlsx`)
  - ~23 ISL interpreters calculated (peak 10 × 2 shifts + 15% reserve; 174 pair interpreter-hours) vs. 40 organizer estimate (`Programme_Grid.xlsx`)
  - 2 peak live captioners (main halls only) & 24 funded panellists (6 panels × 4) (`Action_Plan.pptx Slides 6,8`)

---

## 2. Proposed Programme Strands & Venues (Draft — Pending Final Confirmation)

### 2.1 Eight Principal Programme Strands (`Action_Plan.pptx Slide 5` & `Programme_Grid.xlsx`)
1. **Think Tanks:** Policy dialogue with government (Accessibility in Gujarat, Employment & Livelihoods, Inclusive Education, Rural Inclusion & Panchayats, Women with Disabilities, Gujarat 2028 Accessibility Commitments).
2. **Disability Futures (Conference Strand — Clash Pending Resolution):** Inclusive curriculum & pedagogy, Teachers/technology/AI, Teaching as a collaborative profession, Community-based inclusive education & rehabilitation (Hall B, pending merge agreement with PRAANGAN).
3. **Para Sports:** ~25 games (Paralympic sports, Special Olympics, deaf sports, blind cricket, wheelchair basketball, boccia, para badminton, athletics), Purple March led by Paralympic medallists, Celebrity Friendship Match with *Sitaare Zameen Par* artists, and "Try a para sport" open to public.
4. **Exhibition & Marketplace (Purple Fair / Divya Kala Mela):** Stalls, corporate/govt pavilions, assistive technology try-it zone, children's inclusive play zone.
5. **Employment:** Job fair with on-the-spot interviews, enterprise market.
6. **Culture & Film:** International We Care Film Festival (captioned & audio-described screenings + filmmaker talks), Purple Rain cultural evening, Purple Kaleidoscope music & dance.
7. **Rights & Services:** UDID registration desk, government schemes help desk, Jagruti Mela ("ask the service providers").
8. **Youth:** Student dialogue (young people with & without disabilities), try-a-sport.

### 2.2 Venue Confirmation Matrix (`Programme_Grid.xlsx Venues` & `Action_Plan.pptx Slide 4`)
- **Confirmed (1):** **Sama Indoor Stadium** (Para sports competitions; *Sitaare Zameen Par* celebrity match).
- **Committed / Pending Written Access Audit & Legacy Agreement:** The Maharaja Sayajirao University of Baroda (MSU) — Faculty of Social Work (Registrar approval noted) and Department of Physical Education (65,000 sq m campus infrastructure detailed in `Red and white General Sport Activities Presentations.pdf`, which describes MSU's general university sports facilities rather than festival-specific para-sports allocations).
- **Listed in Brochure / `WEBSITE PFV.docx` (Pending Confirmation in Grid):** GMERS Medical College & Hospital, Gotri, Vadodara.
- **Placeholder Programme Grid Slots (9):** Main Hall, Hall A, Hall B, Hall C, Auditorium, Exhibition Ground, Sports Ground, Open-air Stage, Services Block.

---

## 3. Confirmed Platform Architecture Requirements (Owner Directives — Section 3)

1. **Three Primary Participant Categories ONLY:** `ATTENDEE`, `SLI` (Sign Language Interpreter), `VOLUNTEER`. Existing `DELEGATE`, `STUDENT`, `PROFESSIONAL`, `INSTITUTIONAL`, `ALLY`, and `ACCESSIBLE` categories must be consolidated, migrated, or retired cleanly without destroying historical data.
2. **Account-Based Participant Experience:** Public Website -> Login / Account Creation -> Authenticated Participant Account -> Select/Access Category (`ATTENDEE` / `SLI` / `VOLUNTEER`) -> Complete Registration/Application -> Confirmation / Admin Review -> Authenticated Participant Dashboard.
3. **Three Primary Administrative Roles:** `SUPERADMIN`, `ADMIN` (with Superadmin-configurable custom operational permission profiles), and `REGISTRATION DESK` (`RGST`).
4. **Registration Desk Retained with Manual Attendance Marking:** Retire live camera/USB QR scanning for event-day check-in; retain `/rgst` portal for accessible manual search + checkbox attendance marking of registered Attendees, SLIs, and Volunteers without QR dependency. Retain `qrcode-gen.js` for Admin TOTP MFA and retain human-readable Registration IDs (`PFG-2027-*`) independently of QR credentials.
5. **Preserve Public Website Visual Identity:** Keep existing homepage, Viksit Bharat, Partners, 21 Disabilities, Programme, Accessibility, and Gallery visual designs; modify only for accuracy, security, WCAG 2.2 AA accessibility fixes, and revised auth/registration navigation.
6. **Retain Native PHP + MySQL + PDO + Vanilla JS + HTML5/CSS3 Stack** on Bluehost Apache / cPanel.

---

## 4. Category-Specific Field Requirements (From Organizer Forms)

### 4.1 Volunteer Form Requirements (`VOLUNTEER AND SLI.docx` Form 1 — Primary Source)
- **Personal & Contact:** Full Name, Email Address, WhatsApp/Contact Number, Current City/District in Gujarat, Age/Date of Birth, Gender (`Male / Female / Non-binary / Prefer not to say / Other`), MSU Student/Alumni (`Yes, currently enrolled / Yes, MSU Alumni / No`), Conditional Faculty/Department Name, `CRR No.` *(present in `VOLUNTEER AND SLI.docx`, absent in `VOLUNTEER AND SLI 1.docx` — see `D-14`)*.
- **Disability & Accessibility (Optional):** Identify as PwD (`Yes / No / Prefer not to say`); conditional disability types (`Visual / Locomotor / Hearing / Speech / Neurodivergent / Other` — evaluate mapping to 21 RPwD taxonomy); free-text accessibility support / assistive device requirements.
- **Role Preferences & Experience:** Primary role (`Stage Anchoring / Hosting`, `Event Logistics & Venue Management`, `Guest Registration & Hospitality`, `Accessibility Assistance / Peer Guide`, `Technical / Media Support`); conditional public speaking/stage hosting experience paragraph; Language proficiency (`Gujarati / Hindi / English / Other`). *(Additions in `VOLUNTEER AND SLI 1.docx` / Action Plan: festival day/shift availability.)*
- **Emergency Contact & Declaration:** Emergency Contact Name & Relationship, Emergency Contact Number, Code of Conduct & Consent (`"I agree to maintain an inclusive, respectful environment aligned with the Purple Festival."`).

### 4.2 Sign Language Interpreter (SLI) Form Requirements (`VOLUNTEER AND SLI.docx` Form 2 — Primary Source)
- **Title in Primary Doc:** *Sign Language Interpreters (Gujarat only)* (vs. *Sign Language Interpreters (Gujarat)* in `VOLUNTEER AND SLI 1.docx` — see `D-12`).
- **Personal & Contact:** Full Name, Email Address, WhatsApp/Contact Number, Current City/District in Gujarat, Age/Date of Birth.
- **Qualifications & Experience:** Qualification level (`Certified Indian Sign Language (ISL) Interpreter (e.g., ISLRTC / RCI recognized)`, `Professional Practitioner / Deaf Interpreter / Fluent Community Signer`, `Student / Trainee Interpreter`, `Other`); `CRR No.` *(conditional/clarification required per `D-13` because trainees & community signers are allowed)*; Years of experience (`< 1 year / 1-3 years / 3-5 years / 5+ years`). *(Additions in `VOLUNTEER AND SLI 1.docx`: Training institute, file upload for certification/resume/portfolio, video demo URL, preferred dates/time slots, honorarium/travel support notes.)*
- **Preferences:** Comfortable interpreting for cultural performances, panel discussions, and official ceremonies (`Yes / No`).
- **Emergency Contact & Declaration:** Emergency Contact Name & Relationship, Emergency Contact Number, Code of Conduct & Consent.

### 4.3 Attendee Form Baseline (No Finalized Standalone Attendee Form in Supplied Docs)
- `Delegate Registration Form.docx` (*"Purple Festival Gujarat 2027 – Registration Form"*) provides the closest organizer-drafted general attendee/visitor field set: Personal & contact (Full name, Gender, Age group, Mobile/WhatsApp, Email, City/District, State, Preferred language incl. ISL, Emergency contact, Under-18 guardian details), Self-identification tags (11 personas), Optional disability disclosure & 12 accessibility support checkboxes + free text, Attendance days (21/22/23 Jan) & event interests, Optional outstation travel/lodging/accessible room/dietary preferences, and 4 consent checkboxes (Data use, Photography opt-in, Future updates opt-in, Accuracy).
- Requires Owner approval (`D-08`, `D-06`, `D-05`) on final mandatory vs. optional Attendee fields.

---

## 5. Accessibility, Operations & Communications Requirements
- **Accessibility Commitments (`Brochure p.5`, `Action_Plan.pptx Slide 7`):** WCAG 2.2 AA web platform (fixing all verified contrast, focus, markup, and target-size failures); cross-category accessibility & accommodation requests (without forcing a disability diagnosis to request an accommodation); venue accessibility services (ISL, live captions, Braille/large print/easy-read, ramps/accessible toilets, sensory quiet rooms, wheelchair assistance).
- **Operations:** Manual Registration Desk attendance marking (Attendees, SLIs, Volunteers) decoupled from QR scanning and approval status; Superadmin-managed Admin operational permission profiles; audited sensitive-data access; programme/venue & optional volunteer/SLI assignment scheduling (accounting for 2-interpreter pairing & 20–30 min rotation per `Programme_Grid.xlsx`).
- **Communications:** Reliable transactional email service (Resend + Bluehost cron queue processor + honest delivery state machine `CREATED -> QUEUED -> PROCESSING -> PROVIDER_ACCEPTED -> DELIVERED/FAILED/BOUNCED`), zero third-party QR token leakage, accessible HTML + plain-text templates across all approved lifecycle events. SMS/WhatsApp architecture prepared as optional future plug-in pending `D-11`.

