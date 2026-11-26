<?php
declare(strict_types=1);

/**
 * /apply/sli -> apply/sli.php
 * Purple Festival Gujarat 2027 — Dedicated Sign Language Interpreter (SLI) Application Form (Phase 6)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration-validators.php';
require_once __DIR__ . '/../includes/form-components/applicant-details.php';
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

$defaultCity = '';
$defaultState = '';

$ecDefaults = [
    'emergencyContactName'         => '',
    'emergencyContactRelationship' => '',
    'emergencyContactPhone'        => '',
];

$bootData = [
    'categoryCode'     => 'SLI',
    'categoryName'     => 'Sign Language Interpreter (SLI)',
    'registrationOpen' => $registrationOpen,
    'csrfToken'        => $registrationOpen ? getCsrfToken() : '',
];

$pageTitle = 'Sign Language Interpreter (SLI) Application | Purple Festival Gujarat 2027';
$pageDescription = 'Apply as an Indian Sign Language Interpreter (SLI) for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara). No account required.';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[900px] px-5 py-10 sm:px-8 sm:py-14">
    <nav aria-label="Breadcrumb" class="mb-5 text-sm text-[#46537C]">
      <a href="/apply" class="font-semibold text-[#5B2A86] underline hover:text-[#202D59]">&larr; All Registration Categories</a>
    </nav>

    <header class="mb-7">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        Primary Category 2 of 3 · Free · No Account Required · Coordinator Verification
      </span>
      <h1 class="mt-2 text-[clamp(1.65rem,3.2vw,2.35rem)] font-bold tracking-[-0.02em] text-[#202D59]">
        Sign Language Interpreter (SLI) Application
      </h1>
      <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
        For Certified ISL Interpreters, Deaf Interpreters, fluent community signers, and trainee interpreters (minimum age 18 years). Applications are verified by our Accessibility &amp; SLI Coordinators before session assignment.
      </p>
    </header>

    <?php if (!$registrationOpen): ?>
      <?= pfgRenderRegistrationClosedCard('Sign Language Interpreter (SLI) Application') ?>
    <?php else: ?>
      <div
        id="pfg-form-error-summary"
        role="alert"
        aria-live="assertive"
        tabindex="-1"
        hidden
        class="mb-6 rounded-2xl border-2 border-red-600 bg-red-50 p-5 text-sm text-red-950"
      ></div>

      <form id="pfg-category-form" novalidate class="space-y-7">
        <!-- Section 0: Direct Applicant Personal & Contact Details -->
        <?= pfgRenderApplicantDetails(false, 'SLI Application') ?>

        <!-- Section 1: Interpreter Profile & Gujarat Availability Context -->
        <section aria-labelledby="sli-sec1-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 1</p>
          <h2 id="sli-sec1-heading" class="mt-1 text-xl font-bold text-[#202D59]">Interpreter Profile &amp; Location Context</h2>

          <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div>
              <label for="pfg-sli-age" class="block text-sm font-bold text-[#202D59]">
                Age (Minimum 18 years) <span class="text-red-700" aria-hidden="true">*</span>
              </label>
              <input
                type="number"
                id="pfg-sli-age"
                name="age"
                min="18"
                max="85"
                required
                placeholder="18–85"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
            </div>

            <div>
              <label for="pfg-sli-city" class="block text-sm font-bold text-[#202D59]">
                City / District <span class="text-red-700" aria-hidden="true">*</span>
              </label>
              <input
                type="text"
                id="pfg-sli-city"
                name="cityDistrict"
                maxlength="120"
                required
                value="<?= $esc($defaultCity) ?>"
                placeholder="e.g., Vadodara, Ahmedabad, Delhi"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
            </div>

            <div>
              <label for="pfg-sli-state" class="block text-sm font-bold text-[#202D59]">
                State / Union Territory <span class="text-red-700" aria-hidden="true">*</span>
              </label>
              <select
                id="pfg-sli-state"
                name="state"
                required
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              >
                <option value="">Select state / UT</option>
                <?php foreach (PFG_INDIAN_STATES as $st): ?>
                  <option value="<?= $esc($st) ?>" <?= $defaultState === $st ? 'selected' : '' ?>><?= $esc($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Explicit Gujarat Residency (NO silent default true, NO hard block for outstation interpreters) -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Are you currently based in Gujarat? <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <p class="mt-0.5 text-xs text-[#46537C]">
              Priority is given to interpreters based in Gujarat; qualified interpreters from other states (e.g., ISLRTC / national network) are warmly welcomed to apply for coordinator review.
            </p>
            <div class="mt-2.5 flex flex-wrap gap-3">
              <label for="pfg-sli-guj-yes" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-5 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]">
                <input type="radio" id="pfg-sli-guj-yes" name="basedInGujarat" value="yes" required class="h-5 w-5 accent-[#5B2A86]" />
                <span>Yes, based in Gujarat</span>
              </label>
              <label for="pfg-sli-guj-no" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-5 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]">
                <input type="radio" id="pfg-sli-guj-no" name="basedInGujarat" value="no" required class="h-5 w-5 accent-[#5B2A86]" />
                <span>No, based outside Gujarat</span>
              </label>
            </div>
          </fieldset>

          <div id="pfg-sli-outstation-note" hidden role="note" class="mt-3 rounded-xl border border-[#5B2A86]/35 bg-[#F7F4FC] px-4 py-3 text-xs leading-relaxed text-[#202D59]">
            <strong>Outstation / National Interpreter Application:</strong> Your application will be accepted and tagged for SLI Coordinator review. Feel free to share any travel or accommodation expectations in Section 3 below.
          </div>

          <div class="mt-5">
            <label for="pfg-sli-aff" class="block text-sm font-bold text-[#202D59]">
              Professional Affiliation / Deaf Association / Independent <span class="font-normal text-[#46537C]">(Optional)</span>
            </label>
            <input
              type="text"
              id="pfg-sli-aff"
              name="professionalAffiliation"
              maxlength="200"
              placeholder="e.g., ISLRTC, Association of Sign Language Interpreters, Independent"
              class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            />
          </div>
        </section>

        <!-- Section 2: Professional Qualifications, CRR & Experience -->
        <section aria-labelledby="sli-sec2-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 2</p>
          <h2 id="sli-sec2-heading" class="mt-1 text-xl font-bold text-[#202D59]">Qualifications, CRR &amp; Experience</h2>

          <!-- Exact 4 Qualification Tiers -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Certification / Qualification Level in Sign Language <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 space-y-2.5">
              <?php foreach (PFG_SLI_QUALIFICATION_TIERS as $qCode => $qLabel): ?>
                <label
                  for="pfg-sli-qual-<?= $esc(strtolower($qCode)) ?>"
                  class="touch-target flex cursor-pointer items-start gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-4 py-3 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="radio"
                    id="pfg-sli-qual-<?= $esc(strtolower($qCode)) ?>"
                    name="qualificationCategory"
                    value="<?= $esc($qCode) ?>"
                    required
                    class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($qLabel) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Conditional Qualification Detail (required for OTHER, optional diploma detail otherwise) -->
          <div id="pfg-sli-qual-detail-wrap" class="mt-4">
            <label for="pfg-sli-qual-detail" class="block text-sm font-bold text-[#202D59]">
              <span id="pfg-sli-qual-detail-label">Specific Diploma / Course (e.g., DISLI, DTISL) or Background</span>
              <span id="pfg-sli-qual-detail-star" class="hidden text-red-700" aria-hidden="true">*</span>
            </label>
            <input
              type="text"
              id="pfg-sli-qual-detail"
              name="qualifications"
              maxlength="200"
              placeholder="Optional diploma, course, or community interpreting background"
              class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            />
          </div>

          <!-- RCI CRR Number (Optional / prompted for Certified tier; NEVER hard-blocks Deaf interpreters/trainees) -->
          <div class="mt-5">
            <label for="pfg-sli-crr" class="block text-sm font-bold text-[#202D59]">
              RCI CRR Number
              <span id="pfg-sli-crr-badge" class="ml-1.5 text-xs font-normal text-[#46537C]">(Optional — recommended if certified)</span>
            </label>
            <p id="pfg-sli-crr-help" class="mt-0.5 text-xs text-[#46537C]">
              Enter your Rehabilitation Council of India (RCI) CRR Number if issued. Not required for Deaf interpreters, community signers, or student trainees.
            </p>
            <input
              type="text"
              id="pfg-sli-crr"
              name="crrNumber"
              maxlength="40"
              aria-describedby="pfg-sli-crr-help"
              placeholder="e.g., A12345 (Optional)"
              class="touch-target mt-1.5 w-full max-w-md rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            />
          </div>

          <!-- Exact 4 Experience Bands -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Years of Interpretation Experience <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 grid gap-2.5 sm:grid-cols-4">
              <?php foreach (PFG_SLI_EXPERIENCE_BANDS as $eCode => $eLabel): ?>
                <label
                  for="pfg-sli-exp-<?= $esc(strtolower($eCode)) ?>"
                  class="touch-target flex cursor-pointer items-center gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="radio"
                    id="pfg-sli-exp-<?= $esc(strtolower($eCode)) ?>"
                    name="experienceBand"
                    value="<?= $esc($eCode) ?>"
                    required
                    class="h-5 w-5 accent-[#5B2A86]"
                  />
                  <span><?= $esc($eLabel) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Training Institute & Video Demo URL (Zero file uploads per D-15) -->
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-sli-institute" class="block text-sm font-bold text-[#202D59]">
                Training / Certification Institute <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <input
                type="text"
                id="pfg-sli-institute"
                name="trainingInstitute"
                maxlength="200"
                placeholder="e.g., ISLRTC, AYJNISHD, or recognized centre"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
            </div>

            <div>
              <label for="pfg-sli-video" class="block text-sm font-bold text-[#202D59]">
                Short Video Demonstration Link <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <input
                type="url"
                id="pfg-sli-video"
                name="videoDemoUrl"
                maxlength="500"
                placeholder="https://youtube.com/... or https://drive.google.com/..."
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
              <p class="mt-1 text-xs text-[#46537C]">Must be a valid http:// or https:// URL.</p>
            </div>
          </div>
        </section>

        <!-- Section 3: Availability, Public Stage Comfort & Logistics -->
        <section aria-labelledby="sli-sec3-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 3</p>
          <h2 id="sli-sec3-heading" class="mt-1 text-xl font-bold text-[#202D59]">Stage Comfort, Shift Availability &amp; Preferences</h2>

          <!-- Public Stage Comfort (Required Yes/No) -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Are you comfortable interpreting for cultural performances, panel discussions, and official ceremonies? <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 flex flex-wrap gap-3">
              <label for="pfg-sli-stage-yes" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-5 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]">
                <input type="radio" id="pfg-sli-stage-yes" name="comfortablePublicStage" value="yes" required class="h-5 w-5 accent-[#5B2A86]" />
                <span>Yes</span>
              </label>
              <label for="pfg-sli-stage-no" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-5 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]">
                <input type="radio" id="pfg-sli-stage-no" name="comfortablePublicStage" value="no" required class="h-5 w-5 accent-[#5B2A86]" />
                <span>No</span>
              </label>
            </div>
          </fieldset>

          <!-- Day × Shift Availability Grid -->
          <div class="mt-6">
            <?= pfgRenderShiftAvailabilityGrid('availabilityShifts', [], 'SLI') ?>
          </div>

          <!-- Areas of Expertise -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Areas of Interpreting Expertise <span class="font-normal text-[#46537C]">(Optional)</span>
            </legend>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
              <?php foreach (PFG_SLI_EXPERTISE_AREAS as $idx => $area): ?>
                <label for="pfg-sli-area-<?= $idx ?>" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm">
                  <input type="checkbox" id="pfg-sli-area-<?= $idx ?>" name="areasOfExpertise[]" value="<?= $esc($area) ?>" class="h-5 w-5 accent-[#5B2A86]" />
                  <span><?= $esc($area) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Language Pairs & Working Preferences -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Language Pairs &amp; Working Preferences <span class="font-normal text-[#46537C]">(Optional)</span>
            </legend>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
              <?php foreach (PFG_SLI_WORKING_PREFERENCES as $idx => $pref): ?>
                <label for="pfg-sli-pref-<?= $idx ?>" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm">
                  <input type="checkbox" id="pfg-sli-pref-<?= $idx ?>" name="interpretationPreferences[]" value="<?= $esc($pref) ?>" class="h-5 w-5 accent-[#5B2A86]" />
                  <span><?= $esc($pref) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Honorarium / Travel Support Notes -->
          <div class="mt-6">
            <label for="pfg-sli-honorarium" class="block text-sm font-bold text-[#202D59]">
              Honorarium / Travel Support Notes <span class="font-normal text-[#46537C]">(Optional)</span>
            </label>
            <p class="mt-0.5 text-xs text-[#46537C]">
              Please share any travel, outstation stay, or honorarium expectations. Support is subject to organizer policy and prior written confirmation.
            </p>
            <textarea
              id="pfg-sli-honorarium"
              name="honorariumOrTravelNotes"
              rows="3"
              maxlength="500"
              class="mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
            ></textarea>
          </div>
        </section>

        <!-- Section 4: Shared Accessibility & Accommodations Module -->
        <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, []) ?>

        <!-- Section 5: Emergency Contact (Required) -->
        <?= pfgRenderEmergencyContact($ecDefaults, true, true) ?>

        <!-- Section 6: Declarations & Consents -->
        <?= pfgRenderConsentDeclarations('SLI', []) ?>

        <!-- Submit Controls -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-[#202D59]/15 bg-white p-5">
          <div class="flex flex-wrap items-center gap-3">
            <a href="/apply" class="text-sm font-semibold text-[#46537C] underline hover:text-[#202D59]">Cancel</a>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              id="pfg-btn-review"
              class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-3 text-sm font-bold text-[#202D59] transition-colors hover:bg-[#DDDDF9]"
            >
              Review Information
            </button>
            <button
              type="submit"
              id="pfg-btn-submit"
              class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-7 py-3 text-base font-bold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-50"
            >
              Submit SLI Application
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
<script src="/assets/js/category-forms.js"></script>
HTML;
require_once __DIR__ . '/../includes/footer.php';
