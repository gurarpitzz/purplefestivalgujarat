<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Accessibility & Accommodation Coordination API
 * Endpoint: GET / PATCH / POST /api/admin/accommodations (or /api/admin/accommodations.php)
 * Restricted to authorized ADMIN / SUPERADMIN coordinators with accommodations.read / accommodations.manage.
 *
 * FND-40: submitting an empty / omitted `coordinatorNotes` PRESERVES the existing (encrypted) notes. Notes are only
 *         cleared when `clearCoordinatorNotes: true` is sent explicitly.
 * Grouping: requests are grouped per registration for display but every individual request keeps its own id/status
 *         (`requests[]`); a PATCH by request id updates exactly that request.
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

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Most-urgent-first status roll-up for a registration that has several accommodation requests.
 */
function pfgAccommRollupStatus(array $statuses): string
{
    $priority = ['REQUESTED', 'PENDING', 'IN_PROGRESS', 'ASSIGNED', 'ARRANGED', 'COMPLETED', 'APPROVED', 'DECLINED', 'REJECTED'];
    foreach ($priority as $p) {
        if (in_array($p, $statuses, true)) {
            return $p === 'PENDING' ? 'REQUESTED' : $p;
        }
    }
    return $statuses[0] ?? 'REQUESTED';
}

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();

    // Build accommodation type code -> label map
    $typeLabelMap = [];
    foreach (ACCOMMODATION_TYPES_SEED as $seedType) {
        $typeLabelMap[$seedType['code']] = $seedType['label'];
    }
    try {
        $stTypes = $pdo->query('SELECT code, label FROM accommodation_types ORDER BY display_order ASC');
        foreach (($stTypes ? $stTypes->fetchAll(PDO::FETCH_ASSOC) : []) as $t) {
            $typeLabelMap[(string)$t['code']] = (string)$t['label'];
        }
    } catch (Throwable $e) {
    }

    // =========================================================================
    // GET: List & Filter Participant Accommodation Requests
    // =========================================================================
    if ($method === 'GET') {
        requirePermission(PERMISSIONS['ACCOMMODATIONS_READ'], $user);

        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
        $search = strtolower(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')));

        // Load coordinator assignments map
        $assignmentsByReq = [];
        try {
            $stAssign = $pdo->query('SELECT * FROM accommodation_assignments ORDER BY updated_at DESC');
            foreach (($stAssign ? $stAssign->fetchAll(PDO::FETCH_ASSOC) : []) as $a) {
                $reqId = (string)$a['request_id'];
                if (!isset($assignmentsByReq[$reqId])) {
                    $assignmentsByReq[$reqId] = [
                        'coordinatorNotes' => (string)($a['coordinator_notes'] ?? ''),
                        'assignedToUserId' => $a['assigned_to_user_id'] ?? null,
                        'status'           => (string)($a['status'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
        }

        $stmt = $pdo->query("
            SELECT
                a.id,
                a.registration_id,
                a.user_id,
                a.accommodation_code,
                a.selected_types,
                a.free_text_details,
                a.notes,
                a.coordinator_notes,
                a.assigned_to_user_id,
                a.status,
                a.created_at,
                a.updated_at,
                r.registration_code,
                r.full_name,
                r.email,
                r.phone,
                r.category_code,
                r.status AS registration_status,
                r.attending_days
            FROM accommodation_requests a
            INNER JOIN registrations r ON a.registration_id = r.id
            WHERE r.deleted_at IS NULL
            ORDER BY a.updated_at DESC, a.id ASC
        ");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $groupedByReg = [];
        foreach ($rows as $row) {
            $regId = (string)$row['registration_id'];
            $rowId = (string)$row['id'];
            $types = jsonDecode($row['selected_types'] ?? '[]', []);
            if (!is_array($types)) {
                $types = [];
            }
            if (!empty($row['accommodation_code']) && !in_array((string)$row['accommodation_code'], $types, true)) {
                $types[] = (string)$row['accommodation_code'];
            }

            $assign = $assignmentsByReq[$rowId] ?? null;
            $rawCoordNotes = $row['coordinator_notes'] ?? null;
            if (($rawCoordNotes === null || $rawCoordNotes === '') && $assign && $assign['coordinatorNotes'] !== '') {
                $rawCoordNotes = $assign['coordinatorNotes'];
            }
            $coordNotes = pfgDecryptValue($rawCoordNotes !== null && $rawCoordNotes !== '' ? (string)$rawCoordNotes : null, 'data') ?? '';
            $assignedUser = $row['assigned_to_user_id'] ?? ($assign['assignedToUserId'] ?? null);
            $freeText = pfgDecryptValue($row['free_text_details'] ?? $row['notes'] ?? null, 'data');
            $reqStatus = strtoupper(trim((string)($row['status'] ?? 'REQUESTED')));

            $requestItem = [
                'id'               => $rowId,
                'status'           => $reqStatus,
                'selectedTypes'    => array_values($types),
                'freeTextDetails'  => $freeText,
                'coordinatorNotes' => (string)$coordNotes,
                'assignedToUserId' => $assignedUser,
                'createdAt'        => (string)($row['created_at'] ?? ''),
                'updatedAt'        => (string)($row['updated_at'] ?? ''),
            ];

            if (!isset($groupedByReg[$regId])) {
                $groupedByReg[$regId] = [
                    'id'                 => $rowId,
                    'registrationId'     => $regId,
                    'userId'             => (string)($row['user_id'] ?? ''),
                    'registrationCode'   => (string)$row['registration_code'],
                    'fullName'           => (string)$row['full_name'],
                    'email'              => (string)$row['email'],
                    'phone'              => (string)$row['phone'],
                    'categoryCode'       => (string)$row['category_code'],
                    'registrationStatus' => (string)$row['registration_status'],
                    'attendingDays'      => jsonDecode($row['attending_days'] ?? '[]', []),
                    'selectedTypes'      => array_values($types),
                    'freeTextDetails'    => $freeText,
                    'status'             => $reqStatus,
                    'coordinatorNotes'   => (string)$coordNotes,
                    'assignedToUserId'   => $assignedUser,
                    'createdAt'          => (string)($row['created_at'] ?? ''),
                    'updatedAt'          => (string)($row['updated_at'] ?? ''),
                    'requests'           => [$requestItem],
                ];
            } else {
                $g = &$groupedByReg[$regId];
                $g['requests'][] = $requestItem;
                $g['selectedTypes'] = array_values(array_unique(array_merge($g['selectedTypes'], $types)));
                if (($g['freeTextDetails'] === null || $g['freeTextDetails'] === '') && $freeText) {
                    $g['freeTextDetails'] = $freeText;
                }
                if ($g['coordinatorNotes'] === '' && $coordNotes !== '') {
                    $g['coordinatorNotes'] = (string)$coordNotes;
                }
                if (strcmp((string)($row['created_at'] ?? ''), $g['createdAt']) < 0) {
                    $g['createdAt'] = (string)($row['created_at'] ?? '');
                }
                unset($g);
            }
        }

        $items = [];
        foreach ($groupedByReg as $item) {
            $statuses = array_map(static fn(array $r): string => $r['status'], $item['requests']);
            $item['status'] = pfgAccommRollupStatus($statuses);
            $item['requestCount'] = count($item['requests']);

            $labels = [];
            foreach ($item['selectedTypes'] as $code) {
                $labels[] = $typeLabelMap[$code] ?? $code;
            }
            $item['selectedTypeLabels'] = $labels;

            if ($statusFilter !== '' && !in_array($statusFilter, $statuses, true) && $item['status'] !== $statusFilter) {
                continue;
            }

            if ($search !== '') {
                $haystack = strtolower(
                    $item['fullName'] . ' ' .
                    $item['email'] . ' ' .
                    $item['registrationCode'] . ' ' .
                    implode(' ', $labels) . ' ' .
                    ($item['freeTextDetails'] ?? '')
                );
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

            $items[] = $item;
        }

        $total = count($items);
        $paged = isset($_GET['page']) || isset($_GET['pageSize']);
        if ($paged) {
            [$page, $pageSize, $offset] = pfgAdminPageParams(25, 200);
            $items = array_slice($items, $offset, $pageSize);
        } else {
            $page = 1;
            $pageSize = max(1, $total);
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'SENSITIVE_DATA_READ',
            'resourceType' => 'accommodation_request',
            'resourceId'   => null,
            'metadata'     => [
                'endpoint'     => '/api/admin/accommodations',
                'recordCount'  => count($items),
                'statusFilter' => $statusFilter !== '' ? $statusFilter : null,
            ],
        ]);

        jsonResponse([
            'items'      => $items,
            'total'      => $total,
            'pagination' => pfgAdminPaginationMeta($page, $pageSize, $total),
        ]);
    }

    // =========================================================================
    // PATCH / POST: Update Accommodation Request Status & Coordinator Notes
    // =========================================================================
    if ($method === 'PATCH' || $method === 'POST') {
        requirePermission(PERMISSIONS['ACCOMMODATIONS_MANAGE'], $user);
        requireCsrfToken($user);

        $input = readRequestInput();
        $requestId = trim((string)($input['requestId'] ?? $input['id'] ?? $input['registrationId'] ?? ''));
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        // FND-40: empty / whitespace-only / omitted notes mean "leave existing notes untouched".
        $coordinatorNotes = isset($input['coordinatorNotes']) ? trim((string)$input['coordinatorNotes']) : '';
        $notesProvided = $coordinatorNotes !== '';
        $clearNotes = !empty($input['clearCoordinatorNotes']);

        $allowedStatuses = ['REQUESTED', 'IN_PROGRESS', 'ARRANGED', 'DECLINED', 'ASSIGNED', 'COMPLETED'];
        if ($requestId === '' || !in_array($status, $allowedStatuses, true)) {
            jsonResponse(['error' => 'Valid requestId and status (REQUESTED, IN_PROGRESS, ARRANGED, ASSIGNED, COMPLETED, DECLINED) are required.'], 400);
        }
        if (strlen($coordinatorNotes) > 4000) {
            jsonResponse(['error' => 'Coordinator notes are limited to 4000 characters.'], 400);
        }

        // Exact request id -> that single request; otherwise a registration id -> every request of that registration.
        $st = $pdo->prepare('SELECT * FROM accommodation_requests WHERE id = :id LIMIT 1');
        $st->execute([':id' => $requestId]);
        $targets = [];
        $first = $st->fetch(PDO::FETCH_ASSOC);
        if ($first) {
            $targets = [$first];
        } else {
            $stReg = $pdo->prepare('SELECT * FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at ASC');
            $stReg->execute([':rid' => $requestId]);
            $targets = $stReg->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        if (!$targets) {
            jsonResponse(['error' => 'Accommodation request not found.'], 404);
        }

        $targetRegId = (string)$targets[0]['registration_id'];
        $now = nowSql();

        $encryptedNotes = null; // null => preserve
        if ($notesProvided) {
            $encryptedNotes = pfgEncryptValue($coordinatorNotes, 'data');
        }

        $pdo->beginTransaction();
        try {
            foreach ($targets as $existing) {
                $reqId = (string)$existing['id'];

                if ($clearNotes && !$notesProvided) {
                    $up = $pdo->prepare('UPDATE accommodation_requests SET status = :status, coordinator_notes = NULL, assigned_to_user_id = :uid, updated_at = :now WHERE id = :id');
                    $up->execute([':status' => $status, ':uid' => $user['id'], ':now' => $now, ':id' => $reqId]);
                } else {
                    $up = $pdo->prepare('UPDATE accommodation_requests SET status = :status, coordinator_notes = COALESCE(:notes, coordinator_notes), assigned_to_user_id = :uid, updated_at = :now WHERE id = :id');
                    $up->execute([':status' => $status, ':notes' => $encryptedNotes, ':uid' => $user['id'], ':now' => $now, ':id' => $reqId]);
                }

                // Upsert into accommodation_assignments (notes preserved when not provided)
                try {
                    $stAs = $pdo->prepare('SELECT * FROM accommodation_assignments WHERE request_id = :rid LIMIT 1');
                    $stAs->execute([':rid' => $reqId]);
                    $existingAssign = $stAs->fetch(PDO::FETCH_ASSOC);
                    if ($existingAssign) {
                        $notesForAssign = ($clearNotes && !$notesProvided) ? null : ($encryptedNotes ?? $existingAssign['coordinator_notes']);
                        $pdo->prepare('UPDATE accommodation_assignments SET assigned_to_user_id = :uid, coordinator_notes = :notes, status = :status, updated_at = :now WHERE id = :id')
                            ->execute([':uid' => $user['id'], ':notes' => $notesForAssign, ':status' => $status, ':now' => $now, ':id' => $existingAssign['id']]);
                    } else {
                        $pdo->prepare('INSERT INTO accommodation_assignments (id, request_id, assigned_to_user_id, coordinator_notes, status, created_at, updated_at) VALUES (:id, :req_id, :uid, :notes, :status, :now, :now)')
                            ->execute([':id' => uuid4(), ':req_id' => $reqId, ':uid' => $user['id'], ':notes' => $encryptedNotes ?? $existing['coordinator_notes'], ':status' => $status, ':now' => $now]);
                    }
                } catch (Throwable $e) {
                    error_log('/api/admin/accommodations.php assignment upsert warning: ' . $e->getMessage());
                }
            }
            $pdo->commit();
        } catch (Throwable $txe) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $txe;
        }

        // Privacy-safe participant update notification (status only; NEVER includes coordinator notes or diagnoses in email)
        $statusChanged = false;
        foreach ($targets as $t) {
            if ($status !== strtoupper((string)$t['status'])) {
                $statusChanged = true;
                break;
            }
        }
        if ($statusChanged) {
            try {
                $rSt = $pdo->prepare('SELECT full_name, email, registration_code, user_id FROM registrations WHERE id = :rid LIMIT 1');
                $rSt->execute([':rid' => $targetRegId]);
                $regInfo = $rSt->fetch(PDO::FETCH_ASSOC);
                $notifyEntityId = (string)$targets[0]['id'];
                if ($regInfo && !empty($regInfo['email'])) {
                    pfgEnqueueNotification($pdo, [
                        'to'              => (string)$regInfo['email'],
                        'templateCode'    => 'ACCESSIBILITY_ARRANGEMENT_UPDATED',
                        'variables'       => [
                            'fullName'          => (string)$regInfo['full_name'],
                            'registrationCode'  => (string)$regInfo['registration_code'],
                            'arrangementStatus' => $status,
                        ],
                        'recipientUserId' => $regInfo['user_id'] ?? null,
                        'entityType'      => 'accommodation_request',
                        'entityId'        => $notifyEntityId,
                        'idempotencyKey'  => 'accomm-upd:' . $notifyEntityId . ':' . $status . ':' . substr(md5($now), 0, 8),
                        'createInApp'     => false,
                    ]);
                }
            } catch (Throwable $mailErr) {
                error_log('/api/admin/accommodations.php notification enqueue warning: ' . $mailErr->getMessage());
            }
        }

        // Record admin action & audit log (coordinator note CONTENT is never written to logs)
        foreach ($targets as $existing) {
            try {
                $actStmt = $pdo->prepare("
                    INSERT INTO admin_actions (id, admin_user_id, target_user_id, action_type, reason, before_state, after_state, created_at)
                    VALUES (:id, :admin_id, :target_id, :action_type, NULL, :before_state, :after_state, :now)
                ");
                $actStmt->execute([
                    ':id'           => uuid4(),
                    ':admin_id'     => $user['id'],
                    ':target_id'    => $existing['user_id'] ?? null,
                    ':action_type'  => "ACCOMMODATION_{$status}",
                    ':before_state' => jsonEncode(['status' => $existing['status']]),
                    ':after_state'  => jsonEncode(['status' => $status, 'coordinatorNotesUpdated' => $notesProvided || $clearNotes]),
                    ':now'          => $now,
                ]);
            } catch (Throwable $e) {
            }
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'ACCOMMODATION_UPDATED',
            'resourceType' => 'accommodation_request',
            'resourceId'   => (string)$targets[0]['id'],
            'metadata'     => [
                'registrationId'          => $targetRegId,
                'requestIds'              => array_map(static fn(array $t): string => (string)$t['id'], $targets),
                'previousStatus'          => $targets[0]['status'],
                'newStatus'               => $status,
                'coordinatorNotesUpdated' => $notesProvided || $clearNotes,
            ],
        ]);

        // Return the EFFECTIVE (decrypted) notes so the UI reflects what is actually stored.
        $stNow = $pdo->prepare('SELECT coordinator_notes FROM accommodation_requests WHERE id = :id LIMIT 1');
        $stNow->execute([':id' => (string)$targets[0]['id']]);
        $storedNotes = $stNow->fetchColumn();
        $effectiveNotes = pfgDecryptValue(($storedNotes !== false && $storedNotes !== null && $storedNotes !== '') ? (string)$storedNotes : null, 'data') ?? '';

        jsonResponse([
            'request' => [
                'id'               => (string)$targets[0]['id'],
                'registrationId'   => $targetRegId,
                'status'           => $status,
                'coordinatorNotes' => $effectiveNotes,
                'updatedAt'        => $now,
                'updatedCount'     => count($targets),
            ],
            'coordinatorNotes' => $effectiveNotes,
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/accommodations.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process accommodation coordination request.'], 500);
}
