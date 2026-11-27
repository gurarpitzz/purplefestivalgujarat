<?php
declare(strict_types=1);

/**
 * /apply/sli -> apply/sli.php
 * Purple Festival Gujarat 2027 — Dedicated Sign Language Interpreter (SLI) Application Form (4-Step Branded Flow)
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
$extraHeadHtml = (isset($extraHeadHtml) ? $extraHeadHtml . "\n" : '')
    . '  <link rel="stylesheet" href="/assets/css/form-wave-stepper.css?v=2" />';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="pfg-reg-shell focus:outline-none">
  <?= pfgRenderRegEdgeRibbons() ?>

  <div class="pfg-reg-wrap">
    <div class="pfg-reg-topbar">
      <nav aria-label="Breadcrumb">
        <a href="/apply" class="pfg-reg-back-hub">&larr; All registration categories</a>
      </nav>
      <p class="pfg-reg-meta-pill">Free application &middot; No account required</p>
    </div>

    <div class="pfg-reg-card">
      <?= pfgRenderRegCardHeader('SLI application') ?>

      <div class="pfg-reg-card__body">
        <?php if (!$registrationOpen): ?>
          <?= pfgRenderRegistrationClosedCard('Sign Language Interpreter (SLI) Application') ?>
        <?php else: ?>
          <!-- 4-Step Progress Indicator -->
          <?= pfgRenderWaveStepper([
              ['number' => 1, 'title' => 'Contact details'],
              ['number' => 2, 'title' => 'Interpreter profile'],
              ['number' => 3, 'title' => 'Qualifications & shifts'],
              ['number' => 4, 'title' => 'Accessibility & confirm'],
          ]) ?>

          <div
            id="pfg-form-error-summary"
            role="alert"
            aria-live="assertive"
            tabindex="-1"
            hidden
            class="mb-5 rounded-xl border-2 border-red-700 bg-red-50 p-4 text-sm text-red-950"
          ></div>

          <form id="pfg-category-form" novalidate data-wizard-form="true">
            <!-- STEP 1: Contact details -->
            <div class="pfg-wizard-step" data-wizard-step="1" data-step-title="Contact details">
              <?= pfgRenderApplicantDetails(false, 'SLI Application') ?>
            </div>

            <!-- STEP 2: Interpreter profile -->
            <div class="pfg-wizard-step" data-wizard-step="2" data-step-title="Interpreter profile" hidden>
              <section aria-labelledby="sli-sec1-heading">
                <h2 id="sli-sec1-heading" tabindex="-1" class="pfg-step-heading">Interpreter profile</h2>

                <div class="grid gap-4 sm:grid-cols-3">
                  <div data-field-wrap="age">
                    <label for="pfg-sli-age" class="pfg-field-label">
                      Age (min. 18) <span class="pfg-field-req" aria-hidden="true">*</span>
                    </label>
                    <input
                      type="number"
                      id="pfg-sli-age"
                      name="age"
                      min="18"
                      max="85"
                      required
                      placeholder="18–85"
                      class="pfg-input"
                    />
                  </div>

                  <div data-field-wrap="cityDistrict">
                    <label for="pfg-sli-city" class="pfg-field-label">
                      City / District <span class="pfg-field-req" aria-hidden="true">*</span>
                    </label>
                    <input
                      type="text"
                      id="pfg-sli-city"
                      name="cityDistrict"
                      maxlength="120"
                      required
                      value="<?= $esc($defaultCity) ?>"
                      placeholder="e.g., Vadodara"
                      class="pfg-input"
                    />
                  </div>

                  <div data-field-wrap="state">
                    <label for="pfg-sli-state" class="pfg-field-label">
                      State / Union Territory <span class="pfg-field-req" aria-hidden="true">*</span>
                    </label>
                    <select id="pfg-sli-state" name="state" required class="pfg-select">
                      <option value="">Select state / UT</option>
                      <?php foreach (PFG_INDIAN_STATES as $st): ?>
                        <option value="<?= $esc($st) ?>" <?= $defaultState === $st ? 'selected' : '' ?>><?= $esc($st) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- Gujarat Residency -->
                <fieldset class="mt-5 border-0 p-0" data-field-wrap="basedInGujarat">
                  <legend class="pfg-field-label">
                    Are you currently based in Gujarat? <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <p class="pfg-hint">
                    Priority is given to interpreters based in Gujarat; qualified interpreters from other states are warmly welcomed for coordinator review.
                  </p>
                  <div class="mt-2 grid gap-2.5 sm:grid-cols-2">
                    <label for="pfg-sli-guj-yes" class="pfg-choice-box">
                      <input type="radio" id="pfg-sli-guj-yes" name="basedInGujarat" value="yes" required />
                      <span>Yes, based in Gujarat</span>
                    </label>
                    <label for="pfg-sli-guj-no" class="pfg-choice-box">
                      <input type="radio" id="pfg-sli-guj-no" name="basedInGujarat" value="no" required />
                      <span>No, based outside Gujarat</span>
                    </label>
                  </div>
                </fieldset>

                <div id="pfg-sli-outstation-note" hidden role="note" class="mt-3 rounded-xl border border-[#5B2A86]/35 bg-[#F7F4FC] px-4 py-3 text-xs leading-relaxed text-[#202D59]">
                  <strong>Outstation / National Interpreter Application:</strong> Your application will be accepted and tagged for SLI Coordinator review. Feel free to share travel or accommodation notes on the next step.
                </div>

                <div class="mt-5" data-field-wrap="professionalAffiliation">
                  <label for="pfg-sli-aff" class="pfg-field-label">
                    Professional affiliation / Deaf association / independent <span class="pfg-field-opt">(optional)</span>
                  </label>
                  <input
                    type="text"
                    id="pfg-sli-aff"
                    name="professionalAffiliation"
                    maxlength="200"
                    placeholder="e.g., ISLRTC, Association of Sign Language Interpreters, Independent"
                    class="pfg-input"
                  />
                </div>
              </section>
            </div>

            <!-- STEP 3: Qualifications & shifts -->
            <div class="pfg-wizard-step" data-wizard-step="3" data-step-title="Qualifications &amp; shifts" hidden>
              <section aria-labelledby="sli-sec2-heading">
                <h2 id="sli-sec2-heading" tabindex="-1" class="pfg-step-heading">Qualifications &amp; shifts</h2>

                <!-- 4 Qualification Tiers -->
                <fieldset class="border-0 p-0" data-field-wrap="qualificationCategory">
                  <legend class="pfg-field-label">
                    Certification / qualification level in Sign Language <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 space-y-2">
                    <?php foreach (PFG_SLI_QUALIFICATION_TIERS as $qCode => $qLabel): ?>
                      <label for="pfg-sli-qual-<?= $esc(strtolower($qCode)) ?>" class="pfg-choice-box items-start">
                        <input
                          type="radio"
                          id="pfg-sli-qual-<?= $esc(strtolower($qCode)) ?>"
                          name="qualificationCategory"
                          value="<?= $esc($qCode) ?>"
                          required
                          class="mt-0.5"
                        />
                        <span><?= $esc($qLabel) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <div id="pfg-sli-qual-detail-wrap" class="mt-4" data-field-wrap="qualifications">
                  <label for="pfg-sli-qual-detail" class="pfg-field-label">
                    <span id="pfg-sli-qual-detail-label">Specific diploma / course (e.g., DISLI, DTISL) or background</span>
                    <span id="pfg-sli-qual-detail-star" class="pfg-field-req hidden" aria-hidden="true">*</span>
                  </label>
                  <input
                    type="text"
                    id="pfg-sli-qual-detail"
                    name="qualifications"
                    maxlength="200"
                    placeholder="Optional diploma, course, or community interpreting background"
                    class="pfg-input"
                  />
                </div>

                <!-- RCI CRR Number -->
                <div class="mt-4" data-field-wrap="crrNumber">
                  <label for="pfg-sli-crr" class="pfg-field-label">
                    RCI CRR number
                    <span id="pfg-sli-crr-badge" class="pfg-field-opt">(optional — recommended if certified)</span>
                  </label>
                  <input
                    type="text"
                    id="pfg-sli-crr"
                    name="crrNumber"
                    maxlength="40"
                    aria-describedby="pfg-sli-crr-help"
                    placeholder="e.g., A12345 (optional)"
                    class="pfg-input max-w-md"
                  />
                  <p id="pfg-sli-crr-help" class="pfg-hint">
                    Not required for Deaf interpreters, community signers, or student trainees.
                  </p>
                </div>

                <!-- 4 Experience Bands -->
                <fieldset class="mt-5 border-0 p-0" data-field-wrap="experienceBand">
                  <legend class="pfg-field-label">
                    Years of interpretation experience <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 grid gap-2.5 sm:grid-cols-4">
                    <?php foreach (PFG_SLI_EXPERIENCE_BANDS as $eCode => $eLabel): ?>
                      <label for="pfg-sli-exp-<?= $esc(strtolower($eCode)) ?>" class="pfg-choice-box">
                        <input
                          type="radio"
                          id="pfg-sli-exp-<?= $esc(strtolower($eCode)) ?>"
                          name="experienceBand"
                          value="<?= $esc($eCode) ?>"
                          required
                        />
                        <span><?= $esc($eLabel) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                  <div>
                    <label for="pfg-sli-institute" class="pfg-field-label">
                      Training / certification institute <span class="pfg-field-opt">(optional)</span>
                    </label>
                    <input
                      type="text"
                      id="pfg-sli-institute"
                      name="trainingInstitute"
                      maxlength="200"
                      placeholder="e.g., ISLRTC, AYJNISHD"
                      class="pfg-input"
                    />
                  </div>

                  <div data-field-wrap="videoDemoUrl">
                    <label for="pfg-sli-video" class="pfg-field-label">
                      Short video demonstration link <span class="pfg-field-opt">(optional)</span>
                    </label>
                    <input
                      type="url"
                      id="pfg-sli-video"
                      name="videoDemoUrl"
                      maxlength="500"
                      placeholder="https://youtube.com/... or https://drive.google.com/..."
                      class="pfg-input"
                    />
                    <p class="pfg-hint">Must be a valid http:// or https:// URL.</p>
                  </div>
                </div>

                <hr class="pfg-step-divider" />

                <!-- Public Stage Comfort -->
                <fieldset class="border-0 p-0" data-field-wrap="comfortablePublicStage">
                  <legend class="pfg-field-label">
                    Comfortable interpreting for cultural performances, panels, and official ceremonies? <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 flex flex-wrap gap-3">
                    <label for="pfg-sli-stage-yes" class="pfg-choice-box">
                      <input type="radio" id="pfg-sli-stage-yes" name="comfortablePublicStage" value="yes" required />
                      <span>Yes</span>
                    </label>
                    <label for="pfg-sli-stage-no" class="pfg-choice-box">
                      <input type="radio" id="pfg-sli-stage-no" name="comfortablePublicStage" value="no" required />
                      <span>No</span>
                    </label>
                  </div>
                </fieldset>

                <!-- Day × Shift Availability Grid -->
                <div class="mt-5" data-field-wrap="availabilityShifts">
                  <?= pfgRenderShiftAvailabilityGrid('availabilityShifts', [], 'SLI') ?>
                </div>

                <!-- Areas of Expertise -->
                <fieldset class="mt-5 border-0 p-0">
                  <legend class="pfg-field-label">
                    Areas of interpreting expertise <span class="pfg-field-opt">(optional)</span>
                  </legend>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach (PFG_SLI_EXPERTISE_AREAS as $idx => $area): ?>
                      <label for="pfg-sli-area-<?= $idx ?>" class="pfg-choice-box">
                        <input type="checkbox" id="pfg-sli-area-<?= $idx ?>" name="areasOfExpertise[]" value="<?= $esc($area) ?>" />
                        <span><?= $esc($area) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Language Pairs & Working Preferences -->
                <fieldset class="mt-5 border-0 p-0">
                  <legend class="pfg-field-label">
                    Language pairs &amp; working preferences <span class="pfg-field-opt">(optional)</span>
                  </legend>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach (PFG_SLI_WORKING_PREFERENCES as $idx => $pref): ?>
                      <label for="pfg-sli-pref-<?= $idx ?>" class="pfg-choice-box">
                        <input type="checkbox" id="pfg-sli-pref-<?= $idx ?>" name="interpretationPreferences[]" value="<?= $esc($pref) ?>" />
                        <span><?= $esc($pref) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Honorarium / Travel Support Notes -->
                <div class="mt-5">
                  <label for="pfg-sli-honorarium" class="pfg-field-label">
                    Honorarium / travel support notes <span class="pfg-field-opt">(optional)</span>
                  </label>
                  <p class="pfg-hint">
                    Support is subject to organizer policy and prior written confirmation.
                  </p>
                  <textarea
                    id="pfg-sli-honorarium"
                    name="honorariumOrTravelNotes"
                    rows="3"
                    maxlength="500"
                    class="pfg-textarea"
                  ></textarea>
                </div>
              </section>
            </div>

            <!-- STEP 4: Accessibility & confirm -->
            <div class="pfg-wizard-step" data-wizard-step="4" data-step-title="Accessibility &amp; confirm" hidden>
              <h2 id="sli-sec4-heading" tabindex="-1" class="pfg-step-heading">Accessibility &amp; confirm</h2>
              <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, []) ?>
              <?= pfgRenderEmergencyContact($ecDefaults, true, true) ?>
              <?= pfgRenderConsentDeclarations('SLI', []) ?>
            </div>

            <!-- Bottom Step Navigation & Submit Controls -->
            <div class="pfg-wizard-nav">
              <div class="pfg-wizard-nav__left">
                <a href="/apply" id="pfg-btn-cancel" class="text-sm font-semibold text-[#46537C] underline hover:text-[#202D59]">Cancel</a>
                <button type="button" id="pfg-btn-prev" class="pfg-wizard-btn-prev" hidden>
                  Back
                </button>
              </div>
              <div class="pfg-wizard-nav__right">
                <button type="button" id="pfg-btn-next" class="pfg-wizard-btn-next">
                  <span id="pfg-btn-next-label">Next: Interpreter profile</span>
                  <span aria-hidden="true">&rarr;</span>
                </button>
                <button
                  type="button"
                  id="pfg-btn-review"
                  hidden
                  class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59] transition-colors hover:bg-[#DDDDF9]"
                >
                  Review information
                </button>
                <button
                  type="submit"
                  id="pfg-btn-submit"
                  hidden
                  class="pfg-wizard-btn-next disabled:opacity-50"
                >
                  Submit SLI application
                </button>
              </div>
            </div>
          </form>

          <section id="pfg-review-panel" hidden tabindex="-1" aria-labelledby="pfg-review-heading"></section>

          <section id="pfg-confirmation-panel" hidden tabindex="-1" aria-live="polite"></section>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>
<?php
$bootJson = pfgSafeJsonForScript($bootData);
$extraFooterHtml = <<<HTML
<script>window.__PFG_FORM_BOOT__ = {$bootJson};</script>
<script src="/assets/js/shared-accessibility.js"></script>
<script src="/assets/js/category-forms.js?v=3"></script>
HTML;
require_once __DIR__ . '/../includes/footer.php';
