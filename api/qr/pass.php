<?php
declare(strict_types=1);

/**
 * GET & POST /api/qr/pass.php (also handles /api/qr/{registrationId})
 * Exact PHP port of src/app/api/qr/[registrationId]/route.ts.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/qr-service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body = ($method === 'POST' || $method === 'PATCH') ? readRequestInput() : [];

// Resolve registrationId from query string, request body, or URL path (/api/qr/<registrationId>)
$registrationId = trim((string)($_GET['registrationId'] ?? $_GET['id'] ?? $body['registrationId'] ?? ''));
if ($registrationId === '') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/api/qr/([A-Za-z0-9_-]{8,64})(?:/|$)#', $uriPath, $m)) {
        if (!in_array(strtolower($m[1]), ['verify', 'pass', 'verify.php', 'pass.php'], true)) {
            $registrationId = $m[1];
        }
    }
}

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo jsonEncode(['error' => 'Authentication required.']);
        exit;
    }
    enforceStaffMfaGate($user);

    if ($registrationId === '') {
        http_response_code(400);
        echo jsonEncode(['error' => 'Registration ID is required.']);
        exit;
    }

    $pdo = getDb();
    $userRoles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    $isAdmin = in_array('ADMIN', $userRoles, true) || in_array('SUPERADMIN', $userRoles, true);

    if ($method === 'GET') {
        $regStmt = $pdo->prepare(
            'SELECT r.id, r.registration_code, r.user_id, r.edition_id, r.category_id, r.category_code,
                    r.status, r.payment_status, r.full_name, c.name AS category_name
             FROM registrations r
             LEFT JOIN registration_categories c ON c.id = r.category_id
             WHERE (r.id = :id OR r.registration_code = :code) AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $regStmt->execute([
            ':id'   => $registrationId,
            ':code' => $registrationId,
        ]);
        $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
            http_response_code(404);
            echo jsonEncode(['error' => 'Registration not found.']);
            exit;
        }

        $isOwner = ((string)$reg['user_id'] === (string)$user['id']);
        if (!$isOwner && !$isAdmin) {
            http_response_code(403);
            echo jsonEncode(['error' => 'You do not have permission to view this pass.']);
            exit;
        }

        $credStmt = $pdo->prepare(
            "SELECT * FROM qr_credentials
             WHERE registration_id = :reg_id AND status = 'ACTIVE'
             ORDER BY issued_at DESC
             LIMIT 1"
        );
        $credStmt->execute([':reg_id' => $reg['id']]);
        $credential = $credStmt->fetch(PDO::FETCH_ASSOC);

        if (!$credential) {
            http_response_code(404);
            echo jsonEncode([
                'error'               => 'No active QR pass is issued for this registration yet. Passes are issued once your registration is confirmed or approved.',
                'registrationId'      => $reg['id'],
                'registrationCode'    => $reg['registration_code'],
                'registrationStatus'  => $reg['status'],
                'paymentStatus'       => $reg['payment_status'],
                'hasActiveCredential' => false,
            ]);
            exit;
        }

        // Opaque PFG27.<nonce>.<sig> token (contains zero PII and verifies via verifyCredentialInput)
        $qrPayload = reconstructQrTokenForCredential($credential);
        $qrDataUrl = generateQrSvgDataUrl($qrPayload);

        $chkStmt = $pdo->prepare(
            'SELECT id, venue_label, checkin_date_ist, checked_in_at
             FROM checkins
             WHERE registration_id = :reg_id
             ORDER BY checked_in_at DESC'
        );
        $chkStmt->execute([':reg_id' => $reg['id']]);
        $regCheckins = $chkStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $todayIst = getIstDateString();
        $checkedInToday = false;
        foreach ($regCheckins as $c) {
            if (($c['checkin_date_ist'] ?? '') === $todayIst) {
                $checkedInToday = true;
                break;
            }
        }
        $latest = $regCheckins[0] ?? null;
        $categoryName = !empty($reg['category_name']) ? (string)$reg['category_name'] : (string)$reg['category_code'];

        $accessibleText = sprintf(
            'Purple Festival Gujarat 2027 Digital Entry Pass for %s. Category: %s. Registration ID: %s. Credential ID: %s. Status: %s. Present this QR code or quote your Credential ID at the Registration Desk (RGST).',
            $reg['full_name'],
            $categoryName,
            $reg['registration_code'],
            $credential['credential_code'],
            $credential['status']
        );

        $printUrl = '/dashboard/pass-print.php?registrationId=' . rawurlencode((string)$reg['id']);

        http_response_code(200);
        echo jsonEncode([
            'hasActiveCredential' => true,
            'registrationId'      => $reg['id'],
            'registrationCode'    => $reg['registration_code'],
            'participantName'     => $reg['full_name'],
            'categoryCode'        => $reg['category_code'],
            'categoryName'        => $categoryName,
            'registrationStatus'  => $reg['status'],
            'paymentStatus'       => $reg['payment_status'],
            'credential'          => [
                'id'             => $credential['id'],
                'credentialCode' => $credential['credential_code'],
                'status'         => $credential['status'],
                'issuedAt'       => $credential['issued_at'],
                'issuedAtIst'    => formatIstTimestamp($credential['issued_at']),
            ],
            'qrDataUrl'           => $qrDataUrl,
            'qrPayload'           => $qrPayload,
            'accessibleText'      => $accessibleText,
            'attendance'          => [
                'checkedInToday' => $checkedInToday,
                'totalCheckins'  => count($regCheckins),
                'latestCheckin'  => $latest ? [
                    'checkedInAt'    => $latest['checked_in_at'],
                    'checkedInAtIst' => formatIstTimestamp($latest['checked_in_at']),
                    'venueLabel'     => $latest['venue_label'],
                    'checkinDateIst' => $latest['checkin_date_ist'],
                ] : null,
            ],
            'pdfUrl'              => $printUrl,
            'printUrl'            => $printUrl,
        ]);
        exit;
    }

    if ($method === 'POST' || $method === 'PATCH') {
        requireCsrfToken($user);

        $action = strtolower(trim((string)($body['action'] ?? 'reissue')));
        if (!in_array($action, ['reissue', 'revoke', 'issue'], true)) {
            http_response_code(400);
            echo jsonEncode(['error' => 'Invalid action.']);
            exit;
        }
        $reason = trim((string)($body['reason'] ?? ''));
        if (strlen($reason) > 300) {
            $reason = substr($reason, 0, 300);
        }

        if ($action === 'issue' || $action === 'reissue') {
            http_response_code(410);
            echo jsonEncode([
                'error'      => 'Participant QR entry pass issuance has been discontinued (Decision D-01). Use official Registration IDs for check-in.',
                'deprecated' => true,
            ]);
            exit;
        }

        if ($action === 'revoke') {
            $canRevoke = function_exists('hasPermission') && hasPermission($user, PERMISSIONS['CREDENTIALS_REVOKE']);
            if (!$canRevoke) {
                http_response_code(403);
                echo jsonEncode(['error' => 'Insufficient permissions to revoke legacy credentials.']);
                exit;
            }

            $activeStmt = $pdo->prepare(
                "SELECT * FROM qr_credentials
                 WHERE registration_id = :reg_id AND status = 'ACTIVE'
                 ORDER BY issued_at DESC
                 LIMIT 1"
            );
            $activeStmt->execute([':reg_id' => $registrationId]);
            $activeCred = $activeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$activeCred) {
                http_response_code(404);
                echo jsonEncode(['error' => 'No active credential found to revoke.']);
                exit;
            }

            $revokeReason = $reason !== '' ? $reason : 'Revoked by administrator';
            $revoked = revokeCredential($pdo, (string)$activeCred['id'], $revokeReason, (string)$user['id']);

            if (function_exists('logAudit')) {
                logAudit((string)$user['id'], 'CREDENTIAL_REVOKED', 'qr_credential', (string)$activeCred['id'], [
                    'registrationId' => $registrationId,
                    'credentialCode' => $activeCred['credential_code'],
                    'reason'         => $revokeReason,
                ]);
            }

            http_response_code(200);
            echo jsonEncode([
                'success'    => true,
                'action'     => 'revoke',
                'credential' => $revoked,
            ]);
            exit;
        }
    }

    http_response_code(405);
    echo jsonEncode(['error' => 'Method not allowed.']);
} catch (Throwable $err) {
    error_log('[/api/qr/pass.php] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Failed to manage QR pass.']);
}
