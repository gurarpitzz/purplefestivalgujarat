# Accessibility Compliance & Remediation Report (WCAG 2.2 AA — Phase 15)

**Project:** Purple Festival Gujarat 2027  
**Standard Targeted:** W3C Web Content Accessibility Guidelines (WCAG) 2.2 Level AA + India RPwD Act 2016 Digital Accessibility Mandate (`CAD-07`)  
**Verification Classification (§49–§50 Honesty Rule):** **`VERIFIED WORKING LOCALLY`** (Automated DOM, CSS contrast, keyboard navigation, ARIA live-region, and headless Chromium Playwright verification) · **Manual Assistive Technology (NVDA / JAWS / VoiceOver / TalkBack) & Disabled User Testing:** **`NOT YET EXECUTED (REQUIRED PRE-LAUNCH GATE)`**

> [!IMPORTANT]
> **Strict Honesty Disclosure (§50):** Passing automated checks (DOM semantics, computed contrast ratios, keyboard focus order, and Playwright/axe rules) verifies structural and programmatic WCAG 2.2 AA compliance, **not** full real-world assistive-technology certification. No claim is made that live manual testing with NVDA, JAWS, VoiceOver, TalkBack, or switch devices has been completed. Section 5 defines the mandatory manual assistive-technology testing protocol required before public launch.

---

## 1. Phase 15 Core WCAG 2.2 AA Remediations Implemented (`FND-20`, `FND-46`)

| Defect / Criterion | WCAG 2.2 SC | Root Cause Identified | Remediation Implemented & Verified Locally |
|---|---|---|---|
| **High-Contrast Mode White-on-White Text Failure (`FND-20a`)** | **1.4.3 Contrast (Minimum)** (AA) / **1.4.6 Contrast (Enhanced)** (AAA) | `data-contrast="high"` (`.high-contrast`) left light card surfaces white while forcing text to `#FFFFFF`, rendering text invisible (`1.0:1`). | Rewrote `[data-contrast="high"]` / `.high-contrast` in `assets/css/app.css` to enforce pure `#000000` backgrounds across all cards, modals, tables, forms, and sections with `#FFFFFF` primary text (`21:1`) and `#FFEA00` accent text (`19.6:1`), `#FFFFFF` borders, and preserved partner logo tiles with `#000000` fallback text. |
| **Yellow-on-Black Mode Contrast Inversion (`FND-20b`)** | **1.4.3 Contrast (Minimum)** / **1.4.11 Non-Text Contrast** | `data-contrast="yellow-black"` rendered `#FFEA00` yellow text over light/white card backgrounds (`1.18:1`). | Enforced `#000000` / `#111111` dark surfaces across all containers under `[data-contrast="yellow-black"]` / `.yellow-black` so `#FFEA00` foreground text maintains **`>= 15.6:1` to `19.6:1`** contrast everywhere. |
| **Standard-Mode Brand Contrast Refinements** | **1.4.3 Contrast (Minimum)** | Secondary muted text and teal/purple accents on light surfaces fell near the `4.5:1` boundary. | Tuned light-surface tokens in `assets/css/app.css` (`#6D28D9` purple text on light backgrounds `>= 7.1:1`, `#087A7A` accessible teal `>= 4.8:1`, `#5B526E` muted text `>= 6.3:1`) while preserving the `#5B2A86` royal purple brand identity (`CAD-08`). |
| **Invalid `<button><h3><div>` HTML Nesting in 21 Disabilities Section (`FND-20c`)** | **4.1.2 Name, Role, Value** / HTML5 Spec | `includes/disabilities-section.php` placed block `<h3>` and `<div>` elements inside `<button>`, causing inconsistent screen-reader heading announcement. | Restructured `includes/disabilities-section.php` so `<h3 class="pfg-dis-card-heading">` wraps `<button type="button" class="pfg-dis-card-trigger">` containing strictly phrasing `<span>` elements, paired with `<div role="region" aria-labelledby="...">` and `ArrowUp`/`ArrowDown`/`Home`/`End` keyboard navigation. Verified `0` `<button><h3>` or `<button><div>` violations. |
| **Accessibility Toolbar Live-Region Silence & Page-Load Spam (`FND-46a`)** | **4.1.3 Status Messages** | Repeated preference toggles wrote identical strings to a single live region without clearing, and fired noisy announcements on initial page load. | Implemented `window.pfgAnnounce(msg, politeness)` in `assets/js/accessibility.js` with dedicated polite (`#a11y-live-status`) and assertive (`#a11y-live-assertive`) regions, `requestAnimationFrame` clear-and-write with zero-width space toggling for repeated messages, and silent initial hydration. |
| **Flash of Unstyled / Low-Contrast Content on Navigation (`FND-46b`)** | **1.4.3 Contrast** / **2.3.3 Animation from Interactions** | Accessibility preferences (`contrast`, `fontSize`, `reducedMotion`, `dyslexiaFont`) were applied only after external JS loaded at document end. | Added a synchronous pre-paint inline `<script>` in `includes/header.php` `<head>` that reads `localStorage` (`pfg27_a11y_prefs` / `pfg27_a11y_prefs_v1`) and OS `prefers-reduced-motion: reduce` before CSS paint, and ensured `assets/js/accessibility.js` loads on every public, auth, registration, dashboard, desk, and admin page. |
| **Touch & Pointer Target Sizes** | **2.5.8 Target Size (Minimum)** (WCAG 2.2 AA) | Filter pills, toolbar buttons, and table checkboxes had hit areas smaller than `24×24 CSS px`. | Enforced `min-height: 44px; min-width: 44px` on buttons, tabs, nav links, and filter pills, and `>= 24×24px` visual controls inside `>= 44×44px` hit wrappers for registration and `/rgst` attendance checkboxes. |
| **Focus Visibility & Sticky Header Obscuration** | **2.4.7 Focus Visible** / **2.4.11 Focus Not Obscured (Minimum)** (WCAG 2.2 AA) | Focus outlines were thin or low-contrast on dark sections, and anchored elements could scroll beneath the sticky header. | Enforced `3px solid` high-contrast `:focus-visible` outlines with `2px` offset across light, dark, high-contrast, and yellow-on-black themes, plus `scroll-margin-top: 96px` on all focusable and landmark targets. |

---

## 2. Cross-Category Accessibility & Accommodation Architecture (`CAD-07`)

1. **Accessibility Across All Three Primary Categories (Not a Segregated 4th Category):**
   - `apply/attendee.php`, `apply/volunteer.php`, and `apply/sli.php` all embed the shared partial `includes/form-components/shared-accessibility.php` controlled by `assets/js/shared-accessibility.js`.
   - The legacy standalone `ACCESSIBLE` category card is permanently retired from `/apply`.
2. **Optional Disability Disclosure & Zero Medical Diagnosis Requirement:**
   - *"Do you identify as a Person with Disability?"* offers `Yes`, `No`, and `Prefer not to say` (defaulting to optional/non-blocking).
   - The **Accessibility & Accommodation Support** checklist (13 standardized accommodation types including Indian Sign Language Interpretation, Tactile Interpretation for Deafblind participants, Live Captions / CART, Wheelchair Access, Wheelchair Charging Bay, Sighted Guide, Braille / Large-Print Materials, Quiet / Sensory Room, Accessible Washroom / Lodging, Personal Assistant / Escort Pass, and Dietary Access Support) is available to **every applicant regardless of whether they disclose a disability**.
3. **Accessible Form Error Recovery (WCAG 3.3.1, 3.3.3):**
   - Client and server validation errors focus an `aria-live="assertive"` error summary container at the top of the form with direct anchor links to each invalid field, set `aria-invalid="true"`, and bind field-level error descriptions via `aria-describedby`.

---

## 3. Portal & Transactional Email Accessibility

- **Authenticated Portals (`/dashboard`, `/rgst`, `/admin`):**
  - Full WAI-ARIA tablists (`role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls`, `role="tabpanel"`) with `ArrowLeft`/`ArrowRight`/`Home`/`End` keyboard roving focus.
  - Accessible modal dialogs (`role="dialog"`, `aria-modal="true"`, `aria-labelledby`) with keyboard focus trapping, `Escape` dismissal, and deterministic focus restoration to the triggering control on close.
- **29 Transactional Email Templates (`includes/notification-templates.php`):**
  - Every template renders semantic `<html lang="en">`, layout tables marked `role="presentation"`, explicit high-contrast text colors (`#2B144D` / `#5B2A86` / `#1E1B26` on `#FFFFFF` / `#F6F3FA` yielding **`9.1:1` to `16.8:1`** contrast), zero image-only information, and a complete multipart `text/plain` alternative (`696`–`1,047` bytes) for Braille displays and plain-text screen-reader mail clients.

---

## 4. What Was Verified Locally vs. Known Limitations

| Verification Method | Scope Covered | Status |
|---|---|---|
| **Automated PHP & DOM Structure Audit** | `<html lang="en">`, skip link, single `<main>`, heading hierarchy, zero `<button><h3>`/`<div>` nesting, explicit `<label for>` associations, `aria-live` polite/assertive regions, 29 email HTML + plain-text templates | **`VERIFIED WORKING LOCALLY`** |
| **Headless Chromium Playwright E2E & Computed Style Audit (`1440×900` & `390×844`)** | Standard, High Contrast (`data-contrast="high"`), and Yellow-on-Black (`data-contrast="yellow-black"`) computed foreground/background contrast ratios, mobile `390×844` zero horizontal overflow, keyboard `Tab` focus visibility across `/`, `/programme`, `/accessibility`, `/login`, `/register`, `/apply`, `/dashboard`, `/rgst` | **`VERIFIED WORKING LOCALLY`** |
| **Manual Assistive Technology Testing (NVDA, JAWS, VoiceOver, TalkBack)** | Live speech output cadence, virtual cursor table navigation in `/rgst`, Braille display rendering, mobile touch-explore gestures, and magnification reflow at 400% zoom with real PwD testers | **`NOT YET EXECUTED` (Pre-Launch Gate)** |

---

## 5. Mandatory Pre-Launch Manual Assistive Technology Testing Protocol

Before opening public registration on the production domain (`D-22`), the Festival Accessibility Team must execute and sign off the following manual checks:
1. **Windows NVDA + Firefox/Chrome & JAWS + Chrome:** Complete full signup -> email verification -> `/apply/attendee`, `/apply/volunteer`, and `/apply/sli` (selecting accommodations with and without disability disclosure) -> `/dashboard` review & edit -> `/rgst` 3-day attendance checkbox marking and 15-minute correction modal.
2. **macOS / iOS VoiceOver + Safari & Android TalkBack + Chrome:** Verify mobile menu navigation, Accessibility Toolbar drawer toggles (Contrast, Text Size, Dyslexia Font, Reduced Motion), and 21 Specified Disabilities accordion announcements.
3. **Keyboard-Only & Switch Navigation + 200%/400% Browser Zoom:** Confirm zero keyboard traps, visible focus indicators on every interactive element, and zero clipped controls at `320 CSS px` width (`400%` zoom on `1280px` viewport).
