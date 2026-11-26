<?php
declare(strict_types=1);

/**
 * POST /api/qr/verify (php-site/api/qr/verify.php)
 * Phase 2 Containment (Decision D-01):
 * QR-based event entry scanning has been discontinued in favor of manual Registration Desk
 * attendance management (Phase 9). Enforces auth, RBAC, mandatory staff MFA, and CSRF,
 * then returns 410 Gone while preserving historical database records intact.
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
        'valid'      => false,
        'deprecated' => true,
        'reason'     => 'QR_ENTRY_DISCONTINUED',
        'error'      => 'Participant QR entry verification has been discontinued (Decision D-01). Event-day attendance will use manual Registration Desk checkbox verification (Phase 9).',
    ], 410);
} catch (Throwable $err) {
    error_log('[POST /api/qr/verify] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Failed to process request.'], 500);
}
