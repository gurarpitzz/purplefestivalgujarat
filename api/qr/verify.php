<?php
declare(strict_types=1);

/**
 * POST /api/qr/verify (php-site/api/qr/verify.php)
 * Exact PHP port of src/app/api/qr/verify/route.ts.
 *
 * Restricted strictly to RGST, ADMIN, SUPERADMIN (PERMISSIONS['QR_SCAN']).
 * Data minimization: returns ONLY minimal venue check-in info.
 * NEVER returns disability, medical, accommodation, email, or phone data.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/qr-service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    http_response_code(405);
    echo jsonEncode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo jsonEncode(['error' => 'Authentication required.']);
        exit;
    }

    $canScan = (function_exists('hasPermission') && hasPermission($user, PERMISSIONS['QR_SCAN']))
        || (!empty($user['roles']) && is_array($user['roles']) && count(array_intersect($user['roles'], ['RGST', 'ADMIN', 'SUPERADMIN'])) > 0);

    if (!$canScan) {
        http_response_code(403);
        echo jsonEncode([
            'error' => 'Access denied. QR scanning is restricted to authorized RGST and Admin staff.',
        ]);
        exit;
    }

    $body = readRequestInput();
    $rawInput = trim((string)($body['input'] ?? $body['scanInput'] ?? $body['token'] ?? $body['code'] ?? ''));
    $scopeOverride = isset($body['scopeOverride'])
        ? trim((string)$body['scopeOverride'])
        : (isset($body['entitlementScope']) ? trim((string)$body['entitlementScope']) : null);

    if ($rawInput === '' || strlen($rawInput) > 256) {
        http_response_code(400);
        echo jsonEncode([
            'error' => 'Please provide a valid QR code or Registration/Credential ID.',
        ]);
        exit;
    }

    if ($scopeOverride !== null && strlen($scopeOverride) > 100) {
        $scopeOverride = substr($scopeOverride, 0, 100);
    }

    $pdo = getDb();
    $result = verifyCredentialInput($pdo, $rawInput, $scopeOverride);

    if (!empty($result['credentialId'])) {
        pfgRecordCredentialEvent(
            $pdo,
            (string)$result['credentialId'],
            (string)$user['id'],
            'SCANNED',
            [
                'valid'            => (bool)$result['valid'],
                'reason'           => (string)$result['reason'],
                'entitlementScope' => $result['entitlementScope'] ?? null,
            ]
        );
    }

    $attendanceStatus = ($result['reason'] ?? '') === 'ALREADY_CHECKED_IN'
        ? 'ALREADY_CHECKED_IN'
        : (!empty($result['valid']) ? 'NOT_CHECKED_IN' : 'INELIGIBLE');

    http_response_code(200);
    echo jsonEncode([
        'valid'              => (bool)$result['valid'],
        'reason'             => (string)$result['reason'],
        'message'            => (string)$result['message'],
        'credentialId'       => $result['credentialId'] ?? null,
        'credentialCode'     => $result['credentialCode'] ?? null,
        'registrationId'     => $result['registrationId'] ?? null,
        'registrationCode'   => $result['registrationCode'] ?? null,
        'participantName'    => $result['participantName'] ?? null,
        'categoryCode'       => $result['categoryCode'] ?? null,
        'categoryName'       => $result['categoryName'] ?? null,
        'credentialStatus'   => $result['credentialStatus'] ?? null,
        'registrationStatus' => $result['registrationStatus'] ?? null,
        'attendingDays'      => $result['attendingDays'] ?? [],
        'alreadyCheckedIn'   => (bool)($result['alreadyCheckedIn'] ?? false),
        'attendanceStatus'   => $attendanceStatus,
        'entitlementScope'   => $result['entitlementScope'] ?? null,
        'existingCheckin'    => $result['existingCheckin'] ?? null,
    ]);
} catch (Throwable $err) {
    error_log('[POST /api/qr/verify] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Failed to verify credential.']);
}
