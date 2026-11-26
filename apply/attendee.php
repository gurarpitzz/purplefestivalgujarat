<?php
declare(strict_types=1);

/**
 * /apply/attendee -> apply/attendee.php
 * Purple Festival Gujarat 2027 — Dedicated Attendee Registration Form (Phase 6)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/registration-validators.php';
require_once __DIR__ . '/../includes/form-components/account-identity-card.php';
require_once __DIR__ . '/../includes/form-components/shared-accessibility.php';
require_once __DIR__ . '/../includes/form-components/emergency-contact.php';
require_once __DIR__ . '/../includes/form-components/consent-declarations.php';

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$pdo = getDb();
$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['id'])) {
    header('Location: /login?next=' . rawurlencode('/apply/attendee'));
    exit;
}
enforceStaffMfaGate($currentUser);

$userId = (string)$currentUser['id'];
$stmtUser = $pdo->prepare(
    'SELECT id, email, email_verified_at, full_name, phone, country, city_district, state, preferred_language,
            emergency_contact_name, emergency_contact_relationship, emergency_contact_phone
     FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1'
);
$stmtUser->execute([':id' => $userId]);
$userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
if (!$userRow) {
    header('Location: /login?next=' . rawurlencode('/apply/attendee'));
    exit;
}

$emailVerified = !empty($userRow['email_verified_at']);

// Load edition, disability categories, accommodation types
$stmtEd = $pdo->prepare('SELECT id FROM festival_editions WHERE slug = :slug LIMIT 1');
$stmtEd->execute([':slug' => defined('FESTIVAL_EDITION_SLUG') ? FESTIVAL_EDITION_SLUG : 'gujarat-2027']);
$editionId = (string)($stmtEd->fetchColumn() ?: '');

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

// Check edit mode or existing active registration
$editRegId = isset($_GET['edit']) && is_string($_GET['edit']) ? trim($_GET['edit']) : '';
$mode = 'create';
$existingActive = null;
$initialData = [];

$stmtAct = $pdo->prepare(
    "SELECT * FROM registrations
     WHERE user_id = :uid AND UPPER(category_code) = 'ATTENDEE' AND deleted_at IS NULL
       AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
     ORDER BY created_at DESC LIMIT 1"
);
$stmtAct->execute([':uid' => $userId]);
$existingActive = $stmtAct->fetch(PDO::FETCH_ASSOC) ?: null;

$editableStatuses = ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED', 'CONFIRMED'];
$existingIsEditable = $existingActive
    && in_array(strtoupper((string)$existingActive['status']), $editableStatuses, true);

if ($editRegId !== '' && $existingIsEditable && (string)$existingActive['id'] === $editRegId) {
    $mode = 'edit';
    $stmtAd = $pdo->prepare('SELECT * FROM attendee_details WHERE registration_id = :rid LIMIT 1');
    $stmtAd->execute([':rid' => $editRegId]);
    $adRow = $stmtAd->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmtDisOwn = $pdo->prepare('SELECT disability_code, self_description FROM participant_disability_disclosures WHERE registration_id = :rid');
    $stmtDisOwn->execute([':rid' => $editRegId]);
    $disCodes = [];
    $disSelf = '';
    foreach ($stmtDisOwn->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dr) {
        if (!empty($dr['disability_code'])) {
            $disCodes[] = (string)$dr['disability_code'];
        }
        if ($disSelf === '' && !empty($dr['self_description'])) {
            $disSelf = (string)pfgDecryptSensitiveValue((string)$dr['self_description']);
        }
    }

    $stmtAccOwn = $pdo->prepare('SELECT * FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC LIMIT 1');
    $stmtAccOwn->execute([':rid' => $editRegId]);
    $accRow = $stmtAccOwn->fetch(PDO::FETCH_ASSOC) ?: [];

    $initialData = [
        'organizationName'             => $existingActive['organization_name'] ?? '',
        'designation'                  => $existingActive['designation'] ?? '',
        'attendingDays'                => jsonDecode($existingActive['attending_days'] ?? '[]', []),
        'interestedTracks'             => jsonDecode($existingActive['interested_tracks'] ?? '[]', []),
        'emergencyContactName'         => $existingActive['emergency_contact_name'] ?? ($userRow['emergency_contact_name'] ?? ''),
        'emergencyContactRelationship' => $existingActive['emergency_contact_relationship'] ?? ($userRow['emergency_contact_relationship'] ?? ''),
        'emergencyContactPhone'        => $existingActive['emergency_contact_phone'] ?? ($userRow['emergency_contact_phone'] ?? ''),
        'isPersonWithDisability'       => $existingActive['is_person_with_disability'] ?? '',
        'disabilityCategoryCodes'      => $disCodes,
        'disabilitySelfDescription'    => $disSelf,
        'accommodationTypes'           => jsonDecode($accRow['selected_types'] ?? '[]', []),
        'accommodationFreeText'        => (string)(pfgDecryptSensitiveValue($accRow['free_text_details'] ?? $accRow['notes'] ?? null) ?? ''),
        'escortAccompanying'           => !empty($accRow['escort_accompanying']),
        'escortName'                   => (string)(pfgDecryptSensitiveValue($accRow['escort_name_encrypted'] ?? null) ?? ''),
        'attendeeDetails'              => [
            'ageGroup'                 => $adRow['age_group'] ?? '',
            'gender'                   => $adRow['gender'] ?? '',
            'selfIdentificationTags'   => jsonDecode($adRow['self_identification_tags'] ?? '[]', []),
            'guardianName'             => $adRow['guardian_name'] ?? '',
            'guardianRelationship'     => $adRow['guardian_relationship'] ?? '',
            'guardianPhone'            => $adRow['guardian_phone'] ?? '',
            'paraSportsAthlete'        => !empty($adRow['para_sports_athlete']),
            'paraSportsEvents'         => jsonDecode($adRow['para_sports_events'] ?? '[]', []),
            'paraSportsOther'          => $adRow['para_sports_other'] ?? '',
            'outstationTravelRequired' => !empty($adRow['outstation_travel_required']),
            'arrivalAt'                => $adRow['arrival_at'] ?? '',
            'departureAt'              => $adRow['departure_at'] ?? '',
            'travelMode'               => $adRow['travel_mode'] ?? '',
            'lodgingNeeded'            => !empty($adRow['lodging_needed']),
            'accessibleRoomNeeded'     => !empty($adRow['accessible_room_needed']),
            'accompanyingCount'        => $adRow['accompanying_count'] ?? '',
            'foodPreference'           => $adRow['food_preference'] ?? '',
            'dietaryRequirements'      => (string)(pfgDecryptSensitiveValue($adRow['dietary_requirements_encrypted'] ?? null) ?? ''),
        ],
    ];
} elseif ($editionId !== '' && !$existingActive) {
    $stmtDr = $pdo->prepare(
        "SELECT payload_json FROM registration_drafts WHERE edition_id = :eid AND user_id = :uid AND category_code = 'ATTENDEE' LIMIT 1"
    );
    $stmtDr->execute([':eid' => $editionId, ':uid' => $userId]);
    $rawDraft = $stmtDr->fetchColumn();
    if (is_string($rawDraft) && $rawDraft !== '') {
        $decoded = jsonDecode($rawDraft, []);
        if (is_array($decoded)) {
            $initialData = pfgDecryptDraftPayload($decoded);
        }
    }
}

$ecDefaults = [
    'emergencyContactName'         => $initialData['emergencyContactName'] ?? ($userRow['emergency_contact_name'] ?? ''),
    'emergencyContactRelationship' => $initialData['emergencyContactRelationship'] ?? ($userRow['emergency_contact_relationship'] ?? ''),
    'emergencyContactPhone'        => $initialData['emergencyContactPhone'] ?? ($userRow['emergency_contact_phone'] ?? ''),
];

$bootData = [
    'categoryCode'   => 'ATTENDEE',
    'categoryName'   => 'Attendee',
    'mode'           => $mode,
    'registrationId' => $mode === 'edit' ? $editRegId : null,
    'csrfToken'      => getCsrfToken(),
    'initialDraft'   => $initialData,
];

$pageTitle = ($mode === 'edit' ? 'Edit Attendee Registration' : 'Attendee Registration') . ' | Purple Festival Gujarat 2027';
$pageDescription = 'Register as an Attendee for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara). Free registration with integrated Accessibility & Accommodations support.';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[900px] px-5 py-10 sm:px-8 sm:py-14">
    <nav aria-label="Breadcrumb" class="mb-5 text-sm text-[#46537C]">
      <a href="/apply" class="font-semibold text-[#5B2A86] underline hover:text-[#202D59]">&larr; All Registration Categories</a>
    </nav>

    <header class="mb-7">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        Primary Category 1 of 3 · Free · Instant Registration ID
      </span>
      <h1 class="mt-2 text-[clamp(1.65rem,3.2vw,2.35rem)] font-bold tracking-[-0.02em] text-[#202D59]">
        <?= $mode === 'edit' ? 'Update Your Attendee Registration' : 'Attendee Registration' ?>
      </h1>
      <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
        For visitors, families, students, professionals, educators, institutions, athletes, allies, and persons with disabilities attending Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).
      </p>
    </header>

    <?php if (!$emailVerified): ?>
      <div role="alert" class="rounded-2xl border-2 border-amber-600/40 bg-amber-50 p-6">
        <h2 class="text-lg font-bold text-amber-950">Please Verify Your Email Address First</h2>
        <p class="mt-2 text-sm text-amber-900">
          Your account email (<strong><?= $esc($userRow['email']) ?></strong>) must be verified before submitting an Attendee registration.
        </p>
        <a href="/verify-email" class="touch-target mt-4 inline-flex items-center justify-center rounded-lg bg-[#202D59] px-5 py-2.5 text-sm font-bold text-white">
          Go to Email Verification
        </a>
      </div>
    <?php elseif ($existingActive && $mode === 'create'): ?>
      <div class="rounded-2xl border-2 border-emerald-700/35 bg-white p-6 shadow-xs">
        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">
          Already Registered · <?= $esc($existingActive['status']) ?>
        </span>
        <h2 class="mt-3 text-xl font-bold text-[#202D59]">You Already Hold an Active Attendee Registration</h2>
        <p class="mt-2 text-sm text-[#46537C]">
          Your official Registration ID is <strong class="font-mono text-[#202D59]"><?= $esc($existingActive['registration_code']) ?></strong>.
          You can view your registration, update accommodations, or edit details from your Participant Dashboard — or apply separately as a Festival Volunteer or Sign Language Interpreter.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <a href="/dashboard" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#202D59] px-5 py-2.5 text-sm font-bold text-white">
            Open Participant Dashboard
          </a>
          <?php if ($existingIsEditable): ?>
          <a href="/apply/attendee?edit=<?= rawurlencode((string)$existingActive['id']) ?>" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59]">
            Edit Attendee Registration
          </a>
          <?php endif; ?>
          <a href="/apply" class="touch-target inline-flex items-center justify-center rounded-lg border border-[#DCE5F7] bg-[#F6F5FE] px-5 py-2.5 text-sm font-semibold text-[#202D59]">
            Explore Volunteer / SLI Categories
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- Error Summary Region -->
      <div
        id="pfg-form-error-summary"
        role="alert"
        aria-live="assertive"
        tabindex="-1"
        hidden
        class="mb-6 rounded-2xl border-2 border-red-600 bg-red-50 p-5 text-sm text-red-950"
      ></div>

      <!-- Draft Status Live Region -->
      <div id="pfg-draft-status" role="status" aria-live="polite" class="mb-4 text-xs font-semibold text-[#46537C]"></div>

      <!-- Registration Form -->
      <form id="pfg-category-form" novalidate class="space-y-7">
        <!-- Section 0: Account Identity Summary -->
        <?= pfgRenderAccountIdentityCard($userRow) ?>

        <!-- Section 1: About You & Minor Safeguarding -->
        <section aria-labelledby="att-sec1-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 1</p>
          <h2 id="att-sec1-heading" class="mt-1 text-xl font-bold text-[#202D59]">About You &amp; Minor Safeguarding</h2>

          <!-- Age Group -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Age Group <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 grid gap-2.5 sm:grid-cols-4">
              <?php foreach (PFG_ATTENDEE_AGE_GROUPS as $agCode => $agLabel): ?>
                <label
                  for="pfg-age-group-<?= $esc(strtolower($agCode)) ?>"
                  class="touch-target flex cursor-pointer items-center gap-2.5 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="radio"
                    id="pfg-age-group-<?= $esc(strtolower($agCode)) ?>"
                    name="ageGroup"
                    value="<?= $esc($agCode) ?>"
                    required
                    class="h-5 w-5 accent-[#5B2A86]"
                  />
                  <span><?= $esc($agLabel) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Gender & Registering on Behalf -->
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-att-gender" class="block text-sm font-bold text-[#202D59]">
                Gender <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <select
                id="pfg-att-gender"
                name="gender"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59] focus:border-[#A459FF] focus:outline-none"
              >
                <option value="">Select (Optional)</option>
                <?php foreach (PFG_GENDER_OPTIONS as $g): ?>
                  <option value="<?= $esc($g) ?>"><?= $esc($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="flex items-end">
              <label
                for="pfg-registering-behalf"
                class="touch-target flex w-full cursor-pointer items-center gap-3 rounded-xl border border-[#DCE5F7] bg-[#F9FAFF] px-4 py-2.5 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
              >
                <input
                  type="checkbox"
                  id="pfg-registering-behalf"
                  name="registeringOnBehalf"
                  value="1"
                  class="h-5 w-5 shrink-0 accent-[#5B2A86]"
                />
                <span>I am registering on someone else&rsquo;s behalf</span>
              </label>
            </div>
          </div>

          <!-- Conditional Parent / Legal Guardian Block -->
          <div id="pfg-guardian-panel" hidden class="mt-5 rounded-xl border-2 border-[#5B2A86]/35 bg-[#F7F4FC] p-4">
            <h3 class="text-sm font-bold text-[#2B144D]">Parent / Legal Guardian / Representative Details</h3>
            <p class="mt-0.5 text-xs text-[#46537C]">Required for attendees under 18 years of age or assisted registrations.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
              <div>
                <label for="pfg-guardian-name" class="block text-xs font-bold text-[#202D59]">
                  Guardian Full Name <span class="text-red-700" aria-hidden="true">*</span>
                </label>
                <input
                  type="text"
                  id="pfg-guardian-name"
                  name="guardianName"
                  maxlength="150"
                  class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm text-[#202D59]"
                />
              </div>
              <div>
                <label for="pfg-guardian-rel" class="block text-xs font-bold text-[#202D59]">
                  Relationship <span class="text-red-700" aria-hidden="true">*</span>
                </label>
                <select
                  id="pfg-guardian-rel"
                  name="guardianRelationship"
                  class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm text-[#202D59]"
                >
                  <option value="">Select relationship</option>
                  <?php foreach (PFG_GUARDIAN_RELATIONSHIPS as $gr): ?>
                    <option value="<?= $esc($gr) ?>"><?= $esc($gr) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="pfg-guardian-phone" class="block text-xs font-bold text-[#202D59]">
                  Guardian Phone <span class="font-normal text-[#46537C]">(Optional)</span>
                </label>
                <input
                  type="tel"
                  id="pfg-guardian-phone"
                  name="guardianPhone"
                  maxlength="20"
                  placeholder="+91 98250 12345"
                  class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm text-[#202D59]"
                />
              </div>
            </div>
          </div>

          <!-- Self-Identification Tags -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              I am attending as (select all that apply) <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <p class="mt-0.5 text-xs text-[#46537C]">
              Disability self-identification is kept private inside the Accessibility section below.
            </p>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
              <?php foreach (PFG_ATTENDEE_SELF_ID_TAGS as $idx => $tag): ?>
                <label
                  for="pfg-self-tag-<?= $idx ?>"
                  class="touch-target flex cursor-pointer items-start gap-3 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="checkbox"
                    id="pfg-self-tag-<?= $idx ?>"
                    name="selfIdentificationTags[]"
                    value="<?= $esc($tag) ?>"
                    class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($tag) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Organisation & Designation -->
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-att-org" class="block text-sm font-bold text-[#202D59]">
                Organisation / Institution / School <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <input
                type="text"
                id="pfg-att-org"
                name="organizationName"
                maxlength="200"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
            </div>
            <div>
              <label for="pfg-att-desig" class="block text-sm font-bold text-[#202D59]">
                Designation / Class or Course <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <input
                type="text"
                id="pfg-att-desig"
                name="designation"
                maxlength="150"
                class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm text-[#202D59]"
              />
            </div>
          </div>
        </section>

        <!-- Section 2: Festival Days & Programme Interests -->
        <section aria-labelledby="att-sec2-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 2</p>
          <h2 id="att-sec2-heading" class="mt-1 text-xl font-bold text-[#202D59]">Festival Days &amp; Programme Interests</h2>

          <!-- Festival Days (All Unchecked by Default) -->
          <fieldset class="mt-5 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Festival Days I Will Attend <span class="text-red-700" aria-hidden="true">*</span>
            </legend>
            <div class="mt-2.5 grid gap-3 sm:grid-cols-3">
              <?php
              $dayOptions = [
                  'DAY_1' => 'Day 1 — Thu, 21 January 2027',
                  'DAY_2' => 'Day 2 — Fri, 22 January 2027',
                  'DAY_3' => 'Day 3 — Sat, 23 January 2027',
              ];
              foreach ($dayOptions as $dCode => $dLabel):
              ?>
                <label
                  for="pfg-day-<?= $esc(strtolower($dCode)) ?>"
                  class="touch-target flex cursor-pointer items-center gap-3 rounded-xl border-2 border-[#DCE5F7] bg-[#F9FAFF] px-4 py-3 text-sm font-semibold text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="checkbox"
                    id="pfg-day-<?= $esc(strtolower($dCode)) ?>"
                    name="attendingDays[]"
                    value="<?= $esc($dCode) ?>"
                    class="h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($dLabel) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Interested Tracks -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Events &amp; Sessions I Want to Attend <span class="font-normal text-[#46537C]">(Optional)</span>
            </legend>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
              <?php foreach (PFG_ATTENDEE_TRACKS as $idx => $trk): ?>
                <label
                  for="pfg-track-<?= $idx ?>"
                  class="touch-target flex cursor-pointer items-start gap-3 rounded-lg border border-[#DCE5F7] bg-[#F9FAFF] px-3.5 py-2.5 text-sm text-[#202D59] hover:border-[#A459FF]"
                >
                  <input
                    type="checkbox"
                    id="pfg-track-<?= $idx ?>"
                    name="interestedTracks[]"
                    value="<?= $esc($trk) ?>"
                    class="mt-0.5 h-5 w-5 shrink-0 accent-[#5B2A86]"
                  />
                  <span><?= $esc($trk) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <!-- Para-Sports Athlete Interest -->
          <fieldset class="mt-6 border-0 p-0">
            <legend class="text-sm font-bold text-[#202D59]">
              Interested in taking part in Para-Sports as an Athlete? <span class="font-normal text-[#46537C]">(Optional)</span>
            </legend>
            <p class="mt-0.5 text-xs text-[#46537C]">
              Separate eligibility and classification rules apply; the MSU Department of Physical Education sports team will contact interested athletes separately.
            </p>
            <div class="mt-2.5 flex flex-wrap gap-3">
              <label for="pfg-parasports-yes" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg border-2 border-[#DCE5F7] bg-[#F9FAFF] px-4 py-2 text-sm font-semibold">
                <input type="radio" id="pfg-parasports-yes" name="paraSportsAthlete" value="yes" class="h-5 w-5 accent-[#5B2A86]" />
                <span>Yes</span>
              </label>
              <label for="pfg-parasports-no" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg border-2 border-[#DCE5F7] bg-[#F9FAFF] px-4 py-2 text-sm font-semibold">
                <input type="radio" id="pfg-parasports-no" name="paraSportsAthlete" value="no" class="h-5 w-5 accent-[#5B2A86]" />
                <span>No</span>
              </label>
            </div>
          </fieldset>

          <div id="pfg-parasports-panel" hidden class="mt-4 rounded-xl border border-[#DCE5F7] bg-[#FAF9FF] p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-[#5B2A86]">Select Para-Sports Events of Interest</p>
            <div class="mt-2.5 grid gap-2 sm:grid-cols-2">
              <?php foreach (PFG_PARA_SPORTS_EVENTS as $idx => $ev): ?>
                <label for="pfg-ps-ev-<?= $idx ?>" class="touch-target flex cursor-pointer items-center gap-2.5 rounded-lg bg-white px-3 py-2 text-sm">
                  <input type="checkbox" id="pfg-ps-ev-<?= $idx ?>" name="paraSportsEvents[]" value="<?= $esc($ev) ?>" class="h-5 w-5 accent-[#5B2A86]" />
                  <span><?= $esc($ev) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="mt-3">
              <label for="pfg-ps-other" class="block text-xs font-semibold text-[#202D59]">Other sport (if applicable)</label>
              <input type="text" id="pfg-ps-other" name="paraSportsOther" maxlength="120" class="touch-target mt-1 w-full max-w-md rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm" />
            </div>
          </div>

          <p class="mt-5 text-xs text-[#46537C]">
            Looking to perform in the cultural programme, host a stall/exhibit, or partner as a sponsor/speaker? Please contact the Festival Secretariat at
            <a href="mailto:purplefestivalvadodara@gmail.com" class="font-semibold text-[#5B2A86] underline">purplefestivalvadodara@gmail.com</a>.
          </p>
        </section>

        <!-- Section 3: Optional Outstation Travel, Stay & Food Logistics -->
        <section aria-labelledby="att-sec3-heading" class="rounded-2xl border-2 border-[#202D59]/15 bg-white p-5 sm:p-7">
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#5B2A86]">Section 3 · Optional</p>
          <h2 id="att-sec3-heading" class="mt-1 text-xl font-bold text-[#202D59]">Outstation Travel, Stay &amp; Food Preferences</h2>

          <label for="pfg-outstation-toggle" class="touch-target mt-4 flex cursor-pointer items-center gap-3 rounded-xl border border-[#DCE5F7] bg-[#F9FAFF] px-4 py-3 text-sm font-bold text-[#202D59]">
            <input type="checkbox" id="pfg-outstation-toggle" name="outstationTravelRequired" value="1" class="h-5 w-5 accent-[#5B2A86]" />
            <span>I am travelling from outside Vadodara</span>
          </label>
          <p class="mt-1 text-xs text-[#46537C]">
            Travel and accommodation support, if any, is limited and confirmed separately by the organizers.
          </p>

          <div id="pfg-outstation-panel" hidden class="mt-4 space-y-4 rounded-xl border border-[#DCE5F7] bg-[#FAF9FF] p-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="pfg-arrival-at" class="block text-xs font-bold text-[#202D59]">Arriving on (Date &amp; Approximate Time)</label>
                <input type="datetime-local" id="pfg-arrival-at" name="arrivalAt" class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm" />
              </div>
              <div>
                <label for="pfg-departure-at" class="block text-xs font-bold text-[#202D59]">Departing on (Date &amp; Approximate Time)</label>
                <input type="datetime-local" id="pfg-departure-at" name="departureAt" class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm" />
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="pfg-travel-mode" class="block text-xs font-bold text-[#202D59]">Mode of Travel</label>
                <select id="pfg-travel-mode" name="travelMode" class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm">
                  <option value="">Select mode (Optional)</option>
                  <?php foreach (PFG_TRAVEL_MODES as $tm): ?>
                    <option value="<?= $esc($tm) ?>"><?= $esc($tm) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="pfg-accompanying-count" class="block text-xs font-bold text-[#202D59]">Number of people travelling with you (0–10)</label>
                <input type="number" id="pfg-accompanying-count" name="accompanyingCount" min="0" max="10" class="touch-target mt-1 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3 py-2 text-sm" />
              </div>
            </div>

            <div class="space-y-2.5">
              <label for="pfg-lodging-needed" class="touch-target flex cursor-pointer items-center gap-3 text-sm font-semibold text-[#202D59]">
                <input type="checkbox" id="pfg-lodging-needed" name="lodgingNeeded" value="1" class="h-5 w-5 accent-[#5B2A86]" />
                <span>I would like accommodation / stay guidance</span>
              </label>

              <div id="pfg-accessible-room-wrap" hidden class="pl-8">
                <label for="pfg-accessible-room" class="touch-target flex cursor-pointer items-center gap-3 text-sm font-semibold text-[#202D59]">
                  <input type="checkbox" id="pfg-accessible-room" name="accessibleRoomNeeded" value="1" class="h-5 w-5 accent-[#5B2A86]" />
                  <span>Accessible room needed (restricted to Accessibility Coordinators)</span>
                </label>
              </div>
            </div>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div>
              <label for="pfg-food-pref" class="block text-sm font-bold text-[#202D59]">
                Food Preference <span class="font-normal text-[#46537C]">(Optional)</span>
              </label>
              <select id="pfg-food-pref" name="foodPreference" class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm">
                <option value="">Select (Optional)</option>
                <?php foreach (PFG_FOOD_PREFERENCES as $fp): ?>
                  <option value="<?= $esc($fp) ?>"><?= $esc($fp) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="pfg-dietary-reqs" class="block text-sm font-bold text-[#202D59]">
                Dietary Needs or Allergies <span class="font-normal text-[#46537C]">(Optional, encrypted)</span>
              </label>
              <input type="text" id="pfg-dietary-reqs" name="dietaryRequirements" maxlength="300" placeholder="e.g., Nut allergy, gluten-free, diabetic meal timing" class="touch-target mt-1.5 w-full rounded-lg border-2 border-[#202D59]/25 bg-white px-3.5 py-2.5 text-sm" />
            </div>
          </div>
        </section>

        <!-- Section 4: Shared Accessibility & Accommodations Module -->
        <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, $initialData) ?>

        <!-- Section 5: Emergency Contact (Optional for adults; Required when Under 18) -->
        <?= pfgRenderEmergencyContact($ecDefaults, false, false) ?>

        <!-- Section 6: Consents & Declarations -->
        <?= pfgRenderConsentDeclarations('ATTENDEE', $mode === 'edit' ? ['consentZeroTolerance' => true, 'consentDataProcessing' => true, 'consentAccuracy' => true] : []) ?>

        <!-- Submit Controls -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-[#202D59]/15 bg-white p-5">
          <div class="flex flex-wrap items-center gap-3">
            <?php if ($mode === 'create'): ?>
              <button
                type="button"
                id="pfg-btn-save-draft"
                class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]"
              >
                Save Draft
              </button>
            <?php endif; ?>
            <a href="/apply" class="text-sm font-semibold text-[#46537C] underline hover:text-[#202D59]">Cancel</a>
          </div>

          <button
            type="submit"
            id="pfg-btn-submit"
            class="touch-target inline-flex items-center justify-center rounded-lg bg-[#A459FF] px-7 py-3 text-base font-bold text-white transition-colors hover:bg-[#8D3BF2] disabled:opacity-50"
          >
            <?= $mode === 'edit' ? 'Save Registration Changes' : 'Complete Free Attendee Registration' ?>
          </button>
        </div>
      </form>

      <!-- Confirmation Panel (populated by category-forms.js on success — ZERO QR code) -->
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
