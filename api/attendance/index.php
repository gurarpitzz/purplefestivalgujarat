<?php
declare(strict_types=1);

/**
 * GET /api/attendance (api/attendance/index.php)
 * Phase 9 Manual Registration Desk Attendance Search, Pagination & Analytics API.
 *
 * Architectural Guarantees (CAD-05, CAD-06, D-01, D-02, D-03, FND-38):
 * - ZERO QR dependency (strictly keyed on attendance_records).
 * - Server-side SQL search & pagination (max 100 per page; never loads full table into RAM).
 * - Strictly desk-safe fields only (NEVER exposes disability disclosures, medical notes,
 *   emergency contacts, or internal review notes).
 * - Accurate SQL-aggregated metrics excluding soft-deleted registrations (deleted_at IS NULL).
 * - D-20 MFA enforced for ADMIN and SUPERADMIN.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Method not allowed. Use GET.'], 405);
}

/**
 * Checks whether the user holds at least one of the specified permission codes.
 */
function pfgAttHasAnyPerm(array $user, array $perms): bool
{
    foreach ($perms as $perm) {
        if (hasPermission($user, $perm)) {
            return true;
        }
    }
    return false;
}

/**
 * Checks whether the actor has Admin/Superadmin override capability (D-03).
 */
function pfgAttIsOverrideActor(array $user): bool
{
    if (hasRole($user, ['SUPERADMIN'])) {
        return true;
    }
    if (!hasRole($user, ['ADMIN'])) {
        return false;
    }
    return pfgAttHasAnyPerm($user, [
        'checkins.override',
        'registrations.manage',
        PERMISSIONS['REGISTRATIONS_WRITE'],
        PERMISSIONS['CHECKINS_CONFIRM'],
    ]);
}

/**
 * Checks whether a column exists on a table across MySQL, MariaDB, and SQLite.
 */
function pfgAttColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        if ($pdo instanceof PfgPdo && $pdo->isSqlite) {
            $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($cols as $col) {
                if (strcasecmp((string)($col['name'] ?? ''), $column) === 0) {
                    return $cache[$key] = true;
                }
            }
            return $cache[$key] = false;
        }
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        return $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

/**
 * Loads configurable festival dates and default attendance scope from system_settings,
 * defaulting to Gujarat 2027 dates ['2027-01-21', '2027-01-22', '2027-01-23'] and 'FESTIVAL_DAY'.
 */
function pfgAttLoadConfig(PDO $pdo): array
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
        // Fall back safely to defaults
    }

    if (count($dates) === 0) {
        $dates = $defaultDates;
    }

    $days = [];
    foreach ($dates as $idx => $dateStr) {
        $shortDate = $dateStr;
        try {
            $dt = new DateTimeImmutable($dateStr, new DateTimeZone('Asia/Kolkata'));
            $shortDate = $dt->format('d M');
        } catch (Throwable $e) {
        }
        $dayNum = $idx + 1;
        $days[] = [
            'date'      => $dateStr,
            'dayNumber' => $dayNum,
            'label'     => 'Day ' . $dayNum,
            'shortDate' => $shortDate,
            'display'   => 'Day ' . $dayNum . ' (' . $shortDate . ')',
        ];
    }

    return [
        'festivalDates' => $dates,
        'days'          => $days,
        'defaultScope'  => $scope,
    ];
}

/**
 * Computes accurate SQL-aggregated attendance metrics for the active edition,
 * strictly excluding soft-deleted registrations (`deleted_at IS NULL`).
 */
function pfgAttComputeMetrics(PDO $pdo, string $editionId, string $scope, array $festivalDates): array
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
        WHERE r.edition_id = :ed
          AND r.deleted_at IS NULL
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
        SELECT ar.attendance_date_ist,
               COUNT(DISTINCT ar.registration_id) AS present_cnt
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

    $canReadAttendance = hasRole($user, ['SUPERADMIN', 'RGST'])
        || (hasRole($user, ['ADMIN']) && pfgAttHasAnyPerm($user, [
            'checkins.read',
            'registrations.read',
            PERMISSIONS['ATTENDANCE_READ_FULL'],
            PERMISSIONS['REGISTRATIONS_READ_ALL'],
        ]));

    if (!$canReadAttendance) {
        logAudit([
            'actorUserId'  => $user['id'] ?? null,
            'action'       => 'ACCESS_DENIED',
            'resourceType' => 'attendance',
            'resourceId'   => '/api/attendance',
            'metadata'     => ['roles' => $user['roles'] ?? []],
        ]);
        jsonResponse(['error' => 'Forbidden: authorized registration desk or admin access is required.'], 403);
    }

    $pdo = getDb();
    $edition = getActiveEdition($pdo);
    if (!$edition) {
        jsonResponse(['error' => 'Active festival edition is not configured.'], 500);
    }
    $editionId = (string)$edition['id'];

    $cfg = pfgAttLoadConfig($pdo);
    $festivalDates = $cfg['festivalDates'];
    $scopeParam = strtoupper(trim((string)($_GET['scope'] ?? '')));
    $scope = ($scopeParam !== '' && preg_match('/^[A-Z0-9_-]{2,64}$/', $scopeParam) === 1)
        ? $scopeParam
        : $cfg['defaultScope'];

    // Filters & Pagination (strictly server-side SQL; fixes FND-38)
    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) > 120) {
        $q = substr($q, 0, 120);
    }
    $category = strtoupper(trim((string)($_GET['category'] ?? 'ALL')));
    $eligibility = strtoupper(trim((string)($_GET['eligibility'] ?? 'ALL')));
    $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
    $day = trim((string)($_GET['day'] ?? ''));
    $dayMode = strtoupper(trim((string)($_GET['dayMode'] ?? 'PRESENT')));

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPageRaw = (int)($_GET['perPage'] ?? $_GET['pageSize'] ?? 25);
    $perPage = min(100, max(1, $perPageRaw > 0 ? $perPageRaw : 25));
    $offset = ($page - 1) * $perPage;

    $where = [
        'r.edition_id = :edition_id',
        'r.deleted_at IS NULL',
    ];
    $params = [
        ':edition_id' => $editionId,
    ];

    if ($q !== '') {
        $qLower = '%' . strtolower($q) . '%';
        $qDigitsOnly = preg_replace('/\D+/', '', $q) ?? '';
        if ($qDigitsOnly !== '') {
            $where[] = "(
                LOWER(r.registration_code) LIKE :q_code
                OR LOWER(r.full_name) LIKE :q_name
                OR r.phone LIKE :q_phone
                OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(r.phone, ''), ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE :q_digits
            )";
            $params[':q_code'] = $qLower;
            $params[':q_name'] = $qLower;
            $params[':q_phone'] = '%' . $q . '%';
            $params[':q_digits'] = '%' . $qDigitsOnly . '%';
        } else {
            $where[] = "(
                LOWER(r.registration_code) LIKE :q_code
                OR LOWER(r.full_name) LIKE :q_name
                OR r.phone LIKE :q_phone
            )";
            $params[':q_code'] = $qLower;
            $params[':q_name'] = $qLower;
            $params[':q_phone'] = '%' . $q . '%';
        }
    }

    if ($category !== '' && $category !== 'ALL') {
        $where[] = 'UPPER(r.category_code) = :category';
        $params[':category'] = $category;
    }

    if ($eligibility === 'ELIGIBLE') {
        $where[] = "UPPER(r.status) IN ('CONFIRMED', 'APPROVED')";
    } elseif ($eligibility === 'INELIGIBLE') {
        $where[] = "UPPER(r.status) NOT IN ('CONFIRMED', 'APPROVED')";
    }

    if ($statusFilter !== '' && $statusFilter !== 'ALL') {
        if ($statusFilter === 'ELIGIBLE') {
            $where[] = "UPPER(r.status) IN ('CONFIRMED', 'APPROVED')";
        } else {
            $where[] = 'UPPER(r.status) = :status_filter';
            $params[':status_filter'] = $statusFilter;
        }
    }

    if ($day !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
        if ($dayMode === 'NOT_PRESENT') {
            $where[] = "NOT EXISTS (
                SELECT 1 FROM attendance_records ar_f
                WHERE ar_f.registration_id = r.id
                  AND ar_f.edition_id = r.edition_id
                  AND ar_f.attendance_date_ist = :day_filter
                  AND ar_f.attendance_scope = :day_scope
                  AND UPPER(ar_f.status) = 'PRESENT'
            )";
        } else {
            $where[] = "EXISTS (
                SELECT 1 FROM attendance_records ar_f
                WHERE ar_f.registration_id = r.id
                  AND ar_f.edition_id = r.edition_id
                  AND ar_f.attendance_date_ist = :day_filter
                  AND ar_f.attendance_scope = :day_scope
                  AND UPPER(ar_f.status) = 'PRESENT'
            )";
        }
        $params[':day_filter'] = $day;
        $params[':day_scope'] = $scope;
    }

    $whereSql = implode(' AND ', $where);

    // 1. Accurate filtered total count in SQL
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    // 2. Paginated desk-safe participant rows (strictly excludes disability, medical, emergency, review notes)
    $hasCityDistrict = pfgAttColumnExists($pdo, 'registrations', 'city_district');
    $hasState = pfgAttColumnExists($pdo, 'registrations', 'state');
    $citySelect = $hasCityDistrict ? 'r.city_district' : 'NULL AS city_district';
    $stateSelect = $hasState ? 'r.state' : 'NULL AS state';

    $listSql = "
        SELECT r.id,
               r.registration_code,
               r.full_name,
               {$citySelect},
               {$stateSelect},
               r.phone,
               r.category_code,
               r.status,
               r.attending_days
        FROM registrations r
        WHERE {$whereSql}
        ORDER BY
            CASE WHEN UPPER(r.status) IN ('CONFIRMED', 'APPROVED') THEN 0 ELSE 1 END ASC,
            r.full_name ASC,
            r.registration_code ASC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($params);
    $regRows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3. Batch-load attendance records ONLY for the paginated registration IDs (<= 100 rows)
    $attByRegId = [];
    $regIds = array_map(static fn(array $r): string => (string)$r['id'], $regRows);
    if (count($regIds) > 0) {
        $placeholders = [];
        $attParams = [
            ':att_ed'    => $editionId,
            ':att_scope' => $scope,
        ];
        foreach ($regIds as $i => $rid) {
            $ph = ':rid_' . $i;
            $placeholders[] = $ph;
            $attParams[$ph] = $rid;
        }
        $inSql = implode(', ', $placeholders);
        $attStmt = $pdo->prepare("
            SELECT ar.id,
                   ar.registration_id,
                   ar.attendance_date_ist,
                   ar.attendance_scope,
                   ar.status,
                   ar.marked_by_user_id,
                   ar.marked_at,
                   ar.venue_label,
                   ar.corrected_by_user_id,
                   ar.corrected_at,
                   ar.correction_reason,
                   u.full_name AS marked_by_name
            FROM attendance_records ar
            LEFT JOIN users u ON u.id = ar.marked_by_user_id
            WHERE ar.edition_id = :att_ed
              AND ar.attendance_scope = :att_scope
              AND ar.registration_id IN ({$inSql})
        ");
        $attStmt->execute($attParams);
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ar) {
            $rId = (string)$ar['registration_id'];
            $dKey = (string)$ar['attendance_date_ist'];
            $attByRegId[$rId][$dKey] = $ar;
        }
    }

    $isOverrideActor = pfgAttIsOverrideActor($user);
    $actorId = (string)$user['id'];
    $nowTs = time();
    $undoWindowSeconds = 900; // 15 minutes (Decision D-03)

    $rows = [];
    foreach ($regRows as $r) {
        $rId = (string)$r['id'];
        $regStatus = strtoupper((string)($r['status'] ?? 'SUBMITTED'));
        $isEligible = in_array($regStatus, ['CONFIRMED', 'APPROVED'], true);

        $cityDistrict = trim((string)($r['city_district'] ?? ''));
        $stateVal = trim((string)($r['state'] ?? ''));
        if ($cityDistrict === '' && $stateVal !== '') {
            $cityDistrict = $stateVal;
        }

        // Only expose last 4 digits of phone for duplicate-name disambiguation (never full phone)
        $phoneDigits = preg_replace('/\D+/', '', (string)($r['phone'] ?? '')) ?? '';
        $phoneLast4 = strlen($phoneDigits) >= 4 ? substr($phoneDigits, -4) : null;

        $attendingDays = jsonDecode($r['attending_days'] ?? null, []);
        if (!is_array($attendingDays)) {
            $attendingDays = [];
        }

        $attendanceMap = [];
        foreach ($festivalDates as $dateKey) {
            $rec = $attByRegId[$rId][$dateKey] ?? null;
            if ($rec) {
                $recStatus = strtoupper((string)($rec['status'] ?? 'PRESENT'));
                $markedById = (string)($rec['marked_by_user_id'] ?? '');
                $markedAt = (string)($rec['marked_at'] ?? '');
                $markedTs = $markedAt !== '' ? strtotime($markedAt) : false;
                $elapsed = ($markedTs !== false) ? max(0, $nowTs - $markedTs) : ($undoWindowSeconds + 1);
                $withinWindow = ($markedTs !== false && $elapsed <= $undoWindowSeconds);
                $isOwnMark = ($markedById !== '' && $markedById === $actorId);
                $canDeskUndo = ($recStatus === 'PRESENT') && ($isOverrideActor || ($isOwnMark && $withinWindow));
                $secondsLeft = (!$isOverrideActor && $isOwnMark && $withinWindow) ? max(0, $undoWindowSeconds - $elapsed) : 0;

                $attendanceMap[$dateKey] = [
                    'recordId'         => (string)$rec['id'],
                    'status'           => $recStatus,
                    'markedAt'         => $markedAt,
                    'markedAtIst'      => $markedAt !== '' ? formatIstTimestamp($markedAt) : null,
                    'markedBy'         => (string)($rec['marked_by_name'] ?? 'Desk Staff'),
                    'markedByUserId'   => $markedById,
                    'venueLabel'       => $rec['venue_label'] !== null ? (string)$rec['venue_label'] : null,
                    'correctedAt'      => $rec['corrected_at'] !== null ? (string)$rec['corrected_at'] : null,
                    'correctionReason' => $rec['correction_reason'] !== null ? (string)$rec['correction_reason'] : null,
                    'canDeskUndo'      => $canDeskUndo,
                    'undoSecondsLeft'  => $secondsLeft,
                ];
            } else {
                $attendanceMap[$dateKey] = [
                    'recordId'        => null,
                    'status'          => 'NOT_MARKED',
                    'markedAt'        => null,
                    'markedAtIst'     => null,
                    'markedBy'        => null,
                    'markedByUserId'  => null,
                    'venueLabel'      => null,
                    'canDeskUndo'     => false,
                    'undoSecondsLeft' => 0,
                ];
            }
        }

        $rows[] = [
            'registrationId'   => $rId,
            'registrationCode' => (string)$r['registration_code'],
            'fullName'         => (string)$r['full_name'],
            'cityDistrict'     => $cityDistrict !== '' ? $cityDistrict : null,
            'phoneLast4'       => $phoneLast4,
            'categoryCode'     => strtoupper((string)$r['category_code']),
            'status'           => $regStatus,
            'isEligible'       => $isEligible,
            'attendingDays'    => array_values($attendingDays),
            'attendance'       => $attendanceMap,
        ];
    }

    // 4. SQL-Aggregated Metrics
    $metrics = pfgAttComputeMetrics($pdo, $editionId, $scope, $festivalDates);

    jsonResponse([
        'editionId'       => $editionId,
        'todayIst'        => getIstDateString(),
        'attendanceScope' => $scope,
        'festivalDates'   => $festivalDates,
        'days'            => $cfg['days'],
        'actor'           => [
            'id'                 => $actorId,
            'fullName'           => (string)($user['fullName'] ?? 'Desk Staff'),
            'canOverrideAnytime' => $isOverrideActor,
            'undoWindowMinutes'  => 15,
        ],
        'pagination'      => [
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => $totalPages,
        ],
        'metrics'         => $metrics,
        'rows'            => $rows,
    ], 200);
} catch (Throwable $err) {
    error_log('[GET /api/attendance] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Failed to load attendance data.'], 500);
}
