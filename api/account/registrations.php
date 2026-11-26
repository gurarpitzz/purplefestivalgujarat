<?php
declare(strict_types=1);

/**
 * GET / PATCH / POST /api/account/registrations
 * Purple Festival Gujarat 2027 — Participant Self-Service Registration & Application Lifecycle API (Phases 6–7)
 *
 * Strictly owner-scoped to the authenticated user:
 * - GET: lists user's registrations with category details, accommodations, and participant-safe status history.
 * - PATCH: updates registration when editable (DRAFT, SUBMITTED, ADDITIONAL_INFO_REQUIRED, or CONFIRMED Attendee);
 *          automatically transitions ADDITIONAL_INFO_REQUIRED -> RESUBMITTED and sends ATTENDEE_REGISTRATION_UPDATED.
 * - POST: executes participant-initiated withdraw or cancel via the server-enforced state machine,
 *         enqueues cancellation email, and flags admin audit if already ASSIGNED (never deletes duty rosters).
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/notification-service.php';
require_once dirname(__DIR__, 2) . '/includes/registration-validators.php';
require_once dirname(__DIR__, 2) . '/includes/application-lifecycle.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function pfgLoadOwnerRegistrationDetail(PDO $pdo, array $reg, array $categoryNameMap): array
{
    $regId = (string)$reg['id'];
    $catCode = strtoupper((string)$reg['category_code']);
    $status = strtoupper((string)$reg['status']);

    $stmtAtt = $pdo->prepare('SELECT * FROM attendee_details WHERE registration_id = :rid LIMIT 1');
    $stmtAtt->execute([':rid' => $regId]);
    $att = $stmtAtt->fetch(PDO::FETCH_ASSOC) ?: null;
    $attOut = null;
    if ($att) {
        $attOut = [
            'ageGroup'                 => $att['age_group'] ?? null,
            'gender'                   => $att['gender'] ?? null,
            'selfIdentificationTags'   => jsonDecode($att['self_identification_tags'] ?? '[]', []),
            'guardianName'             => $att['guardian_name'] ?? null,
            'guardianRelationship'     => $att['guardian_relationship'] ?? null,
            'guardianPhone'            => $att['guardian_phone'] ?? null,
            'paraSportsAthlete'        => !empty($att['para_sports_athlete']),
            'paraSportsEvents'         => jsonDecode($att['para_sports_events'] ?? '[]', []),
            'paraSportsOther'          => $att['para_sports_other'] ?? null,
            'outstationTravelRequired' => !empty($att['outstation_travel_required']),
            'arrivalAt'                => $att['arrival_at'] ?? null,
            'departureAt'              => $att['departure_at'] ?? null,
            'travelMode'               => $att['travel_mode'] ?? null,
            'lodgingNeeded'            => !empty($att['lodging_needed']),
            'accessibleRoomNeeded'     => !empty($att['accessible_room_needed']),
            'accompanyingCount'        => $att['accompanying_count'] !== null ? (int)$att['accompanying_count'] : null,
            'foodPreference'           => $att['food_preference'] ?? null,
            'dietaryRequirements'      => pfgDecryptSensitiveValue($att['dietary_requirements_encrypted'] ?? null),
        ];
    }

    $stmtVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
    $stmtVol->execute([':rid' => $regId]);
    $vol = $stmtVol->fetch(PDO::FETCH_ASSOC) ?: null;
    $volOut = null;
    if ($vol) {
        $volOut = [
            'age'                  => $vol['age'] !== null ? (int)$vol['age'] : null,
            'gender'               => $vol['gender'] ?? null,
            'msuAffiliationStatus' => $vol['msu_affiliation_status'] ?? 'EXTERNAL',
            'msuFacultyDepartment' => $vol['msu_faculty_department'] ?? null,
            'institutionName'      => $vol['institution_name'] ?? null,
            'crrNumber'            => $vol['crr_number'] ?? null,
            'primaryRole'          => $vol['primary_role'] ?? null,
            'preferredRoles'       => jsonDecode($vol['preferred_roles'] ?? '[]', []),
            'anchoringExperience'  => $vol['anchoring_experience'] ?? null,
            'relevantExperience'   => $vol['relevant_experience'] ?? null,
            'languagesSpoken'      => jsonDecode($vol['languages_spoken'] ?? '[]', []),
            'languagesOther'       => $vol['languages_other'] ?? null,
            'availabilityDays'     => jsonDecode($vol['availability_days'] ?? '[]', []),
            'availabilityShifts'   => jsonDecode($vol['availability_shifts'] ?? '[]', []),
            'assignedDepartment'   => $vol['assigned_department'] ?? null,
            'assignedVenue'        => $vol['assigned_venue'] ?? null,
            'assignedShift'        => $vol['assigned_shift'] ?? null,
            'reportingTime'        => $vol['reporting_time'] ?? null,
        ];
    }

    $stmtSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
    $stmtSli->execute([':rid' => $regId]);
    $sli = $stmtSli->fetch(PDO::FETCH_ASSOC) ?: null;
    $sliOut = null;
    if ($sli) {
        $rawVideoUrl = isset($sli['video_demo_url']) ? trim((string)$sli['video_demo_url']) : '';
        $safeVideoUrl = $rawVideoUrl !== '' ? pfgValidateSafeUrl($rawVideoUrl, false) : null;
        $sliOut = [
            'age'                       => $sli['age'] !== null ? (int)$sli['age'] : null,
            'basedInGujarat'            => isset($sli['based_in_gujarat']) ? (bool)$sli['based_in_gujarat'] : null,
            'crrNumber'                 => $sli['crr_number'] ?? null,
            'qualificationCategory'     => $sli['qualification_category'] ?? null,
            'certificationLevel'        => $sli['certification_level'] ?? null,
            'qualifications'            => $sli['qualifications'] ?? null,
            'experienceBand'            => $sli['experience_band'] ?? null,
            'trainingInstitute'         => $sli['training_institute'] ?? null,
            'professionalAffiliation'   => $sli['professional_affiliation'] ?? null,
            'areasOfExpertise'          => jsonDecode($sli['areas_of_expertise'] ?? '[]', []),
            'interpretationPreferences' => jsonDecode($sli['interpretation_preferences'] ?? '[]', []),
            'availabilityDays'          => jsonDecode($sli['availability_days'] ?? '[]', []),
            'availabilityShifts'        => jsonDecode($sli['availability_shifts'] ?? '[]', []),
            'comfortablePublicStage'    => $sli['comfortable_public_stage'] !== null ? (bool)$sli['comfortable_public_stage'] : null,
            'videoDemoUrl'              => ($safeVideoUrl !== null && $safeVideoUrl !== '') ? $safeVideoUrl : null,
            'honorariumOrTravelNotes'   => $sli['honorarium_or_travel_notes'] ?? null,
            'assignedVenue'             => $sli['assigned_venue'] ?? null,
            'dutyTimes'                 => $sli['duty_times'] ?? null,
        ];
    }

    $stmtDel = $pdo->prepare('SELECT * FROM delegate_details WHERE registration_id = :rid LIMIT 1');
    $stmtDel->execute([':rid' => $regId]);
    $delDetail = $stmtDel->fetch(PDO::FETCH_ASSOC) ?: null;

    // Owner's own disability disclosures
    $stmtDis = $pdo->prepare('SELECT disability_code, self_description, notes FROM participant_disability_disclosures WHERE registration_id = :rid ORDER BY created_at ASC');
    $stmtDis->execute([':rid' => $regId]);
    $disCodes = [];
    $disSelfDesc = null;
    foreach ($stmtDis->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dRow) {
        if (!empty($dRow['disability_code'])) {
            $disCodes[] = (string)$dRow['disability_code'];
        }
        if ($disSelfDesc === null) {
            $dec = pfgDecryptSensitiveValue($dRow['self_description'] ?? $dRow['notes'] ?? null);
            if ($dec !== null && $dec !== '') {
                $disSelfDesc = $dec;
            }
        }
    }

    // Owner's accommodation requests
    $stmtAcc = $pdo->prepare('SELECT * FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC');
    $stmtAcc->execute([':rid' => $regId]);
    $accommReqs = [];
    foreach ($stmtAcc->fetchAll(PDO::FETCH_ASSOC) ?: [] as $aRow) {
        $accommReqs[] = [
            'id'                 => (string)$aRow['id'],
            'selectedTypes'      => jsonDecode($aRow['selected_types'] ?? '[]', []),
            'freeTextDetails'    => pfgDecryptSensitiveValue($aRow['free_text_details'] ?? $aRow['notes'] ?? null),
            'escortAccompanying' => !empty($aRow['escort_accompanying']),
            'escortName'         => pfgDecryptSensitiveValue($aRow['escort_name_encrypted'] ?? null),
            'status'             => (string)($aRow['status'] ?? 'REQUESTED'),
            'createdAt'          => (string)$aRow['created_at'],
            'updatedAt'          => (string)($aRow['updated_at'] ?? $aRow['created_at']),
        ];
    }

    // Participant-safe status history (NEVER expose internal admin `reason` column!)
    $stmtHist = $pdo->prepare(
        'SELECT id, previous_status, new_status, actor_role, public_message, created_at
         FROM registration_status_history
         WHERE registration_id = :rid
         ORDER BY created_at ASC'
    );
    $stmtHist->execute([':rid' => $regId]);
    $statusHistory = [];
    foreach ($stmtHist->fetchAll(PDO::FETCH_ASSOC) ?: [] as $hRow) {
        $statusHistory[] = [
            'id'             => (string)$hRow['id'],
            'previousStatus' => $hRow['previous_status'] ?? null,
            'newStatus'      => (string)$hRow['new_status'],
            'actorRole'      => $hRow['actor_role'] ?? null,
            'publicMessage'  => $hRow['public_message'] ?? null,
            'createdAt'      => (string)$hRow['created_at'],
        ];
    }

    $allowedNext = pfgGetAllowedStatusTransitions($catCode, $status, 'PARTICIPANT');
    $canEdit = in_array($status, ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true)
        || ($catCode === 'ATTENDEE' && $status === 'CONFIRMED');

    return [
        'id'                           => $regId,
        'registrationCode'             => (string)$reg['registration_code'],
        'editionId'                    => (string)$reg['edition_id'],
        'categoryCode'                 => $catCode,
        'categoryName'                 => $categoryNameMap[$catCode] ?? $catCode,
        'isLegacyCategory'             => !in_array($catCode, PFG_PRIMARY_CATEGORIES, true),
        'status'                       => $status,
        'paymentStatus'                => (string)$reg['payment_status'],
        'assignmentStatus'             => (string)($reg['assignment_status'] ?? 'UNASSIGNED'),
        'fullName'                     => (string)$reg['full_name'],
        'email'                        => (string)$reg['email'],
        'phone'                        => (string)$reg['phone'],
        'country'                      => (string)$reg['country'],
        'cityDistrict'                 => $reg['city_district'] ?? null,
        'state'                        => $reg['state'] ?? null,
        'organizationName'             => $reg['organization_name'] ?? null,
        'designation'                  => $reg['designation'] ?? null,
        'emergencyContactName'         => $reg['emergency_contact_name'] ?? null,
        'emergencyContactRelationship' => $reg['emergency_contact_relationship'] ?? null,
        'emergencyContactPhone'        => $reg['emergency_contact_phone'] ?? null,
        'isPersonWithDisability'       => (string)$reg['is_person_with_disability'],
        'disabilityCategoryCodes'      => $disCodes,
        'disabilitySelfDescription'    => $disSelfDesc,
        'attendingDays'                => jsonDecode($reg['attending_days'] ?? '[]', []),
        'interestedTracks'             => jsonDecode($reg['interested_tracks'] ?? '[]', []),
        'publicReviewNote'             => $reg['public_review_note'] ?? null,
        'submittedAt'                  => (string)($reg['submitted_at'] ?? $reg['created_at']),
        'createdAt'                    => (string)$reg['created_at'],
        'updatedAt'                    => (string)($reg['updated_at'] ?? $reg['created_at']),
        'attendeeDetails'              => $attOut,
        'volunteerApplication'         => $volOut,
        'sliApplication'               => $sliOut,
        'delegateDetails'              => $delDetail,
        'accommodationRequests'        => $accommReqs,
        'statusHistory'                => $statusHistory,
        'allowedParticipantActions'    => [
            'canEdit'     => $canEdit && in_array($catCode, PFG_PRIMARY_CATEGORIES, true),
            'canWithdraw' => in_array('WITHDRAWN', $allowedNext, true),
            'canCancel'   => in_array('CANCELLED', $allowedNext, true),
            'nextStatuses'=> $allowedNext,
        ],
    ];
}

/**
 * Reconciles shift slots when a partial Dashboard edit submits `attendingDays`
 * for VOLUNTEER or SLI without submitting granular `availabilityShifts`.
 */
function pfgReconcileShiftsFromDays(array $existingShifts, array $submittedDays): array
{
    $cleanDays = [];
    foreach ($submittedDays as $d) {
        if (is_string($d)) {
            $du = strtoupper(trim($d));
            if (in_array($du, PFG_VALID_DAYS, true) && !in_array($du, $cleanDays, true)) {
                $cleanDays[] = $du;
            }
        }
    }
    if (empty($cleanDays)) {
        return [];
    }

    $kept = [];
    $coveredDays = [];
    foreach ($existingShifts as $slot) {
        if (!is_string($slot)) {
            continue;
        }
        $su = strtoupper(trim($slot));
        if (isset(PFG_SHIFT_SLOTS[$su])) {
            $slotDay = PFG_SHIFT_SLOTS[$su]['day'];
            if (in_array($slotDay, $cleanDays, true)) {
                $kept[] = $su;
                $coveredDays[$slotDay] = true;
            }
        }
    }

    foreach ($cleanDays as $day) {
        if (!isset($coveredDays[$day])) {
            foreach ([$day . '_SHIFT_1', $day . '_SHIFT_2'] as $defaultSlot) {
                if (isset(PFG_SHIFT_SLOTS[$defaultSlot])) {
                    $kept[] = $defaultSlot;
                }
            }
        }
    }

    return array_values(array_unique($kept));
}

/**
 * Merges omitted top-level, emergency contact, accessibility, and category child-table
 * fields from the existing DB record into a partial PATCH payload before validation (HIGH-07).
 */
function pfgMergeExistingRegistrationPayload(PDO $pdo, array $reg, string $categoryCode, array $body): array
{
    $regId = (string)$reg['id'];
    $merged = $body;

    // 1. Top-level registrations fields
    if (!array_key_exists('phone', $merged)) {
        $merged['phone'] = (string)($reg['phone'] ?? '');
    }
    if (!array_key_exists('country', $merged)) {
        $merged['country'] = (string)($reg['country'] ?? 'India');
    }
    if (!array_key_exists('cityDistrict', $merged) && !array_key_exists('city_district', $merged)) {
        $merged['cityDistrict'] = (string)($reg['city_district'] ?? '');
    }
    if (!array_key_exists('state', $merged)) {
        $merged['state'] = (string)($reg['state'] ?? '');
    }
    if (!array_key_exists('organizationName', $merged) && !array_key_exists('organization_name', $merged)) {
        $merged['organizationName'] = (string)($reg['organization_name'] ?? '');
    }
    if (!array_key_exists('designation', $merged)) {
        $merged['designation'] = (string)($reg['designation'] ?? '');
    }
    if (!array_key_exists('attendingDays', $merged) && !array_key_exists('attending_days', $merged)) {
        $existingDays = jsonDecode($reg['attending_days'] ?? '[]', []);
        $merged['attendingDays'] = is_array($existingDays) ? $existingDays : [];
    }
    if (!array_key_exists('interestedTracks', $merged) && !array_key_exists('interested_tracks', $merged)) {
        $existingTracks = jsonDecode($reg['interested_tracks'] ?? '[]', []);
        $merged['interestedTracks'] = is_array($existingTracks) ? $existingTracks : [];
    }

    // 2. Emergency contact fields (check top-level and nested child payload)
    $nestedKey = $categoryCode === 'ATTENDEE'
        ? 'attendeeDetails'
        : ($categoryCode === 'VOLUNTEER' ? 'volunteerDetails' : 'sliDetails');
    $nestedIn = isset($body[$nestedKey]) && is_array($body[$nestedKey]) ? $body[$nestedKey] : [];

    if (
        !array_key_exists('emergencyContactName', $merged)
        && !array_key_exists('emergency_contact_name', $merged)
        && !array_key_exists('emergencyContactName', $nestedIn)
    ) {
        $merged['emergencyContactName'] = (string)($reg['emergency_contact_name'] ?? '');
    }
    if (
        !array_key_exists('emergencyContactRelationship', $merged)
        && !array_key_exists('emergency_contact_relationship', $merged)
        && !array_key_exists('emergencyContactRelationship', $nestedIn)
    ) {
        $merged['emergencyContactRelationship'] = (string)($reg['emergency_contact_relationship'] ?? '');
    }
    if (
        !array_key_exists('emergencyContactPhone', $merged)
        && !array_key_exists('emergency_contact_phone', $merged)
        && !array_key_exists('emergencyContactPhone', $nestedIn)
    ) {
        $merged['emergencyContactPhone'] = (string)($reg['emergency_contact_phone'] ?? '');
    }

    // 3. Shared accessibility & accommodation fields (when omitted in partial PATCH)
    if (!array_key_exists('isPersonWithDisability', $merged) && !array_key_exists('is_person_with_disability', $merged)) {
        $merged['isPersonWithDisability'] = (string)($reg['is_person_with_disability'] ?? 'PREFER_NOT_TO_SAY');
    }

    $needsDisMerge = (!array_key_exists('disabilityCategoryCodes', $merged) && !array_key_exists('disability_category_codes', $merged))
        || (!array_key_exists('disabilitySelfDescription', $merged) && !array_key_exists('disability_self_description', $merged));
    if ($needsDisMerge) {
        $stmtDis = $pdo->prepare('SELECT disability_code, self_description, notes FROM participant_disability_disclosures WHERE registration_id = :rid ORDER BY created_at ASC');
        $stmtDis->execute([':rid' => $regId]);
        $disCodes = [];
        $disDesc = '';
        foreach ($stmtDis->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dr) {
            if (!empty($dr['disability_code'])) {
                $disCodes[] = (string)$dr['disability_code'];
            }
            if ($disDesc === '') {
                $dec = pfgDecryptSensitiveValue($dr['self_description'] ?? $dr['notes'] ?? null);
                if (is_string($dec) && $dec !== '') {
                    $disDesc = $dec;
                }
            }
        }
        if (!array_key_exists('disabilityCategoryCodes', $merged) && !array_key_exists('disability_category_codes', $merged)) {
            $merged['disabilityCategoryCodes'] = $disCodes;
        }
        if (!array_key_exists('disabilitySelfDescription', $merged) && !array_key_exists('disability_self_description', $merged)) {
            $merged['disabilitySelfDescription'] = $disDesc;
        }
    }

    $needsAccMerge = (!array_key_exists('accommodationTypes', $merged) && !array_key_exists('accommodation_types', $merged))
        || (!array_key_exists('accommodationFreeText', $merged) && !array_key_exists('accommodation_free_text', $merged))
        || (!array_key_exists('escortAccompanying', $merged) && !array_key_exists('escort_accompanying', $merged))
        || (!array_key_exists('escortName', $merged) && !array_key_exists('escort_name', $merged));
    if ($needsAccMerge) {
        $stmtAcc = $pdo->prepare(
            "SELECT * FROM accommodation_requests
             WHERE registration_id = :rid AND UPPER(status) <> 'CANCELLED'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmtAcc->execute([':rid' => $regId]);
        $accRow = $stmtAcc->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!array_key_exists('accommodationTypes', $merged) && !array_key_exists('accommodation_types', $merged)) {
            $merged['accommodationTypes'] = $accRow ? (jsonDecode($accRow['selected_types'] ?? '[]', []) ?: []) : [];
        }
        if (!array_key_exists('accommodationFreeText', $merged) && !array_key_exists('accommodation_free_text', $merged)) {
            $merged['accommodationFreeText'] = $accRow
                ? (string)(pfgDecryptSensitiveValue($accRow['free_text_details'] ?? $accRow['notes'] ?? null) ?? '')
                : '';
        }
        if (!array_key_exists('escortAccompanying', $merged) && !array_key_exists('escort_accompanying', $merged)) {
            $merged['escortAccompanying'] = $accRow ? !empty($accRow['escort_accompanying']) : false;
        }
        if (!array_key_exists('escortName', $merged) && !array_key_exists('escort_name', $merged)) {
            $merged['escortName'] = $accRow
                ? (string)(pfgDecryptSensitiveValue($accRow['escort_name_encrypted'] ?? null) ?? '')
                : '';
        }
    }

    // 4. Category-specific child record merge
    if ($categoryCode === 'ATTENDEE') {
        $stmtAtt = $pdo->prepare('SELECT * FROM attendee_details WHERE registration_id = :rid LIMIT 1');
        $stmtAtt->execute([':rid' => $regId]);
        $att = $stmtAtt->fetch(PDO::FETCH_ASSOC) ?: [];

        $existingAgeGroup = (string)($att['age_group'] ?? '');
        $existingGuardianName = (string)($att['guardian_name'] ?? '');
        $existingAd = [
            'ageGroup'                 => $existingAgeGroup,
            'gender'                   => (string)($att['gender'] ?? ''),
            'registeringOnBehalf'      => ($existingAgeGroup !== 'UNDER_18' && $existingGuardianName !== ''),
            'selfIdentificationTags'   => jsonDecode($att['self_identification_tags'] ?? '[]', []) ?: [],
            'guardianName'             => $existingGuardianName,
            'guardianRelationship'     => (string)($att['guardian_relationship'] ?? ''),
            'guardianPhone'            => (string)($att['guardian_phone'] ?? ''),
            'paraSportsAthlete'        => !empty($att['para_sports_athlete']),
            'paraSportsEvents'         => jsonDecode($att['para_sports_events'] ?? '[]', []) ?: [],
            'paraSportsOther'          => (string)($att['para_sports_other'] ?? ''),
            'outstationTravelRequired' => !empty($att['outstation_travel_required']),
            'arrivalAt'                => (string)($att['arrival_at'] ?? ''),
            'departureAt'              => (string)($att['departure_at'] ?? ''),
            'travelMode'               => (string)($att['travel_mode'] ?? ''),
            'lodgingNeeded'            => !empty($att['lodging_needed']),
            'accessibleRoomNeeded'     => !empty($att['accessible_room_needed']),
            'accompanyingCount'        => $att['accompanying_count'] ?? null,
            'foodPreference'           => (string)($att['food_preference'] ?? ''),
            'dietaryRequirements'      => (string)(pfgDecryptSensitiveValue($att['dietary_requirements_encrypted'] ?? null) ?? ''),
        ];

        $adSource = isset($body['attendeeDetails']) && is_array($body['attendeeDetails']) ? $body['attendeeDetails'] : [];
        foreach (array_keys($existingAd) as $k) {
            if (!array_key_exists($k, $adSource) && array_key_exists($k, $body)) {
                $adSource[$k] = $body[$k];
            }
        }
        $merged['attendeeDetails'] = array_merge($existingAd, $adSource);
    } elseif ($categoryCode === 'VOLUNTEER') {
        $stmtVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
        $stmtVol->execute([':rid' => $regId]);
        $vol = $stmtVol->fetch(PDO::FETCH_ASSOC) ?: [];

        $existingShifts = jsonDecode($vol['availability_shifts'] ?? '[]', []);
        if (!is_array($existingShifts)) {
            $existingShifts = [];
        }
        $existingVd = [
            'age'                  => $vol['age'] ?? null,
            'gender'               => (string)($vol['gender'] ?? ''),
            'msuAffiliation'       => (string)($vol['msu_affiliation_status'] ?? (!empty($vol['is_msu_student_or_alumni']) ? 'CURRENT_STUDENT' : 'EXTERNAL')),
            'msuFacultyDepartment' => (string)($vol['msu_faculty_department'] ?? ''),
            'institutionName'      => (string)($vol['institution_name'] ?? ''),
            'crrNumber'            => (string)($vol['crr_number'] ?? ''),
            'primaryRole'          => (string)($vol['primary_role'] ?? ''),
            'secondaryRoles'       => jsonDecode($vol['preferred_roles'] ?? '[]', []) ?: [],
            'anchoringExperience'  => (string)($vol['anchoring_experience'] ?? ''),
            'relevantExperience'   => (string)($vol['relevant_experience'] ?? ''),
            'languagesSpoken'      => jsonDecode($vol['languages_spoken'] ?? '[]', []) ?: [],
            'languagesOther'       => (string)($vol['languages_other'] ?? ''),
            'availabilityShifts'   => $existingShifts,
        ];

        $vdSource = isset($body['volunteerDetails']) && is_array($body['volunteerDetails']) ? $body['volunteerDetails'] : [];
        foreach (array_keys($existingVd) as $k) {
            if (!array_key_exists($k, $vdSource) && array_key_exists($k, $body)) {
                $vdSource[$k] = $body[$k];
            }
        }
        // Avoid shadowing alias keys submitted by full-form callers (e.g. msuAffiliationStatus / preferredRoles)
        if (
            array_key_exists('msuAffiliationStatus', $vdSource)
            || array_key_exists('msu_affiliation_status', $vdSource)
            || array_key_exists('msu_affiliation', $vdSource)
            || array_key_exists('msuAffiliationStatus', $body)
        ) {
            unset($existingVd['msuAffiliation']);
            if (!array_key_exists('msuAffiliationStatus', $vdSource) && array_key_exists('msuAffiliationStatus', $body)) {
                $vdSource['msuAffiliationStatus'] = $body['msuAffiliationStatus'];
            }
        }
        if (
            array_key_exists('preferredRoles', $vdSource)
            || array_key_exists('preferred_roles', $vdSource)
            || array_key_exists('preferredRoles', $body)
        ) {
            unset($existingVd['secondaryRoles']);
            if (!array_key_exists('preferredRoles', $vdSource) && array_key_exists('preferredRoles', $body)) {
                $vdSource['preferredRoles'] = $body['preferredRoles'];
            }
        }
        if (
            !array_key_exists('institutionName', $vdSource)
            && !array_key_exists('institution_name', $vdSource)
            && (array_key_exists('organizationName', $body) || array_key_exists('organization_name', $body))
        ) {
            $vdSource['institutionName'] = (string)($body['organizationName'] ?? $body['organization_name'] ?? '');
        }
        $hasExplicitShifts = array_key_exists('availabilityShifts', $vdSource)
            || array_key_exists('availability_shifts', $vdSource)
            || array_key_exists('availabilityShifts', $body)
            || array_key_exists('availability_shifts', $body);
        if (!$hasExplicitShifts && (array_key_exists('attendingDays', $body) || array_key_exists('attending_days', $body))) {
            $subDays = $body['attendingDays'] ?? $body['attending_days'] ?? [];
            if (is_array($subDays)) {
                $vdSource['availabilityShifts'] = pfgReconcileShiftsFromDays($existingShifts, $subDays);
            }
        }
        $merged['volunteerDetails'] = array_merge($existingVd, $vdSource);
    } elseif ($categoryCode === 'SLI') {
        $stmtSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
        $stmtSli->execute([':rid' => $regId]);
        $sli = $stmtSli->fetch(PDO::FETCH_ASSOC) ?: [];

        $existingShifts = jsonDecode($sli['availability_shifts'] ?? '[]', []);
        if (!is_array($existingShifts)) {
            $existingShifts = [];
        }
        $fallbackCity = (string)($reg['city_district'] ?? '');
        $fallbackState = (string)($reg['state'] ?? '');
        if (($fallbackCity === '' || $fallbackState === '') && !empty($sli['city_state'])) {
            $parts = array_map('trim', explode(',', (string)$sli['city_state'], 2));
            if ($fallbackCity === '' && isset($parts[0])) {
                $fallbackCity = $parts[0];
            }
            if ($fallbackState === '' && isset($parts[1])) {
                $fallbackState = $parts[1];
            }
        }

        $existingSd = [
            'age'                       => $sli['age'] ?? null,
            'cityDistrict'              => $fallbackCity,
            'state'                     => $fallbackState,
            'basedInGujarat'            => isset($sli['based_in_gujarat']) && $sli['based_in_gujarat'] !== null ? (bool)$sli['based_in_gujarat'] : null,
            'professionalAffiliation'   => (string)($sli['professional_affiliation'] ?? ''),
            'qualificationCategory'     => (string)($sli['qualification_category'] ?? ''),
            'qualifications'            => (string)($sli['qualifications'] ?? ''),
            'crrNumber'                 => (string)($sli['crr_number'] ?? ''),
            'experienceBand'            => (string)($sli['experience_band'] ?? ''),
            'trainingInstitute'         => (string)($sli['training_institute'] ?? ''),
            'videoDemoUrl'              => (string)($sli['video_demo_url'] ?? ''),
            'comfortablePublicStage'    => isset($sli['comfortable_public_stage']) && $sli['comfortable_public_stage'] !== null ? (bool)$sli['comfortable_public_stage'] : null,
            'availabilityShifts'        => $existingShifts,
            'areasOfExpertise'          => jsonDecode($sli['areas_of_expertise'] ?? '[]', []) ?: [],
            'interpretationPreferences' => jsonDecode($sli['interpretation_preferences'] ?? '[]', []) ?: [],
            'honorariumOrTravelNotes'   => (string)($sli['honorarium_or_travel_notes'] ?? ''),
        ];

        $sdSource = isset($body['sliDetails']) && is_array($body['sliDetails']) ? $body['sliDetails'] : [];
        foreach (array_keys($existingSd) as $k) {
            if (!array_key_exists($k, $sdSource) && array_key_exists($k, $body)) {
                $sdSource[$k] = $body[$k];
            }
        }
        if (array_key_exists('qualificationDetail', $sdSource) || array_key_exists('qualificationDetail', $body)) {
            unset($existingSd['qualifications']);
            if (!array_key_exists('qualificationDetail', $sdSource) && array_key_exists('qualificationDetail', $body)) {
                $sdSource['qualificationDetail'] = $body['qualificationDetail'];
            }
        }
        if (!array_key_exists('cityDistrict', $sdSource) && !array_key_exists('city_district', $sdSource) && (array_key_exists('cityDistrict', $body) || array_key_exists('city_district', $body))) {
            $sdSource['cityDistrict'] = (string)($body['cityDistrict'] ?? $body['city_district'] ?? '');
        }
        if (!array_key_exists('state', $sdSource) && array_key_exists('state', $body)) {
            $sdSource['state'] = (string)$body['state'];
        }
        if (
            !array_key_exists('professionalAffiliation', $sdSource)
            && !array_key_exists('professional_affiliation', $sdSource)
            && (array_key_exists('organizationName', $body) || array_key_exists('organization_name', $body))
        ) {
            $sdSource['professionalAffiliation'] = (string)($body['organizationName'] ?? $body['organization_name'] ?? '');
        }
        $hasExplicitShifts = array_key_exists('availabilityShifts', $sdSource)
            || array_key_exists('availability_shifts', $sdSource)
            || array_key_exists('availabilityShifts', $body)
            || array_key_exists('availability_shifts', $body);
        if (!$hasExplicitShifts && (array_key_exists('attendingDays', $body) || array_key_exists('attending_days', $body))) {
            $subDays = $body['attendingDays'] ?? $body['attending_days'] ?? [];
            if (is_array($subDays)) {
                $sdSource['availabilityShifts'] = pfgReconcileShiftsFromDays($existingShifts, $subDays);
            }
        }
        $merged['sliDetails'] = array_merge($existingSd, $sdSource);
    }

    return $merged;
}

try {
    $pdo = getDb();
    $user = getCurrentUser($pdo);
    if (!$user || empty($user['id'])) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }
    enforceStaffMfaGate($user);
    $userId = (string)$user['id'];

    $catMap = [];
    $stmtCats = $pdo->query('SELECT code, name FROM registration_categories');
    if ($stmtCats) {
        foreach ($stmtCats->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
            $catMap[strtoupper((string)$c['code'])] = (string)$c['name'];
        }
    }

    // ========================================================================
    // GET /api/account/registrations
    // ========================================================================
    if ($method === 'GET') {
        $filterId = isset($_GET['id']) && is_string($_GET['id']) ? trim($_GET['id']) : '';
        $filterCat = isset($_GET['category']) && is_string($_GET['category']) ? strtoupper(trim($_GET['category'])) : '';

        $sql = 'SELECT * FROM registrations WHERE user_id = :uid AND deleted_at IS NULL';
        $params = [':uid' => $userId];

        if ($filterId !== '') {
            $sql .= ' AND id = :rid';
            $params[':rid'] = $filterId;
        }
        if ($filterCat !== '') {
            $sql .= ' AND UPPER(category_code) = :cat';
            $params[':cat'] = $filterCat;
        }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = pfgLoadOwnerRegistrationDetail($pdo, $row, $catMap);
        }

        jsonResponse([
            'registrations' => $items,
            'count'         => count($items),
        ], 200);
    }

    // ========================================================================
    // PATCH /api/account/registrations — Edit editable registration or resubmit info
    // ========================================================================
    if ($method === 'PATCH') {
        requireCsrfToken($user);
        $body = readRequestInput();
        $regId = trim((string)($body['registrationId'] ?? $body['id'] ?? $_GET['id'] ?? ''));
        if ($regId === '') {
            jsonResponse(['error' => 'registrationId is required.'], 400);
        }

        $stmtReg = $pdo->prepare(
            'SELECT * FROM registrations WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmtReg->execute([':id' => $regId, ':uid' => $userId]);
        $reg = $stmtReg->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            jsonResponse(['error' => 'Registration not found for your account.'], 404);
        }

        $categoryCode = strtoupper((string)$reg['category_code']);
        $currentStatus = strtoupper((string)$reg['status']);

        if (!in_array($categoryCode, PFG_PRIMARY_CATEGORIES, true)) {
            jsonResponse(['error' => 'Historical legacy registrations are read-only and cannot be edited.'], 400);
        }

        $editable = in_array($currentStatus, ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true)
            || ($categoryCode === 'ATTENDEE' && $currentStatus === 'CONFIRMED');
        if (!$editable) {
            jsonResponse([
                'error' => "This registration cannot be edited while its status is {$currentStatus}.",
            ], 400);
        }

        $stmtUser = $pdo->prepare(
            'SELECT id, email, email_verified_at, full_name, phone, country, city_district, state, preferred_language FROM users WHERE id = :id LIMIT 1'
        );
        $stmtUser->execute([':id' => $userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: $user;

        // Defense-in-depth email verification check (LOW-03)
        if (empty($userRow['email_verified_at'])) {
            jsonResponse([
                'error' => 'Please verify your email address before editing or resubmitting your registration.',
                'code'  => 'EMAIL_VERIFICATION_REQUIRED',
            ], 403);
        }

        // Merge omitted existing DB record fields so partial dashboard edits succeed cleanly (HIGH-07)
        $mergedBody = pfgMergeExistingRegistrationPayload($pdo, $reg, $categoryCode, $body);

        $validation = pfgValidateRegistrationPayload($pdo, $categoryCode, $mergedBody, $userRow, true);
        if (!$validation['valid']) {
            jsonResponse([
                'error'  => $validation['error'] ?? 'Please review the highlighted fields.',
                'errors' => $validation['errors'],
            ], 400);
        }

        $resubmissionNote = trim((string)($body['resubmissionNote'] ?? $body['resubmission_note'] ?? ''));
        if (mb_strlen($resubmissionNote, 'UTF-8') > 1000) {
            $resubmissionNote = mb_substr($resubmissionNote, 0, 1000, 'UTF-8');
        }

        $vData = $validation['data'];
        $access = $vData['accessibility'];
        $ec = $vData['emergencyContact'];
        $catData = $vData['categoryData'];
        $now = nowSql();
        $resubmitted = false;
        $outboxQueueIds = [];

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE registrations
                 SET phone = :phone,
                     country = :country,
                     city_district = :city_district,
                     state = :state,
                     emergency_contact_name = :ec_name,
                     emergency_contact_relationship = :ec_rel,
                     emergency_contact_phone = :ec_phone,
                     occupation = :occupation,
                     organization_name = :org,
                     designation = :desig,
                     is_person_with_disability = :is_pwd,
                     attending_days = :days,
                     interested_tracks = :tracks,
                     updated_at = :now
                 WHERE id = :id AND user_id = :uid'
            )->execute([
                ':phone'         => $vData['phone'],
                ':country'       => $vData['country'],
                ':city_district' => $vData['cityDistrict'],
                ':state'         => $vData['state'],
                ':ec_name'       => $ec['emergencyContactName'],
                ':ec_rel'        => $ec['emergencyContactRelationship'],
                ':ec_phone'      => $ec['emergencyContactPhone'],
                ':occupation'    => $vData['occupation'],
                ':org'           => $vData['organizationName'],
                ':desig'         => $vData['designation'],
                ':is_pwd'        => $access['isPersonWithDisability'],
                ':days'          => jsonEncode($vData['attendingDays']),
                ':tracks'        => jsonEncode($vData['interestedTracks']),
                ':now'           => $now,
                ':id'            => $regId,
                ':uid'           => $userId,
            ]);

            if ($categoryCode === 'ATTENDEE') {
                $encDietary = pfgEncryptSensitiveValue($catData['dietaryRequirements']);
                $chk = $pdo->prepare('SELECT id FROM attendee_details WHERE registration_id = :rid LIMIT 1');
                $chk->execute([':rid' => $regId]);
                if ($chk->fetchColumn()) {
                    $pdo->prepare(
                        'UPDATE attendee_details
                         SET age_group = :age_group, gender = :gender, self_identification_tags = :self_tags,
                             guardian_name = :g_name, guardian_relationship = :g_rel, guardian_phone = :g_phone,
                             para_sports_athlete = :ps_ath, para_sports_events = :ps_events, para_sports_other = :ps_other,
                             outstation_travel_required = :outstation, arrival_at = :arrival_at, departure_at = :departure_at,
                             travel_mode = :travel_mode, lodging_needed = :lodging, accessible_room_needed = :acc_room,
                             accompanying_count = :acc_count, food_preference = :food_pref,
                             dietary_requirements_encrypted = :dietary_enc, updated_at = :now
                         WHERE registration_id = :rid'
                    )->execute([
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
                        ':now'         => $now,
                        ':rid'         => $regId,
                    ]);
                } else {
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
                        ':rid'         => $regId,
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
                }
            } elseif ($categoryCode === 'VOLUNTEER') {
                $cityStateCompat = trim(($vData['cityDistrict'] ?? '') . (!empty($vData['state']) ? ', ' . $vData['state'] : ''));
                $pdo->prepare(
                    'UPDATE volunteer_applications
                     SET age = :age, gender = :gender, city_state = :city_state, institution_name = :inst_name,
                         is_msu_student_or_alumni = :is_msu, msu_affiliation_status = :msu_status,
                         msu_faculty_department = :msu_fac, crr_number = :crr, primary_role = :primary_role,
                         preferred_roles = :pref_roles, anchoring_experience = :anch_exp, relevant_experience = :rel_exp,
                         availability_days = :avail_days, availability_shifts = :avail_shifts,
                         languages_spoken = :langs, languages_other = :langs_other,
                         emergency_contact_name = :ec_name, emergency_contact_phone = :ec_phone,
                         updated_at = :now
                     WHERE registration_id = :rid'
                )->execute([
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
                    ':now'          => $now,
                    ':rid'          => $regId,
                ]);
            } elseif ($categoryCode === 'SLI') {
                $pdo->prepare(
                    'UPDATE sli_applications
                     SET age = :age, city_state = :city_state, based_in_gujarat = :in_guj, crr_number = :crr,
                         qualification_category = :qual_cat, certification_level = :cert_lvl, qualifications = :quals,
                         experience_band = :exp_band, training_institute = :institute, professional_affiliation = :prof_aff,
                         areas_of_expertise = :expertise, interpretation_preferences = :prefs,
                         availability_days = :avail_days, availability_shifts = :avail_shifts,
                         comfortable_public_stage = :comfort_stage, video_demo_url = :video_url,
                         honorarium_or_travel_notes = :hon_notes, updated_at = :now
                     WHERE registration_id = :rid'
                )->execute([
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
                    ':now'           => $now,
                    ':rid'           => $regId,
                ]);
            }

            // Replace participant's own disability disclosures cleanly if accessibility section submitted
            if (array_key_exists('isPersonWithDisability', $body) || array_key_exists('disabilityCategoryCodes', $body)) {
                $pdo->prepare('DELETE FROM participant_disability_disclosures WHERE registration_id = :rid')
                    ->execute([':rid' => $regId]);

                $encSelfDesc = pfgEncryptSensitiveValue($access['disabilitySelfDescription']);
                if (
                    $access['isPersonWithDisability'] === 'YES' &&
                    (!empty($access['resolvedDisabilityMap']) || $encSelfDesc !== null)
                ) {
                    if (!empty($access['resolvedDisabilityMap'])) {
                        $insDis = $pdo->prepare(
                            'INSERT INTO participant_disability_disclosures (
                                id, registration_id, disability_category_id, disability_code, category_code, self_description, notes, created_at
                             ) VALUES (:id, :rid, :dis_id, :dcode, :ccode, :self_desc, :notes, :now)'
                        );
                        $idx = 0;
                        foreach ($access['resolvedDisabilityMap'] as $dCode => $dId) {
                            $descVal = ($idx === 0) ? $encSelfDesc : null;
                            $insDis->execute([
                                ':id'        => uuid4(),
                                ':rid'       => $regId,
                                ':dis_id'    => (strlen($dId) === 36) ? $dId : null,
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
                             ) VALUES (:id, :rid, NULL, :self_desc, :notes, :now)'
                        )->execute([
                            ':id'        => uuid4(),
                            ':rid'       => $regId,
                            ':self_desc' => $encSelfDesc,
                            ':notes'     => $encSelfDesc,
                            ':now'       => $now,
                        ]);
                    }
                }
            }

            // Update or create accommodation request if accessibility fields supplied
            if (
                array_key_exists('accommodationTypes', $body)
                || array_key_exists('accommodationFreeText', $body)
                || array_key_exists('escortAccompanying', $body)
            ) {
                $hasAccomm = !empty($access['accommodationTypes'])
                    || $access['accommodationFreeText'] !== ''
                    || !empty($access['escortAccompanying']);

                if ($hasAccomm) {
                    $encAccommNotes = pfgEncryptSensitiveValue($access['accommodationFreeText']);
                    $encEscortName = $access['escortAccompanying'] ? pfgEncryptSensitiveValue($access['escortName']) : null;
                    $firstCode = !empty($access['accommodationTypes']) ? (string)$access['accommodationTypes'][0] : null;

                    $chkAcc = $pdo->prepare('SELECT id FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC LIMIT 1');
                    $chkAcc->execute([':rid' => $regId]);
                    $existingAccId = $chkAcc->fetchColumn();
                    if (is_string($existingAccId) && $existingAccId !== '') {
                        $pdo->prepare(
                            "UPDATE accommodation_requests
                             SET accommodation_code = :acode, selected_types = :sel, free_text_details = :ft,
                                 notes = :notes, escort_accompanying = :escort_acc, escort_name_encrypted = :escort_enc,
                                 status = CASE WHEN UPPER(status) = 'CANCELLED' THEN 'REQUESTED' ELSE status END,
                                 updated_at = :now
                             WHERE id = :id"
                        )->execute([
                            ':acode'      => $firstCode,
                            ':sel'        => jsonEncode($access['accommodationTypes']),
                            ':ft'         => $encAccommNotes,
                            ':notes'      => $encAccommNotes,
                            ':escort_acc' => $access['escortAccompanying'] ? 1 : 0,
                            ':escort_enc' => $encEscortName,
                            ':now'        => $now,
                            ':id'         => $existingAccId,
                        ]);
                    } else {
                        $pdo->prepare(
                            "INSERT INTO accommodation_requests (
                                id, registration_id, user_id, accommodation_code, selected_types,
                                free_text_details, notes, escort_accompanying, escort_name_encrypted,
                                status, created_at, updated_at
                             ) VALUES (
                                :id, :rid, :uid, :acode, :sel, :ft, :notes, :escort_acc, :escort_enc, 'REQUESTED', :now1, :now2
                             )"
                        )->execute([
                            ':id'         => uuid4(),
                            ':rid'        => $regId,
                            ':uid'        => $userId,
                            ':acode'      => $firstCode,
                            ':sel'        => jsonEncode($access['accommodationTypes']),
                            ':ft'         => $encAccommNotes,
                            ':notes'      => $encAccommNotes,
                            ':escort_acc' => $access['escortAccompanying'] ? 1 : 0,
                            ':escort_enc' => $encEscortName,
                            ':now1'       => $now,
                            ':now2'       => $now,
                        ]);
                    }
                } else {
                    // MED-06: Participant explicitly cleared all accommodations -> cancel existing REQUESTED rows
                    $pdo->prepare(
                        "UPDATE accommodation_requests
                         SET accommodation_code = NULL,
                             selected_types = '[]',
                             free_text_details = NULL,
                             notes = NULL,
                             escort_accompanying = 0,
                             escort_name_encrypted = NULL,
                             status = 'CANCELLED',
                             updated_at = :now
                         WHERE registration_id = :rid
                           AND UPPER(status) = 'REQUESTED'"
                    )->execute([
                        ':now' => $now,
                        ':rid' => $regId,
                    ]);
                }
            }

            // If responding to coordinator ADDITIONAL_INFO_REQUIRED -> transition cleanly to RESUBMITTED
            if ($currentStatus === 'ADDITIONAL_INFO_REQUIRED') {
                $resubmitPublicMsg = $resubmissionNote !== ''
                    ? $resubmissionNote
                    : 'Applicant updated details and resubmitted for coordinator review.';
                pfgTransitionRegistrationStatus(
                    $pdo,
                    $reg,
                    'RESUBMITTED',
                    array_merge($user, ['actorType' => 'PARTICIPANT']),
                    $resubmissionNote !== '' ? $resubmissionNote : null,
                    $resubmitPublicMsg
                );
                $resubmitted = true;
            } elseif ($categoryCode === 'ATTENDEE') {
                $qRes = pfgEnqueueNotification($pdo, [
                    'to'              => (string)$reg['email'],
                    'templateCode'    => 'ATTENDEE_REGISTRATION_UPDATED',
                    'variables'       => [
                        'fullName'         => (string)$reg['full_name'],
                        'registrationCode' => (string)$reg['registration_code'],
                        'categoryName'     => $catMap[$categoryCode] ?? 'General Attendee',
                        'status'           => $currentStatus,
                        'updateSummary'    => 'Your attending days, logistics, or support preferences have been saved.',
                    ],
                    'recipientUserId' => $userId,
                    'entityType'      => 'registration',
                    'entityId'        => $regId,
                    'idempotencyKey'  => 'att-upd:' . $regId . ':' . time(),
                    'createInApp'     => true,
                    'inAppTitle'      => "Registration Updated ({$reg['registration_code']})",
                    'inAppMessage'    => "Your Attendee registration ({$reg['registration_code']}) details were updated.",
                    'inAppActionUrl'  => '/dashboard',
                ]);
                $outboxQueueIds[] = (string)$qRes['queueId'];
            }

            $pdo->commit();
        } catch (PfgInvalidTransitionException $txInvalid) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(['error' => $txInvalid->getMessage()], 400);
        } catch (Throwable $txErr) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txErr;
        }

        if (!empty($outboxQueueIds) && defined('MAIL_DRIVER') && MAIL_DRIVER === 'file') {
            foreach ($outboxQueueIds as $qid) {
                try {
                    pfgDispatchQueuedIdNow($pdo, $qid);
                } catch (Throwable $e) {
                }
            }
        }

        logAudit($pdo, [
            'actorUserId'  => $userId,
            'action'       => $resubmitted ? 'APPLICATION_RESUBMITTED' : 'REGISTRATION_UPDATED',
            'resourceType' => 'registration',
            'resourceId'   => $regId,
            'metadata'     => [
                'registrationCode' => (string)$reg['registration_code'],
                'categoryCode'     => $categoryCode,
                'resubmitted'      => $resubmitted,
            ],
        ]);

        $stmtFresh = $pdo->prepare('SELECT * FROM registrations WHERE id = :id LIMIT 1');
        $stmtFresh->execute([':id' => $regId]);
        $freshReg = $stmtFresh->fetch(PDO::FETCH_ASSOC);

        jsonResponse([
            'success'      => true,
            'resubmitted'  => $resubmitted,
            'registration' => $freshReg ? pfgLoadOwnerRegistrationDetail($pdo, $freshReg, $catMap) : null,
            'message'      => $resubmitted
                ? 'Your updated application has been resubmitted for coordinator review.'
                : 'Your registration details have been updated.',
        ], 200);
    }

    // ========================================================================
    // POST /api/account/registrations — Withdraw or Cancel own registration
    // ========================================================================
    if ($method === 'POST') {
        requireCsrfToken($user);
        $body = readRequestInput();
        $action = strtolower(trim((string)($body['action'] ?? '')));
        $regId = trim((string)($body['registrationId'] ?? $body['id'] ?? ''));
        $reason = trim((string)($body['reason'] ?? ''));

        if ($regId === '') {
            jsonResponse(['error' => 'registrationId is required.'], 400);
        }
        if (!in_array($action, ['withdraw', 'cancel'], true)) {
            jsonResponse(['error' => 'Invalid action. Supported actions: withdraw, cancel.'], 400);
        }

        $stmtReg = $pdo->prepare(
            'SELECT * FROM registrations WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmtReg->execute([':id' => $regId, ':uid' => $userId]);
        $reg = $stmtReg->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            jsonResponse(['error' => 'Registration not found for your account.'], 404);
        }

        $catCode = strtoupper((string)$reg['category_code']);
        $targetStatus = ($catCode === 'ATTENDEE')
            ? 'CANCELLED'
            : ($action === 'withdraw' ? 'WITHDRAWN' : 'CANCELLED');

        $publicMsg = $reason !== ''
            ? $reason
            : ($targetStatus === 'WITHDRAWN' ? 'Withdrawn by participant.' : 'Cancelled by participant.');

        try {
            $result = pfgTransitionRegistrationStatus(
                $pdo,
                $reg,
                $targetStatus,
                array_merge($user, ['actorType' => 'PARTICIPANT']),
                $reason !== '' ? $reason : 'Participant self-service ' . $action,
                $publicMsg
            );
        } catch (PfgInvalidTransitionException $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }

        jsonResponse([
            'success'                 => true,
            'transition'              => $result,
            'assignedConflictFlagged' => $result['assignedConflictFlagged'],
            'message'                 => $targetStatus === 'WITHDRAWN'
                ? "Your application ({$reg['registration_code']}) has been withdrawn."
                : "Your registration ({$reg['registration_code']}) has been cancelled.",
        ], 200);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $err) {
    error_log('[/api/account/registrations] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Unable to process registration request.'], 500);
}
