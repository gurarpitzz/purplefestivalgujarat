<?php
declare(strict_types=1);

/**
 * GET /api/checkins (php-site/api/checkins/index.php)
 * Exact PHP port of src/app/api/checkins/route.ts.
 *
 * - RGST (ATTENDANCE_READ_OWN only): returns ONLY their own recent check-ins,
 *   available checkinStations, and their high-level today's count.
 * - ADMIN / SUPERADMIN (ATTENDANCE_READ_FULL): returns full attendance analytics
 *   (total registrations, confirmed, checked-in, not-yet-arrived, today's arrivals in IST,
 *   breakdown by category, by venue, by day, plus searchable recent check-ins list).
 * NEVER exposes disability or medical data.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/qr-service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    http_response_code(405);
    echo jsonEncode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo jsonEncode(['error' => 'Authentication required.']);
        exit;
    }

    $userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $canReadFull = (function_exists('hasPermission') && hasPermission($user, PERMISSIONS['ATTENDANCE_READ_FULL']))
        || in_array('ADMIN', $userRoles, true)
        || in_array('SUPERADMIN', $userRoles, true);

    $canReadOwn = $canReadFull
        || (function_exists('hasPermission') && hasPermission($user, PERMISSIONS['ATTENDANCE_READ_OWN']))
        || in_array('RGST', $userRoles, true);

    if (!$canReadFull && !$canReadOwn) {
        http_response_code(403);
        echo jsonEncode([
            'error' => 'Access denied. Attendance data is restricted to authorized staff.',
        ]);
        exit;
    }

    $pdo = getDb();
    $todayIst = getIstDateString();

    $stStmt = $pdo->query(
        'SELECT id, name, gate_code, is_active
         FROM checkin_stations
         WHERE is_active = 1
         ORDER BY name ASC'
    );
    $rawStations = $stStmt ? ($stStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $stations = array_map(static fn(array $s): array => [
        'id'       => (string)$s['id'],
        'name'     => (string)$s['name'],
        'gateCode' => (string)$s['gate_code'],
        'isActive' => (bool)$s['is_active'],
    ], $rawStations);

    // RGST-scoped response: ONLY their own check-ins + stations + today's count
    if (!$canReadFull) {
        $ownStmt = $pdo->prepare(
            'SELECT c.id, c.venue_label, c.method, c.checkin_date_ist, c.entitlement_scope, c.checked_in_at,
                    r.full_name AS participant_name, r.registration_code, r.category_code,
                    q.credential_code
             FROM checkins c
             INNER JOIN registrations r ON r.id = c.registration_id
             INNER JOIN qr_credentials q ON q.id = c.credential_id
             WHERE c.operator_user_id = :operator_id
             ORDER BY c.checked_in_at DESC
             LIMIT 100'
        );
        $ownStmt->execute([':operator_id' => $user['id']]);
        $ownRows = $ownStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $todayCount = 0;
        foreach ($ownRows as $row) {
            if (($row['checkin_date_ist'] ?? '') === $todayIst) {
                $todayCount++;
            }
        }

        $recentCheckins = array_map(static fn(array $r): array => [
            'id'               => (string)$r['id'],
            'participantName'  => (string)$r['participant_name'],
            'registrationCode' => (string)$r['registration_code'],
            'credentialCode'   => (string)$r['credential_code'],
            'categoryCode'     => (string)$r['category_code'],
            'venueLabel'       => (string)$r['venue_label'],
            'method'           => (string)$r['method'],
            'checkinDateIst'   => (string)$r['checkin_date_ist'],
            'entitlementScope' => (string)$r['entitlement_scope'],
            'checkedInAt'      => (string)$r['checked_in_at'],
            'checkedInAtIst'   => formatIstTimestamp($r['checked_in_at']),
        ], array_slice($ownRows, 0, 50));

        http_response_code(200);
        echo jsonEncode([
            'scope'                 => 'OWN',
            'todayIst'              => $todayIst,
            'todayCount'            => $todayCount,
            'totalOperatorCheckins' => count($ownRows),
            'stations'              => $stations,
            'recentCheckins'        => $recentCheckins,
        ]);
        exit;
    }

    // ADMIN / SUPERADMIN full attendance analytics
    $q = strtolower(trim((string)($_GET['q'] ?? '')));
    $categoryFilter = strtoupper(trim((string)($_GET['category'] ?? '')));
    $dateFilter = trim((string)($_GET['date'] ?? ''));
    $venueFilter = strtolower(trim((string)($_GET['venue'] ?? '')));
    $limitParam = (int)($_GET['limit'] ?? 100);
    $limit = $limitParam > 0 ? min(max($limitParam, 1), 500) : 100;

    $allRegStmt = $pdo->query(
        'SELECT id, category_code, status
         FROM registrations
         WHERE deleted_at IS NULL'
    );
    $allRegistrations = $allRegStmt ? ($allRegStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $allChkStmt = $pdo->query(
        'SELECT c.id, c.registration_id, c.operator_user_id, c.venue_label, c.method,
                c.checkin_date_ist, c.entitlement_scope, c.checked_in_at,
                r.full_name AS participant_name, r.registration_code, r.category_code,
                q.credential_code,
                u.full_name AS operator_name
         FROM checkins c
         INNER JOIN registrations r ON r.id = c.registration_id
         INNER JOIN qr_credentials q ON q.id = c.credential_id
         LEFT JOIN users u ON u.id = c.operator_user_id
         ORDER BY c.checked_in_at DESC'
    );
    $allCheckins = $allChkStmt ? ($allChkStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $totalRegistrations = count($allRegistrations);
    $confirmedRegistrations = 0;
    foreach ($allRegistrations as $r) {
        if (in_array($r['status'] ?? '', ['CONFIRMED', 'APPROVED'], true)) {
            $confirmedRegistrations++;
        }
    }

    $checkedInRegIds = [];
    $todayArrivals = 0;
    $myTodayCount = 0;
    $venueMap = [];
    $dayMap = [];

    foreach ($allCheckins as $c) {
        $regId = (string)$c['registration_id'];
        $checkedInRegIds[$regId] = true;

        $cDate = (string)($c['checkin_date_ist'] ?? '');
        if ($cDate === $todayIst) {
            $todayArrivals++;
            if ((string)($c['operator_user_id'] ?? '') === (string)$user['id']) {
                $myTodayCount++;
            }
        }

        $vLabel = (string)($c['venue_label'] ?? 'Main Gate');
        $venueMap[$vLabel] = ($venueMap[$vLabel] ?? 0) + 1;

        if ($cDate !== '') {
            $dayMap[$cDate] = ($dayMap[$cDate] ?? 0) + 1;
        }
    }

    $uniqueCheckedIn = count($checkedInRegIds);
    $notYetArrived = max(0, $confirmedRegistrations - $uniqueCheckedIn);

    $categoryMap = [];
    foreach ($allRegistrations as $r) {
        $catCode = (string)($r['category_code'] ?? 'ATTENDEE');
        if (!isset($categoryMap[$catCode])) {
            $categoryMap[$catCode] = [
                'categoryCode' => $catCode,
                'total'        => 0,
                'confirmed'    => 0,
                'checkedIn'    => 0,
            ];
        }
        $categoryMap[$catCode]['total']++;
        if (in_array($r['status'] ?? '', ['CONFIRMED', 'APPROVED'], true)) {
            $categoryMap[$catCode]['confirmed']++;
        }
        if (isset($checkedInRegIds[(string)$r['id']])) {
            $categoryMap[$catCode]['checkedIn']++;
        }
    }

    $breakdownByCategory = array_values($categoryMap);
    usort($breakdownByCategory, static fn(array $a, array $b): int => $b['confirmed'] <=> $a['confirmed']);

    $breakdownByVenue = [];
    foreach ($venueMap as $vLabel => $cnt) {
        $breakdownByVenue[] = ['venueLabel' => $vLabel, 'count' => $cnt];
    }
    usort($breakdownByVenue, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

    $breakdownByDay = [];
    ksort($dayMap);
    foreach ($dayMap as $dIst => $cnt) {
        $breakdownByDay[] = ['dateIst' => $dIst, 'count' => $cnt];
    }

    $filteredCheckins = [];
    foreach ($allCheckins as $c) {
        if ($q !== '') {
            $hay = strtolower(
                ($c['participant_name'] ?? '') . ' ' .
                ($c['registration_code'] ?? '') . ' ' .
                ($c['credential_code'] ?? '') . ' ' .
                ($c['venue_label'] ?? '') . ' ' .
                ($c['operator_name'] ?? '')
            );
            if (!str_contains($hay, $q)) {
                continue;
            }
        }
        if ($categoryFilter !== '' && strtoupper((string)($c['category_code'] ?? '')) !== $categoryFilter) {
            continue;
        }
        if ($dateFilter !== '' && (string)($c['checkin_date_ist'] ?? '') !== $dateFilter) {
            continue;
        }
        if ($venueFilter !== '' && !str_contains(strtolower((string)($c['venue_label'] ?? '')), $venueFilter)) {
            continue;
        }
        $filteredCheckins[] = $c;
        if (count($filteredCheckins) >= $limit) {
            break;
        }
    }

    $recentCheckins = array_map(static fn(array $c): array => [
        'id'               => (string)$c['id'],
        'participantName'  => (string)$c['participant_name'],
        'registrationCode' => (string)$c['registration_code'],
        'credentialCode'   => (string)$c['credential_code'],
        'categoryCode'     => (string)$c['category_code'],
        'venueLabel'       => (string)$c['venue_label'],
        'operatorName'     => (string)($c['operator_name'] ?? 'RGST Staff'),
        'method'           => (string)$c['method'],
        'checkinDateIst'   => (string)$c['checkin_date_ist'],
        'entitlementScope' => (string)$c['entitlement_scope'],
        'checkedInAt'      => (string)$c['checked_in_at'],
        'checkedInAtIst'   => formatIstTimestamp($c['checked_in_at']),
    ], $filteredCheckins);

    http_response_code(200);
    echo jsonEncode([
        'scope'               => 'FULL',
        'todayIst'            => $todayIst,
        'todayCount'          => $myTodayCount,
        'stations'            => $stations,
        'summary'             => [
            'totalRegistrations'     => $totalRegistrations,
            'confirmedRegistrations' => $confirmedRegistrations,
            'checkedIn'              => $uniqueCheckedIn,
            'totalCheckinEvents'     => count($allCheckins),
            'notYetArrived'          => $notYetArrived,
            'todayArrivals'          => $todayArrivals,
        ],
        'breakdownByCategory' => $breakdownByCategory,
        'breakdownByVenue'    => $breakdownByVenue,
        'breakdownByDay'      => $breakdownByDay,
        'recentCheckins'      => $recentCheckins,
    ]);
} catch (Throwable $err) {
    error_log('[GET /api/checkins] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Failed to load attendance data.']);
}
