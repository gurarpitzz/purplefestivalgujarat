<?php
declare(strict_types=1);

if (is_file(dirname(__DIR__) . '/includes/config.php')) {
    require_once dirname(__DIR__) . '/includes/config.php';
}

$pageTitle = 'Terms & Code of Conduct';
$pageDescription = 'Terms of participation, safeguarding commitment, and code of conduct for Purple Festival Gujarat 2027.';

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
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
            <path d="M10 9H8" />
            <path d="M16 13H8" />
            <path d="M16 17H8" />
          </svg>
          Participation Terms &amp; Safeguarding
        </p>
        <h1 class="font-display mt-3 text-3xl font-bold text-[#211B29] sm:text-4xl">
          Terms of Participation &amp; Code of Conduct
        </h1>
        <p class="mt-2 text-sm text-[#4B4257]">
          Purple Festival Gujarat 2027 &middot; 21–23 January 2027 &middot; Vadodara, Gujarat
        </p>
      </div>

      <div class="space-y-8 text-sm leading-relaxed text-[#211B29]">
        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            1. Inclusive &amp; Dignity-First Code of Conduct
          </h2>
          <p class="text-[#4B4257]">
            Purple Festival Gujarat 2027 maintains a
            <strong>zero-tolerance policy against harassment, discrimination, ableism, or intimidation</strong>
            of any kind. All attendees, delegates, volunteers, Indian Sign Language interpreters,
            speakers, exhibitors, and staff agree to uphold respectful, person-first communication
            and honor accessibility spaces (such as wheelchair lanes, ISL sightlines, and sensory
            sanctuaries).
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            2. Registration Categories, Application Review &amp; No Guaranteed Benefits
          </h2>
          <p class="text-[#4B4257]">
            Registration for Purple Festival Gujarat 2027 is offered across three primary
            categories: <strong>Attendee</strong>, <strong>Volunteer</strong>, and
            <strong>Sign Language Interpreter (SLI)</strong>. Attendee registrations are
            confirmed upon submission (subject to festival edition capacity). Volunteer and
            SLI applications undergo verification and coordinator review prior to approval and
            duty assignment. Submitting an application does not guarantee approval for
            operational roles, and registration or approval does
            <strong>not</strong> guarantee travel reimbursement, lodging, outstation
            accommodation, or honorarium unless separately confirmed in writing by the Festival
            Secretariat.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            3. Registration ID &amp; Venue Check-in Policy
          </h2>
          <p class="text-[#4B4257]">
            Each registration is assigned a personal, non-transferable Registration ID (for
            example, <code>PFG-2027-NNNNNN</code>). Attendance check-in is verified manually at
            official Vadodara Registration Desks using your Registration ID or registered
            participant details — no event-entry QR pass is required. Organizers reserve the right
            to cancel or revoke registrations if misuse, impersonation, or violation of this Code
            of Conduct is detected.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            4. Programme &amp; Venue Updates
          </h2>
          <p class="text-[#4B4257]">
            Some participating venues and session allocations may be marked as
            <em>Proposed / Subject to Confirmation</em> while accessibility audits and scheduling
            are finalized. Festival organizers may update session timings, speakers, or venue
            allocations to maintain participant safety and accessibility standards. Official
            updates are published on this website and communicated to registered participants.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            5. Contact the Secretariat
          </h2>
          <p class="text-[#4B4257]">
            For questions regarding these terms or to report a safeguarding concern, contact
            <a
              href="mailto:purplefestivalvadodara@gmail.com"
              class="font-semibold text-[#59436D] underline"
            >
              purplefestivalvadodara@gmail.com
            </a>
            or call Radha Tandon (+91 99743 83920) / Satish Kapoor (+91 92117 60202).
          </p>
        </section>
      </div>
    </article>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
