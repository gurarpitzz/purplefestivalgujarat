<?php
declare(strict_types=1);

if (!function_exists('renderPurpleFestEmblemSvg')) {
    require_once __DIR__ . '/header.php';
}

$footerNavState = $pfgNavState ?? 'GUEST';

$exploreLinks = [
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'About', 'href' => '/#about'],
    ['label' => 'Programme', 'href' => '/programme'],
    ['label' => 'Accessibility', 'href' => '/accessibility'],
    ['label' => 'Partners', 'href' => '/#partners'],
    ['label' => 'Gallery', 'href' => '/#gallery'],
];

if ($footerNavState === 'ADMIN') {
    $exploreLinks[] = ['label' => 'Admin Portal', 'href' => '/admin'];
} elseif ($footerNavState === 'RGST') {
    $exploreLinks[] = ['label' => 'Registration Desk', 'href' => '/rgst'];
} else {
    $exploreLinks[] = ['label' => 'Apply Now', 'href' => '/apply'];
    $exploreLinks[] = ['label' => 'Staff Login', 'href' => '/staff/login'];
}
?>
  <?php if (empty($hideSiteFooter)): ?>
  <footer class="w-full bg-[#17151C] text-[#F9F7FC]" data-surface="dark">
    <!-- PART A — Charcoal Information Section & Finale Heading -->
    <div class="bg-[#17151C] text-[#F9F7FC]" data-surface="dark">
      <div class="mx-auto max-w-[1380px] px-5 pt-12 pb-6 sm:px-8 sm:pt-16">
        <!-- Top 3-Column Editorial Information Grid -->
        <div class="grid gap-10 md:grid-cols-12 lg:gap-12">
          <!-- Left: Festival Identity -->
          <div class="space-y-3.5 md:col-span-4">
            <div class="flex items-center gap-3">
              <?= renderPurpleFestEmblemSvg('h-9 w-auto text-[#E8DFF0]') ?>
              <div>
                <h2 class="text-xl font-extrabold tracking-tight text-[#F9F7FC] sm:text-2xl">
                  PURPLE FESTIVAL GUJARAT 2027
                </h2>
              </div>
            </div>
            <p class="max-w-sm text-sm leading-relaxed text-[#B6A1C8]">
              Celebrating accessibility, inclusion and diversity.
            </p>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 pt-1 text-xs text-[#E8DFF0]/85">
              <span class="inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-[#A459FF]" aria-hidden="true">
                  <path d="M8 2v4" />
                  <path d="M16 2v4" />
                  <rect width="18" height="18" x="3" y="4" rx="2" />
                  <path d="M3 10h18" />
                </svg>
                21–23 January 2027
              </span>
              <span class="inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true">
                  <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                  <circle cx="12" cy="10" r="3" />
                </svg>
                Vadodara, Gujarat
              </span>
            </div>
          </div>

          <!-- Center: Explore Navigation -->
          <nav aria-label="Footer navigation" class="space-y-3 md:col-span-4">
            <p class="text-xs font-semibold tracking-[0.14em] uppercase text-[#B5B5B5]">
              Explore
            </p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3 md:grid-cols-2">
              <?php foreach ($exploreLinks as $item): ?>
                <a
                  href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                  class="inline-flex min-h-[34px] items-center font-medium text-[#F8F7F4] transition-colors hover:text-[#B6A1C8] hover:underline"
                >
                  <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>
            </div>
          </nav>

          <!-- Right: Get in Touch & Policies -->
          <div class="space-y-3 md:col-span-4">
            <p class="text-xs font-semibold tracking-[0.14em] uppercase text-[#B5B5B5]">
              Get in touch
            </p>
            <div class="space-y-2 text-sm">
              <a
                href="mailto:purplefestivalgujarat@gmail.com"
                class="inline-flex min-h-[34px] items-center gap-1.5 font-medium text-[#F8F7F4] transition-colors hover:text-[#B6A1C8] hover:underline"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#B6A1C8]" aria-hidden="true">
                  <rect width="20" height="16" x="2" y="4" rx="2" />
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                </svg>
                <span class="break-all">purplefestivalgujarat@gmail.com</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-[#B5B5B5]" aria-hidden="true">
                  <path d="M7 7h10v10" />
                  <path d="M7 17 17 7" />
                </svg>
              </a>

              <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-[#E8DFF0]/90">
                <a
                  href="tel:+919974383920"
                  class="inline-flex min-h-[32px] items-center gap-1.5 hover:text-white hover:underline"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                  </svg>
                  <span>Radha Tandon: +91 99743 83920</span>
                </a>
                <a
                  href="tel:+919211760202"
                  class="inline-flex min-h-[32px] items-center gap-1.5 hover:text-white hover:underline"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-[#099F9F]" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                  </svg>
                  <span>Satish Kapoor: +91 92117 60202</span>
                </a>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 text-xs text-[#B5B5B5]">
              <a href="/legal/privacy" class="hover:text-white hover:underline">
                Privacy Policy
              </a>
              <span aria-hidden="true">·</span>
              <a href="/legal/terms" class="hover:text-white hover:underline">
                Terms
              </a>
              <span aria-hidden="true">·</span>
              <a href="/legal/accessibility-statement" class="hover:text-white hover:underline">
                Accessibility Statement
              </a>
              <span aria-hidden="true">·</span>
              <a href="/staff/login" class="hover:text-white hover:underline">
                Staff Portal
              </a>
            </div>
          </div>
        </div>

        <!-- Finale Heading Bar right above the Animated Crowd Strip -->
        <div class="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-6">
          <p class="text-[clamp(24px,3vw,40px)] font-medium leading-[1.15] tracking-[-0.03em] text-[#F8F7F4]">
            Everyone belongs here.
          </p>

          <div class="flex flex-wrap items-center gap-3">
            <!-- AnimationControls -->
            <button
              id="pfg-crowd-pause-btn"
              data-crowd-toggle="true"
              type="button"
              aria-pressed="false"
              aria-label="Pause animated footer crowd"
              class="touch-target inline-flex items-center gap-2 rounded-full border border-white/25 bg-[#23202B] px-4 py-2 text-xs font-semibold tracking-wide text-[#F9F7FC] transition-colors hover:border-[#B6A1C8] hover:bg-[#2E2A38] disabled:cursor-default disabled:opacity-60"
            >
              <svg id="pfg-crowd-icon-pause" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 fill-current text-[#B6A1C8]" aria-hidden="true">
                <rect x="14" y="4" width="4" height="16" rx="1" />
                <rect x="6" y="4" width="4" height="16" rx="1" />
              </svg>
              <svg id="pfg-crowd-icon-play" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden h-3.5 w-3.5 fill-current text-[#D5B77B]" aria-hidden="true">
                <polygon points="6 3 20 12 6 21 6 3" />
              </svg>
              <span id="pfg-crowd-pause-label">Pause Animation</span>
            </button>

            <a
              id="pfg-back-to-top"
              href="#main-content"
              class="touch-target inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-xs font-medium text-[#F8F7F4] transition-colors hover:border-white/40 hover:bg-white/5"
            >
              <span>Back to top</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                <path d="m5 12 7-7 7 7" />
                <path d="M12 19V5" />
              </svg>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- PART B — Full-Width White Animated Inclusive Crowd Strip -->
    <section
      aria-label="Inclusive Community Crowd Illustration"
      class="relative w-full bg-white"
    >
      <p id="pfg-crowd-description" class="sr-only">
        An illustrated crowd of diverse people, including people with disabilities, moving
        together around three welcoming festival representatives: a young Indian woman in a
        traditional Gujarati outfit offering a Namaste gesture, a smiling man seated in a
        modern manual wheelchair, and a young man with a prosthetic lower limb.
      </p>

      <button
        id="pfg-crowd-scene"
        data-crowd-toggle="true"
        type="button"
        aria-label="PURPLE FESTIVAL. Pause inclusive crowd animation"
        aria-describedby="pfg-crowd-description"
        aria-pressed="false"
        data-cast-size="60"
        class="group relative block h-[270px] w-full cursor-pointer overflow-hidden border-0 bg-white p-0 text-left text-[#141414] isolation-isolate sm:h-[330px] lg:h-[395px] disabled:cursor-default focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-[#141414]"
      >
        <!-- Large Faint Editorial Background Typography (5–9% opacity equivalence behind crowd) -->
        <span
          aria-hidden="true"
          class="pointer-events-none select-none absolute left-0 top-2 z-0 w-full text-center font-extrabold leading-none tracking-[-0.04em] text-[#EFEFEF] text-[clamp(56px,11.8vw,210px)]"
        >
          EVERYONE BELONGS
        </span>

        <!-- Layer 1: Background & Midground Crowd Canvas (R->L and L->R behind foreground) -->
        <canvas
          id="pfg-crowd-back-canvas"
          aria-hidden="true"
          class="pointer-events-none absolute inset-0 z-[1] h-full w-full"
        ></canvas>

        <!-- Layer 2: Foreground Crowd Canvas (R->L flush with bottom border) -->
        <canvas
          id="pfg-crowd-front-canvas"
          aria-hidden="true"
          class="pointer-events-none absolute inset-0 z-[2] h-full w-full"
        ></canvas>

        <!-- Layer 3 (z-index 10): High-Contrast Purple-Attire Stationary Central Welcoming Trio -->
        <div
          aria-hidden="true"
          class="pointer-events-none select-none absolute bottom-0 left-1/2 z-[10] -translate-x-1/2 flex items-end justify-center"
          style="height: min(368px, calc(100% - 4px));"
        >
          <div
            class="pointer-events-none absolute inset-x-[-28px] bottom-0 top-[-12px] -z-10"
            style="background: radial-gradient(ellipse 54% 62% at 50% 56%, rgba(255,255,255,0.92) 0%, rgba(255,255,255,0.62) 55%, rgba(255,255,255,0) 100%);"
          ></div>
          <img
            id="pfg-welcome-trio-img"
            src="/assets/footer/welcoming-trio.png?v=4"
            alt=""
            width="680"
            height="480"
            loading="lazy"
            decoding="async"
            class="h-full w-auto max-w-none object-contain object-bottom drop-shadow-[0_3px_10px_rgba(166,154,216,0.22)]"
          />
        </div>
      </button>
    </section>

    <!-- PART C — Bottom Charcoal Copyright Strip -->
    <div class="border-t border-[#282530] bg-[#17151C] text-[#B5B5B5]" data-surface="dark">
      <div class="mx-auto flex min-h-[64px] max-w-[1380px] flex-col items-start justify-between gap-3 px-5 py-4 text-xs sm:flex-row sm:items-center sm:px-8">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
          <p class="font-medium text-[#E8DFF0]">
            &copy; 2027 Purple Festival Gujarat. All rights reserved.
          </p>
          <span aria-hidden="true" class="hidden sm:inline text-white/25">
            |
          </span>
          <p class="text-[#B6A1C8]">A celebration where everyone belongs.</p>
        </div>

        <p class="text-[11px] text-[#B5B5B5]">
          Organised with Brotherhood &middot; Global Inclusive Education Network (GIEN) &middot; MSU Baroda
        </p>
      </div>
    </div>
  </footer>
  <?php endif; ?>

  <!-- Reading Guide Band (controlled by accessibility.js) -->
  <div
    id="a11y-reading-guide"
    aria-hidden="true"
    class="reading-guide-bar hidden"
    style="top: 240px;"
  ></div>

  <!-- Screen Reader Live Status Announcements -->
  <div id="a11y-live-status" role="status" aria-live="polite" class="sr-only"></div>

  <!-- Accessibility Preferences Modal Dialog (src/components/AccessibilityPanel.tsx) -->
  <div
    id="a11y-preferences-modal"
    class="fixed inset-0 z-[1100] hidden justify-end bg-black/60 backdrop-blur-xs"
  >
    <div
      id="a11y-preferences-dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="a11y-panel-title"
      aria-describedby="a11y-panel-desc"
      class="relative flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-[#383242] bg-[#17151C] text-[#F9F7FC] shadow-2xl"
      data-surface="dark"
    >
      <!-- Header -->
      <div class="sticky top-0 z-10 flex items-center justify-between border-b border-[#383242] bg-[#17151C] px-6 py-5">
        <div>
          <p class="text-xs font-semibold tracking-[0.2em] uppercase text-[#D5B77B]">
            WCAG 2.2 AA+
          </p>
          <h2 id="a11y-panel-title" class="font-display text-xl font-semibold text-[#F9F7FC]">
            Accessibility Preferences
          </h2>
        </div>
        <button
          id="a11y-panel-close"
          type="button"
          aria-label="Close accessibility preferences"
          class="touch-target inline-flex items-center justify-center rounded-lg border border-[#4D445C] bg-[#25212B] text-[#F9F7FC] transition-colors hover:border-[#D5B77B] hover:text-[#D5B77B]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
          </svg>
        </button>
      </div>

      <p id="a11y-panel-desc" class="px-6 pt-4 text-sm text-[#D7CCE2]">
        Personalize display, contrast, spacing, and motion settings. Your preferences are saved on
        this device.
      </p>

      <div class="flex-1 space-y-6 px-6 py-5">
        <!-- Screen Reader / TalkBack -->
        <section aria-labelledby="a11y-sr-heading" class="space-y-3">
          <h3
            id="a11y-sr-heading"
            class="flex items-center gap-2 text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
              <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
              <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
              <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
            </svg>
            Screen Reader / TalkBack
          </h3>

          <div class="space-y-3.5 rounded-xl border border-[#383242] bg-[#25212B] p-4">
            <div class="flex items-start justify-between gap-4">
              <div class="space-y-1">
                <div class="text-sm font-semibold text-[#F9F7FC]">
                  <span id="a11y-label-screenReader">Screen Reader / TalkBack</span>
                </div>
                <p class="text-xs text-[#D7CCE2]">Speaks focused, clicked, or hovered content and reads the entire page aloud.</p>
              </div>
              <button
                type="button"
                role="switch"
                data-a11y-toggle="screenReader"
                aria-checked="false"
                aria-labelledby="a11y-label-screenReader"
                class="relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-[#4D445C] bg-[#17151C] transition-colors"
              >
                <span data-a11y-knob="true" aria-hidden="true" class="inline-block h-5 w-5 translate-x-1 rounded-full bg-[#F9F7FC] transition-transform"></span>
              </button>
            </div>

            <div class="pfg-drawer-sr-controls">
              <button type="button" data-sr-action="read-page" class="pfg-drawer-sr-btn pfg-drawer-sr-btn--primary">Read Entire Page</button>
              <button type="button" data-sr-action="stop" class="pfg-drawer-sr-btn">Stop</button>
            </div>

            <div class="space-y-1.5">
              <p class="text-xs font-medium text-[#D7CCE2]">Speech Speed</p>
              <div class="pfg-drawer-sr-rates" role="group" aria-label="Speech reading speed">
                <button type="button" data-sr-rate="0.8" aria-pressed="false" class="pfg-drawer-sr-rate">0.8×</button>
                <button type="button" data-sr-rate="1" aria-pressed="true" class="pfg-drawer-sr-rate is-active">1×</button>
                <button type="button" data-sr-rate="1.25" aria-pressed="false" class="pfg-drawer-sr-rate">1.25×</button>
                <button type="button" data-sr-rate="1.5" aria-pressed="false" class="pfg-drawer-sr-rate">1.5×</button>
              </div>
            </div>
          </div>
        </section>

        <!-- Website Language -->
        <section aria-labelledby="a11y-lang-heading" class="space-y-3">
          <h3
            id="a11y-lang-heading"
            class="flex items-center gap-2 text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
              <path d="M2 12h20" />
            </svg>
            Website Language
          </h3>
          <div class="pfg-drawer-lang" role="group" aria-label="Website language" data-no-translate="true">
            <button type="button" data-lang-switch="en" aria-pressed="true" lang="en" class="pfg-drawer-lang__btn is-active">English</button>
            <button type="button" data-lang-switch="hi" aria-pressed="false" lang="hi" class="pfg-drawer-lang__btn">हिन्दी</button>
            <button type="button" data-lang-switch="gu" aria-pressed="false" lang="gu" class="pfg-drawer-lang__btn">ગુજરાતી</button>
          </div>
        </section>

        <!-- Text Size -->
        <section aria-labelledby="a11y-text-size-heading">
          <div class="mb-3 flex items-center justify-between">
            <h3
              id="a11y-text-size-heading"
              class="flex items-center gap-2 text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
                <polyline points="4 7 4 4 20 4 20 7" />
                <line x1="9" x2="15" y1="20" y2="20" />
                <line x1="12" x2="12" y1="4" y2="20" />
              </svg>
              Text Size
            </h3>
            <span
              id="a11y-text-scale-badge"
              class="rounded-full border border-[#383242] bg-[#25212B] px-3 py-1 text-xs font-bold text-[#D5B77B]"
            >
              100%
            </span>
          </div>
          <div class="flex items-center gap-3">
            <button
              id="a11y-scale-decrease"
              type="button"
              aria-label="Decrease text size"
              class="touch-target inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-[#4D445C] bg-[#25212B] px-4 py-2.5 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] disabled:cursor-not-allowed disabled:opacity-40"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M5 12h14" />
              </svg>
              A-
            </button>
            <button
              id="a11y-scale-reset"
              type="button"
              class="touch-target inline-flex items-center justify-center rounded-lg border border-[#4D445C] bg-[#25212B] px-3 py-2.5 text-xs font-semibold text-[#D7CCE2] hover:border-[#D5B77B] hover:text-[#F9F7FC]"
            >
              100%
            </button>
            <button
              id="a11y-scale-increase"
              type="button"
              aria-label="Increase text size"
              class="touch-target inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-[#4D445C] bg-[#25212B] px-4 py-2.5 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] disabled:cursor-not-allowed disabled:opacity-40"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                <path d="M5 12h14" />
                <path d="M12 5v14" />
              </svg>
              A+
            </button>
          </div>
        </section>

        <!-- Contrast Mode -->
        <fieldset>
          <legend class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[#D5B77B]" aria-hidden="true">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 18a6 6 0 0 0 0-12v12z" />
            </svg>
            Color Contrast Mode
          </legend>
          <div class="space-y-2.5" role="radiogroup" aria-label="Color contrast mode">
            <?php
            $contrastOptions = [
                ['value' => 'default', 'label' => 'Default', 'desc' => 'Dusty Purple & Charcoal'],
                ['value' => 'high', 'label' => 'High Contrast', 'desc' => 'Pure Black & Crisp White'],
                ['value' => 'yellow-black', 'label' => 'Yellow on Black', 'desc' => 'Maximum Low-Vision Contrast'],
            ];
            foreach ($contrastOptions as $opt):
            ?>
              <button
                type="button"
                role="radio"
                data-a11y-contrast="<?= htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') ?>"
                aria-checked="<?= $opt['value'] === 'default' ? 'true' : 'false' ?>"
                class="touch-target flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition-colors <?= $opt['value'] === 'default' ? 'border-[#D5B77B] bg-[#2D2538] text-[#F9F7FC]' : 'border-[#383242] bg-[#25212B] text-[#D7CCE2] hover:border-[#9273B5]' ?>"
              >
                <div>
                  <div class="text-sm font-semibold text-[#F9F7FC]"><?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="text-xs text-[#D7CCE2]"><?= htmlspecialchars($opt['desc'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <span
                  data-a11y-contrast-dot="true"
                  aria-hidden="true"
                  class="h-4 w-4 rounded-full border-2 <?= $opt['value'] === 'default' ? 'border-[#D5B77B] bg-[#D5B77B]' : 'border-[#9273B5] bg-transparent' ?>"
                ></span>
              </button>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <!-- Toggles -->
        <section aria-labelledby="a11y-toggles-heading" class="space-y-3">
          <h3
            id="a11y-toggles-heading"
            class="text-sm font-semibold tracking-wide uppercase text-[#E8DFF0]"
          >
            Reading &amp; Motion Support
          </h3>

          <!-- Reduce Motion -->
          <div class="flex items-start justify-between gap-4 rounded-xl border border-[#383242] bg-[#25212B] p-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-sm font-semibold text-[#F9F7FC]">
                <span class="text-[#D5B77B]">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z" />
                  </svg>
                </span>
                <span id="a11y-label-reducedMotion">Reduce Motion &amp; Animations</span>
              </div>
              <p class="text-xs text-[#D7CCE2]">Skips intro preloader and disables page transitions.</p>
            </div>
            <button
              type="button"
              role="switch"
              data-a11y-toggle="reducedMotion"
              aria-checked="false"
              aria-labelledby="a11y-label-reducedMotion"
              class="relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-[#4D445C] bg-[#17151C] transition-colors"
            >
              <span data-a11y-knob="true" aria-hidden="true" class="inline-block h-5 w-5 translate-x-1 rounded-full bg-[#F9F7FC] transition-transform"></span>
            </button>
          </div>

          <!-- Wide Spacing -->
          <div class="flex items-start justify-between gap-4 rounded-xl border border-[#383242] bg-[#25212B] p-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-sm font-semibold text-[#F9F7FC]">
                <span class="text-[#D5B77B]">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M3 12h18" />
                    <path d="M3 18h18" />
                    <path d="M3 6h18" />
                  </svg>
                </span>
                <span id="a11y-label-wideSpacing">Increased Text &amp; Line Spacing</span>
              </div>
              <p class="text-xs text-[#D7CCE2]">Expands line height, letter, and word spacing.</p>
            </div>
            <button
              type="button"
              role="switch"
              data-a11y-toggle="wideSpacing"
              aria-checked="false"
              aria-labelledby="a11y-label-wideSpacing"
              class="relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-[#4D445C] bg-[#17151C] transition-colors"
            >
              <span data-a11y-knob="true" aria-hidden="true" class="inline-block h-5 w-5 translate-x-1 rounded-full bg-[#F9F7FC] transition-transform"></span>
            </button>
          </div>

          <!-- Highlight Links -->
          <div class="flex items-start justify-between gap-4 rounded-xl border border-[#383242] bg-[#25212B] p-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-sm font-semibold text-[#F9F7FC]">
                <span class="text-[#D5B77B]">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M9 17H7A5 5 0 0 1 7 7h2" />
                    <path d="M15 7h2a5 5 0 1 1 0 10h-2" />
                    <line x1="8" x2="16" y1="12" y2="12" />
                  </svg>
                </span>
                <span id="a11y-label-highlightLinks">Highlight Links &amp; Buttons</span>
              </div>
              <p class="text-xs text-[#D7CCE2]">Adds strong underlines and outlines to interactive elements.</p>
            </div>
            <button
              type="button"
              role="switch"
              data-a11y-toggle="highlightLinks"
              aria-checked="false"
              aria-labelledby="a11y-label-highlightLinks"
              class="relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-[#4D445C] bg-[#17151C] transition-colors"
            >
              <span data-a11y-knob="true" aria-hidden="true" class="inline-block h-5 w-5 translate-x-1 rounded-full bg-[#F9F7FC] transition-transform"></span>
            </button>
          </div>

          <!-- Reading Guide -->
          <div class="flex items-start justify-between gap-4 rounded-xl border border-[#383242] bg-[#25212B] p-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-sm font-semibold text-[#F9F7FC]">
                <span class="text-[#D5B77B]">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M12 7v14" />
                    <path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z" />
                  </svg>
                </span>
                <span id="a11y-label-readingGuide">Reading Guide Band</span>
              </div>
              <p class="text-xs text-[#D7CCE2]">Displays a horizontal focus bar following pointer or keyboard.</p>
            </div>
            <button
              type="button"
              role="switch"
              data-a11y-toggle="readingGuide"
              aria-checked="false"
              aria-labelledby="a11y-label-readingGuide"
              class="relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full border border-[#4D445C] bg-[#17151C] transition-colors"
            >
              <span data-a11y-knob="true" aria-hidden="true" class="inline-block h-5 w-5 translate-x-1 rounded-full bg-[#F9F7FC] transition-transform"></span>
            </button>
          </div>
        </section>

        <!-- Quick Links -->
        <div class="rounded-xl border border-[#383242] bg-[#25212B]/70 p-4 text-xs text-[#D7CCE2]">
          <p class="mb-2 font-semibold text-[#F9F7FC]">Need on-site accommodations?</p>
          <div class="flex flex-wrap gap-3">
            <a
              href="/accessibility"
              data-a11y-close-link="true"
              class="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-[#F9F7FC]"
            >
              Festival Accessibility Guide
            </a>
            <a
              href="/legal/accessibility-statement"
              data-a11y-close-link="true"
              class="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-[#F9F7FC]"
            >
              WCAG 2.2 Statement
            </a>
          </div>
        </div>
      </div>

      <!-- Footer Reset -->
      <div class="sticky bottom-0 border-t border-[#383242] bg-[#17151C] px-6 py-4">
        <button
          id="a11y-reset-all"
          type="button"
          class="touch-target flex w-full items-center justify-center gap-2 rounded-xl border border-[#4D445C] bg-[#25212B] px-4 py-3 text-sm font-semibold text-[#F9F7FC] transition-colors hover:border-[#D5B77B] hover:text-[#D5B77B]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
          </svg>
          Reset All Accessibility Settings
        </button>
      </div>
    </div>
  </div>

  <!-- Floating TalkBack Screen Reader Control Bar (visible when TalkBack is active) -->
  <div
    id="pfg-sr-bar"
    role="region"
    aria-label="TalkBack Screen Reader Controls"
    class="pfg-sr-bar hidden"
    data-surface="dark"
  >
    <div class="pfg-sr-bar__status-row">
      <span class="pfg-sr-bar__badge" aria-hidden="true">TalkBack</span>
      <span id="pfg-sr-bar-status" class="pfg-sr-bar__status" aria-live="off">Ready — tap, hover, or Tab any item</span>
    </div>
    <div class="pfg-sr-bar__controls">
      <button type="button" data-sr-action="prev" aria-label="Previous block" class="pfg-sr-bar__btn">Prev</button>
      <button type="button" data-sr-action="toggle-play" aria-label="Read page aloud or pause" class="pfg-sr-bar__btn pfg-sr-bar__btn--primary">
        <span data-sr-play-label="true">Read Page</span>
      </button>
      <button type="button" data-sr-action="next" aria-label="Next block" class="pfg-sr-bar__btn">Next</button>
      <button type="button" data-sr-action="stop" aria-label="Stop reading" class="pfg-sr-bar__btn">Stop</button>
      <button type="button" data-sr-action="cycle-rate" aria-label="Change reading speed" class="pfg-sr-bar__btn">
        <span data-sr-rate-badge="true">1×</span>
      </button>
      <button type="button" data-sr-action="disable" aria-label="Turn off TalkBack" class="pfg-sr-bar__btn pfg-sr-bar__btn--close">Off</button>
    </div>
  </div>

  <!-- Core Scripts -->
  <script src="/assets/js/gsap.min.js"></script>
  <script src="/assets/js/accessibility.js?v=2"></script>
  <script src="/assets/js/screen-reader.js?v=1"></script>
  <script src="/assets/js/i18n.js?v=1"></script>
  <script src="/assets/js/preloader.js"></script>
  <script src="/assets/js/crowd-footer.js"></script>
  <script>
  (function () {
    "use strict";
    var forms = document.querySelectorAll('form[data-pfg-logout-form="true"]');
    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener("submit", function (e) {
        e.preventDefault();
        var form = this;
        var csrfInput = form.querySelector('input[name="csrf_token"]');
        var redirectInput = form.querySelector('input[name="redirect"]');
        var btn = form.querySelector('button[type="submit"]');
        var csrf = csrfInput ? csrfInput.value : "";
        var redirectTo = redirectInput && redirectInput.value ? redirectInput.value : "/";
        if (btn) {
          btn.disabled = true;
          btn.textContent = "Signing out…";
        }
        fetch("/api/auth/logout", {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-Token": csrf
          },
          body: JSON.stringify({ csrf_token: csrf, redirect: redirectTo })
        })
          .then(function (res) {
            return res.json().catch(function () { return {}; });
          })
          .then(function (data) {
            window.location.href = (data && typeof data.redirectTo === "string" && data.redirectTo.charAt(0) === "/")
              ? data.redirectTo
              : redirectTo;
          })
          .catch(function () {
            window.location.href = redirectTo;
          });
      });
    }
  })();
  </script>
  <?php if (!empty($extraFooterHtml)): ?>
    <?= $extraFooterHtml ?>
  <?php endif; ?>
</body>
</html>
