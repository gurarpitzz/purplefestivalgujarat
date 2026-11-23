<?php
declare(strict_types=1);

if (is_file(dirname(__DIR__) . '/includes/config.php')) {
    require_once dirname(__DIR__) . '/includes/config.php';
}

$pageTitle = 'Accessibility Statement (WCAG 2.2 AA)';
$pageDescription = 'WCAG 2.2 Level AA digital accessibility commitment and compliance statement for Purple Festival Gujarat 2027.';

$a11yFeatures = [
    'Persistent Accessibility Preferences Panel: Text scaling from 90% to 200%, Default / High Contrast / Yellow-on-Black modes, increased line & letter spacing, link highlighting, reading guide band, and reduced-motion toggle.',
    'Keyboard-First Navigation: Full Tab/Shift+Tab/Escape support, visible high-contrast focus rings, skip-to-main-content link, and minimum 44×44px interactive touch targets.',
    'Motion Safety: Automatic respect for OS prefers-reduced-motion settings plus an immediate keyboard-focusable Skip Intro button on the introductory preloader.',
    'Screen Reader Semantics: Logical heading hierarchy, ARIA landmarks, live status announcements, descriptive alternative text for photography and logos, and properly associated form labels and error summaries.',
    'Accessible Documents: Downloadable calendar (.ics) files and clean, structured digital QR pass PDFs.',
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<main id="main-content" tabindex="-1" class="min-h-screen bg-[#F6F1F8] focus:outline-none">
  <div class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
    <a
      href="/"
      class="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-[#59436D] hover:underline"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
        <path d="m12 19-7-7 7-7" />
        <path d="M19 12H5" />
      </svg>
      <span>Back to Home</span>
    </a>

    <article class="surface-card rounded-3xl border border-[#59436D]/20 bg-white p-8 shadow-xs sm:p-12">
      <div class="mb-8 border-b border-[#59436D]/15 pb-6">
        <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] uppercase text-[#59436D]">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
            <circle cx="16" cy="4" r="1" />
            <path d="m18 19 1-7-6 1" />
            <path d="m5 8 3-3 5.5 3-2.36 3.5" />
            <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
            <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
          </svg>
          WCAG 2.2 Level AA Commitment
        </p>
        <h1 class="font-display mt-3 text-3xl font-bold text-[#211B29] sm:text-4xl">
          Digital Accessibility Statement
        </h1>
        <p class="mt-2 text-sm text-[#4B4257]">
          Purple Festival Gujarat 2027 Official Digital Platform
        </p>
      </div>

      <div class="space-y-8 text-sm leading-relaxed text-[#211B29]">
        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            1. Conformance Target
          </h2>
          <p class="text-[#4B4257]">
            Purple Festival Gujarat 2027 is committed to ensuring digital accessibility for
            people of all abilities. This website and registration ecosystem are designed and
            tested to conform to the
            <strong>Web Content Accessibility Guidelines (WCAG) 2.2 Level AA</strong> and align
            with the Rights of Persons with Disabilities (RPwD) Act, 2016 and Guidelines for
            Indian Government Websites (GIGW).
          </p>
        </section>

        <section class="space-y-3">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            2. Built-In Accessibility Features
          </h2>
          <ul class="space-y-2 text-[#4B4257]">
            <?php foreach ($a11yFeatures as $item): ?>
              <li class="flex items-start gap-2.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 h-4 w-4 shrink-0 text-[#19734B]" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <path d="m9 12 2 2 4-4" />
                </svg>
                <span><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            3. Feedback &amp; Reporting Barriers
          </h2>
          <p class="text-[#4B4257]">
            We continuously audit and improve our platform. If you encounter any accessibility
            barrier on this site or require information in an alternative format (such as
            Braille, large print, or accessible PDF), please contact us immediately:
          </p>
          <div class="mt-3 rounded-2xl bg-[#F6F1F8] p-4 text-[#211B29]">
            <p>
              <strong>Email:</strong>
              <a
                href="mailto:purplefestivalvadodara@gmail.com"
                class="font-semibold text-[#59436D] underline"
              >
                purplefestivalvadodara@gmail.com
              </a>
            </p>
            <p class="mt-1">
              <strong>Phone:</strong> Radha Tandon (+91 99743 83920) &middot; Satish Kapoor (+91 92117 60202)
            </p>
          </div>
        </section>
      </div>
    </article>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
