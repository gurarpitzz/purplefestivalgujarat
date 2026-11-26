<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Announcements API (Phase 12)
 * Endpoint: GET / POST / PATCH /api/admin/announcements
 *
 * Authorization: ADMIN (`cms.manage` or `communications.manage`) / SUPERADMIN + verified MFA (D-20) + CSRF on writes.
 * Announcements: severity INFO | WARNING | CRITICAL, status DRAFT | PUBLISHED | ARCHIVED.
 * Optional `broadcast` queues FESTIVAL_IMPORTANT_INFO / FESTIVAL_REMINDER emails through the notification outbox
 * (never bypasses MAIL_DRIVER or the queue worker). Only PUBLISHED announcements can be broadcast, one idempotent
 * send per announcement + template + audience unless `broadcast.resend` is explicitly true.
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

const PFG_ANNOUNCE_SEVERITIES = ['INFO', 'WARNING', 'CRITICAL'];
const PFG_ANNOUNCE_STATUSES = ['DRAFT', 'PUBLISHED', 'ARCHIVED'];
const PFG_ANNOUNCE_AUDIENCES = ['ALL', 'ATTENDEE', 'VOLUNTEER', 'SLI'];
const PFG_ANNOUNCE_BROADCAST_CAP = 5000;

function pfgAnnounceFormat(array $a): array
{
    return [
        'id'          => (string)$a['id'],
        'title'       => (string)$a['title'],
        'message'     => (string)$a['message'],
        'severity'    => strtoupper((string)$a['severity']),
        'status'      => strtoupper((string)$a['status']),
        'publishedAt' => $a['published_at'] ?? null,
        'expiresAt'   => $a['expires_at'] ?? null,
        'createdBy'   => $a['created_by'] ?? null,
        'createdAt'   => (string)($a['created_at'] ?? ''),
    ];
}

function pfgAnnounceNormalizeDateTime($value, string $field): ?string
{
    if ($value === null || trim((string)$value) === '') {
        return null;
    }
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?/', trim((string)$value), $m) || !pfgAdminValidDate($m[1])) {
        jsonResponse(['error' => "{$field} must be a valid date-time (YYYY-MM-DD HH:MM)."], 400);
    }
    return $m[1] . ' ' . $m[2] . ($m[3] ?? ':00');
}

/**
 * Queues the broadcast. Returns ['queued' => n, 'deduped' => n, 'failed' => n, 'audience' => ..., 'templateCode' => ...].
 */
function pfgAnnounceBroadcast(PDO $pdo, array $user, array $announcement, array $broadcast): array
{
    $template = strtoupper(trim((string)($broadcast['templateCode'] ?? 'FESTIVAL_IMPORTANT_INFO')));
    if (!in_array($template, ['FESTIVAL_IMPORTANT_INFO', 'FESTIVAL_REMINDER'], true)) {
        jsonResponse(['error' => 'broadcast.templateCode must be FESTIVAL_IMPORTANT_INFO or FESTIVAL_REMINDER.'], 400);
    }
    $audience = strtoupper(trim((string)($broadcast['audience'] ?? 'ALL')));
    if (!in_array($audience, PFG_ANNOUNCE_AUDIENCES, true)) {
        jsonResponse(['error' => 'broadcast.audience must be one of: ' . implode(', ', PFG_ANNOUNCE_AUDIENCES) . '.'], 400);
    }
    if (strtoupper((string)$announcement['status']) !== 'PUBLISHED') {
        jsonResponse(['error' => 'Only PUBLISHED announcements can be broadcast.'], 400);
    }

    $where = ["r.deleted_at IS NULL", "r.status IN ('CONFIRMED', 'APPROVED')", "r.email <> ''"];
    if ($audience === 'ATTENDEE') {
        $where[] = "r.category_code NOT IN ('VOLUNTEER', 'SLI')";
    } elseif ($audience === 'VOLUNTEER' || $audience === 'SLI') {
        $where[] = 'r.category_code = :cat';
    }
    $st = $pdo->prepare('SELECT r.id, r.email, r.full_name, r.registration_code, r.user_id, r.category_code FROM registrations r WHERE '
        . implode(' AND ', $where) . ' ORDER BY r.created_at ASC LIMIT ' . PFG_ANNOUNCE_BROADCAST_CAP);
    $st->execute($audience === 'VOLUNTEER' || $audience === 'SLI' ? [':cat' => $audience] : []);

    $stamp = !empty($broadcast['resend']) ? substr(md5(nowSql() . random_bytes(4)), 0, 8) : 'once';
    $queued = 0;
    $deduped = 0;
    $failed = 0;
    $seen = [];
    foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
        $emailKey = strtolower((string)$r['email']);
        if (isset($seen[$emailKey])) {
            continue;
        }
        $seen[$emailKey] = true;
        $vars = ['fullName' => (string)$r['full_name'], 'registrationCode' => (string)$r['registration_code']];
        if ($template === 'FESTIVAL_IMPORTANT_INFO') {
            $vars['announcementTitle'] = (string)$announcement['title'];
            $vars['announcementBody'] = (string)$announcement['message'];
        } else {
            $vars['categoryName'] = strtoupper((string)$r['category_code']);
            $vars['reminderDetails'] = (string)$announcement['message'];
        }
        try {
            $q = pfgEnqueueNotification($pdo, [
                'to'              => (string)$r['email'],
                'templateCode'    => $template,
                'variables'       => $vars,
                'recipientUserId' => $r['user_id'] ?? null,
                'entityType'      => 'announcement',
                'entityId'        => (string)$announcement['id'],
                'idempotencyKey'  => 'announce:' . $announcement['id'] . ':' . $template . ':' . $audience . ':' . $stamp . ':' . substr((string)$r['id'], 0, 8),
                'priority'        => strtoupper((string)$announcement['severity']) === 'CRITICAL' ? 3 : 5,
                'createInApp'     => !empty($r['user_id']),
                'inAppTitle'      => mb_substr((string)$announcement['title'], 0, 190, 'UTF-8'),
                'inAppMessage'    => mb_substr((string)$announcement['message'], 0, 500, 'UTF-8'),
            ]);
            if (!empty($q['deduped'])) {
                $deduped++;
            } else {
                $queued++;
            }
        } catch (Throwable $e) {
            $failed++;
            error_log('/api/admin/announcements.php broadcast warning: ' . $e->getMessage());
        }
    }
    logAudit([
        'actorUserId'  => $user['id'],
        'action'       => 'ANNOUNCEMENT_BROADCAST_QUEUED',
        'resourceType' => 'announcement',
        'resourceId'   => (string)$announcement['id'],
        'metadata'     => ['templateCode' => $template, 'audience' => $audience, 'queued' => $queued, 'deduped' => $deduped, 'failed' => $failed],
    ]);
    return ['queued' => $queued, 'deduped' => $deduped, 'failed' => $failed, 'audience' => $audience, 'templateCode' => $template];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    pfgAdminRequireCommunicationsAccess($user);
    $pdo = getDb();
    $editionId = pfgAdminEditionId($pdo);

    if ($method === 'GET') {
        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
        $where = ['edition_id = :ed'];
        $params = [':ed' => $editionId];
        if ($statusFilter !== '') {
            if (!in_array($statusFilter, PFG_ANNOUNCE_STATUSES, true)) {
                jsonResponse(['error' => 'Invalid status filter.'], 400);
            }
            $where[] = 'UPPER(status) = :st';
            $params[':st'] = $statusFilter;
        }
        [$page, $pageSize, $offset] = pfgAdminPageParams(50, 200);
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM announcements WHERE ' . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        $st = $pdo->prepare('SELECT * FROM announcements WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC, id ASC LIMIT {$pageSize} OFFSET {$offset}");
        $st->execute($params);
        jsonResponse([
            'items'      => array_map('pfgAnnounceFormat', $st->fetchAll(PDO::FETCH_ASSOC) ?: []),
            'severities' => PFG_ANNOUNCE_SEVERITIES,
            'statuses'   => PFG_ANNOUNCE_STATUSES,
            'pagination' => pfgAdminPaginationMeta($page, $pageSize, $total),
        ]);
    }

    if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
        requireCsrfToken($user);
        $input = readRequestInput();
        $isCreate = $method === 'POST';
        $existing = null;
        $id = null;
        if (!$isCreate) {
            $id = trim((string)($input['id'] ?? ''));
            if (!pfgAdminIsUuid($id)) {
                jsonResponse(['error' => 'A valid announcement id is required.'], 400);
            }
            $st = $pdo->prepare('SELECT * FROM announcements WHERE id = :id AND edition_id = :ed LIMIT 1');
            $st->execute([':id' => $id, ':ed' => $editionId]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Announcement not found.'], 404);
            }
        }
        $has = static fn(string $k): bool => $isCreate || array_key_exists($k, $input);
        $f = [];

        if ($has('title')) {
            $t = pfgAdminCleanText($input['title'] ?? '', 200);
            if ($t === null || strlen($t) < 3) {
                jsonResponse(['error' => 'title is required (min 3 characters).'], 400);
            }
            $f['title'] = $t;
        }
        if ($has('message')) {
            $m = pfgAdminCleanText($input['message'] ?? '', 5000);
            if ($m === null || strlen($m) < 3) {
                jsonResponse(['error' => 'message is required (min 3 characters).'], 400);
            }
            $f['message'] = $m;
        }
        if ($has('severity')) {
            $sev = strtoupper(trim((string)($input['severity'] ?? 'INFO')));
            if (!in_array($sev, PFG_ANNOUNCE_SEVERITIES, true)) {
                jsonResponse(['error' => 'severity must be one of: ' . implode(', ', PFG_ANNOUNCE_SEVERITIES) . '.'], 400);
            }
            $f['severity'] = $sev;
        }
        if ($has('status')) {
            $stt = strtoupper(trim((string)($input['status'] ?? 'DRAFT')));
            if (!in_array($stt, PFG_ANNOUNCE_STATUSES, true)) {
                jsonResponse(['error' => 'status must be one of: ' . implode(', ', PFG_ANNOUNCE_STATUSES) . '.'], 400);
            }
            $f['status'] = $stt;
        }
        if (array_key_exists('expiresAt', $input)) {
            $f['expires_at'] = pfgAnnounceNormalizeDateTime($input['expiresAt'], 'expiresAt');
        }
        if (array_key_exists('publishedAt', $input)) {
            $f['published_at'] = pfgAnnounceNormalizeDateTime($input['publishedAt'], 'publishedAt');
        }
        $newStatus = $f['status'] ?? strtoupper((string)($existing['status'] ?? 'DRAFT'));
        if ($newStatus === 'PUBLISHED' && !array_key_exists('published_at', $f) && ($isCreate || strtoupper((string)$existing['status']) !== 'PUBLISHED' || empty($existing['published_at']))) {
            $f['published_at'] = nowSql();
        }
        $pub = $f['published_at'] ?? ($existing['published_at'] ?? null);
        $exp = $f['expires_at'] ?? ($existing['expires_at'] ?? null);
        if ($pub && $exp && strtotime((string)$exp) <= strtotime((string)$pub)) {
            jsonResponse(['error' => 'expiresAt must be after publishedAt.'], 400);
        }

        // Validate the broadcast block BEFORE mutating anything.
        $broadcastReq = isset($input['broadcast']) && is_array($input['broadcast']) ? $input['broadcast'] : null;
        if ($broadcastReq !== null && $newStatus !== 'PUBLISHED') {
            jsonResponse(['error' => 'Only PUBLISHED announcements can be broadcast.'], 400);
        }

        if ($isCreate) {
            $id = uuid4();
            $f['id'] = $id;
            $f['edition_id'] = $editionId;
            $f['created_by'] = $user['id'];
            $names = array_keys($f);
            $pdo->prepare('INSERT INTO announcements (' . implode(', ', $names) . ') VALUES (:' . implode(', :', $names) . ')')
                ->execute(array_combine(array_map(static fn($n) => ':' . $n, $names), array_values($f)));
        } else {
            if (!$f && $broadcastReq === null) {
                jsonResponse(['error' => 'No updatable fields were provided.'], 400);
            }
            if ($f) {
                $sets = [];
                $params = [':id' => $id];
                foreach ($f as $col => $val) {
                    $sets[] = "{$col} = :{$col}";
                    $params[':' . $col] = $val;
                }
                $pdo->prepare('UPDATE announcements SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            }
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => $isCreate ? 'ANNOUNCEMENT_CREATED' : 'ANNOUNCEMENT_UPDATED',
            'resourceType' => 'announcement',
            'resourceId'   => $id,
            'metadata'     => ['fields' => array_keys($f), 'previousStatus' => $existing['status'] ?? null, 'newStatus' => $newStatus],
        ]);

        $st = $pdo->prepare('SELECT * FROM announcements WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        $broadcastResult = $broadcastReq !== null ? pfgAnnounceBroadcast($pdo, $user, $row, $broadcastReq) : null;
        jsonResponse(['announcement' => pfgAnnounceFormat($row), 'broadcast' => $broadcastResult], $isCreate ? 201 : 200);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/announcements.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process announcement request.'], 500);
}
