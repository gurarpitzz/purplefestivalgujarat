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
require_once __DIR__ . '/../includes/form-components/account-identity-card.php';
require_once __DIR__ . '/../includes/form-components/shared-accessibility.php';
require_once __DIR__ . '/../includes/form-components/shift-availability-grid.php';
require_once __DIR__ . '/../includes/form-components/emergency-contact.php';
require_once __DIR__ . '/../includes/form-components/consent-declarations.php';

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$pdo = getDb();
$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['id'])) {
    header('Location: /login?next=' . rawurlencode('/apply/sli'));
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
    header('Location: /login?next=' . rawurlencode('/apply/sli'));
    exit;
}
$emailVerified = !empty($userRow['email_verified_at']);

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

$editRegId = isset($_GET['edit']) && is_string($_GET['edit']) ? trim($_GET['edit']) : '';
$mode = 'create';
$initialData = [];

$stmtAct = $pdo->prepare(
    "SELECT * FROM registrations
     WHERE user_id = :uid AND UPPER(category_code) = 'SLI' AND deleted_at IS NULL
       AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
     ORDER BY created_at DESC LIMIT 1"
);
$stmtAct->execute([':uid' => $userId]);
$existingActive = $stmtAct->fetch(PDO::FETCH_ASSOC) ?: null;

if (
    $editRegId !== '' &&
    $existingActive &&
    (string)$existingActive['id'] === $editRegId &&
    in_array((string)$existingActive['status'], ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true)
) {
    $mode = 'edit';
    $stmtSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
    $stmtSli->execute([':rid' => $editRegId]);
    $sRow = $stmtSli->fetch(PDO::FETCH_ASSOC) ?: [];

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
        'cityDistrict'                 => $existingActive['city_district'] ?? ($userRow['city_district'] ?? ''),
        'state'                        => $existingActive['state'] ?? ($userRow['state'] ?? ''),
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
        'sliDetails'                   => [
            'age'                       => $sRow['age'] ?? '',
            'basedInGujarat'            => isset($sRow['based_in_gujarat']) ? ((int)$sRow['based_in_gujarat'] === 1 ? 'yes' : 'no') : '',
            'professionalAffiliation'   => $sRow['professional_affiliation'] ?? '',
            'qualificationCategory'     => $sRow['qualification_category'] ?? '',
            'qualifications'            => $sRow['qualifications'] ?? '',
            'crrNumber'                 => $sRow['crr_number'] ?? '',
            'experienceBand'            => $sRow['experience_band'] ?? '',
            'trainingInstitute'         => $sRow['training_institute'] ?? '',
            'videoDemoUrl'              => $sRow['video_demo_url'] ?? '',
            'comfortablePublicStage'    => isset($sRow['comfortable_public_stage']) ? ((int)$sRow['comfortable_public_stage'] === 1 ? 'yes' : 'no') : '',
            'availabilityShifts'        => jsonDecode($sRow['availability_shifts'] ?? '[]', []),
            'areasOfExpertise'          => jsonDecode($sRow['areas_of_expertise'] ?? '[]', []),
            'interpretationPreferences' => jsonDecode($sRow['interpretation_preferences'] ?? '[]', []),
            'honorariumOrTravelNotes'   => $sRow['honorarium_or_travel_notes'] ?? '',
        ],
    ];
} elseif ($editionId !== '' && !$existingActive) {
    $stmtDr = $pdo->prepare(
        "SELECT payload_json FROM registration_drafts WHERE edition_id = :eid AND user_id = :uid AND category_code = 'SLI' LIMIT 1"
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

$defaultCity = (string)($initialData['cityDistrict'] ?? $userRow['city_district'] ?? '');
$defaultState = (string)($initialData['state'] ?? $userRow['state'] ?? '');

$ecDefaults = [
    'emergencyContactName'         => $initialData['emergencyContactName'] ?? ($userRow['emergency_contact_name'] ?? ''),
    'emergencyContactRelationship' => $initialData['emergencyContactRelationship'] ?? ($userRow['emergency_contact_relationship'] ?? ''),
    'emergencyContactPhone'        => $initialData['emergencyContactPhone'] ?? ($userRow['emergency_contact_phone'] ?? ''),
];

$bootData = [
    'categoryCode'   => 'SLI',
    'categoryName'   => 'Sign Language Interpreter (SLI)',
    'mode'           => $mode,
    'registrationId' => $mode === 'edit' ? $editRegId : null,
    'csrfToken'      => getCsrfToken(),
    'initialDraft'   => $initialData,
];

$pageTitle = ($mode === 'edit' ? 'Edit SLI Application' : 'Sign Language Interpreter (SLI) Application') . ' | Purple Festival Gujarat 2027';
$pageDescription = 'Apply as an Indian Sign Language Interpreter (SLI) for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara).';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[900px] px-5 py-10 sm:px-8 sm:py-14">
    <nav aria-label="Breadcrumb" class="mb-5 text-sm text-[#46537C]">
      <a href="/apply" class="font-semibold text-[#5B2A86] underline hover:text-[#202D59]">&larr; All Registration Categories</a>
    </nav>

    <header class="mb-7">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        Primary Category 3 of 3 · Free · SLI Coordinator Verification Required
      </span>
      <h1 class="mt-2 text-[clamp(1.65rem,3.2vw,2.35rem)] font-bold tracking-[-0.02em] text-[#202D59]">
        <?= $mode === 'edit' ? 'Update Your Sign Language Interpreter Application' : 'Sign Language Interpreter (SLI) Application' ?>
      </h1>
      <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
        For Certified ISL Interpreters, Deaf Interpreters, fluent community signers, and trainee interpreters (minimum age 18 years). Applications are verified by our Accessibility &amp; SLI Coordinators before session assignment.
      </p>
      <?php if ($mode === 'edit' && !empty($existingActive['public_review_note']) && (string)$existingActive['status'] === 'ADDITIONAL_INFO_REQUIRED'): ?>
        <div role="note" class="mt-4 rounded-xl border-2 border-amber-600 bg-amber-50 p-4 text-sm text-amber-950">
          <p class="font-bold">SLI Coordinator Information Request:</p>
          <p class="mt-1"><?= $esc($existingActive['public_review_note']) ?></p>
        </div>
      <?php endif; ?>
    </header>

    <?php if (!$emailVerified): ?>
      <div role="alert" class="rounded-2xl border-2 border-amber-600/40 bg-amber-50 p-6">
        <h2 class="text-lg font-bold text-amber-950">Please Verify Your Email Address First</h2>
        <p class="mt-2 text-sm text-amber-900">
          Your account email (<strong><?= $esc($userRow['email']) ?></strong>) must be verified before submitting an SLI application.
        </p>
        <a href="/verify-email" class="touch-target mt-4 inline-flex items-center justify-center rounded-lg bg-[#202D59] px-5 py-2.5 text-sm font-bold text-white">
          Go to Email Verification
        </a>
      </div>
    <?php elseif ($existingActive && $mode === 'create'): ?>
      <div class="rounded-2xl border-2 border-emerald-700/35 bg-white p-6 shadow-xs">
        <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
          Application Active · <?= $esc($existingActive['status']) ?>
        </span>
        <h2 class="mt-3 text-xl font-bold text-[#202D59]">You Already Have an Active SLI Application</h2>
        <p class="mt-2 text-sm text-[#46537C]">
          Reference ID: <strong class="font-mono text-[#202D59]"><?= $esc($existingActive['registration_code']) ?></strong>.
          Track coordinator verification and paired session assignments in your Participant Dashboard.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <a href="/dashboard" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#202D59] px-5 py-2.5 text-sm font-bold text-white">
            Open Participant Dashboard
          </a>
          <?php if (in_array((string)$existingActive['status'], ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true)): ?>
            <a href="/apply/sli?edit=<?= rawurlencode((string)$existingActive['id']) ?>" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59]">
              Edit Application
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div
        id="pfg-form-error-summary"
        role="alert"
        aria-live="assertive"
        tabindex="-1"
        hidden
        class="mb-6 rounded-2xl border-2 border-red-600 bg-red-50 p-5 text-sm text-red-950"
      ></div>
      <div id="pfg-draft-status" role="status" aria-live="polite" class="mb-4 text-xs font-semibold text-[#46537C]"></div>

      <form id="pfg-category-form" novalidate class="space-y-7">
        <!-- Section 0: Account Identity Summary -->
        <?= pfgRenderAccountIdentityCard($userRow) ?>

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
        <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, $initialData) ?>

        <!-- Section 5: Emergency Contact (Required) -->
        <?= pfgRenderEmergencyContact($ecDefaults, true, true) ?>

        <!-- Section 6: Declarations & Consents -->
        <?= pfgRenderConsentDeclarations('SLI', $mode === 'edit' ? ['consentZeroTolerance' => true, 'consentDataProcessing' => true, 'consentAccuracy' => true] : []) ?>

        <!-- Submit Controls -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-[#202D59]/15 bg-white p-5">
          <div class="flex flex-wrap items-center gap-3">
            <?php if ($mode === 'create'): ?>
              <button type="button" id="pfg-btn-save-draft" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59] hover:bg-[#DDDDF9]">
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
            <?= $mode === 'edit' ? 'Save & Resubmit Application' : 'Submit SLI Application' ?>
          </button>
        </div>
      </form>

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
