<?php
declare(strict_types=1);

/**
 * POST /api/auth/logout
 * Revokes the current session and clears the `pfg_session` cookie.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

try {
    $pdo = getDb();
    $user = getCurrentUser($pdo);
    destroyCurrentSession($pdo);

    if ($user) {
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'LOGOUT',
            'resourceType' => 'session',
            'resourceId'   => $user['sessionId'] ?? null,
        ]);
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST') === 'GET' && !empty($_GET['redirect'])) {
        header('Location: ' . (string)$_GET['redirect']);
        exit;
    }

    jsonResponse(['success' => true], 200);
} catch (Throwable $e) {
    error_log('POST /api/auth/logout error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to sign out cleanly.'], 500);
}
