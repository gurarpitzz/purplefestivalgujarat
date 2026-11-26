<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Venue Management API (Phase 12)
 * Endpoint: GET / POST / PATCH /api/admin/venues
 *
 * - Writes: ADMIN (`cms.manage` or `programme.manage`) / SUPERADMIN + verified MFA (D-20) + session-bound CSRF.
 * - Reads: the above plus Volunteer / SLI coordinators (`volunteers.review` / `sli.review`) so duty rosters can pick venues.
 * - Every venue carries an EXPLICIT `confirmation_status`: PENDING | PROPOSED | CONFIRMED | ARCHIVED. Nothing is ever
 *   implied to be confirmed; unconfirmed venues are labelled as such by the admin portal.
 * - `map_url` is validated with pfgValidateSafeUrl() (http/https only).
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin-common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const PFG_VENUE_STATUSES = ['PENDING', 'PROPOSED', 'CONFIRMED', 'ARCHIVED'];

function pfgVenueFormat(array $v, array $extra = []): array
{
    $features = jsonDecode($v['accessibility_features'] ?? '[]', []);
    return array_merge([
        'id'                    => (string)$v['id'],
        'editionId'             => (string)($v['edition_id'] ?? ''),
        'name'                  => (string)$v['name'],
        'address'               => $v['address'] !== null ? (string)$v['address'] : '',
        'confirmationStatus'    => strtoupper((string)($v['confirmation_status'] ?? 'PENDING')),
        'accessibilityFeatures' => is_array($features) ? array_values(array_filter($features, 'is_string')) : [],
        'capacity'              => isset($v['capacity']) && $v['capacity'] !== null ? (int)$v['capacity'] : null,
        'mapUrl'                => $v['map_url'] !== null && $v['map_url'] !== '' ? (string)$v['map_url'] : null,
        'operationalNotes'      => $v['operational_notes'] ?? null,
        'createdAt'             => (string)($v['created_at'] ?? ''),
        'updatedAt'             => (string)($v['updated_at'] ?? ($v['created_at'] ?? '')),
    ], $extra);
}

/**
 * Validates & normalises the writable venue fields. Returns [fields-by-column]. Only keys present in $input are returned
 * when $partial is true (PATCH), otherwise all columns are returned with defaults (POST).
 */
function pfgVenueParseInput(array $input, bool $partial): array
{
    $out = [];

    if (!$partial || array_key_exists('name', $input)) {
        $name = pfgAdminCleanText($input['name'] ?? '', 200);
        if ($name === null || mb_strlen($name, 'UTF-8') < 2) {
            jsonResponse(['error' => 'Venue name is required (min 2 characters).'], 400);
        }
        $out['name'] = $name;
    }
    if (!$partial || array_key_exists('address', $input)) {
        $out['address'] = pfgAdminCleanText($input['address'] ?? null, 1000);
    }
    if (!$partial || array_key_exists('confirmationStatus', $input) || array_key_exists('confirmation_status', $input)) {
        $cs = strtoupper(trim((string)($input['confirmationStatus'] ?? $input['confirmation_status'] ?? 'PENDING')));
        if (!in_array($cs, PFG_VENUE_STATUSES, true)) {
            jsonResponse(['error' => 'confirmationStatus must be one of: ' . implode(', ', PFG_VENUE_STATUSES) . '.'], 400);
        }
        $out['confirmation_status'] = $cs;
    }
    if (!$partial || array_key_exists('accessibilityFeatures', $input)) {
        $out['accessibility_features'] = jsonEncode(pfgAdminStringList($input['accessibilityFeatures'] ?? [], 30, 120));
    }
    if (!$partial || array_key_exists('capacity', $input)) {
        $cap = $input['capacity'] ?? null;
        if ($cap === null || $cap === '') {
            $out['capacity'] = null;
        } else {
            if (!is_numeric($cap) || (int)$cap < 0 || (int)$cap > 1000000) {
                jsonResponse(['error' => 'capacity must be a non-negative whole number.'], 400);
            }
            $out['capacity'] = (int)$cap;
        }
    }
    if (!$partial || array_key_exists('mapUrl', $input) || array_key_exists('map_url', $input)) {
        $raw = trim((string)($input['mapUrl'] ?? $input['map_url'] ?? ''));
        if ($raw === '') {
            $out['map_url'] = null;
        } else {
            $safe = pfgValidateSafeUrl($raw, false);
            if ($safe === null || $safe === '' || strlen($safe) > 2000) {
                jsonResponse(['error' => 'mapUrl must be a valid http(s) URL.'], 400);
            }
            $out['map_url'] = $safe;
        }
    }
    if (!$partial || array_key_exists('operationalNotes', $input)) {
        $out['operational_notes'] = pfgAdminCleanText($input['operationalNotes'] ?? null, 4000);
    }
    return $out;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();
    $editionId = pfgAdminEditionId($pdo);
    $hasNotesCol = pfgAdminColumnExists($pdo, 'venues', 'operational_notes');
    $hasUpdatedCol = pfgAdminColumnExists($pdo, 'venues', 'updated_at');

    // =========================================================================
    // GET
    // =========================================================================
    if ($method === 'GET') {
        if (!pfgAdminCanManageProgramme($user)
            && !hasPermission($user, PERMISSIONS['VOLUNTEERS_REVIEW'])
            && !hasPermission($user, PERMISSIONS['SLI_REVIEW'])) {
            pfgAdminRequireProgrammeAccess($user);
        }

        $statusFilter = strtoupper(trim((string)($_GET['status'] ?? '')));
        $search = strtolower(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')));
        $where = ['v.edition_id = :ed'];
        $params = [':ed' => $editionId];
        if ($statusFilter !== '') {
            if (!in_array($statusFilter, PFG_VENUE_STATUSES, true)) {
                jsonResponse(['error' => 'Invalid status filter.'], 400);
            }
            $where[] = 'v.confirmation_status = :st';
            $params[':st'] = $statusFilter;
        } elseif (($_GET['includeArchived'] ?? '') !== '1' && ($_GET['includeArchived'] ?? '') !== 'true') {
            // Archived venues are hidden unless explicitly requested.
            $where[] = "v.confirmation_status <> 'ARCHIVED'";
        }
        if ($search !== '') {
            $where[] = "(LOWER(v.name) LIKE :q OR LOWER(COALESCE(v.address, '')) LIKE :q)";
            $params[':q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }

        $stmt = $pdo->prepare('SELECT v.* FROM venues v WHERE ' . implode(' AND ', $where) . ' ORDER BY v.name ASC');
        $stmt->execute($params);
        $items = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $v) {
            $items[] = pfgVenueFormat($v);
        }
        jsonResponse(['items' => $items, 'total' => count($items), 'statuses' => PFG_VENUE_STATUSES]);
    }

    // =========================================================================
    // POST: create venue
    // =========================================================================
    if ($method === 'POST') {
        pfgAdminRequireProgrammeAccess($user);
        requireCsrfToken($user);
        $input = readRequestInput();
        $f = pfgVenueParseInput($input, false);

        $dup = $pdo->prepare("SELECT id FROM venues WHERE edition_id = :ed AND LOWER(name) = :n AND confirmation_status <> 'ARCHIVED' LIMIT 1");
        $dup->execute([':ed' => $editionId, ':n' => strtolower($f['name'])]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['error' => 'A venue with this name already exists for this edition.'], 409);
        }

        $id = uuid4();
        $cols = ['id' => $id, 'edition_id' => $editionId, 'name' => $f['name'], 'address' => $f['address'],
                 'confirmation_status' => $f['confirmation_status'], 'accessibility_features' => $f['accessibility_features'],
                 'capacity' => $f['capacity'], 'map_url' => $f['map_url']];
        if ($hasNotesCol) {
            $cols['operational_notes'] = $f['operational_notes'];
        }
        $names = array_keys($cols);
        $ins = $pdo->prepare('INSERT INTO venues (' . implode(', ', $names) . ') VALUES (:' . implode(', :', $names) . ')');
        $ins->execute(array_combine(array_map(static fn($n) => ':' . $n, $names), array_values($cols)));

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'VENUE_CREATED',
            'resourceType' => 'venue',
            'resourceId'   => $id,
            'metadata'     => ['name' => $f['name'], 'confirmationStatus' => $f['confirmation_status']],
        ]);

        $row = $pdo->prepare('SELECT * FROM venues WHERE id = :id');
        $row->execute([':id' => $id]);
        jsonResponse(['venue' => pfgVenueFormat($row->fetch(PDO::FETCH_ASSOC))], 201);
    }

    // =========================================================================
    // PATCH: update venue (including status transitions & archive)
    // =========================================================================
    if ($method === 'PATCH' || $method === 'PUT') {
        pfgAdminRequireProgrammeAccess($user);
        requireCsrfToken($user);
        $input = readRequestInput();
        $id = trim((string)($input['id'] ?? $input['venueId'] ?? $_GET['id'] ?? ''));
        if ($id === '') {
            jsonResponse(['error' => 'Venue id is required.'], 400);
        }
        $st = $pdo->prepare('SELECT * FROM venues WHERE id = :id AND edition_id = :ed LIMIT 1');
        $st->execute([':id' => $id, ':ed' => $editionId]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            jsonResponse(['error' => 'Venue not found.'], 404);
        }

        $f = pfgVenueParseInput($input, true);
        if (!$hasNotesCol) {
            unset($f['operational_notes']);
        }
        if (!$f) {
            jsonResponse(['error' => 'No updatable fields were provided.'], 400);
        }

        if (isset($f['name']) && strtolower($f['name']) !== strtolower((string)$existing['name'])) {
            $dup = $pdo->prepare("SELECT id FROM venues WHERE edition_id = :ed AND LOWER(name) = :n AND confirmation_status <> 'ARCHIVED' AND id <> :id LIMIT 1");
            $dup->execute([':ed' => $editionId, ':n' => strtolower($f['name']), ':id' => $id]);
            if ($dup->fetch(PDO::FETCH_ASSOC)) {
                jsonResponse(['error' => 'A venue with this name already exists for this edition.'], 409);
            }
        }

        // Do not archive a venue that still hosts active duty assignments.
        if (($f['confirmation_status'] ?? null) === 'ARCHIVED' && strtoupper((string)$existing['confirmation_status']) !== 'ARCHIVED'
            && pfgAdminTableExists($pdo, 'duty_assignments')) {
            $act = $pdo->prepare("SELECT COUNT(*) FROM duty_assignments WHERE venue_id = :id AND status = 'ASSIGNED'");
            $act->execute([':id' => $id]);
            $activeCount = (int)$act->fetchColumn();
            if ($activeCount > 0) {
                jsonResponse(['error' => "This venue has {$activeCount} active duty assignment(s). Reassign or cancel them before archiving.", 'activeAssignments' => $activeCount], 409);
            }
        }

        $sets = [];
        $params = [':id' => $id];
        foreach ($f as $col => $val) {
            $sets[] = "{$col} = :{$col}";
            $params[':' . $col] = $val;
        }
        if ($hasUpdatedCol) {
            $sets[] = 'updated_at = :updated_at';
            $params[':updated_at'] = nowSql();
        }
        $pdo->prepare('UPDATE venues SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'VENUE_UPDATED',
            'resourceType' => 'venue',
            'resourceId'   => $id,
            'metadata'     => [
                'fields'         => array_keys($f),
                'previousStatus' => $existing['confirmation_status'],
                'newStatus'      => $f['confirmation_status'] ?? $existing['confirmation_status'],
            ],
        ]);

        $st->execute([':id' => $id, ':ed' => $editionId]);
        jsonResponse(['venue' => pfgVenueFormat($st->fetch(PDO::FETCH_ASSOC))]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/venues.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process venue request.'], 500);
}
