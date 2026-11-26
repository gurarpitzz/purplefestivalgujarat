<?php
declare(strict_types=1);

/**
 * GET /api/account/dashboard (api/account/dashboard.php)
 * Phase 10 — Authenticated Participant Dashboard Aggregation API.
 *
 * Security & Privacy Invariants (§32.5):
 * - Requires authenticated session (`requireAuth()` + `enforceStaffMfaGate()`).
 * - Strictly scopes EVERY participant query with `WHERE user_id = :currentUserId`.
 * - NEVER returns internal `review_notes`, `registration_status_history.reason`,
 *   `duty_assignments.override_reason`, other participants' data, raw secrets,
 *   or QR entry pass credentials.
 * - Decrypts owner-only sensitive fields (`free_text_details`, `escort_name_encrypted`,
 *   `dietary_requirements_encrypted`) strictly for the authenticated record owner.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/application-lifecycle.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed. Use GET.'], 405);
}

/**
 * Decrypts a sensitive field strictly for the authenticated owner.
 */
function pfgDashboardDecrypt(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (function_exists('pfgDecryptSensitiveValue')) {
        $decrypted = pfgDecryptSensitiveValue($value);
        return is_string($decrypted) ? $decrypted : null;
    }
    $decrypted = pfgDecryptValue($value, 'data');
    return is_string($decrypted) ? $decrypted : null;
}

/**
 * Safely normalizes an internal/external URL for participant display.
 */
function pfgDashboardSafeUrl(?string $url, bool $allowRelative = true): ?string
{
    if ($url === null || trim($url) === '') {
        return null;
    }
    $validated = pfgValidateSafeUrl($url, $allowRelative);
    return ($validated !== null && $validated !== '') ? $validated : null;
}

try {
    $authUser = requireAuth();
    enforceStaffMfaGate($authUser);

    $pdo = getDb();
    $currentUserId = (string)$authUser['id'];
    $edition = getActiveEdition($pdo);
    $editionId = is_array($edition) && !empty($edition['id']) ? (string)$edition['id'] : null;

    // -------------------------------------------------------------------------
    // 1. Owner Profile (explicit allowlisted fields ONLY — zero secrets)
    // -------------------------------------------------------------------------
    $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :uid AND deleted_at IS NULL LIMIT 1');
    $userStmt->execute([':uid' => $currentUserId]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow || empty($userRow['is_active'])) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }

    $emailVerified = !empty($userRow['email_verified_at']);
    $userEmailLower = strtolower(trim((string)($userRow['email'] ?? '')));

    $profile = [
        'id'                           => (string)$userRow['id'],
        'fullName'                     => (string)($userRow['full_name'] ?? ''),
        'email'                        => (string)($userRow['email'] ?? ''),
        'emailVerified'                => $emailVerified,
        'pendingEmail'                 => isset($userRow['pending_email']) && trim((string)$userRow['pending_email']) !== ''
            ? (string)$userRow['pending_email']
            : null,
        'phone'                        => isset($userRow['phone']) && $userRow['phone'] !== '' ? (string)$userRow['phone'] : null,
        'cityDistrict'                 => isset($userRow['city_district']) && $userRow['city_district'] !== '' ? (string)$userRow['city_district'] : null,
        'state'                        => isset($userRow['state']) && $userRow['state'] !== '' ? (string)$userRow['state'] : null,
        'country'                      => (string)($userRow['country'] ?? 'India'),
        'preferredLanguage'            => isset($userRow['preferred_language']) && $userRow['preferred_language'] !== '' ? (string)$userRow['preferred_language'] : 'en',
        'emergencyContactName'         => isset($userRow['emergency_contact_name']) && $userRow['emergency_contact_name'] !== '' ? (string)$userRow['emergency_contact_name'] : null,
        'emergencyContactRelationship' => isset($userRow['emergency_contact_relationship']) && $userRow['emergency_contact_relationship'] !== '' ? (string)$userRow['emergency_contact_relationship'] : null,
        'emergencyContactPhone'        => isset($userRow['emergency_contact_phone']) && $userRow['emergency_contact_phone'] !== '' ? (string)$userRow['emergency_contact_phone'] : null,
        'roles'                        => is_array($authUser['roles'] ?? null) ? array_values($authUser['roles']) : ['PARTICIPANT'],
    ];

    // -------------------------------------------------------------------------
    // 2. Owner Registrations (Primary + Historical Legacy, strictly user_id scoped)
    // -------------------------------------------------------------------------
    $catMap = [];
    try {
        $catStmt = $pdo->query('SELECT id, code, name, category_tier FROM registration_categories');
        foreach (($catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $catRow) {
            $catMap[(string)$catRow['id']] = $catRow;
            $catMap['code:' . strtoupper((string)$catRow['code'])] = $catRow;
        }
    } catch (Throwable $e) {
        // Fallback if category_tier column does not exist in older schema snapshot
        try {
            $catStmt = $pdo->query('SELECT id, code, name FROM registration_categories');
            foreach (($catStmt ? $catStmt->fetchAll(PDO::FETCH_ASSOC) : []) as $catRow) {
                $catMap[(string)$catRow['id']] = $catRow;
                $catMap['code:' . strtoupper((string)$catRow['code'])] = $catRow;
            }
        } catch (Throwable $inner) {
            error_log('[dashboard] registration_categories lookup warning: ' . $inner->getMessage());
        }
    }

    $regStmt = $pdo->prepare(
        'SELECT * FROM registrations
         WHERE user_id = :currentUserId AND deleted_at IS NULL
         ORDER BY created_at DESC'
    );
    $regStmt->execute([':currentUserId' => $currentUserId]);
    $rawRegs = $regStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $regIds = [];
    $regMetaById = [];
    foreach ($rawRegs as $r) {
        $rid = (string)$r['id'];
        $regIds[] = $rid;
        $regMetaById[$rid] = [
            'registrationCode' => (string)$r['registration_code'],
            'categoryCode'     => strtoupper((string)$r['category_code']),
        ];
    }

    $attendeeByReg = [];
    $volunteerByReg = [];
    $sliByReg = [];
    $historyByReg = [];
    $dutiesByReg = [];
    $attendanceByReg = [];

    if (count($regIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($regIds), '?'));

        // 2a. Attendee details
        try {
            $attStmt = $pdo->prepare("SELECT * FROM attendee_details WHERE registration_id IN ({$placeholders})");
            $attStmt->execute($regIds);
            foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rid = (string)$row['registration_id'];
                $selfTags = jsonDecode($row['self_identification_tags'] ?? '[]', []);
                $paraEvents = jsonDecode($row['para_sports_events'] ?? '[]', []);
                $attendeeByReg[$rid] = [
                    'ageGroup'                 => $row['age_group'] ?? null,
                    'gender'                   => $row['gender'] ?? null,
                    'selfIdentificationTags'   => is_array($selfTags) ? array_values($selfTags) : [],
                    'guardianName'             => $row['guardian_name'] ?? null,
                    'guardianRelationship'     => $row['guardian_relationship'] ?? null,
                    'guardianPhone'            => $row['guardian_phone'] ?? null,
                    'paraSportsAthlete'        => (bool)($row['para_sports_athlete'] ?? false),
                    'paraSportsEvents'         => is_array($paraEvents) ? array_values($paraEvents) : [],
                    'paraSportsOther'          => $row['para_sports_other'] ?? null,
                    'outstationTravelRequired' => (bool)($row['outstation_travel_required'] ?? false),
                    'arrivalAt'                => $row['arrival_at'] ?? null,
                    'departureAt'              => $row['departure_at'] ?? null,
                    'travelMode'               => $row['travel_mode'] ?? null,
                    'lodgingNeeded'            => (bool)($row['lodging_needed'] ?? false),
                    'accessibleRoomNeeded'     => (bool)($row['accessible_room_needed'] ?? false),
                    'accompanyingCount'        => isset($row['accompanying_count']) && $row['accompanying_count'] !== null ? (int)$row['accompanying_count'] : null,
                    'foodPreference'           => $row['food_preference'] ?? null,
                    'dietaryRequirements'      => pfgDashboardDecrypt(isset($row['dietary_requirements_encrypted']) ? (string)$row['dietary_requirements_encrypted'] : null),
                ];
            }
        } catch (Throwable $e) {
            // Table may not exist in legacy snapshots
        }

        // 2b. Volunteer applications (allowlisted participant-facing fields)
        try {
            $volStmt = $pdo->prepare("SELECT * FROM volunteer_applications WHERE registration_id IN ({$placeholders})");
            $volStmt->execute($regIds);
            foreach ($volStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rid = (string)$row['registration_id'];
                $prefRoles = jsonDecode($row['preferred_roles'] ?? '[]', []);
                $availDays = jsonDecode($row['availability_days'] ?? '[]', []);
                $availShifts = jsonDecode($row['availability_shifts'] ?? '[]', []);
                $langs = jsonDecode($row['languages_spoken'] ?? '[]', []);
                $volunteerByReg[$rid] = [
                    'age'                    => isset($row['age']) && $row['age'] !== null ? (int)$row['age'] : null,
                    'gender'                 => $row['gender'] ?? null,
                    'cityState'              => $row['city_state'] ?? null,
                    'institutionName'        => $row['institution_name'] ?? null,
                    'msuAffiliationStatus'   => $row['msu_affiliation_status'] ?? (!empty($row['is_msu_student_or_alumni']) ? 'MSU_LEGACY_UNSPECIFIED' : 'EXTERNAL'),
                    'msuFacultyDepartment'   => $row['msu_faculty_department'] ?? null,
                    'isMsuStudentOrAlumni'   => (bool)($row['is_msu_student_or_alumni'] ?? false),
                    'crrNumber'              => $row['crr_number'] ?? null,
                    'primaryRole'            => $row['primary_role'] ?? null,
                    'preferredRoles'         => is_array($prefRoles) ? array_values($prefRoles) : [],
                    'availabilityDays'       => is_array($availDays) ? array_values($availDays) : [],
                    'availabilityShifts'     => is_array($availShifts) ? array_values($availShifts) : [],
                    'languagesSpoken'        => is_array($langs) ? array_values($langs) : [],
                    'languagesOther'         => $row['languages_other'] ?? null,
                    'relevantExperience'     => $row['relevant_experience'] ?? null,
                    'anchoringExperience'    => $row['anchoring_experience'] ?? null,
                    'assignedDepartment'     => $row['assigned_department'] ?? null,
                    'assignedResponsibility' => $row['assigned_responsibility'] ?? null,
                    'assignedSupervisor'     => $row['assigned_supervisor'] ?? null,
                    'assignedVenue'          => $row['assigned_venue'] ?? null,
                    'assignedShift'          => $row['assigned_shift'] ?? null,
                    'reportingTime'          => $row['reporting_time'] ?? null,
                ];
            }
        } catch (Throwable $e) {
            error_log('[dashboard] volunteer_applications query warning: ' . $e->getMessage());
        }

        // 2c. SLI applications (allowlisted participant-facing fields)
        try {
            $sliStmt = $pdo->prepare("SELECT * FROM sli_applications WHERE registration_id IN ({$placeholders})");
            $sliStmt->execute($regIds);
            foreach ($sliStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rid = (string)$row['registration_id'];
                $expertise = jsonDecode($row['areas_of_expertise'] ?? '[]', []);
                $prefs = jsonDecode($row['interpretation_preferences'] ?? '[]', []);
                $availDays = jsonDecode($row['availability_days'] ?? '[]', []);
                $availShifts = jsonDecode($row['availability_shifts'] ?? '[]', []);
                $assignedSessions = jsonDecode($row['assigned_sessions'] ?? '[]', []);
                $sliByReg[$rid] = [
                    'age'                       => isset($row['age']) && $row['age'] !== null ? (int)$row['age'] : null,
                    'cityState'                 => $row['city_state'] ?? null,
                    'basedInGujarat'            => (bool)($row['based_in_gujarat'] ?? false),
                    'crrNumber'                 => $row['crr_number'] ?? null,
                    'qualificationCategory'     => $row['qualification_category'] ?? null,
                    'qualifications'            => $row['qualifications'] ?? null,
                    'trainingInstitute'         => $row['training_institute'] ?? null,
                    'certificationLevel'        => $row['certification_level'] ?? null,
                    'experienceBand'            => $row['experience_band'] ?? null,
                    'yearsOfExperience'         => isset($row['years_of_experience']) && $row['years_of_experience'] !== null ? (int)$row['years_of_experience'] : null,
                    'professionalAffiliation'   => $row['professional_affiliation'] ?? null,
                    'areasOfExpertise'          => is_array($expertise) ? array_values($expertise) : [],
                    'interpretationPreferences' => is_array($prefs) ? array_values($prefs) : [],
                    'comfortablePublicStage'    => isset($row['comfortable_public_stage']) && $row['comfortable_public_stage'] !== null ? (bool)$row['comfortable_public_stage'] : null,
                    'availabilityDays'          => is_array($availDays) ? array_values($availDays) : [],
                    'availabilityShifts'        => is_array($availShifts) ? array_values($availShifts) : [],
                    'videoDemoUrl'              => pfgDashboardSafeUrl(isset($row['video_demo_url']) ? (string)$row['video_demo_url'] : null, false),
                    'assignedVenue'             => $row['assigned_venue'] ?? null,
                    'dutyTimes'                 => $row['duty_times'] ?? null,
                    'assignedSessions'          => is_array($assignedSessions) ? array_values($assignedSessions) : [],
                ];
            }
        } catch (Throwable $e) {
            error_log('[dashboard] sli_applications query warning: ' . $e->getMessage());
        }

        // 2d. Registration status history (PUBLIC messages ONLY — never internal `reason` or `actor_user_id`)
        try {
            $histStmt = $pdo->prepare(
                "SELECT id, registration_id, previous_status, new_status, public_message, created_at
                 FROM registration_status_history
                 WHERE registration_id IN ({$placeholders})
                 ORDER BY created_at DESC"
            );
            $histStmt->execute($regIds);
            foreach ($histStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $h) {
                $rid = (string)$h['registration_id'];
                $historyByReg[$rid][] = [
                    'id'             => (string)$h['id'],
                    'previousStatus' => $h['previous_status'] !== null ? (string)$h['previous_status'] : null,
                    'newStatus'      => (string)$h['new_status'],
                    'publicMessage'  => isset($h['public_message']) && trim((string)$h['public_message']) !== '' ? (string)$h['public_message'] : null,
                    'createdAt'      => (string)$h['created_at'],
                ];
            }
        } catch (Throwable $e) {
            // Table may not exist in older snapshots
        }

        // 2e. Duty assignments (joined with venues & festival_sessions, excluding CANCELLED; never exposes override_reason)
        try {
            $dutyStmt = $pdo->prepare(
                "SELECT
                    da.id,
                    da.registration_id,
                    da.participant_category,
                    da.session_id,
                    da.venue_id,
                    da.role_title,
                    da.department,
                    da.duty_date_ist,
                    da.shift_code,
                    da.start_time,
                    da.end_time,
                    da.reporting_supervisor,
                    da.reporting_time,
                    da.reporting_instructions,
                    da.pairing_group_id,
                    da.status,
                    v.name AS venue_name,
                    v.address AS venue_address,
                    v.confirmation_status AS venue_confirmation_status,
                    v.map_url AS venue_map_url,
                    fs.title AS session_title,
                    fs.track AS session_track,
                    fs.day_number AS session_day_number,
                    fs.status AS session_status
                 FROM duty_assignments da
                 LEFT JOIN venues v ON v.id = da.venue_id
                 LEFT JOIN festival_sessions fs ON fs.id = da.session_id
                 WHERE da.registration_id IN ({$placeholders})
                   AND da.status <> 'CANCELLED'
                 ORDER BY da.duty_date_ist ASC, da.start_time ASC"
            );
            $dutyStmt->execute($regIds);
            $dutyRows = $dutyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Count active assignments per pairing_group_id (for 2-SLI pairing indicator without leaking partner PII)
            $pairingGroupIds = [];
            foreach ($dutyRows as $dr) {
                if (!empty($dr['pairing_group_id'])) {
                    $pairingGroupIds[(string)$dr['pairing_group_id']] = true;
                }
            }
            $pairingCounts = [];
            if (count($pairingGroupIds) > 0) {
                $pgKeys = array_keys($pairingGroupIds);
                $pgPlaceholders = implode(',', array_fill(0, count($pgKeys), '?'));
                $pgStmt = $pdo->prepare(
                    "SELECT pairing_group_id, COUNT(*) AS cnt
                     FROM duty_assignments
                     WHERE pairing_group_id IN ({$pgPlaceholders}) AND status <> 'CANCELLED'
                     GROUP BY pairing_group_id"
                );
                $pgStmt->execute($pgKeys);
                foreach ($pgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pgRow) {
                    $pairingCounts[(string)$pgRow['pairing_group_id']] = (int)$pgRow['cnt'];
                }
            }

            foreach ($dutyRows as $dr) {
                $rid = (string)$dr['registration_id'];
                $pgId = isset($dr['pairing_group_id']) && $dr['pairing_group_id'] !== '' ? (string)$dr['pairing_group_id'] : null;
                $pairedCount = $pgId !== null ? ($pairingCounts[$pgId] ?? 1) : 1;
                $sessionIsPublic = isset($dr['session_status']) && in_array(strtoupper((string)$dr['session_status']), ['CONFIRMED', 'PUBLISHED'], true);

                $dutiesByReg[$rid][] = [
                    'id'                      => (string)$dr['id'],
                    'participantCategory'     => (string)$dr['participant_category'],
                    'sessionId'               => $sessionIsPublic && $dr['session_id'] !== null ? (string)$dr['session_id'] : null,
                    'sessionTitle'            => $sessionIsPublic && $dr['session_title'] !== null ? (string)$dr['session_title'] : null,
                    'sessionTrack'            => $sessionIsPublic && $dr['session_track'] !== null ? (string)$dr['session_track'] : null,
                    'sessionDayNumber'        => $sessionIsPublic && $dr['session_day_number'] !== null ? (int)$dr['session_day_number'] : null,
                    'sessionConfirmed'        => $sessionIsPublic,
                    'venueId'                 => $dr['venue_id'] !== null ? (string)$dr['venue_id'] : null,
                    'venueName'               => $dr['venue_name'] !== null ? (string)$dr['venue_name'] : null,
                    'venueAddress'            => $dr['venue_address'] !== null ? (string)$dr['venue_address'] : null,
                    'venueConfirmationStatus' => $dr['venue_confirmation_status'] !== null ? (string)$dr['venue_confirmation_status'] : null,
                    'venueMapUrl'             => pfgDashboardSafeUrl(isset($dr['venue_map_url']) ? (string)$dr['venue_map_url'] : null, false),
                    'roleTitle'               => (string)$dr['role_title'],
                    'department'              => $dr['department'] !== null ? (string)$dr['department'] : null,
                    'dutyDateIst'             => (string)$dr['duty_date_ist'],
                    'shiftCode'               => (string)$dr['shift_code'],
                    'startTime'               => (string)$dr['start_time'],
                    'endTime'                 => (string)$dr['end_time'],
                    'reportingSupervisor'     => $dr['reporting_supervisor'] !== null ? (string)$dr['reporting_supervisor'] : null,
                    'reportingTime'           => $dr['reporting_time'] !== null ? (string)$dr['reporting_time'] : null,
                    'reportingInstructions'   => $dr['reporting_instructions'] !== null ? (string)$dr['reporting_instructions'] : null,
                    'pairingGroupId'          => $pgId,
                    'isPairedInterpretation'  => $pgId !== null && $pairedCount >= 2,
                    'pairedInterpretersCount' => $pgId !== null ? $pairedCount : 1,
                    'status'                  => (string)$dr['status'],
                ];
            }
        } catch (Throwable $e) {
            // Table may not exist in older snapshots
        }

        // 2f. Attendance summary (read-only days marked PRESENT in attendance_records — ZERO QR pass references)
        try {
            $arStmt = $pdo->prepare(
                "SELECT registration_id, attendance_date_ist, attendance_scope, venue_label, marked_at
                 FROM attendance_records
                 WHERE registration_id IN ({$placeholders})
                   AND status = 'PRESENT'
                 ORDER BY attendance_date_ist ASC, marked_at ASC"
            );
            $arStmt->execute($regIds);
            foreach ($arStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ar) {
                $rid = (string)$ar['registration_id'];
                $attendanceByReg[$rid][] = [
                    'dateIst'    => (string)$ar['attendance_date_ist'],
                    'scope'      => (string)($ar['attendance_scope'] ?? 'FESTIVAL_DAY'),
                    'venueLabel' => $ar['venue_label'] !== null ? (string)$ar['venue_label'] : null,
                    'markedAt'   => (string)$ar['marked_at'],
                ];
            }
        } catch (Throwable $e) {
            // Table may not exist in older snapshots
        }
    }

    $primaryCodes = ['ATTENDEE', 'VOLUNTEER', 'SLI'];
    $activePrimaryCategories = [];
    $registrations = [];

    foreach ($rawRegs as $r) {
        $rid = (string)$r['id'];
        $catCode = strtoupper((string)$r['category_code']);
        $catInfo = $catMap[(string)($r['category_id'] ?? '')] ?? $catMap['code:' . $catCode] ?? null;
        $catName = is_array($catInfo) && !empty($catInfo['name']) ? (string)$catInfo['name'] : $catCode;
        $catTier = is_array($catInfo) && isset($catInfo['category_tier'])
            ? strtoupper((string)$catInfo['category_tier'])
            : (in_array($catCode, $primaryCodes, true) ? 'PRIMARY' : 'LEGACY');

        $isLegacy = ($catTier === 'LEGACY') || !in_array($catCode, $primaryCodes, true);
        $status = strtoupper((string)($r['status'] ?? 'SUBMITTED'));
        $assignmentStatus = strtoupper((string)($r['assignment_status'] ?? 'UNASSIGNED'));

        if (!$isLegacy && !in_array($status, ['CANCELLED', 'WITHDRAWN', 'REJECTED'], true)) {
            $activePrimaryCategories[$catCode] = true;
        }

        $attendingDays = jsonDecode($r['attending_days'] ?? '[]', []);
        if (!is_array($attendingDays)) {
            $attendingDays = [];
        }
        $interestedTracks = jsonDecode($r['interested_tracks'] ?? '[]', []);
        if (!is_array($interestedTracks)) {
            $interestedTracks = [];
        }

        $allowedNext = !$isLegacy
            ? pfgGetAllowedStatusTransitions($catCode, $status, 'PARTICIPANT')
            : [];
        $canEdit = !$isLegacy && (
            in_array($status, ['DRAFT', 'SUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true)
            || ($catCode === 'ATTENDEE' && $status === 'CONFIRMED')
        );
        $canWithdraw = !$isLegacy && (
            in_array('WITHDRAWN', $allowedNext, true)
            || in_array('CANCELLED', $allowedNext, true)
        );

        $presentDays = $attendanceByReg[$rid] ?? [];
        $uniqueDates = [];
        foreach ($presentDays as $pd) {
            $uniqueDates[$pd['dateIst']] = true;
        }

        // NEVER include `review_notes` (internal admin notes) — ONLY `public_review_note`!
        $publicReviewNote = isset($r['public_review_note']) && trim((string)$r['public_review_note']) !== ''
            ? (string)$r['public_review_note']
            : null;

        $registrations[] = [
            'id'                           => $rid,
            'registrationCode'             => (string)$r['registration_code'],
            'categoryCode'                 => $catCode,
            'categoryName'                 => $catName,
            'isLegacy'                     => $isLegacy,
            'status'                       => $status,
            'assignmentStatus'             => $assignmentStatus,
            'attendingDays'                => array_values($attendingDays),
            'interestedTracks'             => array_values($interestedTracks),
            'publicReviewNote'             => $publicReviewNote,
            'submittedAt'                  => $r['submitted_at'] !== null ? (string)$r['submitted_at'] : null,
            'createdAt'                    => (string)$r['created_at'],
            'updatedAt'                    => (string)($r['updated_at'] ?? $r['created_at']),
            'canEdit'                      => $canEdit,
            'canWithdraw'                  => $canWithdraw,
            'fullName'                     => (string)($r['full_name'] ?? ''),
            'phone'                        => (string)($r['phone'] ?? ''),
            'cityDistrict'                 => isset($r['city_district']) && $r['city_district'] !== '' ? (string)$r['city_district'] : null,
            'state'                        => isset($r['state']) && $r['state'] !== '' ? (string)$r['state'] : null,
            'country'                      => (string)($r['country'] ?? 'India'),
            'organizationName'             => isset($r['organization_name']) && $r['organization_name'] !== '' ? (string)$r['organization_name'] : null,
            'designation'                  => isset($r['designation']) && $r['designation'] !== '' ? (string)$r['designation'] : null,
            'occupation'                   => isset($r['occupation']) && $r['occupation'] !== '' ? (string)$r['occupation'] : null,
            'emergencyContactName'         => isset($r['emergency_contact_name']) && $r['emergency_contact_name'] !== '' ? (string)$r['emergency_contact_name'] : null,
            'emergencyContactRelationship' => isset($r['emergency_contact_relationship']) && $r['emergency_contact_relationship'] !== '' ? (string)$r['emergency_contact_relationship'] : null,
            'emergencyContactPhone'        => isset($r['emergency_contact_phone']) && $r['emergency_contact_phone'] !== '' ? (string)$r['emergency_contact_phone'] : null,
            'attendeeDetails'              => $attendeeByReg[$rid] ?? null,
            'volunteerDetails'             => $volunteerByReg[$rid] ?? null,
            'sliDetails'                   => $sliByReg[$rid] ?? null,
            'statusHistory'                => $historyByReg[$rid] ?? [],
            'dutyAssignments'              => $dutiesByReg[$rid] ?? [],
            'attendanceSummary'            => [
                'totalDaysPresent' => count($uniqueDates),
                'days'             => $presentDays,
            ],
        ];
    }

    $eligibleApplyCategories = [];
    foreach ($primaryCodes as $pCode) {
        if (!isset($activePrimaryCategories[$pCode])) {
            $eligibleApplyCategories[] = $pCode;
        }
    }

    // -------------------------------------------------------------------------
    // 3. Owner Accommodations (strictly scoped via registrations.user_id = :uid)
    // -------------------------------------------------------------------------
    $accommodationTypes = [];
    try {
        $atStmt = $pdo->query(
            'SELECT id, code, label, description
             FROM accommodation_types
             WHERE is_active = 1
             ORDER BY display_order ASC'
        );
        $rawTypes = $atStmt ? ($atStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        if (count($rawTypes) === 0 && defined('ACCOMMODATION_TYPES_SEED')) {
            $rawTypes = ACCOMMODATION_TYPES_SEED;
        }
        foreach ($rawTypes as $t) {
            $accommodationTypes[] = [
                'id'          => (string)($t['id'] ?? $t['code']),
                'code'        => (string)$t['code'],
                'label'       => (string)$t['label'],
                'description' => isset($t['description']) && $t['description'] !== '' ? (string)$t['description'] : null,
            ];
        }
    } catch (Throwable $e) {
        if (defined('ACCOMMODATION_TYPES_SEED')) {
            foreach (ACCOMMODATION_TYPES_SEED as $t) {
                $accommodationTypes[] = [
                    'id'          => (string)$t['code'],
                    'code'        => (string)$t['code'],
                    'label'       => (string)$t['label'],
                    'description' => null,
                ];
            }
        }
    }

    $accommodations = [];
    try {
        $accStmt = $pdo->prepare(
            'SELECT a.*, r.registration_code, r.category_code
             FROM accommodation_requests a
             INNER JOIN registrations r ON r.id = a.registration_id
             WHERE r.user_id = :currentUserId AND r.deleted_at IS NULL
             ORDER BY a.created_at DESC'
        );
        $accStmt->execute([':currentUserId' => $currentUserId]);
        foreach ($accStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ar) {
            $selTypes = jsonDecode($ar['selected_types'] ?? '[]', []);
            if (!is_array($selTypes)) {
                $selTypes = [];
            }
            if (count($selTypes) === 0 && !empty($ar['accommodation_code'])) {
                $selTypes = [(string)$ar['accommodation_code']];
            }

            $rawDetails = $ar['free_text_details'] ?? $ar['notes'] ?? null;
            $rawEscortName = $ar['escort_name_encrypted'] ?? null;
            $rawCoordNotes = $ar['coordinator_notes'] ?? null;

            $accommodations[] = [
                'id'                 => (string)$ar['id'],
                'registrationId'     => (string)$ar['registration_id'],
                'registrationCode'   => (string)$ar['registration_code'],
                'categoryCode'       => strtoupper((string)$ar['category_code']),
                'selectedTypes'      => array_values($selTypes),
                'freeTextDetails'    => pfgDashboardDecrypt($rawDetails !== null ? (string)$rawDetails : null),
                'escortAccompanying' => (bool)($ar['escort_accompanying'] ?? false),
                'escortName'         => pfgDashboardDecrypt($rawEscortName !== null ? (string)$rawEscortName : null),
                'coordinatorNotes'   => pfgDashboardDecrypt($rawCoordNotes !== null ? (string)$rawCoordNotes : null),
                'status'             => strtoupper((string)($ar['status'] ?? 'REQUESTED')),
                'createdAt'          => (string)$ar['created_at'],
                'updatedAt'          => (string)($ar['updated_at'] ?? $ar['created_at']),
            ];
        }
    } catch (Throwable $e) {
        error_log('[dashboard] accommodation_requests query warning: ' . $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // 4. Owner In-App Notifications (latest 50 + unreadCount)
    // -------------------------------------------------------------------------
    $notifications = [];
    $unreadCount = 0;
    try {
        $cntStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM in_app_notifications
             WHERE user_id = :currentUserId AND is_read = 0 AND read_at IS NULL'
        );
        $cntStmt->execute([':currentUserId' => $currentUserId]);
        $unreadCount = (int)$cntStmt->fetchColumn();

        $notifStmt = $pdo->prepare(
            'SELECT id, event_type, title, message, action_url, entity_type, entity_id, is_read, read_at, created_at
             FROM in_app_notifications
             WHERE user_id = :currentUserId
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $notifStmt->execute([':currentUserId' => $currentUserId]);
        foreach ($notifStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $n) {
            $isRead = !empty($n['is_read']) || !empty($n['read_at']);
            $notifications[] = [
                'id'         => (string)$n['id'],
                'eventType'  => (string)$n['event_type'],
                'title'      => (string)$n['title'],
                'message'    => (string)$n['message'],
                'actionUrl'  => pfgDashboardSafeUrl(isset($n['action_url']) ? (string)$n['action_url'] : null, true),
                'entityType' => $n['entity_type'] !== null ? (string)$n['entity_type'] : null,
                'entityId'   => $n['entity_id'] !== null ? (string)$n['entity_id'] : null,
                'isRead'     => $isRead,
                'readAt'     => $n['read_at'] !== null ? (string)$n['read_at'] : null,
                'createdAt'  => (string)$n['created_at'],
            ];
        }
    } catch (Throwable $e) {
        error_log('[dashboard] in_app_notifications query warning: ' . $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // 5. Confirmed / Published Programme Highlights (NEVER showing DRAFT/CANCELLED)
    // -------------------------------------------------------------------------
    $confirmedProgramme = [];
    $seenSessionIds = [];
    try {
        $progSql = "SELECT
                        pi.id,
                        pi.session_id,
                        pi.day_number,
                        pi.time_slot,
                        pi.title,
                        pi.description,
                        pi.category,
                        pi.venue_name,
                        pi.accessibility_services,
                        pi.status,
                        v.name AS joined_venue_name,
                        v.confirmation_status AS venue_confirmation_status
                    FROM programme_items pi
                    LEFT JOIN festival_sessions fs ON fs.id = pi.session_id
                    LEFT JOIN venues v ON v.id = fs.venue_id
                    WHERE pi.status IN ('CONFIRMED', 'PUBLISHED')
                      AND (fs.id IS NULL OR fs.status IN ('CONFIRMED', 'PUBLISHED'))";
        $progParams = [];
        if ($editionId !== null) {
            $progSql .= ' AND pi.edition_id = :ed';
            $progParams[':ed'] = $editionId;
        }
        $progSql .= ' ORDER BY pi.day_number ASC, pi.display_order ASC, pi.time_slot ASC LIMIT 60';

        $progStmt = $pdo->prepare($progSql);
        $progStmt->execute($progParams);
        foreach ($progStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
            if (!empty($item['session_id'])) {
                $seenSessionIds[(string)$item['session_id']] = true;
            }
            $a11yServices = jsonDecode($item['accessibility_services'] ?? '[]', []);
            $venueName = !empty($item['joined_venue_name'])
                ? (string)$item['joined_venue_name']
                : (!empty($item['venue_name']) ? (string)$item['venue_name'] : 'Vadodara Venue (To Be Confirmed)');
            $venueConf = !empty($item['venue_confirmation_status'])
                ? strtoupper((string)$item['venue_confirmation_status'])
                : 'PROPOSED';

            $confirmedProgramme[] = [
                'id'                      => (string)$item['id'],
                'source'                  => 'programme_item',
                'dayNumber'               => (int)$item['day_number'],
                'timeSlot'                => (string)$item['time_slot'],
                'title'                   => (string)$item['title'],
                'description'             => $item['description'] !== null ? (string)$item['description'] : null,
                'category'                => (string)$item['category'],
                'venueName'               => $venueName,
                'venueConfirmationStatus' => $venueConf,
                'accessibilityServices'   => is_array($a11yServices) ? array_values($a11yServices) : [],
                'status'                  => strtoupper((string)$item['status']),
            ];
        }

        // Also include confirmed/published festival_sessions not already linked in programme_items
        $sessSql = "SELECT
                        fs.id,
                        fs.day_number,
                        fs.title,
                        fs.track,
                        fs.description,
                        fs.starts_at,
                        fs.ends_at,
                        fs.isl_required,
                        fs.captioning_available,
                        fs.wheelchair_accessible,
                        fs.status,
                        v.name AS venue_name,
                        v.confirmation_status AS venue_confirmation_status
                    FROM festival_sessions fs
                    LEFT JOIN venues v ON v.id = fs.venue_id
                    WHERE fs.status IN ('CONFIRMED', 'PUBLISHED')";
        $sessParams = [];
        if ($editionId !== null) {
            $sessSql .= ' AND fs.edition_id = :ed';
            $sessParams[':ed'] = $editionId;
        }
        $sessSql .= ' ORDER BY fs.day_number ASC, fs.starts_at ASC LIMIT 60';

        $sessStmt = $pdo->prepare($sessSql);
        $sessStmt->execute($sessParams);
        foreach ($sessStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $s) {
            if (isset($seenSessionIds[(string)$s['id']])) {
                continue;
            }
            $services = [];
            if (!empty($s['isl_required'])) {
                $services[] = 'ISL Interpretation';
            }
            if (!empty($s['captioning_available'])) {
                $services[] = 'Live Captions';
            }
            if (!empty($s['wheelchair_accessible'])) {
                $services[] = 'Wheelchair Accessible';
            }
            $startFmt = !empty($s['starts_at']) ? date('h:i A', strtotime((string)$s['starts_at'])) : '';
            $endFmt = !empty($s['ends_at']) ? date('h:i A', strtotime((string)$s['ends_at'])) : '';
            $slot = ($startFmt !== '' && $endFmt !== '') ? ($startFmt . ' – ' . $endFmt) : $startFmt;

            $confirmedProgramme[] = [
                'id'                      => (string)$s['id'],
                'source'                  => 'festival_session',
                'dayNumber'               => (int)$s['day_number'],
                'timeSlot'                => $slot,
                'title'                   => (string)$s['title'],
                'description'             => isset($s['description']) && $s['description'] !== '' ? (string)$s['description'] : null,
                'category'                => (string)$s['track'],
                'venueName'               => !empty($s['venue_name']) ? (string)$s['venue_name'] : 'Vadodara Venue (To Be Confirmed)',
                'venueConfirmationStatus' => !empty($s['venue_confirmation_status']) ? strtoupper((string)$s['venue_confirmation_status']) : 'PROPOSED',
                'accessibilityServices'   => $services,
                'status'                  => strtoupper((string)$s['status']),
            ];
        }
    } catch (Throwable $e) {
        error_log('[dashboard] programme query warning: ' . $e->getMessage());
    }

    // Active public announcements (PUBLISHED and within schedule window)
    $announcements = [];
    try {
        $now = nowSql();
        $annSql = "SELECT id, title, message AS body, severity AS priority, published_at, expires_at
                   FROM announcements
                   WHERE status = 'PUBLISHED'
                     AND (published_at IS NULL OR published_at <= :now1)
                     AND (expires_at IS NULL OR expires_at >= :now2)";
        $annParams = [':now1' => $now, ':now2' => $now];
        if ($editionId !== null) {
            $annSql .= ' AND (edition_id IS NULL OR edition_id = :ed)';
            $annParams[':ed'] = $editionId;
        }
        $annSql .= ' ORDER BY published_at DESC, created_at DESC LIMIT 10';
        $annStmt = $pdo->prepare($annSql);
        $annStmt->execute($annParams);
        foreach ($annStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ann) {
            $announcements[] = [
                'id'          => (string)$ann['id'],
                'title'       => (string)$ann['title'],
                'body'        => (string)$ann['body'],
                'priority'    => (string)($ann['priority'] ?? 'INFO'),
                'publishedAt' => $ann['published_at'] !== null ? (string)$ann['published_at'] : null,
            ];
        }
    } catch (Throwable $e) {
        error_log('[dashboard] announcements query warning: ' . $e->getMessage());
    }

    // -------------------------------------------------------------------------
    // 6. Legacy Claimable Count (verified email only; passwordless non-staff owner)
    // -------------------------------------------------------------------------
    $legacyClaimableCount = 0;
    if ($emailVerified && $userEmailLower !== '') {
        try {
            $claimStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM registrations r
                 INNER JOIN users u ON u.id = r.user_id
                 WHERE r.deleted_at IS NULL
                   AND LOWER(r.email) = :email
                   AND r.user_id <> :currentUserId
                   AND (u.password_hash IS NULL OR u.password_hash = '')
                   AND NOT EXISTS (
                       SELECT 1
                       FROM user_roles ur
                       INNER JOIN roles ro ON ro.id = ur.role_id
                       WHERE ur.user_id = u.id
                         AND ro.code IN ('SUPERADMIN', 'ADMIN', 'RGST')
                   )
                   AND NOT EXISTS (
                       SELECT 1
                       FROM legacy_registration_claims lrc
                       WHERE lrc.registration_id = r.id
                   )"
            );
            $claimStmt->execute([
                ':email'         => $userEmailLower,
                ':currentUserId' => $currentUserId,
            ]);
            $legacyClaimableCount = (int)$claimStmt->fetchColumn();
        } catch (Throwable $e) {
            // If legacy_registration_claims table is absent in a legacy test snapshot, count safely without it
            try {
                $claimFallback = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM registrations r
                     INNER JOIN users u ON u.id = r.user_id
                     WHERE r.deleted_at IS NULL
                       AND LOWER(r.email) = :email
                       AND r.user_id <> :currentUserId
                       AND (u.password_hash IS NULL OR u.password_hash = '')
                       AND NOT EXISTS (
                           SELECT 1
                           FROM user_roles ur
                           INNER JOIN roles ro ON ro.id = ur.role_id
                           WHERE ur.user_id = u.id
                             AND ro.code IN ('SUPERADMIN', 'ADMIN', 'RGST')
                       )"
                );
                $claimFallback->execute([
                    ':email'         => $userEmailLower,
                    ':currentUserId' => $currentUserId,
                ]);
                $legacyClaimableCount = (int)$claimFallback->fetchColumn();
            } catch (Throwable $inner) {
                $legacyClaimableCount = 0;
            }
        }
    }

    jsonResponse([
        'profile'                 => $profile,
        'registrations'           => $registrations,
        'eligibleApplyCategories' => $eligibleApplyCategories,
        'accommodations'          => $accommodations,
        'accommodationTypes'      => $accommodationTypes,
        'notifications'           => $notifications,
        'unreadCount'             => $unreadCount,
        'confirmedProgramme'      => $confirmedProgramme,
        'announcements'           => $announcements,
        'legacyClaimableCount'    => $legacyClaimableCount,
        'festivalInfo'            => [
            'name'            => FESTIVAL_NAME,
            'datesDisplay'    => FESTIVAL_DATES_DISPLAY,
            'locationDisplay' => FESTIVAL_LOCATION_DISPLAY,
            'supportEmail'    => defined('NOTIFICATION_SUPPORT_EMAIL') ? NOTIFICATION_SUPPORT_EMAIL : 'purplefestivalvadodara@gmail.com',
            'days'            => defined('FESTIVAL_DAYS') ? FESTIVAL_DAYS : [],
        ],
    ], 200);
} catch (Throwable $err) {
    error_log('[/api/account/dashboard] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Unable to load dashboard right now.'], 500);
}
