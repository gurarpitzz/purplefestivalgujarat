# Website Reverse-Engineering & Preloader Technical Audit
**Reference Site:** `https://purplefestivalvadodara-main.ajeetgraphics.com/` (and `/apply/`, subpages)  
**Target Rebrand:** **Purple Festival Gujarat 2027** (`PURPLE FESTIVAL GUJARAT 2027`)  
**Tech Stack of Reference Site:** Next.js (App Router), GSAP 3 + ScrollTrigger + CustomEase, Lenis Smooth Scroll, Tailwind CSS + CSS Modules, with custom runtime DOM-injection IIFEs appended to `app/layout.js` on top of a cloned **Battalion™** real-estate financing template.

---

## 1. Introductory Preloader — Exact Reverse-Engineered Specification

### 1.1 DOM Structure & Exact Markup
The preloader is rendered in `app/layout.js` (component `q`, CSS module `5093`) as a full-screen fixed overlay (`z-[1001]`):

```html
<div
  aria-hidden="true"
  inert=""
  class="fixed text-blue-900 left-0 top-0 w-full h-full z-[1001] flex items-center justify-center overflow-hidden styles_preloader__JmvRd"
>
  <!-- Curtain Backdrop -->
  <span class="fixed left-0 top-0 w-full h-screen bg-purple-100 -z-1"></span>

  <!-- Animated Logo Container (initially opacity-0, removed on mount) -->
  <div class="w-full relative opacity-0 flex items-center justify-center">
    <div class="styles_logo__4NIgm">
      <svg
        class="styles_logoSvg__TF4KR"
        width="900"
        height="320"
        viewBox="0 0 900 320"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        style="width: min(88vw, 860px); height: auto; overflow: visible"
        aria-hidden="true"
        focusable="false"
      >
        <!-- 1. Left horizontal lead-in line -->
        <path
          data-draw="left-line"
          d="M -280 156 L 312 156 C 338 156 350 110 360.15 118.4"
          fill="none"
          stroke="#202D59"
          stroke-width="6.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />

        <!-- 2. Core Logo Group (measured for header-docking scale/translate) -->
        <g data-logo-core="true">
          <!-- Continuous 3-figure calligraphic emblem ribbon -->
          <path
            data-draw="emblem-ribbon"
            d="M 360.15 118.4 C 350.74 157.88 368.27 174.29 374.54 171.88 C 380.81 169.47 369.56 135.42 374.54 133.58 C 379.58 131.78 395.88 170.65 402.09 168.24 C 408.36 165.78 399.63 115.38 366.42 95.33 C 333.21 75.9 318.2 97.12 326.94 109.89 C 336.96 123.27 363.28 107.48 377.06 83.18 C 397.72 46.72 435.3 46.72 460.33 84.97 C 477.24 111.12 470.97 171.88 462.24 174.29 C 453.44 176.75 445.94 127.53 439.67 127.53 C 433.4 127.53 425.89 176.75 418.39 174.29 C 410.88 171.88 408.36 111.12 429.03 84.97 C 454.06 52.77 497.91 54.62 524.84 83.18 C 539.85 100.2 564.94 117.17 573.68 105.07 C 581.18 94.1 564.94 77.07 535.48 95.33 C 506.08 113.53 493.54 163.98 499.81 168.24 C 506.08 171.88 522.32 131.78 527.36 133.58 C 532.35 135.42 521.09 169.47 527.36 171.88 C 533.64 174.29 548.03 157.88 539.85 118.4"
            fill="none"
            stroke="#202D59"
            stroke-width="6.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <!-- 3 Hearts above the 3 figures -->
          <path data-heart="1" d="M 356.96 43.64 C 349.4 34.96 335.12 36.92 336.24 48.12 C 337.36 58.2 353.04 65.2 364.8 68.84 C 369.28 58.2 371.52 41.96 362.56 36.08 C 355.84 31.6 354.72 40.28 356.96 43.64 Z" fill="#202D59" />
          <path data-heart="2" d="M 447.68 26.84 C 442.08 14.52 428.08 14.52 428.08 26.84 C 428.08 38.04 442.08 45.88 447.68 52.88 C 453.28 45.88 467.28 38.04 467.28 26.84 C 467.28 14.52 453.28 14.52 447.68 26.84 Z" fill="#202D59" />
          <path data-heart="3" d="M 536.72 43.64 C 540.08 40.28 538.96 31.6 532.24 36.08 C 523.28 41.96 525.52 58.2 530 68.84 C 541.76 65.2 557.44 58.2 558.56 48.12 C 559.68 36.92 545.4 34.96 536.72 43.64 Z" fill="#202D59" />

          <!-- Vector Wordmark (MUST BE REBRANDED TO GUJARAT 2027) -->
          <g>
            <!-- Original vector path spells "PURPLE FEST" -->
            <path data-draw-text="title" fill="#202D59" fill-rule="evenodd" stroke="#202D59" stroke-width="1.2" d="..." />
            <!-- Original vector path spells "Vadodara" -->
            <path data-draw-text="subtitle" fill="#202D59" fill-rule="evenodd" stroke="#202D59" stroke-width="0.9" d="..." />
          </g>
        </g>

        <!-- 3. Right horizontal exit line -->
        <path
          data-draw="right-line"
          d="M 539.85 118.4 C 550 110 562 156 588 156 L 1180 156"
          fill="none"
          stroke="#202D59"
          stroke-width="6.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>

    <!-- Legacy progress bar from Battalion template (explicitly hidden via CSS) -->
    <div class="styles_progressBar__oY7fE" style="display:none;opacity:0;height:0">
      <span class="styles_progressBarArrow__s71dn"></span>
    </div>
  </div>
</div>
```

### 1.2 Exact CSS Styles & Color Tokens
Extracted verbatim from `css_0fa3ad3e26d5da69.css` and `css_dc7cdf09db0feb00.css`:
- **Backdrop (`bg-purple-100`)**: `rgb(221, 221, 249)` / `#DDDDF9` (soft lavender-periwinkle)
- **Primary Ink / Stroke (`text-blue-900`)**: `rgb(32, 45, 89)` / `#202D59` (deep royal navy)
- **Accent Purple (`text-purple`, `.btn--primary`)**: `rgb(164, 89, 255)` / `#A459FF`
- **Accent Teal (`bg-green`, `.btn--secondary`)**: `rgb(9, 159, 159)` / `#099F9F`
- **Surface Neutral (`bg-cream`)**: `rgb(239, 239, 239)` / `#EFEFEF`
- **Primary Webfont**: `__fontPrimary_3c02a0` (weights 400 & 600, WOFF2, fallback Arial)
- **Layout Overrides**:
  ```css
  .styles_preloader__JmvRd {
    padding-top: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }
  .styles_logo__4NIgm {
    position: relative !important;
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    overflow: visible !important;
  }
  .styles_logoSvg__TF4KR {
    width: min(88vw, 860px) !important;
    height: auto !important;
    overflow: visible !important;
    display: block !important;
  }
  ```

### 1.3 Exact GSAP Animation Timeline (3.53s Total)
On mount, smooth scrolling (Lenis) is paused (`lenis.stop()`), `opacity-0` is removed from the wrapper, and `gsap.set()` initializes:
- `leftPath`: `{ strokeDasharray: leftLen, strokeDashoffset: leftLen, opacity: 1 }` (fallback `680`)
- `ribbonPath`: `{ strokeDasharray: ribbonLen, strokeDashoffset: ribbonLen, opacity: 0 }` (fallback `1480`)
- `rightPath`: `{ strokeDasharray: rightLen, strokeDashoffset: rightLen, opacity: 0 }` (fallback `680`)
- `[heart1, heart2, heart3]`: `{ scale: 0, opacity: 0, transformOrigin: "50% 85%" }`
- `titleEl`: `{ strokeDasharray: 340, strokeDashoffset: 340, fillOpacity: 0, opacity: 0, y: 8 }`
- `subtitleEl`: `{ strokeDasharray: 240, strokeDashoffset: 240, fillOpacity: 0, opacity: 0, y: 8 }`

**Dynamic Header-Docking Math** (flies logo seamlessly into `#header-logo`):
```js
const hEl = document.querySelector("#header-logo, [data-header-logo]");
const yRect = hEl ? hEl.getBoundingClientRect() : { left: 40, top: 20, width: 186, height: 31 };
const svgRect = svgRef.current.getBoundingClientRect();
const coreRect = coreRef.current ? coreRef.current.getBoundingClientRect() : svgRect;

const scale = Math.min(
  yRect.width / (coreRect.width || 300),
  (yRect.height * 1.65) / (coreRect.height || 240)
);
const offsetX = (coreRect.left + coreRect.width / 2) - svgRect.left;
const offsetY = (coreRect.top + coreRect.height / 2) - svgRect.top;
const tx = (yRect.left + yRect.width / 2) - (svgRect.left + offsetX * scale);
const ty = (yRect.top + yRect.height / 2) - (svgRect.top + offsetY * scale);
```

**Exact Choreography Table (`window.__preloaderTimeline`):**

| Start Time | Duration | End Time | Target | Properties Animated | Easing | Purpose |
|---:|---:|---:|---|---|---|---|
| `0.00s` | `0.55s` | `0.55s` | `[data-draw="left-line"]` | `strokeDashoffset: 0` | `power2.in` | Horizontal ink line accelerates in from left edge into emblem |
| `0.55s` | `0.00s` | `0.55s` | `[data-draw="emblem-ribbon"]` | `opacity: 1` (`.set`) | — | Reveals ribbon path at exact handoff point `(360.15, 118.4)` |
| `0.55s` | `1.30s` | `1.85s` | `[data-draw="emblem-ribbon"]` | `strokeDashoffset: 0` | `none` | Continuous, uniform-speed calligraphic draw of 3 inclusive figures |
| `0.85s` | `0.32s` | `1.17s` | `[data-heart="1"]` | `scale: 1, opacity: 1` | `back.out(2.2)` | Left heart pops in with spring overshoot as figure 1 forms |
| `1.25s` | `0.32s` | `1.57s` | `[data-heart="2"]` | `scale: 1, opacity: 1` | `back.out(2.2)` | Center heart pops in as figure 2 forms |
| `1.65s` | `0.32s` | `1.97s` | `[data-heart="3"]` | `scale: 1, opacity: 1` | `back.out(2.2)` | Right heart pops in as figure 3 forms |
| `1.35s` | `0.55s` | `1.90s` | `[data-draw-text="title"]` | `opacity: 1, y: 0, strokeDashoffset: 0` | `power2.out` | Title outline draws & floats up (`8px → 0`) |
| `1.58s` | `0.35s` | `1.93s` | `[data-draw-text="title"]` | `fillOpacity: 1, strokeWidth: 0` | `power2.out` | Title fills solid while stroke dissolves |
| `1.85s` | `0.00s` | `1.85s` | `[data-draw="right-line"]` | `opacity: 1` (`.set`) | — | Reveals right exit line at `(539.85, 118.4)` |
| `1.85s` | `0.55s` | `2.40s` | `[data-draw="right-line"]` | `strokeDashoffset: 0` | `power2.out` | Horizontal ink line shoots out to right edge |
| `1.85s` | `0.50s` | `2.35s` | `[data-draw-text="subtitle"]` | `opacity: 1, y: 0, strokeDashoffset: 0` | `power2.out` | Subtitle outline draws & floats up (`8px → 0`) |
| `2.05s` | `0.35s` | `2.40s` | `[data-draw-text="subtitle"]` | `fillOpacity: 1, strokeWidth: 0` | `power2.out` | Subtitle fills solid while stroke dissolves |
| `2.50s` | `0.28s` | `2.78s` | `leftPath` & `rightPath` | `strokeDashoffset: -len, opacity: 0` | `power2.inOut` | Both side lead-in/out lines wipe away cleanly |
| `2.70s` | `0.00s` | `2.70s` | Callback | `setPreloaderFinished(true); lenis.start()` | — | Unlocks scroll & triggers Hero `.stagger-reveal` (`delay: 0.2s`) |
| `2.70s` | `0.82s` | `3.52s` | `svgRef` | `x: tx, y: ty, scale: scale, transformOrigin: "top left"` | `power3.inOut` | Entire SVG flies & shrinks into `#header-logo` slot |
| `3.05s` | `0.48s` | `3.53s` | Backdrop `span` | `yPercent: -100, opacity: 0` | `power3.inOut` | Lavender curtain lifts upward revealing the Hero |
| `3.22s` | `0.00s` | `3.22s` | Callback | `setPreloaderRemoved(true)` | — | Reveals `#header-logo` (`opacity-0` removed) |
| `3.25s` | `0.25s` | `3.50s` | `svgRef` | `opacity: 0` | `power2.out` | Crossfades preloader SVG into `#header-logo` |
| `3.53s+` | `0.10s` | `3.63s` | `onComplete` | `delayedCall(0.1, () => container.remove())` | — | Unmounts preloader node from DOM |

### 1.4 Clean React + GSAP Recreation Blueprint for Gujarat 2027
To recreate this faithfully for **Purple Festival Gujarat 2027** while meeting WCAG 2.1 AA+:
1. **Rebrand the SVG Text**: Keep the exact `left-line`, `emblem-ribbon`, `heart 1–3`, and `right-line` SVG geometry (preserving the iconic continuous line-draw feel), and replace the hardcoded vector paths for `"PURPLE FEST"` + `"Vadodara"` with crisp `<text>` (or newly traced SVG paths) reading:
   - **Title (`data-draw-text="title"`)**: `PURPLE FESTIVAL`
   - **Subtitle (`data-draw-text="subtitle"`)**: `GUJARAT 2027`
   - (Using `<text>` with `stroke-dasharray` / `fill-opacity` animation or GSAP `clip-path` + `y: 8 -> 0` ensures clean rendering and instant localization/maintainability.)
2. **WCAG `prefers-reduced-motion` Support**: Check `window.matchMedia('(prefers-reduced-motion: reduce)').matches` (and the site's "Stop animations" preference) *before* creating the GSAP timeline. If reduced motion is active, skip the 3.5s animation immediately, set `preloaderFinished = true` and `preloaderRemoved = true`, and do not lock scroll.
3. **Keyboard-Accessible "Skip Intro" Button**: Unlike the reference site (which sets `aria-hidden="true" inert=""` on the entire preloader so users cannot skip it), place a visible, high-contrast, keyboard-focusable **"Skip Intro"** button in the top-right/bottom-right corner (`z-[1002]`, outside the `aria-hidden` SVG) that calls `tl.progress(1)` and immediately completes the transition + restores focus to `#main-content`.
4. **Session Persistence (Optional UX Polish)**: Consider playing the full 3.5s preloader on first visit (or on `/` hard load) and respecting `sessionStorage` on internal route returns so returning visitors aren't blocked repeatedly.

---

## 2. Hero, Unwanted 3D Logo Animation & Navigation Audit

### 2.1 Unwanted 3D Purple Festival Logo Animation (DO NOT RECREATE)
The reference site's hero (`HomeIntroSection` in `chunks_317`, `Sequence` player in `chunks_751`) contains an **unwanted 3D logo frame-sequence animation at the bottom of the hero**:
- **How it works:** A `200svh` hero container (`h-[120rem] ... laptop:h-[200svh]`) renders a sticky `<canvas>` (`containerClassName="pt-[35rem] ... laptop:pt-[21rem] sticky top-0"`) fed by Web Workers unpacking `/sequence/hero/desktop/pack_intro.bin` (**127 desktop JPG frames**, `960x520`) or `/sequence/hero/mobile/pack_intro.bin` (**88 mobile JPG frames**, `480x260`).
- **Behavior:** After `preloaderFinished === true`, it plays frames `0 → 46` over `2.0s` (`CustomEase`), then scrubs frames `46 → 127` as the user scrolls (`start: "top top", end: "+=100%", scrub: true`).
- **Secondary Hero-Bottom Block:** Immediately below the `200svh` block is a Battalion template `aspect-[2098/794]` showcase with a teal animated arrow (`span.bg-green.styles_arrow__q0_t0`) scrubbed via `clip-path`.
- **Action for Gujarat 2027:** **Completely omit** the `pack_intro.bin` / `<canvas>` 3D logo frame sequence, all `/sequence/*` Web Worker loaders (`dollar`, `stacked_building`, `key`, `careers`, `contact`, `tier`, `tpo`, `404`, `leaves`, `smile`), and the `styles_arrow__q0_t0` real-estate graphic. Instead, use the **flat authorized Purple Festival logo** (`purple logo.JPG.jpeg` / clean emblem) cleanly in the hero/header.

### 2.2 Hero Composition & Entrance Transition
- **Hero Entrance after Preloader (`2.70s`):** When `scrollReady` / `preloaderFinished` flips to `true`, the hero container fades in (`opacity: 0 → 1`, `duration: 0.9s`, `delay: 0.2s`, `ease: "power3.out"`) and `.stagger-reveal` children (H1, H2, primary CTA button) stagger in (`opacity: 0 → 1`, `duration: 1.1s`, `delay: 0.2s`, `stagger: 0.05s`, `ease: "power3.out"`).
- **Hero Headline Typography:** `.styles_heroTitle__vQ4MT` scales from `50px` (mobile) to `92px`/`10rem` (desktop), `font-weight: 600`, `line-height: 1` (overridden on small screens to `clamp(2.8rem, 8vw, 3.4rem)`).
- **Template Leaks Inside Hero (MUST STRIP):**
  - Subtitle: `"Smart capital for real estate operators."`
  - Lower Hero `<h3>`: `"Battalion™ is the new market leader for investment property financing. Smart, simple, and built for you."`
  - Fixed bottom-left rate ticker (`data-chat-footer="true"`): `"Bridge Financing starting at 8.990%"` / `"Rental Loans starting at 5.990%"`.

### 2.3 Header & Navigation Audit
- **Header Container:** Fixed top bar (`styles_header__udZKm`, `z-[999]`/`1001`, `bg-purple-100` `#DDDDF9`) containing:
  1. Left: `#header-logo` SVG (`186x31` viewBox, `opacity-0` until `preloaderRemoved === true`). Currently has `aria-label="Purple Fest Vadodara 2027 Home"` and spells `"PurpleFest"` — **must be updated to "Purple Festival Gujarat 2027 Home"**.
  2. Center/Right: `<nav aria-label="Primary Navigation">` (rendered via Strapi Flight data; note that SSR HTML had an empty `<ul>` because the Battalion template's client navigation hydrates from Flight data):
     - **Top-level / Dropdown items found in Flight payload:**
       - `Programme & Zones` (`/programs`)
       - `Inclusion & Passes` (dropdown wrapper) → `GIEN & Barrier-Free` (`/composyte`), `Paralympians` (`/tier-1-program`), `21 Disabilities` (`/other-services`), `Pass Sizer` (`/deal-sizer`)
       - `Movement` (dropdown wrapper) → `About & Goa Legacy` (`/about-us`), `Viksit Bharat @ 2047` (`/viksit-bharat`), `Volunteer & Cultural Call` (`/careers`)
       - `Exhibitors & CSR` (`/battalion-tpo`) / `Stall Tiers (A–D)` (`/programs-tpo`)
       - `Register & Contact` (`/contact`)
  3. **Broken/Template Header CTAs (MUST FIX):**
     - `"Make A Payment"` → links to `https://google.com` (`target="_blank"`)!
     - `"Apply Now"` → links to `/careers` instead of `/apply`!
  4. **Accessibility Toggle Button:** Circular `42x42px` button (`.pf-a11y-header-btn`, `#190826` bg, `#4A1575` border) opening `#pf-a11y-header-panel`.
  5. **Mobile Menu Button:** `.menu-opener` (24x24 hamburger/cross SVG toggle).
- **Footer Quick Links (`.pf-boccia-footer`):** Home (`/`), About Us (`/about-us`), Viksit Bharat @ 2047 (`/viksit-bharat`), Programme & 6 Zones (`/programs`), Paralympic Champions (`/tier-1-program`), 21 Disabilities (RPwD) (`/other-services`), Events & Passes (`/events`), Apply / Register (`/apply`), Contact Us (`/contact`), plus Privacy Policy (`/privacy-policy`) and Terms & Conditions (`/terms-and-conditions`).
- **`/apply/` Subpage Structure:** 3-step registration flow:
  - `#step-choice`: Choice between `"Register as a stall"` and `"Register as a delegate"`.
  - `#step-stall`: Exhibition & Pavilion Booking (Stall Identity, Zone Placement, Coordinator Details, Payment min ₹8,500).
  - `#step-delegate`: Delegate Registration (Fee ₹2,200, FAQ, Accommodation info) — note this has its own internal nav bar (`Home`, `About`, `Events`, `Schedule`, `Partners`, `Team`, `Get in Touch`, `Register`).

---

## 3. Partner Logos & Image Asset Inventory

All 11 logo assets hosted on `https://purplefestivalvadodara-main.ajeetgraphics.com` were verified live (**HTTP 200**):

| # | Hosted URL (relative to base) | Format & Size | Exact `alt` Text on Reference Site | Role / Tier on Site | Local Workspace Match (`d:\purplefestgujarat\`) |
|---:|---|---|---|---|---|
| 1 | `/uploads/pf_logo_emblem.png` | `image/png` (230,017 B) | `Purple Fest Vadodara` | Footer & `/apply/` Brand Emblem | `purple logo.JPG.jpeg` (53,978 B — flat authorized emblem) |
| 2 | `/uploads/pf_logo_praangan.jpg` | `image/jpeg` (21,606 B) | `Praangan, Vadodara, Gujarat — A center for children with special needs logo` | Tier 1: Organiser | `PARANGAN LOGO.jpg.jpeg` (21,606 B — exact byte match) |
| 3 | `/uploads/pf_logo_brotherhood.jpg` | `image/jpeg` (75,533 B) | `Brotherhood, New Delhi, India logo` | Tier 1: Organiser | `BH LOGO.jpg.jpeg` (75,533 B — exact byte match) |
| 4 | `/uploads/pf_logo_tiss_cdsa.jpg` | `image/jpeg` (76,644 B) | `Centre for Disabilities Studies and Action (CDSA), Tata Institute of Social Sciences (TISS), Mumbai logo` | Tier 1: Organiser | `LOGO 2 (1).jpg.jpeg` (76,644 B — exact byte match) |
| 5 | `/uploads/pf_logo_goa_commission.png` | `image/png` (53,978 B) | `State Commission for Persons with Disabilities, Goa logo` | Tier 1: Knowledge Partner | `GOA_BH_LOGO-removebg-preview.png` (85,065 B) |
| 6 | `/uploads/pf_logo_gien.jpg` | `image/jpeg` (292,870 B) | `Global Inclusive Education Network (GIEN) logo` | Tier 1: Global Network Partner | `GIEN FINAL LOGO.jpg.jpeg` (1,159,824 B) |
| 7 | `/uploads/pf_logo_msu_vadodara.svg` | `image/svg+xml` (4,204 B) | `The Maharaja Sayajirao University of Baroda, Vadodara institutional crest` | Tier 2: Venue Partner | None locally (custom SVG crest on reference site) |
| 8 | `/uploads/pf_logo_gmers_gotri.svg` | `image/svg+xml` (3,076 B) | `GMERS Medical College and Hospital, Gotri, Vadodara institutional crest` | Tier 2: Venue Partner | None locally (custom SVG crest on reference site) |
| 9 | `/uploads/pf_logo_gnlu.svg` | `image/svg+xml` (3,198 B) | `Gujarat National Law University (GNLU), Gandhinagar institutional crest` | Tier 2: Strategic & Advisory Partner | None locally (custom SVG crest on reference site) |
| 10 | `/uploads/pf_logo_icmr.svg` | `image/svg+xml` (2,974 B) | `Indian Council of Medical Research (ICMR), New Delhi emblem` | Tier 2: Strategic Research Advisor | None locally (custom SVG crest on reference site) |
| 11 | `/uploads/pf_logo_global_bihari.svg` | `image/svg+xml` (2,942 B) | `Global Bihari, New Delhi Strategic Media Partner emblem` | Tier 2: Strategic Media Partner | None locally (custom SVG crest on reference site) |

> **Important Verification Note:** Cross-check Tier 2 Venue/Strategic Partners (MSU Vadodara, GMERS Gotri, GNLU, ICMR, Global Bihari) against the official **Purple Festival Gujarat 2027 Brochure** (audited by `doc-auditor`) before displaying them, and ensure only authorized partners/venues for the Gujarat 2027 edition are published.

---

## 4. Branding Replacement & Unrelated Template Content to Exclude

### 4.1 Branding Occurrences to Replace
Across `index.html` and `apply.html`, the reference site contains heavy Vadodara / PurpleFest branding that must be systematically replaced with **"Purple Festival Gujarat"** / **"PURPLE FESTIVAL GUJARAT 2027"**:

| String / Pattern | `index.html` | `apply.html` | Locations |
|---|---:|---:|---|
| `Purple Fest Vadodara 2027` | 34 | 21 | `<title>`, OG/Twitter meta tags, Hero/CTA headings, FAQ, Pass cards, Committee cards |
| `Purple Fest Vadodara` | 42 | 26 | Header `aria-label`, Footer title & copyright, logo `alt` text, Preloader SVG (`PURPLE FEST` / `Vadodara`) |
| `PurpleFest` | 13 | 15 | Header SVG wordmark, `/apply/` title, internal labels |
| `purplefestivalvadodara` (domain/email) | 12 | 11 | `purplefestivalvadodara.com`, `purplefestivalvadodara@gmail.com` (verify official Gujarat 2027 contact email/domain!) |
| `Vadodara` | 114 | 69 | Keep only where referring to actual Vadodara addresses/venues (e.g. Praangan Secretariat in Alkapuri, Vadodara) if confirmed by official docs; replace festival title references with **Gujarat** |

### 4.2 Unrelated Battalion™ / Real-Estate Financing Template Sections to STRIP OUT
The homepage (`index.html`) has **10 `<section>` elements**, of which **6 are leftover Battalion™ real-estate lending template sections** that must be **100% excluded**:

| Section # | DOM Selector / ID | Leftover Template Content to Exclude | Verdict |
|---:|---|---|---|
| 1 (partial) | `.home-hero-section` | Subtitle `"Smart capital for real estate operators."`, `<h3>` `"Battalion™ is the new market leader for investment property financing..."`, fixed bottom ticker `"Bridge Financing starting at 8.990%"` / `"Rental Loans starting at 5.990%"`, `pack_intro.bin` 3D canvas sequence, `styles_arrow__q0_t0` block | **STRIP ALL TEMPLATE PARTS** |
| 3 | `section#why-battalion.home-benefits-section` | `"Benefits of Battalion™"`, `"Capital, simplified"`, `"Battle-tested expertise"`, `"Built for investors"`, `"Certainty you can count on"` | **DELETE COMPLETELY** |
| 4 | `.home-metrics-section` | `"Battalion™ gives you the edge"`, `"18 proprietary loan products"`, `"10-day average close"`, `"99% client success rate"`, `"$5 billion funded"`, `/sequence/dollar/` canvas | **DELETE COMPLETELY** |
| 5 | `section#programs.home-programs-section` | `"R30™ Prime"` (`30-year loans from 5.990%`), `"PrimeBridge™"` (`Rates from 8.990%`), `"InvestorLOC™"` (`Loans up to $1M`) | **DELETE COMPLETELY** |
| 6 | `section#refreshingly-certain-from-start-to-finish` | `"Refreshingly certain, from start to finish"` (4-step mortgage pre-approval flow: Get started, Tailored options, Prep for success, Ongoing support) | **DELETE COMPLETELY** |
| 7 | `.py-100.laptop:py-120.bg-cream` | `"Results that investors trust"`, `"I work with Battalion for the right guidance to scale my business and navigate any real estate market"` | **DELETE COMPLETELY** |
| 10 (partial) | `.cta-section` | Body copy: `"Apply now or give us a call to see how Battalion can help you fund your next investment."` | **REWRITE COPY** |
| Header | `header .btn--outline-dark` / `.btn--primary` | `"Make A Payment"` (`href="https://google.com"`), `"Apply Now"` (`href="/careers"`) | **REPLACE WITH REAL FESTIVAL CTAs** |
| Routes | `/battalion-tpo`, `/programs-tpo`, `/contact-tpo`, `/deal-sizer`, `/composyte`, `/tier-1-program` | Leftover Battalion route slugs repurposed or unused | **USE CLEAN SEMANTIC GUJARAT 2027 ROUTES** |

---

## 5. Existing Accessibility Controls & WCAG 2.1 AA+ Gaps

### 5.1 What the Reference Site Implements (`window.__pfWcagInit`)
Injected via `chunks_app_layout.js` and persisted in `localStorage` (`pf_a11y_prefs_v2`):
1. **Skip Link**: `<a href="#main-content" class="skip-to-content">Skip to main content</a>` targeting `<main id="main-content" tabindex="-1">`.
2. **Accessibility Menu Panel (`#pf-a11y-header-panel`)**: Triggered by circular header icon button (`.pf-a11y-header-btn`) and footer link (`.pf-a11y-footer-link`):
   - **Text Size (`A-` / `A+`)**: 5 steps (`85%`, `100%`, `115%`, `130%`, `150%`), updates `html.style.fontSize` and `--base-font-size`.
   - **Contrast Modes (radio group)**: `Light` (default), `Dark` (`#12061C` bg / `#F5EEFC` text), `Yellow on black` (`#000000` bg / `#FFFF00` text / `#00FFFF` links).
   - **Toggles (checkboxes)**:
     - `Highlight links and buttons` (`html.pf-a11y-links`)
     - `Wider text spacing` (`html.pf-a11y-spacing`)
     - `Stop animations` (`html.pf-a11y-reduce-motion` — forces `window.__preloaderTimeline.progress(1)` and pauses `<video>`)
     - `TalkBack Screen Reader Audio` (`html.pf-talkback-mode` — uses `window.speechSynthesis` on `focusin` + polite `aria-live` region `#pf-talkback-live`)
   - **Reset All Settings** button.
3. **GSAP SplitText Screen-Reader Normalization**: Consolidates split `.line`/`.word`/`.char` fragments into an `.sr-only` span and marks visual fragments `aria-hidden="true"`.
4. **Escape Key Handler**: Closes open mobile menu or accessibility panel and returns focus to `.pf-a11y-header-btn`.

### 5.2 Critical Accessibility Gaps to Fix in Gujarat 2027 Build
1. **No Automatic OS `prefers-reduced-motion` Support on Preloader**: The preloader ignores `@media (prefers-reduced-motion: reduce)` unless the user has *already* manually enabled "Stop animations" in localStorage. **Fix:** Respect `prefers-reduced-motion: reduce` automatically on initial load.
2. **No "Skip Intro" Control on Preloader**: The 3.5s preloader locks scroll (`lenis.stop()`) and is marked `inert`, giving keyboard/screen-reader/low-patience users zero way to bypass it. **Fix:** Add a keyboard-focusable **Skip Intro** button.
3. **Focus Trap & Modal Semantics Missing in A11y Panel**: `#pf-a11y-header-panel` uses `role="dialog"` without `aria-modal` or keyboard focus-trapping, and clicking outside closes it without returning focus to the trigger. **Fix:** Implement proper focus management (Tab cycling or clean popover semantics + focus return on close).
4. **Destructive `<track>` Removal on Videos**: Line 419 of `__pfWcagInit` runs `v.querySelectorAll("track").forEach(t => t.remove())`, which actively **deletes closed captions/subtitles**! **Fix:** Never strip `<track>` elements; support captions and Indian Sign Language (ISL) video tracks.
5. **Broken Navigation & Duplicate Headings**:
   - SSR `<nav>` has an empty `<ul>` before hydration.
   - Hero renders duplicate text in mobile (`aria-hidden="true"`) and desktop (`h1`/`h2`) blocks, and `styles_fallback__a_QZN` duplicates `<h3>` text in the DOM.
   - Header CTA `"Make A Payment"` points to `https://google.com` with no external-link warning.
6. **Color Contrast & Touch Targets**:
   - Ensure all buttons and interactive controls meet **44×44px** minimum touch target size (reference `.pf-a11y-header-btn` is `42×42px`, slightly under WCAG 2.1 AAA / Apple HIG 44px).
   - Verify WCAG AA contrast (`≥ 4.5:1` normal text, `≥ 3:1` large text) across royal purple, gold accents, and light/dark/yellow-on-black contrast modes.
7. **200% Text Zoom (WCAG 1.4.4)**: Reference panel caps text scaling at `150%`; extend to `200%` without horizontal overflow.

