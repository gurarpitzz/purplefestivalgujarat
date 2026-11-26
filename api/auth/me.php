<?php
declare(strict_types=1);

/**
 * GET /api/auth/me
 * Returns the currently authenticated user profile, roles, permissions, and session-bound CSRF token.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

try {
    $user = getCurrentUser();
    jsonResponse([
        'user'      => $user ?? null,
        'csrfToken' => $user ? getCsrfToken() : null,
    ], 200);
} catch (Throwable $e) {
    error_log('GET /api/auth/me error: ' . $e->getMessage());
    jsonResponse(['user' => null, 'csrfToken' => null], 200);
}
