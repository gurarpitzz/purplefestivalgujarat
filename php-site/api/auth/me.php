<?php
declare(strict_types=1);

/**
 * GET /api/auth/me
 * Returns the currently authenticated user profile, roles, and permissions (or null).
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

try {
    $user = getCurrentUser();
    jsonResponse(['user' => $user ?? null], 200);
} catch (Throwable $e) {
    error_log('GET /api/auth/me error: ' . $e->getMessage());
    jsonResponse(['user' => null], 200);
}
