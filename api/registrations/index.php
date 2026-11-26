<?php
declare(strict_types=1);

/**
 * GET & POST /api/registrations
 * Purple Festival Gujarat 2027 — Phase 6 & 7 Three-Category Registration API
 *
 * Enforces:
 * - Strictly 3 Primary Categories (ATTENDEE, VOLUNTEER, SLI) with category_tier = 'PRIMARY' AND is_active = 1
 * - Authenticated, email-verified participant session + CSRF + Idempotency-Key on POST
 * - Configurable multi-category rule (D-09: default 1 active registration per primary category; blocks duplicate in same category with 409)
 * - Free Attendee registration default (fee_amount_inr = 0, payment_status = 'NOT_REQUIRED', zero coupon/payment UI)
 * - Strict server-side validation via includes/registration-validators.php
 * - Atomic monotonic Registration ID (PFG-2027-NNNNNN) via includes/registration-id.php (ZERO QR credential issuance)
 * - AES-256-GCM encryption for sensitive free-text fields + stripping from registration_responses
 * - Immutable registration_status_history + Phase 4 transactional outbox email + in_app_notifications in ONE PDO transaction
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/registration-id.php';
require_once __DIR__ . '/../../includes/notification-service.php';
require_once __DIR__ . '/../../includes/registration-validators.php';
require_once __DIR__ . '/../../includes/application-lifecycle.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function pfgRegApiJson(array $payload, int $status = 200): void
{
    jsonResponse($payload, $status);
}

function pfgGetActiveFestivalEdition(PDO $pdo): ?array
{
    $slug = defined('FESTIVAL_EDITION_SLUG') ? FESTIVAL_EDITION_SLUG : 'gujarat-2027';
    $stmt = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $bySlug = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($bySlug) {
        return $bySlug;
    }

    $stmt2 = $pdo->query('SELECT * FROM festival_editions WHERE is_active = 1 LIMIT 1');
    $active = $stmt2 ? $stmt2->fetch(PDO::FETCH_ASSOC) : null;
    return $active ?: null;
}

function pfgFetchAccountProfileRow(PDO $pdo, string $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, email_verified_at, full_name, phone, country,
                city_district, state, preferred_language,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
                is_active, deleted_at
         FROM users
         WHERE id = :id AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pfgSanitizeAnswersSnapshot(array $body): array
{
    $safe = $body;
    $stripKeys = [
        'password',
        'confirmPassword',
        'csrfToken',
        'csrf_token',
        '_csrf',
        'isPersonWithDisability',
        'is_person_with_disability',
        'disabilityCategoryCodes',
        'disability_category_codes',
        'disabilitySelfDescription',
        'disability_self_description',
        'accommodationTypes',
        'accommodation_types',
        'accommodationFreeText',
        'accommodation_free_text',
        'escortAccompanying',
        'escort_accompanying',
        'escortName',
        'escort_name',
        'dietaryRequirements',
        'dietary_requirements',
        'accessibleRoomNeeded',
        'accessible_room_needed',
    ];
    foreach ($stripKeys as $k) {
        unset($safe[$k]);
    }
    if (isset($safe['attendeeDetails']) && is_array($safe['attendeeDetails'])) {
        unset(
            $safe['attendeeDetails']['dietaryRequirements'],
            $safe['attendeeDetails']['dietary_requirements'],
            $safe['attendeeDetails']['accessibleRoomNeeded'],
            $safe['attendeeDetails']['accessible_room_needed']
        );
    }
    return $safe;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ============================================================================
// GET /api/registrations
// ============================================================================
if ($method === 'GET') {
    try {
        $pdo = getDb();
        $edition = pfgGetActiveFestivalEdition($pdo);
        if (!$edition) {
            pfgRegApiJson(['error' => 'No active festival edition configured.'], 404);
        }

        // Strictly PRIMARY active categories ONLY (CAD-02)
        $stmtCat = $pdo->prepare(
            "SELECT * FROM registration_categories
             WHERE edition_id = :eid AND category_tier = 'PRIMARY' AND is_active = 1
             ORDER BY display_order ASC"
        );
        $stmtCat->execute([':eid' => $edition['id']]);
        $catRows = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $categories = [];
        $categoryNameMap = [];
        foreach ($catRows as $c) {
            $categoryNameMap[(string)$c['code']] = (string)$c['name'];
            $categories[] = [
                'id'               => (string)$c['id'],
                'editionId'        => (string)$c['edition_id'],
                'code'             => (string)$c['code'],
                'categoryTier'     => (string)$c['category_tier'],
                'name'             => (string)$c['name'],
                'description'      => $c['description'] ?? null,
                'requiresApproval' => !empty($c['requires_approval']),
                'requiresPayment'  => false,
                'feeAmountInr'     => 0,
                'isActive'         => !empty($c['is_active']),
                'displayOrder'     => (int)($c['display_order'] ?? 0),
            ];
        }

        $stmtDis = $pdo->query('SELECT * FROM disability_categories ORDER BY display_order ASC');
        $disRows = $stmtDis ? ($stmtDis->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $disabilityCategories = [];
        foreach ($disRows as $d) {
            $disabilityCategories[] = [
                'id'                    => (string)$d['id'],
                'code'                  => (string)$d['code'],
                'name'                  => (string)$d['name'],
                'rpwdGroup'             => (string)($d['rpwd_group'] ?? ''),
                'description'           => (string)($d['description'] ?? ''),
                'accommodationExamples' => jsonDecode($d['accommodation_examples'] ?? '[]', []),
                'displayOrder'          => (int)($d['display_order'] ?? 0),
            ];
        }

        $stmtAcc = $pdo->query('SELECT * FROM accommodation_types WHERE is_active = 1 ORDER BY display_order ASC');
        $accRows = $stmtAcc ? ($stmtAcc->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $accommodationTypes = [];
        foreach ($accRows as $a) {
            $accommodationTypes[] = [
                'id'           => (string)$a['id'],
                'code'         => (string)$a['code'],
                'label'        => (string)$a['label'],
                'description'  => $a['description'] ?? null,
                'isActive'     => !empty($a['is_active']),
                'displayOrder' => (int)($a['display_order'] ?? 0),
            ];
        }

        $user = getCurrentUser($pdo);
        $registrationOpen = pfgIsPublicRegistrationOpen($pdo);
        if (!$user || empty($user['id'])) {
            pfgRegApiJson([
                'edition'              => $edition,
                'categories'           => $categories,
                'disabilityCategories' => $disabilityCategories,
                'accommodationTypes'   => $accommodationTypes,
                'allowMultiCategory'   => pfgIsMultiCategoryAllowed($pdo),
                'registrationOpen'     => $registrationOpen,
                'user'                 => null,
                'registrations'        => [],
            ]);
        }

        $profileRow = pfgFetchAccountProfileRow($pdo, (string)$user['id']);
        $stmtRegs = $pdo->prepare(
            'SELECT * FROM registrations WHERE user_id = :uid AND deleted_at IS NULL ORDER BY created_at DESC'
        );
        $stmtRegs->execute([':uid' => $user['id']]);
        $userRegs = $stmtRegs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Also include legacy category names for historical read-only display
        $allCatStmt = $pdo->prepare('SELECT code, name FROM registration_categories WHERE edition_id = :eid');
        $allCatStmt->execute([':eid' => $edition['id']]);
        foreach ($allCatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ac) {
            if (!isset($categoryNameMap[(string)$ac['code']])) {
                $categoryNameMap[(string)$ac['code']] = (string)$ac['name'];
            }
        }

        $enrichedRegistrations = [];
        foreach ($userRegs as $reg) {
            $regId = (string)$reg['id'];

            $stmtAtt = $pdo->prepare('SELECT * FROM attendee_details WHERE registration_id = :rid LIMIT 1');
            $stmtAtt->execute([':rid' => $regId]);
            $attDetail = $stmtAtt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($attDetail) {
                $attDetail['self_identification_tags'] = jsonDecode($attDetail['self_identification_tags'] ?? '[]', []);
                $attDetail['para_sports_events'] = jsonDecode($attDetail['para_sports_events'] ?? '[]', []);
                $attDetail['dietary_requirements'] = pfgDecryptSensitiveValue($attDetail['dietary_requirements_encrypted'] ?? null);
                unset($attDetail['dietary_requirements_encrypted']);
            }

            $stmtVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
            $stmtVol->execute([':rid' => $regId]);
            $volApp = $stmtVol->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmtSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
            $stmtSli->execute([':rid' => $regId]);
            $sliApp = $stmtSli->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmtDel = $pdo->prepare('SELECT * FROM delegate_details WHERE registration_id = :rid LIMIT 1');
            $stmtDel->execute([':rid' => $regId]);
            $delDetail = $stmtDel->fetch(PDO::FETCH_ASSOC) ?: null;

            $accommReqs = [];
            $stmtAccomm = $pdo->prepare('SELECT * FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC');
            $stmtAccomm->execute([':rid' => $regId]);
            foreach ($stmtAccomm->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $accommReqs[] = [
                    'id'                 => (string)$r['id'],
                    'registrationId'     => (string)$r['registration_id'],
                    'selectedTypes'      => jsonDecode($r['selected_types'] ?? '[]', []),
                    'freeTextDetails'    => pfgDecryptSensitiveValue($r['free_text_details'] ?? $r['notes'] ?? null),
                    'escortAccompanying' => !empty($r['escort_accompanying']),
                    'escortName'         => pfgDecryptSensitiveValue($r['escort_name_encrypted'] ?? null),
                    'status'             => (string)($r['status'] ?? 'REQUESTED'),
                    'createdAt'          => (string)$r['created_at'],
                ];
            }

            $enrichedRegistrations[] = [
                'id'                     => $regId,
                'registrationCode'       => (string)$reg['registration_code'],
                'editionId'              => (string)$reg['edition_id'],
                'userId'                 => (string)$reg['user_id'],
                'categoryId'             => (string)$reg['category_id'],
                'categoryCode'           => (string)$reg['category_code'],
                'categoryName'           => $categoryNameMap[$reg['category_code']] ?? (string)$reg['category_code'],
                'status'                 => (string)$reg['status'],
                'paymentStatus'          => (string)$reg['payment_status'],
                'assignmentStatus'       => (string)($reg['assignment_status'] ?? 'UNASSIGNED'),
                'fullName'               => (string)$reg['full_name'],
                'email'                  => (string)$reg['email'],
                'phone'                  => (string)$reg['phone'],
                'country'                => (string)$reg['country'],
                'cityDistrict'           => $reg['city_district'] ?? null,
                'state'                  => $reg['state'] ?? null,
                'occupation'             => $reg['occupation'] ?? null,
                'organizationName'       => $reg['organization_name'] ?? null,
                'designation'            => $reg['designation'] ?? null,
                'isPersonWithDisability' => (string)$reg['is_person_with_disability'],
                'attendingDays'          => jsonDecode($reg['attending_days'] ?? '[]', []),
                'interestedTracks'       => jsonDecode($reg['interested_tracks'] ?? '[]', []),
                'publicReviewNote'       => $reg['public_review_note'] ?? null,
                'submittedAt'            => (string)($reg['submitted_at'] ?? $reg['created_at']),
                'createdAt'              => (string)$reg['created_at'],
                'attendeeDetails'        => $attDetail,
                'volunteerApplication'   => $volApp,
                'sliApplication'         => $sliApp,
                'delegateDetails'        => $delDetail,
                'accommodationRequests'  => $accommReqs,
            ];
        }

        $userPayload = $user;
        if ($profileRow) {
            $userPayload['emailVerified'] = !empty($profileRow['email_verified_at']);
            $userPayload['emailVerifiedAt'] = $profileRow['email_verified_at'] ?? null;
            $userPayload['cityDistrict'] = $profileRow['city_district'] ?? null;
            $userPayload['state'] = $profileRow['state'] ?? null;
            $userPayload['preferredLanguage'] = $profileRow['preferred_language'] ?? null;
        }

        pfgRegApiJson([
            'edition'              => $edition,
            'categories'           => $categories,
            'disabilityCategories' => $disabilityCategories,
            'accommodationTypes'   => $accommodationTypes,
            'allowMultiCategory'   => pfgIsMultiCategoryAllowed($pdo),
            'registrationOpen'     => $registrationOpen,
            'user'                 => $userPayload,
            'registrations'        => $enrichedRegistrations,
        ]);
    } catch (Throwable $e) {
        error_log('GET /api/registrations error: ' . $e->getMessage());
        pfgRegApiJson(['error' => 'Unable to load registration data.'], 500);
    }
}

// ============================================================================
// POST /api/registrations
// ============================================================================
if ($method !== 'POST') {
    pfgRegApiJson(['error' => 'Method not allowed.'], 405);
}

try {
    $pdo = getDb();
    $clientIp = getClientIp() ?: 'unknown';
    pfgRequireRateLimit($pdo, 'registration_submit_ip', $clientIp, 30, 900, 900);

    // §6 Centralized Registration Launch Activation Gate (blocks BEFORE any mutation)
    if (!pfgIsPublicRegistrationOpen($pdo)) {
        pfgRegApiJson([
            'error'              => 'Festival registration is not currently open.',
            'registrationClosed' => true,
        ], 503);
    }

    // 1. Authenticated participant session strictly required (Phase 5 + CAD-03)
    $currentUser = getCurrentUser($pdo);
    if (!is_array($currentUser) || empty($currentUser['id'])) {
        pfgRegApiJson([
            'error'        => 'Authentication required. Please sign in or create a verified participant account before registering.',
            'authRequired' => true,
        ], 401);
    }

    enforceStaffMfaGate($currentUser);
    requireCsrfToken($currentUser);

    $userId = (string)$currentUser['id'];
    $userRow = pfgFetchAccountProfileRow($pdo, $userId);
    if (!$userRow || empty($userRow['is_active'])) {
        pfgRegApiJson(['error' => 'Your account is not active.'], 403);
    }

    if (empty($userRow['email_verified_at'])) {
        pfgRegApiJson([
            'error'                     => 'Please verify your email address before submitting a registration.',
            'emailVerificationRequired' => true,
        ], 403);
    }

    $body = readRequestInput();
    if (!is_array($body) || empty($body)) {
        pfgRegApiJson(['error' => 'Please check the highlighted form fields.'], 400);
    }

    $edition = pfgGetActiveFestivalEdition($pdo);
    if (!$edition) {
        pfgRegApiJson(['error' => 'Festival registration is not currently active.'], 404);
    }

    // 2. Strictly enforce 3 Primary Categories (category_tier = 'PRIMARY' AND is_active = 1)
    $categoryCode = strtoupper(trim((string)($body['categoryCode'] ?? $body['category_code'] ?? '')));
    if ($categoryCode === '' || !in_array($categoryCode, PFG_PRIMARY_CATEGORIES, true)) {
        pfgRegApiJson([
            'error' => 'Selected registration category is not available. Only Attendee, Festival Volunteer, and Sign Language Interpreter (SLI) registrations are open.',
        ], 400);
    }

    $stmtCat = $pdo->prepare(
        "SELECT * FROM registration_categories
         WHERE edition_id = :eid AND UPPER(code) = :code AND category_tier = 'PRIMARY' AND is_active = 1
         LIMIT 1"
    );
    $stmtCat->execute([
        ':eid'  => $edition['id'],
        ':code' => $categoryCode,
    ]);
    $category = $stmtCat->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        pfgRegApiJson([
            'error' => 'Selected registration category is not currently active.',
        ], 400);
    }

    $nowTs = time();
    if (!empty($category['opens_at'])) {
        $opensTs = strtotime((string)$category['opens_at']);
        if ($opensTs !== false && $opensTs > $nowTs) {
            pfgRegApiJson(['error' => 'Registration for this category has not opened yet.'], 400);
        }
    }
    if (!empty($category['closes_at'])) {
        $closesTs = strtotime((string)$category['closes_at']);
        if ($closesTs !== false && $closesTs < $nowTs) {
            pfgRegApiJson(['error' => 'Registration for this category has closed.'], 400);
        }
    }

    // 3. Idempotency-Key check (owner-scoped)
    $rawIdem = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $body['idempotencyKey'] ?? $body['idempotency_key'] ?? ''));
    $idempotencyKey = $rawIdem !== '' ? substr(preg_replace('/[^A-Za-z0-9_\-:.]/', '', $rawIdem) ?? '', 0, 128) : null;
    if ($idempotencyKey === '') {
        $idempotencyKey = null;
    }

    if ($idempotencyKey !== null) {
        $stmtIdem = $pdo->prepare(
            'SELECT * FROM registrations
             WHERE edition_id = :eid AND idempotency_key = :ikey AND user_id = :uid AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmtIdem->execute([
            ':eid'  => $edition['id'],
            ':ikey' => $idempotencyKey,
            ':uid'  => $userId,
        ]);
        $idemReg = $stmtIdem->fetch(PDO::FETCH_ASSOC);
        if ($idemReg) {
            pfgRegApiJson([
                'registration' => [
                    'id'                     => (string)$idemReg['id'],
                    'registrationCode'       => (string)$idemReg['registration_code'],
                    'editionId'              => (string)$idemReg['edition_id'],
                    'userId'                 => (string)$idemReg['user_id'],
                    'categoryId'             => (string)$idemReg['category_id'],
                    'categoryCode'           => (string)$idemReg['category_code'],
                    'status'                 => (string)$idemReg['status'],
                    'paymentStatus'          => (string)$idemReg['payment_status'],
                    'assignmentStatus'       => (string)($idemReg['assignment_status'] ?? 'UNASSIGNED'),
                    'fullName'               => (string)$idemReg['full_name'],
                    'email'                  => (string)$idemReg['email'],
                    'phone'                  => (string)$idemReg['phone'],
                    'country'                => (string)$idemReg['country'],
                    'cityDistrict'           => $idemReg['city_district'] ?? null,
                    'state'                  => $idemReg['state'] ?? null,
                    'attendingDays'          => jsonDecode($idemReg['attending_days'] ?? '[]', []),
                    'interestedTracks'       => jsonDecode($idemReg['interested_tracks'] ?? '[]', []),
                    'submittedAt'            => (string)($idemReg['submitted_at'] ?? $idemReg['created_at']),
                    'createdAt'              => (string)$idemReg['created_at'],
                ],
                'category' => [
                    'code'             => (string)$category['code'],
                    'name'             => (string)$category['name'],
                    'requiresApproval' => !empty($category['requires_approval']),
                    'requiresPayment'  => false,
                ],
                'idempotentReplay' => true,
            ], 200);
        }
    }

    // 4. Duplicate active registration guard (configurable D-09)
    $stmtDup = $pdo->prepare(
        "SELECT id, registration_code, category_code, status FROM registrations
         WHERE user_id = :uid
           AND edition_id = :eid
           AND UPPER(category_code) = :ccode
           AND deleted_at IS NULL
           AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
         LIMIT 1"
    );
    $stmtDup->execute([
        ':uid'   => $userId,
        ':eid'   => $edition['id'],
        ':ccode' => $categoryCode,
    ]);
    $dupSameCat = $stmtDup->fetch(PDO::FETCH_ASSOC);
    if ($dupSameCat) {
        pfgRegApiJson([
            'error'                    => "You already have an active {$category['name']} registration ({$dupSameCat['registration_code']}). You may manage it from your dashboard or apply for a different category.",
            'existingRegistrationCode' => (string)$dupSameCat['registration_code'],
            'existingRegistrationId'   => (string)$dupSameCat['id'],
            'existingStatus'           => (string)$dupSameCat['status'],
        ], 409);
    }

    if (!pfgIsMultiCategoryAllowed($pdo)) {
        $stmtAny = $pdo->prepare(
            "SELECT id, registration_code, category_code, status FROM registrations
             WHERE user_id = :uid
               AND edition_id = :eid
               AND deleted_at IS NULL
               AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
             LIMIT 1"
        );
        $stmtAny->execute([
            ':uid' => $userId,
            ':eid' => $edition['id'],
        ]);
        $existingAny = $stmtAny->fetch(PDO::FETCH_ASSOC);
        if ($existingAny) {
            pfgRegApiJson([
                'error'                    => "Only one active registration per account is currently permitted (existing: {$existingAny['registration_code']}).",
                'existingRegistrationCode' => (string)$existingAny['registration_code'],
                'existingRegistrationId'   => (string)$existingAny['id'],
            ], 409);
        }
    }

    // 5. Strict server-side validation across all category fields, accessibility, emergency contact & consents
    $validation = pfgValidateRegistrationPayload($pdo, $categoryCode, $body, $userRow, false);
    if (!$validation['valid']) {
        pfgRegApiJson([
            'error'  => $validation['error'] ?? 'Please review the highlighted fields.',
            'errors' => $validation['errors'],
        ], 400);
    }

    $vData = $validation['data'];
    $access = $vData['accessibility'];
    $ec = $vData['emergencyContact'];
    $consents = $vData['consents'];
    $catData = $vData['categoryData'];

    // 6. Configurable owner default: Free registration path (D-05)
    $feeAmountInr = 0;
    $discountInr = 0;
    $netAmountInr = 0;
    $paymentStatus = 'NOT_REQUIRED';

    $requiresApproval = !empty($category['requires_approval']) || in_array($categoryCode, ['VOLUNTEER', 'SLI'], true);
    $status = $requiresApproval ? 'SUBMITTED' : 'CONFIRMED';

    // 7. Atomically allocate next monotonic Registration ID BEFORE the outer transaction
    $registrationCode = pfgAllocateRegistrationCode($pdo, (string)$edition['id']);
    $registrationId = uuid4();
    $now = nowSql();
    $ipAddress = getClientIp();
    $userAgent = getClientUserAgent();
    $outboxQueueIds = [];

    // 8. Persist ALL canonical writes inside ONE atomic PDO transaction
    $pdo->beginTransaction();

    try {
        // Backfill blank profile fields on users so participant never has to re-enter them on a second category
        $profileUpdates = [];
        $profileParams = [':uid' => $userId, ':now' => $now];
        if (empty($userRow['phone']) && !empty($vData['phone'])) {
            $profileUpdates[] = 'phone = :phone';
            $profileParams[':phone'] = $vData['phone'];
        }
        if (empty($userRow['city_district']) && !empty($vData['cityDistrict'])) {
            $profileUpdates[] = 'city_district = :city_district';
            $profileParams[':city_district'] = $vData['cityDistrict'];
        }
        if (empty($userRow['state']) && !empty($vData['state'])) {
            $profileUpdates[] = 'state = :state';
            $profileParams[':state'] = $vData['state'];
        }
        if (empty($userRow['emergency_contact_name']) && !empty($ec['emergencyContactName'])) {
            $profileUpdates[] = 'emergency_contact_name = :ec_name';
            $profileParams[':ec_name'] = $ec['emergencyContactName'];
        }
        if (empty($userRow['emergency_contact_relationship']) && !empty($ec['emergencyContactRelationship'])) {
            $profileUpdates[] = 'emergency_contact_relationship = :ec_rel';
            $profileParams[':ec_rel'] = $ec['emergencyContactRelationship'];
        }
        if (empty($userRow['emergency_contact_phone']) && !empty($ec['emergencyContactPhone'])) {
            $profileUpdates[] = 'emergency_contact_phone = :ec_phone';
            $profileParams[':ec_phone'] = $ec['emergencyContactPhone'];
        }
        if (!empty($profileUpdates)) {
            $profileUpdates[] = 'updated_at = :now';
            $pdo->prepare('UPDATE users SET ' . implode(', ', $profileUpdates) . ' WHERE id = :uid')
                ->execute($profileParams);
        }

        // Insert master registration row
        $stmtInsReg = $pdo->prepare(
            "INSERT INTO registrations (
                id, registration_code, idempotency_key, edition_id, user_id, category_id, category_code,
                status, payment_status, assignment_status,
                full_name, email, phone, country, city_district, state,
                emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
                occupation, organization_name, designation, is_person_with_disability,
                attending_days, interested_tracks, coupon_code, fee_amount_inr, discount_inr, net_amount_inr,
                submitted_at, created_at, updated_at
             ) VALUES (
                :id, :registration_code, :idempotency_key, :edition_id, :user_id, :category_id, :category_code,
                :status, :payment_status, 'UNASSIGNED',
                :full_name, :email, :phone, :country, :city_district, :state,
                :ec_name, :ec_rel, :ec_phone,
                :occupation, :organization_name, :designation, :is_person_with_disability,
                :attending_days, :interested_tracks, NULL, :fee_amount_inr, :discount_inr, :net_amount_inr,
                :submitted_at, :created_at, :updated_at
             )"
        );
        $stmtInsReg->execute([
            ':id'                        => $registrationId,
            ':registration_code'         => $registrationCode,
            ':idempotency_key'           => $idempotencyKey,
            ':edition_id'                => $edition['id'],
            ':user_id'                   => $userId,
            ':category_id'               => $category['id'],
            ':category_code'             => $categoryCode,
            ':status'                    => $status,
            ':payment_status'            => $paymentStatus,
            ':full_name'                 => $vData['fullName'],
            ':email'                     => $vData['email'],
            ':phone'                     => $vData['phone'],
            ':country'                   => $vData['country'],
            ':city_district'             => $vData['cityDistrict'],
            ':state'                     => $vData['state'],
            ':ec_name'                   => $ec['emergencyContactName'],
            ':ec_rel'                    => $ec['emergencyContactRelationship'],
            ':ec_phone'                  => $ec['emergencyContactPhone'],
            ':occupation'                => $vData['occupation'],
            ':organization_name'         => $vData['organizationName'],
            ':designation'               => $vData['designation'],
            ':is_person_with_disability' => $access['isPersonWithDisability'],
            ':attending_days'            => jsonEncode($vData['attendingDays']),
            ':interested_tracks'         => jsonEncode($vData['interestedTracks']),
            ':fee_amount_inr'            => $feeAmountInr,
            ':discount_inr'              => $discountInr,
            ':net_amount_inr'            => $netAmountInr,
            ':submitted_at'              => $now,
            ':created_at'                => $now,
            ':updated_at'                => $now,
        ]);

        // Sanitized form snapshot (all sensitive disability/accommodation/dietary/escort fields stripped)
        $safeAnswers = pfgSanitizeAnswersSnapshot($body);
        if ($categoryCode === 'SLI' && !empty($catData['outstationReviewTag'])) {
            $safeAnswers['outstationCoordinatorReview'] = true;
        }
        $pdo->prepare(
            'INSERT INTO registration_responses (id, registration_id, answers, created_at, updated_at)
             VALUES (:id, :registration_id, :answers, :created_at, :updated_at)'
        )->execute([
            ':id'              => uuid4(),
            ':registration_id' => $registrationId,
            ':answers'         => jsonEncode($safeAnswers),
            ':created_at'      => $now,
            ':updated_at'      => $now,
        ]);

        // Category-specific canonical child table writes
        if ($categoryCode === 'ATTENDEE') {
            $encDietary = pfgEncryptSensitiveValue($catData['dietaryRequirements']);
            $pdo->prepare(
                'INSERT INTO attendee_details (
                    id, registration_id, age_group, gender, self_identification_tags,
                    guardian_name, guardian_relationship, guardian_phone,
                    para_sports_athlete, para_sports_events, para_sports_other,
                    outstation_travel_required, arrival_at, departure_at, travel_mode,
                    lodging_needed, accessible_room_needed, accompanying_count,
                    food_preference, dietary_requirements_encrypted, created_at, updated_at
                 ) VALUES (
                    :id, :rid, :age_group, :gender, :self_tags,
                    :g_name, :g_rel, :g_phone,
                    :ps_ath, :ps_events, :ps_other,
                    :outstation, :arrival_at, :departure_at, :travel_mode,
                    :lodging, :acc_room, :acc_count,
                    :food_pref, :dietary_enc, :now1, :now2
                 )'
            )->execute([
                ':id'          => uuid4(),
                ':rid'         => $registrationId,
                ':age_group'   => $catData['ageGroup'],
                ':gender'      => $catData['gender'],
                ':self_tags'   => jsonEncode($catData['selfIdentificationTags']),
                ':g_name'      => $catData['guardianName'],
                ':g_rel'       => $catData['guardianRelationship'],
                ':g_phone'     => $catData['guardianPhone'],
                ':ps_ath'      => $catData['paraSportsAthlete'] ? 1 : 0,
                ':ps_events'   => jsonEncode($catData['paraSportsEvents']),
                ':ps_other'    => $catData['paraSportsOther'],
                ':outstation'  => $catData['outstationTravelRequired'] ? 1 : 0,
                ':arrival_at'  => $catData['arrivalAt'],
                ':departure_at'=> $catData['departureAt'],
                ':travel_mode' => $catData['travelMode'],
                ':lodging'     => $catData['lodgingNeeded'] ? 1 : 0,
                ':acc_room'    => $catData['accessibleRoomNeeded'] ? 1 : 0,
                ':acc_count'   => $catData['accompanyingCount'],
                ':food_pref'   => $catData['foodPreference'],
                ':dietary_enc' => $encDietary,
                ':now1'        => $now,
                ':now2'        => $now,
            ]);
        } elseif ($categoryCode === 'VOLUNTEER') {
            $cityStateCompat = trim(($vData['cityDistrict'] ?? '') . (!empty($vData['state']) ? ', ' . $vData['state'] : ''));
            $pdo->prepare(
                'INSERT INTO volunteer_applications (
                    id, registration_id, age, gender, city_state, institution_name,
                    is_msu_student_or_alumni, msu_affiliation_status, msu_faculty_department,
                    crr_number, primary_role, preferred_roles, anchoring_experience, relevant_experience,
                    availability_days, availability_shifts, languages_spoken, languages_other,
                    emergency_contact_name, emergency_contact_phone, created_at, updated_at
                 ) VALUES (
                    :id, :rid, :age, :gender, :city_state, :inst_name,
                    :is_msu, :msu_status, :msu_fac,
                    :crr, :primary_role, :pref_roles, :anch_exp, :rel_exp,
                    :avail_days, :avail_shifts, :langs, :langs_other,
                    :ec_name, :ec_phone, :now1, :now2
                 )'
            )->execute([
                ':id'           => uuid4(),
                ':rid'          => $registrationId,
                ':age'          => $catData['age'],
                ':gender'       => $catData['gender'],
                ':city_state'   => $cityStateCompat !== '' ? $cityStateCompat : null,
                ':inst_name'    => $catData['institutionName'],
                ':is_msu'       => $catData['isMsuStudentOrAlumni'] ? 1 : 0,
                ':msu_status'   => $catData['msuAffiliationStatus'],
                ':msu_fac'      => $catData['msuFacultyDepartment'],
                ':crr'          => $catData['crrNumber'],
                ':primary_role' => $catData['primaryRole'],
                ':pref_roles'   => jsonEncode($catData['preferredRoles']),
                ':anch_exp'     => $catData['anchoringExperience'],
                ':rel_exp'      => $catData['relevantExperience'],
                ':avail_days'   => jsonEncode($catData['availabilityDays']),
                ':avail_shifts' => jsonEncode($catData['availabilityShifts']),
                ':langs'        => jsonEncode($catData['languagesSpoken']),
                ':langs_other'  => $catData['languagesOther'],
                ':ec_name'      => $ec['emergencyContactName'],
                ':ec_phone'     => $ec['emergencyContactPhone'],
                ':now1'         => $now,
                ':now2'         => $now,
            ]);
        } elseif ($categoryCode === 'SLI') {
            $pdo->prepare(
                'INSERT INTO sli_applications (
                    id, registration_id, age, city_state, based_in_gujarat, crr_number,
                    qualification_category, certification_level, qualifications,
                    experience_band, training_institute, professional_affiliation,
                    areas_of_expertise, interpretation_preferences,
                    availability_days, availability_shifts, comfortable_public_stage,
                    video_demo_url, honorarium_or_travel_notes, created_at, updated_at
                 ) VALUES (
                    :id, :rid, :age, :city_state, :in_guj, :crr,
                    :qual_cat, :cert_lvl, :quals,
                    :exp_band, :institute, :prof_aff,
                    :expertise, :prefs,
                    :avail_days, :avail_shifts, :comfort_stage,
                    :video_url, :hon_notes, :now1, :now2
                 )'
            )->execute([
                ':id'            => uuid4(),
                ':rid'           => $registrationId,
                ':age'           => $catData['age'],
                ':city_state'    => $catData['cityState'] !== '' ? $catData['cityState'] : null,
                ':in_guj'        => $catData['basedInGujarat'] ? 1 : 0,
                ':crr'           => $catData['crrNumber'],
                ':qual_cat'      => $catData['qualificationCategory'],
                ':cert_lvl'      => $catData['certificationLevel'],
                ':quals'         => $catData['qualifications'],
                ':exp_band'      => $catData['experienceBand'],
                ':institute'     => $catData['trainingInstitute'],
                ':prof_aff'      => $catData['professionalAffiliation'],
                ':expertise'     => jsonEncode($catData['areasOfExpertise']),
                ':prefs'         => jsonEncode($catData['interpretationPreferences']),
                ':avail_days'    => jsonEncode($catData['availabilityDays']),
                ':avail_shifts'  => jsonEncode($catData['availabilityShifts']),
                ':comfort_stage' => $catData['comfortablePublicStage'] ? 1 : 0,
                ':video_url'     => $catData['videoDemoUrl'],
                ':hon_notes'     => $catData['honorariumOrTravelNotes'],
                ':now1'          => $now,
                ':now2'          => $now,
            ]);
        }

        // DPDP Act 2023 Consent Ledger (version 2.0)
        $consentRows = [
            ['type' => 'ZERO_TOLERANCE_HARASSMENT', 'granted' => $consents['consentZeroTolerance'] ? 1 : 0],
            ['type' => 'DATA_PROCESSING_DPDP',      'granted' => $consents['consentDataProcessing'] ? 1 : 0],
            ['type' => 'INFORMATION_ACCURACY',      'granted' => $consents['consentAccuracy'] ? 1 : 0],
            ['type' => 'PHOTOGRAPHY_MEDIA',         'granted' => $consents['consentMediaPhotography'] ? 1 : 0],
            ['type' => 'FUTURE_EVENT_UPDATES',      'granted' => $consents['consentFutureUpdates'] ? 1 : 0],
        ];
        $stmtConsent = $pdo->prepare(
            "INSERT INTO privacy_consent_records (
                id, user_id, registration_id, consent_type, consent_version, granted, ip_address, recorded_at
             ) VALUES (
                :id, :uid, :rid, :ctype, '2.0', :granted, :ip, :now
             )"
        );
        foreach ($consentRows as $cItem) {
            $stmtConsent->execute([
                ':id'      => uuid4(),
                ':uid'     => $userId,
                ':rid'     => $registrationId,
                ':ctype'   => $cItem['type'],
                ':granted' => $cItem['granted'],
                ':ip'      => $ipAddress,
                ':now'     => $now,
            ]);
        }

        // Protected Disability Disclosures (strictly ONLY when YES; AES-256-GCM encrypted free-text)
        $encSelfDesc = pfgEncryptSensitiveValue($access['disabilitySelfDescription']);
        if (
            $access['isPersonWithDisability'] === 'YES' &&
            (!empty($access['resolvedDisabilityMap']) || $encSelfDesc !== null)
        ) {
            if (!empty($access['resolvedDisabilityMap'])) {
                $stmtDisIns = $pdo->prepare(
                    'INSERT INTO participant_disability_disclosures (
                        id, registration_id, disability_category_id, disability_code, category_code, self_description, notes, created_at
                     ) VALUES (
                        :id, :rid, :dis_id, :dcode, :ccode, :self_desc, :notes, :now
                     )'
                );
                $idx = 0;
                foreach ($access['resolvedDisabilityMap'] as $dCode => $dId) {
                    $descVal = ($idx === 0) ? $encSelfDesc : null;
                    // Only pass FK UUID when it matches a real row ID (36-char UUID)
                    $fkId = (strlen($dId) === 36) ? $dId : null;
                    $stmtDisIns->execute([
                        ':id'        => uuid4(),
                        ':rid'       => $registrationId,
                        ':dis_id'    => $fkId,
                        ':dcode'     => $dCode,
                        ':ccode'     => $dCode,
                        ':self_desc' => $descVal,
                        ':notes'     => $descVal,
                        ':now'       => $now,
                    ]);
                    $idx++;
                }
            } else {
                $pdo->prepare(
                    'INSERT INTO participant_disability_disclosures (
                        id, registration_id, disability_category_id, self_description, notes, created_at
                     ) VALUES (
                        :id, :rid, NULL, :self_desc, :notes, :now
                     )'
                )->execute([
                    ':id'        => uuid4(),
                    ':rid'       => $registrationId,
                    ':self_desc' => $encSelfDesc,
                    ':notes'     => $encSelfDesc,
                    ':now'       => $now,
                ]);
            }
        }

        // Protected Accommodation Requests (open to ALL participants regardless of disability disclosure — CAD-07)
        $hasAccomm = !empty($access['accommodationTypes'])
            || $access['accommodationFreeText'] !== ''
            || !empty($access['escortAccompanying']);

        if ($hasAccomm) {
            $encAccommNotes = pfgEncryptSensitiveValue($access['accommodationFreeText']);
            $encEscortName = $access['escortAccompanying'] ? pfgEncryptSensitiveValue($access['escortName']) : null;
            $firstCode = !empty($access['accommodationTypes']) ? (string)$access['accommodationTypes'][0] : null;

            $pdo->prepare(
                "INSERT INTO accommodation_requests (
                    id, registration_id, user_id, accommodation_code, selected_types,
                    free_text_details, notes, escort_accompanying, escort_name_encrypted,
                    status, created_at, updated_at
                 ) VALUES (
                    :id, :rid, :uid, :acode, :sel_types,
                    :ft_details, :notes, :escort_acc, :escort_enc,
                    'REQUESTED', :now1, :now2
                 )"
            )->execute([
                ':id'         => uuid4(),
                ':rid'        => $registrationId,
                ':uid'        => $userId,
                ':acode'      => $firstCode,
                ':sel_types'  => jsonEncode($access['accommodationTypes']),
                ':ft_details' => $encAccommNotes,
                ':notes'      => $encAccommNotes,
                ':escort_acc' => $access['escortAccompanying'] ? 1 : 0,
                ':escort_enc' => $encEscortName,
                ':now1'       => $now,
                ':now2'       => $now,
            ]);
        }

        // Immutable lifecycle status history entries
        pfgRecordRegistrationStatusHistory(
            $pdo,
            $registrationId,
            null,
            'SUBMITTED',
            $userId,
            'PARTICIPANT',
            null,
            'Registration submitted by authenticated participant.'
        );
        if ($status === 'CONFIRMED') {
            pfgRecordRegistrationStatusHistory(
                $pdo,
                $registrationId,
                'SUBMITTED',
                'CONFIRMED',
                null,
                'SYSTEM',
                'Instant confirmation under free Attendee policy.',
                'Your Attendee registration is confirmed.'
            );
        }

        // Clean up any saved draft for this category
        $pdo->prepare(
            'DELETE FROM registration_drafts WHERE edition_id = :eid AND user_id = :uid AND category_code = :ccode'
        )->execute([
            ':eid'   => $edition['id'],
            ':uid'   => $userId,
            ':ccode' => $categoryCode,
        ]);

        // Phase 4 Transactional Outbox: enqueue registration confirmation / application received email
        if ($status === 'CONFIRMED') {
            $qRes = pfgEnqueueNotification($pdo, [
                'to'              => $vData['email'],
                'templateCode'    => 'ATTENDEE_REGISTRATION_CONFIRMED',
                'variables'       => [
                    'fullName'          => $vData['fullName'],
                    'registrationCode'  => $registrationCode,
                    'categoryName'      => (string)$category['name'],
                    'status'            => 'CONFIRMED',
                    'attendingDaysText' => implode(', ', $vData['attendingDays']),
                ],
                'recipientUserId' => $userId,
                'entityType'      => 'registration',
                'entityId'        => $registrationId,
                'idempotencyKey'  => 'reg-confirm:' . $registrationId,
                'createInApp'     => true,
                'inAppTitle'      => "Registration Confirmed ({$registrationCode})",
                'inAppMessage'    => "Your {$category['name']} registration ({$registrationCode}) is confirmed. Quote this Registration ID at the Vadodara Registration Desk.",
                'inAppActionUrl'  => '/dashboard',
            ]);
            $outboxQueueIds[] = (string)$qRes['queueId'];
        } else {
            $appTpl = $categoryCode === 'SLI' ? 'SLI_APPLICATION_RECEIVED' : 'VOLUNTEER_APPLICATION_RECEIVED';
            $qRes = pfgEnqueueNotification($pdo, [
                'to'              => $vData['email'],
                'templateCode'    => $appTpl,
                'variables'       => [
                    'fullName'         => $vData['fullName'],
                    'registrationCode' => $registrationCode,
                    'categoryName'     => (string)$category['name'],
                    'status'           => 'SUBMITTED',
                ],
                'recipientUserId' => $userId,
                'entityType'      => 'registration',
                'entityId'        => $registrationId,
                'idempotencyKey'  => 'app-received:' . $registrationId,
                'createInApp'     => true,
                'inAppTitle'      => "Application Received ({$registrationCode})",
                'inAppMessage'    => "Your {$category['name']} application ({$registrationCode}) has been received and is pending coordinator review.",
                'inAppActionUrl'  => '/dashboard',
            ]);
            $outboxQueueIds[] = (string)$qRes['queueId'];
        }

        if ($hasAccomm) {
            $accQ = pfgEnqueueNotification($pdo, [
                'to'              => $vData['email'],
                'templateCode'    => 'ACCESSIBILITY_REQUEST_RECEIVED',
                'variables'       => [
                    'fullName'           => $vData['fullName'],
                    'registrationCode'   => $registrationCode,
                    'accommodationCount' => (string)max(1, count($access['accommodationTypes'])),
                ],
                'recipientUserId' => $userId,
                'entityType'      => 'registration',
                'entityId'        => $registrationId,
                'idempotencyKey'  => 'accomm-submit:' . $registrationId,
                'createInApp'     => true,
                'inAppTitle'      => "Accessibility Request Received ({$registrationCode})",
                'inAppMessage'    => "Your accessibility support preferences for {$registrationCode} have been securely recorded for our coordinators.",
                'inAppActionUrl'  => '/dashboard',
            ]);
            $outboxQueueIds[] = (string)$accQ['queueId'];
        }

        $pdo->commit();
    } catch (Throwable $txErr) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $txErr;
    }

    // Best-effort post-commit local file-sink dispatch
    $emailDelivery = ['queued' => count($outboxQueueIds), 'status' => 'QUEUED'];
    if (!empty($outboxQueueIds) && defined('MAIL_DRIVER') && MAIL_DRIVER === 'file') {
        try {
            $processed = 0;
            foreach ($outboxQueueIds as $qid) {
                if (pfgDispatchQueuedIdNow($pdo, $qid) !== null) {
                    $processed++;
                }
            }
            $emailDelivery = ['queued' => count($outboxQueueIds), 'status' => 'CAPTURED_LOCAL', 'processed' => $processed];
        } catch (Throwable $mailErr) {
            error_log('[POST /api/registrations] file-sink dispatch warning: ' . $mailErr->getMessage());
        }
    }

    $auditMeta = [
        'registrationCode' => $registrationCode,
        'categoryCode'     => $categoryCode,
        'status'           => $status,
        'paymentStatus'    => $paymentStatus,
        'qrIssued'         => false,
    ];
    if ($categoryCode === 'SLI' && !empty($catData['outstationReviewTag'])) {
        $auditMeta['outstationSliCoordinatorReview'] = true;
    }

    logAudit($pdo, [
        'actorUserId'  => $userId,
        'action'       => 'REGISTRATION_SUBMITTED',
        'resourceType' => 'registration',
        'resourceId'   => $registrationId,
        'ipAddress'    => $ipAddress,
        'userAgent'    => $userAgent,
        'metadata'     => $auditMeta,
    ]);

    pfgRegApiJson([
        'registration' => [
            'id'                     => $registrationId,
            'registrationCode'       => $registrationCode,
            'editionId'              => (string)$edition['id'],
            'userId'                 => $userId,
            'categoryId'             => (string)$category['id'],
            'categoryCode'           => $categoryCode,
            'categoryName'           => (string)$category['name'],
            'status'                 => $status,
            'paymentStatus'          => $paymentStatus,
            'assignmentStatus'       => 'UNASSIGNED',
            'fullName'               => $vData['fullName'],
            'email'                  => $vData['email'],
            'phone'                  => $vData['phone'],
            'country'                => $vData['country'],
            'cityDistrict'           => $vData['cityDistrict'],
            'state'                  => $vData['state'],
            'organizationName'       => $vData['organizationName'],
            'designation'            => $vData['designation'],
            'isPersonWithDisability' => $access['isPersonWithDisability'],
            'attendingDays'          => $vData['attendingDays'],
            'interestedTracks'       => $vData['interestedTracks'],
            'submittedAt'            => $now,
            'createdAt'              => $now,
        ],
        'category' => [
            'code'             => (string)$category['code'],
            'name'             => (string)$category['name'],
            'requiresApproval' => $requiresApproval,
            'requiresPayment'  => false,
        ],
        'pricing' => [
            'feeAmountInr' => 0,
            'discountInr'  => 0,
            'netAmountInr' => 0,
            'couponCode'   => null,
        ],
        'qrPass'        => null,
        'emailSentTo'   => $vData['email'],
        'emailDelivery' => $emailDelivery,
    ], 201);
} catch (Throwable $e) {
    error_log('POST /api/registrations error: ' . $e->getMessage());
    pfgRegApiJson([
        'error' => 'Unable to complete registration right now. Please try again.',
    ], 500);
}
