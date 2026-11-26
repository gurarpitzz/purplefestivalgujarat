<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Overview & Real-Time Analytics API
 * Endpoint: GET /api/admin/overview (or /api/admin/overview.php)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $canReadAccomm = hasPermission($user, PERMISSIONS['ACCOMMODATIONS_READ']);
    $canReadPayments = hasPermission($user, PERMISSIONS['PAYMENTS_READ']);
    $canReadAttendance = hasPermission($user, PERMISSIONS['ATTENDANCE_READ_FULL']);
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

    $volunteerPipeline = [
        'total'       => 0,
        'submitted'   => 0,
        'underReview' => 0,
        'approved'    => 0,
        'waitlisted'  => 0,
        'rejected'    => 0,
    ];
    $sliPipeline = [
        'total'       => 0,
        'submitted'   => 0,
        'underReview' => 0,
        'approved'    => 0,
        'waitlisted'  => 0,
        'rejected'    => 0,
    ];

    foreach ($allRegs as $r) {
        $regId = (string)($r['id'] ?? '');
        $userId = (string)($r['user_id'] ?? '');
        $emailKey = strtolower(trim((string)($r['email'] ?? '')));
        $catCode = strtoupper(trim((string)($r['category_code'] ?? 'ATTENDEE')));
        $status = strtoupper(trim((string)($r['status'] ?? 'SUBMITTED')));
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
        $isPending = in_array($status, ['SUBMITTED', 'UNDER_REVIEW', 'ADDITIONAL_INFO_REQUIRED', 'PENDING'], true);

        if ($isConfirmed) {
            $confirmedRegistrations++;
        }
        if ($status === 'APPROVED') {
            $approvedRegistrations++;
        }
        if ($isPending) {
            $pendingApprovals++;
        }

        if ($catCode === 'DELEGATE') {
            $delegates++;
        }
        if ($catCode === 'ALLY') {
            $alliesSupporters++;
        }

        if ($catCode === 'VOLUNTEER') {
            $volunteerApplications++;
            $volunteerPipeline['total']++;
            if ($isConfirmed) {
                $approvedVolunteers++;
                $volunteerPipeline['approved']++;
            } elseif ($status === 'SUBMITTED' || $status === 'PENDING') {
                $volunteerPipeline['submitted']++;
            } elseif ($status === 'UNDER_REVIEW' || $status === 'ADDITIONAL_INFO_REQUIRED') {
                $volunteerPipeline['underReview']++;
            } elseif ($status === 'WAITLISTED') {
                $volunteerPipeline['waitlisted']++;
            } elseif ($status === 'REJECTED') {
                $volunteerPipeline['rejected']++;
            }
        }

        if ($catCode === 'SLI') {
            $sliApplications++;
            $sliPipeline['total']++;
            if ($isConfirmed) {
                $approvedSli++;
                $sliPipeline['approved']++;
            } elseif ($status === 'SUBMITTED' || $status === 'PENDING') {
                $sliPipeline['submitted']++;
            } elseif ($status === 'UNDER_REVIEW' || $status === 'ADDITIONAL_INFO_REQUIRED') {
                $sliPipeline['underReview']++;
            } elseif ($status === 'WAITLISTED') {
                $sliPipeline['waitlisted']++;
            } elseif ($status === 'REJECTED') {
                $sliPipeline['rejected']++;
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

    // 2. Accommodation requests summary (merging accommodation_requests and registration_accommodations)
    $accommByReg = [];
    $accommRowsAll = [];

    foreach (['accommodation_requests', 'registration_accommodations'] as $tableName) {
        try {
            $stmtAc = $pdo->query("SELECT id, registration_id, status FROM {$tableName}");
            $rows = $stmtAc ? $stmtAc->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $rid = (string)($row['registration_id'] ?? '');
                $id = (string)($row['id'] ?? '');
                $key = $rid !== '' ? $rid : $id;
                if ($key !== '' && !isset($accommByReg[$key])) {
                    $accommByReg[$key] = strtoupper(trim((string)($row['status'] ?? 'REQUESTED')));
                    $accommRowsAll[] = [
                        'id'             => $id,
                        'registrationId' => $rid,
                        'status'         => $accommByReg[$key],
                    ];
                }
            }
        } catch (Throwable $e) {
            // Table may not exist yet in edge environments
        }
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

    // 3. Check-ins & Attendance breakdown
    $stmtCheckins = $pdo->query("
        SELECT
            id,
            registration_id,
            venue_label,
            checkin_date_ist,
            method,
            checked_in_at
        FROM checkins
        ORDER BY checked_in_at DESC
    ");
    $allCheckins = $stmtCheckins ? $stmtCheckins->fetchAll(PDO::FETCH_ASSOC) : [];

    $uniqueCheckedInRegIds = [];
    $checkedInByCategorySet = [];
    $attendanceByDayMap = [];
    $attendanceByVenueMap = [];
    $todayCheckins = 0;

    foreach ($allCheckins as $c) {
        $rid = (string)($c['registration_id'] ?? '');
        $dateIst = (string)($c['checkin_date_ist'] ?? '');
        if ($dateIst === '' && !empty($c['checked_in_at'])) {
            $dateIst = getIstDateString((string)$c['checked_in_at']);
        }
        $venueLabel = trim((string)($c['venue_label'] ?? 'Main Gate')) ?: 'Main Gate';

        if ($rid !== '') {
            $uniqueCheckedInRegIds[$rid] = true;
        }
        if ($dateIst === $todayIst) {
            $todayCheckins++;
        }
        if ($dateIst !== '') {
            $attendanceByDayMap[$dateIst] = ($attendanceByDayMap[$dateIst] ?? 0) + 1;
        }
        $attendanceByVenueMap[$venueLabel] = ($attendanceByVenueMap[$venueLabel] ?? 0) + 1;

        if ($rid !== '' && isset($regCategoryById[$rid])) {
            $cat = $regCategoryById[$rid];
            if (!isset($checkedInByCategorySet[$cat])) {
                $checkedInByCategorySet[$cat] = [];
            }
            $checkedInByCategorySet[$cat][$rid] = true;
        }
    }

    foreach ($checkedInByCategorySet as $cat => $regSet) {
        if (isset($categoryMap[$cat])) {
            $categoryMap[$cat]['checkedIn'] = count($regSet);
        }
    }

    // Recent 20 check-ins with participant details
    $stmtRecent = $pdo->query("
        SELECT
            c.id,
            c.registration_id,
            r.registration_code,
            r.full_name,
            r.category_code,
            c.venue_label,
            c.checkin_date_ist,
            c.method,
            c.checked_in_at
        FROM checkins c
        INNER JOIN registrations r ON c.registration_id = r.id
        ORDER BY c.checked_in_at DESC
        LIMIT 20
    ");
    $recentRows = $stmtRecent ? $stmtRecent->fetchAll(PDO::FETCH_ASSOC) : [];
    $recentCheckins = [];
    foreach ($recentRows as $rc) {
        $recentCheckins[] = [
            'id'               => (string)$rc['id'],
            'registrationId'   => (string)$rc['registration_id'],
            'registrationCode' => (string)$rc['registration_code'],
            'participantName'  => (string)$rc['full_name'],
            'categoryCode'     => (string)$rc['category_code'],
            'venueLabel'       => (string)($rc['venue_label'] ?? 'Main Gate'),
            'checkinDateIst'   => (string)($rc['checkin_date_ist'] ?? $todayIst),
            'method'           => (string)($rc['method'] ?? 'QR_CAMERA'),
            'checkedInAt'      => (string)($rc['checked_in_at'] ?? ''),
        ];
    }

    // 4. Payment reconciliation (combining payment_orders and payments)
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

    $seenPaymentIds = [];
    $statusAgg = [];
    foreach (['payment_orders', 'payments'] as $payTable) {
        try {
            $stmtPay = $pdo->query("SELECT id, status, amount_inr, discount_inr, net_amount_inr FROM {$payTable}");
            $payRows = $stmtPay ? $stmtPay->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($payRows as $pr) {
                $pid = (string)($pr['id'] ?? '');
                if ($pid !== '' && isset($seenPaymentIds[$pid])) {
                    continue;
                }
                if ($pid !== '') {
                    $seenPaymentIds[$pid] = true;
                }
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
        } catch (Throwable $e) {
            // Ignore if table not created
        }
    }
    $paymentReconciliation['byStatus'] = array_values($statusAgg);

    ksort($dailyTrendMap);
    $dailyRegistrationTrends = [];
    foreach ($dailyTrendMap as $date => $count) {
        $dailyRegistrationTrends[] = ['date' => (string)$date, 'count' => (int)$count];
    }

    $categoryDistribution = array_values($categoryMap);
    usort($categoryDistribution, static function (array $a, array $b): int {
        return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
    });

    ksort($attendanceByDayMap);
    $attendanceByDay = [];
    foreach ($attendanceByDayMap as $date => $count) {
        $attendanceByDay[] = ['date' => (string)$date, 'count' => (int)$count];
    }

    arsort($attendanceByVenueMap);
    $attendanceByVenue = [];
    foreach ($attendanceByVenueMap as $venueLabel => $count) {
        $attendanceByVenue[] = ['venueLabel' => (string)$venueLabel, 'count' => (int)$count];
    }

    $uniqueCheckedInParticipants = count($uniqueCheckedInRegIds);
    $notYetArrived = max(0, $confirmedRegistrations - $uniqueCheckedInParticipants);
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
            'delegates'                            => $delegates,
            'volunteerApplications'                => $volunteerApplications,
            'approvedVolunteers'                   => $approvedVolunteers,
            'pendingVolunteers'                    => $volunteerPendingCount,
            'sliApplications'                      => $sliApplications,
            'approvedSli'                          => $approvedSli,
            'pendingSli'                           => $sliPendingCount,
            'alliesSupporters'                     => $alliesSupporters,
            'participantsRequestingAccommodations' => $canReadAccomm ? count($accommByReg) : null,
            'totalCheckins'                        => $canReadAttendance ? count($allCheckins) : null,
            'uniqueCheckedInParticipants'          => $canReadAttendance ? $uniqueCheckedInParticipants : null,
            'notYetArrived'                        => $canReadAttendance ? $notYetArrived : null,
            'todayCheckins'                        => $canReadAttendance ? $todayCheckins : null,
        ],
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
