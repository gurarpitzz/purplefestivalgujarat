<?php
declare(strict_types=1);

/**
 * GET / PATCH /api/account/notifications (api/account/notifications.php)
 * Phase 10 — Authenticated Participant In-App Notifications Inbox API.
 *
 * Security & Ownership Invariants (§32.5):
 * - Requires authenticated user (`requireAuth()` + `enforceStaffMfaGate()`).
 * - Strictly scopes EVERY SQL query with `WHERE user_id = :uid`.
 * - `PATCH` requires valid session-bound CSRF token (`requireCsrfToken()`).
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireAuth();
    enforceStaffMfaGate($user);

    $pdo = getDb();
    $currentUserId = (string)$user['id'];

    if ($method === 'GET') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $unreadOnlyRaw = strtolower(trim((string)($_GET['unreadOnly'] ?? $_GET['unread_only'] ?? '')));
        $unreadOnly = in_array($unreadOnlyRaw, ['1', 'true', 'yes'], true);

        $unreadStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM in_app_notifications
             WHERE user_id = :uid AND is_read = 0 AND read_at IS NULL'
        );
        $unreadStmt->execute([':uid' => $currentUserId]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        $whereSql = 'WHERE user_id = :uid';
        if ($unreadOnly) {
            $whereSql .= ' AND is_read = 0 AND read_at IS NULL';
        }

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM in_app_notifications {$whereSql}");
        $totalStmt->execute([':uid' => $currentUserId]);
        $total = (int)$totalStmt->fetchColumn();

        $listStmt = $pdo->prepare(
            "SELECT id, event_type, title, message, action_url, entity_type, entity_id, is_read, read_at, created_at
             FROM in_app_notifications
             {$whereSql}
             ORDER BY created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute([':uid' => $currentUserId]);
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $notifications = [];
        foreach ($rows as $row) {
            $safeUrl = null;
            if (isset($row['action_url']) && trim((string)$row['action_url']) !== '') {
                $v = pfgValidateSafeUrl((string)$row['action_url'], true);
                if ($v !== null && $v !== '') {
                    $safeUrl = $v;
                }
            }
            $isRead = !empty($row['is_read']) || !empty($row['read_at']);
            $notifications[] = [
                'id'         => (string)$row['id'],
                'eventType'  => (string)$row['event_type'],
                'title'      => (string)$row['title'],
                'message'    => (string)$row['message'],
                'actionUrl'  => $safeUrl,
                'entityType' => $row['entity_type'] !== null ? (string)$row['entity_type'] : null,
                'entityId'   => $row['entity_id'] !== null ? (string)$row['entity_id'] : null,
                'isRead'     => $isRead,
                'readAt'     => $row['read_at'] !== null ? (string)$row['read_at'] : null,
                'createdAt'  => (string)$row['created_at'],
            ];
        }

        $totalPages = max(1, (int)ceil($total / $limit));

        jsonResponse([
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'pagination'    => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
        ], 200);
    }

    if ($method === 'PATCH') {
        requireCsrfToken($user);

        $body = readRequestInput();
        $markAllRead = !empty($body['markAllRead']) || !empty($body['mark_all_read']);
        $notificationId = trim((string)($body['notificationId'] ?? $body['notification_id'] ?? ''));
        $now = nowSql();

        if ($markAllRead) {
            $upd = $pdo->prepare(
                'UPDATE in_app_notifications
                 SET is_read = 1, read_at = COALESCE(read_at, :now)
                 WHERE user_id = :uid AND (is_read = 0 OR read_at IS NULL)'
            );
            $upd->execute([
                ':now' => $now,
                ':uid' => $currentUserId,
            ]);

            jsonResponse([
                'success'      => true,
                'updatedCount' => $upd->rowCount(),
                'unreadCount'  => 0,
                'message'      => 'All notifications marked as read.',
            ], 200);
        }

        if ($notificationId === '' || strlen($notificationId) > 64) {
            jsonResponse(['error' => 'Provide a valid notificationId or set markAllRead to true.'], 400);
        }

        // Verify ownership strictly before updating
        $checkStmt = $pdo->prepare(
            'SELECT id, read_at FROM in_app_notifications
             WHERE id = :id AND user_id = :uid
             LIMIT 1'
        );
        $checkStmt->execute([
            ':id'  => $notificationId,
            ':uid' => $currentUserId,
        ]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['error' => 'Notification not found.'], 404);
        }

        $effectiveReadAt = !empty($existing['read_at']) ? (string)$existing['read_at'] : $now;
        $upd = $pdo->prepare(
            'UPDATE in_app_notifications
             SET is_read = 1, read_at = COALESCE(read_at, :now)
             WHERE id = :id AND user_id = :uid'
        );
        $upd->execute([
            ':now' => $now,
            ':id'  => $notificationId,
            ':uid' => $currentUserId,
        ]);

        $unreadStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM in_app_notifications
             WHERE user_id = :uid AND is_read = 0 AND read_at IS NULL'
        );
        $unreadStmt->execute([':uid' => $currentUserId]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        jsonResponse([
            'success'        => true,
            'notificationId' => $notificationId,
            'readAt'         => $effectiveReadAt,
            'unreadCount'    => $unreadCount,
            'message'        => 'Notification marked as read.',
        ], 200);
    }

    jsonResponse(['error' => 'Method not allowed. Use GET or PATCH.'], 405);
} catch (Throwable $err) {
    error_log('[/api/account/notifications] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Unable to process notifications right now.'], 500);
}
