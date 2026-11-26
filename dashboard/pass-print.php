<?php
declare(strict_types=1);

/**
 * /dashboard/pass-print -> dashboard/pass-print.php
 * RETIRED ROUTE (HTTP 410 Gone) — Decisions D-01 & CAD-17.
 */

require_once dirname(__DIR__) . '/includes/config.php';

http_response_code(410);
$pageTitle = 'Pass Print Discontinued';
$pageDescription = 'Printable QR passes and participant dashboards are discontinued for Purple Festival Gujarat 2027.';
$extraHeadHtml = '<meta name="robots" content="noindex, nofollow" />';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[680px] px-5 py-16 sm:px-8">
    <section aria-labelledby="retired-pass-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-7 sm:p-10 shadow-xs">
      <h1 id="retired-pass-heading" class="text-2xl font-bold text-[#202D59]">No Printable QR Pass Is Required</h1>
      <p class="mt-3 text-sm leading-relaxed text-[#46537C]">
        Purple Festival Gujarat 2027 uses official Registration IDs (<code>PFG-2027-NNNNNN</code>) verified manually at the Vadodara Registration Desk. Printable QR passes and the Participant Dashboard have been discontinued.
      </p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="/apply" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-6 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]">Go to Registration</a>
        <a href="/" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">Return to Homepage</a>
      </div>
    </section>
  </div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
