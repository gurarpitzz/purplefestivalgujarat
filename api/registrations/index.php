<?php
declare(strict_types=1);

/**
 * GET / POST /api/registrations
 * Purple Festival Gujarat 2027 — Accountless Public Three-Category Registration API (CAD-17)
 *
 * Security & Architecture Guarantees:
 * - Strictly 3 Primary Categories: ATTENDEE, VOLUNTEER, SLI (no legacy categories).
 * - Accountless public submission: ZERO website accounts created, ZERO passwords collected,
 *   `registrations.user_id = NULL`, `accommodation_requests.user_id = NULL`,
 *   `privacy_consent_records.user_id = NULL`.
 * - NEVER attaches to existing administrative or historical `users` rows, NEVER authenticates
 *   by email, NEVER modifies existing registrations, NEVER discloses existing Registration IDs
 *   or private details on conflict.
 * - Enforces centralized public registration activation gate (`pfgIsPublicRegistrationOpen`).
 * - Enforces anonymous session-bound CSRF token, accessible honeypot anti-spam check,
 *   persistent DB rate limits (IP + email), and `Idempotency-Key` replay safety.
 * - Smart duplicate detection: blocks exact `(edition_id, category_code, LOWER(email), LOWER(TRIM(full_name)))`
 *   active duplicates with generic HTTP 409 (zero PII leak), while permitting caregivers /
 *   institutions to register multiple distinct people using a shared contact email.
 * - Atomic Registration ID allocation (`PFG-2027-NNNNNN`) + transactional email outbox queueing
 *   (Resend / file sink, zero QR pass issuance per D-01).
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/registration-validators.php';
require_once dirname(__DIR__, 2) . '/includes/application-lifecycle.php';
require_once dirname(__DIR__, 2) . '/includes/notification-service.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!function_exists('pfgRegColumnExists')) {
    function pfgRegColumnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $k = $table . '.' . $column;
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
            );
            $st->execute([':t' => $table, ':c' => $column]);
            return $cache[$k] = ((int)$st->fetchColumn() > 0);
        } catch (Throwable $e) {
            return $cache[$k] = false;
        }
    }
}

try {
    $pdo = getDb();

    // Load active festival edition
    $edStmt = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
    $edStmt->execute([':slug' => FESTIVAL_EDITION_SLUG]);
    $edition = $edStmt->fetch(PDO::FETCH_ASSOC);
    if (!$edition) {
        $edStmt2 = $pdo->query("SELECT * FROM festival_editions WHERE status = 'ACTIVE' ORDER BY year DESC LIMIT 1");
        $edition = $edStmt2 ? $edStmt2->fetch(PDO::FETCH_ASSOC) : null;
    }
    if (!$edition) {
        jsonResponse(['error' => 'Active festival edition is not configured.'], 503);
    }
    $editionId = (string)$edition['id'];

    // =========================================================================
    // GET /api/registrations — Public Categories, Accessibility Options & CSRF Token
    // =========================================================================
    if ($method === 'GET') {
        $catStmt = $pdo->prepare("
            SELECT id, code, name, description, fee_amount_inr, requires_approval,
                   opens_at, closes_at, display_order
            FROM registration_categories
            WHERE edition_id = :eid
              AND is_active = 1
              AND UPPER(code) IN ('ATTENDEE', 'VOLUNTEER', 'SLI')
            ORDER BY display_order ASC
        ");
        $catStmt->execute([':eid' => $editionId]);
        $categories = [];
        foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $categories[] = [
                'id'               => (string)$row['id'],
                'code'             => strtoupper((string)$row['code']),
                'name'             => (string)$row['name'],
                'description'      => (string)($row['description'] ?? ''),
                'feeAmountInr'     => (int)($row['fee_amount_inr'] ?? 0),
                'requiresApproval' => (bool)$row['requires_approval'],
                'opensAt'          => $row['opens_at'] ?? null,
                'closesAt'         => $row['closes_at'] ?? null,
                'displayOrder'     => (int)($row['display_order'] ?? 0),
            ];
        }

        $disStmt = $pdo->query('SELECT id, code, name, rpwd_group, description, display_order FROM disability_categories ORDER BY display_order ASC');
        $disabilities = $disStmt ? ($disStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $accStmt = $pdo->query('SELECT id, code, label, description, display_order FROM accommodation_types WHERE is_active = 1 ORDER BY display_order ASC');
        $accommodations = $accStmt ? ($accStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $registrationOpen = pfgIsPublicRegistrationOpen($pdo);
        $csrfToken = getCsrfToken();

        jsonResponse([
            'edition' => [
                'id'       => $editionId,
                'slug'     => (string)$edition['slug'],
                'name'     => (string)$edition['name'],
                'year'     => (int)($edition['year'] ?? 2027),
                'location' => (string)($edition['location'] ?? 'Vadodara, Gujarat'),
            ],
            'accountRequired'      => false,
            'registrationOpen'     => $registrationOpen,
            'allowMultiCategory'   => pfgIsMultiCategoryAllowed($pdo),
            'categories'           => $categories,
            'disabilityCategories' => $disabilities,
            'accommodationTypes'   => $accommodations,
            'csrfToken'            => $csrfToken,
        ], 200);
    }

    // =========================================================================
    // POST /api/registrations — Accountless Public Submission (3 Categories Only)
    // =========================================================================
    if ($method === 'POST') {
        // 1. Centralized registration activation gate (CAD-17 / §3)
        if (!pfgIsPublicRegistrationOpen($pdo)) {
            jsonResponse([
                'error'              => 'Public registration is currently closed. Please check back once registration opens or contact purplefestivalvadodara@gmail.com.',
                'code'               => 'REGISTRATION_CLOSED',
                'registrationOpen'   => false,
                'registrationClosed' => true,
            ], 503);
        }

        // 2. Persistent IP rate limiting before validation/DB work
        $clientIp = getClientIp() ?: 'unknown';
        pfgRequireRateLimit($pdo, 'registration_submit_ip', $clientIp, 40, 900, 900);

        // 3. Anonymous session-bound CSRF verification (NEVER relies on participant login)
        requireCsrfToken(null);

        $body = readRequestInput();

        // 4. Accessible anti-spam honeypot check (no visual CAPTCHA)
        $honeypot = trim((string)($body['websiteHp'] ?? $body['website_hp'] ?? $body['hp_field'] ?? ''));
        if ($honeypot !== '') {
            logAudit([
                'actorUserId'  => null,
                'action'       => 'REGISTRATION_HONEYPOT_REJECTED',
                'resourceType' => 'security',
                'resourceId'   => 'public_registration',
                'metadata'     => ['reason' => 'honeypot_filled'],
            ]);
            jsonResponse(['error' => 'Unable to process submission. Please check the form and try again.'], 400);
        }

        // 5. Enforce strictly 3 primary categories
        $categoryCode = strtoupper(trim((string)($body['categoryCode'] ?? $body['category_code'] ?? '')));
        if (!in_array($categoryCode, PFG_PRIMARY_CATEGORIES, true)) {
            jsonResponse([
                'error'  => 'Only Attendee, Sign Language Interpreter (SLI), and Festival Volunteer categories are open for registration.',
                'errors' => ['categoryCode' => 'Please select Attendee, Volunteer, or Sign Language Interpreter (SLI).'],
            ], 400);
        }

        // Load active category configuration & enforce registration window
        $catStmt = $pdo->prepare('
            SELECT id, code, name, fee_amount_inr, requires_approval, is_active, opens_at, closes_at
            FROM registration_categories
            WHERE edition_id = :eid AND UPPER(code) = :code
            LIMIT 1
        ');
        $catStmt->execute([':eid' => $editionId, ':code' => $categoryCode]);
        $categoryRow = $catStmt->fetch(PDO::FETCH_ASSOC);
        if (!$categoryRow || empty($categoryRow['is_active'])) {
            jsonResponse(['error' => 'This registration category is not currently available.'], 400);
        }

        $nowTs = time();
        if (!empty($categoryRow['opens_at']) && strtotime((string)$categoryRow['opens_at']) > $nowTs) {
            jsonResponse(['error' => 'Registration for this category has not opened yet.'], 403);
        }
        if (!empty($categoryRow['closes_at']) && strtotime((string)$categoryRow['closes_at']) < $nowTs) {
            jsonResponse(['error' => 'Registration for this category is now closed.'], 403);
        }

        // 6. Strict server-side validation (accountless — $accountProfile = null)
        $validation = pfgValidateRegistrationPayload($pdo, $categoryCode, $body, null, false);
        if (!$validation['valid']) {
            jsonResponse([
                'error'  => $validation['error'] ?? 'Please correct the highlighted fields.',
                'errors' => $validation['errors'],
            ], 400);
        }
        $data = $validation['data'];

        // Per-email persistent rate limit (generous for caregivers/institutions, blocks flooding)
        pfgRequireRateLimit($pdo, 'registration_submit_email', $data['email'], 20, 900, 900);

        // 7. Idempotency-Key check (prevents duplicate records from double clicks or network retries)
        $rawIdem = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $body['idempotencyKey'] ?? null;
        $idempotencyKey = is_string($rawIdem) && trim($rawIdem) !== '' ? trim($rawIdem) : null;
        if ($idempotencyKey !== null && (strlen($idempotencyKey) > 128 || !preg_match('/^[A-Za-z0-9._:\-]+$/', $idempotencyKey))) {
            jsonResponse(['error' => 'Invalid Idempotency-Key format.'], 400);
        }

        if ($idempotencyKey !== null) {
            $idemStmt = $pdo->prepare('
                SELECT id, registration_code, category_code, status, payment_status, assignment_status,
                       attending_days, submitted_at
                FROM registrations
                WHERE edition_id = :eid AND idempotency_key = :ikey AND deleted_at IS NULL
                LIMIT 1
            ');
            $idemStmt->execute([':eid' => $editionId, ':ikey' => $idempotencyKey]);
            $idemRow = $idemStmt->fetch(PDO::FETCH_ASSOC);
            if ($idemRow) {
                $replayCat = strtoupper((string)$idemRow['category_code']);
                $replayStatus = strtoupper((string)$idemRow['status']);
                jsonResponse([
                    'idempotentReplay' => true,
                    'message'          => $replayStatus === 'CONFIRMED'
                        ? 'Your registration is confirmed.'
                        : 'Your application has been received and is pending coordinator review.',
                    'registration'     => [
                        'id'               => (string)$idemRow['id'],
                        'registrationCode' => (string)$idemRow['registration_code'],
                        'categoryCode'     => $replayCat,
                        'categoryName'     => (string)$categoryRow['name'],
                        'status'           => $replayStatus,
                        'paymentStatus'    => (string)$idemRow['payment_status'],
                        'assignmentStatus' => (string)$idemRow['assignment_status'],
                        'attendingDays'    => jsonDecode($idemRow['attending_days'] ?? '[]', []),
                        'submittedAt'      => $idemRow['submitted_at'],
                    ],
                    'emailQueued'      => true,
                    'qrPass'           => null,
                ], 200);
            }
        }

        // 8. Accountless Smart Duplicate Detection (§10):
        //    - Blocks exact duplicate (same edition + same category + same normalized email + same normalized full name).
        //    - Allows caregivers / schools / NGOs to register multiple distinct people under a shared contact email.
        //    - NEVER discloses an existing Registration ID or personal details on HTTP 409.
        $normEmail = strtolower(trim($data['email']));
        $normFullName = preg_replace('/\s+/', ' ', strtolower(trim($data['fullName']))) ?? strtolower(trim($data['fullName']));
        $allowMultiCategory = pfgIsMultiCategoryAllowed($pdo);

        $dupSameCatStmt = $pdo->prepare("
            SELECT id, full_name
            FROM registrations
            WHERE edition_id = :eid
              AND UPPER(category_code) = :cat
              AND LOWER(email) = :email
              AND deleted_at IS NULL
              AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
            LIMIT 25
        ");
        $dupSameCatStmt->execute([
            ':eid'   => $editionId,
            ':cat'   => $categoryCode,
            ':email' => $normEmail,
        ]);
        foreach ($dupSameCatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $cand) {
            $candNormName = preg_replace('/\s+/', ' ', strtolower(trim((string)($cand['full_name'] ?? '')))) ?? '';
            if ($candNormName === $normFullName) {
                jsonResponse([
                    'error' => 'A registration with these details already exists for this category. If you need to update your details or need assistance, please contact purplefestivalvadodara@gmail.com.',
                    'code'  => 'DUPLICATE_REGISTRATION',
                ], 409);
            }
        }

        if (!$allowMultiCategory) {
            $dupAnyCatStmt = $pdo->prepare("
                SELECT id, full_name
                FROM registrations
                WHERE edition_id = :eid
                  AND LOWER(email) = :email
                  AND deleted_at IS NULL
                  AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
                LIMIT 25
            ");
            $dupAnyCatStmt->execute([
                ':eid'   => $editionId,
                ':email' => $normEmail,
            ]);
            foreach ($dupAnyCatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $cand) {
                $candNormName = preg_replace('/\s+/', ' ', strtolower(trim((string)($cand['full_name'] ?? '')))) ?? '';
                if ($candNormName === $normFullName) {
                    jsonResponse([
                        'error' => 'A registration with these details already exists for this festival edition. Please contact purplefestivalvadodara@gmail.com if you need assistance.',
                        'code'  => 'DUPLICATE_REGISTRATION',
                    ], 409);
                }
            }
        }

        // 9. Determine initial status (strictly per approved category policy)
        $requiresApproval = !empty($categoryRow['requires_approval']) || in_array($categoryCode, ['VOLUNTEER', 'SLI'], true);
        $initialStatus = $requiresApproval ? 'SUBMITTED' : 'CONFIRMED';

        // 10. Atomic Registration ID allocation & single-transaction persistence (user_id = NULL everywhere)
        $now = nowSql();
        $regId = uuid4();
        $registrationCode = '';
        $primaryQueueId = null;
        $accommQueueId = null;
        $hasAccomm = !empty($data['accessibility']['accommodationTypes'])
            || $data['accessibility']['accommodationFreeText'] !== ''
            || $data['accessibility']['escortAccompanying'];

        $hasPrefLangCol = pfgRegColumnExists($pdo, 'registrations', 'preferred_language');

        $pdo->beginTransaction();
        try {
            $registrationCode = pfgAllocateRegistrationCode($pdo, $editionId);

            if ($hasPrefLangCol) {
                $insReg = $pdo->prepare("
                    INSERT INTO registrations (
                        id, registration_code, idempotency_key, edition_id, user_id,
                        category_id, category_code, status, payment_status, assignment_status,
                        full_name, email, phone, country, preferred_language, city_district, state,
                        occupation, organization_name, designation,
                        is_person_with_disability, attending_days, interested_tracks,
                        emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
                        fee_amount_inr, discount_inr, net_amount_inr,
                        submitted_at, created_at, updated_at
                    ) VALUES (
                        :id, :code, :ikey, :eid, NULL,
                        :cat_id, :cat_code, :status, 'NOT_REQUIRED', 'UNASSIGNED',
                        :full_name, :email, :phone, :country, :pref_lang, :city, :state,
                        :occupation, :org, :desig,
                        :pwd, :days, :tracks,
                        :ec_name, :ec_rel, :ec_phone,
                        0, 0, 0,
                        :now, :now, :now
                    )
                ");
                $insReg->execute([
                    ':id'        => $regId,
                    ':code'      => $registrationCode,
                    ':ikey'      => $idempotencyKey,
                    ':eid'       => $editionId,
                    ':cat_id'    => (string)$categoryRow['id'],
                    ':cat_code'  => $categoryCode,
                    ':status'    => $initialStatus,
                    ':full_name' => $data['fullName'],
                    ':email'     => $normEmail,
                    ':phone'     => $data['phone'],
                    ':country'   => $data['country'],
                    ':pref_lang' => $data['preferredLanguage'] ?? null,
                    ':city'      => $data['cityDistrict'],
                    ':state'     => $data['state'],
                    ':occupation' => $data['occupation'],
                    ':org'       => $data['organizationName'],
                    ':desig'     => $data['designation'],
                    ':pwd'       => $data['accessibility']['isPersonWithDisability'],
                    ':days'      => jsonEncode($data['attendingDays']),
                    ':tracks'    => jsonEncode($data['interestedTracks']),
                    ':ec_name'   => $data['emergencyContact']['emergencyContactName'],
                    ':ec_rel'    => $data['emergencyContact']['emergencyContactRelationship'],
                    ':ec_phone'  => $data['emergencyContact']['emergencyContactPhone'],
                    ':now'       => $now,
                ]);
            } else {
                $insReg = $pdo->prepare("
                    INSERT INTO registrations (
                        id, registration_code, idempotency_key, edition_id, user_id,
                        category_id, category_code, status, payment_status, assignment_status,
                        full_name, email, phone, country, city_district, state,
                        occupation, organization_name, designation,
                        is_person_with_disability, attending_days, interested_tracks,
                        emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
                        fee_amount_inr, discount_inr, net_amount_inr,
                        submitted_at, created_at, updated_at
                    ) VALUES (
                        :id, :code, :ikey, :eid, NULL,
                        :cat_id, :cat_code, :status, 'NOT_REQUIRED', 'UNASSIGNED',
                        :full_name, :email, :phone, :country, :city, :state,
                        :occupation, :org, :desig,
                        :pwd, :days, :tracks,
                        :ec_name, :ec_rel, :ec_phone,
                        0, 0, 0,
                        :now, :now, :now
                    )
                ");
                $insReg->execute([
                    ':id'        => $regId,
                    ':code'      => $registrationCode,
                    ':ikey'      => $idempotencyKey,
                    ':eid'       => $editionId,
                    ':cat_id'    => (string)$categoryRow['id'],
                    ':cat_code'  => $categoryCode,
                    ':status'    => $initialStatus,
                    ':full_name' => $data['fullName'],
                    ':email'     => $normEmail,
                    ':phone'     => $data['phone'],
                    ':country'   => $data['country'],
                    ':city'      => $data['cityDistrict'],
                    ':state'     => $data['state'],
                    ':occupation' => $data['occupation'],
                    ':org'       => $data['organizationName'],
                    ':desig'     => $data['designation'],
                    ':pwd'       => $data['accessibility']['isPersonWithDisability'],
                    ':days'      => jsonEncode($data['attendingDays']),
                    ':tracks'    => jsonEncode($data['interestedTracks']),
                    ':ec_name'   => $data['emergencyContact']['emergencyContactName'],
                    ':ec_rel'    => $data['emergencyContact']['emergencyContactRelationship'],
                    ':ec_phone'  => $data['emergencyContact']['emergencyContactPhone'],
                    ':now'       => $now,
                ]);
            }

            // Persist category-specific details
            if ($categoryCode === 'ATTENDEE') {
                $cd = $data['categoryData'];
                $pdo->prepare("
                    INSERT INTO attendee_details (
                        id, registration_id, age_group, gender, self_identification_tags,
                        guardian_name, guardian_relationship, guardian_phone,
                        para_sports_athlete, para_sports_events, para_sports_other,
                        outstation_travel_required, arrival_at, departure_at, travel_mode,
                        lodging_needed, accessible_room_needed, accompanying_count,
                        food_preference, dietary_requirements_encrypted,
                        created_at, updated_at
                    ) VALUES (
                        :id, :rid, :age_group, :gender, :self_tags,
                        :g_name, :g_rel, :g_phone,
                        :ps_ath, :ps_ev, :ps_other,
                        :outstation, :arr, :dep, :tmode,
                        :lodging, :acc_room, :acc_cnt,
                        :food, :diet_enc,
                        :now, :now
                    )
                ")->execute([
                    ':id'         => uuid4(),
                    ':rid'        => $regId,
                    ':age_group'  => $cd['ageGroup'],
                    ':gender'     => $cd['gender'],
                    ':self_tags'  => jsonEncode($cd['selfIdentificationTags']),
                    ':g_name'     => $cd['guardianName'],
                    ':g_rel'      => $cd['guardianRelationship'],
                    ':g_phone'    => $cd['guardianPhone'],
                    ':ps_ath'     => $cd['paraSportsAthlete'] ? 1 : 0,
                    ':ps_ev'      => jsonEncode($cd['paraSportsEvents']),
                    ':ps_other'   => $cd['paraSportsOther'],
                    ':outstation' => $cd['outstationTravelRequired'] ? 1 : 0,
                    ':arr'        => $cd['arrivalAt'],
                    ':dep'        => $cd['departureAt'],
                    ':tmode'      => $cd['travelMode'],
                    ':lodging'    => $cd['lodgingNeeded'] ? 1 : 0,
                    ':acc_room'   => $cd['accessibleRoomNeeded'] ? 1 : 0,
                    ':acc_cnt'    => $cd['accompanyingCount'],
                    ':food'       => $cd['foodPreference'],
                    ':diet_enc'   => pfgEncryptSensitiveValue($cd['dietaryRequirements']),
                    ':now'        => $now,
                ]);
            } elseif ($categoryCode === 'VOLUNTEER') {
                $cd = $data['categoryData'];
                $pdo->prepare("
                    INSERT INTO volunteer_applications (
                        id, registration_id, age, gender,
                        is_msu_student_or_alumni, msu_affiliation_status, msu_faculty_department,
                        institution_name, crr_number, primary_role, preferred_roles,
                        anchoring_experience, relevant_experience,
                        languages_spoken, languages_other, availability_days, availability_shifts,
                        emergency_contact_name, emergency_contact_phone,
                        created_at, updated_at
                    ) VALUES (
                        :id, :rid, :age, :gender,
                        :is_msu, :msu_status, :msu_fac,
                        :inst, :crr, :prim_role, :pref_roles,
                        :anch_exp, :rel_exp,
                        :langs, :langs_other, :avail_days, :avail_shifts,
                        :ec_name, :ec_phone,
                        :now, :now
                    )
                ")->execute([
                    ':id'           => uuid4(),
                    ':rid'          => $regId,
                    ':age'          => $cd['age'],
                    ':gender'       => $cd['gender'],
                    ':is_msu'       => $cd['isMsuStudentOrAlumni'] ? 1 : 0,
                    ':msu_status'   => $cd['msuAffiliationStatus'],
                    ':msu_fac'      => $cd['msuFacultyDepartment'],
                    ':inst'         => $cd['institutionName'],
                    ':crr'          => $cd['crrNumber'],
                    ':prim_role'    => $cd['primaryRole'],
                    ':pref_roles'   => jsonEncode($cd['preferredRoles']),
                    ':anch_exp'     => $cd['anchoringExperience'],
                    ':rel_exp'      => $cd['relevantExperience'],
                    ':langs'        => jsonEncode($cd['languagesSpoken']),
                    ':langs_other'  => $cd['languagesOther'],
                    ':avail_days'   => jsonEncode($cd['availabilityDays']),
                    ':avail_shifts' => jsonEncode($cd['availabilityShifts']),
                    ':ec_name'      => $data['emergencyContact']['emergencyContactName'],
                    ':ec_phone'     => $data['emergencyContact']['emergencyContactPhone'],
                    ':now'          => $now,
                ]);
            } elseif ($categoryCode === 'SLI') {
                $cd = $data['categoryData'];
                $pdo->prepare("
                    INSERT INTO sli_applications (
                        id, registration_id, age, city_state, based_in_gujarat,
                        professional_affiliation, qualification_category, certification_level,
                        qualifications, crr_number, experience_band, training_institute,
                        video_demo_url, comfortable_public_stage,
                        availability_days, availability_shifts, areas_of_expertise,
                        interpretation_preferences, honorarium_or_travel_notes,
                        created_at, updated_at
                    ) VALUES (
                        :id, :rid, :age, :city_state, :guj,
                        :prof_aff, :qual_cat, :cert_lvl,
                        :quals, :crr, :exp_band, :inst,
                        :video, :stage,
                        :avail_days, :avail_shifts, :expertise,
                        :prefs, :hon_notes,
                        :now, :now
                    )
                ")->execute([
                    ':id'           => uuid4(),
                    ':rid'          => $regId,
                    ':age'          => $cd['age'],
                    ':city_state'   => $cd['cityState'],
                    ':guj'          => $cd['basedInGujarat'] ? 1 : 0,
                    ':prof_aff'     => $cd['professionalAffiliation'],
                    ':qual_cat'     => $cd['qualificationCategory'],
                    ':cert_lvl'     => $cd['certificationLevel'],
                    ':quals'        => $cd['qualifications'],
                    ':crr'          => $cd['crrNumber'],
                    ':exp_band'     => $cd['experienceBand'],
                    ':inst'         => $cd['trainingInstitute'],
                    ':video'        => $cd['videoDemoUrl'],
                    ':stage'        => $cd['comfortablePublicStage'] ? 1 : 0,
                    ':avail_days'   => jsonEncode($cd['availabilityDays']),
                    ':avail_shifts' => jsonEncode($cd['availabilityShifts']),
                    ':expertise'    => jsonEncode($cd['areasOfExpertise']),
                    ':prefs'        => jsonEncode($cd['interpretationPreferences']),
                    ':hon_notes'    => $cd['honorariumOrTravelNotes'],
                    ':now'          => $now,
                ]);
            }

            // Persist voluntary disability disclosures (ONLY if YES, encrypted with AES-256-GCM)
            $acc = $data['accessibility'];
            if ($acc['isPersonWithDisability'] === 'YES') {
                $encSelfDesc = pfgEncryptSensitiveValue($acc['disabilitySelfDescription']);
                $resolvedMap = $acc['resolvedDisabilityMap'];
                if (!empty($resolvedMap)) {
                    $insDis = $pdo->prepare('
                        INSERT INTO participant_disability_disclosures (
                            id, registration_id, disability_category_id, disability_code, self_description, created_at
                        ) VALUES (:id, :rid, :dc_id, :dcode, :self_desc, :now)
                    ');
                    $first = true;
                    foreach ($resolvedMap as $dCode => $dId) {
                        $insDis->execute([
                            ':id'        => uuid4(),
                            ':rid'       => $regId,
                            ':dc_id'     => $dId,
                            ':dcode'     => $dCode,
                            ':self_desc' => $first ? $encSelfDesc : null,
                            ':now'       => $now,
                        ]);
                        $first = false;
                    }
                } elseif ($encSelfDesc !== null) {
                    $pdo->prepare('
                        INSERT INTO participant_disability_disclosures (
                            id, registration_id, disability_category_id, disability_code, self_description, created_at
                        ) VALUES (:id, :rid, NULL, NULL, :self_desc, :now)
                    ')->execute([
                        ':id'        => uuid4(),
                        ':rid'       => $regId,
                        ':self_desc' => $encSelfDesc,
                        ':now'       => $now,
                    ]);
                }
            }

            // Persist accommodation request (user_id = NULL, encrypted free-text & escort name)
            if ($hasAccomm) {
                $encFreeText = pfgEncryptSensitiveValue($acc['accommodationFreeText']);
                $encEscort = pfgEncryptSensitiveValue($acc['escortName']);
                $firstCode = !empty($acc['accommodationTypes']) ? (string)$acc['accommodationTypes'][0] : 'OTHER';
                $pdo->prepare("
                    INSERT INTO accommodation_requests (
                        id, registration_id, user_id, accommodation_code, selected_types,
                        free_text_details, escort_accompanying, escort_name_encrypted,
                        status, created_at, updated_at
                    ) VALUES (
                        :id, :rid, NULL, :acode, :stypes,
                        :ftext, :escort, :escort_name,
                        'REQUESTED', :now, :now
                    )
                ")->execute([
                    ':id'          => uuid4(),
                    ':rid'         => $regId,
                    ':acode'       => $firstCode,
                    ':stypes'      => jsonEncode($acc['accommodationTypes']),
                    ':ftext'       => $encFreeText,
                    ':escort'      => $acc['escortAccompanying'] ? 1 : 0,
                    ':escort_name' => $encEscort,
                    ':now'         => $now,
                ]);
            }

            // Persist DPDP consent records with user_id = NULL and registration_id = $regId
            $consents = $data['consents'];
            $consentRows = [
                ['CODE_OF_CONDUCT_ZERO_TOLERANCE', $consents['consentZeroTolerance']],
                ['DATA_PROCESSING_DPDP', $consents['consentDataProcessing']],
                ['INFORMATION_ACCURACY', $consents['consentAccuracy']],
            ];
            if ($consents['consentMediaPhotography']) {
                $consentRows[] = ['PHOTOGRAPHY_MEDIA', true];
            }
            if ($consents['consentFutureUpdates']) {
                $consentRows[] = ['FUTURE_FESTIVAL_UPDATES', true];
            }
            $insConsent = $pdo->prepare("
                INSERT INTO privacy_consent_records (
                    id, user_id, registration_id, consent_type, consent_version, granted, ip_address, recorded_at
                ) VALUES (:id, NULL, :rid, :ctype, '2027.2', :granted, :ip, :now)
            ");
            foreach ($consentRows as [$cType, $cGranted]) {
                $insConsent->execute([
                    ':id'      => uuid4(),
                    ':rid'     => $regId,
                    ':ctype'   => $cType,
                    ':granted' => $cGranted ? 1 : 0,
                    ':ip'      => $clientIp,
                    ':now'     => $now,
                ]);
            }

            // Record initial immutable lifecycle history (actor_user_id = NULL, actor_role = APPLICANT)
            pfgRecordRegistrationStatusHistory(
                $pdo,
                $regId,
                null,
                $initialStatus,
                null,
                'APPLICANT',
                $requiresApproval
                    ? "Public accountless {$categoryCode} application submitted."
                    : 'Public accountless Attendee registration submitted and confirmed.',
                null
            );

            // Enqueue transactional confirmation / acknowledgement email (recipientUserId = NULL, createInApp = false)
            $primaryTemplate = match ($categoryCode) {
                'VOLUNTEER' => 'VOLUNTEER_APPLICATION_RECEIVED',
                'SLI'       => 'SLI_APPLICATION_RECEIVED',
                default     => 'ATTENDEE_REGISTRATION_CONFIRMED',
            };
            $qPrimary = pfgEnqueueNotification($pdo, [
                'to'              => $normEmail,
                'templateCode'    => $primaryTemplate,
                'variables'       => [
                    'fullName'         => $data['fullName'],
                    'registrationCode' => $registrationCode,
                    'categoryName'     => (string)$categoryRow['name'],
                    'status'           => $initialStatus,
                ],
                'recipientUserId' => null,
                'entityType'      => 'registration',
                'entityId'        => $regId,
                'idempotencyKey'  => 'reg-submit:' . $regId . ':' . $primaryTemplate,
                'createInApp'     => false,
            ]);
            $primaryQueueId = (string)($qPrimary['queueId'] ?? '');

            // Enqueue privacy-safe accessibility acknowledgement email if accommodations requested
            if ($hasAccomm) {
                $qAccomm = pfgEnqueueNotification($pdo, [
                    'to'              => $normEmail,
                    'templateCode'    => 'ACCESSIBILITY_REQUEST_RECEIVED',
                    'variables'       => [
                        'fullName'           => $data['fullName'],
                        'registrationCode'   => $registrationCode,
                        'accommodationCount' => (string)count($acc['accommodationTypes']),
                    ],
                    'recipientUserId' => null,
                    'entityType'      => 'registration',
                    'entityId'        => $regId,
                    'idempotencyKey'  => 'reg-accomm:' . $regId,
                    'createInApp'     => false,
                ]);
                $accommQueueId = (string)($qAccomm['queueId'] ?? '');
            }

            $pdo->commit();
        } catch (Throwable $txErr) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($txErr instanceof PDOException && $txErr->getCode() === '23000' && $idempotencyKey !== null) {
                // Race-condition idempotency replay fallback
                $idemStmt = $pdo->prepare('
                    SELECT id, registration_code, category_code, status, payment_status, assignment_status,
                           attending_days, submitted_at
                    FROM registrations
                    WHERE edition_id = :eid AND idempotency_key = :ikey AND deleted_at IS NULL
                    LIMIT 1
                ');
                $idemStmt->execute([':eid' => $editionId, ':ikey' => $idempotencyKey]);
                $idemRow = $idemStmt->fetch(PDO::FETCH_ASSOC);
                if ($idemRow) {
                    jsonResponse([
                        'idempotentReplay' => true,
                        'registration'     => [
                            'id'               => (string)$idemRow['id'],
                            'registrationCode' => (string)$idemRow['registration_code'],
                            'categoryCode'     => strtoupper((string)$idemRow['category_code']),
                            'categoryName'     => (string)$categoryRow['name'],
                            'status'           => strtoupper((string)$idemRow['status']),
                            'paymentStatus'    => (string)$idemRow['payment_status'],
                            'assignmentStatus' => (string)$idemRow['assignment_status'],
                            'attendingDays'    => jsonDecode($idemRow['attending_days'] ?? '[]', []),
                            'submittedAt'      => $idemRow['submitted_at'],
                        ],
                        'emailQueued'      => true,
                        'qrPass'           => null,
                    ], 200);
                }
            }
            throw $txErr;
        }

        // Post-commit best-effort local file-sink dispatch (production Resend handled by cron worker)
        $emailDelivery = ['queueId' => $primaryQueueId, 'status' => 'QUEUED'];
        if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'file') {
            if ($primaryQueueId !== '') {
                try {
                    pfgDispatchQueuedIdNow($pdo, $primaryQueueId);
                    $emailDelivery['status'] = 'CAPTURED_LOCAL';
                } catch (Throwable $mailErr) {
                    error_log('[/api/registrations] local dispatch warning: ' . $mailErr->getMessage());
                }
            }
            if ($accommQueueId !== null && $accommQueueId !== '') {
                try {
                    pfgDispatchQueuedIdNow($pdo, $accommQueueId);
                } catch (Throwable $mailErr) {
                    error_log('[/api/registrations] local accomm dispatch warning: ' . $mailErr->getMessage());
                }
            }
        }

        // Privacy-safe audit log (zero personal data in metadata)
        logAudit([
            'actorUserId'  => null,
            'action'       => 'PUBLIC_REGISTRATION_CREATED',
            'resourceType' => 'registration',
            'resourceId'   => $regId,
            'metadata'     => [
                'registrationCode'  => $registrationCode,
                'categoryCode'      => $categoryCode,
                'status'            => $initialStatus,
                'hasAccommodations' => $hasAccomm,
                'accountless'       => true,
            ],
        ]);

        jsonResponse([
            'message'      => $initialStatus === 'CONFIRMED'
                ? 'Your Attendee registration is confirmed. A confirmation email has been queued.'
                : 'Your application has been submitted and is pending coordinator review. An acknowledgement email has been queued.',
            'registration' => [
                'id'               => $regId,
                'registrationCode' => $registrationCode,
                'categoryCode'     => $categoryCode,
                'categoryName'     => (string)$categoryRow['name'],
                'status'           => $initialStatus,
                'paymentStatus'    => 'NOT_REQUIRED',
                'assignmentStatus' => 'UNASSIGNED',
                'attendingDays'    => $data['attendingDays'],
                'submittedAt'      => $now,
            ],
            'emailQueued'   => true,
            'emailDelivery' => $emailDelivery,
            'qrPass'        => null,
        ], 201);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/registrations error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process registration right now. Please try again.'], 500);
}
