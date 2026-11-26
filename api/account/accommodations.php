<?php
declare(strict_types=1);

/**
 * GET & POST /api/account/accommodations (php-site/api/account/accommodations.php)
 * Exact PHP port of src/app/api/account/accommodations/route.ts.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo jsonEncode(['error' => 'Authentication required.']);
        exit;
    }

    $pdo = getDb();

    if ($method === 'GET') {
        $typesStmt = $pdo->query(
            'SELECT id, code, label, description, is_active, display_order
             FROM accommodation_types
             WHERE is_active = 1
             ORDER BY display_order ASC'
        );
        $rawTypes = $typesStmt ? ($typesStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        if (count($rawTypes) === 0 && defined('ACCOMMODATION_TYPES_SEED')) {
            $rawTypes = ACCOMMODATION_TYPES_SEED;
        }
        $accommodationTypes = array_map(static fn(array $t): array => [
            'id'          => $t['id'] ?? $t['code'],
            'code'        => (string)$t['code'],
            'label'       => (string)$t['label'],
            'description' => $t['description'] ?? null,
        ], $rawTypes);

        $reqStmt = $pdo->prepare(
            'SELECT a.id, a.registration_id, a.selected_types, a.accommodation_code,
                    a.free_text_details, a.notes, a.coordinator_notes, a.status, a.created_at, a.updated_at,
                    r.registration_code, r.category_code
             FROM accommodation_requests a
             INNER JOIN registrations r ON r.id = a.registration_id
             WHERE (a.user_id = :user_id OR r.user_id = :reg_user_id) AND r.deleted_at IS NULL
             ORDER BY a.created_at DESC'
        );
        $reqStmt->execute([
            ':user_id'     => $user['id'],
            ':reg_user_id' => $user['id'],
        ]);
        $rows = $reqStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Also merge any rows stored in registration_accommodations not already in accommodation_requests
        $seenIds = [];
        foreach ($rows as $row) {
            $seenIds[(string)$row['id']] = true;
        }
        try {
            $regAccStmt = $pdo->prepare(
                'SELECT a.id, a.registration_id, a.selected_types, a.accommodation_code,
                        a.free_text_details, a.notes, a.coordinator_notes, a.status, a.created_at, a.updated_at,
                        r.registration_code, r.category_code
                 FROM registration_accommodations a
                 INNER JOIN registrations r ON r.id = a.registration_id
                 WHERE r.user_id = :reg_user_id AND r.deleted_at IS NULL
                 ORDER BY a.created_at DESC'
            );
            $regAccStmt->execute([':reg_user_id' => $user['id']]);
            $extraRows = $regAccStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($extraRows as $er) {
                if (!isset($seenIds[(string)$er['id']])) {
                    $rows[] = $er;
                    $seenIds[(string)$er['id']] = true;
                }
            }
        } catch (Throwable $e) {
            // Ignore if table not queried
        }

        $requests = array_map(static function (array $r): array {
            $selected = jsonDecode($r['selected_types'] ?? '[]', []);
            if (!is_array($selected)) {
                $selected = [];
            }
            if (count($selected) === 0 && !empty($r['accommodation_code'])) {
                $selected = [(string)$r['accommodation_code']];
            }
            $details = pfgDecryptValue($r['free_text_details'] ?? $r['notes'] ?? null, 'data');
            return [
                'id'               => (string)$r['id'],
                'registrationId'   => (string)$r['registration_id'],
                'registrationCode' => (string)$r['registration_code'],
                'categoryCode'     => (string)$r['category_code'],
                'selectedTypes'    => array_values($selected),
                'freeTextDetails'  => $details,
                'coordinatorNotes' => pfgDecryptValue($r['coordinator_notes'] ?? null, 'data'),
                'status'           => (string)($r['status'] ?? 'REQUESTED'),
                'createdAt'        => (string)$r['created_at'],
                'updatedAt'        => (string)($r['updated_at'] ?? $r['created_at']),
            ];
        }, $rows);

        http_response_code(200);
        echo jsonEncode([
            'requests'           => $requests,
            'accommodationTypes' => $accommodationTypes,
        ]);
        exit;
    }

    if ($method === 'POST') {
        enforceStaffMfaGate($user);
        requireCsrfToken($user);

        $body = readRequestInput();
        $registrationId = trim((string)($body['registrationId'] ?? $body['registration_id'] ?? ''));
        $rawSelected = $body['selectedTypes'] ?? $body['selected_types'] ?? [];
        if (is_string($rawSelected)) {
            $rawSelected = jsonDecode($rawSelected, []);
        }
        $selectedTypes = [];
        if (is_array($rawSelected)) {
            foreach ($rawSelected as $item) {
                $trimmed = trim((string)$item);
                if ($trimmed !== '') {
                    $selectedTypes[] = $trimmed;
                }
            }
        }
        $freeTextDetails = trim((string)($body['freeTextDetails'] ?? $body['free_text_details'] ?? $body['notes'] ?? ''));
        if (strlen($freeTextDetails) > 1000) {
            $freeTextDetails = substr($freeTextDetails, 0, 1000);
        }

        if ($registrationId === '') {
            http_response_code(400);
            echo jsonEncode(['error' => 'Please select a registration.']);
            exit;
        }

        if (count($selectedTypes) === 0 && $freeTextDetails === '') {
            http_response_code(400);
            echo jsonEncode([
                'error' => 'Please select at least one accommodation type or describe your requirement.',
            ]);
            exit;
        }

        $regStmt = $pdo->prepare(
            'SELECT id, registration_code, category_code
             FROM registrations
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
             LIMIT 1'
        );
        $regStmt->execute([
            ':id'      => $registrationId,
            ':user_id' => $user['id'],
        ]);
        $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
            http_response_code(404);
            echo jsonEncode(['error' => 'Registration not found for your account.']);
            exit;
        }

        $reqId = uuid4();
        $now = nowSql();
        $selectedJson = jsonEncode($selectedTypes);
        $primaryCode = $selectedTypes[0] ?? null;
        $encryptedDetails = $freeTextDetails !== '' ? pfgEncryptValue($freeTextDetails, 'data') : null;

        $insStmt = $pdo->prepare(
            'INSERT INTO accommodation_requests (
                id, registration_id, user_id, accommodation_code, selected_types,
                free_text_details, notes, status, created_at, updated_at
            ) VALUES (
                :id, :registration_id, :user_id, :accommodation_code, :selected_types,
                :free_text_details, :notes, :status, :created_at, :updated_at
            )'
        );
        $insStmt->execute([
            ':id'                 => $reqId,
            ':registration_id'    => $reg['id'],
            ':user_id'            => $user['id'],
            ':accommodation_code' => $primaryCode,
            ':selected_types'     => $selectedJson,
            ':free_text_details'  => $encryptedDetails,
            ':notes'              => $encryptedDetails,
            ':status'             => 'REQUESTED',
            ':created_at'         => $now,
            ':updated_at'         => $now,
        ]);

        // Mirror to registration_accommodations so both tables remain synchronized
        try {
            $mirrorStmt = $pdo->prepare(
                'INSERT INTO registration_accommodations (
                    id, registration_id, user_id, accommodation_code, selected_types,
                    free_text_details, notes, status, created_at, updated_at
                ) VALUES (
                    :id, :registration_id, :user_id, :accommodation_code, :selected_types,
                    :free_text_details, :notes, :status, :created_at, :updated_at
                )'
            );
            $mirrorStmt->execute([
                ':id'                 => $reqId,
                ':registration_id'    => $reg['id'],
                ':user_id'            => $user['id'],
                ':accommodation_code' => $primaryCode,
                ':selected_types'     => $selectedJson,
                ':free_text_details'  => $encryptedDetails,
                ':notes'              => $encryptedDetails,
                ':status'             => 'REQUESTED',
                ':created_at'         => $now,
                ':updated_at'         => $now,
            ]);
        } catch (Throwable $e) {
            // Non-fatal if mirror table insert is skipped
        }

        if (function_exists('logAudit')) {
            logAudit(
                (string)$user['id'],
                'ACCOMMODATION_REQUEST_SUBMITTED',
                'accommodation_request',
                $reqId,
                [
                    'registrationId' => $reg['id'],
                    'selectedCount'  => count($selectedTypes),
                ]
            );
        }

        http_response_code(201);
        echo jsonEncode([
            'request' => [
                'id'               => $reqId,
                'registrationId'   => (string)$reg['id'],
                'userId'           => (string)$user['id'],
                'registrationCode' => (string)$reg['registration_code'],
                'categoryCode'     => (string)$reg['category_code'],
                'selectedTypes'    => $selectedTypes,
                'freeTextDetails'  => $freeTextDetails !== '' ? $freeTextDetails : null,
                'status'           => 'REQUESTED',
                'createdAt'        => $now,
                'updatedAt'        => $now,
            ],
            'message' => 'Your accessibility accommodation request has been submitted to our coordinators.',
        ]);
        exit;
    }

    http_response_code(405);
    echo jsonEncode(['error' => 'Method not allowed.']);
} catch (Throwable $err) {
    error_log('[/api/account/accommodations] Error: ' . $err->getMessage());
    http_response_code(500);
    echo jsonEncode(['error' => 'Unable to process accommodation request.']);
}
