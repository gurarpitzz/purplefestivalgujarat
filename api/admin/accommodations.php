<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Accessibility & Accommodation Coordination API
 * Endpoint: GET / PATCH / POST /api/admin/accommodations (or /api/admin/accommodations.php)
 * Restricted to authorized ADMIN / SUPERADMIN coordinators with accommodations.read / accommodations.manage.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

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

        // Gather accommodation requests across accommodation_requests and registration_accommodations
        $groupedByReg = [];

        foreach (['accommodation_requests', 'registration_accommodations'] as $tableName) {
            try {
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
                    FROM {$tableName} a
                    INNER JOIN registrations r ON a.registration_id = r.id
                    WHERE r.deleted_at IS NULL
                    ORDER BY a.updated_at DESC
                ");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

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

                    $assign = $assignmentsByReq[$rowId] ?? $assignmentsByReq[$regId] ?? null;
                    $rawCoordNotes = $assign['coordinatorNotes']
                        ?? $row['coordinator_notes']
                        ?? '';
                    $coordNotes = pfgDecryptValue($rawCoordNotes !== '' ? (string)$rawCoordNotes : null, 'data') ?? '';
                    $assignedUser = $assign['assignedToUserId']
                        ?? $row['assigned_to_user_id']
                        ?? null;
                    $freeText = pfgDecryptValue($row['free_text_details'] ?? $row['notes'] ?? null, 'data');

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
                            'selectedTypes'      => $types,
                            'freeTextDetails'    => $freeText,
                            'status'             => strtoupper(trim((string)($row['status'] ?? 'REQUESTED'))),
                            'coordinatorNotes'   => (string)$coordNotes,
                            'assignedToUserId'   => $assignedUser,
                            'createdAt'          => (string)($row['created_at'] ?? ''),
                            'updatedAt'          => (string)($row['updated_at'] ?? ''),
                        ];
                    } else {
                        $groupedByReg[$regId]['selectedTypes'] = array_values(array_unique(array_merge(
                            $groupedByReg[$regId]['selectedTypes'],
                            $types
                        )));
                        if (($groupedByReg[$regId]['freeTextDetails'] === null || $groupedByReg[$regId]['freeTextDetails'] === '') && $freeText) {
                            $groupedByReg[$regId]['freeTextDetails'] = $freeText;
                        }
                        if ($groupedByReg[$regId]['coordinatorNotes'] === '' && $coordNotes !== '') {
                            $groupedByReg[$regId]['coordinatorNotes'] = (string)$coordNotes;
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        $items = [];
        foreach ($groupedByReg as $item) {
            $codes = $item['selectedTypes'];
            $labels = [];
            foreach ($codes as $code) {
                $labels[] = $typeLabelMap[$code] ?? $code;
            }
            $item['selectedTypeLabels'] = $labels;

            if ($statusFilter !== '' && $item['status'] !== $statusFilter) {
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
            'items' => $items,
            'total' => count($items),
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
        $coordinatorNotes = isset($input['coordinatorNotes']) ? trim((string)$input['coordinatorNotes']) : null;

        $allowedStatuses = ['REQUESTED', 'IN_PROGRESS', 'ARRANGED', 'DECLINED', 'ASSIGNED', 'COMPLETED'];
        if ($requestId === '' || !in_array($status, $allowedStatuses, true)) {
            jsonResponse(['error' => 'Valid requestId and status (REQUESTED, IN_PROGRESS, ARRANGED, ASSIGNED, COMPLETED, DECLINED) are required.'], 400);
        }

        // Locate existing request in either accommodation_requests or registration_accommodations
        $existing = null;
        foreach (['accommodation_requests', 'registration_accommodations'] as $tbl) {
            try {
                $st = $pdo->prepare("SELECT * FROM {$tbl} WHERE id = :id OR registration_id = :rid LIMIT 1");
                $st->execute([':id' => $requestId, ':rid' => $requestId]);
                $found = $st->fetch(PDO::FETCH_ASSOC);
                if ($found) {
                    $existing = $found;
                    break;
                }
            } catch (Throwable $e) {
            }
        }

        if (!$existing) {
            jsonResponse(['error' => 'Accommodation request not found.'], 404);
        }

        $targetRegId = (string)$existing['registration_id'];
        $targetReqId = (string)$existing['id'];
        $now = nowSql();
        $encryptedNotes = ($coordinatorNotes !== null && $coordinatorNotes !== '')
            ? pfgEncryptValue($coordinatorNotes, 'data')
            : $coordinatorNotes;

        // Update both accommodation_requests and registration_accommodations so tables stay in sync
        foreach (['accommodation_requests', 'registration_accommodations'] as $tbl) {
            try {
                $up = $pdo->prepare("
                    UPDATE {$tbl}
                    SET status = :status,
                        coordinator_notes = COALESCE(:notes, coordinator_notes),
                        assigned_to_user_id = :uid,
                        updated_at = :now
                    WHERE id = :id OR registration_id = :rid
                ");
                $up->execute([
                    ':status' => $status,
                    ':notes'  => $encryptedNotes,
                    ':uid'    => $user['id'],
                    ':now'    => $now,
                    ':id'     => $targetReqId,
                    ':rid'    => $targetRegId,
                ]);
            } catch (Throwable $e) {
            }
        }

        // Upsert into accommodation_assignments
        try {
            $stAs = $pdo->prepare('SELECT * FROM accommodation_assignments WHERE request_id = :rid LIMIT 1');
            $stAs->execute([':rid' => $targetReqId]);
            $existingAssign = $stAs->fetch(PDO::FETCH_ASSOC);

            if ($existingAssign) {
                $upAs = $pdo->prepare("
                    UPDATE accommodation_assignments
                    SET assigned_to_user_id = :uid,
                        coordinator_notes = :notes,
                        status = :status,
                        updated_at = :now
                    WHERE id = :id
                ");
                $upAs->execute([
                    ':uid'    => $user['id'],
                    ':notes'  => $encryptedNotes ?? $existingAssign['coordinator_notes'],
                    ':status' => $status,
                    ':now'    => $now,
                    ':id'     => $existingAssign['id'],
                ]);
            } else {
                $insAs = $pdo->prepare("
                    INSERT INTO accommodation_assignments (id, request_id, assigned_to_user_id, coordinator_notes, status, created_at, updated_at)
                    VALUES (:id, :req_id, :uid, :notes, :status, :now, :now)
                ");
                $insAs->execute([
                    ':id'     => uuid4(),
                    ':req_id' => $targetReqId,
                    ':uid'    => $user['id'],
                    ':notes'  => $encryptedNotes,
                    ':status' => $status,
                    ':now'    => $now,
                ]);
            }
        } catch (Throwable $e) {
        }

        // Record admin action & audit log
        try {
            $actStmt = $pdo->prepare("
                INSERT INTO admin_actions (id, admin_user_id, target_user_id, action_type, reason, before_state, after_state, created_at)
                VALUES (:id, :admin_id, :target_id, :action_type, :reason, :before_state, :after_state, :now)
            ");
            $actStmt->execute([
                ':id'           => uuid4(),
                ':admin_id'     => $user['id'],
                ':target_id'    => $existing['user_id'] ?? null,
                ':action_type'  => "ACCOMMODATION_{$status}",
                ':reason'       => $coordinatorNotes,
                ':before_state' => jsonEncode(['status' => $existing['status']]),
                ':after_state'  => jsonEncode(['status' => $status, 'coordinatorNotes' => $coordinatorNotes]),
                ':now'          => $now,
            ]);
        } catch (Throwable $e) {
        }

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'ACCOMMODATION_UPDATED',
            'resourceType' => 'accommodation_request',
            'resourceId'   => $targetReqId,
            'metadata'     => [
                'registrationId' => $targetRegId,
                'previousStatus' => $existing['status'],
                'newStatus'      => $status,
            ],
        ]);

        jsonResponse([
            'request' => [
                'id'               => $targetReqId,
                'registrationId'   => $targetRegId,
                'status'           => $status,
                'coordinatorNotes' => $coordinatorNotes ?? ($existing['coordinator_notes'] ?? ''),
                'updatedAt'        => $now,
            ],
            'coordinatorNotes' => $coordinatorNotes ?? ($existing['coordinator_notes'] ?? ''),
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/accommodations.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process accommodation coordination request.'], 500);
}
