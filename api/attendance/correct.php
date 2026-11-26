<?php
declare(strict_types=1);

/**
 * POST /api/attendance/correct (api/attendance/correct.php)
 * Phase 9 Audited Attendance Correction / Undo Endpoint (Decision D-03).
 *
 * Architectural Guarantees:
 * - Auth required + D-20 MFA enforced for ADMIN / SUPERADMIN + CSRF verified via requireCsrfToken().
 * - Permission rules (D-03):
 *     - SUPERADMIN or ADMIN (with checkins.override / registrations.manage / registrations.write / checkins.confirm): allowed anytime.
 *     - RGST: allowed ONLY on records they personally marked (marked_by_user_id === user.id)
 *       within 15 minutes (900 seconds) of marked_at; otherwise returns HTTP 403.
 * - Mandatory non-empty reason (>= 5 chars).
 * - Updates attendance_records (`status`, `corrected_by_user_id`, `corrected_at`, `correction_reason`),
 *   appends immutable row to `attendance_correction_logs`, and logs `ATTENDANCE_CORRECTED` in `audit_logs`.
 * - NEVER deletes attendance rows.
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

function pfgAttCorrHasAnyPerm(array $user, array $perms): bool
{
    foreach ($perms as $perm) {
        if (hasPermission($user, $perm)) {
            return true;
        }
    }
    return false;
}

function pfgAttCorrIsOverrideActor(array $user): bool
{
    if (hasRole($user, ['SUPERADMIN'])) {
        return true;
    }
    if (!hasRole($user, ['ADMIN'])) {
        return false;
    }
    return pfgAttCorrHasAnyPerm($user, [
        'checkins.override',
        'registrations.manage',
        PERMISSIONS['REGISTRATIONS_WRITE'],
        PERMISSIONS['CHECKINS_CONFIRM'],
    ]);
}

function pfgAttCorrLoadConfig(PDO $pdo): array
{
    $defaultDates = ['2027-01-21', '2027-01-22', '2027-01-23'];
    $defaultScope = 'FESTIVAL_DAY';
    $dates = [];
    $scope = $defaultScope;

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
        } elseif (isset($map['attendance.mode']) && is_array($map['attendance.mode']) && !empty($map['attendance.mode']['defaultScope'])) {
            $candidateScope = (string)$map['attendance.mode']['defaultScope'];
        }
        if (is_string($candidateScope) && preg_match('/^[A-Z0-9_-]{2,64}$/', strtoupper(trim($candidateScope))) === 1) {
            $scope = strtoupper(trim($candidateScope));
        }
    } catch (Throwable $e) {
    }

    if (count($dates) === 0) {
        $dates = $defaultDates;
    }

    return [
        'festivalDates' => $dates,
        'defaultScope'  => $scope,
    ];
}

function pfgAttCorrComputeMetrics(PDO $pdo, string $editionId, string $scope, array $festivalDates): array
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

    $isOverrideActor = pfgAttCorrIsOverrideActor($user);
    $isRgstActor = hasRole($user, ['RGST']);

    if (!$isOverrideActor && !$isRgstActor) {
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'attendance_correction',
            'resourceId'   => '/api/attendance/correct',
            'metadata'     => ['roles' => $user['roles'] ?? []],
        ]);
        jsonResponse(['error' => 'Forbidden: authorized registration desk or admin override permission is required.'], 403);
    }

    requireCsrfToken($user);

    $input = readRequestInput();
    $attendanceRecordId = trim((string)($input['attendanceRecordId'] ?? $input['attendance_record_id'] ?? $input['recordId'] ?? ''));
    $registrationId = trim((string)($input['registrationId'] ?? $input['registration_id'] ?? ''));
    $registrationCode = strtoupper(trim((string)($input['registrationCode'] ?? $input['registration_code'] ?? '')));
    $attendanceDateIst = trim((string)($input['attendanceDateIst'] ?? $input['attendance_date_ist'] ?? ''));
    $scopeInput = strtoupper(trim((string)($input['attendanceScope'] ?? $input['attendance_scope'] ?? '')));
    $newStatus = strtoupper(trim((string)($input['newStatus'] ?? $input['new_status'] ?? '')));
    $reason = trim((string)($input['reason'] ?? ''));

    if (!in_array($newStatus, ['REVOKED', 'PRESENT'], true)) {
        jsonResponse(['error' => 'Invalid newStatus. Must be REVOKED or PRESENT.'], 400);
    }

    $reasonLen = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
    if ($reason === '' || $reasonLen < 5) {
        jsonResponse(['error' => 'A correction reason of at least 5 characters is required.'], 400);
    }
    if ($reasonLen > 1000) {
        $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 1000, 'UTF-8') : substr($reason, 0, 1000);
    }

    $pdo = getDb();
    $cfg = pfgAttCorrLoadConfig($pdo);
    $festivalDates = $cfg['festivalDates'];
    $scope = ($scopeInput !== '' && preg_match('/^[A-Z0-9_-]{2,64}$/', $scopeInput) === 1)
        ? $scopeInput
        : $cfg['defaultScope'];

    // Resolve the target attendance record
    if ($attendanceRecordId !== '') {
        $stmt = $pdo->prepare("
            SELECT ar.*,
                   r.registration_code,
                   r.full_name,
                   r.category_code,
                   r.status AS registration_status,
                   u.full_name AS marked_by_name
            FROM attendance_records ar
            INNER JOIN registrations r ON r.id = ar.registration_id
            LEFT JOIN users u ON u.id = ar.marked_by_user_id
            WHERE ar.id = :id AND r.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $attendanceRecordId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        if (($registrationId === '' && $registrationCode === '') || $attendanceDateIst === '') {
            jsonResponse(['error' => 'Provide attendanceRecordId, or both registrationId/registrationCode and attendanceDateIst.'], 400);
        }
        if ($registrationId !== '') {
            $stmt = $pdo->prepare("
                SELECT ar.*,
                       r.registration_code,
                       r.full_name,
                       r.category_code,
                       r.status AS registration_status,
                       u.full_name AS marked_by_name
                FROM attendance_records ar
                INNER JOIN registrations r ON r.id = ar.registration_id
                LEFT JOIN users u ON u.id = ar.marked_by_user_id
                WHERE ar.registration_id = :reg_id
                  AND ar.attendance_date_ist = :att_date
                  AND ar.attendance_scope = :scope
                  AND r.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([
                ':reg_id'   => $registrationId,
                ':att_date' => $attendanceDateIst,
                ':scope'    => $scope,
            ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT ar.*,
                       r.registration_code,
                       r.full_name,
                       r.category_code,
                       r.status AS registration_status,
                       u.full_name AS marked_by_name
                FROM attendance_records ar
                INNER JOIN registrations r ON r.id = ar.registration_id
                LEFT JOIN users u ON u.id = ar.marked_by_user_id
                WHERE UPPER(r.registration_code) = :reg_code
                  AND ar.attendance_date_ist = :att_date
                  AND ar.attendance_scope = :scope
                  AND r.deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([
                ':reg_code' => $registrationCode,
                ':att_date' => $attendanceDateIst,
                ':scope'    => $scope,
            ]);
        }
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$record) {
        jsonResponse(['error' => 'Attendance record not found.'], 404);
    }

    $actorId = (string)$user['id'];
    $recordId = (string)$record['id'];
    $regId = (string)$record['registration_id'];
    $editionId = (string)$record['edition_id'];
    $recordScope = (string)$record['attendance_scope'];
    $previousStatus = strtoupper((string)$record['status']);

    // Enforce D-03 permission rule for non-override RGST operators
    if (!$isOverrideActor) {
        $markedById = (string)($record['marked_by_user_id'] ?? '');
        if ($markedById !== $actorId) {
            logAudit([
                'actorUserId'  => $actorId,
                'action'       => 'ACCESS_DENIED',
                'resourceType' => 'attendance_correction',
                'resourceId'   => $recordId,
                'metadata'     => ['reason' => 'RGST_NOT_ORIGINAL_MARKER'],
            ]);
            jsonResponse([
                'error' => 'Desk staff can only correct attendance records they personally marked. Please request an Admin override.',
                'code'  => 'OWN_MARK_REQUIRED',
            ], 403);
        }

        $markedAt = (string)($record['marked_at'] ?? '');
        $markedTs = $markedAt !== '' ? strtotime($markedAt) : false;
        $elapsedSeconds = ($markedTs !== false) ? (time() - $markedTs) : 999999;
        if ($markedTs === false || $elapsedSeconds > 900) {
            logAudit([
                'actorUserId'  => $actorId,
                'action'       => 'ACCESS_DENIED',
                'resourceType' => 'attendance_correction',
                'resourceId'   => $recordId,
                'metadata'     => [
                    'reason'         => 'RGST_UNDO_WINDOW_EXPIRED',
                    'elapsedSeconds' => $elapsedSeconds,
                ],
            ]);
            jsonResponse([
                'error' => 'The 15-minute self-undo window has expired for this record. An Admin or Superadmin override is required.',
                'code'  => 'UNDO_WINDOW_EXPIRED',
            ], 403);
        }
    }

    // If re-instating to PRESENT, ensure registration is still eligible
    if ($newStatus === 'PRESENT') {
        $regStatus = strtoupper((string)($record['registration_status'] ?? ''));
        if (!in_array($regStatus, ['CONFIRMED', 'APPROVED'], true)) {
            jsonResponse([
                'error' => sprintf('Cannot reinstate attendance because registration status is %s (must be CONFIRMED or APPROVED).', $regStatus),
                'code'  => 'REGISTRATION_NOT_ELIGIBLE',
            ], 400);
        }
    }

    // No-op if status already matches
    if ($previousStatus === $newStatus) {
        $metrics = pfgAttCorrComputeMetrics($pdo, $editionId, $recordScope, $festivalDates);
        jsonResponse([
            'success'   => true,
            'unchanged' => true,
            'message'   => 'Attendance record is already ' . $newStatus . '.',
            'record'    => [
                'id'                => $recordId,
                'recordId'          => $recordId,
                'registrationId'    => $regId,
                'registrationCode'  => (string)$record['registration_code'],
                'fullName'          => (string)$record['full_name'],
                'categoryCode'      => strtoupper((string)$record['category_code']),
                'attendanceDateIst' => (string)$record['attendance_date_ist'],
                'attendanceScope'   => $recordScope,
                'status'            => $previousStatus,
                'markedByUserId'    => (string)$record['marked_by_user_id'],
                'markedBy'          => (string)($record['marked_by_name'] ?? 'Desk Staff'),
                'markedAt'          => (string)$record['marked_at'],
                'markedAtIst'       => formatIstTimestamp($record['marked_at']),
                'correctedByUserId' => $record['corrected_by_user_id'] !== null ? (string)$record['corrected_by_user_id'] : null,
                'correctedAt'       => $record['corrected_at'] !== null ? (string)$record['corrected_at'] : null,
                'correctionReason'  => $record['correction_reason'] !== null ? (string)$record['correction_reason'] : null,
                'canDeskUndo'       => ($previousStatus === 'PRESENT') && $isOverrideActor,
            ],
            'metrics'   => $metrics,
        ], 200);
    }

    $now = nowSql();
    $correctionLogId = uuid4();

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("
            UPDATE attendance_records
            SET status = :new_status,
                corrected_by_user_id = :actor_id,
                corrected_at = :now,
                correction_reason = :reason,
                updated_at = :updated_at
            WHERE id = :id
        ");
        $upd->execute([
            ':new_status' => $newStatus,
            ':actor_id'   => $actorId,
            ':now'        => $now,
            ':reason'     => $reason,
            ':updated_at' => $now,
            ':id'         => $recordId,
        ]);

        $insLog = $pdo->prepare("
            INSERT INTO attendance_correction_logs
            (id, attendance_record_id, registration_id, actor_user_id, previous_status, new_status, reason, created_at)
            VALUES (:id, :rec_id, :reg_id, :actor_id, :prev_status, :new_status, :reason, :now)
        ");
        $insLog->execute([
            ':id'          => $correctionLogId,
            ':rec_id'      => $recordId,
            ':reg_id'      => $regId,
            ':actor_id'    => $actorId,
            ':prev_status' => $previousStatus,
            ':new_status'  => $newStatus,
            ':reason'      => $reason,
            ':now'         => $now,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    logAudit([
        'actorUserId'  => $actorId,
        'action'       => 'ATTENDANCE_CORRECTED',
        'resourceType' => 'attendance_record',
        'resourceId'   => $recordId,
        'metadata'     => [
            'correctionLogId'   => $correctionLogId,
            'registrationId'    => $regId,
            'registrationCode'  => (string)$record['registration_code'],
            'attendanceDateIst' => (string)$record['attendance_date_ist'],
            'attendanceScope'   => $recordScope,
            'previousStatus'    => $previousStatus,
            'newStatus'         => $newStatus,
            'reason'            => $reason,
            'isAdminOverride'   => $isOverrideActor,
        ],
    ]);

    $metrics = pfgAttCorrComputeMetrics($pdo, $editionId, $recordScope, $festivalDates);

    $markedTs = strtotime((string)$record['marked_at']);
    $withinWindow = ($markedTs !== false && (time() - $markedTs) <= 900);
    $isOwnMark = ((string)$record['marked_by_user_id'] === $actorId);
    $canDeskUndo = ($newStatus === 'PRESENT') && ($isOverrideActor || ($isOwnMark && $withinWindow));

    jsonResponse([
        'success'         => true,
        'unchanged'       => false,
        'correctionLogId' => $correctionLogId,
        'message'         => sprintf(
            'Attendance updated from %s to %s for %s.',
            $previousStatus,
            $newStatus,
            (string)$record['attendance_date_ist']
        ),
        'record'          => [
            'id'                => $recordId,
            'recordId'          => $recordId,
            'registrationId'    => $regId,
            'registrationCode'  => (string)$record['registration_code'],
            'fullName'          => (string)$record['full_name'],
            'categoryCode'      => strtoupper((string)$record['category_code']),
            'attendanceDateIst' => (string)$record['attendance_date_ist'],
            'attendanceScope'   => $recordScope,
            'status'            => $newStatus,
            'markedByUserId'    => (string)$record['marked_by_user_id'],
            'markedBy'          => (string)($record['marked_by_name'] ?? 'Desk Staff'),
            'markedAt'          => (string)$record['marked_at'],
            'markedAtIst'       => formatIstTimestamp($record['marked_at']),
            'correctedByUserId' => $actorId,
            'correctedAt'       => $now,
            'correctedAtIst'    => formatIstTimestamp($now),
            'correctionReason'  => $reason,
            'canDeskUndo'       => $canDeskUndo,
            'undoSecondsLeft'   => (!$isOverrideActor && $isOwnMark && $withinWindow) ? max(0, 900 - (time() - (int)$markedTs)) : 0,
        ],
        'metrics'         => $metrics,
    ], 200);
} catch (Throwable $err) {
    error_log('[POST /api/attendance/correct] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Failed to save attendance correction. Please retry.'], 500);
}
