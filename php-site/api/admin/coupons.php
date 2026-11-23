<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Discount & Waiver Coupons API
 * Endpoint: GET / POST / PATCH /api/admin/coupons (or /api/admin/coupons.php)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    requirePermission(PERMISSIONS['COUPONS_MANAGE'], $user);
    $pdo = getDb();

    // =========================================================================
    // GET: List All Coupons with Redemption Aggregates
    // =========================================================================
    if ($method === 'GET') {
        $redemptionAgg = [];
        try {
            $stRed = $pdo->query('
                SELECT coupon_id, COUNT(*) AS redemption_count, COALESCE(SUM(discount_applied_inr), 0) AS total_discount_inr
                FROM coupon_redemptions
                GROUP BY coupon_id
            ');
            foreach (($stRed ? $stRed->fetchAll(PDO::FETCH_ASSOC) : []) as $ra) {
                $redemptionAgg[(string)$ra['coupon_id']] = [
                    'redemptionCount'  => (int)$ra['redemption_count'],
                    'totalDiscountInr' => (int)$ra['total_discount_inr'],
                ];
            }
        } catch (Throwable $e) {
        }

        $byCode = [];
        foreach (['coupons', 'coupon_codes'] as $tbl) {
            try {
                $st = $pdo->query("SELECT * FROM {$tbl} ORDER BY created_at DESC");
                foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $c) {
                    $codeKey = strtoupper((string)$c['code']);
                    if (isset($byCode[$codeKey])) {
                        continue;
                    }
                    $cid = (string)$c['id'];
                    $agg = $redemptionAgg[$cid] ?? null;
                    $usedCount = (int)($c['used_count'] ?? 0);
                    $byCode[$codeKey] = [
                        'id'                 => $cid,
                        'editionId'          => $c['edition_id'] ?? null,
                        'code'               => (string)$c['code'],
                        'description'        => $c['description'] ?? null,
                        'discountType'       => (string)($c['discount_type'] ?? 'PERCENTAGE'),
                        'discountValue'      => (int)($c['discount_value'] ?? 0),
                        'eligibleCategories' => jsonDecode($c['eligible_categories'] ?? '[]', []),
                        'validFrom'          => $c['valid_from'] ?? null,
                        'validUntil'         => $c['valid_until'] ?? null,
                        'maxUses'            => $c['max_uses'] !== null ? (int)$c['max_uses'] : null,
                        'usedCount'          => $usedCount,
                        'redemptionCount'    => max($usedCount, (int)($agg['redemptionCount'] ?? 0)),
                        'totalDiscountInr'   => (int)($agg['totalDiscountInr'] ?? 0),
                        'maxUsesPerUser'     => (int)($c['max_uses_per_user'] ?? 1),
                        'isActive'           => !empty($c['is_active']),
                        'createdAt'          => (string)($c['created_at'] ?? ''),
                    ];
                }
            } catch (Throwable $e) {
            }
        }

        jsonResponse(['items' => array_values($byCode)]);
    }

    // =========================================================================
    // POST: Create New Coupon
    // =========================================================================
    if ($method === 'POST') {
        $input = readRequestInput();
        $rawCode = strtoupper(trim((string)($input['code'] ?? '')));
        $code = (string)preg_replace('/[^A-Z0-9_-]/', '', $rawCode);

        if (strlen($code) < 3 || strlen($code) > 64) {
            jsonResponse(['error' => 'Coupon code must be between 3 and 64 alphanumeric characters.'], 400);
        }

        $discountType = strtoupper(trim((string)($input['discountType'] ?? 'PERCENTAGE')));
        if (!in_array($discountType, ['PERCENTAGE', 'FIXED_AMOUNT', 'FULL_WAIVER'], true)) {
            jsonResponse(['error' => 'Invalid discountType.'], 400);
        }

        $discountValue = $discountType === 'FULL_WAIVER' ? 100 : max(0, min(100000, (int)($input['discountValue'] ?? 0)));
        if ($discountType === 'PERCENTAGE' && $discountValue > 100) {
            jsonResponse(['error' => 'Percentage discount cannot exceed 100%.'], 400);
        }

        $description = trim((string)($input['description'] ?? ''));
        $eligibleCategories = isset($input['eligibleCategories']) && is_array($input['eligibleCategories'])
            ? array_values(array_map('strval', $input['eligibleCategories']))
            : [];
        $maxUses = isset($input['maxUses']) && $input['maxUses'] !== '' && $input['maxUses'] !== null
            ? max(1, (int)$input['maxUses'])
            : null;
        $maxUsesPerUser = isset($input['maxUsesPerUser']) ? max(1, min(20, (int)$input['maxUsesPerUser'])) : 1;
        $validFrom = !empty($input['validFrom']) ? date('Y-m-d H:i:s', strtotime((string)$input['validFrom'])) : null;
        $validUntil = !empty($input['validUntil']) ? date('Y-m-d H:i:s', strtotime((string)$input['validUntil'])) : null;
        $isActive = !array_key_exists('isActive', $input) || !empty($input['isActive']);

        // Check active edition
        $stEd = $pdo->prepare('SELECT id FROM festival_editions WHERE slug = :slug LIMIT 1');
        $stEd->execute([':slug' => FESTIVAL_EDITION_SLUG]);
        $edRow = $stEd->fetch(PDO::FETCH_ASSOC);
        if (!$edRow) {
            $anyEd = $pdo->query('SELECT id FROM festival_editions LIMIT 1');
            $edRow = $anyEd ? $anyEd->fetch(PDO::FETCH_ASSOC) : null;
        }
        $editionId = $edRow ? (string)$edRow['id'] : null;

        // Check duplicate code
        foreach (['coupons', 'coupon_codes'] as $tbl) {
            try {
                $stDup = $pdo->prepare("SELECT id FROM {$tbl} WHERE UPPER(code) = :code LIMIT 1");
                $stDup->execute([':code' => $code]);
                if ($stDup->fetch(PDO::FETCH_ASSOC)) {
                    jsonResponse(['error' => 'A coupon with this code already exists.'], 409);
                }
            } catch (Throwable $e) {
            }
        }

        $id = uuid4();
        $now = nowSql();

        foreach (['coupons', 'coupon_codes'] as $tbl) {
            try {
                $ins = $pdo->prepare("
                    INSERT INTO {$tbl} (
                        id, edition_id, code, description, discount_type, discount_value,
                        eligible_categories, valid_from, valid_until, max_uses, used_count,
                        max_uses_per_user, is_active, created_by, created_at
                    ) VALUES (
                        :id, :eid, :code, :desc, :dtype, :dval, :cats, :vfrom, :vuntil,
                        :max_uses, 0, :max_user, :active, :uid, :now
                    )
                ");
                $ins->execute([
                    ':id'       => $id,
                    ':eid'      => $editionId,
                    ':code'     => $code,
                    ':desc'     => $description !== '' ? $description : null,
                    ':dtype'    => $discountType,
                    ':dval'     => $discountValue,
                    ':cats'     => jsonEncode($eligibleCategories),
                    ':vfrom'    => $validFrom,
                    ':vuntil'   => $validUntil,
                    ':max_uses' => $maxUses,
                    ':max_user' => $maxUsesPerUser,
                    ':active'   => $isActive ? 1 : 0,
                    ':uid'      => $user['id'],
                    ':now'      => $now,
                ]);
            } catch (Throwable $e) {
            }
        }

        $created = [
            'id'                 => $id,
            'editionId'          => $editionId,
            'code'               => $code,
            'description'        => $description !== '' ? $description : null,
            'discountType'       => $discountType,
            'discountValue'      => $discountValue,
            'eligibleCategories' => $eligibleCategories,
            'validFrom'          => $validFrom,
            'validUntil'         => $validUntil,
            'maxUses'            => $maxUses,
            'usedCount'          => 0,
            'redemptionCount'    => 0,
            'totalDiscountInr'   => 0,
            'maxUsesPerUser'     => $maxUsesPerUser,
            'isActive'           => $isActive,
            'createdAt'          => $now,
        ];

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'COUPON_CREATED',
            'resourceType' => 'coupon_code',
            'resourceId'   => $id,
            'metadata'     => [
                'code'          => $code,
                'discountType'  => $discountType,
                'discountValue' => $discountValue,
            ],
        ]);

        jsonResponse(['item' => $created], 201);
    }

    // =========================================================================
    // PATCH: Toggle Active Status or Update Coupon
    // =========================================================================
    if ($method === 'PATCH') {
        $input = readRequestInput();
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            jsonResponse(['error' => 'Coupon id is required.'], 400);
        }

        $existing = null;
        foreach (['coupons', 'coupon_codes'] as $tbl) {
            try {
                $st = $pdo->prepare("SELECT * FROM {$tbl} WHERE id = :id LIMIT 1");
                $st->execute([':id' => $id]);
                $found = $st->fetch(PDO::FETCH_ASSOC);
                if ($found) {
                    $existing = $found;
                    break;
                }
            } catch (Throwable $e) {
            }
        }

        if (!$existing) {
            jsonResponse(['error' => 'Coupon not found.'], 404);
        }

        $nextActive = array_key_exists('isActive', $input)
            ? (!empty($input['isActive']) ? 1 : 0)
            : (int)$existing['is_active'];
        $nextDesc = array_key_exists('description', $input)
            ? (trim((string)$input['description']) ?: null)
            : $existing['description'];
        $nextMaxUses = array_key_exists('maxUses', $input)
            ? ($input['maxUses'] !== null && $input['maxUses'] !== '' ? max(1, (int)$input['maxUses']) : null)
            : $existing['max_uses'];

        foreach (['coupons', 'coupon_codes'] as $tbl) {
            try {
                $up = $pdo->prepare("
                    UPDATE {$tbl}
                    SET is_active = :active,
                        description = :desc,
                        max_uses = :max_uses
                    WHERE id = :id OR UPPER(code) = :code
                ");
                $up->execute([
                    ':active'   => $nextActive,
                    ':desc'     => $nextDesc,
                    ':max_uses' => $nextMaxUses,
                    ':id'       => $id,
                    ':code'     => strtoupper((string)$existing['code']),
                ]);
            } catch (Throwable $e) {
            }
        }

        $updated = [
            'id'            => $id,
            'code'          => (string)$existing['code'],
            'description'   => $nextDesc,
            'discountType'  => (string)$existing['discount_type'],
            'discountValue' => (int)$existing['discount_value'],
            'maxUses'       => $nextMaxUses !== null ? (int)$nextMaxUses : null,
            'usedCount'     => (int)($existing['used_count'] ?? 0),
            'isActive'      => (bool)$nextActive,
        ];

        logAudit([
            'actorUserId'  => $user['id'],
            'action'       => 'COUPON_UPDATED',
            'resourceType' => 'coupon_code',
            'resourceId'   => $id,
            'metadata'     => ['code' => $existing['code'], 'isActive' => (bool)$nextActive],
        ]);

        jsonResponse(['item' => $updated]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/coupons.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process coupon request.'], 500);
}
