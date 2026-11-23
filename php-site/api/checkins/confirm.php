<?php
declare(strict_types=1);

/**
 * POST /api/checkins/confirm (php-site/api/checkins/confirm.php)
 * Exact PHP port of src/app/api/checkins/confirm/route.ts.
 *
 * Restricted strictly to RGST, ADMIN, SUPERADMIN (PERMISSIONS['CHECKINS_CONFIRM']).
 * Explicitly confirms attendance with DB-level unique constraint duplicate protection.
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

    $userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $canConfirm = (function_exists('hasPermission') && hasPermission($user, PERMISSIONS['CHECKINS_CONFIRM']))
        || count(array_intersect($userRoles, ['RGST', 'ADMIN', 'SUPERADMIN'])) > 0;

    if (!$canConfirm) {
        http_response_code(403);
        echo jsonEncode([
            'error' => 'Access denied. Check-in confirmation requires RGST or Admin authorization.',
        ]);
        exit;
    }

    $body = readRequestInput();
    $credentialId = trim((string)($body['credentialId'] ?? $body['credential_id'] ?? ''));
    $venueLabel = trim((string)($body['venueLabel'] ?? $body['venue_label'] ?? ''));
    $stationId = !empty($body['stationId'])
        ? trim((string)$body['stationId'])
        : (!empty($body['station_id']) ? trim((string)$body['station_id']) : null);
    $scanMethod = strtoupper(trim((string)($body['method'] ?? 'QR_CAMERA')));
    if (!in_array($scanMethod, ['QR_CAMERA', 'USB_SCANNER', 'MANUAL_ID'], true)) {
        $scanMethod = 'QR_CAMERA';
    }
    $scopeOverride = isset($body['scopeOverride'])
        ? trim((string)$body['scopeOverride'])
        : (isset($body['entitlementScope']) ? trim((string)$body['entitlementScope']) : null);

    if ($credentialId === '' || $venueLabel === '' || strlen($venueLabel) > 150) {
        http_response_code(400);
        echo jsonEncode([
            'error' => 'Invalid check-in request. Valid credentialId and venueLabel are required.',
        ]);
        exit;
    }

    $pdo = getDb();

    // Guard registration eligibility before confirming check-in
    $credStmt = $pdo->prepare(
        'SELECT q.id AS credential_id, q.credential_code, q.status AS credential_status, q.edition_id,
                r.id AS registration_id, r.registration_code, r.status AS registration_status,
                r.payment_status, r.full_name AS participant_name, r.category_code
         FROM qr_credentials q
         INNER JOIN registrations r ON r.id = q.registration_id
         WHERE q.id = :id AND r.deleted_at IS NULL
         LIMIT 1'
    );
    $credStmt->execute([':id' => $credentialId]);
    $credRow = $credStmt->fetch(PDO::FETCH_ASSOC);

    if (!$credRow) {
        http_response_code(404);
        echo jsonEncode(['error' => 'Credential not found.']);
        exit;
    }

    if ($credRow['credential_status'] !== 'ACTIVE') {
        http_response_code(400);
        echo jsonEncode([
            'error' => "Credential is {$credRow['credential_status']} and cannot be checked in.",
        ]);
        exit;
    }

    if (!in_array($credRow['registration_status'], ['CONFIRMED', 'APPROVED'], true)) {
        http_response_code(400);
        echo jsonEncode([
            'error' => "Registration status is {$credRow['registration_status']} and is not eligible for entry.",
        ]);
        exit;
    }

    if (!in_array($credRow['payment_status'], ['NOT_REQUIRED', 'CAPTURED'], true)) {
        http_response_code(400);
        echo jsonEncode([
            'error' => "Payment status is {$credRow['payment_status']}. Entry requires verified payment.",
        ]);
        exit;
    }

    $result = confirmParticipantCheckin($pdo, [
        'credentialId'   => $credentialId,
        'operatorUserId' => (string)$user['id'],
        'venueLabel'     => $venueLabel,
        'stationId'      => $stationId,
        'method'         => $scanMethod,
        'scopeOverride'  => $scopeOverride,
    ]);

    $auditAction = !empty($result['success'])
        ? 'CHECKIN_CONFIRMED'
        : (!empty($result['duplicate']) ? 'CHECKIN_DUPLICATE_PREVENTED' : 'CHECKIN_REJECTED');

    if (function_exists('logAudit')) {
        logAudit(
            (string)$user['id'],
            $auditAction,
            'checkin',
            (string)($result['checkin']['id'] ?? $credentialId),
            [
                'credentialId'     => $credentialId,
                'registrationCode' => $credRow['registration_code'],
                'venueLabel'       => $venueLabel,
                'stationId'        => $stationId,
                'method'           => $scanMethod,
                'duplicate'        => !empty($result['duplicate']),
            ]
        );
    }

    if (!empty($result['duplicate'])) {
        http_response_code(409);
        echo jsonEncode([
            'success'         => false,
            'duplicate'       => true,
            'message'         => 'ALREADY CHECKED IN',
            'existingCheckin' => $result['existingCheckin'] ?? null,
        ]);
        exit;
    }

    if (empty($result['success']) || empty($result['checkin'])) {
        http_response_code(400);
        echo jsonEncode([
            'success' => false,
            'message' => $result['message'] ?? 'Check-in failed.',
        ]);
        exit;
    }

    $chk = $result['checkin'];
    http_response_code(201);
    echo jsonEncode([
        'success' => true,
        'message' => 'Participant checked in successfully.',
        'checkin' => [
            'id'               => (string)$chk['id'],
            'credentialId'     => (string)$chk['credentialId'],
            'credentialCode'   => (string)$credRow['credential_code'],
            'registrationCode' => (string)$credRow['registration_code'],
            'participantName'  => (string)$credRow['participant_name'],
            'categoryCode'     => (string)$credRow['category_code'],
            'venueLabel'       => (string)$chk['venueLabel'],
            'stationId'        => $chk['stationId'],
            'entitlementScope' => (string)$chk['entitlementScope'],
            'checkinDateIst'   => (string)$chk['checkinDateIst'],
            'method'           => (string)$chk['method'],
            'checkedInAt'      => (string)$chk['checkedInAt'],
            'checkedInAtIst'   => (string)$chk['checkedInAtIst'],
        ],
    ]);
} catch (Throwable $err) {
    error_log('[POST /api/checkins/confirm] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Failed to confirm participant check-in.']);
}
