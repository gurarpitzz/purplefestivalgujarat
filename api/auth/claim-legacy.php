<?php
declare(strict_types=1);

/**
 * GET  /api/auth/claim-legacy — lists legacy (pre-Phase-5) registrations that match the verified caller's e-mail
 * POST /api/auth/claim-legacy — re-links them to the caller (CSRF required)
 *
 * Eligibility (all enforced in SQL):
 *   - caller is an authenticated, e-mail-verified, participant-only account
 *   - registrations.email (lower-cased) equals the caller's verified e-mail and deleted_at IS NULL
 *   - current owner is a DIFFERENT passwordless legacy user holding NO SUPERADMIN / ADMIN / RGST role
 *   - registration has never been claimed before (legacy_registration_claims.registration_id is unique)
 * registration_code is never modified. Staff-owned registrations can never be claimed (403).
 */

require_once __DIR__ . '/_participant.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!in_array($method, ['GET', 'POST'], true)) {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

const PFG_STAFF_ROLE_SQL = "('SUPERADMIN','ADMIN','RGST')";

/** Returns true when the user holds any staff role. */
function pfgClaimOwnerIsStaff(PDO $pdo, string $ownerId): bool
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM user_roles ur INNER JOIN roles ro ON ro.id = ur.role_id WHERE ur.user_id = :u AND ro.code IN ' . PFG_STAFF_ROLE_SQL);
    $s->execute([':u' => $ownerId]);
    return (int)$s->fetchColumn() > 0;
}

try {
    $pdo = getDb();
    $user = requireAuth($pdo);

    if (!hasRole($user, ['PARTICIPANT']) || pfgAuthHasStaffRole($user['roles'] ?? [])) {
        jsonResponse(['error' => 'Only participant accounts can claim legacy registrations.'], 403);
    }

    $uStmt = $pdo->prepare('SELECT email, email_verified_at FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $uStmt->execute([':id' => $user['id']]);
    $dbUser = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$dbUser || empty($dbUser['email_verified_at'])) {
        jsonResponse(['error' => 'Please verify your email address before claiming earlier registrations.', 'emailVerificationRequired' => true], 403);
    }
    $email = pfgAuthNormalizeEmail($dbUser['email']);

    $eligibleSql = "
        SELECT r.id, r.registration_code, r.category_code, r.status, r.submitted_at, r.user_id AS owner_id
        FROM registrations r
        INNER JOIN users lu ON lu.id = r.user_id
        WHERE LOWER(r.email) = :email
          AND r.deleted_at IS NULL
          AND r.user_id <> :uid
          AND (lu.password_hash IS NULL OR lu.password_hash = '')
          AND NOT EXISTS (
              SELECT 1 FROM user_roles ur
              INNER JOIN roles ro ON ro.id = ur.role_id
              WHERE ur.user_id = lu.id AND ro.code IN " . PFG_STAFF_ROLE_SQL . "
          )
          AND NOT EXISTS (SELECT 1 FROM legacy_registration_claims c WHERE c.registration_id = r.id)
    ";

    $eligibleStmt = $pdo->prepare($eligibleSql . ' ORDER BY r.created_at ASC');
    $eligibleStmt->execute([':email' => $email, ':uid' => $user['id']]);
    $eligible = $eligibleStmt->fetchAll(PDO::FETCH_ASSOC);

    $present = static function (array $row): array {
        return [
            'registrationId'   => (string)$row['id'],
            'registrationCode' => (string)$row['registration_code'],
            'categoryCode'     => (string)$row['category_code'],
            'status'           => (string)$row['status'],
            'submittedAt'      => $row['submitted_at'] ?? null,
        ];
    };

    if ($method === 'GET') {
        jsonResponse([
            'claimable' => array_map($present, $eligible),
            'count'     => count($eligible),
        ], 200);
    }

    requireCsrfToken($user);
    pfgRequireRateLimit($pdo, 'auth_claim_legacy_user', (string)$user['id'], 10, 3600, 3600, (string)$user['id']);

    $input = readRequestInput();
    $reqIds = [];
    foreach (['registrationIds', 'registrationCodes'] as $key) {
        if (isset($input[$key]) && is_array($input[$key])) {
            foreach ($input[$key] as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $reqIds[$key][] = trim($v);
                }
            }
        }
    }
    foreach (['registrationId', 'registrationCode'] as $key) {
        if (isset($input[$key]) && is_string($input[$key]) && trim($input[$key]) !== '') {
            $reqIds[$key . 's'][] = trim($input[$key]);
        }
    }

    $targets = $eligible;
    if (!empty($reqIds)) {
        // Hard refusal: explicitly requested registrations owned by staff accounts.
        $lookups = [];
        foreach (($reqIds['registrationIds'] ?? []) as $rid) {
            $lookups[] = ['r.id', $rid];
        }
        foreach (($reqIds['registrationCodes'] ?? []) as $code) {
            $lookups[] = ['r.registration_code', $code];
        }
        $wanted = [];
        foreach ($lookups as [$col, $val]) {
            $q = $pdo->prepare("SELECT r.id, r.user_id FROM registrations r WHERE $col = :v AND r.deleted_at IS NULL LIMIT 1");
            $q->execute([':v' => $val]);
            $hit = $q->fetch(PDO::FETCH_ASSOC);
            if ($hit) {
                if (pfgClaimOwnerIsStaff($pdo, (string)$hit['user_id'])) {
                    logAudit([
                        'actorUserId'  => (string)$user['id'],
                        'action'       => 'ACCESS_DENIED',
                        'resourceType' => 'registration',
                        'resourceId'   => (string)$hit['id'],
                        'metadata'     => ['reason' => 'legacy_claim_staff_owned_blocked'],
                    ]);
                    // Uniform 404 (LOW-01): never distinguish staff-owned codes from non-claimable/nonexistent codes
                    jsonResponse(['error' => 'No matching claimable registrations were found for your verified email.', 'claimed' => [], 'count' => 0], 404);
                }
                $wanted[(string)$hit['id']] = true;
            }
        }
        $targets = array_values(array_filter($eligible, static fn(array $r): bool => isset($wanted[(string)$r['id']])));
        if (empty($targets)) {
            jsonResponse(['error' => 'No matching claimable registrations were found for your verified email.', 'claimed' => [], 'count' => 0], 404);
        }
    }

    $claimed = [];
    $ip = getClientIp();
    $now = nowSql();

    $pdo->beginTransaction();
    try {
        foreach ($targets as $row) {
            $upd = $pdo->prepare('UPDATE registrations SET user_id = :uid, updated_at = :now WHERE id = :id AND user_id = :old AND deleted_at IS NULL');
            $upd->execute([':uid' => $user['id'], ':now' => $now, ':id' => $row['id'], ':old' => $row['owner_id']]);
            if ($upd->rowCount() !== 1) {
                continue; // lost a race; skip safely
            }
            $pdo->prepare('UPDATE accommodation_requests SET user_id = :uid, updated_at = :now WHERE registration_id = :rid')
                ->execute([':uid' => $user['id'], ':now' => $now, ':rid' => $row['id']]);
            $pdo->prepare('
                INSERT INTO legacy_registration_claims (id, user_id, registration_id, claimed_email, previous_user_id, ip_address, claimed_at)
                VALUES (:id, :uid, :rid, :em, :prev, :ip, :now)
            ')->execute([
                ':id'   => uuid4(),
                ':uid'  => $user['id'],
                ':rid'  => $row['id'],
                ':em'   => $email,
                ':prev' => $row['owner_id'],
                ':ip'   => $ip,
                ':now'  => $now,
            ]);
            $claimed[] = $row;
        }
        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }

    foreach ($claimed as $row) {
        logAudit([
            'actorUserId'  => (string)$user['id'],
            'action'       => 'LEGACY_REGISTRATION_CLAIMED',
            'resourceType' => 'registration',
            'resourceId'   => (string)$row['id'],
            'metadata'     => [
                'registrationCode' => (string)$row['registration_code'],
                'previousUserId'   => (string)$row['owner_id'],
            ],
        ]);
    }

    jsonResponse([
        'success' => true,
        'claimed' => array_map($present, $claimed),
        'count'   => count($claimed),
        'message' => count($claimed) > 0
            ? 'Your earlier registrations are now linked to your account.'
            : 'No registrations were available to claim.',
    ], 200);
} catch (Throwable $e) {
    error_log('/api/auth/claim-legacy error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process legacy registration claim right now.'], 500);
}
