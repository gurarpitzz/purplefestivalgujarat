<?php
declare(strict_types=1);

/**
 * /apply/volunteer -> apply/volunteer.php
 * Purple Festival Gujarat 2027 — Dedicated Festival Volunteer Application Form (4-Step Branded Flow)
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
      <?= pfgRenderRegCardHeader('Volunteer application') ?>

      <div class="pfg-reg-card__body">
        <?php if (!$registrationOpen): ?>
          <?= pfgRenderRegistrationClosedCard('Festival Volunteer Application') ?>
        <?php else: ?>
          <!-- 4-Step Progress Indicator -->
          <?= pfgRenderWaveStepper([
              ['number' => 1, 'title' => 'Contact details'],
              ['number' => 2, 'title' => 'Volunteer profile'],
              ['number' => 3, 'title' => 'Roles & shifts'],
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
              <?= pfgRenderApplicantDetails(true, 'Volunteer Application') ?>
            </div>

            <!-- STEP 2: Volunteer profile -->
            <div class="pfg-wizard-step" data-wizard-step="2" data-step-title="Volunteer profile" hidden>
              <section aria-labelledby="vol-sec1-heading">
                <h2 id="vol-sec1-heading" tabindex="-1" class="pfg-step-heading">Volunteer profile</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                  <div data-field-wrap="age">
                    <label for="pfg-vol-age" class="pfg-field-label">
                      Age (min. 16) <span class="pfg-field-req" aria-hidden="true">*</span>
                    </label>
                    <input
                      type="number"
                      id="pfg-vol-age"
                      name="age"
                      min="16"
                      max="85"
                      required
                      placeholder="16–85"
                      class="pfg-input"
                    />
                  </div>

                  <div data-field-wrap="gender">
                    <label for="pfg-vol-gender" class="pfg-field-label">
                      Gender <span class="pfg-field-opt">(optional)</span>
                    </label>
                    <select id="pfg-vol-gender" name="gender" class="pfg-select">
                      <option value="">Select (optional)</option>
                      <?php foreach (PFG_GENDER_OPTIONS as $g): ?>
                        <option value="<?= $esc($g) ?>"><?= $esc($g) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- MSU Affiliation Enum -->
                <fieldset class="mt-5 border-0 p-0" data-field-wrap="msuAffiliation">
                  <legend class="pfg-field-label">
                    Are you a student or alumni of MSU Baroda? <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 grid gap-2.5 sm:grid-cols-3">
                    <?php foreach (PFG_VOLUNTEER_MSU_AFFILIATIONS as $mCode => $mLabel): ?>
                      <label for="pfg-msu-<?= $esc(strtolower($mCode)) ?>" class="pfg-choice-box items-start">
                        <input
                          type="radio"
                          id="pfg-msu-<?= $esc(strtolower($mCode)) ?>"
                          name="msuAffiliation"
                          value="<?= $esc($mCode) ?>"
                          required
                          class="mt-0.5"
                        />
                        <span><?= $esc($mLabel) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Conditional MSU Faculty / Department -->
                <div id="pfg-msu-faculty-wrap" hidden class="mt-4" data-field-wrap="msuFacultyDepartment">
                  <label for="pfg-msu-faculty" class="pfg-field-label">
                    MSU faculty / department name <span class="pfg-field-req" aria-hidden="true">*</span>
                  </label>
                  <input
                    type="text"
                    id="pfg-msu-faculty"
                    name="msuFacultyDepartment"
                    maxlength="150"
                    placeholder="e.g., Faculty of Social Work, Dept. of Physical Education"
                    class="pfg-input"
                  />
                </div>

                <!-- Conditional External Institution / NSS / NCC -->
                <div id="pfg-ext-inst-wrap" hidden class="mt-4">
                  <label for="pfg-ext-inst" class="pfg-field-label">
                    College / institution / NSS / NCC unit / organisation <span class="pfg-field-opt">(optional)</span>
                  </label>
                  <input
                    type="text"
                    id="pfg-ext-inst"
                    name="institutionName"
                    maxlength="200"
                    placeholder="Optional college, NSS/NCC unit, NGO, or workplace"
                    class="pfg-input"
                  />
                </div>

                <!-- Strictly Optional RCI CRR Number -->
                <div class="mt-5">
                  <label for="pfg-vol-crr" class="pfg-field-label">
                    RCI CRR number <span class="pfg-field-opt">(optional — only if registered rehabilitation professional)</span>
                  </label>
                  <input
                    type="text"
                    id="pfg-vol-crr"
                    name="crrNumber"
                    maxlength="40"
                    aria-describedby="pfg-vol-crr-help"
                    placeholder="Optional"
                    class="pfg-input max-w-md"
                  />
                  <p id="pfg-vol-crr-help" class="pfg-hint">
                    Leave blank if not applicable — students, NSS/NCC, and community volunteers do not need a CRR number.
                  </p>
                </div>
              </section>
            </div>

            <!-- STEP 3: Roles & shifts -->
            <div class="pfg-wizard-step" data-wizard-step="3" data-step-title="Roles &amp; shifts" hidden>
              <section aria-labelledby="vol-sec2-heading">
                <h2 id="vol-sec2-heading" tabindex="-1" class="pfg-step-heading">Roles &amp; shifts</h2>

                <!-- 5 Primary Roles -->
                <fieldset class="border-0 p-0" data-field-wrap="primaryRole">
                  <legend class="pfg-field-label">
                    Primary role interested in <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 space-y-2">
                    <?php foreach (PFG_VOLUNTEER_PRIMARY_ROLES as $idx => $roleLabel): ?>
                      <label for="pfg-primary-role-<?= $idx ?>" class="pfg-choice-box">
                        <input
                          type="radio"
                          id="pfg-primary-role-<?= $idx ?>"
                          name="primaryRole"
                          value="<?= $esc($roleLabel) ?>"
                          required
                        />
                        <span><?= $esc($roleLabel) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Conditional Anchoring Experience -->
                <div id="pfg-anchoring-exp-wrap" hidden class="mt-4 rounded-xl border-2 border-[#5B2A86]/35 bg-[#F7F4FC] p-4" data-field-wrap="anchoringExperience">
                  <label for="pfg-anchoring-exp" class="pfg-field-label">
                    Prior public speaking or stage hosting experience <span class="pfg-field-req" aria-hidden="true">*</span>
                  </label>
                  <p class="pfg-hint">
                    Briefly describe any prior public speaking, emceeing, or stage hosting experience (10–1,000 characters).
                  </p>
                  <textarea
                    id="pfg-anchoring-exp"
                    name="anchoringExperience"
                    rows="3"
                    maxlength="1000"
                    class="pfg-textarea"
                  ></textarea>
                </div>

                <!-- Optional Secondary Roles -->
                <fieldset class="mt-5 border-0 p-0">
                  <legend class="pfg-field-label">
                    Other roles you are open to helping with <span class="pfg-field-opt">(optional)</span>
                  </legend>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach (array_merge(PFG_VOLUNTEER_PRIMARY_ROLES, ['Para-Sports Support']) as $idx => $sRole): ?>
                      <label for="pfg-sec-role-<?= $idx ?>" class="pfg-choice-box">
                        <input type="checkbox" id="pfg-sec-role-<?= $idx ?>" name="secondaryRoles[]" value="<?= $esc($sRole) ?>" />
                        <span><?= $esc($sRole) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Optional Relevant Experience -->
                <div class="mt-5">
                  <label for="pfg-vol-rel-exp" class="pfg-field-label">
                    Relevant volunteering or inclusion experience <span class="pfg-field-opt">(optional)</span>
                  </label>
                  <textarea
                    id="pfg-vol-rel-exp"
                    name="relevantExperience"
                    rows="3"
                    maxlength="1000"
                    placeholder="Any prior festival, NSS/NCC, community, or disability inclusion volunteering..."
                    class="pfg-textarea"
                  ></textarea>
                </div>

                <!-- Languages Spoken -->
                <fieldset class="mt-5 border-0 p-0" data-field-wrap="languagesSpoken">
                  <legend class="pfg-field-label">
                    Languages spoken <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <p class="pfg-hint">Select all that apply.</p>
                  <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    <?php foreach (PFG_VOLUNTEER_LANGUAGES as $idx => $lang): ?>
                      <label for="pfg-lang-<?= $idx ?>" class="pfg-choice-box">
                        <input type="checkbox" id="pfg-lang-<?= $idx ?>" name="languagesSpoken[]" value="<?= $esc($lang) ?>" />
                        <span><?= $esc($lang) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <div id="pfg-lang-other-wrap" hidden class="mt-3">
                  <label for="pfg-lang-other" class="pfg-field-label">Specify other language(s)</label>
                  <input type="text" id="pfg-lang-other" name="languagesOther" maxlength="80" class="pfg-input max-w-md" />
                </div>

                <hr class="pfg-step-divider" />

                <!-- Day × Shift Availability Grid -->
                <h3 class="pfg-sub-heading">Shift availability</h3>
                <div data-field-wrap="availabilityShifts">
                  <?= pfgRenderShiftAvailabilityGrid('availabilityShifts', [], 'VOLUNTEER') ?>
                </div>
              </section>
            </div>

            <!-- STEP 4: Accessibility & confirm -->
            <div class="pfg-wizard-step" data-wizard-step="4" data-step-title="Accessibility &amp; confirm" hidden>
              <h2 id="vol-sec4-heading" tabindex="-1" class="pfg-step-heading">Accessibility &amp; confirm</h2>
              <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, []) ?>
              <?= pfgRenderEmergencyContact($ecDefaults, true, true) ?>
              <?= pfgRenderConsentDeclarations('VOLUNTEER', []) ?>
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
                  <span id="pfg-btn-next-label">Next: Volunteer profile</span>
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
                  Submit volunteer application
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
<script src="/assets/js/category-forms.js?v=4"></script>
HTML;
require_once __DIR__ . '/../includes/footer.php';
