<?php
declare(strict_types=1);

/**
 * /apply/attendee -> apply/attendee.php
 * Purple Festival Gujarat 2027 — Dedicated Attendee Registration Form (4-Step Branded Flow)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration-validators.php';
require_once __DIR__ . '/../includes/form-components/applicant-details.php';
require_once __DIR__ . '/../includes/form-components/wave-stepper.php';
require_once __DIR__ . '/../includes/form-components/shared-accessibility.php';
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
    'categoryCode'     => 'ATTENDEE',
    'categoryName'     => 'Attendee',
    'registrationOpen' => $registrationOpen,
    'csrfToken'        => $registrationOpen ? getCsrfToken() : '',
];

$pageTitle = 'Attendee Registration | Purple Festival Gujarat 2027';
$pageDescription = 'Register as an Attendee for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara). Free accountless registration with integrated Accessibility & Accommodations support.';
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
      <p class="pfg-reg-meta-pill">Free registration &middot; No account required</p>
    </div>

    <div class="pfg-reg-card">
      <?= pfgRenderRegCardHeader('Attendee registration') ?>

      <div class="pfg-reg-card__body">
        <?php if (!$registrationOpen): ?>
          <?= pfgRenderRegistrationClosedCard('Attendee Registration') ?>
        <?php else: ?>
          <!-- 4-Step Progress Indicator -->
          <?= pfgRenderWaveStepper([
              ['number' => 1, 'title' => 'Contact details'],
              ['number' => 2, 'title' => 'About you'],
              ['number' => 3, 'title' => 'Festival & travel'],
              ['number' => 4, 'title' => 'Accessibility & confirm'],
          ]) ?>

          <!-- Error Summary Region -->
          <div
            id="pfg-form-error-summary"
            role="alert"
            aria-live="assertive"
            tabindex="-1"
            hidden
            class="mb-5 rounded-xl border-2 border-red-700 bg-red-50 p-4 text-sm text-red-950"
          ></div>

          <!-- Registration Form (4-Step Flow) -->
          <form id="pfg-category-form" novalidate data-wizard-form="true">
            <!-- STEP 1: Contact details -->
            <div class="pfg-wizard-step" data-wizard-step="1" data-step-title="Contact details">
              <?= pfgRenderApplicantDetails(true, 'Attendee Registration') ?>
            </div>

            <!-- STEP 2: About you -->
            <div class="pfg-wizard-step" data-wizard-step="2" data-step-title="About you" hidden>
              <section aria-labelledby="att-sec1-heading">
                <h2 id="att-sec1-heading" tabindex="-1" class="pfg-step-heading">About you</h2>

                <!-- Age Group -->
                <fieldset class="border-0 p-0" data-field-wrap="ageGroup">
                  <legend class="pfg-field-label">
                    Age group <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 grid gap-2.5 sm:grid-cols-4">
                    <?php foreach (PFG_ATTENDEE_AGE_GROUPS as $agCode => $agLabel): ?>
                      <label for="pfg-age-group-<?= $esc(strtolower($agCode)) ?>" class="pfg-choice-box">
                        <input
                          type="radio"
                          id="pfg-age-group-<?= $esc(strtolower($agCode)) ?>"
                          name="ageGroup"
                          value="<?= $esc($agCode) ?>"
                          required
                        />
                        <span><?= $esc($agLabel) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Gender -->
                <div class="mt-4" data-field-wrap="gender">
                  <label for="pfg-att-gender" class="pfg-field-label">
                    Gender <span class="pfg-field-opt">(optional)</span>
                  </label>
                  <select id="pfg-att-gender" name="gender" class="pfg-select">
                    <option value="">Select (optional)</option>
                    <?php foreach (PFG_GENDER_OPTIONS as $g): ?>
                      <option value="<?= $esc($g) ?>"><?= $esc($g) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Registering for someone else -->
                <div class="mt-3">
                  <label for="pfg-registering-behalf" class="pfg-choice-box">
                    <input
                      type="checkbox"
                      id="pfg-registering-behalf"
                      name="registeringOnBehalf"
                      value="1"
                    />
                    <span>I&rsquo;m registering for someone else</span>
                  </label>
                </div>

                <!-- Conditional Parent / Legal Guardian Block -->
                <div id="pfg-guardian-panel" hidden class="mt-4 rounded-xl border-2 border-[#5B2A86]/35 bg-[#F7F4FC] p-4">
                  <h3 class="text-sm font-bold text-[#2B144D]">Parent / legal guardian / representative details</h3>
                  <p class="mt-0.5 text-xs text-[#46537C]">Required for attendees under 18 years of age or assisted registrations.</p>
                  <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div data-field-wrap="guardianName">
                      <label for="pfg-guardian-name" class="pfg-field-label">
                        Guardian full name <span class="pfg-field-req" aria-hidden="true">*</span>
                      </label>
                      <input
                        type="text"
                        id="pfg-guardian-name"
                        name="guardianName"
                        maxlength="150"
                        class="pfg-input"
                      />
                    </div>
                    <div data-field-wrap="guardianRelationship">
                      <label for="pfg-guardian-rel" class="pfg-field-label">
                        Relationship <span class="pfg-field-req" aria-hidden="true">*</span>
                      </label>
                      <select id="pfg-guardian-rel" name="guardianRelationship" class="pfg-select">
                        <option value="">Select relationship</option>
                        <?php foreach (PFG_GUARDIAN_RELATIONSHIPS as $gr): ?>
                          <option value="<?= $esc($gr) ?>"><?= $esc($gr) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div data-field-wrap="guardianPhone">
                      <label for="pfg-guardian-phone" class="pfg-field-label">
                        Guardian phone <span class="pfg-field-opt">(optional)</span>
                      </label>
                      <input
                        type="tel"
                        id="pfg-guardian-phone"
                        name="guardianPhone"
                        maxlength="20"
                        placeholder="+91 98250 12345"
                        class="pfg-input"
                      />
                    </div>
                  </div>
                </div>

                <!-- Attendance Roles -->
                <fieldset class="mt-5 border-0 p-0" data-field-wrap="selfIdentificationTags">
                  <legend class="pfg-field-label">
                    I am attending as <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <p class="pfg-hint">Select all that apply.</p>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach (PFG_ATTENDEE_SELF_ID_TAGS as $idx => $tag): ?>
                      <label for="pfg-self-tag-<?= $idx ?>" class="pfg-choice-box items-start">
                        <input
                          type="checkbox"
                          id="pfg-self-tag-<?= $idx ?>"
                          name="selfIdentificationTags[]"
                          value="<?= $esc($tag) ?>"
                          class="mt-0.5"
                        />
                        <span><?= $esc($tag) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Organisation & Designation -->
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                  <div data-field-wrap="organizationName">
                    <label for="pfg-att-org" class="pfg-field-label">
                      Organisation / school <span class="pfg-field-opt">(optional)</span>
                    </label>
                    <input
                      type="text"
                      id="pfg-att-org"
                      name="organizationName"
                      maxlength="200"
                      class="pfg-input"
                    />
                  </div>
                  <div data-field-wrap="designation">
                    <label for="pfg-att-desig" class="pfg-field-label">
                      Designation / class or course <span class="pfg-field-opt">(optional)</span>
                    </label>
                    <input
                      type="text"
                      id="pfg-att-desig"
                      name="designation"
                      maxlength="150"
                      class="pfg-input"
                    />
                  </div>
                </div>
              </section>
            </div>

            <!-- STEP 3: Festival & travel -->
            <div class="pfg-wizard-step" data-wizard-step="3" data-step-title="Festival &amp; travel" hidden>
              <section aria-labelledby="att-sec2-heading">
                <h2 id="att-sec2-heading" tabindex="-1" class="pfg-step-heading">Festival &amp; travel</h2>

                <!-- Festival Days -->
                <fieldset class="border-0 p-0" data-field-wrap="attendingDays">
                  <legend class="pfg-field-label">
                    Festival days I will attend <span class="pfg-field-req" aria-hidden="true">*</span>
                  </legend>
                  <div class="mt-2 grid gap-2.5 sm:grid-cols-3">
                    <?php
                    $dayOptions = [
                        'DAY_1' => 'Day 1 — Thu, 21 Jan 2027',
                        'DAY_2' => 'Day 2 — Fri, 22 Jan 2027',
                        'DAY_3' => 'Day 3 — Sat, 23 Jan 2027',
                    ];
                    foreach ($dayOptions as $dCode => $dLabel):
                    ?>
                      <label for="pfg-day-<?= $esc(strtolower($dCode)) ?>" class="pfg-choice-box">
                        <input
                          type="checkbox"
                          id="pfg-day-<?= $esc(strtolower($dCode)) ?>"
                          name="attendingDays[]"
                          value="<?= $esc($dCode) ?>"
                        />
                        <span><?= $esc($dLabel) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Interested Tracks -->
                <fieldset class="mt-5 border-0 p-0">
                  <legend class="pfg-field-label">
                    Events &amp; sessions I want to attend <span class="pfg-field-opt">(optional)</span>
                  </legend>
                  <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <?php foreach (PFG_ATTENDEE_TRACKS as $idx => $trk): ?>
                      <label for="pfg-track-<?= $idx ?>" class="pfg-choice-box items-start">
                        <input
                          type="checkbox"
                          id="pfg-track-<?= $idx ?>"
                          name="interestedTracks[]"
                          value="<?= $esc($trk) ?>"
                          class="mt-0.5"
                        />
                        <span><?= $esc($trk) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </fieldset>

                <!-- Para-Sports Athlete Interest -->
                <fieldset class="mt-5 border-0 p-0">
                  <legend class="pfg-field-label">
                    Interested in taking part in Para-Sports as an athlete? <span class="pfg-field-opt">(optional)</span>
                  </legend>
                  <p class="pfg-hint">
                    Separate eligibility and classification rules apply; the MSU Department of Physical Education sports team will contact interested athletes separately.
                  </p>
                  <div class="mt-2 flex flex-wrap gap-3">
                    <label for="pfg-parasports-yes" class="pfg-choice-box">
                      <input type="radio" id="pfg-parasports-yes" name="paraSportsAthlete" value="yes" />
                      <span>Yes</span>
                    </label>
                    <label for="pfg-parasports-no" class="pfg-choice-box">
                      <input type="radio" id="pfg-parasports-no" name="paraSportsAthlete" value="no" />
                      <span>No</span>
                    </label>
                  </div>
                </fieldset>

                <div id="pfg-parasports-panel" hidden class="mt-4 rounded-xl border border-[#DCE5F7] bg-[#FAF9FF] p-4">
                  <p class="text-xs font-bold uppercase tracking-wide text-[#5B2A86]">Select Para-Sports events of interest</p>
                  <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
                    <?php foreach (PFG_PARA_SPORTS_EVENTS as $idx => $ev): ?>
                      <label for="pfg-ps-ev-<?= $idx ?>" class="pfg-choice-box">
                        <input type="checkbox" id="pfg-ps-ev-<?= $idx ?>" name="paraSportsEvents[]" value="<?= $esc($ev) ?>" />
                        <span><?= $esc($ev) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <div class="mt-3">
                    <label for="pfg-ps-other" class="pfg-field-label">Other sport <span class="pfg-field-opt">(optional)</span></label>
                    <input type="text" id="pfg-ps-other" name="paraSportsOther" maxlength="120" class="pfg-input max-w-md" />
                  </div>
                </div>

                <hr class="pfg-step-divider" />

                <!-- Outstation Travel, Stay & Food Preferences -->
                <h3 class="pfg-sub-heading">Travel, stay &amp; food <span class="pfg-field-opt text-sm">(optional)</span></h3>

                <label for="pfg-outstation-toggle" class="pfg-choice-box">
                  <input type="checkbox" id="pfg-outstation-toggle" name="outstationTravelRequired" value="1" />
                  <span>I am travelling from outside Vadodara</span>
                </label>
                <p class="pfg-hint">
                  Travel and accommodation support, if any, is limited and confirmed separately by the organizers.
                </p>

                <div id="pfg-outstation-panel" hidden class="mt-4 space-y-4 rounded-xl border border-[#DCE5F7] bg-[#FAF9FF] p-4">
                  <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label for="pfg-arrival-at" class="pfg-field-label">Arriving on (date &amp; approx. time)</label>
                      <input type="datetime-local" id="pfg-arrival-at" name="arrivalAt" class="pfg-input" />
                    </div>
                    <div>
                      <label for="pfg-departure-at" class="pfg-field-label">Departing on (date &amp; approx. time)</label>
                      <input type="datetime-local" id="pfg-departure-at" name="departureAt" class="pfg-input" />
                    </div>
                  </div>
                  <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label for="pfg-travel-mode" class="pfg-field-label">Mode of travel</label>
                      <select id="pfg-travel-mode" name="travelMode" class="pfg-select">
                        <option value="">Select mode (optional)</option>
                        <?php foreach (PFG_TRAVEL_MODES as $tm): ?>
                          <option value="<?= $esc($tm) ?>"><?= $esc($tm) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div>
                      <label for="pfg-accompanying-count" class="pfg-field-label">People travelling with you (0–10)</label>
                      <input type="number" id="pfg-accompanying-count" name="accompanyingCount" min="0" max="10" class="pfg-input" />
                    </div>
                  </div>

                  <div class="space-y-2.5">
                    <label for="pfg-lodging-needed" class="pfg-choice-box">
                      <input type="checkbox" id="pfg-lodging-needed" name="lodgingNeeded" value="1" />
                      <span>I would like accommodation / stay guidance</span>
                    </label>

                    <div id="pfg-accessible-room-wrap" hidden class="pl-6">
                      <label for="pfg-accessible-room" class="pfg-choice-box">
                        <input type="checkbox" id="pfg-accessible-room" name="accessibleRoomNeeded" value="1" />
                        <span>Accessible room needed (restricted to Accessibility Coordinators)</span>
                      </label>
                    </div>
                  </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                  <div>
                    <label for="pfg-food-pref" class="pfg-field-label">
                      Food preference <span class="pfg-field-opt">(optional)</span>
                    </label>
                    <select id="pfg-food-pref" name="foodPreference" class="pfg-select">
                      <option value="">Select (optional)</option>
                      <?php foreach (PFG_FOOD_PREFERENCES as $fp): ?>
                        <option value="<?= $esc($fp) ?>"><?= $esc($fp) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label for="pfg-dietary-reqs" class="pfg-field-label">
                      Dietary needs or allergies <span class="pfg-field-opt">(optional, encrypted)</span>
                    </label>
                    <input type="text" id="pfg-dietary-reqs" name="dietaryRequirements" maxlength="300" placeholder="e.g., Nut allergy, gluten-free" class="pfg-input" />
                  </div>
                </div>

                <p class="mt-4 text-xs text-[#46537C]">
                  Looking to perform, host a stall, or partner as a sponsor/speaker? Email
                  <a href="mailto:purplefestivalvadodara@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalvadodara@gmail.com</a>.
                </p>
              </section>
            </div>

            <!-- STEP 4: Accessibility & confirm -->
            <div class="pfg-wizard-step" data-wizard-step="4" data-step-title="Accessibility &amp; confirm" hidden>
              <h2 id="att-sec4-heading" tabindex="-1" class="pfg-step-heading">Accessibility &amp; confirm</h2>
              <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, []) ?>
              <?= pfgRenderEmergencyContact($ecDefaults, false, false) ?>
              <?= pfgRenderConsentDeclarations('ATTENDEE', []) ?>
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
                  <span id="pfg-btn-next-label">Next: About you</span>
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
                  Complete free attendee registration
                </button>
              </div>
            </div>
          </form>

          <!-- Review Summary Panel -->
          <section id="pfg-review-panel" hidden tabindex="-1" aria-labelledby="pfg-review-heading"></section>

          <!-- Confirmation Panel -->
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
