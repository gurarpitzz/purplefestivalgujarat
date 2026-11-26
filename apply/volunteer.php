<?php
declare(strict_types=1);

/**
 * /apply/volunteer -> apply/volunteer.php
 * Purple Festival Gujarat 2027 — Dedicated Festival Volunteer Application Form (Phase 6)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration-validators.php';
require_once __DIR__ . '/../includes/form-components/applicant-details.php';
require_once __DIR__ . '/../includes/form-components/wave-stepper.php';
require_once __DIR__ . '/../includes/form-components/shared-accessibility.php';
require_once __DIR__ . '/../includes/form-components/shift-availability-grid.php';
require_once __DIR__ . '/../includes/form-components/emergency-contact.php';
require_once __DIR__ . '/../includes/form-components/consent-declarations.php';

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$pdo = getDb();
$registrationOpen = pfgIsPublicRegistrationOpen($pdo);

$disabilityCategories = [];
$stmtDis = $pdo->query('SELECT id, code, name, rpwd_group, description FROM disability_categories ORDER BY display_order ASC');
if ($stmtDis) {
    $disabilityCategories = $stmtDis->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if (empty($disabilityCategories) && defined('RPWD_21_DISABILITIES')) {
    $disabilityCategories = RPWD_21_DISABILITIES;
}

$accommodationTypes = [];
$stmtAcc = $pdo->query('SELECT id, code, label, description FROM accommodation_types WHERE is_active = 1 ORDER BY display_order ASC');
if ($stmtAcc) {
    $accommodationTypes = $stmtAcc->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if (empty($accommodationTypes) && defined('ACCOMMODATION_TYPES_SEED')) {
    $accommodationTypes = ACCOMMODATION_TYPES_SEED;
}

$ecDefaults = [
    'emergencyContactName'         => '',
    'emergencyContactRelationship' => '',
    'emergencyContactPhone'        => '',
];

$bootData = [
    'categoryCode'     => 'VOLUNTEER',
    'categoryName'     => 'Festival Volunteer',
    'registrationOpen' => $registrationOpen,
    'csrfToken'        => $registrationOpen ? getCsrfToken() : '',
];

$pageTitle = 'Festival Volunteer Application | Purple Festival Gujarat 2027';
$pageDescription = 'Apply as a Festival Volunteer for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara). Volunteers with and without disabilities are equally welcomed. No account required.';
$extraHeadHtml = (isset($extraHeadHtml) ? $extraHeadHtml . "\n" : '')
    . '  <link rel="stylesheet" href="/assets/css/form-wave-stepper.css?v=1" />';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] pfg-ivory-surface text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[900px] px-5 py-10 sm:px-8 sm:py-14">
    <nav aria-label="Breadcrumb" class="mb-5 text-sm text-[#46537C]">
      <a href="/apply" class="font-semibold text-[#5B2A86] underline hover:text-[#202D59]">&larr; All Registration Categories</a>
    </nav>

    <header class="mb-7">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        Primary Category 3 of 3 · Free · No Account Required · Coordinator Review
      </span>
      <h1 class="mt-2 text-[clamp(1.65rem,3.2vw,2.35rem)] font-bold tracking-[-0.02em] text-[#202D59]">
        Festival Volunteer Application
      </h1>
      <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
        Volunteers with and without disabilities are equally welcomed (minimum age 16 years). Applications are reviewed by our Volunteer Coordinators before approval and shift assignment.
      </p>
    </header>

    <?php if (!$registrationOpen): ?>
      <?= pfgRenderRegistrationClosedCard('Festival Volunteer Application') ?>
    <?php else: ?>
      <!-- 3-Step Flowing Wave-Node Progress Bar -->
      <?= pfgRenderWaveStepper([
          ['number' => 1, 'title' => 'Your Details'],
          ['number' => 2, 'title' => 'Roles & Shifts'],
          ['number' => 3, 'title' => 'Accessibility & Confirm'],
      ]) ?>

      <div
        id="pfg-form-error-summary"
        role="alert"
        aria-live="assertive"
        tabindex="-1"
        hidden
        class="mb-6 rounded-2xl border-2 border-red-600 bg-red-50 p-5 text-sm text-red-950"
      ></div>

      <form id="pfg-category-form" novalidate class="space-y-7" data-wizard-form="true">
        <!-- STEP 1: Applicant Details + Volunteer Profile -->
        <div class="pfg-wizard-step space-y-7" data-wizard-step="1" data-step-title="Your Details">
        <!-- Section 0: Direct Applicant Personal & Contact Details -->
        <?= pfgRenderApplicantDetails(true, 'Volunteer Application') ?>

        <!-- Section 1: Volunteer Profile & MSU Affiliation -->
        <section aria-labelledby="vol-sec1-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 1</p>
          <h2 id="vol-sec1-heading" class="mt-1 text-xl font-bold text-[#202D59]">Volunteer Profile &amp; Institutional Affiliation</h2>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-vol-age" class="block text-sm font-bold text-[#202D59]">
                Age (Minimum 16 years) <span class="text-red-700" aria-hidden="true">*</span>
              </label>
              <input
                type="number"
                id="pfg-vol-age"
                name="age"
                min="16"
                max="85"
                required
                placeholder="16–85"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
            </div>

            <div>
              <label for="pfg-vol-gender" class="block text-sm font-bold text-[#202D59]">
                Gender <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <select
                id="pfg-vol-gender"
                name="gender"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              >
                <option value="">Select (Optional)</option>
                <?php foreach (PFG_GENDER_OPTIONS as $g): ?>
                  <option value="<?= $esc($g) ?>"><?= $esc($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- MSU Affiliation Enum (NOT a boolean checkbox) -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Are you a student or alumni of The Maharaja Sayajirao University of Baroda (MSU)? <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 grid gap-2.5 sm:grid-cols-3">
              <?php foreach (PFG_VOLUNTEER_MSU_AFFILIATIONS as $mCode => $mLabel): ?>
                <label
                  for="pfg-msu-<?= $esc(strtolower($mCode)) ?>"
                  class="touch-target flex cursor-pointer items-start gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-3 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="radio"
                    id="pfg-msu-<?= $esc(strtolower($mCode)) ?>"
                    name="msuAffiliation"
                    value="<?= $esc($mCode) ?>"
                    required
                    class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($mLabel) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Conditional MSU Faculty / Department -->
          <div id="pfg-msu-faculty-wrap" hidden class="mt-4">
            <label for="pfg-msu-faculty" class="block text-sm font-bold text-[#202D59]">
              MSU Faculty / Department Name <span class="text-red-700" aria-hidden="true">*</span>
            </label>
            <input
              type="text"
              id="pfg-msu-faculty"
              name="msuFacultyDepartment"
              maxlength="150"
              placeholder="e.g., Faculty of Social Work, Dept. of Physical Education"
              class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            />
          </div>

          <!-- Conditional External Institution / NSS / NCC -->
          <div id="pfg-ext-inst-wrap" hidden class="mt-4">
            <label for="pfg-ext-inst" class="block text-sm font-bold text-[#202D59]">
              College / Institution / NSS / NCC Unit / Organisation <span class="font-normal text-[#46537C]">(Optional)</span>
            </label>
            <input
              type="text"
              id="pfg-ext-inst"
              name="institutionName"
              maxlength="200"
              placeholder="Optional college, NSS/NCC unit, NGO, or workplace"
              class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            />
          </div>

          <!-- Strictly Optional RCI CRR Number -->
          <div class="mt-5">
            <label for="pfg-vol-crr" class="block text-sm font-bold text-[#202D59]">
              RCI CRR Number <span class="font-normal text-[#46537C]">(Optional — only if you are a registered rehabilitation professional)</span>
            </label>
            <p id="pfg-vol-crr-help" class="mt-0.5 text-xs text-[#46537C]">
              Leave blank if not applicable — students, NSS/NCC, and community volunteers do not need a CRR number.
            </p>
            <input
              type="text"
              id="pfg-vol-crr"
              name="crrNumber"
              maxlength="40"
              aria-describedby="pfg-vol-crr-help"
              placeholder="Optional"
              class="touch-target mt-1.5 w-full max-w-md rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            />
          </div>
        </section>
        </div><!-- /STEP 1 -->

        <!-- STEP 2: Role Preferences, Languages & Shift Availability -->
        <div class="pfg-wizard-step space-y-7" data-wizard-step="2" data-step-title="Roles &amp; Shifts" hidden>
        <!-- Section 2: Role Preferences, Languages & Experience -->
        <section aria-labelledby="vol-sec2-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 2</p>
          <h2 id="vol-sec2-heading" class="mt-1 text-xl font-bold text-[#202D59]">Role Preferences, Languages &amp; Experience</h2>

          <!-- Exact 5 Organizer Primary Roles -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Primary Role Interested In <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 space-y-2.5">
              <?php foreach (PFG_VOLUNTEER_PRIMARY_ROLES as $idx => $roleLabel): ?>
                <label
                  for="pfg-primary-role-<?= $idx ?>"
                  class="touch-target flex cursor-pointer items-center gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-4 py-3 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="radio"
                    id="pfg-primary-role-<?= $idx ?>"
                    name="primaryRole"
                    value="<?= $esc($roleLabel) ?>"
                    required
                    class="h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($roleLabel) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Conditional Anchoring Experience (required when Stage Anchoring / Hosting selected) -->
          <div id="pfg-anchoring-exp-wrap" hidden class="mt-5 rounded-xl border-2 border-[#5B2A86]/35 bg-[#F7F4FC] p-4">
            <label for="pfg-anchoring-exp" class="block text-sm font-bold text-[#2B144D]">
              Prior Public Speaking or Stage Hosting Experience <span class="text-red-700" aria-hidden="true">*</span>
            </label>
            <p class="mt-0.5 text-xs text-[#46537C]">
              Since you selected Stage Anchoring / Hosting, please briefly describe any prior public speaking, emceeing, or stage hosting experience (10–1,000 characters).
            </p>
            <textarea
              id="pfg-anchoring-exp"
              name="anchoringExperience"
              rows="3"
              maxlength="1000"
              class="mt-2 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            ></textarea>
          </div>

          <!-- Optional Secondary Roles -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Other Roles You Are Open to Helping With <span class="font-normal text-[#46537C]">(Optional)</span>
            </legend>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
              <?php foreach (array_merge(PFG_VOLUNTEER_PRIMARY_ROLES, ['Para-Sports Support']) as $idx => $sRole): ?>
                <label for="pfg-sec-role-<?= $idx ?>" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-3 py-2 text-sm">
                  <input type="checkbox" id="pfg-sec-role-<?= $idx ?>" name="secondaryRoles[]" value="<?= $esc($sRole) ?>" class="h-5 w-5 accent-[#5B2A86]" />
                  <span><?= $esc($sRole) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Optional Relevant Experience -->
          <div class="mt-5">
            <label for="pfg-vol-rel-exp" class="block text-sm font-bold text-[#202D59]">
              Relevant Volunteering or Inclusion Experience <span class="font-normal text-[#46537C]">(Optional)</span>
            </label>
            <textarea
              id="pfg-vol-rel-exp"
              name="relevantExperience"
              rows="3"
              maxlength="1000"
              placeholder="Any prior festival, NSS/NCC, community, or disability inclusion volunteering..."
              class="mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            ></textarea>
          </div>

          <!-- Languages Spoken (NEVER pre-selected!) -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Languages Spoken <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <p class="mt-0.5 text-xs text-[#46537C]">Select at least one language you can communicate in.</p>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-3">
              <?php foreach (PFG_VOLUNTEER_LANGUAGES as $idx => $lang): ?>
                <label for="pfg-lang-<?= $idx ?>" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm font-medium text-[#202D59] hover:border-[#A459FF]">
                  <input type="checkbox" id="pfg-lang-<?= $idx ?>" name="languagesSpoken[]" value="<?= $esc($lang) ?>" class="h-5 w-5 accent-[#5B2A86]" />
                  <span><?= $esc($lang) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <div id="pfg-lang-other-wrap" hidden class="mt-3">
            <label for="pfg-lang-other" class="block text-xs font-bold text-[#202D59]">Specify Other Language(s)</label>
            <input type="text" id="pfg-lang-other" name="languagesOther" maxlength="80" class="touch-target mt-1 w-full max-w-md rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm" />
          </div>
        </section>

        <!-- Section 3: Day × Shift Availability Grid -->
        <section aria-labelledby="vol-sec3-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 3</p>
          <h2 id="vol-sec3-heading" class="mt-1 mb-4 text-xl font-bold text-[#202D59]">Shift Availability</h2>
          <?= pfgRenderShiftAvailabilityGrid('availabilityShifts', [], 'VOLUNTEER') ?>
        </section>
        </div><!-- /STEP 2 -->

        <!-- STEP 3: Accessibility, Emergency Contact & Declarations -->
        <div class="pfg-wizard-step space-y-7" data-wizard-step="3" data-step-title="Accessibility &amp; Confirm" hidden>
        <!-- Section 4: Shared Accessibility & Accommodations Module -->
        <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, []) ?>

        <!-- Section 5: Emergency Contact (Required) -->
        <?= pfgRenderEmergencyContact($ecDefaults, true, true) ?>

        <!-- Section 6: Declarations & Consents -->
        <?= pfgRenderConsentDeclarations('VOLUNTEER', []) ?>
        </div><!-- /STEP 3 -->

        <!-- Wizard Navigation & Submit Controls -->
        <div class="pfg-wizard-nav">
          <div class="pfg-wizard-nav__left">
            <a href="/apply" id="pfg-btn-cancel" class="text-sm font-semibold text-[#46537C] underline hover:text-[#202D59]">Cancel</a>
            <button type="button" id="pfg-btn-prev" class="pfg-wizard-btn-prev" hidden>
              <span aria-hidden="true">&larr;</span>
              <span>Previous</span>
            </button>
          </div>
          <div class="pfg-wizard-nav__right">
            <button type="button" id="pfg-btn-next" class="pfg-wizard-btn-next">
              <span id="pfg-btn-next-label">Next: Roles &amp; Shifts</span>
              <span aria-hidden="true">&rarr;</span>
            </button>
            <button
              type="button"
              id="pfg-btn-review"
              hidden
              class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] transition-colors hover:bg-[#DDDDF9]"
            >
              Review Information
            </button>
            <button
              type="submit"
              id="pfg-btn-submit"
              hidden
              class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-7 py-3 text-base font-bold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-50"
            >
              Submit Volunteer Application
            </button>
          </div>
        </div>
      </form>

      <section id="pfg-review-panel" hidden tabindex="-1" aria-labelledby="pfg-review-heading" class="rounded-2xl border-2 border-[#5B2A86]/35 bg-white p-6 sm:p-8 shadow-sm"></section>

      <section id="pfg-confirmation-panel" hidden tabindex="-1" aria-live="polite" class="rounded-2xl border-2 border-emerald-700/40 bg-white p-6 sm:p-8 shadow-sm"></section>
    <?php endif; ?>
  </div>
</main>
<?php
$bootJson = pfgSafeJsonForScript($bootData);
$extraFooterHtml = <<<HTML
<script>window.__PFG_FORM_BOOT__ = {$bootJson};</script>
<script src="/assets/js/shared-accessibility.js"></script>
<script src="/assets/js/category-forms.js?v=2"></script>
HTML;
require_once __DIR__ . '/../includes/footer.php';
