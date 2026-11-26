<?php
declare(strict_types=1);

/**
 * GET & POST /api/qr/pass.php (also handles /api/qr/{registrationId})
 *
 * Permanently discontinued under Decision D-01 (HIGH-03).
 * Participant QR entry passes are no longer issued, reconstructed, or served.
 * All event-day check-in uses official Registration IDs at the Vadodara Registration Desk.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo jsonEncode(['error' => 'Authentication required.']);
        exit;
    }
    enforceStaffMfaGate($user);

    if (in_array($method, ['GET', 'POST', 'PATCH'], true)) {
        if ($method !== 'GET') {
            requireCsrfToken($user);
        }
        http_response_code(410);
        echo jsonEncode([
            'error'      => 'Participant QR entry passes have been discontinued under Decision D-01. Use your Registration ID for manual desk check-in.',
            'deprecated' => true,
        ]);
        exit;
    }

    http_response_code(405);
    echo jsonEncode(['error' => 'Method not allowed.']);
} catch (Throwable $err) {
    error_log('[/api/qr/pass.php] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Failed to process request.']);
}
