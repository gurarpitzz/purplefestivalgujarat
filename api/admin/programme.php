<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Programme API (Phase 12)
 * Endpoint: GET / POST / PATCH /api/admin/programme
 *
 * Manages `festival_sessions` (operational sessions with volunteer / SLI headcount needs) and `programme_items`
 * (public programme grid entries). Both carry an explicit lifecycle status so DRAFT / PROPOSED content is never
 * mistaken for confirmed programme: DRAFT | PROPOSED | CONFIRMED | PUBLISHED | CANCELLED.
 *
 * Writes: ADMIN (`cms.manage` or `programme.manage`) / SUPERADMIN + MFA (D-20) + session-bound CSRF.
 * Reads: the above plus Volunteer / SLI coordinators (read-only, to build duty rosters).
 * Optional `notifyParticipants` queues PROGRAMME_CHANGE_NOTICE (never bypasses MAIL_DRIVER / the queue worker).
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

const PFG_PROGRAMME_STATUSES = ['DRAFT', 'PROPOSED', 'CONFIRMED', 'PUBLISHED', 'CANCELLED'];
const PFG_PROGRAMME_NOTIFY_CAP = 5000;

function pfgProgNormalizeDateTime($value, string $field): string
{
    $s = trim((string)$value);
    // Optional trailing timezone (e.g. +05:30 / Z) is accepted and ignored: all festival times are IST.
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/', $s, $m) || !pfgAdminValidDate($m[1])) {
        jsonResponse(['error' => "{$field} must be a valid date-time (YYYY-MM-DD HH:MM)."], 400);
    }
    return $m[1] . ' ' . $m[2] . ($m[3] ?? ':00');
}

function pfgProgEditionDays(PDO $pdo, string $editionId): array
{
    $st = $pdo->prepare('SELECT start_date, end_date FROM festival_editions WHERE id = :id LIMIT 1');
    $st->execute([':id' => $editionId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    try {
        $start = new DateTimeImmutable(substr((string)($row['start_date'] ?? '2027-01-21'), 0, 10));
        $end = new DateTimeImmutable(substr((string)($row['end_date'] ?? '2027-01-23'), 0, 10));
        $days = (int)$start->diff($end)->days + 1;
    } catch (Throwable $e) {
        $start = new DateTimeImmutable('2027-01-21');
        $days = 3;
    }
    return [$start, max(1, min(14, $days))];
}

function pfgProgStatus($value, string $default = 'DRAFT'): string
{
    $s = strtoupper(trim((string)($value ?? $default)));
    if (!in_array($s, PFG_PROGRAMME_STATUSES, true)) {
        jsonResponse(['error' => 'status must be one of: ' . implode(', ', PFG_PROGRAMME_STATUSES) . '.'], 400);
    }
    return $s;
}

function pfgProgIntField($value, string $name, int $min, int $max): int
{
    if (!is_numeric($value) || (int)$value < $min || (int)$value > $max) {
        jsonResponse(['error' => "{$name} must be a whole number between {$min} and {$max}."], 400);
    }
    return (int)$value;
}

function pfgProgResolveVenue(PDO $pdo, string $editionId, $venueId, array &$warnings): ?string
{
    if ($venueId === null || $venueId === '') {
        return null;
    }
    if (!is_string($venueId) || !pfgAdminIsUuid($venueId)) {
        jsonResponse(['error' => 'venueId is invalid.'], 400);
    }
    $st = $pdo->prepare('SELECT id, name, confirmation_status FROM venues WHERE id = :id AND edition_id = :ed LIMIT 1');
    $st->execute([':id' => $venueId, ':ed' => $editionId]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    if (!$v) {
        jsonResponse(['error' => 'Venue not found for this edition.'], 400);
    }
    $vs = strtoupper((string)$v['confirmation_status']);
    if ($vs === 'ARCHIVED') {
        jsonResponse(['error' => 'Archived venues cannot be used for new programme entries.'], 400);
    }
    if ($vs !== 'CONFIRMED') {
        $warnings[] = "Venue \"{$v['name']}\" is {$vs}, not CONFIRMED.";
    }
    return (string)$v['id'];
}

function pfgProgFormatSession(array $s, array $assigned): array
{
    $id = (string)$s['id'];
    return [
        'id'                   => $id,
        'title'                => (string)$s['title'],
        'track'                => (string)$s['track'],
        'dayNumber'            => (int)$s['day_number'],
        'startsAt'             => (string)$s['starts_at'],
        'endsAt'               => (string)$s['ends_at'],
        'venueId'              => $s['venue_id'] !== null ? (string)$s['venue_id'] : null,
        'venueName'            => $s['venue_name'] ?? null,
        'venueConfirmationStatus' => isset($s['venue_status']) ? strtoupper((string)$s['venue_status']) : null,
        'islRequired'          => !empty($s['isl_required']),
        'captioningAvailable'  => !empty($s['captioning_available']),
        'wheelchairAccessible' => !empty($s['wheelchair_accessible']),
        'status'               => strtoupper((string)$s['status']),
        'description'          => $s['description'] ?? null,
        'volunteersNeeded'     => (int)($s['volunteers_needed'] ?? 0),
        'slisNeeded'           => (int)($s['slis_needed'] ?? 0),
        'assignedVolunteers'   => (int)($assigned[$id]['VOLUNTEER'] ?? 0),
        'assignedSlis'         => (int)($assigned[$id]['SLI'] ?? 0),
        'createdAt'            => (string)($s['created_at'] ?? ''),
        'updatedAt'            => (string)($s['updated_at'] ?? ($s['created_at'] ?? '')),
    ];
}

function pfgProgFormatItem(array $i): array
{
    $speakers = jsonDecode($i['speakers'] ?? '[]', []);
    $services = jsonDecode($i['accessibility_services'] ?? '[]', []);
    return [
        'id'                    => (string)$i['id'],
        'sessionId'             => $i['session_id'] !== null ? (string)$i['session_id'] : null,
        'dayNumber'             => (int)$i['day_number'],
        'timeSlot'              => (string)$i['time_slot'],
        'title'                 => (string)$i['title'],
        'description'           => $i['description'] ?? null,
        'category'              => (string)$i['category'],
        'venueName'             => $i['venue_name'] ?? null,
        'speakers'              => is_array($speakers) ? array_values(array_filter($speakers, 'is_string')) : [],
        'accessibilityServices' => is_array($services) ? array_values(array_filter($services, 'is_string')) : [],
        'displayOrder'          => (int)$i['display_order'],
        'status'                => strtoupper((string)$i['status']),
        'createdAt'             => (string)($i['created_at'] ?? ''),
        'updatedAt'             => (string)($i['updated_at'] ?? ($i['created_at'] ?? '')),
    ];
}

function pfgProgLoadSession(PDO $pdo, string $editionId, string $id): ?array
{
    $st = $pdo->prepare('
        SELECT s.*, v.name AS venue_name, v.confirmation_status AS venue_status
        FROM festival_sessions s LEFT JOIN venues v ON v.id = s.venue_id
        WHERE s.id = :id AND s.edition_id = :ed LIMIT 1
    ');
    $st->execute([':id' => $id, ':ed' => $editionId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function pfgProgAssignedCounts(PDO $pdo): array
{
    $assigned = [];
    if (!pfgAdminTableExists($pdo, 'duty_assignments')) {
        return $assigned;
    }
    $st = $pdo->query("SELECT session_id, participant_category, COUNT(*) AS c FROM duty_assignments WHERE status = 'ASSIGNED' AND session_id IS NOT NULL GROUP BY session_id, participant_category");
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        $assigned[(string)$r['session_id']][strtoupper((string)$r['participant_category'])] = (int)$r['c'];
    }
    return $assigned;
}

/**
 * Queues PROGRAMME_CHANGE_NOTICE for affected participants. Returns number queued.
 * audience ASSIGNED => volunteers / SLIs with an ACTIVE duty on the session; ALL => every approved / confirmed registration.
 */
function pfgProgNotify(PDO $pdo, array $user, string $entityType, string $entityId, string $summary, string $audience, ?string $sessionId): int
{
    $params = [];
    if ($audience === 'ALL') {
        $sql = "SELECT r.id, r.email, r.full_name, r.registration_code, r.user_id FROM registrations r
                WHERE r.deleted_at IS NULL AND r.status IN ('CONFIRMED', 'APPROVED') AND r.email <> ''";
    } else {
        if ($sessionId === null || !pfgAdminTableExists($pdo, 'duty_assignments')) {
            return 0;
        }
        $sql = "SELECT DISTINCT r.id, r.email, r.full_name, r.registration_code, r.user_id FROM duty_assignments d
                INNER JOIN registrations r ON r.id = d.registration_id
                WHERE d.session_id = :sid AND d.status = 'ASSIGNED' AND r.deleted_at IS NULL AND r.email <> ''";
        $params[':sid'] = $sessionId;
    }
    $st = $pdo->prepare($sql . ' LIMIT ' . PFG_PROGRAMME_NOTIFY_CAP);
    $st->execute($params);
    $stamp = substr(md5($summary . '|' . nowSql()), 0, 10);
    $queued = 0;
    $seen = [];
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $emailKey = strtolower((string)$r['email']);
        if (isset($seen[$emailKey])) {
            continue;
        }
        $seen[$emailKey] = true;
        try {
            $q = pfgEnqueueNotification($pdo, [
                'to'              => (string)$r['email'],
                'templateCode'    => 'PROGRAMME_CHANGE_NOTICE',
                'variables'       => [
                    'fullName'         => (string)$r['full_name'],
                    'registrationCode' => (string)$r['registration_code'],
                    'changeSummary'    => $summary,
                    'programmeUrl'     => rtrim((string)APP_URL, '/') . '/programme',
                ],
                'recipientUserId' => $r['user_id'] ?? null,
                'entityType'      => $entityType,
                'entityId'        => $entityId,
                'idempotencyKey'  => 'prog-change:' . $entityId . ':' . $stamp . ':' . substr((string)$r['id'], 0, 8),
                'createInApp'     => !empty($r['user_id']),
                'inAppTitle'      => 'Programme Update',
                'inAppMessage'    => mb_substr($summary, 0, 300, 'UTF-8'),
                'inAppActionUrl'  => '/programme',
            ]);
            if (empty($q['deduped'])) {
                $queued++;
            }
        } catch (Throwable $e) {
            error_log('/api/admin/programme.php notify warning: ' . $e->getMessage());
        }
    }
    logAudit([
        'actorUserId'  => $user['id'],
        'action'       => 'PROGRAMME_CHANGE_NOTICE_QUEUED',
        'resourceType' => $entityType,
        'resourceId'   => $entityId,
        'metadata'     => ['audience' => $audience, 'queued' => $queued],
    ]);
    return $queued;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();
    $editionId = pfgAdminEditionId($pdo);
    $sessionExtraCols = pfgAdminColumnExists($pdo, 'festival_sessions', 'volunteers_needed');
    $sessionDescCol = pfgAdminColumnExists($pdo, 'festival_sessions', 'description');
    $sessionUpdatedCol = pfgAdminColumnExists($pdo, 'festival_sessions', 'updated_at');
    [$editionStart, $editionDays] = pfgProgEditionDays($pdo, $editionId);

    // =========================================================================
    // GET
    // =========================================================================
    if ($method === 'GET') {
        if (!pfgAdminCanManageProgramme($user)
            && !hasPermission($user, PERMISSIONS['VOLUNTEERS_REVIEW'])
            && !hasPermission($user, PERMISSIONS['SLI_REVIEW'])) {
            pfgAdminRequireProgrammeAccess($user);
        }
        $type = strtolower(trim((string)($_GET['type'] ?? 'all')));
        $day = (int)($_GET['day'] ?? 0);
        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
        if ($statusFilter !== '' && !in_array($statusFilter, PFG_PROGRAMME_STATUSES, true)) {
            jsonResponse(['error' => 'Invalid status filter.'], 400);
        }

        $sessions = [];
        $items = [];
        if ($type === 'all' || $type === 'sessions') {
            $where = ['s.edition_id = :ed'];
            $params = [':ed' => $editionId];
            if ($day > 0) {
                $where[] = 's.day_number = :day';
                $params[':day'] = $day;
            }
            if ($statusFilter !== '') {
                $where[] = 'UPPER(s.status) = :st';
                $params[':st'] = $statusFilter;
            }
            $st = $pdo->prepare('
                SELECT s.*, v.name AS venue_name, v.confirmation_status AS venue_status
                FROM festival_sessions s LEFT JOIN venues v ON v.id = s.venue_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY s.day_number ASC, s.starts_at ASC, s.title ASC');
            $st->execute($params);
            $assigned = pfgProgAssignedCounts($pdo);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $s) {
                $sessions[] = pfgProgFormatSession($s, $assigned);
            }
        }
        if ($type === 'all' || $type === 'items') {
            $where = ['edition_id = :ed'];
            $params = [':ed' => $editionId];
            if ($day > 0) {
                $where[] = 'day_number = :day';
                $params[':day'] = $day;
            }
            if ($statusFilter !== '') {
                $where[] = 'UPPER(status) = :st';
                $params[':st'] = $statusFilter;
            }
            $st = $pdo->prepare('SELECT * FROM programme_items WHERE ' . implode(' AND ', $where) . ' ORDER BY day_number ASC, display_order ASC, time_slot ASC');
            $st->execute($params);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $i) {
                $items[] = pfgProgFormatItem($i);
            }
        }
        jsonResponse([
            'sessions'   => $sessions,
            'items'      => $items,
            'statuses'   => PFG_PROGRAMME_STATUSES,
            'editionDays' => $editionDays,
        ]);
    }

    // =========================================================================
    // POST / PATCH
    // =========================================================================
    if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
        pfgAdminRequireProgrammeAccess($user);
        requireCsrfToken($user);
        $input = readRequestInput();
        $isCreate = $method === 'POST';
        $entity = strtolower(trim((string)($input['entity'] ?? '')));
        if (!in_array($entity, ['session', 'item'], true)) {
            jsonResponse(['error' => "entity must be 'session' or 'item'."], 400);
        }

        $warnings = [];
        $notifyRequested = !empty($input['notifyParticipants']);
        $changeSummary = null;
        $audience = 'ASSIGNED';
        if ($notifyRequested) {
            $changeSummary = pfgAdminCleanText($input['changeSummary'] ?? '', 1000);
            if ($changeSummary === null || strlen($changeSummary) < 5) {
                jsonResponse(['error' => 'changeSummary (min 5 characters) is required when notifyParticipants is true.'], 400);
            }
            $audience = strtoupper(trim((string)($input['notifyAudience'] ?? 'ASSIGNED'))) === 'ALL' ? 'ALL' : 'ASSIGNED';
            if ($audience === 'ALL' && !pfgAdminCanManageCommunications($user)) {
                jsonResponse(['error' => 'Permission cms.manage or communications.manage is required to notify all participants.'], 403);
            }
        }

        // ------------------------------ SESSIONS ------------------------------
        if ($entity === 'session') {
            $id = null;
            $existing = null;
            if (!$isCreate) {
                $id = trim((string)($input['id'] ?? ''));
                if ($id === '') {
                    jsonResponse(['error' => 'Session id is required.'], 400);
                }
                $existing = pfgProgLoadSession($pdo, $editionId, $id);
                if (!$existing) {
                    jsonResponse(['error' => 'Session not found.'], 404);
                }
            }
            $has = static fn(string $k): bool => $isCreate || array_key_exists($k, $input);
            $f = [];

            if ($has('title')) {
                $title = pfgAdminCleanText($input['title'] ?? '', 250);
                if ($title === null || strlen($title) < 2) {
                    jsonResponse(['error' => 'Session title is required.'], 400);
                }
                $f['title'] = $title;
            }
            if ($has('track')) {
                $f['track'] = pfgAdminCleanText($input['track'] ?? 'General', 100) ?? 'General';
            }
            if ($has('dayNumber')) {
                $f['day_number'] = pfgProgIntField($input['dayNumber'] ?? null, 'dayNumber', 1, $editionDays);
            }
            if ($has('startsAt')) {
                $f['starts_at'] = pfgProgNormalizeDateTime($input['startsAt'] ?? '', 'startsAt');
            }
            if ($has('endsAt')) {
                $f['ends_at'] = pfgProgNormalizeDateTime($input['endsAt'] ?? '', 'endsAt');
            }
            $startsAt = $f['starts_at'] ?? (string)($existing['starts_at'] ?? '');
            $endsAt = $f['ends_at'] ?? (string)($existing['ends_at'] ?? '');
            if (strtotime($endsAt) <= strtotime($startsAt)) {
                jsonResponse(['error' => 'endsAt must be after startsAt.'], 400);
            }
            $dayNumber = $f['day_number'] ?? (int)($existing['day_number'] ?? 1);
            $expectedDate = $editionStart->modify('+' . ($dayNumber - 1) . ' days')->format('Y-m-d');
            if (substr($startsAt, 0, 10) !== $expectedDate) {
                $warnings[] = "startsAt date {$expectedDate} was expected for Day {$dayNumber}.";
            }
            if ($has('venueId')) {
                $f['venue_id'] = pfgProgResolveVenue($pdo, $editionId, $input['venueId'] ?? null, $warnings);
            }
            foreach (['islRequired' => 'isl_required', 'captioningAvailable' => 'captioning_available', 'wheelchairAccessible' => 'wheelchair_accessible'] as $k => $col) {
                if (array_key_exists($k, $input)) {
                    $f[$col] = !empty($input[$k]) ? 1 : 0;
                } elseif ($isCreate) {
                    $f[$col] = 1;
                }
            }
            if ($has('status')) {
                $f['status'] = pfgProgStatus($input['status'] ?? null, 'DRAFT');
            }
            if ($sessionDescCol && $has('description')) {
                $f['description'] = pfgAdminCleanText($input['description'] ?? null, 4000);
            }
            if ($sessionExtraCols) {
                if ($has('volunteersNeeded')) {
                    $f['volunteers_needed'] = pfgProgIntField($input['volunteersNeeded'] ?? 0, 'volunteersNeeded', 0, 1000);
                }
                if ($has('slisNeeded')) {
                    $f['slis_needed'] = pfgProgIntField($input['slisNeeded'] ?? 0, 'slisNeeded', 0, 100);
                }
            }
            $newStatus = $f['status'] ?? strtoupper((string)($existing['status'] ?? 'DRAFT'));
            if (in_array($newStatus, ['CONFIRMED', 'PUBLISHED'], true) && empty($f['venue_id'] ?? ($existing['venue_id'] ?? null))) {
                $warnings[] = 'This session is ' . $newStatus . ' but has no venue assigned.';
            }

            if ($isCreate) {
                $id = uuid4();
                $f['id'] = $id;
                $f['edition_id'] = $editionId;
                $names = array_keys($f);
                $pdo->prepare('INSERT INTO festival_sessions (' . implode(', ', $names) . ') VALUES (:' . implode(', :', $names) . ')')
                    ->execute(array_combine(array_map(static fn($n) => ':' . $n, $names), array_values($f)));
            } else {
                if (!$f) {
                    jsonResponse(['error' => 'No updatable fields were provided.'], 400);
                }
                $sets = [];
                $params = [':id' => $id];
                foreach ($f as $col => $val) {
                    $sets[] = "{$col} = :{$col}";
                    $params[':' . $col] = $val;
                }
                if ($sessionUpdatedCol) {
                    $sets[] = 'updated_at = :upd';
                    $params[':upd'] = nowSql();
                }
                $pdo->prepare('UPDATE festival_sessions SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            }

            $affectedAssignments = 0;
            if ($newStatus === 'CANCELLED' && pfgAdminTableExists($pdo, 'duty_assignments')) {
                $ca = $pdo->prepare("SELECT COUNT(*) FROM duty_assignments WHERE session_id = :sid AND status = 'ASSIGNED'");
                $ca->execute([':sid' => $id]);
                $affectedAssignments = (int)$ca->fetchColumn();
                if ($affectedAssignments > 0) {
                    $warnings[] = "{$affectedAssignments} active duty assignment(s) still reference this cancelled session; review them in Duty Assignments.";
                }
            }

            logAudit([
                'actorUserId'  => $user['id'],
                'action'       => $isCreate ? 'PROGRAMME_SESSION_CREATED' : 'PROGRAMME_SESSION_UPDATED',
                'resourceType' => 'festival_session',
                'resourceId'   => $id,
                'metadata'     => [
                    'fields'         => array_keys($f),
                    'previousStatus' => $existing['status'] ?? null,
                    'newStatus'      => $newStatus,
                ],
            ]);

            $notified = $notifyRequested ? pfgProgNotify($pdo, $user, 'festival_session', $id, (string)$changeSummary, $audience, $id) : 0;
            $assigned = pfgProgAssignedCounts($pdo);
            jsonResponse([
                'session'  => pfgProgFormatSession(pfgProgLoadSession($pdo, $editionId, $id) ?? [], $assigned),
                'warnings' => $warnings,
                'notified' => $notified,
                'affectedAssignments' => $affectedAssignments,
            ], $isCreate ? 201 : 200);
        }

        // ------------------------------ PROGRAMME ITEMS ------------------------------
        $id = null;
        $existing = null;
        if (!$isCreate) {
            $id = trim((string)($input['id'] ?? ''));
            if ($id === '') {
                jsonResponse(['error' => 'Item id is required.'], 400);
            }
            $st = $pdo->prepare('SELECT * FROM programme_items WHERE id = :id AND edition_id = :ed LIMIT 1');
            $st->execute([':id' => $id, ':ed' => $editionId]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Programme item not found.'], 404);
            }
        }
        $has = static fn(string $k): bool => $isCreate || array_key_exists($k, $input);
        $f = [];
        if ($has('title')) {
            $title = pfgAdminCleanText($input['title'] ?? '', 250);
            if ($title === null || strlen($title) < 2) {
                jsonResponse(['error' => 'Item title is required.'], 400);
            }
            $f['title'] = $title;
        }
        if ($has('dayNumber')) {
            $f['day_number'] = pfgProgIntField($input['dayNumber'] ?? null, 'dayNumber', 1, $editionDays);
        }
        if ($has('timeSlot')) {
            $ts = pfgAdminCleanText($input['timeSlot'] ?? '', 80);
            if ($ts === null) {
                jsonResponse(['error' => 'timeSlot is required (e.g. "10:00 – 11:30").'], 400);
            }
            $f['time_slot'] = $ts;
        }
        if ($has('category')) {
            $f['category'] = pfgAdminCleanText($input['category'] ?? 'General', 100) ?? 'General';
        }
        if ($has('description')) {
            $f['description'] = pfgAdminCleanText($input['description'] ?? null, 4000);
        }
        if ($has('venueName')) {
            $f['venue_name'] = pfgAdminCleanText($input['venueName'] ?? null, 200);
        }
        if ($has('speakers')) {
            $f['speakers'] = jsonEncode(pfgAdminStringList($input['speakers'] ?? [], 30, 200));
        }
        if ($has('accessibilityServices')) {
            $f['accessibility_services'] = jsonEncode(pfgAdminStringList($input['accessibilityServices'] ?? [], 20, 120));
        }
        if ($has('displayOrder')) {
            $f['display_order'] = pfgProgIntField($input['displayOrder'] ?? 0, 'displayOrder', 0, 100000);
        }
        if ($has('sessionId')) {
            $sid = $input['sessionId'] ?? null;
            if ($sid === null || $sid === '') {
                $f['session_id'] = null;
            } else {
                if (!is_string($sid) || !pfgAdminIsUuid($sid) || !pfgProgLoadSession($pdo, $editionId, $sid)) {
                    jsonResponse(['error' => 'sessionId does not reference a session of this edition.'], 400);
                }
                $f['session_id'] = $sid;
            }
        }
        if ($has('status')) {
            $f['status'] = pfgProgStatus($input['status'] ?? null, 'DRAFT');
        }

        if ($isCreate) {
            $id = uuid4();
            $f['id'] = $id;
            $f['edition_id'] = $editionId;
            $names = array_keys($f);
            $pdo->prepare('INSERT INTO programme_items (' . implode(', ', $names) . ') VALUES (:' . implode(', :', $names) . ')')
                ->execute(array_combine(array_map(static fn($n) => ':' . $n, $names), array_values($f)));
        } else {
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
            $pdo->prepare('UPDATE programme_items SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }

        $itemSessionId = $f['session_id'] ?? ($existing['session_id'] ?? null);
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => $isCreate ? 'PROGRAMME_ITEM_CREATED' : 'PROGRAMME_ITEM_UPDATED',
            'resourceType' => 'programme_item',
            'resourceId'   => $id,
            'metadata'     => [
                'fields'         => array_keys($f),
                'previousStatus' => $existing['status'] ?? null,
                'newStatus'      => $f['status'] ?? ($existing['status'] ?? null),
            ],
        ]);
        $notified = $notifyRequested ? pfgProgNotify($pdo, $user, 'programme_item', $id, (string)$changeSummary, $audience, $itemSessionId !== null ? (string)$itemSessionId : null) : 0;

        $st = $pdo->prepare('SELECT * FROM programme_items WHERE id = :id');
        $st->execute([':id' => $id]);
        jsonResponse([
            'item'     => pfgProgFormatItem($st->fetch(PDO::FETCH_ASSOC)),
            'warnings' => $warnings,
            'notified' => $notified,
        ], $isCreate ? 201 : 200);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/programme.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process programme request.'], 500);
}
