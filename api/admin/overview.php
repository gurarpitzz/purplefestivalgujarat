<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Overview & Real-Time Analytics API
 * Endpoint: GET /api/admin/overview (or /api/admin/overview.php)
 *
 * Attendance metrics come EXCLUSIVELY from the decoupled manual-desk `attendance_records` table (D-01: zero QR
 * dependency): approval status, assignment status and physical attendance are three separate dimensions.
 * Every aggregate excludes soft-deleted registrations (FND-39).
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin-common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

/**
 * Buckets a registration status into the review-pipeline columns shared by Volunteer & SLI pipelines.
 */
function pfgOverviewPipelineBucket(string $status): ?string
{
    $s = pfgAdminNormalizeStatus($status);
    if (in_array($s, ['APPROVED', 'CONFIRMED'], true)) {
        return 'approved';
    }
    if ($s === 'SUBMITTED') {
        return 'submitted';
    }
    if (in_array($s, ['UNDER_REVIEW', 'ADDITIONAL_INFO_REQUIRED', 'RESUBMITTED'], true)) {
        return 'underReview';
    }
    if ($s === 'WAITLISTED') {
        return 'waitlisted';
    }
    if ($s === 'REJECTED') {
        return 'rejected';
    }
    return null;
}

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $canReadAccomm = hasPermission($user, PERMISSIONS['ACCOMMODATIONS_READ']);
    $canReadPayments = hasPermission($user, PERMISSIONS['PAYMENTS_READ']);
    $canReadAttendance = hasPermission($user, PERMISSIONS['ATTENDANCE_READ_FULL']);
    $canReadRegistrations = hasPermission($user, PERMISSIONS['REGISTRATIONS_READ_ALL']);
    $pdo = getDb();
    $todayIst = getIstDateString();

    // 1. Fetch all active (non-deleted) registrations
    $stmtRegs = $pdo->query("
        SELECT
            id,
            user_id,
            email,
            category_code,
            status,
            payment_status,
            discount_inr,
            net_amount_inr,
            created_at
        FROM registrations
        WHERE deleted_at IS NULL
    ");
    $allRegs = $stmtRegs ? $stmtRegs->fetchAll(PDO::FETCH_ASSOC) : [];

    $uniquePeopleSet = [];
    $totalApplications = 0;
    $confirmedRegistrations = 0;
    $approvedRegistrations = 0;
    $attendees = 0;
    $delegates = 0;
    $volunteerApplications = 0;
    $approvedVolunteers = 0;
    $sliApplications = 0;
    $approvedSli = 0;
    $alliesSupporters = 0;
    $pendingApprovals = 0;

    $categoryMap = [];
    $dailyTrendMap = [];
    $regCategoryById = [];
    $eligibleRegIds = [];

    $emptyPipeline = [
        'total'       => 0,
        'submitted'   => 0,
        'underReview' => 0,
        'approved'    => 0,
        'waitlisted'  => 0,
        'rejected'    => 0,
    ];
    $volunteerPipeline = $emptyPipeline;
    $sliPipeline = $emptyPipeline;

    foreach ($allRegs as $r) {
        $regId = (string)($r['id'] ?? '');
        $userId = (string)($r['user_id'] ?? '');
        $emailKey = strtolower(trim((string)($r['email'] ?? '')));
        $catCode = strtoupper(trim((string)($r['category_code'] ?? 'ATTENDEE')));
        $status = pfgAdminNormalizeStatus((string)($r['status'] ?? 'SUBMITTED'));
        $createdAt = (string)($r['created_at'] ?? '');

        $totalApplications++;
        $personKey = $userId !== '' ? $userId : $emailKey;
        if ($personKey !== '') {
            $uniquePeopleSet[$personKey] = true;
        }
        if ($regId !== '') {
            $regCategoryById[$regId] = $catCode;
        }

        $isConfirmed = in_array($status, ['CONFIRMED', 'APPROVED'], true);
        $isPending = in_array($status, ['SUBMITTED', 'UNDER_REVIEW', 'RESUBMITTED', 'ADDITIONAL_INFO_REQUIRED'], true);

        if ($isConfirmed) {
            $confirmedRegistrations++;
            if ($regId !== '') {
                $eligibleRegIds[$regId] = true;
            }
        }
        if ($status === 'APPROVED') {
            $approvedRegistrations++;
        }
        if ($isPending) {
            $pendingApprovals++;
        }

        if ($catCode === 'ATTENDEE') {
            $attendees++;
        }
        if ($catCode === 'DELEGATE') {
            $delegates++;
        }
        if ($catCode === 'ALLY') {
            $alliesSupporters++;
        }

        if ($catCode === 'VOLUNTEER' || $catCode === 'SLI') {
            $bucket = pfgOverviewPipelineBucket($status);
            if ($catCode === 'VOLUNTEER') {
                $volunteerApplications++;
                $volunteerPipeline['total']++;
                if ($bucket !== null) {
                    $volunteerPipeline[$bucket]++;
                }
                if ($bucket === 'approved') {
                    $approvedVolunteers++;
                }
            } else {
                $sliApplications++;
                $sliPipeline['total']++;
                if ($bucket !== null) {
                    $sliPipeline[$bucket]++;
                }
                if ($bucket === 'approved') {
                    $approvedSli++;
                }
            }
        }

        if (!isset($categoryMap[$catCode])) {
            $categoryMap[$catCode] = [
                'categoryCode' => $catCode,
                'total'        => 0,
                'confirmed'    => 0,
                'pending'      => 0,
                'checkedIn'    => 0,
            ];
        }
        $categoryMap[$catCode]['total']++;
        if ($isConfirmed) {
            $categoryMap[$catCode]['confirmed']++;
        }
        if ($isPending) {
            $categoryMap[$catCode]['pending']++;
        }

        $dateKey = $createdAt !== '' ? getIstDateString($createdAt) : $todayIst;
        $dailyTrendMap[$dateKey] = ($dailyTrendMap[$dateKey] ?? 0) + 1;
    }

    // 2. Accommodation requests summary (canonical `accommodation_requests` ONLY, excluding soft-deleted registrations — FND-39)
    $accommByReg = [];
    $accommRowsAll = [];

    $stmtAc = $pdo->query("
        SELECT a.id, a.registration_id, a.status
        FROM accommodation_requests a
        INNER JOIN registrations r ON a.registration_id = r.id
        WHERE r.deleted_at IS NULL
    ");
    $rows = $stmtAc ? $stmtAc->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $row) {
        $rid = (string)($row['registration_id'] ?? '');
        $id = (string)($row['id'] ?? '');
        $key = $rid !== '' ? $rid : $id;
        $st = strtoupper(trim((string)($row['status'] ?? 'REQUESTED')));
        if ($key !== '' && !isset($accommByReg[$key])) {
            $accommByReg[$key] = $st;
        }
        $accommRowsAll[] = ['id' => $id, 'registrationId' => $rid, 'status' => $st];
    }

    $requestedCount = 0;
    $inProgressCount = 0;
    $arrangedCount = 0;
    $declinedCount = 0;

    foreach ($accommRowsAll as $ar) {
        $st = $ar['status'];
        if ($st === 'REQUESTED' || $st === 'PENDING') {
            $requestedCount++;
        } elseif ($st === 'IN_PROGRESS' || $st === 'ASSIGNED') {
            $inProgressCount++;
        } elseif ($st === 'ARRANGED' || $st === 'COMPLETED' || $st === 'APPROVED') {
            $arrangedCount++;
        } elseif ($st === 'DECLINED' || $st === 'REJECTED') {
            $declinedCount++;
        }
    }

    $accommodationSummary = [
        'totalRequests'      => count($accommRowsAll),
        'uniqueParticipants' => count($accommByReg),
        'requested'          => $requestedCount,
        'inProgress'         => $inProgressCount,
        'assigned'           => $inProgressCount,
        'arranged'           => $arrangedCount,
        'completed'          => $arrangedCount,
        'declined'           => $declinedCount,
    ];

    // 3. Manual-desk attendance from `attendance_records` (PRESENT only; REVOKED corrections never count).
    $uniquePresentRegIds = [];
    $attendanceMarks = 0;
    $todayMarks = 0;
    $dayMarks = [];
    $dayPeople = [];
    $venueMarks = [];
    $presentByCategorySet = [];
    $recentCheckins = [];
    $attendanceAvailable = pfgAdminTableExists($pdo, 'attendance_records');

    if ($attendanceAvailable && $canReadAttendance) {
        $stmtAtt = $pdo->query("
            SELECT a.registration_id, a.attendance_date_ist, a.venue_label
            FROM attendance_records a
            INNER JOIN registrations r ON a.registration_id = r.id
            WHERE r.deleted_at IS NULL AND a.status = 'PRESENT'
        ");
        foreach (($stmtAtt ? $stmtAtt->fetchAll(PDO::FETCH_ASSOC) : []) as $a) {
            $rid = (string)$a['registration_id'];
            $dateIst = (string)($a['attendance_date_ist'] ?? '');
            $venueLabel = trim((string)($a['venue_label'] ?? '')) ?: 'Registration Desk';

            $attendanceMarks++;
            $uniquePresentRegIds[$rid] = true;
            if ($dateIst === $todayIst) {
                $todayMarks++;
            }
            if ($dateIst !== '') {
                $dayMarks[$dateIst] = ($dayMarks[$dateIst] ?? 0) + 1;
                $dayPeople[$dateIst][$rid] = true;
            }
            $venueMarks[$venueLabel] = ($venueMarks[$venueLabel] ?? 0) + 1;
            if (isset($regCategoryById[$rid])) {
                $presentByCategorySet[$regCategoryById[$rid]][$rid] = true;
            }
        }

        foreach ($presentByCategorySet as $cat => $regSet) {
            if (isset($categoryMap[$cat])) {
                $categoryMap[$cat]['checkedIn'] = count($regSet);
            }
        }

        $stmtRecent = $pdo->query("
            SELECT
                a.id,
                a.registration_id,
                a.attendance_date_ist,
                a.attendance_scope,
                a.venue_label,
                a.marked_at,
                r.registration_code,
                r.full_name,
                r.category_code
            FROM attendance_records a
            INNER JOIN registrations r ON a.registration_id = r.id
            WHERE r.deleted_at IS NULL AND a.status = 'PRESENT'
            ORDER BY a.marked_at DESC
            LIMIT 20
        ");
        foreach (($stmtRecent ? $stmtRecent->fetchAll(PDO::FETCH_ASSOC) : []) as $rc) {
            $recentCheckins[] = [
                'id'               => (string)$rc['id'],
                'registrationId'   => (string)$rc['registration_id'],
                'registrationCode' => (string)$rc['registration_code'],
                'participantName'  => (string)$rc['full_name'],
                'categoryCode'     => (string)$rc['category_code'],
                'venueLabel'       => trim((string)($rc['venue_label'] ?? '')) ?: 'Registration Desk',
                'checkinDateIst'   => (string)($rc['attendance_date_ist'] ?? $todayIst),
                'method'           => 'MANUAL_DESK',
                'checkedInAt'      => (string)($rc['marked_at'] ?? ''),
            ];
        }
    }

    // 4. Payment reconciliation (canonical `payment_orders` ONLY, excluding soft-deleted registrations)
    $paymentReconciliation = [
        'capturedOrders'     => 0,
        'capturedRevenueInr' => 0,
        'pendingOrders'      => 0,
        'pendingAmountInr'   => 0,
        'refundedOrders'     => 0,
        'refundedAmountInr'  => 0,
        'totalDiscountInr'   => 0,
        'byStatus'           => [],
    ];

    if ($canReadPayments) {
        $statusAgg = [];
        $stmtPay = $pdo->query("
            SELECT p.id, p.status, p.amount_inr, p.discount_inr, p.net_amount_inr
            FROM payment_orders p
            INNER JOIN registrations r ON p.registration_id = r.id
            WHERE r.deleted_at IS NULL
        ");
        $payRows = $stmtPay ? $stmtPay->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($payRows as $pr) {
            $st = strtoupper(trim((string)($pr['status'] ?? 'PENDING')));
            $amt = (int)($pr['amount_inr'] ?? 0);
            $disc = (int)($pr['discount_inr'] ?? 0);
            $net = (int)($pr['net_amount_inr'] ?? 0);

            if (!isset($statusAgg[$st])) {
                $statusAgg[$st] = [
                    'status'      => $st,
                    'count'       => 0,
                    'grossInr'    => 0,
                    'discountInr' => 0,
                    'netInr'      => 0,
                ];
            }
            $statusAgg[$st]['count']++;
            $statusAgg[$st]['grossInr'] += $amt;
            $statusAgg[$st]['discountInr'] += $disc;
            $statusAgg[$st]['netInr'] += $net;

            $paymentReconciliation['totalDiscountInr'] += $disc;
            if ($st === 'CAPTURED' || $st === 'PAID' || $st === 'COMPLETED') {
                $paymentReconciliation['capturedOrders']++;
                $paymentReconciliation['capturedRevenueInr'] += $net;
            } elseif ($st === 'PENDING' || $st === 'AUTHORIZED') {
                $paymentReconciliation['pendingOrders']++;
                $paymentReconciliation['pendingAmountInr'] += $net;
            } elseif ($st === 'REFUNDED' || $st === 'PARTIALLY_REFUNDED') {
                $paymentReconciliation['refundedOrders']++;
                $paymentReconciliation['refundedAmountInr'] += $net;
            }
        }
        $paymentReconciliation['byStatus'] = array_values($statusAgg);
    }

    // 5. Operations: approved Volunteers / SLIs without any active duty assignment, and failed notifications
    $unassignedVolunteers = 0;
    $unassignedSli = 0;
    $hasDutyTable = pfgAdminTableExists($pdo, 'duty_assignments');
    $stmtUn = $pdo->query($hasDutyTable
        ? "
            SELECT r.category_code, COUNT(*) AS c
            FROM registrations r
            WHERE r.deleted_at IS NULL
              AND r.category_code IN ('VOLUNTEER', 'SLI')
              AND r.status IN ('APPROVED', 'CONFIRMED')
              AND NOT EXISTS (SELECT 1 FROM duty_assignments d WHERE d.registration_id = r.id AND d.status = 'ASSIGNED')
            GROUP BY r.category_code
        "
        : "
            SELECT r.category_code, COUNT(*) AS c
            FROM registrations r
            WHERE r.deleted_at IS NULL
              AND r.category_code IN ('VOLUNTEER', 'SLI')
              AND r.status IN ('APPROVED', 'CONFIRMED')
            GROUP BY r.category_code
        ");
    foreach (($stmtUn ? $stmtUn->fetchAll(PDO::FETCH_ASSOC) : []) as $u) {
        if ($u['category_code'] === 'VOLUNTEER') {
            $unassignedVolunteers = (int)$u['c'];
        } else {
            $unassignedSli = (int)$u['c'];
        }
    }

    $failedNotifications = null;
    if ($canReadRegistrations && pfgAdminTableExists($pdo, 'notification_queue')) {
        $failedNotifications = (int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status IN ('FAILED', 'BOUNCED')")->fetchColumn();
    }

    ksort($dailyTrendMap);
    $dailyRegistrationTrends = [];
    foreach ($dailyTrendMap as $date => $count) {
        $dailyRegistrationTrends[] = ['date' => (string)$date, 'count' => (int)$count];
    }

    $categoryDistribution = array_values($categoryMap);
    usort($categoryDistribution, static function (array $a, array $b): int {
        return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
    });

    ksort($dayMarks);
    $attendanceByDay = [];
    $attendanceByDayDetailed = [];
    foreach ($dayMarks as $date => $count) {
        $attendanceByDay[] = ['date' => (string)$date, 'count' => (int)$count];
        $attendanceByDayDetailed[] = [
            'date'         => (string)$date,
            'uniquePeople' => count($dayPeople[$date] ?? []),
            'marks'        => (int)$count,
        ];
    }

    arsort($venueMarks);
    $attendanceByVenue = [];
    foreach ($venueMarks as $venueLabel => $count) {
        $attendanceByVenue[] = ['venueLabel' => (string)$venueLabel, 'count' => (int)$count];
    }

    $attendanceByCategory = [];
    foreach ($presentByCategorySet as $cat => $regSet) {
        $attendanceByCategory[] = ['categoryCode' => (string)$cat, 'uniquePeople' => count($regSet)];
    }
    usort($attendanceByCategory, static function (array $a, array $b): int {
        return $b['uniquePeople'] <=> $a['uniquePeople'];
    });

    $uniquePresent = count($uniquePresentRegIds);
    $eligiblePresent = count(array_intersect_key($uniquePresentRegIds, $eligibleRegIds));
    $notYetArrived = max(0, count($eligibleRegIds) - $eligiblePresent);
    $volunteerPendingCount = $volunteerPipeline['submitted'] + $volunteerPipeline['underReview'];
    $sliPendingCount = $sliPipeline['submitted'] + $sliPipeline['underReview'];

    jsonResponse([
        'generatedAt'                 => nowIso(),
        'todayIst'                    => $todayIst,
        'metrics'                     => [
            'uniquePeople'                         => count($uniquePeopleSet),
            'totalApplications'                    => $totalApplications,
            'totalRegistrations'                   => $totalApplications,
            'confirmedRegistrations'               => $confirmedRegistrations,
            'approvedRegistrations'                => $approvedRegistrations,
            'pendingApprovals'                     => $pendingApprovals,
            'attendees'                            => $attendees,
            'delegates'                            => $delegates,
            'volunteerApplications'                => $volunteerApplications,
            'approvedVolunteers'                   => $approvedVolunteers,
            'pendingVolunteers'                    => $volunteerPendingCount,
            'sliApplications'                      => $sliApplications,
            'approvedSli'                          => $approvedSli,
            'pendingSli'                           => $sliPendingCount,
            'alliesSupporters'                     => $alliesSupporters,
            'unassignedApprovedVolunteers'         => $unassignedVolunteers,
            'unassignedApprovedSli'                => $unassignedSli,
            'failedNotifications'                  => $failedNotifications,
            'participantsRequestingAccommodations' => $canReadAccomm ? count($accommByReg) : null,
            'totalCheckins'                        => $canReadAttendance ? $attendanceMarks : null,
            'uniqueCheckedInParticipants'          => $canReadAttendance ? $uniquePresent : null,
            'notYetArrived'                        => $canReadAttendance ? $notYetArrived : null,
            'todayCheckins'                        => $canReadAttendance ? $todayMarks : null,
        ],
        'attendance'                  => $canReadAttendance ? [
            'uniquePeoplePresent' => $uniquePresent,
            'totalMarks'          => $attendanceMarks,
            'todayMarks'          => $todayMarks,
            'byDay'               => $attendanceByDayDetailed,
            'byCategory'          => $attendanceByCategory,
            'source'              => 'attendance_records',
        ] : null,
        'accommodationSummary'        => $canReadAccomm ? $accommodationSummary : null,
        'paymentReconciliation'       => $canReadPayments ? $paymentReconciliation : null,
        'dailyRegistrationTrends'     => $dailyRegistrationTrends,
        'categoryDistribution'        => $categoryDistribution,
        'attendanceByDay'             => $canReadAttendance ? $attendanceByDay : [],
        'attendanceByVenue'           => $canReadAttendance ? $attendanceByVenue : [],
        'volunteerPipeline'           => $volunteerPipeline,
        'sliPipeline'                 => $sliPipeline,
        'recentCheckins'              => $canReadAttendance ? $recentCheckins : [],
    ]);
} catch (Throwable $e) {
    error_log('GET /api/admin/overview.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to load overview metrics.'], 500);
}
