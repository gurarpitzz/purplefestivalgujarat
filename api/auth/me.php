<?php
declare(strict_types=1);

/**
 * GET /api/auth/me
 * Returns the currently authenticated staff user profile, roles, permissions, and session-bound CSRF token.
 * Non-staff / legacy participant sessions are treated as unauthenticated under CAD-17.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

try {
    $user = getCurrentUser();
    if (is_array($user)) {
        $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
        $isStaff = in_array('SUPERADMIN', $roles, true)
            || in_array('ADMIN', $roles, true)
            || in_array('RGST', $roles, true);
        if (!$isStaff) {
            $user = null;
        }
    }
    jsonResponse([
        'user'      => $user ?? null,
        'csrfToken' => $user ? getCsrfToken() : null,
    ], 200);
} catch (Throwable $e) {
    error_log('GET /api/auth/me error: ' . $e->getMessage());
    jsonResponse(['user' => null, 'csrfToken' => null], 200);
}
