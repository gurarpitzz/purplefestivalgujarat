<?php
declare(strict_types=1);

/**
 * POST /api/auth/signup
 * Phase 2 Security Containment:
 * Direct unverified participant self-signup is contained pending Phase 5 verified participant onboarding
 * so unauthenticated callers cannot mass-create locked-out accounts or enumerate registered emails.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

try {
    $pdo = getDb();
    $clientIp = getClientIp() ?: 'unknown';
    pfgRequireRateLimit($pdo, 'auth_signup_ip', $clientIp, 10, 3600, 3600);

    logAudit([
        'actorUserId'  => null,
        'action'       => 'ACCESS_DENIED',
        'resourceType' => 'auth',
        'resourceId'   => '/api/auth/signup',
        'metadata'     => ['reason' => 'signup_contained_pending_phase5'],
    ]);

    jsonResponse([
        'error' => 'Direct account signup is currently disabled while verified participant onboarding is being upgraded. Please submit your festival registration at /apply.',
    ], 403);
} catch (Throwable $e) {
    error_log('POST /api/auth/signup error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process request right now.'], 500);
}
