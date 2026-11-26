<?php
declare(strict_types=1);

/**
 * POST /api/attendance/mark (api/attendance/mark.php)
 * Phase 9 Atomic Manual Attendance Marking Endpoint.
 *
 * Architectural Guarantees (CAD-05, CAD-06, D-01, D-02, D-03):
 * - Auth required: RGST / ADMIN (with checkins.scan or checkins.confirm) / SUPERADMIN.
 * - D-20 MFA enforced for ADMIN / SUPERADMIN.
 * - CSRF strictly verified via requireCsrfToken().
 * - Rejects ineligible registrations (status NOT IN ('CONFIRMED','APPROVED')) with HTTP 400.
 * - Atomic duplicate-safe insert keyed on UNIQUE(edition_id, registration_id, attendance_date_ist, attendance_scope):
 *     - Already PRESENT -> HTTP 200 with alreadyMarked: true (ZERO duplicates).
 *     - Previously REVOKED -> updates to PRESENT, appends attendance_correction_logs, audits ATTENDANCE_REINSTATED, HTTP 200.
 *     - New mark -> inserts PRESENT, audits ATTENDANCE_MARKED, HTTP 201.
 * - Zero QR dependency.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Method not allowed. Use POST.'], 405);
}

function pfgAttMarkHasAnyPerm(array $user, array $perms): bool
{
    foreach ($perms as $perm) {
        if (hasPermission($user, $perm)) {
            return true;
        }
    }
    return false;
}

function pfgAttMarkIsOverrideActor(array $user): bool
{
    if (hasRole($user, ['SUPERADMIN'])) {
        return true;
    }
    if (!hasRole($user, ['ADMIN'])) {
        return false;
    }
    return hasPermission($user, PERMISSIONS['ATTENDANCE_OVERRIDE'])
        || hasPermission($user, PERMISSIONS['REGISTRATIONS_WRITE']);
}

function pfgAttMarkLoadConfig(PDO $pdo): array
{
    $defaultDates = ['2027-01-21', '2027-01-22', '2027-01-23'];
    $defaultScope = 'FESTIVAL_DAY';
    $dates = [];
    $scope = $defaultScope;
    $allowedScopes = [];

    try {
        $stmt = $pdo->prepare(
            "SELECT `key`, `value` FROM system_settings
             WHERE `key` IN ('attendance.festival_dates', 'festival.dates', 'attendance.scope', 'attendance.mode')"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['key']] = jsonDecode($row['value'] ?? null, $row['value'] ?? null);
        }

        $candidateDates = null;
        if (isset($map['attendance.festival_dates'])) {
            $val = $map['attendance.festival_dates'];
            $candidateDates = is_array($val) && isset($val['dates']) && is_array($val['dates'])
                ? $val['dates']
                : (is_array($val) ? $val : null);
        } elseif (isset($map['festival.dates']) && is_array($map['festival.dates'])) {
            $candidateDates = isset($map['festival.dates']['dates']) && is_array($map['festival.dates']['dates'])
                ? $map['festival.dates']['dates']
                : $map['festival.dates'];
        } elseif (isset($map['attendance.mode']) && is_array($map['attendance.mode']) && isset($map['attendance.mode']['festivalDates']) && is_array($map['attendance.mode']['festivalDates'])) {
            $candidateDates = $map['attendance.mode']['festivalDates'];
        }

        if (is_array($candidateDates)) {
            foreach ($candidateDates as $d) {
                if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($d)) === 1) {
                    $clean = trim($d);
                    if (!in_array($clean, $dates, true)) {
                        $dates[] = $clean;
                    }
                }
            }
        }

        $candidateScope = null;
        if (isset($map['attendance.scope'])) {
            $sv = $map['attendance.scope'];
            $candidateScope = is_array($sv) ? (string)($sv['scope'] ?? $sv['defaultScope'] ?? '') : (string)$sv;
            if (is_array($sv) && isset($sv['allowedScopes']) && is_array($sv['allowedScopes'])) {
                foreach ($sv['allowedScopes'] as $as) {
                    $cleanAs = is_string($as) ? strtoupper(trim($as)) : '';
                    if ($cleanAs !== '' && preg_match('/^[A-Z0-9_-]{2,64}$/', $cleanAs) === 1 && !in_array($cleanAs, $allowedScopes, true)) {
                        $allowedScopes[] = $cleanAs;
                    }
                }
            }
        } elseif (isset($map['attendance.mode']) && is_array($map['attendance.mode']) && !empty($map['attendance.mode']['defaultScope'])) {
            $candidateScope = (string)$map['attendance.mode']['defaultScope'];
        }
        if (isset($map['attendance.mode']) && is_array($map['attendance.mode']) && isset($map['attendance.mode']['allowedScopes']) && is_array($map['attendance.mode']['allowedScopes'])) {
            foreach ($map['attendance.mode']['allowedScopes'] as $as) {
                $cleanAs = is_string($as) ? strtoupper(trim($as)) : '';
                if ($cleanAs !== '' && preg_match('/^[A-Z0-9_-]{2,64}$/', $cleanAs) === 1 && !in_array($cleanAs, $allowedScopes, true)) {
                    $allowedScopes[] = $cleanAs;
                }
            }
        }
        if (is_string($candidateScope) && preg_match('/^[A-Z0-9_-]{2,64}$/', strtoupper(trim($candidateScope))) === 1) {
            $scope = strtoupper(trim($candidateScope));
        }
    } catch (Throwable $e) {
        $allowedScopes = [];
    }

    if (!in_array($scope, $allowedScopes, true)) {
        array_unshift($allowedScopes, $scope);
    }

    if (count($dates) === 0) {
        $dates = $defaultDates;
    }

    return [
        'festivalDates' => $dates,
        'defaultScope'  => $scope,
        'allowedScopes' => $allowedScopes,
    ];
}

function pfgAttMarkFetchRecord(PDO $pdo, string $editionId, string $registrationId, string $dateIst, string $scope, bool $forUpdate = false): ?array
{
    if ($forUpdate) {
        $lockClause = ($pdo instanceof PfgPdo && $pdo->isSqlite) ? '' : ' FOR UPDATE';
        $stmt = $pdo->prepare("
            SELECT *
            FROM attendance_records
            WHERE edition_id = :ed
              AND registration_id = :reg_id
              AND attendance_date_ist = :att_date
              AND attendance_scope = :scope
            LIMIT 1{$lockClause}
        ");
        $stmt->execute([
            ':ed'       => $editionId,
            ':reg_id'   => $registrationId,
            ':att_date' => $dateIst,
            ':scope'    => $scope,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['marked_by_name'] = null;
        if (!empty($row['marked_by_user_id'])) {
            $uStmt = $pdo->prepare('SELECT full_name FROM users WHERE id = :uid LIMIT 1');
            $uStmt->execute([':uid' => (string)$row['marked_by_user_id']]);
            $name = $uStmt->fetchColumn();
            if (is_string($name) && $name !== '') {
                $row['marked_by_name'] = $name;
            }
        }
        return $row;
    }

    $stmt = $pdo->prepare("
        SELECT ar.*, u.full_name AS marked_by_name
        FROM attendance_records ar
        LEFT JOIN users u ON u.id = ar.marked_by_user_id
        WHERE ar.edition_id = :ed
          AND ar.registration_id = :reg_id
          AND ar.attendance_date_ist = :att_date
          AND ar.attendance_scope = :scope
        LIMIT 1
    ");
    $stmt->execute([
        ':ed'       => $editionId,
        ':reg_id'   => $registrationId,
        ':att_date' => $dateIst,
        ':scope'    => $scope,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pfgAttMarkFormatRecord(array $rec, array $reg, array $actor, bool $isOverrideActor): array
{
    $markedAt = (string)($rec['marked_at'] ?? '');
    $markedById = (string)($rec['marked_by_user_id'] ?? '');
    $recStatus = strtoupper((string)($rec['status'] ?? 'PRESENT'));
    $markedTs = $markedAt !== '' ? strtotime($markedAt) : false;
    $elapsed = ($markedTs !== false) ? max(0, time() - $markedTs) : 901;
    $withinWindow = ($markedTs !== false && $elapsed <= 900);
    $isOwnMark = ($markedById !== '' && $markedById === (string)$actor['id']);
    $canDeskUndo = ($recStatus === 'PRESENT') && ($isOverrideActor || ($isOwnMark && $withinWindow));

    return [
        'id'                => (string)$rec['id'],
        'recordId'          => (string)$rec['id'],
        'editionId'         => (string)$rec['edition_id'],
        'registrationId'    => (string)$rec['registration_id'],
        'registrationCode'  => (string)$reg['registration_code'],
        'fullName'          => (string)$reg['full_name'],
        'categoryCode'      => strtoupper((string)$reg['category_code']),
        'attendanceDateIst' => (string)$rec['attendance_date_ist'],
        'attendanceScope'   => (string)$rec['attendance_scope'],
        'status'            => $recStatus,
        'markedByUserId'    => $markedById,
        'markedBy'          => (string)($rec['marked_by_name'] ?? $actor['fullName'] ?? 'Desk Staff'),
        'markedAt'          => $markedAt,
        'markedAtIst'       => $markedAt !== '' ? formatIstTimestamp($markedAt) : null,
        'stationId'         => $rec['station_id'] !== null ? (string)$rec['station_id'] : null,
        'venueLabel'        => $rec['venue_label'] !== null ? (string)$rec['venue_label'] : null,
        'canDeskUndo'       => $canDeskUndo,
        'undoSecondsLeft'   => (!$isOverrideActor && $isOwnMark && $withinWindow) ? max(0, 900 - $elapsed) : 0,
    ];
}

function pfgAttMarkComputeMetrics(PDO $pdo, string $editionId, string $scope, array $festivalDates): array
{
    $byCategory = [
        'ATTENDEE'  => ['registered' => 0, 'eligible' => 0, 'uniquePresent' => 0],
        'VOLUNTEER' => ['registered' => 0, 'eligible' => 0, 'uniquePresent' => 0],
        'SLI'       => ['registered' => 0, 'eligible' => 0, 'uniquePresent' => 0],
    ];
    $totalRegistrations = 0;
    $eligibleRegistrations = 0;

    $regStmt = $pdo->prepare("
        SELECT UPPER(r.category_code) AS cat_code,
               COUNT(*) AS registered_cnt,
               SUM(CASE WHEN UPPER(r.status) IN ('CONFIRMED', 'APPROVED') THEN 1 ELSE 0 END) AS eligible_cnt
        FROM registrations r
        WHERE r.edition_id = :ed AND r.deleted_at IS NULL
        GROUP BY UPPER(r.category_code)
    ");
    $regStmt->execute([':ed' => $editionId]);
    foreach ($regStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $cat = (string)($row['cat_code'] ?? 'ATTENDEE');
        $regCnt = (int)($row['registered_cnt'] ?? 0);
        $eligCnt = (int)($row['eligible_cnt'] ?? 0);
        $totalRegistrations += $regCnt;
        $eligibleRegistrations += $eligCnt;
        if (!isset($byCategory[$cat])) {
            $byCategory[$cat] = ['registered' => 0, 'eligible' => 0, 'uniquePresent' => 0];
        }
        $byCategory[$cat]['registered'] = $regCnt;
        $byCategory[$cat]['eligible'] = $eligCnt;
    }

    $uniquePeoplePresent = 0;
    $totalAttendanceEvents = 0;
    $presStmt = $pdo->prepare("
        SELECT UPPER(r.category_code) AS cat_code,
               COUNT(DISTINCT ar.registration_id) AS unique_present_cnt,
               COUNT(ar.id) AS event_cnt
        FROM attendance_records ar
        INNER JOIN registrations r ON r.id = ar.registration_id
        WHERE ar.edition_id = :ed
          AND ar.attendance_scope = :scope
          AND UPPER(ar.status) = 'PRESENT'
          AND r.deleted_at IS NULL
        GROUP BY UPPER(r.category_code)
    ");
    $presStmt->execute([':ed' => $editionId, ':scope' => $scope]);
    foreach ($presStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $cat = (string)($row['cat_code'] ?? 'ATTENDEE');
        $uniq = (int)($row['unique_present_cnt'] ?? 0);
        $evts = (int)($row['event_cnt'] ?? 0);
        $uniquePeoplePresent += $uniq;
        $totalAttendanceEvents += $evts;
        if (!isset($byCategory[$cat])) {
            $byCategory[$cat] = ['registered' => 0, 'eligible' => 0, 'uniquePresent' => 0];
        }
        $byCategory[$cat]['uniquePresent'] = $uniq;
    }

    $byDay = [];
    foreach ($festivalDates as $d) {
        $byDay[$d] = 0;
    }
    $dayStmt = $pdo->prepare("
        SELECT ar.attendance_date_ist, COUNT(DISTINCT ar.registration_id) AS present_cnt
        FROM attendance_records ar
        INNER JOIN registrations r ON r.id = ar.registration_id
        WHERE ar.edition_id = :ed
          AND ar.attendance_scope = :scope
          AND UPPER(ar.status) = 'PRESENT'
          AND r.deleted_at IS NULL
        GROUP BY ar.attendance_date_ist
    ");
    $dayStmt->execute([':ed' => $editionId, ':scope' => $scope]);
    foreach ($dayStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $d = (string)($row['attendance_date_ist'] ?? '');
        if ($d !== '') {
            $byDay[$d] = (int)($row['present_cnt'] ?? 0);
        }
    }

    return [
        'totalRegistrations'    => $totalRegistrations,
        'eligibleRegistrations' => $eligibleRegistrations,
        'uniquePeoplePresent'   => $uniquePeoplePresent,
        'totalAttendanceEvents' => $totalAttendanceEvents,
        'notYetPresent'         => max(0, $eligibleRegistrations - $uniquePeoplePresent),
        'byCategory'            => $byCategory,
        'byDay'                 => $byDay,
    ];
}

try {
    $user = requireAuth();
    enforceStaffMfaGate($user);

    $canMarkAttendance = hasRole($user, ['SUPERADMIN', 'RGST'])
        || (hasRole($user, ['ADMIN']) && pfgAttMarkHasAnyPerm($user, [
            'checkins.scan',
            'attendance.mark',
            PERMISSIONS['CHECKINS_CONFIRM'],
            PERMISSIONS['QR_SCAN'],
            PERMISSIONS['ATTENDANCE_OVERRIDE'],
            PERMISSIONS['REGISTRATIONS_WRITE'],
            'registrations.manage',
        ]));

    if (!$canMarkAttendance) {
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'attendance',
            'resourceId'   => '/api/attendance/mark',
            'metadata'     => ['roles' => $user['roles'] ?? []],
        ]);
        jsonResponse(['error' => 'Forbidden: authorized registration desk or admin permission is required to mark attendance.'], 403);
    }

    requireCsrfToken($user);

    $input = readRequestInput();
    $registrationId = trim((string)($input['registrationId'] ?? $input['registration_id'] ?? ''));
    $registrationCode = strtoupper(trim((string)($input['registrationCode'] ?? $input['registration_code'] ?? '')));
    $attendanceDateIst = trim((string)($input['attendanceDateIst'] ?? $input['attendance_date_ist'] ?? ''));
    $scopeInput = strtoupper(trim((string)($input['attendanceScope'] ?? $input['attendance_scope'] ?? '')));
    $stationIdInput = trim((string)($input['stationId'] ?? $input['station_id'] ?? ''));
    $venueLabel = trim((string)($input['venueLabel'] ?? $input['venue_label'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));

    if ($registrationId === '' && $registrationCode === '') {
        jsonResponse(['error' => 'Either registrationId or registrationCode is required.'], 400);
    }

    $pdo = getDb();
    $cfg = pfgAttMarkLoadConfig($pdo);
    $festivalDates = $cfg['festivalDates'];

    if ($attendanceDateIst === '' || !in_array($attendanceDateIst, $festivalDates, true)) {
        jsonResponse([
            'error'         => 'Invalid attendanceDateIst. Allowed festival dates: ' . implode(', ', $festivalDates) . '.',
            'allowedDates'  => $festivalDates,
        ], 400);
    }

    // §8 Configurable IST Event-Day Attendance Window Restriction
    $actorId = (string)$user['id'];
    $isOverrideActor = pfgAttMarkIsOverrideActor($user);
    $todayIst = getIstDateString();
    $enforceEventDayWindow = pfgIsAttendanceEventDayWindowEnforced($pdo);
    $isFutureDateOverride = false;
    $futureOverrideReason = '';

    if ($enforceEventDayWindow && $attendanceDateIst > $todayIst) {
        $overrideRequested = !empty($input['overrideFutureDate']) || !empty($input['override_future_date']);
        $overrideReasonRaw = trim((string)($input['overrideReason'] ?? $input['override_reason'] ?? ''));

        if (!$isOverrideActor) {
            logAudit([
                'actorUserId'  => $actorId,
                'action'       => 'ACCESS_DENIED',
                'resourceType' => 'attendance',
                'resourceId'   => '/api/attendance/mark',
                'metadata'     => [
                    'reason'            => 'FUTURE_EVENT_DAY_BLOCKED',
                    'attendanceDateIst' => $attendanceDateIst,
                    'todayIst'          => $todayIst,
                    'roles'             => $user['roles'] ?? [],
                ],
            ]);
            jsonResponse([
                'error'             => sprintf('Cannot mark attendance for future festival date %s before that event day (IST).', $attendanceDateIst),
                'code'              => 'FUTURE_EVENT_DAY_BLOCKED',
                'attendanceDateIst' => $attendanceDateIst,
                'todayIst'          => $todayIst,
            ], 403);
        }

        if (!$overrideRequested) {
            jsonResponse([
                'error'             => sprintf('Cannot mark attendance for future festival date %s before that event day (IST). Authorized override actors must pass overrideFutureDate=true with overrideReason (>= 10 characters).', $attendanceDateIst),
                'code'              => 'FUTURE_EVENT_DAY_OVERRIDE_REQUIRED',
                'attendanceDateIst' => $attendanceDateIst,
                'todayIst'          => $todayIst,
            ], 403);
        }

        $overrideReasonLen = function_exists('mb_strlen') ? mb_strlen($overrideReasonRaw, 'UTF-8') : strlen($overrideReasonRaw);
        if ($overrideReasonLen < 10) {
            jsonResponse([
                'error' => 'A documented overrideReason of at least 10 characters is required to mark attendance for a future festival date.',
                'code'  => 'OVERRIDE_REASON_REQUIRED',
            ], 400);
        }

        $isFutureDateOverride = true;
        $futureOverrideReason = function_exists('mb_substr') ? mb_substr($overrideReasonRaw, 0, 400, 'UTF-8') : substr($overrideReasonRaw, 0, 400);
        $overrideTag = '[FUTURE_DATE_OVERRIDE: ' . $futureOverrideReason . ']';
        $notes = $notes !== '' ? ($notes . ' ' . $overrideTag) : $overrideTag;
    }

    $allowedScopes = $cfg['allowedScopes'];
    $attendanceScope = $cfg['defaultScope'];
    if ($scopeInput !== '') {
        if (!in_array($scopeInput, $allowedScopes, true)) {
            jsonResponse([
                'error'         => 'Invalid attendanceScope. Allowed scopes: ' . implode(', ', $allowedScopes) . '.',
                'allowedScopes' => $allowedScopes,
            ], 400);
        }
        $attendanceScope = $scopeInput;
    }

    if (strlen($venueLabel) > 150) {
        $venueLabel = substr($venueLabel, 0, 150);
    }
    if (strlen($notes) > 500) {
        $notes = substr($notes, 0, 500);
    }

    // Lookup registration (strictly non-deleted)
    if ($registrationId !== '') {
        $regStmt = $pdo->prepare("
            SELECT id, edition_id, registration_code, full_name, category_code, status
            FROM registrations
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $regStmt->execute([':id' => $registrationId]);
    } else {
        $regStmt = $pdo->prepare("
            SELECT id, edition_id, registration_code, full_name, category_code, status
            FROM registrations
            WHERE UPPER(registration_code) = :code AND deleted_at IS NULL
            LIMIT 1
        ");
        $regStmt->execute([':code' => $registrationCode]);
    }
    $reg = $regStmt->fetch(PDO::FETCH_ASSOC);
    if (!$reg) {
        jsonResponse(['error' => 'Registration record not found.'], 404);
    }

    $regId = (string)$reg['id'];
    $editionId = (string)$reg['edition_id'];
    $regCode = (string)$reg['registration_code'];
    $regStatus = strtoupper((string)($reg['status'] ?? 'SUBMITTED'));

    // Enforce eligibility: ONLY CONFIRMED or APPROVED registrations may be marked present
    if (!in_array($regStatus, ['CONFIRMED', 'APPROVED'], true)) {
        jsonResponse([
            'error'              => sprintf(
                'Participant %s (%s) is not eligible for attendance because their registration status is %s. Only CONFIRMED or APPROVED registrations may be marked present.',
                (string)$reg['full_name'],
                $regCode,
                $regStatus
            ),
            'code'               => 'REGISTRATION_NOT_ELIGIBLE',
            'registrationStatus' => $regStatus,
        ], 400);
    }

    // Validate optional stationId against checkin_stations so FK constraint never fails
    $stationId = null;
    if ($stationIdInput !== '') {
        $stStmt = $pdo->prepare('SELECT id, name FROM checkin_stations WHERE id = :sid AND is_active = 1 LIMIT 1');
        $stStmt->execute([':sid' => $stationIdInput]);
        $stRow = $stStmt->fetch(PDO::FETCH_ASSOC);
        if ($stRow) {
            $stationId = (string)$stRow['id'];
            if ($venueLabel === '') {
                $venueLabel = (string)$stRow['name'];
            }
        }
    }

    $now = nowSql();

    $pdo->beginTransaction();
    try {
        $existing = pfgAttMarkFetchRecord($pdo, $editionId, $regId, $attendanceDateIst, $attendanceScope, true);

        // Case 1: Already marked PRESENT -> Idempotent HTTP 200, ZERO duplicates
        if ($existing && strtoupper((string)$existing['status']) === 'PRESENT') {
            $pdo->commit();
            $metrics = pfgAttMarkComputeMetrics($pdo, $editionId, $attendanceScope, $festivalDates);
            jsonResponse([
                'success'       => true,
                'alreadyMarked' => true,
                'reinstated'    => false,
                'message'       => 'Attendance was already marked PRESENT for ' . $attendanceDateIst . '.',
                'record'        => pfgAttMarkFormatRecord($existing, $reg, $user, $isOverrideActor),
                'metrics'       => $metrics,
            ], 200);
        }

        // Case 2: Previously REVOKED -> Reinstate to PRESENT + append correction log + audit
        if ($existing) {
            $prevStatus = strtoupper((string)$existing['status']);
            $recordId = (string)$existing['id'];
            $reinstateReason = $notes !== '' ? $notes : 'Reinstated at registration desk.';

            $upd = $pdo->prepare("
                UPDATE attendance_records
                SET status = 'PRESENT',
                    marked_by_user_id = :actor_id,
                    marked_at = :marked_at,
                    station_id = :station_id,
                    venue_label = :venue_label,
                    notes = :notes,
                    corrected_by_user_id = :corr_actor,
                    corrected_at = :corr_at,
                    correction_reason = :corr_reason,
                    updated_at = :updated_at
                WHERE id = :id
                  AND status = :prev_status
            ");
            $upd->execute([
                ':actor_id'    => $actorId,
                ':marked_at'   => $now,
                ':station_id'  => $stationId,
                ':venue_label' => $venueLabel !== '' ? $venueLabel : ($existing['venue_label'] ?? null),
                ':notes'       => $notes !== '' ? $notes : ($existing['notes'] ?? null),
                ':corr_actor'  => $actorId,
                ':corr_at'     => $now,
                ':corr_reason' => $reinstateReason,
                ':updated_at'  => $now,
                ':id'          => $recordId,
                ':prev_status' => $prevStatus,
            ]);

            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                $winner = pfgAttMarkFetchRecord($pdo, $editionId, $regId, $attendanceDateIst, $attendanceScope);
                if ($winner && strtoupper((string)$winner['status']) === 'PRESENT') {
                    $metrics = pfgAttMarkComputeMetrics($pdo, $editionId, $attendanceScope, $festivalDates);
                    jsonResponse([
                        'success'       => true,
                        'alreadyMarked' => true,
                        'reinstated'    => false,
                        'message'       => 'Attendance was already marked PRESENT for ' . $attendanceDateIst . '.',
                        'record'        => pfgAttMarkFormatRecord($winner, $reg, $user, $isOverrideActor),
                        'metrics'       => $metrics,
                    ], 200);
                }
                jsonResponse(['error' => 'Attendance record changed concurrently. Please refresh and retry.'], 409);
            }

            $corrLogId = uuid4();
            $insCorr = $pdo->prepare("
                INSERT INTO attendance_correction_logs
                (id, attendance_record_id, registration_id, actor_user_id, previous_status, new_status, reason, created_at)
                VALUES (:id, :rec_id, :reg_id, :actor_id, :prev_status, 'PRESENT', :reason, :now)
            ");
            $insCorr->execute([
                ':id'          => $corrLogId,
                ':rec_id'      => $recordId,
                ':reg_id'      => $regId,
                ':actor_id'    => $actorId,
                ':prev_status' => $prevStatus,
                ':reason'      => $reinstateReason,
                ':now'         => $now,
            ]);

            $pdo->commit();

            logAudit([
                'actorUserId'  => $actorId,
                'action'       => 'ATTENDANCE_REINSTATED',
                'resourceType' => 'attendance_record',
                'resourceId'   => $recordId,
                'metadata'     => [
                    'registrationId'     => $regId,
                    'registrationCode'   => $regCode,
                    'attendanceDateIst'  => $attendanceDateIst,
                    'attendanceScope'    => $attendanceScope,
                    'previousStatus'     => $prevStatus,
                    'newStatus'          => 'PRESENT',
                    'correctionLogId'    => $corrLogId,
                    'futureDateOverride' => $isFutureDateOverride,
                ],
            ]);

            if ($isFutureDateOverride) {
                logAudit([
                    'actorUserId'  => $actorId,
                    'action'       => 'ATTENDANCE_FUTURE_DATE_OVERRIDE',
                    'resourceType' => 'attendance_record',
                    'resourceId'   => $recordId,
                    'metadata'     => [
                        'registrationId'    => $regId,
                        'registrationCode'  => $regCode,
                        'attendanceDateIst' => $attendanceDateIst,
                        'todayIst'          => $todayIst,
                        'overrideReason'    => $futureOverrideReason,
                        'reinstated'        => true,
                    ],
                ]);
            }

            $saved = pfgAttMarkFetchRecord($pdo, $editionId, $regId, $attendanceDateIst, $attendanceScope) ?? $existing;
            $metrics = pfgAttMarkComputeMetrics($pdo, $editionId, $attendanceScope, $festivalDates);

            jsonResponse([
                'success'       => true,
                'alreadyMarked' => false,
                'reinstated'    => true,
                'message'       => 'Attendance reinstated to PRESENT for ' . $attendanceDateIst . '.',
                'record'        => pfgAttMarkFormatRecord($saved, $reg, $user, $isOverrideActor),
                'metrics'       => $metrics,
            ], 200);
        }

        // Case 3: Fresh atomic insert into attendance_records
        $newRecordId = uuid4();
        $ins = $pdo->prepare("
            INSERT INTO attendance_records
            (id, edition_id, registration_id, attendance_date_ist, attendance_scope, status,
             marked_by_user_id, marked_at, station_id, venue_label, notes, created_at, updated_at)
            VALUES
            (:id, :edition_id, :registration_id, :attendance_date_ist, :attendance_scope, 'PRESENT',
             :marked_by_user_id, :marked_at, :station_id, :venue_label, :notes, :created_at, :updated_at)
        ");
        $ins->execute([
            ':id'                  => $newRecordId,
            ':edition_id'          => $editionId,
            ':registration_id'     => $regId,
            ':attendance_date_ist' => $attendanceDateIst,
            ':attendance_scope'    => $attendanceScope,
            ':marked_by_user_id'   => $actorId,
            ':marked_at'           => $now,
            ':station_id'          => $stationId,
            ':venue_label'         => $venueLabel !== '' ? $venueLabel : null,
            ':notes'               => $notes !== '' ? $notes : null,
            ':created_at'          => $now,
            ':updated_at'          => $now,
        ]);

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Handle concurrent duplicate-key insert atomically (SQLSTATE 23000)
        $sqlState = (string)$e->getCode();
        if ($sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'duplicate') || str_contains(strtolower($e->getMessage()), 'unique')) {
            $winner = pfgAttMarkFetchRecord($pdo, $editionId, $regId, $attendanceDateIst, $attendanceScope);
            if ($winner && strtoupper((string)$winner['status']) === 'PRESENT') {
                $metrics = pfgAttMarkComputeMetrics($pdo, $editionId, $attendanceScope, $festivalDates);
                jsonResponse([
                    'success'       => true,
                    'alreadyMarked' => true,
                    'reinstated'    => false,
                    'message'       => 'Attendance was already marked PRESENT for ' . $attendanceDateIst . '.',
                    'record'        => pfgAttMarkFormatRecord($winner, $reg, $user, $isOverrideActor),
                    'metrics'       => $metrics,
                ], 200);
            }
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    logAudit([
        'actorUserId'  => $actorId,
        'action'       => 'ATTENDANCE_MARKED',
        'resourceType' => 'attendance_record',
        'resourceId'   => $newRecordId,
        'metadata'     => [
            'registrationId'     => $regId,
            'registrationCode'   => $regCode,
            'categoryCode'       => strtoupper((string)$reg['category_code']),
            'attendanceDateIst'  => $attendanceDateIst,
            'attendanceScope'    => $attendanceScope,
            'venueLabel'         => $venueLabel !== '' ? $venueLabel : null,
            'futureDateOverride' => $isFutureDateOverride,
        ],
    ]);

    if ($isFutureDateOverride) {
        logAudit([
            'actorUserId'  => $actorId,
            'action'       => 'ATTENDANCE_FUTURE_DATE_OVERRIDE',
            'resourceType' => 'attendance_record',
            'resourceId'   => $newRecordId,
            'metadata'     => [
                'registrationId'    => $regId,
                'registrationCode'  => $regCode,
                'attendanceDateIst' => $attendanceDateIst,
                'todayIst'          => $todayIst,
                'overrideReason'    => $futureOverrideReason,
                'reinstated'        => false,
            ],
        ]);
    }

    $saved = pfgAttMarkFetchRecord($pdo, $editionId, $regId, $attendanceDateIst, $attendanceScope);
    $metrics = pfgAttMarkComputeMetrics($pdo, $editionId, $attendanceScope, $festivalDates);

    jsonResponse([
        'success'       => true,
        'alreadyMarked' => false,
        'reinstated'    => false,
        'message'       => 'Attendance marked PRESENT for ' . $attendanceDateIst . '.',
        'record'        => $saved
            ? pfgAttMarkFormatRecord($saved, $reg, $user, $isOverrideActor)
            : [
                'id'                => $newRecordId,
                'recordId'          => $newRecordId,
                'editionId'         => $editionId,
                'registrationId'    => $regId,
                'registrationCode'  => $regCode,
                'fullName'          => (string)$reg['full_name'],
                'categoryCode'      => strtoupper((string)$reg['category_code']),
                'attendanceDateIst' => $attendanceDateIst,
                'attendanceScope'   => $attendanceScope,
                'status'            => 'PRESENT',
                'markedByUserId'    => $actorId,
                'markedBy'          => (string)($user['fullName'] ?? 'Desk Staff'),
                'markedAt'          => $now,
                'markedAtIst'       => formatIstTimestamp($now),
                'stationId'         => $stationId,
                'venueLabel'        => $venueLabel !== '' ? $venueLabel : null,
                'canDeskUndo'       => true,
                'undoSecondsLeft'   => $isOverrideActor ? 0 : 900,
            ],
        'metrics'       => $metrics,
    ], 201);
} catch (Throwable $err) {
    error_log('[POST /api/attendance/mark] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Failed to mark attendance. Please retry.'], 500);
}
