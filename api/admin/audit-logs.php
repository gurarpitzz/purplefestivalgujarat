<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Immutable Security & Operations Audit Trail API
 * Endpoint: GET /api/admin/audit-logs (or /api/admin/audit-logs.php)
 * Phase 2 Hardened:
 * - Requires AUDIT_LOGS_READ permission (FND-03) and mandatory MFA gate (D-20).
 * - True SQL COUNT(*) + LIMIT/OFFSET pagination (removes 500-row cap, FND-28).
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
    requirePermission(PERMISSIONS['AUDIT_LOGS_READ'], $user);
    $pdo = getDb();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['pageSize'] ?? 30)));
    $actionFilter = trim((string)($_GET['action'] ?? ''));
    $resourceTypeFilter = trim((string)($_GET['resourceType'] ?? ''));
    $search = strtolower(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')));

    $where = [];
    $params = [];

    if ($actionFilter !== '') {
        $where[] = 'LOWER(a.action) LIKE :action';
        $params[':action'] = '%' . strtolower($actionFilter) . '%';
    }
    if ($resourceTypeFilter !== '') {
        $where[] = 'LOWER(a.resource_type) = :rtype';
        $params[':rtype'] = strtolower($resourceTypeFilter);
    }
    if ($search !== '') {
        $where[] = '(LOWER(a.action) LIKE :q OR LOWER(a.resource_type) LIKE :q OR LOWER(COALESCE(a.resource_id, \'\')) LIKE :q OR LOWER(COALESCE(u.email, \'\')) LIKE :q OR LOWER(COALESCE(u.full_name, \'\')) LIKE :q)';
        $params[':q'] = '%' . $search . '%';
    }

    $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // True total count across full audit trail (no artificial 500-row truncation)
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM audit_logs a
        LEFT JOIN users u ON a.actor_user_id = u.id
        {$whereSql}
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $pageSize));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $pageSize;

    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.action,
            a.resource_type,
            a.resource_id,
            a.ip_address,
            a.metadata,
            a.created_at,
            a.actor_user_id,
            u.full_name AS actor_name,
            u.email AS actor_email
        FROM audit_logs a
        LEFT JOIN users u ON a.actor_user_id = u.id
        {$whereSql}
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT {$pageSize} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'id'           => (string)$row['id'],
            'action'       => (string)$row['action'],
            'resourceType' => (string)$row['resource_type'],
            'resourceId'   => $row['resource_id'] ?? null,
            'ipAddress'    => $row['ip_address'] ?? null,
            'metadata'     => jsonDecode($row['metadata'] ?? null, null),
            'createdAt'    => (string)($row['created_at'] ?? ''),
            'actorUserId'  => $row['actor_user_id'] ?? null,
            'actorName'    => $row['actor_name'] ?? null,
            'actorEmail'   => $row['actor_email'] ?? null,
        ];
    }

    jsonResponse([
        'items'      => $items,
        'pagination' => [
            'page'       => $page,
            'pageSize'   => $pageSize,
            'total'      => $total,
            'totalPages' => $totalPages,
        ],
    ]);
} catch (Throwable $e) {
    error_log('GET /api/admin/audit-logs.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to load audit logs.'], 500);
}
