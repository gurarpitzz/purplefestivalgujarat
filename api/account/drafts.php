<?php
declare(strict_types=1);

/**
 * GET / POST / DELETE /api/account/drafts
 * Purple Festival Gujarat 2027 — Per-Category Autosave Drafts API (Phase 6)
 *
 * Persists drafts cleanly per (edition_id, user_id, category_code) in `registration_drafts`.
 * Sensitive draft free-text values are encrypted at rest before persisting and decrypted only for the authenticated owner.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/registration-validators.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function pfgDraftGetActiveEditionId(PDO $pdo): ?string
{
    $slug = defined('FESTIVAL_EDITION_SLUG') ? FESTIVAL_EDITION_SLUG : 'gujarat-2027';
    $stmt = $pdo->prepare('SELECT id FROM festival_editions WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $id = $stmt->fetchColumn();
    if (is_string($id) && $id !== '') {
        return $id;
    }
    $stmt2 = $pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1');
    $id2 = $stmt2 ? $stmt2->fetchColumn() : false;
    return is_string($id2) && $id2 !== '' ? $id2 : null;
}

function pfgDraftEncryptSensitiveFields(array $payload): array
{
    unset($payload['password'], $payload['confirmPassword'], $payload['csrfToken'], $payload['csrf_token'], $payload['_csrf']);
    $sensitiveKeys = ['disabilitySelfDescription', 'accommodationFreeText', 'escortName', 'dietaryRequirements'];
    foreach ($sensitiveKeys as $k) {
        if (isset($payload[$k]) && is_string($payload[$k]) && trim($payload[$k]) !== '') {
            $payload[$k] = pfgEncryptSensitiveValue($payload[$k]);
        }
    }
    if (isset($payload['attendeeDetails']) && is_array($payload['attendeeDetails'])) {
        if (isset($payload['attendeeDetails']['dietaryRequirements']) && is_string($payload['attendeeDetails']['dietaryRequirements']) && trim($payload['attendeeDetails']['dietaryRequirements']) !== '') {
            $payload['attendeeDetails']['dietaryRequirements'] = pfgEncryptSensitiveValue($payload['attendeeDetails']['dietaryRequirements']);
        }
    }
    return $payload;
}

function pfgDraftDecryptSensitiveFields(array $payload): array
{
    $sensitiveKeys = ['disabilitySelfDescription', 'accommodationFreeText', 'escortName', 'dietaryRequirements'];
    foreach ($sensitiveKeys as $k) {
        if (isset($payload[$k]) && is_string($payload[$k]) && $payload[$k] !== '') {
            $payload[$k] = pfgDecryptSensitiveValue($payload[$k]);
        }
    }
    if (isset($payload['attendeeDetails']) && is_array($payload['attendeeDetails'])) {
        if (isset($payload['attendeeDetails']['dietaryRequirements']) && is_string($payload['attendeeDetails']['dietaryRequirements']) && $payload['attendeeDetails']['dietaryRequirements'] !== '') {
            $payload['attendeeDetails']['dietaryRequirements'] = pfgDecryptSensitiveValue($payload['attendeeDetails']['dietaryRequirements']);
        }
    }
    return $payload;
}

try {
    $pdo = getDb();
    $user = getCurrentUser($pdo);
    if (!$user || empty($user['id'])) {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }
    enforceStaffMfaGate($user);
    $userId = (string)$user['id'];

    $editionId = pfgDraftGetActiveEditionId($pdo);
    if ($editionId === null) {
        jsonResponse(['error' => 'No active festival edition configured.'], 404);
    }

    // ========================================================================
    // GET /api/account/drafts(?category=ATTENDEE|VOLUNTEER|SLI)
    // ========================================================================
    if ($method === 'GET') {
        $catParam = isset($_GET['category']) && is_string($_GET['category']) ? strtoupper(trim($_GET['category'])) : '';
        if ($catParam !== '') {
            if (!in_array($catParam, PFG_PRIMARY_CATEGORIES, true)) {
                jsonResponse(['error' => 'Invalid primary category code.'], 400);
            }
            $stmt = $pdo->prepare(
                'SELECT id, category_code, payload_json, created_at, updated_at
                 FROM registration_drafts
                 WHERE edition_id = :eid AND user_id = :uid AND category_code = :ccode
                 LIMIT 1'
            );
            $stmt->execute([
                ':eid'   => $editionId,
                ':uid'   => $userId,
                ':ccode' => $catParam,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                jsonResponse(['draft' => null], 200);
            }
            $decoded = jsonDecode($row['payload_json'] ?? '{}', []);
            jsonResponse([
                'draft' => [
                    'id'           => (string)$row['id'],
                    'categoryCode' => (string)$row['category_code'],
                    'payload'      => pfgDraftDecryptSensitiveFields(is_array($decoded) ? $decoded : []),
                    'createdAt'    => (string)$row['created_at'],
                    'updatedAt'    => (string)$row['updated_at'],
                ],
            ], 200);
        }

        $stmt = $pdo->prepare(
            'SELECT id, category_code, payload_json, created_at, updated_at
             FROM registration_drafts
             WHERE edition_id = :eid AND user_id = :uid
             ORDER BY updated_at DESC'
        );
        $stmt->execute([':eid' => $editionId, ':uid' => $userId]);
        $drafts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $decoded = jsonDecode($row['payload_json'] ?? '{}', []);
            $drafts[] = [
                'id'           => (string)$row['id'],
                'categoryCode' => (string)$row['category_code'],
                'payload'      => pfgDraftDecryptSensitiveFields(is_array($decoded) ? $decoded : []),
                'createdAt'    => (string)$row['created_at'],
                'updatedAt'    => (string)$row['updated_at'],
            ];
        }
        jsonResponse(['drafts' => $drafts], 200);
    }

    // ========================================================================
    // POST /api/account/drafts — Save or update draft
    // ========================================================================
    if ($method === 'POST') {
        requireCsrfToken($user);
        $body = readRequestInput();

        $catCode = strtoupper(trim((string)($body['categoryCode'] ?? $body['category_code'] ?? '')));
        if (!in_array($catCode, PFG_PRIMARY_CATEGORIES, true)) {
            jsonResponse(['error' => 'Draft categoryCode must be ATTENDEE, VOLUNTEER, or SLI.'], 400);
        }

        $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : [];
        $encoded = jsonEncode(pfgDraftEncryptSensitiveFields($payload));
        if (strlen($encoded) > 65535) {
            jsonResponse(['error' => 'Draft payload is too large.'], 400);
        }

        $now = nowSql();
        $draftId = uuid4();

        $stmt = $pdo->prepare(
            'INSERT INTO registration_drafts (id, edition_id, user_id, category_code, payload_json, created_at, updated_at)
             VALUES (:id, :eid, :uid, :ccode, :payload, :now1, :now2)
             ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            ':id'      => $draftId,
            ':eid'     => $editionId,
            ':uid'     => $userId,
            ':ccode'   => $catCode,
            ':payload' => $encoded,
            ':now1'    => $now,
            ':now2'    => $now,
        ]);

        jsonResponse([
            'saved'        => true,
            'categoryCode' => $catCode,
            'updatedAt'    => $now,
        ], 200);
    }

    // ========================================================================
    // DELETE /api/account/drafts
    // ========================================================================
    if ($method === 'DELETE') {
        requireCsrfToken($user);
        $body = readRequestInput();
        $catCode = strtoupper(trim((string)($_GET['category'] ?? $body['categoryCode'] ?? $body['category_code'] ?? '')));

        if ($catCode !== '') {
            if (!in_array($catCode, PFG_PRIMARY_CATEGORIES, true)) {
                jsonResponse(['error' => 'Invalid primary category code.'], 400);
            }
            $stmt = $pdo->prepare(
                'DELETE FROM registration_drafts WHERE edition_id = :eid AND user_id = :uid AND category_code = :ccode'
            );
            $stmt->execute([
                ':eid'   => $editionId,
                ':uid'   => $userId,
                ':ccode' => $catCode,
            ]);
            jsonResponse(['deleted' => true, 'categoryCode' => $catCode, 'rowsAffected' => $stmt->rowCount()], 200);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM registration_drafts WHERE edition_id = :eid AND user_id = :uid'
        );
        $stmt->execute([':eid' => $editionId, ':uid' => $userId]);
        jsonResponse(['deleted' => true, 'rowsAffected' => $stmt->rowCount()], 200);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $err) {
    error_log('[/api/account/drafts] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Unable to process draft request.'], 500);
}
