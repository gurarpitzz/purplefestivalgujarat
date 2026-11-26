<?php
declare(strict_types=1);

if (is_file(dirname(__DIR__) . '/includes/config.php')) {
    require_once dirname(__DIR__) . '/includes/config.php';
}

$pageTitle = 'Privacy Policy (DPDP Act Compliant)';
$pageDescription = 'Digital Personal Data Protection (DPDP) Act 2023 compliant privacy notice for Purple Festival Gujarat 2027.';

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
            <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
            <path d="m9 12 2 2 4-4" />
          </svg>
          Digital Personal Data Protection (DPDP) Act, 2023
        </p>
        <h1 class="font-display mt-3 text-3xl font-bold text-[#211B29] sm:text-4xl">
          Privacy Policy &amp; Data Protection Notice
        </h1>
        <p class="mt-2 text-sm text-[#4B4257]">
          Effective for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara, Gujarat)
        </p>
      </div>

      <div class="space-y-8 text-sm leading-relaxed text-[#211B29]">
        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            1. Purpose &amp; Lawful Basis of Processing
          </h2>
          <p class="text-[#4B4257]">
            Purple Festival Gujarat 2027 collects personal data strictly with your explicit,
            informed consent in accordance with India&apos;s Digital Personal Data Protection
            (DPDP) Act, 2023. We process your information solely to manage participant accounts
            and three-category festival registrations (<strong>Attendee</strong>,
            <strong>Volunteer</strong>, and <strong>Sign Language Interpreter (SLI)</strong>),
            review Volunteer and SLI applications, arrange requested accessibility accommodations,
            verify your official Registration ID (<code>PFG-2027-NNNNNN</code>) at manual
            Vadodara Registration Desks, and communicate official festival updates.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            2. Voluntary Disability Disclosure &amp; Sensitive Access Needs
          </h2>
          <p class="text-[#4B4257]">
            Disclosing a disability is <strong>100% optional</strong> and is never required to
            participate in any category. <strong>No medical diagnosis or disability certificate is
            required to request reasonable accommodations</strong> (such as ISL interpretation,
            Braille schedules, wheelchair seating, or sensory sanctuary access). When you
            voluntarily share disability information or accommodation free-text details, that
            information is stored separately, encrypted at rest using authenticated
            <strong>AES-256-GCM</strong> encryption, protected by strict role-based access
            control (RBAC), and visible <strong>only to authorized Accessibility Coordinators and
            Administrators</strong>. Registration Desk (RGST) check-in operators view only minimal
            attendance-verification fields (Registration ID, name, category, and confirmation
            status) and never see your disability disclosures, accommodation notes, or emergency
            contacts.
          </p>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            3. Technical Security Safeguards &amp; Zero Third-Party QR Sharing
          </h2>
          <ul class="list-disc space-y-1.5 pl-5 text-[#4B4257]">
            <li>
              <strong>Password Protection:</strong> Account passwords are never stored in
              plaintext and are hashed using salted <strong>bcrypt</strong> (cost factor 12).
            </li>
            <li>
              <strong>Authenticated Encryption at Rest:</strong> Voluntary disability disclosures
              and accommodation free-text details are encrypted at rest with
              <strong>AES-256-GCM</strong> authenticated encryption.
            </li>
            <li>
              <strong>Sensitive-Data Audit Logging:</strong> Administrative reads of protected
              disability or accommodation records generate immutable
              <code>SENSITIVE_DATA_READ</code> entries in our audit trail alongside authentication
              and moderation logs.
            </li>
            <li>
              <strong>Session, CSRF &amp; Account Lockout Controls:</strong> Sessions use HttpOnly,
              SameSite=Lax cookies (with the <code>Secure</code> attribute over HTTPS),
              state-changing actions require session-bound CSRF tokens, and all accounts enforce
              a maximum of five consecutive failed sign-in attempts followed by a three-hour
              automatic account lockout alongside IP rate limiting and strict server-side RBAC.
            </li>
            <li>
              <strong>No Participant QR Passes or Third-Party QR Sharing:</strong> Purple
              Festival Gujarat 2027 uses official Registration IDs with manual Registration Desk
              verification rather than participant event-entry QR passes. We do
              <strong>not</strong> transmit participant data to <code>api.qrserver.com</code> or
              any external third-party QR generation service.
            </li>
          </ul>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            4. Your Rights Under the DPDP Act
          </h2>
          <ul class="list-disc space-y-1.5 pl-5 text-[#4B4257]">
            <li>
              <strong>Right to Access:</strong> View your registration details, consent
              records, and accommodation requests anytime via your Participant Dashboard.
            </li>
            <li>
              <strong>Right to Correction &amp; Updating:</strong> Update your profile or
              accessibility needs before the festival.
            </li>
            <li>
              <strong>Right to Erasure &amp; Consent Withdrawal:</strong> Request deletion of
              your personal data or withdraw consent at any time, subject to legal retention
              obligations.
            </li>
            <li>
              <strong>Right to Grievance Redressal:</strong> Contact our Secretariat for prompt
              resolution of any privacy concern.
            </li>
          </ul>
        </section>

        <section class="space-y-2">
          <h2 class="font-display text-xl font-bold text-[#211B29]">
            5. Data Retention &amp; Contact
          </h2>
          <p class="text-[#4B4257]">
            Personal data is retained only as long as necessary to conduct Purple Festival
            Gujarat 2027, fulfill statutory or post-event certification obligations, and meet
            applicable legal requirements. The exact post-festival retention and erasure window is
            governed by the organizer&apos;s DPDP Data Retention Policy (finalized prior to
            festival close under organizer governance). We never sell or rent participant data to
            third parties. To exercise your privacy rights or request erasure, email
            <a
              href="mailto:purplefestivalvadodara@gmail.com"
              class="font-semibold text-[#59436D] underline"
            >
              purplefestivalvadodara@gmail.com
            </a>.
          </p>
        </section>
      </div>
    </article>
  </div>
</main>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
