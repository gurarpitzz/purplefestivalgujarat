<?php
declare(strict_types=1);

/**
 * POST /api/webhooks/resend
 * Official Resend (Svix) Delivery Tracking Webhook Endpoint (Phase 4I)
 *
 * Security Guarantees:
 * - Does NOT rely on browser session or CSRF tokens.
 * - Fails closed (HTTP 503) if RESEND_WEBHOOK_SECRET is not configured.
 * - Verifies official Svix HMAC-SHA256 signature (`svix-id`, `svix-timestamp`, `svix-signature`)
 *   against the exact raw HTTP body with a ±300-second replay window.
 * - Rejects unauthenticated, replayed, or malformed payloads (401 / 400).
 * - Processes events idempotently (`uq_ndl_provider_event`) with monotonic state-precedence
 *   so out-of-order delayed events never overwrite newer terminal delivery states.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/notification-service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    http_response_code(405);
    echo jsonEncode(['error' => 'Method not allowed.']);
    exit;
}

$secret = defined('RESEND_WEBHOOK_SECRET') ? trim((string)RESEND_WEBHOOK_SECRET) : '';
if ($secret === '') {
    http_response_code(503);
    echo jsonEncode(['error' => 'Resend webhook endpoint is not configured.']);
    exit;
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || trim($rawBody) === '') {
    http_response_code(400);
    echo jsonEncode(['error' => 'Empty webhook payload.']);
    exit;
}

$headers = [
    'svix-id'        => (string)($_SERVER['HTTP_SVIX_ID'] ?? $_SERVER['HTTP_WEBHOOK_ID'] ?? ''),
    'svix-timestamp' => (string)($_SERVER['HTTP_SVIX_TIMESTAMP'] ?? $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? ''),
    'svix-signature' => (string)($_SERVER['HTTP_SVIX_SIGNATURE'] ?? $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? ''),
];

$verify = pfgVerifySvixWebhookSignature($rawBody, $headers, $secret, 300);
if (empty($verify['valid'])) {
    http_response_code(401);
    echo jsonEncode(['error' => 'Invalid webhook signature.']);
    exit;
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo jsonEncode(['error' => 'Malformed JSON webhook payload.']);
    exit;
}

try {
    $pdo = getDb();
    $result = pfgProcessResendWebhook($pdo, (string)$verify['svixId'], $decoded);
    http_response_code((int)($result['httpStatus'] ?? 200));
    echo jsonEncode([
        'ok'     => !empty($result['ok']),
        'action' => $result['action'] ?? null,
        'status' => $result['newStatus'] ?? $result['status'] ?? $result['currentStatus'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('/api/webhooks/resend error: ' . $e->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Unable to process webhook event.']);
}

