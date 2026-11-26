<?php
declare(strict_types=1);

/**
 * POST /api/coupons/validate(.php)
 * Exact PHP + PDO port of src/app/api/coupons/validate/route.ts
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
if (file_exists(__DIR__ . '/../../includes/auth.php')) {
    require_once __DIR__ . '/../../includes/auth.php';
}

if (!function_exists('pfgCouponJson')) {
    function pfgCouponJson(array $payload, int $status = 200): void
    {
        if (function_exists('jsonResponse')) {
            jsonResponse($payload, $status);
            return;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    pfgCouponJson(['valid' => false, 'error' => 'Method not allowed.'], 405);
}

try {
    $input = function_exists('readRequestInput') ? readRequestInput() : [];
    $rawCode = isset($input['code']) ? trim((string)$input['code']) : '';
    $rawCategoryCode = isset($input['categoryCode']) ? trim((string)$input['categoryCode']) : '';

    if (strlen($rawCode) < 2 || strlen($rawCode) > 64) {
        pfgCouponJson([
            'valid' => false,
            'error' => 'Please enter a coupon code.',
        ], 400);
    }

    if (strlen($rawCategoryCode) < 2 || strlen($rawCategoryCode) > 64) {
        pfgCouponJson([
            'valid' => false,
            'error' => 'Invalid coupon input.',
        ], 400);
    }

    $code = strtoupper($rawCode);
    $categoryCode = strtoupper($rawCategoryCode);

    $pdo = getDb();
    if (function_exists('pfgRequireRateLimit')) {
        $clientIp = getClientIp() ?: 'unknown';
        pfgRequireRateLimit($pdo, 'coupon_validate_ip', $clientIp, 30, 900, 900);
    }

    // Resolve active festival edition
    $stmtEd = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
    $stmtEd->execute([':slug' => defined('FESTIVAL_EDITION_SLUG') ? FESTIVAL_EDITION_SLUG : 'gujarat-2027']);
    $edition = $stmtEd->fetch(PDO::FETCH_ASSOC);

    if (!$edition) {
        $stmtEd2 = $pdo->query('SELECT * FROM festival_editions WHERE is_active = 1 LIMIT 1');
        $edition = $stmtEd2 ? $stmtEd2->fetch(PDO::FETCH_ASSOC) : null;
    }

    if (!$edition) {
        pfgCouponJson([
            'valid' => false,
            'error' => 'Active festival edition not found.',
        ], 404);
    }

    // Resolve registration category
    $stmtCat = $pdo->prepare(
        'SELECT * FROM registration_categories WHERE edition_id = :edition_id AND UPPER(code) = :code LIMIT 1'
    );
    $stmtCat->execute([
        ':edition_id' => $edition['id'],
        ':code'       => $categoryCode,
    ]);
    $category = $stmtCat->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        pfgCouponJson([
            'valid' => false,
            'error' => 'Selected registration category not found.',
        ], 400);
    }

    // Lookup coupon in coupons or coupon_codes
    $coupon = null;
    try {
        $stmtC1 = $pdo->prepare(
            'SELECT * FROM coupons WHERE UPPER(code) = :code AND (edition_id = :edition_id OR edition_id IS NULL) LIMIT 1'
        );
        $stmtC1->execute([':code' => $code, ':edition_id' => $edition['id']]);
        $coupon = $stmtC1->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $coupon = null;
    }

    if (!$coupon) {
        try {
            $stmtC2 = $pdo->prepare(
                'SELECT * FROM coupon_codes WHERE UPPER(code) = :code AND (edition_id = :edition_id OR edition_id IS NULL) LIMIT 1'
            );
            $stmtC2->execute([':code' => $code, ':edition_id' => $edition['id']]);
            $coupon = $stmtC2->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $coupon = null;
        }
    }

    if (!$coupon || empty($coupon['is_active'])) {
        pfgCouponJson([
            'valid' => false,
            'error' => 'Coupon code is invalid or no longer active.',
        ], 404);
    }

    $nowTs = time();
    if (!empty($coupon['valid_from'])) {
        $fromTs = strtotime((string)$coupon['valid_from']);
        if ($fromTs !== false && $fromTs > $nowTs) {
            pfgCouponJson([
                'valid' => false,
                'error' => 'This coupon code is not active yet.',
            ], 400);
        }
    }

    if (!empty($coupon['valid_until'])) {
        $untilTs = strtotime((string)$coupon['valid_until']);
        if ($untilTs !== false && $untilTs < $nowTs) {
            pfgCouponJson([
                'valid' => false,
                'error' => 'This coupon code has expired.',
            ], 400);
        }
    }

    if ($coupon['max_uses'] !== null && $coupon['max_uses'] !== '' && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
        pfgCouponJson([
            'valid' => false,
            'error' => 'This coupon code has reached its redemption limit.',
        ], 400);
    }

    $eligible = jsonDecode($coupon['eligible_categories'] ?? '[]', []);
    if (!is_array($eligible)) {
        $eligible = [];
    }
    $eligibleUpper = array_map(static fn($v): string => strtoupper(trim((string)$v)), $eligible);
    if (!empty($eligibleUpper) && !in_array($categoryCode, $eligibleUpper, true)) {
        pfgCouponJson([
            'valid' => false,
            'error' => "Coupon {$code} does not apply to the {$category['name']} category.",
        ], 400);
    }

    $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (is_array($user) && !empty($user['id'])) {
        $stmtPrior = $pdo->prepare(
            'SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = :coupon_id AND user_id = :user_id'
        );
        $stmtPrior->execute([
            ':coupon_id' => $coupon['id'],
            ':user_id'   => $user['id'],
        ]);
        $priorCount = (int)$stmtPrior->fetchColumn();
        $maxPerUser = isset($coupon['max_uses_per_user']) ? (int)$coupon['max_uses_per_user'] : 1;
        if ($priorCount >= max(1, $maxPerUser)) {
            pfgCouponJson([
                'valid' => false,
                'error' => 'You have already redeemed this coupon code.',
            ], 400);
        }
    }

    $baseAmountInr = (int)($category['fee_amount_inr'] ?? 0);
    $discountType = strtoupper((string)($coupon['discount_type'] ?? 'PERCENTAGE'));
    $discountValue = (int)($coupon['discount_value'] ?? 0);

    $discountInr = 0;
    if ($discountType === 'FULL_WAIVER') {
        $discountInr = $baseAmountInr;
    } elseif ($discountType === 'PERCENTAGE') {
        $discountInr = min(
            $baseAmountInr,
            (int)round(($baseAmountInr * $discountValue) / 100)
        );
    } else {
        $discountInr = min($baseAmountInr, $discountValue);
    }

    $netAmountInr = max(0, $baseAmountInr - $discountInr);

    pfgCouponJson([
        'valid' => true,
        'coupon' => [
            'code'          => (string)$coupon['code'],
            'description'   => $coupon['description'] ?? null,
            'discountType'  => $discountType,
            'discountValue' => $discountValue,
        ],
        'pricing' => [
            'baseAmountInr' => $baseAmountInr,
            'discountInr'   => $discountInr,
            'netAmountInr'  => $netAmountInr,
        ],
    ], 200);
} catch (Throwable $e) {
    error_log('POST /api/coupons/validate error: ' . $e->getMessage());
    pfgCouponJson([
        'valid' => false,
        'error' => 'Unable to validate coupon right now.',
    ], 500);
}
