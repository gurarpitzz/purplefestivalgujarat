<?php
declare(strict_types=1);

/**
 * GET & POST /api/admin/notifications
 * Phase 4E / 4N Backend Notification Inspection, Retry & Intentional Resend API.
 *
 * Security Guarantees:
 * - Strictly restricted to authenticated ADMIN / SUPERADMIN with mandatory MFA verified (D-20).
 * - GET requires `registrations.read.all` (returns redacted queue metadata & delivery attempt logs;
 *   never exposes `ephemeral_secret_enc`, API keys, or raw tokens).
 * - POST (`action=retry` or `action=resend`) requires `registrations.write` + session-bound CSRF token,
 *   records immutable `audit_logs` + `admin_actions`, and NEVER alters application approval status.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/notification-service.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();

    if ($method === 'GET') {
        requirePermission(PERMISSIONS['REGISTRATIONS_READ_ALL'], $user);

        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
        $eventFilter = trim((string)($_GET['eventType'] ?? ''));
        $entityId = trim((string)($_GET['entityId'] ?? ''));
        $queueId = trim((string)($_GET['id'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 25)));
        $offset = ($page - 1) * $pageSize;

        if ($queueId !== '') {
            $st = $pdo->prepare('
                SELECT id, template_code, event_type, channel, entity_type, entity_id,
                       recipient_user_id, recipient_email, subject, status, priority,
                       provider, provider_message_id, last_provider_event, last_provider_event_at,
                       idempotency_key, resent_from_id, attempts, max_attempts,
                       next_retry_at, last_attempt_at, sent_at, provider_accepted_at,
                       delivered_at, failed_at, bounced_at, error_category, last_error,
                       created_at, updated_at
                FROM notification_queue
                WHERE id = :id
                LIMIT 1
            ');
            $st->execute([':id' => $queueId]);
            $item = $st->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                jsonResponse(['error' => 'Notification record not found.'], 404);
            }

            $logStmt = $pdo->prepare('
                SELECT id, attempt_number, provider, provider_message_id, event_type,
                       provider_event_id, status, http_status, error_category, response_details, created_at
                FROM notification_delivery_logs
                WHERE job_id = :jid
                ORDER BY created_at DESC
            ');
            $logStmt->execute([':jid' => $queueId]);
            $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            jsonResponse(['item' => $item, 'attempts' => $logs]);
        }

        $where = ['1 = 1'];
        $params = [];
        if ($statusFilter !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $statusFilter;
        }
        if ($eventFilter !== '') {
            $where[] = 'event_type = :etype';
            $params[':etype'] = $eventFilter;
        }
        if ($entityId !== '') {
            $where[] = 'entity_id = :ent_id';
            $params[':ent_id'] = $entityId;
        }

        $whereSql = implode(' AND ', $where);
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM notification_queue WHERE {$whereSql}");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $listStmt = $pdo->prepare("
            SELECT id, template_code, event_type, channel, entity_type, entity_id,
                   recipient_user_id, recipient_email, subject, status, priority,
                   provider, provider_message_id, last_provider_event, resent_from_id,
                   attempts, max_attempts, next_retry_at, sent_at, provider_accepted_at,
                   delivered_at, failed_at, bounced_at, error_category, last_error,
                   created_at, updated_at
            FROM notification_queue
            WHERE {$whereSql}
            ORDER BY created_at DESC
            LIMIT {$pageSize} OFFSET {$offset}
        ");
        $listStmt->execute($params);
        $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        jsonResponse([
            'items'      => $items,
            'pagination' => [
                'page'       => $page,
                'pageSize'   => $pageSize,
                'total'      => $total,
                'totalPages' => max(1, (int)ceil($total / $pageSize)),
            ],
        ]);
    }

    if ($method === 'POST') {
        requirePermission(PERMISSIONS['REGISTRATIONS_WRITE'], $user);
        requireCsrfToken($user);

        $input = readRequestInput();
        $action = strtolower(trim((string)($input['action'] ?? '')));
        $queueId = trim((string)($input['queueId'] ?? $input['id'] ?? ''));
        $reason = trim((string)($input['reason'] ?? 'Authorized administrative notification recovery'));

        if ($queueId === '' || !in_array($action, ['retry', 'resend'], true)) {
            jsonResponse(['error' => 'Valid queueId and action (retry or resend) are required.'], 400);
        }

        try {
            if ($action === 'retry') {
                $result = pfgAdminRetryNotification($pdo, $queueId, (string)$user['id'], $reason);
                logAudit([
                    'actorUserId'  => $user['id'],
                    'action'       => 'NOTIFICATION_RETRIED',
                    'resourceType' => 'notification_queue',
                    'resourceId'   => $queueId,
                    'metadata'     => ['reason' => $reason, 'previousStatus' => $result['previousStatus']],
                ]);
                jsonResponse(['success' => true, 'result' => $result], 200);
            } else {
                $result = pfgAdminResendNotification($pdo, $queueId, (string)$user['id'], $reason);
                logAudit([
                    'actorUserId'  => $user['id'],
                    'action'       => 'NOTIFICATION_RESENT',
                    'resourceType' => 'notification_queue',
                    'resourceId'   => (string)$result['newQueueId'],
                    'metadata'     => ['resentFromId' => $queueId, 'reason' => $reason],
                ]);
                jsonResponse(['success' => true, 'result' => $result], 201);
            }
        } catch (InvalidArgumentException $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            jsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/notifications.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process notification administration request.'], 500);
}

