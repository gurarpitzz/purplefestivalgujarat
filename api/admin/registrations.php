<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Registrations, Volunteer/SLI Review & CSV Export API
 * Endpoints:
 *   GET    /api/admin/registrations (SQL-paginated list + filters + ?export=csv / ?format=csv)
 *   GET    /api/admin/registrations?id=<uuid> or /api/admin/registrations/<uuid> (full detail payload)
 *   PATCH  /api/admin/registrations (or POST) — lifecycle status review, legacy duty fields, legacy credential revocation
 *
 * Phase 7 integration: every status change is validated against the canonical application state machine
 * (local fallback in includes/admin-common.php mirrors includes/application-lifecycle.php), is written to
 * `registration_status_history`, and keeps approval, assignment and attendance as three separate dimensions.
 * Sensitive disability / accommodation data stays gated behind `accommodations.read` + SENSITIVE_DATA_READ audit.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qr-service.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/notification-service.php';
require_once __DIR__ . '/../../includes/admin-common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Helper to record an audit log entry safely regardless of auth.php helper signature.
 */
function pfgAdminRecordAudit(PDO $pdo, ?string $actorUserId, string $action, string $resourceType, ?string $resourceId = null, array $metadata = []): void
{
    if (function_exists('recordAuditLog')) {
        try {
            recordAuditLog([
                'actorUserId'  => $actorUserId,
                'action'       => $action,
                'resourceType' => $resourceType,
                'resourceId'   => $resourceId,
                'ipAddress'    => getClientIp(),
                'userAgent'    => getClientUserAgent(),
                'metadata'     => $metadata,
            ]);
            return;
        } catch (Throwable $e) {
            // Fall through to direct insert
        }
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (id, actor_user_id, action, resource_type, resource_id, ip_address, user_agent, metadata, created_at)
            VALUES (:id, :actor, :action, :rtype, :rid, :ip, :ua, :meta, :now)
        ");
        $stmt->execute([
            ':id'     => uuid4(),
            ':actor'  => $actorUserId,
            ':action' => $action,
            ':rtype'  => $resourceType,
            ':rid'    => $resourceId,
            ':ip'     => getClientIp(),
            ':ua'     => getClientUserAgent(),
            ':meta'   => jsonEncode($metadata),
            ':now'    => nowSql(),
        ]);
    } catch (Throwable $e) {
        error_log('Audit log insert failed: ' . $e->getMessage());
    }
}

/**
 * Extracts registration ID from query parameter, request body, or URL path `/api/admin/registrations/<id>`.
 */
function pfgResolveRegistrationId(array $input = []): string
{
    if (!empty($_GET['id']) && is_string($_GET['id'])) {
        return trim($_GET['id']);
    }
    if (!empty($_GET['registrationId']) && is_string($_GET['registrationId'])) {
        return trim($_GET['registrationId']);
    }
    if (!empty($input['id']) && is_string($input['id'])) {
        return trim($input['id']);
    }
    if (!empty($input['registrationId']) && is_string($input['registrationId'])) {
        return trim($input['registrationId']);
    }
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (is_string($pathInfo) && trim($pathInfo, '/') !== '') {
        return trim($pathInfo, '/');
    }
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/api/admin/registrations(?:\.php)?/([A-Za-z0-9_-]{8,64})$#', $uriPath, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Participant-supplied URLs are only echoed back when they are valid http(s) URLs.
 */
function pfgRegSafeUrl($url): ?string
{
    if (!is_string($url) || trim($url) === '') {
        return null;
    }
    $safe = pfgValidateSafeUrl($url, false);
    return ($safe !== null && $safe !== '') ? $safe : null;
}

function pfgFormatVolunteerRow(?array $v): ?array
{
    if (!$v) {
        return null;
    }
    return [
        'id'                     => (string)($v['id'] ?? ''),
        'registrationId'         => (string)($v['registration_id'] ?? ''),
        'age'                    => isset($v['age']) ? (int)$v['age'] : null,
        'gender'                 => $v['gender'] ?? null,
        'cityState'              => $v['city_state'] ?? null,
        'institutionName'        => $v['institution_name'] ?? null,
        'isMsuStudentOrAlumni'   => !empty($v['is_msu_student_or_alumni']),
        'msuAffiliationStatus'   => $v['msu_affiliation_status'] ?? null,
        'msuFacultyDepartment'   => $v['msu_faculty_department'] ?? null,
        'crrNumber'              => $v['crr_number'] ?? null,
        'primaryRole'            => $v['primary_role'] ?? null,
        'preferredRoles'         => jsonDecode($v['preferred_roles'] ?? '[]', []),
        'availabilityDays'       => jsonDecode($v['availability_days'] ?? '[]', []),
        'availabilityShifts'     => jsonDecode($v['availability_shifts'] ?? '[]', []),
        'languagesSpoken'        => jsonDecode($v['languages_spoken'] ?? '[]', []),
        'languagesOther'         => $v['languages_other'] ?? null,
        'relevantExperience'     => $v['relevant_experience'] ?? null,
        'anchoringExperience'    => $v['anchoring_experience'] ?? null,
        'emergencyContactName'   => $v['emergency_contact_name'] ?? null,
        'emergencyContactPhone'  => $v['emergency_contact_phone'] ?? null,
        'documentUrl'            => pfgRegSafeUrl($v['document_url'] ?? null),
        'assignedDepartment'     => $v['assigned_department'] ?? null,
        'assignedResponsibility' => $v['assigned_responsibility'] ?? null,
        'assignedSupervisor'     => $v['assigned_supervisor'] ?? null,
        'assignedVenue'          => $v['assigned_venue'] ?? null,
        'assignedShift'          => $v['assigned_shift'] ?? null,
        'reportingTime'          => $v['reporting_time'] ?? null,
        'assignmentNotes'        => $v['assignment_notes'] ?? null,
        'createdAt'              => $v['created_at'] ?? null,
        'updatedAt'              => $v['updated_at'] ?? null,
    ];
}

function pfgFormatSliRow(?array $s): ?array
{
    if (!$s) {
        return null;
    }
    return [
        'id'                        => (string)($s['id'] ?? ''),
        'registrationId'            => (string)($s['registration_id'] ?? ''),
        'age'                       => isset($s['age']) ? (int)$s['age'] : null,
        'cityState'                 => $s['city_state'] ?? null,
        'basedInGujarat'            => !isset($s['based_in_gujarat']) || !empty($s['based_in_gujarat']),
        'crrNumber'                 => $s['crr_number'] ?? null,
        'qualifications'            => $s['qualifications'] ?? null,
        'qualificationCategory'     => $s['qualification_category'] ?? null,
        'trainingInstitute'         => $s['training_institute'] ?? null,
        'certificationLevel'        => $s['certification_level'] ?? null,
        'yearsOfExperience'         => isset($s['years_of_experience']) ? (int)$s['years_of_experience'] : null,
        'experienceBand'            => $s['experience_band'] ?? null,
        'comfortablePublicStage'    => isset($s['comfortable_public_stage']) ? !empty($s['comfortable_public_stage']) : null,
        'professionalAffiliation'   => $s['professional_affiliation'] ?? null,
        'areasOfExpertise'          => jsonDecode($s['areas_of_expertise'] ?? '[]', []),
        'interpretationPreferences' => jsonDecode($s['interpretation_preferences'] ?? '[]', []),
        'availabilityDays'          => jsonDecode($s['availability_days'] ?? '[]', []),
        'availabilityShifts'        => jsonDecode($s['availability_shifts'] ?? '[]', []),
        'videoDemoUrl'              => pfgRegSafeUrl($s['video_demo_url'] ?? null),
        'documentUrl'               => pfgRegSafeUrl($s['document_url'] ?? null),
        'honorariumOrTravelNotes'   => $s['honorarium_or_travel_notes'] ?? null,
        'assignedSessions'          => jsonDecode($s['assigned_sessions'] ?? '[]', []),
        'assignedVenue'             => $s['assigned_venue'] ?? null,
        'dutyTimes'                 => $s['duty_times'] ?? null,
        'assignmentNotes'           => $s['assignment_notes'] ?? null,
        'createdAt'                 => $s['created_at'] ?? null,
        'updatedAt'                 => $s['updated_at'] ?? null,
    ];
}

function pfgFormatRegistrationRow(array $r, bool $canReadAccomm = true, bool $detail = false): array
{
    $row = [
        'id'                     => (string)$r['id'],
        'registrationCode'       => (string)$r['registration_code'],
        'editionId'              => (string)($r['edition_id'] ?? ''),
        'userId'                 => (string)($r['user_id'] ?? ''),
        'categoryId'             => (string)($r['category_id'] ?? ''),
        'categoryCode'           => (string)$r['category_code'],
        'status'                 => (string)$r['status'],
        'assignmentStatus'       => (string)($r['assignment_status'] ?? 'UNASSIGNED'),
        'paymentStatus'          => (string)($r['payment_status'] ?? 'NOT_REQUIRED'),
        'fullName'               => (string)$r['full_name'],
        'email'                  => (string)$r['email'],
        'phone'                  => (string)$r['phone'],
        'country'                => (string)($r['country'] ?? 'India'),
        'occupation'             => $r['occupation'] ?? null,
        'organizationName'       => $r['organization_name'] ?? null,
        'designation'            => $r['designation'] ?? null,
        'isPersonWithDisability' => $canReadAccomm ? ($r['is_person_with_disability'] ?? 'PREFER_NOT_TO_SAY') : 'RESTRICTED',
        'attendingDays'          => jsonDecode($r['attending_days'] ?? '[]', []),
        'interestedTracks'       => jsonDecode($r['interested_tracks'] ?? '[]', []),
        'reviewerId'             => $r['reviewer_id'] ?? null,
        'reviewNotes'            => $r['review_notes'] ?? null,
        'reviewedAt'             => $r['reviewed_at'] ?? null,
        'submittedAt'            => $r['submitted_at'] ?? null,
        'createdAt'              => $r['created_at'] ?? null,
        'updatedAt'              => $r['updated_at'] ?? null,
    ];
    if ($detail) {
        $row['cityDistrict'] = $r['city_district'] ?? null;
        $row['state'] = $r['state'] ?? null;
        $row['emergencyContactName'] = $r['emergency_contact_name'] ?? null;
        $row['emergencyContactRelationship'] = $r['emergency_contact_relationship'] ?? null;
        $row['emergencyContactPhone'] = $r['emergency_contact_phone'] ?? null;
        $row['publicReviewNote'] = $r['public_review_note'] ?? null;
        $row['couponCode'] = $r['coupon_code'] ?? null;
    }
    return $row;
}

function pfgEscapeCsvCell($value): string
{
    $str = $value === null ? '' : (string)$value;
    // Neutralize spreadsheet formula injection (CSV/Excel/Calc DDE & formula execution — FND-25)
    if ($str !== '' && preg_match('/^\s*[=+\-@\t\r]/', $str)) {
        $str = "'" . $str;
    }
    if (preg_match('/[",\r\n]/', $str)) {
        return '"' . str_replace('"', '""', $str) . '"';
    }
    return $str;
}

/**
 * Escapes LIKE wildcards in user search input.
 */
function pfgRegLike(string $s): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtolower($s)) . '%';
}

/**
 * Bulk-loads attendance, credentials, accommodation, duty and category detail rows for a set of registration rows
 * (a single page — never the whole table) and returns enriched list items.
 */
function pfgRegEnrichRows(PDO $pdo, array $rows, bool $canReadAccomm): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn(array $r): string => (string)$r['id'], $rows);
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $attendance = [];
    if (pfgAdminTableExists($pdo, 'attendance_records')) {
        $st = $pdo->prepare("SELECT registration_id, attendance_date_ist, marked_at, venue_label FROM attendance_records WHERE registration_id IN ({$ph}) AND status = 'PRESENT' ORDER BY marked_at DESC");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $a) {
            $rid = (string)$a['registration_id'];
            if (!isset($attendance[$rid])) {
                $attendance[$rid] = ['count' => 0, 'lastAt' => (string)$a['marked_at'], 'lastVenue' => (string)($a['venue_label'] ?? 'Registration Desk'), 'days' => []];
            }
            $attendance[$rid]['count']++;
            $attendance[$rid]['days'][] = (string)$a['attendance_date_ist'];
        }
    }

    $credByReg = [];
    if (pfgAdminTableExists($pdo, 'qr_credentials')) {
        $st = $pdo->prepare("SELECT registration_id, status, credential_code FROM qr_credentials WHERE registration_id IN ({$ph}) ORDER BY issued_at DESC");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $cr) {
            $rid = (string)$cr['registration_id'];
            if (!isset($credByReg[$rid])) {
                $credByReg[$rid] = ['status' => (string)$cr['status'], 'code' => (string)$cr['credential_code']];
            }
        }
    }

    $accommByReg = [];
    if ($canReadAccomm) {
        $st = $pdo->prepare("SELECT registration_id, status, selected_types, accommodation_code, free_text_details, notes FROM accommodation_requests WHERE registration_id IN ({$ph})");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $ac) {
            $rid = (string)$ac['registration_id'];
            $types = jsonDecode($ac['selected_types'] ?? '[]', []);
            if (empty($types) && !empty($ac['accommodation_code'])) {
                $types = [(string)$ac['accommodation_code']];
            }
            $types = is_array($types) ? $types : [];
            if (!isset($accommByReg[$rid])) {
                $accommByReg[$rid] = [
                    'status'          => (string)($ac['status'] ?? 'REQUESTED'),
                    'selectedTypes'   => $types,
                    'freeTextDetails' => pfgDecryptValue($ac['free_text_details'] ?? $ac['notes'] ?? null, 'data'),
                ];
            } else {
                $accommByReg[$rid]['selectedTypes'] = array_values(array_unique(array_merge($accommByReg[$rid]['selectedTypes'], $types)));
            }
        }
    }

    $dutyCounts = [];
    if (pfgAdminTableExists($pdo, 'duty_assignments')) {
        $st = $pdo->prepare("SELECT registration_id, COUNT(*) AS c FROM duty_assignments WHERE registration_id IN ({$ph}) AND status = 'ASSIGNED' GROUP BY registration_id");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $d) {
            $dutyCounts[(string)$d['registration_id']] = (int)$d['c'];
        }
    }

    $volByReg = [];
    $sliByReg = [];
    $hasVol = false;
    $hasSli = false;
    foreach ($rows as $r) {
        $cat = strtoupper((string)$r['category_code']);
        $hasVol = $hasVol || $cat === 'VOLUNTEER';
        $hasSli = $hasSli || $cat === 'SLI';
    }
    if ($hasVol) {
        $st = $pdo->prepare("SELECT * FROM volunteer_applications WHERE registration_id IN ({$ph})");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $v) {
            $volByReg[(string)$v['registration_id']] = pfgFormatVolunteerRow($v);
        }
    }
    if ($hasSli) {
        $st = $pdo->prepare("SELECT * FROM sli_applications WHERE registration_id IN ({$ph})");
        $st->execute($ids);
        foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $s) {
            $sliByReg[(string)$s['registration_id']] = pfgFormatSliRow($s);
        }
    }

    $items = [];
    foreach ($rows as $r) {
        $rid = (string)$r['id'];
        $ck = $attendance[$rid] ?? null;
        $cred = $credByReg[$rid] ?? null;
        $accomm = $accommByReg[$rid] ?? null;

        $item = pfgFormatRegistrationRow($r, $canReadAccomm);
        $item['checkedIn'] = $ck !== null;
        $item['checkinCount'] = $ck['count'] ?? 0;
        $item['lastCheckinAt'] = $ck['lastAt'] ?? null;
        $item['lastCheckinVenue'] = $ck['lastVenue'] ?? null;
        $item['attendanceDays'] = $ck ? array_values(array_unique($ck['days'])) : [];
        $item['credentialStatus'] = $cred['status'] ?? null;
        $item['credentialCode'] = $cred['code'] ?? null;
        $item['activeDutyCount'] = $dutyCounts[$rid] ?? 0;
        if ($canReadAccomm) {
            $item['hasAccommodationRequest'] = $accomm !== null;
            $item['accommodationStatus'] = $accomm['status'] ?? null;
            $item['accommodationTypes'] = $accomm['selectedTypes'] ?? [];
            $item['accommodationFreeText'] = $accomm['freeTextDetails'] ?? null;
        }
        $item['volunteerDetails'] = $volByReg[$rid] ?? null;
        $item['sliDetails'] = $sliByReg[$rid] ?? null;
        $items[] = $item;
    }
    return $items;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();
    $canReadAccomm = hasPermission($user, PERMISSIONS['ACCOMMODATIONS_READ']);
    $canReadPayments = hasPermission($user, PERMISSIONS['PAYMENTS_READ']);

    // =========================================================================
    // GET: Single Registration Detail OR Filterable List / CSV Export
    // =========================================================================
    if ($method === 'GET') {
        requirePermission(PERMISSIONS['REGISTRATIONS_READ_ALL'], $user);
        $singleId = pfgResolveRegistrationId();

        if ($singleId !== '') {
            $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([':id' => $singleId]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reg) {
                jsonResponse(['error' => 'Registration not found.'], 404);
            }

            // Legacy delegate details (canonical `delegate_details` ONLY)
            $delegateRow = null;
            $stDel = $pdo->prepare('SELECT * FROM delegate_details WHERE registration_id = :rid LIMIT 1');
            $stDel->execute([':rid' => $singleId]);
            $found = $stDel->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $delegateRow = [
                    'id'                       => (string)$found['id'],
                    'registrationId'           => (string)$found['registration_id'],
                    'gender'                   => $found['gender'] ?? null,
                    'age'                      => isset($found['age']) ? (int)$found['age'] : null,
                    'cityState'                => $found['city_state'] ?? null,
                    'paraSportsInterests'      => jsonDecode($found['para_sports_interests'] ?? '[]', []),
                    'outstationTravelRequired' => !empty($found['outstation_travel_required']),
                    'accommodationNeeded'      => !empty($found['accommodation_needed']),
                    'foodPreference'           => $found['food_preference'] ?? null,
                    'emergencyContactName'     => $found['emergency_contact_name'] ?? null,
                    'emergencyContactPhone'    => $found['emergency_contact_phone'] ?? null,
                    'guardianName'             => $found['guardian_name'] ?? null,
                ];
            }

            // Phase 6 attendee details (dietary requirements are health-adjacent: gated behind accommodations.read)
            $attendeeRow = null;
            $dietaryRead = false;
            if (pfgAdminTableExists($pdo, 'attendee_details')) {
                $stAtt = $pdo->prepare('SELECT * FROM attendee_details WHERE registration_id = :rid LIMIT 1');
                $stAtt->execute([':rid' => $singleId]);
                $ad = $stAtt->fetch(PDO::FETCH_ASSOC);
                if ($ad) {
                    $dietary = null;
                    if ($canReadAccomm && !empty($ad['dietary_requirements_encrypted'])) {
                        $dietary = pfgDecryptValue((string)$ad['dietary_requirements_encrypted'], 'data');
                        $dietaryRead = $dietary !== null && $dietary !== '';
                    }
                    $attendeeRow = [
                        'id'                       => (string)$ad['id'],
                        'ageGroup'                 => $ad['age_group'] ?? null,
                        'gender'                   => $ad['gender'] ?? null,
                        'selfIdentificationTags'   => $canReadAccomm ? jsonDecode($ad['self_identification_tags'] ?? '[]', []) : null,
                        'guardianName'             => $ad['guardian_name'] ?? null,
                        'guardianRelationship'     => $ad['guardian_relationship'] ?? null,
                        'guardianPhone'            => $ad['guardian_phone'] ?? null,
                        'paraSportsAthlete'        => !empty($ad['para_sports_athlete']),
                        'paraSportsEvents'         => jsonDecode($ad['para_sports_events'] ?? '[]', []),
                        'paraSportsOther'          => $ad['para_sports_other'] ?? null,
                        'outstationTravelRequired' => !empty($ad['outstation_travel_required']),
                        'arrivalAt'                => $ad['arrival_at'] ?? null,
                        'departureAt'              => $ad['departure_at'] ?? null,
                        'travelMode'               => $ad['travel_mode'] ?? null,
                        'lodgingNeeded'            => !empty($ad['lodging_needed']),
                        'accessibleRoomNeeded'     => $canReadAccomm ? !empty($ad['accessible_room_needed']) : null,
                        'accompanyingCount'        => isset($ad['accompanying_count']) ? (int)$ad['accompanying_count'] : null,
                        'foodPreference'           => $ad['food_preference'] ?? null,
                        'dietaryRequirements'      => $dietary,
                    ];
                }
            }

            $stVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
            $stVol->execute([':rid' => $singleId]);
            $volRow = pfgFormatVolunteerRow($stVol->fetch(PDO::FETCH_ASSOC) ?: null);

            $stSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
            $stSli->execute([':rid' => $singleId]);
            $sliRow = pfgFormatSliRow($stSli->fetch(PDO::FETCH_ASSOC) ?: null);

            // Credentials (historical read-only; no raw QR token reconstruction per D-01)
            $credentials = [];
            $checkins = [];
            if (pfgAdminTableExists($pdo, 'qr_credentials')) {
                $stCred = $pdo->prepare('SELECT * FROM qr_credentials WHERE registration_id = :rid ORDER BY issued_at DESC');
                $stCred->execute([':rid' => $singleId]);
                $credentials = array_map(static function (array $cr): array {
                    $formatted = pfgFormatCredentialRow($cr);
                    unset($formatted['token_hash'], $formatted['tokenHash']);
                    return $formatted;
                }, $stCred->fetchAll(PDO::FETCH_ASSOC) ?: []);
            }
            if (pfgAdminTableExists($pdo, 'checkins')) {
                $stChk = $pdo->prepare('SELECT * FROM checkins WHERE registration_id = :rid ORDER BY checked_in_at DESC');
                $stChk->execute([':rid' => $singleId]);
                $checkins = array_map(static function (array $c): array {
                    return [
                        'id'               => (string)$c['id'],
                        'credentialId'     => (string)$c['credential_id'],
                        'registrationId'   => (string)$c['registration_id'],
                        'venueLabel'       => (string)($c['venue_label'] ?? 'Main Gate'),
                        'entitlementScope' => (string)($c['entitlement_scope'] ?? ''),
                        'checkinDateIst'   => (string)($c['checkin_date_ist'] ?? ''),
                        'method'           => (string)($c['method'] ?? 'QR_CAMERA'),
                        'result'           => (string)($c['result'] ?? 'CHECKED_IN'),
                        'checkedInAt'      => (string)($c['checked_in_at'] ?? ''),
                    ];
                }, $stChk->fetchAll(PDO::FETCH_ASSOC) ?: []);
            }

            // Manual-desk attendance (canonical, D-01) — status + day only; never review notes or disability data
            $attendanceRecords = [];
            if (pfgAdminTableExists($pdo, 'attendance_records')) {
                $stAr = $pdo->prepare('SELECT id, attendance_date_ist, attendance_scope, status, marked_at, venue_label, corrected_at FROM attendance_records WHERE registration_id = :rid ORDER BY attendance_date_ist ASC, marked_at ASC');
                $stAr->execute([':rid' => $singleId]);
                foreach (($stAr->fetchAll(PDO::FETCH_ASSOC) ?: []) as $a) {
                    $attendanceRecords[] = [
                        'id'          => (string)$a['id'],
                        'date'        => (string)$a['attendance_date_ist'],
                        'scope'       => (string)$a['attendance_scope'],
                        'status'      => (string)$a['status'],
                        'markedAt'    => (string)$a['marked_at'],
                        'venueLabel'  => $a['venue_label'] ?? null,
                        'correctedAt' => $a['corrected_at'] ?? null,
                    ];
                }
            }

            // Duty assignments (Phase 12)
            $dutyAssignments = [];
            if (pfgAdminTableExists($pdo, 'duty_assignments')) {
                $stDa = $pdo->prepare('
                    SELECT d.*, s.title AS session_title, v.name AS venue_name
                    FROM duty_assignments d
                    LEFT JOIN festival_sessions s ON s.id = d.session_id
                    LEFT JOIN venues v ON v.id = d.venue_id
                    WHERE d.registration_id = :rid ORDER BY d.duty_date_ist ASC, d.start_time ASC
                ');
                $stDa->execute([':rid' => $singleId]);
                foreach (($stDa->fetchAll(PDO::FETCH_ASSOC) ?: []) as $d) {
                    $dutyAssignments[] = [
                        'id'                    => (string)$d['id'],
                        'registrationId'        => (string)$d['registration_id'],
                        'participantCategory'   => strtoupper((string)$d['participant_category']),
                        'sessionId'             => $d['session_id'] ?? null,
                        'sessionTitle'          => $d['session_title'] ?? null,
                        'venueId'               => $d['venue_id'] ?? null,
                        'venueName'             => $d['venue_name'] ?? null,
                        'roleTitle'             => (string)$d['role_title'],
                        'department'            => $d['department'] ?? null,
                        'dutyDateIst'           => (string)$d['duty_date_ist'],
                        'shiftCode'             => (string)$d['shift_code'],
                        'startTime'             => (string)$d['start_time'],
                        'endTime'               => (string)$d['end_time'],
                        'reportingSupervisor'   => $d['reporting_supervisor'] ?? null,
                        'reportingTime'         => $d['reporting_time'] ?? null,
                        'reportingInstructions' => $d['reporting_instructions'] ?? null,
                        'pairingGroupId'        => $d['pairing_group_id'] ?? null,
                        'status'                => strtoupper((string)$d['status']),
                        'overrideReason'        => $d['override_reason'] ?? null,
                    ];
                }
            }

            // Lifecycle history (append-only) + admin-legal next states
            $statusHistory = [];
            if (pfgAdminTableExists($pdo, 'registration_status_history')) {
                $stH = $pdo->prepare('
                    SELECT h.previous_status, h.new_status, h.actor_role, h.reason, h.public_message, h.created_at, u.full_name AS actor_name
                    FROM registration_status_history h LEFT JOIN users u ON u.id = h.actor_user_id
                    WHERE h.registration_id = :rid ORDER BY h.created_at ASC, h.id ASC
                ');
                $stH->execute([':rid' => $singleId]);
                foreach (($stH->fetchAll(PDO::FETCH_ASSOC) ?: []) as $h) {
                    $statusHistory[] = [
                        'previousStatus' => $h['previous_status'] ?? null,
                        'newStatus'      => (string)$h['new_status'],
                        'actorRole'      => $h['actor_role'] ?? null,
                        'actorName'      => $h['actor_name'] ?? null,
                        'reason'         => $h['reason'] ?? null,
                        'publicMessage'  => $h['public_message'] ?? null,
                        'createdAt'      => (string)$h['created_at'],
                    ];
                }
            }
            $allowedTransitions = pfgAdminAllowedTransitions((string)$reg['category_code'], (string)$reg['status']);

            // Payments (canonical `payment_orders` ONLY)
            $paymentOrders = [];
            if ($canReadPayments) {
                $stPay = $pdo->prepare('SELECT * FROM payment_orders WHERE registration_id = :rid ORDER BY created_at DESC');
                $stPay->execute([':rid' => $singleId]);
                foreach (($stPay->fetchAll(PDO::FETCH_ASSOC) ?: []) as $pr) {
                    $paymentOrders[] = [
                        'id'              => (string)$pr['id'],
                        'registrationId'  => (string)$pr['registration_id'],
                        'provider'        => (string)($pr['provider'] ?? 'RAZORPAY'),
                        'providerOrderId' => (string)($pr['provider_order_id'] ?? ''),
                        'amountInr'       => (int)($pr['amount_inr'] ?? 0),
                        'discountInr'     => (int)($pr['discount_inr'] ?? 0),
                        'netAmountInr'    => (int)($pr['net_amount_inr'] ?? 0),
                        'status'          => (string)($pr['status'] ?? 'PENDING'),
                        'createdAt'       => (string)($pr['created_at'] ?? ''),
                    ];
                }
            }

            // Accommodations & Disability Disclosures (canonical tables ONLY, when permitted)
            $accommodations = null;
            $disabilityDisclosures = null;

            if ($canReadAccomm) {
                $accommodations = [];
                $stAc = $pdo->prepare('SELECT * FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC');
                $stAc->execute([':rid' => $singleId]);
                foreach ($stAc->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ar) {
                    $types = jsonDecode($ar['selected_types'] ?? '[]', []);
                    if (empty($types) && !empty($ar['accommodation_code'])) {
                        $types = [(string)$ar['accommodation_code']];
                    }
                    $accommodations[] = [
                        'id'               => (string)$ar['id'],
                        'registrationId'   => (string)$ar['registration_id'],
                        'selectedTypes'    => $types,
                        'freeTextDetails'  => pfgDecryptValue($ar['free_text_details'] ?? $ar['notes'] ?? null, 'data'),
                        'status'           => (string)($ar['status'] ?? 'REQUESTED'),
                        'coordinatorNotes' => pfgDecryptValue($ar['coordinator_notes'] ?? null, 'data'),
                        'updatedAt'        => $ar['updated_at'] ?? null,
                    ];
                }

                $disclosures = [];
                $stDis = $pdo->prepare('
                    SELECT d.id, d.self_description, d.notes, d.disability_code,
                           dc.code AS category_code, dc.name AS category_name, dc.rpwd_group
                    FROM participant_disability_disclosures d
                    LEFT JOIN disability_categories dc ON d.disability_category_id = dc.id OR d.disability_code = dc.code
                    WHERE d.registration_id = :rid
                ');
                $stDis->execute([':rid' => $singleId]);
                foreach ($stDis->fetchAll(PDO::FETCH_ASSOC) ?: [] as $dr) {
                    $disclosures[] = [
                        'id'              => (string)$dr['id'],
                        'selfDescription' => pfgDecryptValue($dr['self_description'] ?? $dr['notes'] ?? null, 'data'),
                        'categoryCode'    => $dr['category_code'] ?? $dr['disability_code'] ?? null,
                        'categoryName'    => $dr['category_name'] ?? $dr['disability_code'] ?? null,
                        'rpwdGroup'       => $dr['rpwd_group'] ?? null,
                    ];
                }
                $disabilityDisclosures = $disclosures;

                if (count($accommodations) > 0 || count($disabilityDisclosures) > 0 || $dietaryRead) {
                    pfgAdminRecordAudit($pdo, (string)$user['id'], 'SENSITIVE_DATA_READ', 'registration', $singleId, [
                        'registrationCode'   => $reg['registration_code'],
                        'accommodationCount' => count($accommodations),
                        'disclosureCount'    => count($disabilityDisclosures),
                        'dietaryRead'        => $dietaryRead,
                    ]);
                }
            }

            jsonResponse([
                'registration'             => pfgFormatRegistrationRow($reg, $canReadAccomm, true),
                'delegateDetails'          => $delegateRow,
                'attendeeDetails'          => $attendeeRow,
                'volunteerDetails'         => $volRow,
                'sliDetails'               => $sliRow,
                'credentials'              => $credentials,
                'checkins'                 => $checkins,
                'attendanceRecords'        => $attendanceRecords,
                'dutyAssignments'          => $dutyAssignments,
                'statusHistory'            => $statusHistory,
                'allowedTransitions'       => $allowedTransitions,
                'paymentOrders'            => $paymentOrders,
                'accommodationsRestricted' => !$canReadAccomm,
                'accommodations'           => $accommodations,
                'disabilityDisclosures'    => $disabilityDisclosures,
            ]);
        }

        // LIST / FILTER / EXPORT (SQL-level filtering, ordering and pagination — no row cap)
        $search = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
        $category = strtoupper(trim((string)($_GET['category'] ?? '')));
        $statusParam = strtoupper(trim((string)($_GET['status'] ?? '')));
        $assignmentStatus = strtoupper(trim((string)($_GET['assignmentStatus'] ?? '')));
        $paymentStatus = strtoupper(trim((string)($_GET['paymentStatus'] ?? '')));
        $country = trim((string)($_GET['country'] ?? ''));
        $checkinStatus = strtoupper(trim((string)($_GET['checkinStatus'] ?? '')));
        $hasAccommodations = strtolower(trim((string)($_GET['hasAccommodations'] ?? '')));
        $sortBy = trim((string)($_GET['sortBy'] ?? 'createdAt'));
        $sortOrder = strtolower(trim((string)($_GET['sortOrder'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';

        $exportFormat = strtolower(trim((string)($_GET['export'] ?? $_GET['format'] ?? '')));
        $includeAccommodations = ($_GET['includeAccommodations'] ?? '') === 'true' || ($_GET['includeAccommodations'] ?? '') === '1';

        [$page, $pageSize, $offset] = pfgAdminPageParams(25, 200);

        if ($hasAccommodations !== '' && !$canReadAccomm) {
            jsonResponse(['error' => 'Accommodations read permission required to filter by accommodation status.'], 403);
        }

        if ($exportFormat === 'xlsx') {
            jsonResponse(['error' => 'XLSX export is not supported. Use format=csv for RFC-4180 CSV export.'], 400);
        }

        if ($exportFormat === 'csv') {
            if (!hasPermission($user, PERMISSIONS['REGISTRATIONS_EXPORT'])) {
                jsonResponse(['error' => 'Export permission required.'], 403);
            }
            if ($includeAccommodations && !$canReadAccomm) {
                jsonResponse(['error' => 'Accommodations read permission required for accommodation export.'], 403);
            }
        }

        $where = ['r.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = "(LOWER(r.full_name) LIKE :q OR LOWER(r.email) LIKE :q OR LOWER(r.registration_code) LIKE :q OR LOWER(r.phone) LIKE :q OR LOWER(COALESCE(r.organization_name, '')) LIKE :q)";
            $params[':q'] = pfgRegLike($search);
        }

        if ($category !== '') {
            if ($category === 'ATTENDEE') {
                // Attendee tab = the ATTENDEE category plus historical LEGACY attendee-like categories.
                $where[] = "UPPER(r.category_code) NOT IN ('VOLUNTEER', 'SLI')";
            } else {
                $where[] = 'UPPER(r.category_code) = :category';
                $params[':category'] = $category;
            }
        }

        if ($statusParam !== '') {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $statusParam))));
            $ph = [];
            foreach ($statuses as $idx => $stVal) {
                $key = ':status' . $idx;
                $ph[] = $key;
                $params[$key] = $stVal;
            }
            if ($ph) {
                $where[] = 'UPPER(r.status) IN (' . implode(', ', $ph) . ')';
            }
        }

        if ($assignmentStatus !== '' && pfgAdminColumnExists($pdo, 'registrations', 'assignment_status')) {
            $where[] = 'UPPER(r.assignment_status) = :assign_status';
            $params[':assign_status'] = $assignmentStatus;
        }

        if ($paymentStatus !== '') {
            $where[] = 'UPPER(r.payment_status) = :pay_status';
            $params[':pay_status'] = $paymentStatus;
        }

        if ($country !== '') {
            $where[] = 'LOWER(r.country) LIKE :country';
            $params[':country'] = pfgRegLike($country);
        }

        if ($checkinStatus !== '' && pfgAdminTableExists($pdo, 'attendance_records')) {
            $exists = "EXISTS (SELECT 1 FROM attendance_records ar WHERE ar.registration_id = r.id AND ar.status = 'PRESENT')";
            if ($checkinStatus === 'CHECKED_IN') {
                $where[] = $exists;
            } elseif ($checkinStatus === 'NOT_CHECKED_IN') {
                $where[] = 'NOT ' . $exists;
            }
        }

        if ($hasAccommodations === 'true') {
            $where[] = 'EXISTS (SELECT 1 FROM accommodation_requests acr WHERE acr.registration_id = r.id)';
        } elseif ($hasAccommodations === 'false') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM accommodation_requests acr WHERE acr.registration_id = r.id)';
        }

        $orderCol = match ($sortBy) {
            'fullName'         => 'r.full_name',
            'registrationCode' => 'r.registration_code',
            'status'           => 'r.status',
            'categoryCode'     => 'r.category_code',
            default            => 'r.created_at',
        };
        $whereSql = implode(' AND ', $where);
        $orderSql = "ORDER BY {$orderCol} {$sortOrder}, r.id ASC";

        // CSV Export Mode (strictly RFC-4180 CSV with formula neutralization; XLSX rejected above)
        if ($exportFormat === 'csv') {
            $batch = 500;
            $exportRows = [];
            $exportOffset = 0;
            do {
                $st = $pdo->prepare("SELECT r.* FROM registrations r WHERE {$whereSql} {$orderSql} LIMIT {$batch} OFFSET {$exportOffset}");
                $st->execute($params);
                $chunk = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach (pfgRegEnrichRows($pdo, $chunk, $canReadAccomm && $includeAccommodations) as $r) {
                    $base = [
                        'Registration Code'  => $r['registrationCode'],
                        'Full Name'          => $r['fullName'],
                        'Email'              => $r['email'],
                        'Phone'              => $r['phone'],
                        'Country'            => $r['country'],
                        'Category'           => $r['categoryCode'],
                        'Status'             => $r['status'],
                        'Assignment Status'  => $r['assignmentStatus'],
                        'Payment Status'     => $r['paymentStatus'],
                        'Occupation'         => $r['occupation'] ?? '',
                        'Organization'       => $r['organizationName'] ?? '',
                        'Designation'        => $r['designation'] ?? '',
                        'Attending Days'     => implode(', ', is_array($r['attendingDays']) ? $r['attendingDays'] : []),
                        'Credential Code'    => $r['credentialCode'] ?? '',
                        'Credential Status'  => $r['credentialStatus'] ?? '',
                        'Checked In'         => $r['checkedIn'] ? 'Yes' : 'No',
                        'Check-in Count'     => $r['checkinCount'],
                        'Last Check-in Venue' => $r['lastCheckinVenue'] ?? '',
                        'Submitted At'       => $r['submittedAt'] ?? '',
                    ];
                    if ($includeAccommodations && $canReadAccomm) {
                        $base['Accommodation Requested'] = !empty($r['hasAccommodationRequest']) ? 'Yes' : 'No';
                        $base['Accommodation Status'] = $r['accommodationStatus'] ?? '';
                        $base['Accommodation Types'] = implode('; ', $r['accommodationTypes'] ?? []);
                        $base['Accommodation Details'] = $r['accommodationFreeText'] ?? '';
                    }
                    $exportRows[] = $base;
                }
                $exportOffset += $batch;
            } while (count($chunk) === $batch && $exportOffset < 200000);

            pfgAdminRecordAudit($pdo, (string)$user['id'], 'REGISTRATIONS_EXPORTED', 'registration', null, [
                'format'                => $exportFormat,
                'rowCount'              => count($exportRows),
                'includeAccommodations' => $includeAccommodations && $canReadAccomm,
            ]);
            if ($includeAccommodations && $canReadAccomm) {
                pfgAdminRecordAudit($pdo, (string)$user['id'], 'SENSITIVE_DATA_READ', 'registration', null, [
                    'endpoint'    => '/api/admin/registrations?export=csv',
                    'recordCount' => count($exportRows),
                    'export'      => true,
                ]);
            }

            $dateSlug = date('Y-m-d');
            $headers = count($exportRows) > 0
                ? array_keys($exportRows[0])
                : ['Registration Code', 'Full Name', 'Email', 'Category', 'Status'];

            $lines = [implode(',', array_map('pfgEscapeCsvCell', $headers))];
            foreach ($exportRows as $er) {
                $cells = [];
                foreach ($headers as $h) {
                    $cells[] = pfgEscapeCsvCell($er[$h] ?? '');
                }
                $lines[] = implode(',', $cells);
            }
            $csvContent = implode("\r\n", $lines);

            http_response_code(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pfg2027-registrations-' . $dateSlug . '.csv"');
            echo $csvContent;
            exit;
        }

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$whereSql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $st = $pdo->prepare("SELECT r.* FROM registrations r WHERE {$whereSql} {$orderSql} LIMIT {$pageSize} OFFSET {$offset}");
        $st->execute($params);
        $pageRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = pfgRegEnrichRows($pdo, $pageRows, $canReadAccomm);

        // The list embeds accommodation text for authorized coordinators — that is a sensitive read and is audited.
        if ($canReadAccomm) {
            $withAccomm = count(array_filter($items, static fn(array $i): bool => !empty($i['hasAccommodationRequest'])));
            if ($withAccomm > 0) {
                pfgAdminRecordAudit($pdo, (string)$user['id'], 'SENSITIVE_DATA_READ', 'registration', null, [
                    'endpoint'    => '/api/admin/registrations',
                    'recordCount' => $withAccomm,
                    'page'        => $page,
                ]);
            }
        }

        jsonResponse([
            'items'      => $items,
            'pagination' => pfgAdminPaginationMeta($page, $pageSize, $total),
        ]);
    }

    // =========================================================================
    // PATCH / POST: Lifecycle status, review notes, legacy duty fields, legacy credential revocation
    // =========================================================================
    if ($method === 'PATCH' || $method === 'POST') {
        requireCsrfToken($user);

        $input = readRequestInput();
        $id = pfgResolveRegistrationId($input);

        if ($id === '') {
            jsonResponse(['error' => 'Registration ID is required.'], 400);
        }

        $credentialAction = isset($input['credentialAction']) && is_string($input['credentialAction'])
            ? strtoupper(trim($input['credentialAction']))
            : '';

        // D-01: Participant QR entry pass issuance is permanently discontinued
        if ($credentialAction === 'ISSUE' || $credentialAction === 'REISSUE') {
            jsonResponse([
                'error'      => 'Participant QR entry pass issuance has been discontinued (Decision D-01). Registrations use official Registration IDs and manual desk verification.',
                'deprecated' => true,
            ], 410);
        }

        $hasStatusChange = isset($input['status']) && is_string($input['status']) && trim($input['status']) !== '';
        $hasNotesChange = array_key_exists('reviewNotes', $input) || array_key_exists('publicMessage', $input);
        $hasVolunteerAssign = !empty($input['volunteerAssignment']) && is_array($input['volunteerAssignment']);
        $hasSliAssign = !empty($input['sliAssignment']) && is_array($input['sliAssignment']);

        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['error' => 'Registration not found.'], 404);
        }
        $catUpper = strtoupper((string)$existing['category_code']);

        // Granular write permissions BEFORE any database mutation.
        // Volunteer / SLI coordinators may review their own category through volunteers.review / sli.review.
        $canReviewCategory = ($catUpper === 'VOLUNTEER' && hasPermission($user, PERMISSIONS['VOLUNTEERS_REVIEW']))
            || ($catUpper === 'SLI' && hasPermission($user, PERMISSIONS['SLI_REVIEW']));
        if (($hasStatusChange || $hasNotesChange) && !hasPermission($user, PERMISSIONS['REGISTRATIONS_WRITE']) && !$canReviewCategory) {
            requirePermission(PERMISSIONS['REGISTRATIONS_WRITE'], $user);
        }
        if ($hasVolunteerAssign && !hasPermission($user, PERMISSIONS['VOLUNTEERS_REVIEW'])) {
            requirePermission(PERMISSIONS['VOLUNTEERS_REVIEW'], $user);
        }
        if ($hasSliAssign && !hasPermission($user, PERMISSIONS['SLI_REVIEW'])) {
            requirePermission(PERMISSIONS['SLI_REVIEW'], $user);
        }
        if ($credentialAction === 'REVOKE' && !hasPermission($user, PERMISSIONS['CREDENTIALS_REVOKE'])) {
            requirePermission(PERMISSIONS['CREDENTIALS_REVOKE'], $user);
        }
        if (!$hasStatusChange && !$hasNotesChange && !$hasVolunteerAssign && !$hasSliAssign && $credentialAction === '') {
            requirePermission(PERMISSIONS['REGISTRATIONS_WRITE'], $user);
        }

        // ---- Canonical lifecycle validation ----
        $prevStatus = (string)$existing['status'];
        $prevNormalized = pfgAdminNormalizeStatus($prevStatus);
        $newStatus = $hasStatusChange ? pfgAdminNormalizeStatus((string)$input['status']) : null;
        $knownStatuses = ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'ADDITIONAL_INFO_REQUIRED', 'RESUBMITTED', 'CONFIRMED', 'APPROVED', 'WAITLISTED', 'REJECTED', 'WITHDRAWN', 'CANCELLED'];
        if ($newStatus !== null && !in_array($newStatus, $knownStatuses, true)) {
            jsonResponse(['error' => "Invalid status value: {$newStatus}"], 400);
        }
        $statusChanged = $newStatus !== null && $newStatus !== $prevNormalized;
        $allowedTransitions = pfgAdminAllowedTransitions($catUpper, $prevStatus);
        if ($statusChanged && !in_array($newStatus, $allowedTransitions, true)) {
            jsonResponse([
                'error'              => "Illegal status transition {$prevNormalized} → {$newStatus} for a {$catUpper} registration.",
                'currentStatus'      => $prevNormalized,
                'allowedTransitions' => $allowedTransitions,
            ], 409);
        }

        // Duty fields may only be set for approved applicants (or in the same request that approves them)
        $willBeApproved = $statusChanged ? pfgAdminIsApprovedStatus($newStatus) : pfgAdminIsApprovedStatus($prevStatus);
        if (($hasVolunteerAssign || $hasSliAssign) && !$willBeApproved) {
            jsonResponse(['error' => 'Only APPROVED applicants can be assigned duties.'], 400);
        }

        $reviewNotes = array_key_exists('reviewNotes', $input) ? (string)$input['reviewNotes'] : null;
        $publicMessage = array_key_exists('publicMessage', $input) ? trim((string)$input['publicMessage']) : null;
        if ($reviewNotes !== null && strlen($reviewNotes) > 4000) {
            jsonResponse(['error' => 'reviewNotes is limited to 4000 characters.'], 400);
        }
        if ($publicMessage !== null && strlen($publicMessage) > 1000) {
            jsonResponse(['error' => 'publicMessage is limited to 1000 characters.'], 400);
        }

        // Legacy duty assignment string/array bounds validation (MED-04) BEFORE any DB write
        $validateLegacyString = static function ($raw, string $fieldLabel, int $maxLen): ?string {
            if ($raw === null) {
                return null;
            }
            if (!is_string($raw)) {
                jsonResponse(['error' => "{$fieldLabel} must be a string."], 400);
            }
            $trimmed = trim($raw);
            if (mb_strlen($trimmed, 'UTF-8') > $maxLen) {
                jsonResponse(['error' => "{$fieldLabel} must not exceed {$maxLen} characters."], 400);
            }
            return $trimmed;
        };

        $validatedVolAssign = [];
        if ($hasVolunteerAssign) {
            $va = $input['volunteerAssignment'];
            $volBounds = [
                'assignedDepartment'     => ['assignedDepartment', 120],
                'assignedResponsibility' => ['assignedResponsibility', 2000],
                'assignedSupervisor'     => ['assignedSupervisor', 150],
                'assignedVenue'          => ['assignedVenue', 200],
                'assignedShift'          => ['assignedShift', 100],
                'reportingTime'          => ['reportingTime', 80],
                'assignmentNotes'        => ['assignmentNotes', 2000],
            ];
            foreach ($volBounds as $k => [$label, $maxLen]) {
                if (array_key_exists($k, $va)) {
                    $validatedVolAssign[$k] = $validateLegacyString($va[$k], $label, $maxLen);
                }
            }
        }

        $validatedSliAssign = [];
        if ($hasSliAssign) {
            $sa = $input['sliAssignment'];
            $sliBounds = [
                'assignedVenue'   => ['assignedVenue', 200],
                'dutyTimes'       => ['dutyTimes', 150],
                'assignmentNotes' => ['assignmentNotes', 2000],
            ];
            foreach ($sliBounds as $k => [$label, $maxLen]) {
                if (array_key_exists($k, $sa)) {
                    $validatedSliAssign[$k] = $validateLegacyString($sa[$k], $label, $maxLen);
                }
            }
            if (array_key_exists('assignedSessions', $sa) && $sa['assignedSessions'] !== null) {
                if (!is_array($sa['assignedSessions'])) {
                    jsonResponse(['error' => 'assignedSessions must be an array of strings.'], 400);
                }
                if (count($sa['assignedSessions']) > 50) {
                    jsonResponse(['error' => 'assignedSessions cannot exceed 50 items.'], 400);
                }
                $cleanSessions = [];
                foreach ($sa['assignedSessions'] as $item) {
                    if (!is_string($item)) {
                        jsonResponse(['error' => 'Every assignedSessions entry must be a string.'], 400);
                    }
                    $t = trim($item);
                    if (mb_strlen($t, 'UTF-8') > 200) {
                        jsonResponse(['error' => 'Each assignedSessions entry must not exceed 200 characters.'], 400);
                    }
                    if ($t !== '') {
                        $cleanSessions[] = $t;
                    }
                }
                $validatedSliAssign['assignedSessions'] = $cleanSessions;
            }
        }

        $beforeState = [
            'status'      => $existing['status'],
            'reviewNotes' => $existing['review_notes'],
        ];

        $now = nowSql();
        $nextStatus = $statusChanged ? $newStatus : $prevStatus;
        $nextNotes = $reviewNotes !== null ? $reviewNotes : $existing['review_notes'];
        $touchedReview = $statusChanged || $reviewNotes !== null;
        $reviewerId = $touchedReview ? (string)$user['id'] : $existing['reviewer_id'];
        $reviewedAt = $touchedReview ? $now : $existing['reviewed_at'];
        $hasPublicNoteCol = pfgAdminColumnExists($pdo, 'registrations', 'public_review_note');
        $actorRole = pfgAdminPrimaryRole($user);

        $dutiesCancelled = 0;
        $pdo->beginTransaction();
        try {
            $sets = 'status = :status, review_notes = :review_notes, reviewer_id = :reviewer_id, reviewed_at = :reviewed_at, updated_at = :updated_at';
            $upParams = [
                ':status'       => $nextStatus,
                ':review_notes' => $nextNotes,
                ':reviewer_id'  => $reviewerId,
                ':reviewed_at'  => $reviewedAt,
                ':updated_at'   => $now,
                ':id'           => $id,
            ];
            if ($hasPublicNoteCol && $publicMessage !== null) {
                $sets .= ', public_review_note = :pub';
                $upParams[':pub'] = $publicMessage !== '' ? $publicMessage : null;
            }
            $pdo->prepare("UPDATE registrations SET {$sets} WHERE id = :id")->execute($upParams);

            if ($statusChanged) {
                // Fast-track decision straight from SUBMITTED / RESUBMITTED: record the implicit review step first.
                $fastTracked = pfgAdminRegistrationTrack($catUpper) === 'REVIEW'
                    && in_array($prevNormalized, ['SUBMITTED', 'RESUBMITTED'], true)
                    && in_array($newStatus, ['APPROVED', 'REJECTED', 'WAITLISTED', 'ADDITIONAL_INFO_REQUIRED'], true);
                if ($fastTracked) {
                    pfgAdminRecordStatusHistory($pdo, $id, $prevNormalized, 'UNDER_REVIEW', (string)$user['id'], $actorRole, 'Implicit review step (decision recorded directly from ' . $prevNormalized . ').', null, nowSql(time() - 1));
                }
                pfgAdminRecordStatusHistory(
                    $pdo,
                    $id,
                    $fastTracked ? 'UNDER_REVIEW' : $prevNormalized,
                    $newStatus,
                    (string)$user['id'],
                    $actorRole,
                    $reviewNotes !== null && trim($reviewNotes) !== '' ? $reviewNotes : null,
                    $publicMessage !== null && $publicMessage !== '' ? $publicMessage : null
                );

                // A registration that is no longer active cannot keep live duties.
                if (in_array($newStatus, ['REJECTED', 'CANCELLED', 'WITHDRAWN'], true) && pfgAdminTableExists($pdo, 'duty_assignments')) {
                    $cd = $pdo->prepare("UPDATE duty_assignments SET status = 'CANCELLED', updated_at = :now WHERE registration_id = :rid AND status = 'ASSIGNED'");
                    $cd->execute([':now' => $now, ':rid' => $id]);
                    $dutiesCancelled = $cd->rowCount();
                    if ($dutiesCancelled > 0 && pfgAdminColumnExists($pdo, 'registrations', 'assignment_status')) {
                        $pdo->prepare("UPDATE registrations SET assignment_status = 'UNASSIGNED' WHERE id = :id")->execute([':id' => $id]);
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txe;
        }

        // Refresh registration row
        $stmt->execute([':id' => $id]);
        $updatedReg = $stmt->fetch(PDO::FETCH_ASSOC);

        // Volunteer assignment update (legacy free-text duty fields; structured rosters live in /api/admin/assignments)
        $updatedVolunteer = null;
        $existingVol = null;
        if ($hasVolunteerAssign) {
            $stVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
            $stVol->execute([':rid' => $id]);
            $existingVol = $stVol->fetch(PDO::FETCH_ASSOC);

            if ($existingVol) {
                $upVol = $pdo->prepare("
                    UPDATE volunteer_applications
                    SET assigned_department = :dept,
                        assigned_responsibility = :resp,
                        assigned_supervisor = :sup,
                        assigned_venue = :venue,
                        assigned_shift = :shift,
                        reporting_time = :rtime,
                        assignment_notes = :notes,
                        updated_at = :now
                    WHERE id = :id
                ");
                $upVol->execute([
                    ':dept'  => array_key_exists('assignedDepartment', $validatedVolAssign) ? $validatedVolAssign['assignedDepartment'] : $existingVol['assigned_department'],
                    ':resp'  => array_key_exists('assignedResponsibility', $validatedVolAssign) ? $validatedVolAssign['assignedResponsibility'] : $existingVol['assigned_responsibility'],
                    ':sup'   => array_key_exists('assignedSupervisor', $validatedVolAssign) ? $validatedVolAssign['assignedSupervisor'] : $existingVol['assigned_supervisor'],
                    ':venue' => array_key_exists('assignedVenue', $validatedVolAssign) ? $validatedVolAssign['assignedVenue'] : $existingVol['assigned_venue'],
                    ':shift' => array_key_exists('assignedShift', $validatedVolAssign) ? $validatedVolAssign['assignedShift'] : $existingVol['assigned_shift'],
                    ':rtime' => array_key_exists('reportingTime', $validatedVolAssign) ? $validatedVolAssign['reportingTime'] : $existingVol['reporting_time'],
                    ':notes' => array_key_exists('assignmentNotes', $validatedVolAssign) ? $validatedVolAssign['assignmentNotes'] : $existingVol['assignment_notes'],
                    ':now'   => $now,
                    ':id'    => $existingVol['id'],
                ]);
            } else {
                $insVol = $pdo->prepare("
                    INSERT INTO volunteer_applications (
                        id, registration_id, assigned_department, assigned_responsibility,
                        assigned_supervisor, assigned_venue, assigned_shift, reporting_time,
                        assignment_notes, created_at, updated_at
                    ) VALUES (
                        :id, :rid, :dept, :resp, :sup, :venue, :shift, :rtime, :notes, :now, :now
                    )
                ");
                $insVol->execute([
                    ':id'    => uuid4(),
                    ':rid'   => $id,
                    ':dept'  => $validatedVolAssign['assignedDepartment'] ?? null,
                    ':resp'  => $validatedVolAssign['assignedResponsibility'] ?? null,
                    ':sup'   => $validatedVolAssign['assignedSupervisor'] ?? null,
                    ':venue' => $validatedVolAssign['assignedVenue'] ?? null,
                    ':shift' => $validatedVolAssign['assignedShift'] ?? null,
                    ':rtime' => $validatedVolAssign['reportingTime'] ?? null,
                    ':notes' => $validatedVolAssign['assignmentNotes'] ?? null,
                    ':now'   => $now,
                ]);
            }
            $stVol->execute([':rid' => $id]);
            $updatedVolunteer = pfgFormatVolunteerRow($stVol->fetch(PDO::FETCH_ASSOC) ?: null);
        }

        // SLI assignment update
        $updatedSli = null;
        $existingSli = null;
        if ($hasSliAssign) {
            $stSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
            $stSli->execute([':rid' => $id]);
            $existingSli = $stSli->fetch(PDO::FETCH_ASSOC);

            $sessionsJson = array_key_exists('assignedSessions', $validatedSliAssign)
                ? jsonEncode($validatedSliAssign['assignedSessions'])
                : ($existingSli['assigned_sessions'] ?? '[]');

            if ($existingSli) {
                $upSli = $pdo->prepare("
                    UPDATE sli_applications
                    SET assigned_sessions = :sessions,
                        assigned_venue = :venue,
                        duty_times = :duty,
                        assignment_notes = :notes,
                        updated_at = :now
                    WHERE id = :id
                ");
                $upSli->execute([
                    ':sessions' => $sessionsJson,
                    ':venue'    => array_key_exists('assignedVenue', $validatedSliAssign) ? $validatedSliAssign['assignedVenue'] : $existingSli['assigned_venue'],
                    ':duty'     => array_key_exists('dutyTimes', $validatedSliAssign) ? $validatedSliAssign['dutyTimes'] : $existingSli['duty_times'],
                    ':notes'    => array_key_exists('assignmentNotes', $validatedSliAssign) ? $validatedSliAssign['assignmentNotes'] : $existingSli['assignment_notes'],
                    ':now'      => $now,
                    ':id'       => $existingSli['id'],
                ]);
            } else {
                $insSli = $pdo->prepare("
                    INSERT INTO sli_applications (
                        id, registration_id, assigned_sessions, assigned_venue, duty_times,
                        assignment_notes, created_at, updated_at
                    ) VALUES (
                        :id, :rid, :sessions, :venue, :duty, :notes, :now, :now
                    )
                ");
                $insSli->execute([
                    ':id'       => uuid4(),
                    ':rid'      => $id,
                    ':sessions' => $sessionsJson,
                    ':venue'    => $validatedSliAssign['assignedVenue'] ?? null,
                    ':duty'     => $validatedSliAssign['dutyTimes'] ?? null,
                    ':notes'    => $validatedSliAssign['assignmentNotes'] ?? null,
                    ':now'      => $now,
                ]);
            }
            $stSli->execute([':rid' => $id]);
            $updatedSli = pfgFormatSliRow($stSli->fetch(PDO::FETCH_ASSOC) ?: null);
        }

        // Credential revocation (legacy historical records only; new issuance discontinued per D-01)
        $revocationReason = trim((string)($input['revocationReason'] ?? 'Revoked by festival administrator'));
        if ($revocationReason === '') {
            $revocationReason = 'Revoked by festival administrator';
        }
        $credentialResult = null;
        $emailDeliveryResult = null;

        if ($credentialAction === 'REVOKE') {
            $stAct = $pdo->prepare("SELECT id FROM qr_credentials WHERE registration_id = :rid AND status = 'ACTIVE'");
            $stAct->execute([':rid' => $id]);
            foreach (($stAct->fetchAll(PDO::FETCH_ASSOC) ?: []) as $ac) {
                revokeCredential($pdo, (string)$ac['id'], $revocationReason, (string)$user['id']);
            }
            $credentialResult = ['action' => 'REVOKED'];
        }

        // Phase 4C/4M Transactional Outbox: persist admin review/assignment notification
        // (Zero QR issuance per D-01; never exposes internal review notes in email)
        $dutyDetails = null;
        if ($updatedVolunteer) {
            $dutyDetails = [
                'Department' => $updatedVolunteer['assignedDepartment'] ?? null,
                'Venue'      => $updatedVolunteer['assignedVenue'] ?? null,
                'Shift'      => $updatedVolunteer['assignedShift'] ?? null,
                'Reporting'  => $updatedVolunteer['reportingTime'] ?? null,
            ];
        } elseif ($updatedSli) {
            $dutyDetails = [
                'Assigned Venue'    => $updatedSli['assignedVenue'] ?? null,
                'Duty Times'        => $updatedSli['dutyTimes'] ?? null,
                'Assigned Sessions' => $updatedSli['assignedSessions'] ?? null,
            ];
        }

        $notificationTemplate = null;
        $notificationKeySuffix = null;
        if ($statusChanged) {
            if ($catUpper === 'VOLUNTEER') {
                $notificationTemplate = match ($newStatus) {
                    'APPROVED', 'CONFIRMED'    => 'VOLUNTEER_APPLICATION_APPROVED',
                    'REJECTED'                 => 'VOLUNTEER_APPLICATION_REJECTED',
                    'WAITLISTED'               => 'VOLUNTEER_APPLICATION_WAITLISTED',
                    'ADDITIONAL_INFO_REQUIRED' => 'VOLUNTEER_INFO_REQUESTED',
                    default                    => null,
                };
            } elseif ($catUpper === 'SLI') {
                $notificationTemplate = match ($newStatus) {
                    'APPROVED', 'CONFIRMED'    => 'SLI_APPLICATION_APPROVED',
                    'REJECTED'                 => 'SLI_APPLICATION_REJECTED',
                    'WAITLISTED'               => 'SLI_APPLICATION_WAITLISTED',
                    'ADDITIONAL_INFO_REQUIRED' => 'SLI_INFO_REQUESTED',
                    default                    => null,
                };
            } else {
                $notificationTemplate = match ($newStatus) {
                    'APPROVED', 'CONFIRMED' => 'ATTENDEE_REGISTRATION_CONFIRMED',
                    'CANCELLED'             => 'ATTENDEE_REGISTRATION_CANCELLED',
                    default                 => null,
                };
            }
            $notificationKeySuffix = 'status:' . $newStatus . ':' . substr(md5($now), 0, 8);
        } elseif ($hasVolunteerAssign && $updatedVolunteer) {
            $notificationTemplate = (!empty($existingVol['assigned_venue']) || !empty($existingVol['assigned_shift']))
                ? 'VOLUNTEER_ASSIGNMENT_UPDATED'
                : 'VOLUNTEER_ASSIGNMENT_CREATED';
            $notificationKeySuffix = 'vol-assign:' . md5(jsonEncode($dutyDetails));
        } elseif ($hasSliAssign && $updatedSli) {
            $notificationTemplate = (!empty($existingSli['assigned_venue']) || !empty($existingSli['duty_times']))
                ? 'SLI_ASSIGNMENT_UPDATED'
                : 'SLI_ASSIGNMENT_CREATED';
            $notificationKeySuffix = 'sli-assign:' . md5(jsonEncode($dutyDetails));
        }

        if ($notificationTemplate !== null && !empty($updatedReg['email'])) {
            try {
                $qInfo = pfgEnqueueNotification($pdo, [
                    'to'              => (string)$updatedReg['email'],
                    'templateCode'    => $notificationTemplate,
                    'variables'       => [
                        'fullName'         => (string)$updatedReg['full_name'],
                        'registrationCode' => (string)$updatedReg['registration_code'],
                        'categoryName'     => $catUpper,
                        'status'           => (string)$updatedReg['status'],
                        'dutyDetails'      => $dutyDetails,
                    ],
                    'recipientUserId' => $updatedReg['user_id'] ?? null,
                    'entityType'      => 'registration',
                    'entityId'        => (string)$updatedReg['id'],
                    'idempotencyKey'  => 'admin-reg:' . $updatedReg['id'] . ':' . $notificationKeySuffix,
                    'createInApp'     => true,
                    'inAppTitle'      => "Registration Update ({$updatedReg['registration_code']})",
                    'inAppMessage'    => "Your {$catUpper} registration ({$updatedReg['registration_code']}) has been updated to {$updatedReg['status']}.",
                ]);
                if (MAIL_DRIVER === 'file' && empty($qInfo['deduped'])) {
                    pfgDispatchQueuedIdNow($pdo, (string)$qInfo['queueId']);
                    $emailDeliveryResult = ['queueId' => $qInfo['queueId'], 'status' => 'CAPTURED_LOCAL'];
                } else {
                    $emailDeliveryResult = ['queueId' => $qInfo['queueId'], 'status' => (string)$qInfo['status']];
                }
            } catch (Throwable $e) {
                error_log('/api/admin/registrations.php notification enqueue error: ' . $e->getMessage());
            }
        }

        // Record admin action & audit log
        try {
            $actStmt = $pdo->prepare("
                INSERT INTO admin_actions (id, admin_user_id, target_user_id, action_type, reason, before_state, after_state, created_at)
                VALUES (:id, :admin_id, :target_id, :action_type, :reason, :before_state, :after_state, :now)
            ");
            $actStmt->execute([
                ':id'           => uuid4(),
                ':admin_id'     => $user['id'],
                ':target_id'    => $updatedReg['user_id'] ?? null,
                ':action_type'  => $credentialAction !== ''
                    ? "CREDENTIAL_{$credentialAction}"
                    : ($statusChanged ? "REGISTRATION_STATUS_{$nextStatus}" : 'REGISTRATION_UPDATED'),
                ':reason'       => $reviewNotes ?? ($input['revocationReason'] ?? null),
                ':before_state' => jsonEncode($beforeState),
                ':after_state'  => jsonEncode([
                    'status'              => $updatedReg['status'],
                    'reviewNotes'         => $updatedReg['review_notes'],
                    'volunteerAssignment' => $input['volunteerAssignment'] ?? null,
                    'sliAssignment'       => $input['sliAssignment'] ?? null,
                    'credentialResult'    => $credentialResult,
                    'dutiesCancelled'     => $dutiesCancelled,
                ]),
                ':now'          => $now,
            ]);
        } catch (Throwable $e) {
        }

        pfgAdminRecordAudit(
            $pdo,
            (string)$user['id'],
            $statusChanged ? "REGISTRATION_{$nextStatus}" : 'REGISTRATION_UPDATED',
            'registration',
            $id,
            [
                'registrationCode' => $updatedReg['registration_code'],
                'previousStatus'   => $existing['status'],
                'newStatus'        => $updatedReg['status'],
                'credentialAction' => $credentialAction ?: null,
                'dutiesCancelled'  => $dutiesCancelled,
            ]
        );

        jsonResponse([
            'registration'       => pfgFormatRegistrationRow($updatedReg, $canReadAccomm),
            'allowedTransitions' => pfgAdminAllowedTransitions($catUpper, (string)$updatedReg['status']),
            'dutiesCancelled'    => $dutiesCancelled,
            'volunteerDetails'   => $updatedVolunteer,
            'sliDetails'         => $updatedSli,
            'credentialResult'   => $credentialResult,
            'emailDelivery'      => $emailDeliveryResult,
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/registrations.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process registration admin request.'], 500);
}
