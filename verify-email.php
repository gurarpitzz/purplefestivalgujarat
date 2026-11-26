<?php
declare(strict_types=1);

/**
 * /verify-email -> verify-email.php
 * RETIRED ROUTE (HTTP 410 Gone) — CAD-17 Accountless Public Registration.
 */

require_once __DIR__ . '/includes/config.php';

http_response_code(410);
$pageTitle = 'Email Verification No Longer Required';
$pageDescription = 'Participant account email verification is discontinued for Purple Festival Gujarat 2027.';
$extraHeadHtml = '<meta name="robots" content="noindex, nofollow" />';

require_once __DIR__ . '/includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[680px] px-5 py-16 sm:px-8">
    <section aria-labelledby="retired-verify-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-7 sm:p-10 shadow-xs">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        No Account Required
      </span>
      <h1 id="retired-verify-heading" class="mt-3 text-2xl font-bold text-[#202D59]">
        Participant Email Verification Is No Longer Needed
      </h1>
      <p class="mt-3 text-sm leading-relaxed text-[#46537C]">
        Purple Festival Gujarat 2027 no longer requires participant website accounts or account activation links. Simply choose your category and submit your registration directly — your official Registration ID and updates will be emailed to you.
      </p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="/apply" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-6 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]">
          Register Now
        </a>
        <a href="/" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">
          Return to Homepage
        </a>
      </div>
    </section>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
