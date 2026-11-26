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
require_once __DIR__ . '/../includes/form-components/account-identity-card.php';
require_once __DIR__ . '/../includes/form-components/shared-accessibility.php';
require_once __DIR__ . '/../includes/form-components/shift-availability-grid.php';
require_once __DIR__ . '/../includes/form-components/emergency-contact.php';
require_once __DIR__ . '/../includes/form-components/consent-declarations.php';

$esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$pdo = getDb();
$currentUser = getCurrentUser($pdo);
if (!$currentUser || empty($currentUser['id'])) {
    header('Location: /login?next=' . rawurlencode('/apply/volunteer'));
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
    header('Location: /login?next=' . rawurlencode('/apply/volunteer'));
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
     WHERE user_id = :uid AND UPPER(category_code) = 'VOLUNTEER' AND deleted_at IS NULL
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
    $stmtVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
    $stmtVol->execute([':rid' => $editRegId]);
    $vRow = $stmtVol->fetch(PDO::FETCH_ASSOC) ?: [];

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
        'emergencyContactName'         => $existingActive['emergency_contact_name'] ?? ($vRow['emergency_contact_name'] ?? ''),
        'emergencyContactRelationship' => $existingActive['emergency_contact_relationship'] ?? ($userRow['emergency_contact_relationship'] ?? ''),
        'emergencyContactPhone'        => $existingActive['emergency_contact_phone'] ?? ($vRow['emergency_contact_phone'] ?? ''),
        'isPersonWithDisability'       => $existingActive['is_person_with_disability'] ?? '',
        'disabilityCategoryCodes'      => $disCodes,
        'disabilitySelfDescription'    => $disSelf,
        'accommodationTypes'           => jsonDecode($accRow['selected_types'] ?? '[]', []),
        'accommodationFreeText'        => (string)(pfgDecryptSensitiveValue($accRow['free_text_details'] ?? $accRow['notes'] ?? null) ?? ''),
        'escortAccompanying'           => !empty($accRow['escort_accompanying']),
        'escortName'                   => (string)(pfgDecryptSensitiveValue($accRow['escort_name_encrypted'] ?? null) ?? ''),
        'volunteerDetails'             => [
            'age'                  => $vRow['age'] ?? '',
            'gender'               => $vRow['gender'] ?? '',
            'msuAffiliation'       => $vRow['msu_affiliation_status'] ?? '',
            'msuFacultyDepartment' => $vRow['msu_faculty_department'] ?? '',
            'institutionName'      => $vRow['institution_name'] ?? '',
            'crrNumber'            => $vRow['crr_number'] ?? '',
            'primaryRole'          => $vRow['primary_role'] ?? '',
            'secondaryRoles'       => jsonDecode($vRow['preferred_roles'] ?? '[]', []),
            'anchoringExperience'  => $vRow['anchoring_experience'] ?? '',
            'relevantExperience'   => $vRow['relevant_experience'] ?? '',
            'languagesSpoken'      => jsonDecode($vRow['languages_spoken'] ?? '[]', []),
            'languagesOther'       => $vRow['languages_other'] ?? '',
            'availabilityShifts'   => jsonDecode($vRow['availability_shifts'] ?? '[]', []),
        ],
    ];
} elseif ($editionId !== '' && !$existingActive) {
    $stmtDr = $pdo->prepare(
        "SELECT payload_json FROM registration_drafts WHERE edition_id = :eid AND user_id = :uid AND category_code = 'VOLUNTEER' LIMIT 1"
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
    'categoryCode'   => 'VOLUNTEER',
    'categoryName'   => 'Festival Volunteer',
    'mode'           => $mode,
    'registrationId' => $mode === 'edit' ? $editRegId : null,
    'csrfToken'      => getCsrfToken(),
    'initialDraft'   => $initialData,
];

$pageTitle = ($mode === 'edit' ? 'Edit Volunteer Application' : 'Festival Volunteer Application') . ' | Purple Festival Gujarat 2027';
$pageDescription = 'Apply as a Festival Volunteer for Purple Festival Gujarat 2027 (21–23 January 2027, Vadodara). Volunteers with and without disabilities are equally welcomed.';

require_once __DIR__ . '/../includes/header.php';
?>
<main id="main-content" tabindex="-1" class="min-h-[calc(100vh-76px)] bg-[#FAF9FF] text-[#202D59] focus:outline-none">
  <div class="mx-auto max-w-[900px] px-5 py-10 sm:px-8 sm:py-14">
    <nav aria-label="Breadcrumb" class="mb-5 text-sm text-[#46537C]">
      <a href="/apply" class="font-semibold text-[#5B2A86] underline hover:text-[#202D59]">&larr; All Registration Categories</a>
    </nav>

    <header class="mb-7">
      <span class="inline-flex items-center rounded-full bg-[#F2E9FE] px-3 py-1 text-xs font-bold text-[#5B2A86]">
        Primary Category 2 of 3 · Free · Coordinator Review Required
      </span>
      <h1 class="mt-2 text-[clamp(1.65rem,3.2vw,2.35rem)] font-bold tracking-[-0.02em] text-[#202D59]">
        <?= $mode === 'edit' ? 'Update Your Volunteer Application' : 'Festival Volunteer Application' ?>
      </h1>
      <p class="mt-2 text-sm leading-relaxed text-[#46537C]">
        Volunteers with and without disabilities are equally welcomed (minimum age 16 years). Applications are reviewed by our Volunteer Coordinators before approval and shift assignment.
      </p>
      <?php if ($mode === 'edit' && !empty($existingActive['public_review_note']) && (string)$existingActive['status'] === 'ADDITIONAL_INFO_REQUIRED'): ?>
        <div role="note" class="mt-4 rounded-xl border-2 border-amber-600 bg-amber-50 p-4 text-sm text-amber-950">
          <p class="font-bold">Coordinator Information Request:</p>
          <p class="mt-1"><?= $esc($existingActive['public_review_note']) ?></p>
        </div>
      <?php endif; ?>
    </header>

    <?php if (!$emailVerified): ?>
      <div role="alert" class="rounded-2xl border-2 border-amber-600/40 bg-amber-50 p-6">
        <h2 class="text-lg font-bold text-amber-950">Please Verify Your Email Address First</h2>
        <p class="mt-2 text-sm text-amber-900">
          Your account email (<strong><?= $esc($userRow['email']) ?></strong>) must be verified before submitting a Volunteer application.
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
        <h2 class="mt-3 text-xl font-bold text-[#202D59]">You Already Have an Active Volunteer Application</h2>
        <p class="mt-2 text-sm text-[#46537C]">
          Reference ID: <strong class="font-mono text-[#202D59]"><?= $esc($existingActive['registration_code']) ?></strong>.
          Track coordinator review status and shift assignments in your Participant Dashboard.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
          <a href="/dashboard" class="touch-target inline-flex items-center justify-center rounded-lg bg-[#202D59] px-5 py-2.5 text-sm font-bold text-white">
            Open Participant Dashboard
          </a>
          <?php if (in_array((string)$existingActive['status'], ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true)): ?>
            <a href="/apply/volunteer?edit=<?= rawurlencode((string)$existingActive['id']) ?>" class="touch-target inline-flex items-center justify-center rounded-lg border-2 border-[#202D59] bg-white px-5 py-2.5 text-sm font-bold text-[#202D59]">
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

        <!-- Section 4: Shared Accessibility & Accommodations Module -->
        <?= pfgRenderSharedAccessibility($disabilityCategories, $accommodationTypes, $initialData) ?>

        <!-- Section 5: Emergency Contact (Required) -->
        <?= pfgRenderEmergencyContact($ecDefaults, true, true) ?>

        <!-- Section 6: Declarations & Consents -->
        <?= pfgRenderConsentDeclarations('VOLUNTEER', $mode === 'edit' ? ['consentZeroTolerance' => true, 'consentDataProcessing' => true, 'consentAccuracy' => true] : []) ?>

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
            <?= $mode === 'edit' ? 'Save & Resubmit Application' : 'Submit Volunteer Application' ?>
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
