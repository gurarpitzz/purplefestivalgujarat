<?php
declare(strict_types=1);

/**
 * /dashboard -> dashboard/index.php
 * RETIRED ROUTE (HTTP 410 Gone) — CAD-17 Accountless Public Registration.
 * Performs ZERO database queries and exposes ZERO historical participant records.
 */

require_once dirname(__DIR__) . '/includes/config.php';

http_response_code(410);
$pageTitle = 'Participant Dashboard Retired';
$pageDescription = 'Participant accounts and dashboards have been discontinued for Purple Festival Gujarat 2027. Register directly without an account.';
$extraHeadHtml = '<meta name="robots" content="noindex, nofollow" />';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[680px] px-5 py-16 sm:px-8">
    <section aria-labelledby="retired-dash-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-7 sm:p-10 shadow-xs">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        Simplified Accountless Registration
      </span>
      <h1 id="retired-dash-heading" class="mt-3 text-2xl font-bold tracking-[-0.02em] text-[#202D59] sm:text-3xl">
        Participant Accounts &amp; Dashboards Are No Longer Required
      </h1>
      <p class="mt-3 text-sm leading-relaxed text-[#46537C]">
        To make Purple Festival Gujarat 2027 simpler and more accessible for everyone, the Participant Dashboard and participant logins have been retired. You can register directly as an <strong>Attendee</strong>, <strong>Sign Language Interpreter (SLI)</strong>, or <strong>Volunteer</strong> without creating an account.
      </p>
      <p class="mt-3 text-sm leading-relaxed text-[#46537C]">
        All official confirmations, application decisions, accessibility updates, and duty assignments are delivered directly to your email address. If you already registered and need a correction or assistance, email
        <a href="mailto:purplefestivalgujarat@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalgujarat@gmail.com</a>
        with your official Registration ID (<code>PFG-2027-NNNNNN</code>).
      </p>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="/apply" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-6 py-3 text-sm font-bold text-white hover:bg-[#8D3BF2]">
          Register Now (No Account Needed)
        </a>
        <a href="/" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">
          Return to Homepage
        </a>
      </div>
    </section>
  </div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
