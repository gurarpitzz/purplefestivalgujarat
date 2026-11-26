<?php
declare(strict_types=1);

/**
 * Participant Dashboard (/dashboard -> dashboard/index.php)
 * Phase 10 — Complete Accessible Participant Dashboard (WCAG 2.2 AA).
 *
 * Security & Regression Invariants:
 * - Unauthenticated requests receive HTTP 302 redirect to `/login?next=%2Fdashboard` (T2-14, A-12).
 * - ZERO fatal errors or TypeErrors on any auth state.
 * - Embeds session-bound `var csrfToken = ...` cleanly via `pfgSafeJsonForScript()` (T2-14).
 * - ZERO participant QR entry pass imagery or QR pass dependencies (Decision D-01).
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /login?next=' . rawurlencode('/dashboard'));
    exit;
}

$userFullName = (string)($user['fullName'] ?? $user['full_name'] ?? 'Participant');
$userEmail = (string)($user['email'] ?? '');
$userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : ['PARTICIPANT'];

$isAdmin = in_array('SUPERADMIN', $userRoles, true) || in_array('ADMIN', $userRoles, true);
$isRgst = in_array('RGST', $userRoles, true) || $isAdmin;

$csrfToken = getCsrfToken();

$pageTitle = 'Participant Dashboard | Purple Festival Gujarat 2027';
$pageDescription = 'View your Attendee, Volunteer, and Sign Language Interpreter registrations, duty assignments, accessibility accommodations, notifications, and profile settings for Purple Festival Gujarat 2027.';
$hideSiteHeader = true;
$hideSiteFooter = true;
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-screen editorial-mesh-light">
  <!-- Top Charcoal Header -->
  <header class="bg-[#17151C] text-[#F9F7FC] border-b border-[#383242]" data-surface="dark">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-1">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#D5B77B]">
            <?= htmlspecialchars(FESTIVAL_NAME . ' • ' . FESTIVAL_DATES_DISPLAY, ENT_QUOTES, 'UTF-8') ?>
          </p>
          <h1 id="dash-welcome-heading" class="font-display text-2xl sm:text-3xl font-semibold text-[#F9F7FC]">
            Welcome, <?= htmlspecialchars($userFullName, ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <p id="dash-header-subtitle" class="text-sm text-[#D7CCE2]">
            <?= htmlspecialchars($userEmail . ' • ' . FESTIVAL_LOCATION_DISPLAY, ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <a
            href="/"
            class="min-h-[44px] px-3.5 py-2 rounded-xl border border-[#383242] bg-[#25212B] hover:bg-[#383242] text-[#D7CCE2] hover:text-[#F9F7FC] text-sm font-semibold inline-flex items-center gap-1.5 transition-colors"
          >
            Festival Home
          </a>

          <?php if ($isAdmin): ?>
            <a
              href="/admin"
              class="min-h-[44px] px-4 py-2 rounded-xl bg-[#D5B77B] hover:bg-[#e1c68f] text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
            >
              Admin Portal
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                <path d="M15 3h6v6" />
                <path d="M10 14 21 3" />
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
              </svg>
            </a>
          <?php endif; ?>

          <?php if ($isRgst): ?>
            <a
              href="/rgst"
              class="min-h-[44px] px-4 py-2 rounded-xl border border-[#D5B77B]/60 bg-[#25212B] hover:bg-[#D5B77B] text-[#D5B77B] hover:text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
            >
              Registration Desk
            </a>
          <?php endif; ?>

          <a
            href="/apply"
            class="min-h-[44px] px-4 py-2 rounded-xl bg-[#9273B5] hover:bg-[#A58DBB] text-[#17151C] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
              <circle cx="12" cy="12" r="10" />
              <path d="M8 12h8" />
              <path d="M12 8v8" />
            </svg>
            Apply / Add Role
          </a>

          <button
            type="button"
            data-a11y-trigger="true"
            aria-expanded="false"
            aria-controls="a11y-preferences-modal"
            aria-label="Open Accessibility Preferences"
            title="Accessibility Preferences"
            class="min-h-[44px] min-w-[44px] px-3 py-2 rounded-xl border border-[#4D445C] bg-[#25212B] hover:border-[#D5B77B] text-[#F9F7FC] text-sm font-semibold inline-flex items-center justify-center gap-1.5 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-[#D5B77B]" aria-hidden="true">
              <circle cx="16" cy="4" r="1" />
              <path d="m18 19 1-7-6 1" />
              <path d="m5 8 3-3 5.5 3-2.36 3.5" />
              <path d="M4.24 14.5a5 5 0 0 0 6.88 6" />
              <path d="M13.76 17.5a5 5 0 0 0-6.88-6" />
            </svg>
            <span class="sr-only sm:not-sr-only">Accessibility</span>
          </button>

          <button
            type="button"
            id="dash-logout-btn"
            class="min-h-[44px] px-4 py-2 rounded-xl border border-[#383242] bg-[#25212B] hover:bg-[#383242] text-[#F9F7FC] text-sm font-semibold inline-flex items-center gap-1.5 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" x2="9" y1="12" y2="12" />
            </svg>
            <span id="dash-logout-label">Sign Out</span>
          </button>
        </div>
      </div>

      <!-- Accessible Dashboard Tablist -->
      <nav aria-label="Participant dashboard sections" class="mt-6">
        <div role="tablist" aria-label="Dashboard sections" class="flex flex-wrap gap-2">
          <button
            type="button"
            role="tab"
            id="tab-overview"
            data-dash-tab="overview"
            aria-controls="dash-panel-overview"
            aria-selected="true"
            tabindex="0"
            class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#F6F1F8] text-[#17151C]"
          >
            <span>Overview &amp; Registrations</span>
            <span id="dash-tab-reg-count" class="px-2 py-0.5 rounded-full text-xs font-bold bg-[#59436D]/15 text-[#432F56]">0</span>
          </button>

          <button
            type="button"
            role="tab"
            id="tab-accommodations"
            data-dash-tab="accommodations"
            aria-controls="dash-panel-accommodations"
            aria-selected="false"
            tabindex="-1"
            class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
          >
            <span>Accommodations</span>
            <span id="dash-tab-acc-count" class="px-2 py-0.5 rounded-full text-xs font-bold bg-white/10 text-[#F9F7FC]">0</span>
          </button>

          <button
            type="button"
            role="tab"
            id="tab-notifications"
            data-dash-tab="notifications"
            aria-controls="dash-panel-notifications"
            aria-selected="false"
            tabindex="-1"
            class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
          >
            <span>Notifications</span>
            <span
              id="dash-tab-unread-badge"
              class="hidden px-2 py-0.5 rounded-full text-xs font-bold bg-[#D5B77B] text-[#17151C]"
              aria-label="Unread notifications"
            >0</span>
          </button>

          <button
            type="button"
            role="tab"
            id="tab-programme"
            data-dash-tab="programme"
            aria-controls="dash-panel-programme"
            aria-selected="false"
            tabindex="-1"
            class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
          >
            <span>Programme &amp; Support</span>
          </button>

          <button
            type="button"
            role="tab"
            id="tab-settings"
            data-dash-tab="settings"
            aria-controls="dash-panel-settings"
            aria-selected="false"
            tabindex="-1"
            class="min-h-[44px] px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 transition-colors bg-[#25212B] text-[#D7CCE2] hover:text-[#F9F7FC]"
          >
            <span>Profile &amp; Account</span>
          </button>
        </div>
      </nav>
    </div>
  </header>

  <main id="main-content" class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Global Polite/Alert Status Region -->
    <div id="dash-global-status" class="hidden rounded-xl p-4 text-sm font-medium" aria-live="polite"></div>

    <!-- Staff Quick Return Notice (only shown if staff views participant portal) -->
    <?php if ($isAdmin || $isRgst): ?>
      <div class="rounded-2xl bg-[#17151C]/5 border border-[#59436D]/25 px-5 py-3.5 flex flex-wrap items-center justify-between gap-3 text-sm text-[#211B29]">
        <p>
          You are signed in with staff privileges. You can manage your own participant registrations here or return to your operational portal.
        </p>
        <div class="flex flex-wrap gap-2">
          <?php if ($isAdmin): ?>
            <a href="/admin" class="font-bold text-[#432F56] underline underline-offset-4 hover:text-[#5B2A86]">Return to Admin Portal</a>
          <?php endif; ?>
          <?php if ($isRgst): ?>
            <a href="/rgst" class="font-bold text-[#432F56] underline underline-offset-4 hover:text-[#5B2A86]">Open Registration Desk</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Legacy Claim Banner (shown dynamically when legacyClaimableCount > 0) -->
    <section
      id="dash-legacy-claim-banner"
      aria-label="Claim previous festival registrations"
      class="hidden rounded-2xl bg-[#FFF9EB] border-2 border-[#D5B77B] p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs"
    >
      <div class="space-y-1">
        <p class="text-xs font-bold uppercase tracking-wider text-[#5E4514]">Previous Registration Found</p>
        <h2 class="font-display text-lg font-semibold text-[#17151C]">
          Claim Your Legacy Festival Registration
        </h2>
        <p id="dash-legacy-claim-text" class="text-sm text-[#4B4257]">
          We found unlinked registration record(s) matching your verified email address. Link them to your account for read-only viewing here.
        </p>
      </div>
      <div class="shrink-0">
        <button
          type="button"
          id="dash-claim-legacy-btn"
          class="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] text-sm font-bold transition-colors disabled:opacity-60"
        >
          Claim Legacy Registration
        </button>
      </div>
    </section>

    <!-- Loading Indicator -->
    <div
      id="dash-loading-state"
      role="status"
      aria-live="polite"
      class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-8 text-center space-y-2"
    >
      <p class="font-display text-lg font-semibold text-[#17151C]">Loading your dashboard&hellip;</p>
      <p class="text-sm text-[#4B4257]">Fetching your registrations, duty assignments, accommodations, and notifications.</p>
    </div>

    <!-- ================= TAB 1: OVERVIEW & REGISTRATIONS ================= -->
    <section
      id="dash-panel-overview"
      role="tabpanel"
      aria-labelledby="tab-overview"
      data-dash-panel="overview"
      class="hidden space-y-6"
    >
      <!-- Summary Metrics Strip -->
      <div id="dash-overview-metrics" class="grid grid-cols-2 lg:grid-cols-4 gap-4" aria-label="Account summary metrics"></div>

      <!-- Active Festival Announcements (if any PUBLISHED) -->
      <div id="dash-announcements-wrap" class="hidden space-y-3" aria-label="Official festival announcements"></div>

      <!-- Primary Category Cards (Attendee, Volunteer, SLI) -->
      <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="font-display text-xl sm:text-2xl font-semibold text-[#17151C]">
              Your Festival Registrations
            </h2>
            <p class="text-sm text-[#4B4257] mt-0.5">
              Present your Registration ID (<span class="font-mono font-semibold text-[#432F56]">PFG-2027-NNNNNN</span>) at the Vadodara Registration Desk for manual attendance check-in.
            </p>
          </div>
          <a
            href="/apply"
            class="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white hover:bg-[#F6F1F8] text-[#432F56] text-sm font-bold inline-flex items-center gap-1.5 transition-colors"
          >
            Explore Registration Roles
          </a>
        </div>

        <div id="dash-registrations-container" class="space-y-5"></div>
      </div>

      <!-- Remaining Eligible Primary Categories CTA -->
      <div id="dash-eligible-apply-wrap" class="hidden surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <div>
          <h2 class="font-display text-lg font-semibold text-[#17151C]">Apply for Additional Roles</h2>
          <p class="text-sm text-[#4B4257] mt-0.5">
            You can hold up to one active registration per primary category (Attendee, Volunteer, and Sign Language Interpreter).
          </p>
        </div>
        <div id="dash-eligible-apply-grid" class="grid grid-cols-1 md:grid-cols-3 gap-4"></div>
      </div>

      <!-- Historical / Legacy Registrations (Read-only archive, hidden if none) -->
      <div id="dash-legacy-regs-wrap" class="hidden space-y-3">
        <div>
          <h2 class="font-display text-lg font-semibold text-[#17151C]">Historical / Legacy Registrations</h2>
          <p class="text-xs text-[#4B4257]">
            Preserved read-only records from earlier registration categories.
          </p>
        </div>
        <div id="dash-legacy-regs-list" class="space-y-3"></div>
      </div>
    </section>

    <!-- ================= TAB 2: ACCESSIBILITY & ACCOMMODATIONS ================= -->
    <section
      id="dash-panel-accommodations"
      role="tabpanel"
      aria-labelledby="tab-accommodations"
      data-dash-panel="accommodations"
      class="hidden grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <div>
          <h2 class="font-display text-xl font-semibold text-[#17151C]">
            Request Accessibility Accommodations
          </h2>
          <p class="text-sm text-[#4B4257] mt-1">
            Request ISL interpretation, Braille materials, wheelchair seating, sensory sanctuary access, or sighted guide support. No medical diagnosis is required — details are encrypted and visible only to authorized accessibility coordinators.
          </p>
        </div>

        <div id="dash-acc-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>

        <div id="dash-acc-no-regs" class="hidden rounded-xl bg-[#F6F1F8] border border-[#59436D]/20 p-4 text-sm text-[#4B4257] space-y-3">
          <p>Please submit a festival registration first so we can link your accommodation request to your Registration ID.</p>
          <a href="/apply" class="inline-flex items-center min-h-[44px] px-4 py-2 rounded-xl bg-[#59436D] text-[#F9F7FC] text-sm font-bold">
            Start Registration
          </a>
        </div>

        <form id="dash-acc-form" class="hidden space-y-4" novalidate>
          <div>
            <label for="acc-reg-select" class="block text-sm font-semibold text-[#211B29] mb-1.5">
              Link to Registration
            </label>
            <select
              id="acc-reg-select"
              name="registrationId"
              required
              class="w-full min-h-[44px] px-3 py-2 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
            ></select>
          </div>

          <fieldset>
            <legend class="text-sm font-semibold text-[#211B29] mb-2">
              Select Accommodations Needed
            </legend>
            <div id="dash-acc-types-list" class="grid grid-cols-1 gap-2 max-h-64 overflow-y-auto pr-1"></div>
          </fieldset>

          <div>
            <label for="acc-notes" class="block text-sm font-semibold text-[#211B29] mb-1.5">
              Specific Preferences or Timing Details (Optional)
            </label>
            <textarea
              id="acc-notes"
              name="freeTextDetails"
              rows="3"
              maxlength="1000"
              placeholder="Share seating, communication, companion, or day-specific preferences..."
              class="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
            ></textarea>
          </div>

          <button
            type="submit"
            id="dash-acc-submit-btn"
            class="btn-primary-pfg w-full min-h-[46px] rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm transition-colors disabled:opacity-60"
          >
            Submit Accommodation Request
          </button>
        </form>
      </div>

      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <div class="flex items-center justify-between gap-2">
          <h2 class="font-display text-xl font-semibold text-[#17151C]">
            Your Active &amp; Submitted Requests
          </h2>
          <a href="/accessibility" class="text-xs font-bold text-[#432F56] underline underline-offset-4 hover:text-[#5B2A86]">
            Accessibility Guide
          </a>
        </div>
        <div id="dash-acc-list" class="space-y-3"></div>
      </div>
    </section>

    <!-- ================= TAB 3: NOTIFICATIONS INBOX ================= -->
    <section
      id="dash-panel-notifications"
      role="tabpanel"
      aria-labelledby="tab-notifications"
      data-dash-panel="notifications"
      class="hidden space-y-4"
    >
      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#59436D]/15 pb-4">
          <div>
            <h2 class="font-display text-xl font-semibold text-[#17151C]">
              Notifications Inbox
            </h2>
            <p id="dash-notif-summary" class="text-sm text-[#4B4257] mt-0.5">
              Updates on your applications, duty assignments, accessibility requests, and festival schedule.
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-[#59436D]/25 bg-[#F6F1F8] p-1" role="group" aria-label="Filter notifications">
              <button
                type="button"
                id="dash-notif-filter-all"
                data-notif-filter="all"
                aria-pressed="true"
                class="min-h-[36px] px-3 py-1 rounded-lg text-xs font-bold bg-white text-[#17151C] shadow-xs"
              >
                All
              </button>
              <button
                type="button"
                id="dash-notif-filter-unread"
                data-notif-filter="unread"
                aria-pressed="false"
                class="min-h-[36px] px-3 py-1 rounded-lg text-xs font-bold text-[#4B4257] hover:text-[#17151C]"
              >
                Unread
              </button>
            </div>
            <button
              type="button"
              id="dash-notif-mark-all-btn"
              class="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white hover:bg-[#F6F1F8] text-[#432F56] text-xs font-bold transition-colors disabled:opacity-50"
            >
              Mark All as Read
            </button>
          </div>
        </div>

        <div id="dash-notif-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>
        <div id="dash-notif-list" class="space-y-3"></div>
        <div id="dash-notif-pagination" class="hidden flex items-center justify-between pt-3 border-t border-[#59436D]/15 text-sm"></div>
      </div>
    </section>

    <!-- ================= TAB 4: CONFIRMED PROGRAMME & SUPPORT ================= -->
    <section
      id="dash-panel-programme"
      role="tabpanel"
      aria-labelledby="tab-programme"
      data-dash-panel="programme"
      class="hidden space-y-6"
    >
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="font-display text-xl font-semibold text-[#17151C]">
                Confirmed Programme Highlights
              </h2>
              <p class="text-sm text-[#4B4257] mt-0.5">
                Published and confirmed sessions for <?= htmlspecialchars(FESTIVAL_DATES_DISPLAY, ENT_QUOTES, 'UTF-8') ?>. Draft sessions are never displayed.
              </p>
            </div>
            <a href="/programme" class="text-xs font-bold text-[#432F56] underline underline-offset-4 hover:text-[#5B2A86]">
              View Full Programme Page
            </a>
          </div>

          <div class="flex flex-wrap gap-2" role="group" aria-label="Filter programme by festival day">
            <button type="button" data-prog-day="all" aria-pressed="true" class="min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#59436D] text-white">All Days</button>
            <button type="button" data-prog-day="1" aria-pressed="false" class="min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#F6F1F8] text-[#432F56] border border-[#59436D]/20">Day 1 (21 Jan)</button>
            <button type="button" data-prog-day="2" aria-pressed="false" class="min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#F6F1F8] text-[#432F56] border border-[#59436D]/20">Day 2 (22 Jan)</button>
            <button type="button" data-prog-day="3" aria-pressed="false" class="min-h-[40px] px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#F6F1F8] text-[#432F56] border border-[#59436D]/20">Day 3 (23 Jan)</button>
          </div>

          <div id="dash-programme-list" class="space-y-3"></div>
        </div>

        <!-- Official Festival Support & Guidance -->
        <aside aria-label="Festival support and venue guidance" class="space-y-4">
          <div class="surface-card rounded-2xl bg-[#17151C] text-[#F9F7FC] p-6 space-y-4" data-surface="dark">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#D5B77B]">
              Official Secretariat Support
            </p>
            <h2 class="font-display text-xl font-semibold text-[#F9F7FC]">
              Need Help With Your Participation?
            </h2>
            <p class="text-sm text-[#D7CCE2]">
              Reach out to the Purple Festival Gujarat 2027 Secretariat in Vadodara for accessibility assistance, registration queries, or volunteer/SLI coordination.
            </p>
            <dl class="space-y-3 text-sm pt-2 border-t border-[#383242]">
              <div>
                <dt class="text-xs text-[#D7CCE2]">Festival Dates &amp; City</dt>
                <dd class="font-semibold text-[#F9F7FC]"><?= htmlspecialchars(FESTIVAL_DATES_DISPLAY . ' • ' . FESTIVAL_LOCATION_DISPLAY, ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
              <div>
                <dt class="text-xs text-[#D7CCE2]">Secretariat Email</dt>
                <dd>
                  <a
                    id="dash-support-email-link"
                    href="mailto:<?= htmlspecialchars(defined('NOTIFICATION_SUPPORT_EMAIL') ? NOTIFICATION_SUPPORT_EMAIL : 'purplefestivalvadodara@gmail.com', ENT_QUOTES, 'UTF-8') ?>"
                    class="font-semibold text-[#D5B77B] underline underline-offset-4 break-all hover:text-white"
                  >
                    <?= htmlspecialchars(defined('NOTIFICATION_SUPPORT_EMAIL') ? NOTIFICATION_SUPPORT_EMAIL : 'purplefestivalvadodara@gmail.com', ENT_QUOTES, 'UTF-8') ?>
                  </a>
                </dd>
              </div>
              <div>
                <dt class="text-xs text-[#D7CCE2]">Check-In Guidance</dt>
                <dd class="text-xs text-[#E8DFF0] leading-relaxed">
                  Arrive at the Vadodara Registration Desk with your Registration ID (<span class="font-mono font-bold text-[#D5B77B]">PFG-2027-NNNNNN</span>) or registered phone number for quick manual check-in. No QR pass is required.
                </dd>
              </div>
            </dl>
            <div class="pt-3 border-t border-[#383242] flex flex-col gap-2 text-xs">
              <a href="/accessibility" class="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-white">Festival Accessibility Guide</a>
              <a href="/legal/privacy" class="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-white">Privacy Notice (DPDP Act 2023)</a>
              <a href="/legal/accessibility-statement" class="font-semibold text-[#D5B77B] underline underline-offset-4 hover:text-white">WCAG 2.2 AA Accessibility Statement</a>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <!-- ================= TAB 5: PROFILE & ACCOUNT SETTINGS ================= -->
    <section
      id="dash-panel-settings"
      role="tabpanel"
      aria-labelledby="tab-settings"
      data-dash-panel="settings"
      class="hidden grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <!-- Profile & Contact Settings -->
      <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
        <div>
          <h2 class="font-display text-xl font-semibold text-[#17151C]">
            Profile &amp; Emergency Contact
          </h2>
          <p class="text-sm text-[#4B4257] mt-0.5">
            Keep your contact information, preferred language, and emergency contact up to date.
          </p>
        </div>

        <div id="dash-profile-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>

        <form id="dash-profile-form" class="space-y-4" novalidate>
          <div>
            <label for="prof-name" class="block text-sm font-semibold text-[#211B29] mb-1">
              Full Name <span class="text-[#B42332]" aria-hidden="true">*</span>
            </label>
            <input
              id="prof-name"
              name="fullName"
              type="text"
              required
              autocomplete="name"
              value="<?= htmlspecialchars($userFullName, ENT_QUOTES, 'UTF-8') ?>"
              class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="prof-phone" class="block text-sm font-semibold text-[#211B29] mb-1">
                Phone Number
              </label>
              <input
                id="prof-phone"
                name="phone"
                type="tel"
                autocomplete="tel"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <div>
              <label for="prof-language" class="block text-sm font-semibold text-[#211B29] mb-1">
                Preferred Language
              </label>
              <select
                id="prof-language"
                name="preferredLanguage"
                class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              >
                <option value="en">English</option>
                <option value="gu">ગુજરાતી (Gujarati)</option>
                <option value="hi">हिन्दी (Hindi)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
              <label for="prof-city" class="block text-sm font-semibold text-[#211B29] mb-1">
                City / District
              </label>
              <input
                id="prof-city"
                name="cityDistrict"
                type="text"
                autocomplete="address-level2"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <div>
              <label for="prof-state" class="block text-sm font-semibold text-[#211B29] mb-1">
                State
              </label>
              <input
                id="prof-state"
                name="state"
                type="text"
                autocomplete="address-level1"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <div>
              <label for="prof-country" class="block text-sm font-semibold text-[#211B29] mb-1">
                Country
              </label>
              <input
                id="prof-country"
                name="country"
                type="text"
                autocomplete="country-name"
                value="India"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
          </div>

          <fieldset class="pt-3 border-t border-[#59436D]/15 space-y-3">
            <legend class="text-sm font-semibold text-[#432F56]">
              Emergency Contact
            </legend>
            <div>
              <label for="prof-ec-name" class="block text-xs font-semibold text-[#211B29] mb-1">
                Contact Name
              </label>
              <input
                id="prof-ec-name"
                name="emergencyContactName"
                type="text"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label for="prof-ec-rel" class="block text-xs font-semibold text-[#211B29] mb-1">
                  Relationship
                </label>
                <input
                  id="prof-ec-rel"
                  name="emergencyContactRelationship"
                  type="text"
                  placeholder="e.g., Parent, Spouse, Friend"
                  class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
                />
              </div>
              <div>
                <label for="prof-ec-phone" class="block text-xs font-semibold text-[#211B29] mb-1">
                  Emergency Phone
                </label>
                <input
                  id="prof-ec-phone"
                  name="emergencyContactPhone"
                  type="tel"
                  class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
                />
              </div>
            </div>
          </fieldset>

          <button
            type="submit"
            id="dash-profile-submit-btn"
            class="btn-primary-pfg w-full min-h-[46px] rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] font-bold text-sm transition-colors disabled:opacity-60"
          >
            Save Profile Details
          </button>
        </form>
      </div>

      <!-- Right Column: Email Change & Password Change -->
      <div class="space-y-6">
        <!-- Email & Verification Card -->
        <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-display text-xl font-semibold text-[#17151C]">
              Email Address
            </h2>
            <span id="dash-email-verified-badge" class="px-2.5 py-1 rounded-lg text-xs font-bold border bg-[#F6F1F8] text-[#432F56] border-[#59436D]/20">
              Checking&hellip;
            </span>
          </div>

          <p class="text-sm text-[#4B4257]">
            Current email: <strong id="dash-current-email" class="text-[#17151C]"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></strong>
          </p>

          <div id="dash-pending-email-box" class="hidden rounded-xl bg-[#FFF9EB] border border-[#D5B77B] p-3.5 text-xs text-[#5E4514]"></div>
          <div id="dash-email-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>

          <form id="dash-email-form" class="space-y-3" novalidate>
            <div>
              <label for="prof-new-email" class="block text-xs font-semibold text-[#211B29] mb-1">
                Request New Email Address
              </label>
              <input
                id="prof-new-email"
                name="email"
                type="email"
                autocomplete="email"
                placeholder="new-email@example.com"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
              <p class="text-xs text-[#4B4257] mt-1">
                We will send a verification link to your new email address. Your login email only updates after you verify it.
              </p>
            </div>
            <div>
              <label for="prof-email-curr-pw" class="block text-xs font-semibold text-[#211B29] mb-1">
                Current Password
              </label>
              <input
                id="prof-email-curr-pw"
                name="currentPassword"
                type="password"
                autocomplete="current-password"
                placeholder="Enter your current password to confirm"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <button
              type="submit"
              id="dash-email-submit-btn"
              class="min-h-[44px] px-5 py-2 rounded-xl border border-[#59436D] text-[#432F56] hover:bg-[#F6F1F8] font-bold text-sm transition-colors disabled:opacity-60"
            >
              Send Email Change Verification
            </button>
          </form>
        </div>

        <!-- Change Password Card -->
        <div class="surface-card rounded-2xl bg-white border border-[#59436D]/20 p-6 space-y-4">
          <div>
            <h2 class="font-display text-xl font-semibold text-[#17151C]">
              Change Password
            </h2>
            <p class="text-xs text-[#4B4257] mt-0.5">
              Changing your password revokes other active sessions on your account.
            </p>
          </div>

          <div id="dash-password-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>

          <form id="dash-password-form" class="space-y-3" novalidate>
            <div>
              <label for="prof-curr-pw" class="block text-xs font-semibold text-[#211B29] mb-1">
                Current Password
              </label>
              <input
                id="prof-curr-pw"
                name="currentPassword"
                type="password"
                autocomplete="current-password"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <div>
              <label for="prof-new-pw" class="block text-xs font-semibold text-[#211B29] mb-1">
                New Password (minimum 8 characters, include letter &amp; number)
              </label>
              <input
                id="prof-new-pw"
                name="newPassword"
                type="password"
                autocomplete="new-password"
                minlength="8"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <div>
              <label for="prof-confirm-pw" class="block text-xs font-semibold text-[#211B29] mb-1">
                Confirm New Password
              </label>
              <input
                id="prof-confirm-pw"
                name="confirmNewPassword"
                type="password"
                autocomplete="new-password"
                minlength="8"
                class="w-full min-h-[44px] px-3.5 rounded-xl border border-[#59436D]/30 bg-white text-sm text-[#17151C]"
              />
            </div>
            <button
              type="submit"
              id="dash-password-submit-btn"
              class="min-h-[44px] px-5 py-2 rounded-xl bg-[#17151C] hover:bg-[#25212B] text-[#F9F7FC] font-bold text-sm transition-colors disabled:opacity-60"
            >
              Update Password
            </button>
          </form>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- ================= ACCESSIBLE MODAL 1: EDIT / RESUBMIT REGISTRATION ================= -->
<div
  id="dash-edit-modal"
  class="fixed inset-0 z-[1050] hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
>
  <div
    role="dialog"
    aria-modal="true"
    aria-labelledby="dash-edit-modal-title"
    class="relative w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white border border-[#59436D]/25 shadow-2xl p-6 space-y-4"
  >
    <div class="flex items-start justify-between gap-3 border-b border-[#59436D]/15 pb-3">
      <div>
        <p id="dash-edit-modal-code" class="font-mono text-xs font-bold text-[#432F56]"></p>
        <h2 id="dash-edit-modal-title" class="font-display text-xl font-semibold text-[#17151C]">
          Edit Registration
        </h2>
      </div>
      <button
        type="button"
        data-close-modal="dash-edit-modal"
        aria-label="Close edit registration dialog"
        class="min-h-[44px] min-w-[44px] rounded-xl border border-[#59436D]/25 inline-flex items-center justify-center text-[#211B29] hover:bg-[#F6F1F8]"
      >
        &times;
      </button>
    </div>

    <div id="dash-edit-review-callout" class="hidden rounded-xl bg-[#FFF9EB] border border-[#D5B77B] p-3.5 text-sm text-[#5E4514]"></div>
    <div id="dash-edit-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>

    <form id="dash-edit-form" class="space-y-4" novalidate>
      <input type="hidden" id="edit-reg-id" name="registrationId" value="" />

      <fieldset>
        <legend class="text-sm font-semibold text-[#211B29] mb-2">Attending / Available Festival Days</legend>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
          <label class="cursor-pointer rounded-xl border border-[#59436D]/20 p-2.5 flex items-center gap-2 text-xs font-semibold">
            <input type="checkbox" name="editAttendingDays" value="DAY_1" class="w-4 h-4 accent-[#59436D]" />
            <span>Day 1 (21 Jan)</span>
          </label>
          <label class="cursor-pointer rounded-xl border border-[#59436D]/20 p-2.5 flex items-center gap-2 text-xs font-semibold">
            <input type="checkbox" name="editAttendingDays" value="DAY_2" class="w-4 h-4 accent-[#59436D]" />
            <span>Day 2 (22 Jan)</span>
          </label>
          <label class="cursor-pointer rounded-xl border border-[#59436D]/20 p-2.5 flex items-center gap-2 text-xs font-semibold">
            <input type="checkbox" name="editAttendingDays" value="DAY_3" class="w-4 h-4 accent-[#59436D]" />
            <span>Day 3 (23 Jan)</span>
          </label>
        </div>
      </fieldset>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label for="edit-phone" class="block text-xs font-semibold text-[#211B29] mb-1">Phone Number</label>
          <input id="edit-phone" type="tel" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm" />
        </div>
        <div>
          <label for="edit-org" class="block text-xs font-semibold text-[#211B29] mb-1">Organization / Institution</label>
          <input id="edit-org" type="text" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label for="edit-city" class="block text-xs font-semibold text-[#211B29] mb-1">City / District</label>
          <input id="edit-city" type="text" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm" />
        </div>
        <div>
          <label for="edit-state" class="block text-xs font-semibold text-[#211B29] mb-1">State</label>
          <input id="edit-state" type="text" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label for="edit-ec-name" class="block text-xs font-semibold text-[#211B29] mb-1">Emergency Contact Name</label>
          <input id="edit-ec-name" type="text" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm" />
        </div>
        <div>
          <label for="edit-ec-phone" class="block text-xs font-semibold text-[#211B29] mb-1">Emergency Contact Phone</label>
          <input id="edit-ec-phone" type="tel" class="w-full min-h-[44px] px-3 rounded-xl border border-[#59436D]/30 bg-white text-sm" />
        </div>
      </div>

      <div id="edit-resubmit-note-wrap" class="hidden">
        <label for="edit-resubmit-note" class="block text-xs font-semibold text-[#211B29] mb-1">
          Response to Coordinator Info Request
        </label>
        <textarea
          id="edit-resubmit-note"
          rows="3"
          maxlength="1000"
          placeholder="Provide the additional details requested by the review coordinator..."
          class="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm"
        ></textarea>
      </div>

      <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-[#59436D]/15">
        <button
          type="button"
          data-close-modal="dash-edit-modal"
          class="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white text-[#432F56] text-sm font-semibold"
        >
          Cancel
        </button>
        <button
          type="submit"
          id="dash-edit-submit-btn"
          class="min-h-[44px] px-5 py-2 rounded-xl bg-[#59436D] hover:bg-[#432F56] text-[#F9F7FC] text-sm font-bold disabled:opacity-60"
        >
          Save Updates
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= ACCESSIBLE MODAL 2: WITHDRAW / CANCEL CONFIRMATION ================= -->
<div
  id="dash-withdraw-modal"
  class="fixed inset-0 z-[1050] hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
>
  <div
    role="dialog"
    aria-modal="true"
    aria-labelledby="dash-withdraw-modal-title"
    class="relative w-full max-w-md rounded-2xl bg-white border border-[#B42332]/30 shadow-2xl p-6 space-y-4"
  >
    <div class="flex items-start justify-between gap-3">
      <div>
        <p id="dash-withdraw-modal-code" class="font-mono text-xs font-bold text-[#8A1824]"></p>
        <h2 id="dash-withdraw-modal-title" class="font-display text-xl font-semibold text-[#17151C]">
          Cancel / Withdraw Registration
        </h2>
      </div>
      <button
        type="button"
        data-close-modal="dash-withdraw-modal"
        aria-label="Close cancel confirmation dialog"
        class="min-h-[44px] min-w-[44px] rounded-xl border border-[#59436D]/25 inline-flex items-center justify-center text-[#211B29] hover:bg-[#F6F1F8]"
      >
        &times;
      </button>
    </div>

    <p id="dash-withdraw-modal-desc" class="text-sm text-[#4B4257]">
      Are you sure you want to withdraw this registration? If you already have an assigned volunteer or interpreter shift, coordinators will be notified automatically.
    </p>

    <div id="dash-withdraw-message" class="hidden rounded-xl p-3.5 text-sm" aria-live="polite"></div>

    <form id="dash-withdraw-form" class="space-y-4" novalidate>
      <input type="hidden" id="withdraw-reg-id" value="" />
      <input type="hidden" id="withdraw-action" value="withdraw" />

      <div>
        <label for="withdraw-reason" class="block text-xs font-semibold text-[#211B29] mb-1">
          Reason (Optional)
        </label>
        <textarea
          id="withdraw-reason"
          rows="2"
          maxlength="500"
          placeholder="Optional note for organizers..."
          class="w-full p-3 rounded-xl border border-[#59436D]/30 bg-white text-sm"
        ></textarea>
      </div>

      <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-[#59436D]/15">
        <button
          type="button"
          data-close-modal="dash-withdraw-modal"
          class="min-h-[44px] px-4 py-2 rounded-xl border border-[#59436D]/30 bg-white text-[#432F56] text-sm font-semibold"
        >
          Keep Registration
        </button>
        <button
          type="submit"
          id="dash-withdraw-submit-btn"
          class="min-h-[44px] px-5 py-2 rounded-xl bg-[#B42332] hover:bg-[#8A1824] text-white text-sm font-bold disabled:opacity-60"
        >
          Confirm Withdrawal
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  var csrfToken = <?= pfgSafeJsonForScript($csrfToken) ?>;
  window.PFG_DASHBOARD_CONFIG = <?= pfgSafeJsonForScript([
      'csrfToken'       => $csrfToken,
      'festivalName'    => FESTIVAL_NAME,
      'festivalDates'   => FESTIVAL_DATES_DISPLAY,
      'festivalCity'    => FESTIVAL_LOCATION_DISPLAY,
      'endpoints'       => [
          'dashboard'       => '/api/account/dashboard',
          'notifications'   => '/api/account/notifications',
          'profile'         => '/api/account/profile',
          'accommodations'  => '/api/account/accommodations',
          'registrations'   => '/api/account/registrations',
          'claimLegacy'     => '/api/auth/claim-legacy',
          'logout'          => '/api/auth/logout',
      ],
  ]) ?>;
</script>
<script src="/assets/js/participant-dashboard.js"></script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
