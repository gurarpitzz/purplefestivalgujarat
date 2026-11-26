<?php
declare(strict_types=1);

/**
 * POST /api/auth/logout
 * Phase 2 Hardened Logout:
 * - Rejects GET requests (405 Method Not Allowed) to prevent logout CSRF and open redirect abuse.
 * - Requires valid session-bound CSRF token before revoking the session.
 * - Strictly validates any optional relative redirect target.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    if (!headers_sent()) {
        header('Allow: POST');
    }
    jsonResponse(['error' => 'Method not allowed. Use POST with a valid CSRF token.'], 405);
}

try {
    $pdo = getDb();
    $user = getCurrentUser($pdo);

    requireCsrfToken($user);

    destroyCurrentSession($pdo);

    if ($user) {
        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'LOGOUT',
            'resourceType' => 'session',
            'resourceId'   => $user['sessionId'] ?? null,
        ]);
    }

    $input = readRequestInput();
    $redirect = isset($input['redirect']) && is_string($input['redirect'])
        ? pfgValidateSafeRedirect($input['redirect'], '/login')
        : '/login';

    jsonResponse([
        'success'    => true,
        'redirectTo' => $redirect,
    ], 200);
} catch (Throwable $e) {
    error_log('POST /api/auth/logout error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to sign out cleanly.'], 500);
}
