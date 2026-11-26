<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Volunteer & SLI Duty Assignment API (Phase 12)
 * Endpoint: GET / POST / PATCH / DELETE /api/admin/assignments
 *
 * Authorization: ADMIN (`volunteers.review` for Volunteers, `sli.review` for SLIs) / SUPERADMIN + verified MFA (D-20)
 * + session-bound CSRF on writes.
 *
 * Guarantees:
 *  - Only APPROVED (or legacy CONFIRMED) Volunteer / SLI registrations can be assigned (400 otherwise).
 *  - Cancelled programme sessions cannot receive new assignments (400).
 *  - Server-side overlap detection per participant (409 DUTY_OVERLAP) — an AUTHORIZED reviewer (`registrations.write`
 *    or SUPERADMIN) may override with a written `overrideReason`, which is stored and audited.
 *  - Availability conflicts and over-staffing are returned as non-blocking `warnings`.
 *  - SLIs may be paired (`pairingGroupId`) — at most 2 SLIs per group, same date & session.
 *  - Keeps `registrations.assignment_status` (UNASSIGNED / ASSIGNED / COMPLETED) in sync — approval and attendance are
 *    separate dimensions and are never touched here.
 *  - Queues VOLUNTEER_ASSIGNMENT_* / SLI_ASSIGNMENT_* notifications (+ in-app) through the outbox; DELETE only cancels.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification-service.php';
require_once __DIR__ . '/../../includes/admin-common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const PFG_DUTY_STATUSES = ['ASSIGNED', 'CANCELLED', 'COMPLETED'];

function pfgDutyCanCategory(array $user, string $category): bool
{
    return $category === 'SLI'
        ? hasPermission($user, PERMISSIONS['SLI_REVIEW'])
        : hasPermission($user, PERMISSIONS['VOLUNTEERS_REVIEW']);
}

function pfgDutyRequireCategory(array $user, string $category): void
{
    if (pfgDutyCanCategory($user, $category)) {
        return;
    }
    $perm = $category === 'SLI' ? PERMISSIONS['SLI_REVIEW'] : PERMISSIONS['VOLUNTEERS_REVIEW'];
    logAudit([
        'actorUserId'  => $user['id'] ?? null,
        'action'       => 'ACCESS_DENIED',
        'resourceType' => 'rbac',
        'resourceId'   => parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
        'metadata'     => ['requiredPermission' => $perm, 'actualRoles' => $user['roles'] ?? []],
    ]);
    jsonResponse(['error' => "Permission {$perm} is required."], 403);
}

function pfgDutyCanOverride(array $user): bool
{
    return hasPermission($user, PERMISSIONS['REGISTRATIONS_WRITE']);
}

function pfgDutyFormat(array $r): array
{
    return [
        'id'                    => (string)$r['id'],
        'registrationId'        => (string)$r['registration_id'],
        'registrationCode'      => (string)($r['registration_code'] ?? ''),
        'fullName'              => (string)($r['full_name'] ?? ''),
        'participantCategory'   => strtoupper((string)$r['participant_category']),
        'sessionId'             => $r['session_id'] !== null ? (string)$r['session_id'] : null,
        'sessionTitle'          => $r['session_title'] ?? null,
        'sessionStatus'         => isset($r['session_status']) ? strtoupper((string)$r['session_status']) : null,
        'venueId'               => $r['venue_id'] !== null ? (string)$r['venue_id'] : null,
        'venueName'             => $r['venue_name'] ?? null,
        'roleTitle'             => (string)$r['role_title'],
        'department'            => $r['department'] ?? null,
        'dutyDateIst'           => (string)$r['duty_date_ist'],
        'shiftCode'             => (string)$r['shift_code'],
        'startTime'             => (string)$r['start_time'],
        'endTime'               => (string)$r['end_time'],
        'reportingSupervisor'   => $r['reporting_supervisor'] ?? null,
        'reportingTime'         => $r['reporting_time'] ?? null,
        'reportingInstructions' => $r['reporting_instructions'] ?? null,
        'pairingGroupId'        => $r['pairing_group_id'] ?? null,
        'status'                => strtoupper((string)$r['status']),
        'overrideReason'        => $r['override_reason'] ?? null,
        'assignedByUserId'      => $r['assigned_by_user_id'] ?? null,
        'createdAt'             => (string)($r['created_at'] ?? ''),
        'updatedAt'             => (string)($r['updated_at'] ?? ''),
    ];
}

const PFG_DUTY_SELECT = '
    SELECT d.*, r.registration_code, r.full_name,
           s.title AS session_title, s.status AS session_status, v.name AS venue_name
    FROM duty_assignments d
    INNER JOIN registrations r ON r.id = d.registration_id
    LEFT JOIN festival_sessions s ON s.id = d.session_id
    LEFT JOIN venues v ON v.id = d.venue_id
';

function pfgDutyLoad(PDO $pdo, string $id): ?array
{
    $st = $pdo->prepare(PFG_DUTY_SELECT . ' WHERE d.id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Recomputes `registrations.assignment_status` from the registration's duty rows.
 */
function pfgDutySyncAssignmentStatus(PDO $pdo, string $registrationId): string
{
    $st = $pdo->prepare('SELECT status, COUNT(*) AS c FROM duty_assignments WHERE registration_id = :rid GROUP BY status');
    $st->execute([':rid' => $registrationId]);
    $counts = [];
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $counts[strtoupper((string)$r['status'])] = (int)$r['c'];
    }
    $next = !empty($counts['ASSIGNED']) ? 'ASSIGNED' : (!empty($counts['COMPLETED']) ? 'COMPLETED' : 'UNASSIGNED');
    if (pfgAdminColumnExists($pdo, 'registrations', 'assignment_status')) {
        $pdo->prepare('UPDATE registrations SET assignment_status = :s, updated_at = :now WHERE id = :id')
            ->execute([':s' => $next, ':now' => nowSql(), ':id' => $registrationId]);
    }
    return $next;
}

/**
 * Active duties of the same participant on the same IST date whose time window overlaps [start, end).
 */
function pfgDutyFindOverlaps(PDO $pdo, string $registrationId, string $date, string $start, string $end, ?string $excludeId): array
{
    $sql = "SELECT d.id, d.role_title, d.shift_code, d.start_time, d.end_time, d.duty_date_ist, s.title AS session_title, v.name AS venue_name
            FROM duty_assignments d
            LEFT JOIN festival_sessions s ON s.id = d.session_id
            LEFT JOIN venues v ON v.id = d.venue_id
            WHERE d.registration_id = :rid AND d.duty_date_ist = :dt AND d.status = 'ASSIGNED'
              AND d.start_time < :end AND d.end_time > :start";
    $params = [':rid' => $registrationId, ':dt' => $date, ':end' => $end, ':start' => $start];
    if ($excludeId !== null) {
        $sql .= ' AND d.id <> :ex';
        $params[':ex'] = $excludeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $c) {
        $out[] = [
            'id'           => (string)$c['id'],
            'roleTitle'    => (string)$c['role_title'],
            'shiftCode'    => (string)$c['shift_code'],
            'startTime'    => (string)$c['start_time'],
            'endTime'      => (string)$c['end_time'],
            'dutyDateIst'  => (string)$c['duty_date_ist'],
            'sessionTitle' => $c['session_title'] ?? null,
            'venueName'    => $c['venue_name'] ?? null,
        ];
    }
    return $out;
}

/**
 * Does the applicant's declared availability cover this IST date? Returns null when no availability was declared.
 */
function pfgDutyAvailabilityCovers(array $availability, string $date, DateTimeImmutable $editionStart): ?bool
{
    $entries = array_values(array_filter(array_map(static fn($e) => is_string($e) ? strtolower(trim($e)) : '', $availability)));
    if (!$entries) {
        return null;
    }
    try {
        $d = new DateTimeImmutable($date);
    } catch (Throwable $e) {
        return null;
    }
    $dayNumber = (int)$editionStart->diff($d)->format('%r%a') + 1;
    $dayOfMonth = (int)$d->format('j');
    $mon = strtolower($d->format('M'));
    foreach ($entries as $e) {
        if (str_contains($e, 'all') || str_contains($e, $date)) {
            return true;
        }
        if (preg_match('/day\s*0?' . $dayNumber . '\b/', $e)) {
            return true;
        }
        if (preg_match('/\b0?' . $dayOfMonth . '\s*' . $mon . '/', $e)) {
            return true;
        }
    }
    return false;
}

function pfgDutyAvailabilityFor(PDO $pdo, string $registrationId, string $category): array
{
    $table = $category === 'SLI' ? 'sli_applications' : 'volunteer_applications';
    $st = $pdo->prepare("SELECT availability_days FROM {$table} WHERE registration_id = :rid LIMIT 1");
    $st->execute([':rid' => $registrationId]);
    $raw = $st->fetchColumn();
    $list = jsonDecode($raw !== false ? $raw : '[]', []);
    return is_array($list) ? $list : [];
}

/**
 * Builds + enqueues the participant notification (outbox + in-app). Never throws.
 */
function pfgDutyNotify(PDO $pdo, array $reg, array $duty, string $category, string $kind): ?array
{
    if (empty($reg['email'])) {
        return null;
    }
    $tpl = ($category === 'SLI' ? 'SLI_ASSIGNMENT_' : 'VOLUNTEER_ASSIGNMENT_') . ($kind === 'created' ? 'CREATED' : 'UPDATED');
    $time = $duty['start_time'] . ' – ' . $duty['end_time'];
    $details = array_filter([
        'Role'                 => $duty['role_title'],
        'Department'           => $duty['department'] ?? null,
        'Date'                 => $duty['duty_date_ist'],
        'Shift'                => $duty['shift_code'] . ' (' . $time . ' IST)',
        'Session'              => $duty['session_title'] ?? null,
        'Venue'                => $duty['venue_name'] ?? null,
        'Reporting Time'       => $duty['reporting_time'] ?? null,
        'Reporting Supervisor' => $duty['reporting_supervisor'] ?? null,
        'Instructions'         => $duty['reporting_instructions'] ?? null,
        'Status'               => strtoupper((string)$duty['status']) !== 'ASSIGNED' ? ucfirst(strtolower((string)$duty['status'])) : null,
    ], static fn($v) => $v !== null && $v !== '');
    try {
        return pfgEnqueueNotification($pdo, [
            'to'              => (string)$reg['email'],
            'templateCode'    => $tpl,
            'variables'       => [
                'fullName'         => (string)$reg['full_name'],
                'registrationCode' => (string)$reg['registration_code'],
                'dutyDetails'      => $details,
            ],
            'recipientUserId' => $reg['user_id'] ?? null,
            'entityType'      => 'duty_assignment',
            'entityId'        => (string)$duty['id'],
            'idempotencyKey'  => 'duty:' . $duty['id'] . ':' . $kind . ':' . substr(md5(jsonEncode($details) . ($duty['updated_at'] ?? '')), 0, 12),
            'createInApp'     => !empty($reg['user_id']),
            'inAppTitle'      => $kind === 'created' ? 'New duty assignment' : 'Duty assignment updated',
            'inAppMessage'    => "{$duty['role_title']} on {$duty['duty_date_ist']} ({$time}).",
            'inAppActionUrl'  => '/dashboard',
        ]);
    } catch (Throwable $e) {
        error_log('/api/admin/assignments.php notification enqueue warning: ' . $e->getMessage());
        return null;
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();
    if (!pfgAdminTableExists($pdo, 'duty_assignments')) {
        jsonResponse(['error' => 'Duty assignments are unavailable until migration 010 has been applied.'], 503);
    }
    $editionId = pfgAdminEditionId($pdo);
    $canVol = pfgDutyCanCategory($user, 'VOLUNTEER');
    $canSli = pfgDutyCanCategory($user, 'SLI');
    if (!$canVol && !$canSli) {
        pfgDutyRequireCategory($user, 'VOLUNTEER');
    }

    $stEd = $pdo->prepare('SELECT start_date, end_date FROM festival_editions WHERE id = :id');
    $stEd->execute([':id' => $editionId]);
    $edRow = $stEd->fetch(PDO::FETCH_ASSOC) ?: [];
    try {
        $editionStart = new DateTimeImmutable(substr((string)($edRow['start_date'] ?? '2027-01-21'), 0, 10));
        $editionEnd = new DateTimeImmutable(substr((string)($edRow['end_date'] ?? '2027-01-23'), 0, 10));
    } catch (Throwable $e) {
        $editionStart = new DateTimeImmutable('2027-01-21');
        $editionEnd = new DateTimeImmutable('2027-01-23');
    }

    // =========================================================================
    // GET: assignment list OR candidate picker
    // =========================================================================
    if ($method === 'GET') {
        $category = strtoupper(trim((string)($_GET['category'] ?? '')));
        if ($category !== '' && !in_array($category, ['VOLUNTEER', 'SLI'], true)) {
            jsonResponse(['error' => 'category must be VOLUNTEER or SLI.'], 400);
        }
        if ($category !== '') {
            pfgDutyRequireCategory($user, $category);
        }
        $allowedCats = [];
        if ($canVol) {
            $allowedCats[] = 'VOLUNTEER';
        }
        if ($canSli) {
            $allowedCats[] = 'SLI';
        }
        $catList = $category !== '' ? [$category] : $allowedCats;
        $catPh = [];
        $catParams = [];
        foreach ($catList as $i => $c) {
            $catPh[] = ':cat' . $i;
            $catParams[':cat' . $i] = $c;
        }
        [$page, $pageSize, $offset] = pfgAdminPageParams(50, 200);
        $search = strtolower(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')));
        $likeSearch = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';

        if (strtolower((string)($_GET['view'] ?? '')) === 'candidates') {
            $where = ["r.deleted_at IS NULL", "r.edition_id = :ed", "r.category_code IN (" . implode(',', $catPh) . ")", "r.status IN ('APPROVED', 'CONFIRMED')"];
            $params = array_merge([':ed' => $editionId], $catParams);
            if ($search !== '') {
                $where[] = '(LOWER(r.full_name) LIKE :q OR LOWER(r.registration_code) LIKE :q OR LOWER(r.email) LIKE :q)';
                $params[':q'] = $likeSearch;
            }
            $whereSql = implode(' AND ', $where);
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM registrations r WHERE {$whereSql}");
            $cnt->execute($params);
            $total = (int)$cnt->fetchColumn();

            $assignCol = pfgAdminColumnExists($pdo, 'registrations', 'assignment_status') ? 'r.assignment_status' : "'UNASSIGNED'";
            $st = $pdo->prepare("
                SELECT r.id, r.registration_code, r.full_name, r.category_code, r.status, {$assignCol} AS assignment_status,
                       (SELECT COUNT(*) FROM duty_assignments d WHERE d.registration_id = r.id AND d.status = 'ASSIGNED') AS active_assignments
                FROM registrations r WHERE {$whereSql}
                ORDER BY r.full_name ASC, r.id ASC LIMIT {$pageSize} OFFSET {$offset}
            ");
            $st->execute($params);
            $items = [];
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $c) {
                $cat = strtoupper((string)$c['category_code']);
                $items[] = [
                    'registrationId'      => (string)$c['id'],
                    'registrationCode'    => (string)$c['registration_code'],
                    'fullName'            => (string)$c['full_name'],
                    'participantCategory' => $cat,
                    'status'              => strtoupper((string)$c['status']),
                    'assignmentStatus'    => strtoupper((string)$c['assignment_status']),
                    'availabilityDays'    => array_values(array_filter(pfgDutyAvailabilityFor($pdo, (string)$c['id'], $cat), 'is_string')),
                    'activeAssignments'   => (int)$c['active_assignments'],
                ];
            }
            jsonResponse(['items' => $items, 'pagination' => pfgAdminPaginationMeta($page, $pageSize, $total)]);
        }

        $where = ['d.edition_id = :ed', 'd.participant_category IN (' . implode(',', $catPh) . ')'];
        $params = array_merge([':ed' => $editionId], $catParams);
        foreach (['registrationId' => 'd.registration_id', 'sessionId' => 'd.session_id', 'venueId' => 'd.venue_id'] as $q => $col) {
            $v = trim((string)($_GET[$q] ?? ''));
            if ($v !== '') {
                if (!pfgAdminIsUuid($v)) {
                    jsonResponse(['error' => "{$q} is invalid."], 400);
                }
                $where[] = "{$col} = :{$q}";
                $params[':' . $q] = $v;
            }
        }
        $date = trim((string)($_GET['date'] ?? ''));
        if ($date !== '') {
            if (!pfgAdminValidDate($date)) {
                jsonResponse(['error' => 'date must be YYYY-MM-DD.'], 400);
            }
            $where[] = 'd.duty_date_ist = :dt';
            $params[':dt'] = $date;
        }
        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
        if ($statusFilter !== '') {
            if (!in_array($statusFilter, PFG_DUTY_STATUSES, true)) {
                jsonResponse(['error' => 'Invalid status filter.'], 400);
            }
            $where[] = 'd.status = :st';
            $params[':st'] = $statusFilter;
        }
        if ($search !== '') {
            $where[] = '(LOWER(r.full_name) LIKE :q OR LOWER(r.registration_code) LIKE :q OR LOWER(d.role_title) LIKE :q)';
            $params[':q'] = $likeSearch;
        }
        $whereSql = implode(' AND ', $where);
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM duty_assignments d INNER JOIN registrations r ON r.id = d.registration_id WHERE {$whereSql}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $st = $pdo->prepare(PFG_DUTY_SELECT . " WHERE {$whereSql} ORDER BY d.duty_date_ist ASC, d.start_time ASC, r.full_name ASC, d.id ASC LIMIT {$pageSize} OFFSET {$offset}");
        $st->execute($params);
        $items = array_map('pfgDutyFormat', $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        jsonResponse(['items' => $items, 'pagination' => pfgAdminPaginationMeta($page, $pageSize, $total)]);
    }

    // =========================================================================
    // POST: create assignment
    // =========================================================================
    if ($method === 'POST') {
        requireCsrfToken($user);
        $input = readRequestInput();

        $registrationId = trim((string)($input['registrationId'] ?? ''));
        if (!pfgAdminIsUuid($registrationId)) {
            jsonResponse(['error' => 'A valid registrationId is required.'], 400);
        }
        $stReg = $pdo->prepare('SELECT * FROM registrations WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stReg->execute([':id' => $registrationId]);
        $reg = $stReg->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            jsonResponse(['error' => 'Registration not found.'], 404);
        }
        $category = strtoupper((string)$reg['category_code']);
        if (!in_array($category, ['VOLUNTEER', 'SLI'], true)) {
            jsonResponse(['error' => 'Only Volunteer and SLI registrations can receive duty assignments.'], 400);
        }
        pfgDutyRequireCategory($user, $category);
        if (!pfgAdminIsApprovedStatus((string)$reg['status'])) {
            jsonResponse(['error' => 'Only APPROVED applicants can be assigned duties. Current status: ' . strtoupper((string)$reg['status']) . '.'], 400);
        }

        $warnings = [];
        $roleTitle = pfgAdminCleanText($input['roleTitle'] ?? '', 150);
        if ($roleTitle === null) {
            jsonResponse(['error' => 'roleTitle is required.'], 400);
        }
        $department = pfgAdminCleanText($input['department'] ?? null, 120);
        $dutyDate = trim((string)($input['dutyDateIst'] ?? ''));
        if (!pfgAdminValidDate($dutyDate)) {
            jsonResponse(['error' => 'dutyDateIst must be a valid date (YYYY-MM-DD).'], 400);
        }
        $dutyDt = new DateTimeImmutable($dutyDate);
        if ($dutyDt < $editionStart || $dutyDt > $editionEnd) {
            jsonResponse(['error' => 'dutyDateIst must fall within the festival dates (' . $editionStart->format('Y-m-d') . ' to ' . $editionEnd->format('Y-m-d') . ').'], 400);
        }
        $shiftCode = strtoupper((string)preg_replace('/[^A-Za-z0-9_-]/', '', (string)($input['shiftCode'] ?? '')));
        if ($shiftCode === '' || strlen($shiftCode) > 32) {
            jsonResponse(['error' => 'shiftCode is required (letters, digits, - or _; max 32).'], 400);
        }
        $startTime = trim((string)($input['startTime'] ?? ''));
        $endTime = trim((string)($input['endTime'] ?? ''));
        if (!pfgAdminValidTime($startTime) || !pfgAdminValidTime($endTime)) {
            jsonResponse(['error' => 'startTime and endTime must be HH:MM (24h).'], 400);
        }
        if ($endTime <= $startTime) {
            jsonResponse(['error' => 'endTime must be after startTime.'], 400);
        }

        // Session / venue validation
        $sessionId = null;
        $venueId = null;
        $session = null;
        if (!empty($input['sessionId'])) {
            $sid = (string)$input['sessionId'];
            $stS = $pdo->prepare('SELECT * FROM festival_sessions WHERE id = :id AND edition_id = :ed LIMIT 1');
            $stS->execute([':id' => $sid, ':ed' => $editionId]);
            $session = $stS->fetch(PDO::FETCH_ASSOC);
            if (!pfgAdminIsUuid($sid) || !$session) {
                jsonResponse(['error' => 'sessionId does not reference a session of this edition.'], 400);
            }
            if (strtoupper((string)$session['status']) === 'CANCELLED') {
                jsonResponse(['error' => 'This session has been cancelled; duties cannot be assigned to it.'], 400);
            }
            $sessionId = $sid;
            if (substr((string)$session['starts_at'], 0, 10) !== $dutyDate) {
                $warnings[] = 'The duty date differs from the date of the selected session (' . substr((string)$session['starts_at'], 0, 10) . ').';
            }
            if (!in_array(strtoupper((string)$session['status']), ['CONFIRMED', 'PUBLISHED'], true)) {
                $warnings[] = 'The selected session is ' . strtoupper((string)$session['status']) . ', not yet confirmed.';
            }
        }
        $venueInput = $input['venueId'] ?? ($session['venue_id'] ?? null);
        if (!empty($venueInput)) {
            $stV = $pdo->prepare('SELECT id, name, confirmation_status FROM venues WHERE id = :id AND edition_id = :ed LIMIT 1');
            $stV->execute([':id' => (string)$venueInput, ':ed' => $editionId]);
            $venue = $stV->fetch(PDO::FETCH_ASSOC);
            if (!pfgAdminIsUuid((string)$venueInput) || !$venue) {
                jsonResponse(['error' => 'venueId does not reference a venue of this edition.'], 400);
            }
            if (strtoupper((string)$venue['confirmation_status']) === 'ARCHIVED') {
                jsonResponse(['error' => 'Archived venues cannot receive new assignments.'], 400);
            }
            if (strtoupper((string)$venue['confirmation_status']) !== 'CONFIRMED') {
                $warnings[] = "Venue \"{$venue['name']}\" is " . strtoupper((string)$venue['confirmation_status']) . ', not CONFIRMED.';
            }
            $venueId = (string)$venue['id'];
        }

        // SLI pairing group
        $pairingGroupId = null;
        if (!empty($input['pairingGroupId'])) {
            $pairingGroupId = (string)$input['pairingGroupId'];
            if (!pfgAdminIsUuid($pairingGroupId)) {
                jsonResponse(['error' => 'pairingGroupId must be a UUID.'], 400);
            }
            if ($category !== 'SLI') {
                jsonResponse(['error' => 'Pairing groups apply to SLI assignments only.'], 400);
            }
            $stG = $pdo->prepare("SELECT registration_id, duty_date_ist, session_id FROM duty_assignments WHERE pairing_group_id = :g AND status = 'ASSIGNED'");
            $stG->execute([':g' => $pairingGroupId]);
            $members = $stG->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (count($members) >= 2) {
                jsonResponse(['error' => 'A pairing group supports at most 2 SLIs.'], 400);
            }
            foreach ($members as $m) {
                if ((string)$m['registration_id'] === $registrationId) {
                    jsonResponse(['error' => 'This SLI is already part of the pairing group.'], 400);
                }
                if ((string)$m['duty_date_ist'] !== $dutyDate || (string)($m['session_id'] ?? '') !== (string)($sessionId ?? '')) {
                    jsonResponse(['error' => 'Paired SLIs must share the same duty date and session.'], 400);
                }
            }
        }

        // Overlap detection + authorized override
        $conflicts = pfgDutyFindOverlaps($pdo, $registrationId, $dutyDate, $startTime, $endTime, null);
        $overrideReason = pfgAdminCleanText($input['overrideReason'] ?? null, 1000);
        $overridden = false;
        if ($conflicts) {
            if ($overrideReason === null) {
                jsonResponse([
                    'code'      => 'DUTY_OVERLAP',
                    'error'     => 'This participant already has an overlapping duty on that date. Provide an overrideReason to proceed (requires registrations.write).',
                    'conflicts' => $conflicts,
                ], 409);
            }
            if (!pfgDutyCanOverride($user)) {
                jsonResponse(['error' => 'Overriding an overlap requires the registrations.write permission.', 'code' => 'DUTY_OVERLAP', 'conflicts' => $conflicts], 403);
            }
            if (strlen($overrideReason) < 10) {
                jsonResponse(['error' => 'overrideReason must explain the override (min 10 characters).'], 400);
            }
            $overridden = true;
        }

        // Non-blocking warnings: availability and headcount
        $availability = pfgDutyAvailabilityFor($pdo, $registrationId, $category);
        if (pfgDutyAvailabilityCovers($availability, $dutyDate, $editionStart) === false) {
            $warnings[] = 'The applicant did not mark ' . $dutyDate . ' as an available day.';
        }
        if ($session) {
            $need = (int)($session[$category === 'SLI' ? 'slis_needed' : 'volunteers_needed'] ?? 0);
            if ($need > 0) {
                $cc = $pdo->prepare("SELECT COUNT(*) FROM duty_assignments WHERE session_id = :sid AND participant_category = :c AND status = 'ASSIGNED'");
                $cc->execute([':sid' => $sessionId, ':c' => $category]);
                if ((int)$cc->fetchColumn() >= $need) {
                    $warnings[] = "This session already has its requested {$need} " . ($category === 'SLI' ? 'SLI(s)' : 'volunteer(s)') . '.';
                }
            }
        }

        $id = uuid4();
        $now = nowSql();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('
                INSERT INTO duty_assignments (
                    id, edition_id, registration_id, participant_category, session_id, venue_id, role_title, department,
                    duty_date_ist, shift_code, start_time, end_time, reporting_supervisor, reporting_time, reporting_instructions,
                    pairing_group_id, status, assigned_by_user_id, override_reason, created_at, updated_at
                ) VALUES (
                    :id, :ed, :rid, :cat, :sid, :vid, :role, :dept,
                    :dt, :shift, :start, :end, :sup, :rtime, :instr,
                    :pair, \'ASSIGNED\', :by, :override, :now, :now
                )
            ')->execute([
                ':id' => $id, ':ed' => $editionId, ':rid' => $registrationId, ':cat' => $category, ':sid' => $sessionId, ':vid' => $venueId,
                ':role' => $roleTitle, ':dept' => $department, ':dt' => $dutyDate, ':shift' => $shiftCode, ':start' => $startTime, ':end' => $endTime,
                ':sup' => pfgAdminCleanText($input['reportingSupervisor'] ?? null, 150),
                ':rtime' => pfgAdminCleanText($input['reportingTime'] ?? null, 80),
                ':instr' => pfgAdminCleanText($input['reportingInstructions'] ?? null, 2000),
                ':pair' => $pairingGroupId, ':by' => $user['id'], ':override' => $overridden ? $overrideReason : null, ':now' => $now,
            ]);
            pfgDutySyncAssignmentStatus($pdo, $registrationId);
            $pdo->commit();
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txe;
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'DUTY_ASSIGNED',
            'resourceType' => 'duty_assignment',
            'resourceId'   => $id,
            'metadata'     => [
                'registrationId' => $registrationId, 'category' => $category, 'date' => $dutyDate, 'shift' => $shiftCode,
                'sessionId' => $sessionId, 'venueId' => $venueId, 'pairingGroupId' => $pairingGroupId, 'warnings' => count($warnings),
            ],
        ]);
        if ($overridden) {
            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => 'DUTY_OVERLAP_OVERRIDDEN',
                'resourceType' => 'duty_assignment',
                'resourceId'   => $id,
                'metadata'     => ['registrationId' => $registrationId, 'reason' => $overrideReason, 'conflictIds' => array_column($conflicts, 'id')],
            ]);
        }

        $row = pfgDutyLoad($pdo, $id);
        $notification = null;
        if (!array_key_exists('notify', $input) || !empty($input['notify'])) {
            $notification = pfgDutyNotify($pdo, $reg, $row, $category, 'created');
        }
        jsonResponse([
            'assignment'       => pfgDutyFormat($row),
            'warnings'         => $warnings,
            'assignmentStatus' => 'ASSIGNED',
            'notification'     => $notification ? ['queueId' => $notification['queueId'] ?? null, 'status' => $notification['status'] ?? null] : null,
        ], 201);
    }

    // =========================================================================
    // PATCH / DELETE (DELETE cancels — duty history is never hard-deleted)
    // =========================================================================
    if (in_array($method, ['PATCH', 'PUT', 'DELETE'], true)) {
        requireCsrfToken($user);
        $input = readRequestInput();
        $id = trim((string)($input['id'] ?? $input['assignmentId'] ?? $_GET['id'] ?? ''));
        if (!pfgAdminIsUuid($id)) {
            jsonResponse(['error' => 'A valid assignment id is required.'], 400);
        }
        $existing = pfgDutyLoad($pdo, $id);
        if (!$existing || (string)$existing['edition_id'] !== $editionId) {
            jsonResponse(['error' => 'Assignment not found.'], 404);
        }
        $category = strtoupper((string)$existing['participant_category']);
        pfgDutyRequireCategory($user, $category);

        $stReg = $pdo->prepare('SELECT * FROM registrations WHERE id = :id LIMIT 1');
        $stReg->execute([':id' => $existing['registration_id']]);
        $reg = $stReg->fetch(PDO::FETCH_ASSOC);
        if (!$reg) {
            jsonResponse(['error' => 'Registration not found.'], 404);
        }

        if ($method === 'DELETE') {
            $input['status'] = 'CANCELLED';
        }
        $warnings = [];
        $f = [];
        $oldStatus = strtoupper((string)$existing['status']);
        $newStatus = $oldStatus;
        if (array_key_exists('status', $input)) {
            $newStatus = strtoupper(trim((string)$input['status']));
            if (!in_array($newStatus, PFG_DUTY_STATUSES, true)) {
                jsonResponse(['error' => 'status must be one of: ' . implode(', ', PFG_DUTY_STATUSES) . '.'], 400);
            }
            $f['status'] = $newStatus;
        }
        if ($oldStatus === 'CANCELLED' && $newStatus === 'CANCELLED' && count(array_diff(array_keys($input), ['id', 'assignmentId', 'status', 'notify', 'csrf_token', 'csrfToken', '_csrf'])) > 0) {
            jsonResponse(['error' => 'A cancelled assignment cannot be edited. Re-activate it (status ASSIGNED) first.'], 400);
        }
        if ($newStatus === 'ASSIGNED' && !pfgAdminIsApprovedStatus((string)$reg['status'])) {
            jsonResponse(['error' => 'Only APPROVED applicants can hold an active duty assignment. Current status: ' . strtoupper((string)$reg['status']) . '.'], 400);
        }

        $dutyDate = (string)$existing['duty_date_ist'];
        $startTime = (string)$existing['start_time'];
        $endTime = (string)$existing['end_time'];
        $sessionId = $existing['session_id'];
        $scheduleChanged = false;

        if (array_key_exists('roleTitle', $input)) {
            $rt = pfgAdminCleanText($input['roleTitle'], 150);
            if ($rt === null) {
                jsonResponse(['error' => 'roleTitle cannot be empty.'], 400);
            }
            $f['role_title'] = $rt;
        }
        foreach (['department' => ['department', 120], 'reportingSupervisor' => ['reporting_supervisor', 150], 'reportingTime' => ['reporting_time', 80], 'reportingInstructions' => ['reporting_instructions', 2000]] as $k => [$col, $max]) {
            if (array_key_exists($k, $input)) {
                $f[$col] = pfgAdminCleanText($input[$k], $max);
            }
        }
        if (array_key_exists('dutyDateIst', $input)) {
            $dutyDate = trim((string)$input['dutyDateIst']);
            if (!pfgAdminValidDate($dutyDate)) {
                jsonResponse(['error' => 'dutyDateIst must be a valid date (YYYY-MM-DD).'], 400);
            }
            $dt = new DateTimeImmutable($dutyDate);
            if ($dt < $editionStart || $dt > $editionEnd) {
                jsonResponse(['error' => 'dutyDateIst must fall within the festival dates.'], 400);
            }
            $f['duty_date_ist'] = $dutyDate;
            $scheduleChanged = true;
        }
        if (array_key_exists('shiftCode', $input)) {
            $sc = strtoupper((string)preg_replace('/[^A-Za-z0-9_-]/', '', (string)$input['shiftCode']));
            if ($sc === '' || strlen($sc) > 32) {
                jsonResponse(['error' => 'shiftCode is invalid.'], 400);
            }
            $f['shift_code'] = $sc;
        }
        if (array_key_exists('startTime', $input) || array_key_exists('endTime', $input)) {
            $startTime = trim((string)($input['startTime'] ?? $startTime));
            $endTime = trim((string)($input['endTime'] ?? $endTime));
            if (!pfgAdminValidTime($startTime) || !pfgAdminValidTime($endTime) || $endTime <= $startTime) {
                jsonResponse(['error' => 'startTime / endTime must be HH:MM with endTime after startTime.'], 400);
            }
            $f['start_time'] = $startTime;
            $f['end_time'] = $endTime;
            $scheduleChanged = true;
        }
        if (array_key_exists('sessionId', $input)) {
            if ($input['sessionId'] === null || $input['sessionId'] === '') {
                $f['session_id'] = null;
                $sessionId = null;
            } else {
                $stS = $pdo->prepare('SELECT * FROM festival_sessions WHERE id = :id AND edition_id = :ed LIMIT 1');
                $stS->execute([':id' => (string)$input['sessionId'], ':ed' => $editionId]);
                $session = $stS->fetch(PDO::FETCH_ASSOC);
                if (!pfgAdminIsUuid((string)$input['sessionId']) || !$session) {
                    jsonResponse(['error' => 'sessionId does not reference a session of this edition.'], 400);
                }
                if (strtoupper((string)$session['status']) === 'CANCELLED') {
                    jsonResponse(['error' => 'This session has been cancelled; duties cannot be assigned to it.'], 400);
                }
                $f['session_id'] = (string)$session['id'];
                $sessionId = (string)$session['id'];
            }
        }
        if (array_key_exists('venueId', $input)) {
            if ($input['venueId'] === null || $input['venueId'] === '') {
                $f['venue_id'] = null;
            } else {
                $stV = $pdo->prepare('SELECT id, confirmation_status FROM venues WHERE id = :id AND edition_id = :ed LIMIT 1');
                $stV->execute([':id' => (string)$input['venueId'], ':ed' => $editionId]);
                $venue = $stV->fetch(PDO::FETCH_ASSOC);
                if (!pfgAdminIsUuid((string)$input['venueId']) || !$venue || strtoupper((string)$venue['confirmation_status']) === 'ARCHIVED') {
                    jsonResponse(['error' => 'venueId does not reference an active venue of this edition.'], 400);
                }
                $f['venue_id'] = (string)$venue['id'];
            }
        }
        if (array_key_exists('pairingGroupId', $input)) {
            $pg = $input['pairingGroupId'];
            if ($pg === null || $pg === '') {
                $f['pairing_group_id'] = null;
            } else {
                if (!pfgAdminIsUuid((string)$pg) || $category !== 'SLI') {
                    jsonResponse(['error' => 'pairingGroupId must be a UUID and applies to SLI assignments only.'], 400);
                }
                $stG = $pdo->prepare("SELECT registration_id, duty_date_ist, session_id FROM duty_assignments WHERE pairing_group_id = :g AND status = 'ASSIGNED' AND id <> :id");
                $stG->execute([':g' => (string)$pg, ':id' => $id]);
                $members = $stG->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (count($members) >= 2) {
                    jsonResponse(['error' => 'A pairing group supports at most 2 SLIs.'], 400);
                }
                foreach ($members as $m) {
                    if ((string)$m['registration_id'] === (string)$existing['registration_id']) {
                        jsonResponse(['error' => 'This SLI is already part of the pairing group.'], 400);
                    }
                    if ((string)$m['duty_date_ist'] !== $dutyDate || (string)($m['session_id'] ?? '') !== (string)($sessionId ?? '')) {
                        jsonResponse(['error' => 'Paired SLIs must share the same duty date and session.'], 400);
                    }
                }
                $f['pairing_group_id'] = (string)$pg;
            }
        }

        // Overlap re-check whenever the schedule changes or a cancelled duty is re-activated
        $needsOverlapCheck = $newStatus === 'ASSIGNED' && ($scheduleChanged || $oldStatus !== 'ASSIGNED');
        $overrideReason = pfgAdminCleanText($input['overrideReason'] ?? null, 1000);
        if ($needsOverlapCheck) {
            $conflicts = pfgDutyFindOverlaps($pdo, (string)$existing['registration_id'], $dutyDate, $startTime, $endTime, $id);
            if ($conflicts) {
                if ($overrideReason === null) {
                    jsonResponse([
                        'code' => 'DUTY_OVERLAP',
                        'error' => 'This change overlaps another duty for the same participant. Provide an overrideReason to proceed (requires registrations.write).',
                        'conflicts' => $conflicts,
                    ], 409);
                }
                if (!pfgDutyCanOverride($user)) {
                    jsonResponse(['error' => 'Overriding an overlap requires the registrations.write permission.', 'code' => 'DUTY_OVERLAP', 'conflicts' => $conflicts], 403);
                }
                if (strlen($overrideReason) < 10) {
                    jsonResponse(['error' => 'overrideReason must explain the override (min 10 characters).'], 400);
                }
                $f['override_reason'] = $overrideReason;
                logAudit([
                    'actorUserId'  => $user['id'],
                    'action'       => 'DUTY_OVERLAP_OVERRIDDEN',
                    'resourceType' => 'duty_assignment',
                    'resourceId'   => $id,
                    'metadata'     => ['registrationId' => $existing['registration_id'], 'reason' => $overrideReason, 'conflictIds' => array_column($conflicts, 'id')],
                ]);
            }
            $availability = pfgDutyAvailabilityFor($pdo, (string)$existing['registration_id'], $category);
            if (pfgDutyAvailabilityCovers($availability, $dutyDate, $editionStart) === false) {
                $warnings[] = 'The applicant did not mark ' . $dutyDate . ' as an available day.';
            }
        }

        if (!$f) {
            jsonResponse(['error' => 'No updatable fields were provided.'], 400);
        }

        $sets = [];
        $params = [':id' => $id];
        foreach ($f as $col => $val) {
            $sets[] = "{$col} = :{$col}";
            $params[':' . $col] = $val;
        }
        $sets[] = 'updated_at = :upd';
        $params[':upd'] = nowSql();

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE duty_assignments SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            $assignmentStatus = pfgDutySyncAssignmentStatus($pdo, (string)$existing['registration_id']);
            $pdo->commit();
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txe;
        }

        $action = $newStatus !== $oldStatus
            ? ($newStatus === 'CANCELLED' ? 'DUTY_CANCELLED' : ($newStatus === 'COMPLETED' ? 'DUTY_COMPLETED' : 'DUTY_REACTIVATED'))
            : 'DUTY_UPDATED';
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => $action,
            'resourceType' => 'duty_assignment',
            'resourceId'   => $id,
            'metadata'     => ['registrationId' => $existing['registration_id'], 'fields' => array_keys($f), 'previousStatus' => $oldStatus, 'newStatus' => $newStatus],
        ]);

        $row = pfgDutyLoad($pdo, $id);
        $notification = null;
        $material = $scheduleChanged || $newStatus !== $oldStatus
            || array_intersect(array_keys($f), ['venue_id', 'session_id', 'role_title', 'shift_code', 'reporting_supervisor', 'reporting_time', 'reporting_instructions']);
        if ($material && $newStatus !== 'COMPLETED' && (!array_key_exists('notify', $input) || !empty($input['notify']))) {
            $notification = pfgDutyNotify($pdo, $reg, $row, $category, 'updated');
        }
        jsonResponse([
            'assignment'       => pfgDutyFormat($row),
            'warnings'         => $warnings,
            'assignmentStatus' => $assignmentStatus,
            'notification'     => $notification ? ['queueId' => $notification['queueId'] ?? null, 'status' => $notification['status'] ?? null] : null,
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/assignments.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process duty assignment request.'], 500);
}
