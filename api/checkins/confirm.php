<?php
declare(strict_types=1);

/**
 * POST /api/checkins/confirm (php-site/api/checkins/confirm.php)
 * Phase 2 Containment (Decision D-01):
 * QR-based check-in confirmation has been discontinued pending Phase 9 manual checkbox attendance.
 * Enforces auth, RBAC, mandatory staff MFA, and CSRF, then returns 410 Gone without modifying historical checkins.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    jsonResponse(['error' => 'Method not allowed. Use POST.'], 405);
}

try {
    $user = requireRole(['RGST', 'ADMIN', 'SUPERADMIN']);
    requireCsrfToken($user);

    jsonResponse([
        'success'    => false,
        'deprecated' => true,
        'reason'     => 'QR_CHECKIN_DISCONTINUED',
        'error'      => 'QR-based check-in confirmation has been discontinued (Decision D-01). Manual Registration Desk attendance confirmation is scheduled for Phase 9.',
    ], 410);
} catch (Throwable $err) {
    error_log('[POST /api/checkins/confirm] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Failed to process request.'], 500);
}
