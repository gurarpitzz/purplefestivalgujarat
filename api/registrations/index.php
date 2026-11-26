<?php
declare(strict_types=1);

/**
 * GET & POST /api/registrations(/index.php)
 * Exact PHP + PDO MySQL/SQLite port of src/app/api/registrations/route.ts
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
if (file_exists(__DIR__ . '/../../includes/auth.php')) {
    require_once __DIR__ . '/../../includes/auth.php';
}
require_once __DIR__ . '/../../includes/registration-id.php';
require_once __DIR__ . '/../../includes/mailer.php';

if (!function_exists('pfgRegJson')) {
    function pfgRegJson(array $payload, int $status = 200): void
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

function pfgGetActiveEdition(PDO $pdo): ?array
{
    $slug = defined('FESTIVAL_EDITION_SLUG') ? FESTIVAL_EDITION_SLUG : 'gujarat-2027';
    $stmt = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $bySlug = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($bySlug) {
        return $bySlug;
    }

    $stmt2 = $pdo->query('SELECT * FROM festival_editions WHERE is_active = 1 LIMIT 1');
    $active = $stmt2 ? $stmt2->fetch(PDO::FETCH_ASSOC) : null;
    return $active ?: null;
}

function pfgCreateUserSessionCompat(PDO $pdo, string $userId, ?string $ipAddress, ?string $userAgent): bool
{
    if (function_exists('createSession')) {
        try {
            $ref = new ReflectionFunction('createSession');
            $numParams = $ref->getNumberOfParameters();
            if ($numParams >= 2) {
                $firstParam = $ref->getParameters()[0];
                $firstType = $firstParam->getType();
                if ($firstType instanceof ReflectionNamedType && $firstType->getName() === 'PDO') {
                    createSession($pdo, $userId);
                    return true;
                }
                createSession($userId, [
                    'ipAddress' => $ipAddress,
                    'userAgent' => $userAgent,
                ]);
                return true;
            }
            createSession($userId);
            return true;
        } catch (Throwable $e) {
            // Fall through to direct session creation
        }
    }

    // Fallback direct session creation
    try {
        $rawToken = bin2hex(random_bytes(32));
        $secret = defined('AUTH_SECRET') ? (string)AUTH_SECRET : 'pfg2027-prod-auth-secret-change-in-env';
        $tokenHash = hash_hmac('sha256', $rawToken, $secret);
        $duration = defined('SESSION_DURATION_SECONDS') ? (int)SESSION_DURATION_SECONDS : (60 * 60 * 24 * 7);
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);

        $stmt = $pdo->prepare(
            'INSERT INTO sessions (id, user_id, token_hash, ip_address, user_agent, mfa_verified, expires_at, created_at)
             VALUES (:id, :user_id, :token_hash, :ip_address, :user_agent, 0, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':id'         => uuid4(),
            ':user_id'    => $userId,
            ':token_hash' => $tokenHash,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':expires_at' => $expiresAt,
            ':created_at' => nowSql(),
        ]);

        $cookieName = defined('SESSION_COOKIE_NAME') ? (string)SESSION_COOKIE_NAME : 'pfg_session';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            setcookie($cookieName, $rawToken, [
                'expires'  => time() + $duration,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$cookieName] = $rawToken;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function pfgQueueNotificationCompat(
    PDO $pdo,
    string $templateCode,
    ?string $recipientUserId,
    string $recipientEmail,
    string $subject,
    array $variables
): void {
    $now = nowSql();
    $safeVars = function_exists('pfgScrubSensitiveArray') ? pfgScrubSensitiveArray($variables) : $variables;
    $payloadJson = jsonEncode($safeVars);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO notification_queue (
                id, template_code, event_type, channel, recipient_user_id, recipient_email, subject, payload, status, provider, attempts, max_attempts, created_at, updated_at
             ) VALUES (
                :id, :template_code, :event_type, 'EMAIL', :recipient_user_id, :recipient_email, :subject, :payload, 'QUEUED', 'queue', 0, 3, :created_at, :updated_at
             )"
        );
        $stmt->execute([
            ':id'                => uuid4(),
            ':template_code'     => $templateCode,
            ':event_type'        => $templateCode,
            ':recipient_user_id' => $recipientUserId,
            ':recipient_email'   => $recipientEmail,
            ':subject'           => $subject,
            ':payload'           => $payloadJson,
            ':created_at'        => $now,
            ':updated_at'        => $now,
        ]);
    } catch (Throwable $e) {
        error_log('[Registrations] notification_queue insert skipped: ' . $e->getMessage());
    }
}

function pfgRecordAuditCompat(
    PDO $pdo,
    ?string $actorUserId,
    string $action,
    string $resourceType,
    ?string $resourceId,
    ?string $ipAddress,
    ?string $userAgent,
    array $metadata = []
): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (
                id, actor_user_id, action, resource_type, resource_id, ip_address, user_agent, metadata, created_at
             ) VALUES (
                :id, :actor_user_id, :action, :resource_type, :resource_id, :ip_address, :user_agent, :metadata, :created_at
             )'
        );
        $stmt->execute([
            ':id'            => uuid4(),
            ':actor_user_id' => $actorUserId,
            ':action'        => $action,
            ':resource_type' => $resourceType,
            ':resource_id'   => $resourceId,
            ':ip_address'    => $ipAddress,
            ':user_agent'    => $userAgent,
            ':metadata'      => jsonEncode($metadata),
            ':created_at'    => nowSql(),
        ]);
    } catch (Throwable $e) {
        // Do not fail registration if audit log insert fails
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ============================================================================
// GET /api/registrations
// ============================================================================
if ($method === 'GET') {
    try {
        $pdo = getDb();
        $edition = pfgGetActiveEdition($pdo);
        if (!$edition) {
            pfgRegJson(['error' => 'No active festival edition configured.'], 404);
        }

        $stmtCat = $pdo->prepare(
            'SELECT * FROM registration_categories WHERE edition_id = :eid AND is_active = 1 ORDER BY display_order ASC'
        );
        $stmtCat->execute([':eid' => $edition['id']]);
        $catRows = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $categories = [];
        $categoryNameMap = [];
        foreach ($catRows as $c) {
            $categoryNameMap[$c['code']] = $c['name'];
            $categories[] = [
                'id'               => (string)$c['id'],
                'editionId'        => (string)$c['edition_id'],
                'code'             => (string)$c['code'],
                'name'             => (string)$c['name'],
                'description'      => $c['description'] ?? null,
                'requiresApproval' => !empty($c['requires_approval']),
                'requiresPayment'  => !empty($c['requires_payment']),
                'feeAmountInr'     => (int)($c['fee_amount_inr'] ?? 0),
                'isActive'         => !empty($c['is_active']),
                'displayOrder'     => (int)($c['display_order'] ?? 0),
            ];
        }

        $stmtDis = $pdo->query('SELECT * FROM disability_categories ORDER BY display_order ASC');
        $disRows = $stmtDis ? $stmtDis->fetchAll(PDO::FETCH_ASSOC) : [];
        $disabilityCategories = [];
        foreach ($disRows as $d) {
            $disabilityCategories[] = [
                'id'                    => (string)$d['id'],
                'code'                  => (string)$d['code'],
                'name'                  => (string)$d['name'],
                'rpwdGroup'             => (string)($d['rpwd_group'] ?? ''),
                'description'           => (string)($d['description'] ?? ''),
                'accommodationExamples' => jsonDecode($d['accommodation_examples'] ?? '[]', []),
                'displayOrder'          => (int)($d['display_order'] ?? 0),
            ];
        }

        $stmtAcc = $pdo->query('SELECT * FROM accommodation_types WHERE is_active = 1 ORDER BY display_order ASC');
        $accRows = $stmtAcc ? $stmtAcc->fetchAll(PDO::FETCH_ASSOC) : [];
        $accommodationTypes = [];
        foreach ($accRows as $a) {
            $accommodationTypes[] = [
                'id'           => (string)$a['id'],
                'code'         => (string)$a['code'],
                'label'        => (string)$a['label'],
                'description'  => $a['description'] ?? null,
                'isActive'     => !empty($a['is_active']),
                'displayOrder' => (int)($a['display_order'] ?? 0),
            ];
        }

        $user = function_exists('getCurrentUser') ? getCurrentUser() : null;
        if (!$user || empty($user['id'])) {
            pfgRegJson([
                'edition'              => $edition,
                'categories'           => $categories,
                'disabilityCategories' => $disabilityCategories,
                'accommodationTypes'   => $accommodationTypes,
                'user'                 => null,
                'registrations'        => [],
            ]);
        }

        $stmtRegs = $pdo->prepare(
            'SELECT * FROM registrations WHERE user_id = :uid AND deleted_at IS NULL ORDER BY created_at DESC'
        );
        $stmtRegs->execute([':uid' => $user['id']]);
        $userRegs = $stmtRegs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $enrichedRegistrations = [];
        foreach ($userRegs as $reg) {
            $regId = (string)$reg['id'];

            $stmtCred = $pdo->prepare(
                "SELECT * FROM qr_credentials WHERE registration_id = :rid AND status = 'ACTIVE' ORDER BY issued_at DESC LIMIT 1"
            );
            $stmtCred->execute([':rid' => $regId]);
            $activeCred = $stmtCred->fetch(PDO::FETCH_ASSOC);

            $activeCredObj = null;
            if ($activeCred) {
                $qrToken = function_exists('reconstructQrTokenForCredential')
                    ? reconstructQrTokenForCredential($activeCred)
                    : (string)$activeCred['credential_code'];
                $activeCredObj = [
                    'id'             => (string)$activeCred['id'],
                    'credentialCode' => (string)$activeCred['credential_code'],
                    'status'         => (string)$activeCred['status'],
                    'issuedAt'       => (string)$activeCred['issued_at'],
                    'rawToken'       => $qrToken,
                    'qrDataUrl'      => function_exists('generateQrSvgDataUrl')
                        ? generateQrSvgDataUrl($qrToken)
                        : null,
                ];
            }

            $stmtVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
            $stmtVol->execute([':rid' => $regId]);
            $volApp = $stmtVol->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmtSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
            $stmtSli->execute([':rid' => $regId]);
            $sliApp = $stmtSli->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmtDel = $pdo->prepare('SELECT * FROM delegate_details WHERE registration_id = :rid LIMIT 1');
            $stmtDel->execute([':rid' => $regId]);
            $delDetail = $stmtDel->fetch(PDO::FETCH_ASSOC) ?: null;

            $accommReqs = [];
            $stmtAccomm = $pdo->prepare('SELECT * FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC');
            $stmtAccomm->execute([':rid' => $regId]);
            $rows = $stmtAccomm->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $accommReqs[] = [
                    'id'              => (string)$r['id'],
                    'registrationId'  => (string)$r['registration_id'],
                    'selectedTypes'   => jsonDecode($r['selected_types'] ?? '[]', []),
                    'freeTextDetails' => pfgDecryptValue($r['free_text_details'] ?? $r['notes'] ?? null, 'data'),
                    'status'          => (string)($r['status'] ?? 'REQUESTED'),
                    'createdAt'       => (string)$r['created_at'],
                ];
            }

            $stmtChk = $pdo->prepare('SELECT * FROM checkins WHERE registration_id = :rid ORDER BY checked_in_at DESC');
            $stmtChk->execute([':rid' => $regId]);
            $userCheckins = $stmtChk->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $enrichedRegistrations[] = [
                'id'                     => $regId,
                'registrationCode'       => (string)$reg['registration_code'],
                'editionId'              => (string)$reg['edition_id'],
                'userId'                 => (string)$reg['user_id'],
                'categoryId'             => (string)$reg['category_id'],
                'categoryCode'           => (string)$reg['category_code'],
                'categoryName'           => $categoryNameMap[$reg['category_code']] ?? (string)$reg['category_code'],
                'status'                 => (string)$reg['status'],
                'paymentStatus'          => (string)$reg['payment_status'],
                'fullName'               => (string)$reg['full_name'],
                'email'                  => (string)$reg['email'],
                'phone'                  => (string)$reg['phone'],
                'country'                => (string)$reg['country'],
                'occupation'             => $reg['occupation'] ?? null,
                'organizationName'       => $reg['organization_name'] ?? null,
                'designation'            => $reg['designation'] ?? null,
                'isPersonWithDisability' => (string)$reg['is_person_with_disability'],
                'attendingDays'          => jsonDecode($reg['attending_days'] ?? '[]', []),
                'interestedTracks'       => jsonDecode($reg['interested_tracks'] ?? '[]', []),
                'submittedAt'            => (string)($reg['submitted_at'] ?? $reg['created_at']),
                'createdAt'              => (string)$reg['created_at'],
                'activeCredential'       => $activeCredObj,
                'volunteerApplication'   => $volApp,
                'sliApplication'         => $sliApp,
                'delegateDetails'        => $delDetail,
                'accommodationRequests'  => $accommReqs,
                'checkins'               => $userCheckins,
            ];
        }

        pfgRegJson([
            'edition'              => $edition,
            'categories'           => $categories,
            'disabilityCategories' => $disabilityCategories,
            'accommodationTypes'   => $accommodationTypes,
            'user'                 => $user,
            'registrations'        => $enrichedRegistrations,
        ]);
    } catch (Throwable $e) {
        error_log('GET /api/registrations error: ' . $e->getMessage());
        pfgRegJson(['error' => 'Unable to load registration data.'], 500);
    }
}

// ============================================================================
// POST /api/registrations
// ============================================================================
if ($method !== 'POST') {
    pfgRegJson(['error' => 'Method not allowed.'], 405);
}

try {
    $body = function_exists('readRequestInput') ? readRequestInput() : [];
    if (!is_array($body) || empty($body)) {
        pfgRegJson(['error' => 'Please check the highlighted form fields.'], 400);
    }

    // 1. Validate inputs matching src/lib/validation.ts (baseRegistrationSchema)
    $fullName = trim((string)($body['fullName'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $phone = trim((string)($body['phone'] ?? ''));
    $country = trim((string)($body['country'] ?? 'India'));
    if ($country === '') {
        $country = 'India';
    }
    $password = isset($body['password']) && is_string($body['password']) ? $body['password'] : '';
    $categoryCode = strtoupper(trim((string)($body['categoryCode'] ?? '')));
    $occupation = trim((string)($body['occupation'] ?? ''));
    $organizationName = trim((string)($body['organizationName'] ?? ''));
    $designation = trim((string)($body['designation'] ?? ''));
    $isPersonWithDisability = strtoupper(trim((string)($body['isPersonWithDisability'] ?? 'PREFER_NOT_TO_SAY')));

    $disabilityCategoryCodes = isset($body['disabilityCategoryCodes']) && is_array($body['disabilityCategoryCodes'])
        ? array_values(array_filter(array_map('strval', $body['disabilityCategoryCodes'])))
        : [];
    $disabilitySelfDescription = trim((string)($body['disabilitySelfDescription'] ?? ''));

    $accommodationTypes = isset($body['accommodationTypes']) && is_array($body['accommodationTypes'])
        ? array_values(array_filter(array_map('strval', $body['accommodationTypes'])))
        : [];
    $accommodationFreeText = trim((string)($body['accommodationFreeText'] ?? ''));

    $attendingDays = isset($body['attendingDays']) && is_array($body['attendingDays'])
        ? array_values(array_filter(array_map('strval', $body['attendingDays'])))
        : [];
    $interestedTracks = isset($body['interestedTracks']) && is_array($body['interestedTracks'])
        ? array_values(array_filter(array_map('strval', $body['interestedTracks'])))
        : [];
    $couponCode = trim((string)($body['couponCode'] ?? ''));

    $consentZeroTolerance = filter_var($body['consentZeroTolerance'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $consentDataProcessing = filter_var($body['consentDataProcessing'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $consentMediaPhotography = filter_var($body['consentMediaPhotography'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 200) {
        pfgRegJson(['error' => 'Please enter your full name.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        pfgRegJson(['error' => 'Please enter a valid email address.'], 400);
    }
    if (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone)) {
        pfgRegJson(['error' => 'Please enter a valid phone number (e.g., +91 98250 12345).'], 400);
    }
    if (mb_strlen($country) < 2 || mb_strlen($country) > 100) {
        pfgRegJson(['error' => 'Please enter your country.'], 400);
    }
    if ($password !== '' && strlen($password) < 8) {
        pfgRegJson(['error' => 'Password must be at least 8 characters.'], 400);
    }
    if (strlen($categoryCode) < 2 || strlen($categoryCode) > 64) {
        pfgRegJson(['error' => 'Please choose a registration category.'], 400);
    }
    if (mb_strlen($occupation) < 2 || mb_strlen($occupation) > 120) {
        pfgRegJson(['error' => 'Please select your occupation or affiliation.'], 400);
    }
    if (!in_array($isPersonWithDisability, ['YES', 'NO', 'PREFER_NOT_TO_SAY'], true)) {
        pfgRegJson(['error' => 'Invalid disability disclosure option.'], 400);
    }
    if (!$consentZeroTolerance) {
        pfgRegJson(['error' => 'You must acknowledge the zero-tolerance policy towards harassment.'], 400);
    }
    if (!$consentDataProcessing) {
        pfgRegJson(['error' => 'You must consent to the processing of your registration information.'], 400);
    }

    $delegateDetails = isset($body['delegateDetails']) && is_array($body['delegateDetails'])
        ? $body['delegateDetails']
        : null;
    $volunteerDetails = isset($body['volunteerDetails']) && is_array($body['volunteerDetails'])
        ? $body['volunteerDetails']
        : null;
    $sliDetails = isset($body['sliDetails']) && is_array($body['sliDetails'])
        ? $body['sliDetails']
        : null;

    if ($sliDetails && !empty($sliDetails['videoDemoUrl'])) {
        $videoUrl = trim((string)$sliDetails['videoDemoUrl']);
        if ($videoUrl !== '') {
            $safeVideoUrl = pfgValidateSafeUrl($videoUrl, false);
            if ($safeVideoUrl === null) {
                pfgRegJson(['error' => 'Please enter a valid http:// or https:// video demo URL.'], 400);
            }
            $sliDetails['videoDemoUrl'] = $safeVideoUrl;
        }
    }

    $pdo = getDb();
    if (function_exists('pfgRequireRateLimit')) {
        $clientIp = getClientIp() ?: 'unknown';
        pfgRequireRateLimit($pdo, 'public_registration_ip', $clientIp, 25, 900, 900);
    }
    $edition = pfgGetActiveEdition($pdo);
    if (!$edition) {
        pfgRegJson(['error' => 'Festival registration is not currently active.'], 404);
    }

    $stmtCat = $pdo->prepare(
        'SELECT * FROM registration_categories WHERE edition_id = :eid AND UPPER(code) = :code AND is_active = 1 LIMIT 1'
    );
    $stmtCat->execute([
        ':eid'  => $edition['id'],
        ':code' => $categoryCode,
    ]);
    $category = $stmtCat->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        pfgRegJson(['error' => 'Selected registration category is not available.'], 400);
    }

    $nowTs = time();
    if (!empty($category['opens_at'])) {
        $opensTs = strtotime((string)$category['opens_at']);
        if ($opensTs !== false && $opensTs > $nowTs) {
            pfgRegJson(['error' => 'Registration for this category has not opened yet.'], 400);
        }
    }
    if (!empty($category['closes_at'])) {
        $closesTs = strtotime((string)$category['closes_at']);
        if ($closesTs !== false && $closesTs < $nowTs) {
            pfgRegJson(['error' => 'Registration for this category has closed.'], 400);
        }
    }

    $ipAddress = getClientIp();
    $userAgent = getClientUserAgent();

    // Optional Idempotency-Key (header or request payload) for safe retriable submissions (Phase 3I)
    $rawIdempotencyKey = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $body['idempotencyKey'] ?? $body['idempotency_key'] ?? ''));
    $idempotencyKey = $rawIdempotencyKey !== '' ? substr(preg_replace('/[^A-Za-z0-9_\-:.]/', '', $rawIdempotencyKey) ?? '', 0, 128) : null;
    if ($idempotencyKey === '') {
        $idempotencyKey = null;
    }

    // 2. Identity & ownership protection (FND-07):
    // NEVER allow an unauthenticated caller to attach a new registration to ANY existing user record
    // (participant or staff) and NEVER leak existingRegistrationCode to unauthenticated requests.
    $currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
    $normalizedEmail = strtolower($email);
    $userId = '';
    $needsNewUser = false;
    $passwordHash = null;

    if (is_array($currentUser) && !empty($currentUser['id'])) {
        requireCsrfToken($currentUser);
        if (strtolower((string)($currentUser['email'] ?? '')) !== $normalizedEmail) {
            pfgRegJson([
                'error' => 'Authenticated submissions must use your signed-in account email address.',
            ], 403);
        }
        $userId = (string)$currentUser['id'];
    } else {
        // Check idempotency replay FIRST before rejecting existing email (only if exact email + key match)
        if ($idempotencyKey !== null) {
            $stmtIdem = $pdo->prepare(
                'SELECT * FROM registrations WHERE edition_id = :eid AND idempotency_key = :ikey AND LOWER(email) = :email AND deleted_at IS NULL LIMIT 1'
            );
            $stmtIdem->execute([
                ':eid'   => $edition['id'],
                ':ikey'  => $idempotencyKey,
                ':email' => $normalizedEmail,
            ]);
            $idemReg = $stmtIdem->fetch(PDO::FETCH_ASSOC);
            if ($idemReg) {
                pfgRegJson([
                    'registration' => [
                        'id'                     => (string)$idemReg['id'],
                        'registrationCode'       => (string)$idemReg['registration_code'],
                        'editionId'              => (string)$idemReg['edition_id'],
                        'userId'                 => (string)$idemReg['user_id'],
                        'categoryId'             => (string)$idemReg['category_id'],
                        'categoryCode'           => (string)$idemReg['category_code'],
                        'status'                 => (string)$idemReg['status'],
                        'paymentStatus'          => (string)$idemReg['payment_status'],
                        'fullName'               => (string)$idemReg['full_name'],
                        'email'                  => (string)$idemReg['email'],
                        'phone'                  => (string)$idemReg['phone'],
                        'country'                => (string)$idemReg['country'],
                        'occupation'             => $idemReg['occupation'] ?? null,
                        'organizationName'       => $idemReg['organization_name'] ?? null,
                        'designation'            => $idemReg['designation'] ?? null,
                        'isPersonWithDisability' => (string)$idemReg['is_person_with_disability'],
                        'attendingDays'          => jsonDecode($idemReg['attending_days'] ?? '[]', []),
                        'interestedTracks'       => jsonDecode($idemReg['interested_tracks'] ?? '[]', []),
                        'submittedAt'            => (string)($idemReg['submitted_at'] ?? $idemReg['created_at']),
                        'createdAt'              => (string)$idemReg['created_at'],
                    ],
                    'category' => [
                        'code'             => (string)$category['code'],
                        'name'             => (string)$category['name'],
                        'requiresApproval' => !empty($category['requires_approval']),
                        'requiresPayment'  => !empty($category['requires_payment']),
                    ],
                    'idempotentReplay' => true,
                ], 200);
            }
        }

        $stmtUser = $pdo->prepare('SELECT id, is_active, deleted_at FROM users WHERE LOWER(email) = :email LIMIT 1');
        $stmtUser->execute([':email' => $normalizedEmail]);
        $existingUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            pfgRecordAuditCompat(
                $pdo,
                null,
                'UNAUTHORIZED_ACCOUNT_ATTACHMENT_BLOCKED',
                'user',
                (string)$existingUser['id'],
                $ipAddress,
                $userAgent,
                ['categoryCode' => $categoryCode]
            );
            pfgRegJson([
                'error' => 'Unable to complete registration with this email address. If you already registered or hold an account, please contact the festival secretariat.',
            ], 409);
        }

        $needsNewUser = true;
        $userId = uuid4();
        if ($password !== '') {
            $passwordHash = function_exists('hashPassword')
                ? hashPassword($password)
                : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }
    }

    // Authenticated idempotency replay check
    if (!$needsNewUser && $idempotencyKey !== null) {
        $stmtIdem = $pdo->prepare(
            'SELECT * FROM registrations WHERE edition_id = :eid AND idempotency_key = :ikey AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmtIdem->execute([
            ':eid'  => $edition['id'],
            ':ikey' => $idempotencyKey,
            ':uid'  => $userId,
        ]);
        $idemReg = $stmtIdem->fetch(PDO::FETCH_ASSOC);
        if ($idemReg) {
            pfgRegJson([
                'registration' => [
                    'id'                     => (string)$idemReg['id'],
                    'registrationCode'       => (string)$idemReg['registration_code'],
                    'editionId'              => (string)$idemReg['edition_id'],
                    'userId'                 => (string)$idemReg['user_id'],
                    'categoryId'             => (string)$idemReg['category_id'],
                    'categoryCode'           => (string)$idemReg['category_code'],
                    'status'                 => (string)$idemReg['status'],
                    'paymentStatus'          => (string)$idemReg['payment_status'],
                    'fullName'               => (string)$idemReg['full_name'],
                    'email'                  => (string)$idemReg['email'],
                    'phone'                  => (string)$idemReg['phone'],
                    'country'                => (string)$idemReg['country'],
                    'occupation'             => $idemReg['occupation'] ?? null,
                    'organizationName'       => $idemReg['organization_name'] ?? null,
                    'designation'            => $idemReg['designation'] ?? null,
                    'isPersonWithDisability' => (string)$idemReg['is_person_with_disability'],
                    'attendingDays'          => jsonDecode($idemReg['attending_days'] ?? '[]', []),
                    'interestedTracks'       => jsonDecode($idemReg['interested_tracks'] ?? '[]', []),
                    'submittedAt'            => (string)($idemReg['submitted_at'] ?? $idemReg['created_at']),
                    'createdAt'              => (string)$idemReg['created_at'],
                ],
                'category' => [
                    'code'             => (string)$category['code'],
                    'name'             => (string)$category['name'],
                    'requiresApproval' => !empty($category['requires_approval']),
                    'requiresPayment'  => !empty($category['requires_payment']),
                ],
                'idempotentReplay' => true,
            ], 200);
        }
    }

    // 3. Prevent duplicate active registration in the SAME category for this edition
    if (!$needsNewUser) {
        $stmtDup = $pdo->prepare(
            "SELECT * FROM registrations
             WHERE user_id = :uid
               AND edition_id = :eid
               AND category_code = :ccode
               AND deleted_at IS NULL
               AND status NOT IN ('WITHDRAWN', 'CANCELLED', 'REJECTED')
             LIMIT 1"
        );
        $stmtDup->execute([
            ':uid'   => $userId,
            ':eid'   => $edition['id'],
            ':ccode' => $categoryCode,
        ]);
        $duplicateReg = $stmtDup->fetch(PDO::FETCH_ASSOC);

        if ($duplicateReg) {
            $dupPayload = [
                'error' => "You already have an active {$category['name']} registration. You can still register for a different role under your account.",
            ];
            if (is_array($currentUser) && !empty($currentUser['id'])) {
                $dupPayload['existingRegistrationCode'] = (string)$duplicateReg['registration_code'];
            }
            pfgRegJson($dupPayload, 409);
        }
    }

    // 4. Server-side coupon validation & fee calculation (canonical `coupons` table ONLY)
    $feeAmountInr = (int)($category['fee_amount_inr'] ?? 0);
    $discountInr = 0;
    $appliedCoupon = null;

    if ($couponCode !== '') {
        $normalizedCouponCode = strtoupper($couponCode);
        $stmtC = $pdo->prepare(
            'SELECT * FROM coupons WHERE UPPER(code) = :code AND (edition_id = :eid OR edition_id IS NULL) LIMIT 1'
        );
        $stmtC->execute([':code' => $normalizedCouponCode, ':eid' => $edition['id']]);
        $coupon = $stmtC->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$coupon || empty($coupon['is_active'])) {
            pfgRegJson(['error' => 'The coupon code entered is invalid or inactive.'], 400);
        }

        if (!empty($coupon['valid_from'])) {
            $fromTs = strtotime((string)$coupon['valid_from']);
            if ($fromTs !== false && $fromTs > $nowTs) {
                pfgRegJson(['error' => 'This coupon code is not active yet.'], 400);
            }
        }

        if (!empty($coupon['valid_until'])) {
            $untilTs = strtotime((string)$coupon['valid_until']);
            if ($untilTs !== false && $untilTs < $nowTs) {
                pfgRegJson(['error' => 'This coupon code has expired.'], 400);
            }
        }

        if ($coupon['max_uses'] !== null && $coupon['max_uses'] !== '' && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
            pfgRegJson(['error' => 'This coupon code has reached its maximum usage limit.'], 400);
        }

        $eligible = jsonDecode($coupon['eligible_categories'] ?? '[]', []);
        if (!is_array($eligible)) {
            $eligible = [];
        }
        $eligibleUpper = array_map(static fn($v): string => strtoupper(trim((string)$v)), $eligible);
        if (!empty($eligibleUpper) && !in_array($categoryCode, $eligibleUpper, true)) {
            pfgRegJson([
                'error' => "This coupon code is not eligible for the {$category['name']} category.",
            ], 400);
        }

        if (!$needsNewUser) {
            $stmtRed = $pdo->prepare(
                'SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = :cid AND user_id = :uid'
            );
            $stmtRed->execute([':cid' => $coupon['id'], ':uid' => $userId]);
            $userRedemptionCount = (int)$stmtRed->fetchColumn();
            $maxPerUser = isset($coupon['max_uses_per_user']) ? (int)$coupon['max_uses_per_user'] : 1;
            if ($userRedemptionCount >= max(1, $maxPerUser)) {
                pfgRegJson(['error' => 'You have already used this coupon code.'], 400);
            }
        }

        $discountType = strtoupper((string)($coupon['discount_type'] ?? 'PERCENTAGE'));
        $discountVal = (int)($coupon['discount_value'] ?? 0);
        if ($discountType === 'FULL_WAIVER') {
            $discountInr = $feeAmountInr;
        } elseif ($discountType === 'PERCENTAGE') {
            $discountInr = min($feeAmountInr, (int)round(($feeAmountInr * $discountVal) / 100));
        } else {
            $discountInr = min($feeAmountInr, $discountVal);
        }

        $appliedCoupon = $coupon;
    }

    $netAmountInr = max(0, $feeAmountInr - $discountInr);

    // 5. Determine initial registration status & payment status
    $status = 'CONFIRMED';
    $paymentStatus = 'NOT_REQUIRED';
    $shouldSendConfirmationEmail = true;

    if (!empty($category['requires_approval'])) {
        $status = 'SUBMITTED';
        $paymentStatus = 'NOT_REQUIRED';
        $shouldSendConfirmationEmail = false;
    } elseif (!empty($category['requires_payment']) && $netAmountInr > 0) {
        $status = 'SUBMITTED';
        $paymentStatus = 'PENDING';
        $shouldSendConfirmationEmail = false;
    }

    // 6. Atomically allocate next monotonic Registration ID BEFORE the outer registration transaction
    //    so rolled-back transactions NEVER reuse a number and server restarts preserve the high-water mark.
    $registrationCode = allocateNextRegistrationCode($pdo, (string)$edition['id']);
    $registrationId = uuid4();
    $now = nowSql();

    // 7. Execute ALL registration writes (including new user creation & role assignment — FND-21)
    //    inside ONE single atomic PDO transaction against CANONICAL tables ONLY.
    $pdo->beginTransaction();

    try {
        if ($needsNewUser) {
            $stmtInsUser = $pdo->prepare(
                "INSERT INTO users (id, email, password_hash, full_name, phone, country, is_active, account_status, created_at, updated_at)
                 VALUES (:id, :email, :password_hash, :full_name, :phone, :country, 1, 'ACTIVE', :created_at, :updated_at)"
            );
            $stmtInsUser->execute([
                ':id'            => $userId,
                ':email'         => $normalizedEmail,
                ':password_hash' => $passwordHash,
                ':full_name'     => $fullName,
                ':phone'         => $phone,
                ':country'       => $country,
                ':created_at'    => $now,
                ':updated_at'    => $now,
            ]);

            $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE code = 'PARTICIPANT' LIMIT 1");
            $stmtRole->execute();
            $roleId = (string)$stmtRole->fetchColumn();

            if ($roleId === '') {
                $roleId = uuid4();
                $stmtInsRole = $pdo->prepare(
                    "INSERT INTO roles (id, code, name, description, created_at)
                     VALUES (:id, 'PARTICIPANT', 'Participant', 'Festival participant account.', :created_at)"
                );
                $stmtInsRole->execute([
                    ':id'         => $roleId,
                    ':created_at' => $now,
                ]);
            }

            $stmtUr = $pdo->prepare(
                'INSERT IGNORE INTO user_roles (id, user_id, role_id, assigned_at)
                 VALUES (:id, :user_id, :role_id, :assigned_at)'
            );
            $stmtUr->execute([
                ':id'          => uuid4(),
                ':user_id'     => $userId,
                ':role_id'     => $roleId,
                ':assigned_at' => $now,
            ]);
        }

        $stmtInsReg = $pdo->prepare(
            "INSERT INTO registrations (
                id, registration_code, idempotency_key, edition_id, user_id, category_id, category_code,
                status, payment_status, assignment_status, full_name, email, phone, country,
                occupation, organization_name, designation, is_person_with_disability,
                attending_days, interested_tracks, coupon_code, fee_amount_inr, discount_inr, net_amount_inr,
                submitted_at, created_at, updated_at
             ) VALUES (
                :id, :registration_code, :idempotency_key, :edition_id, :user_id, :category_id, :category_code,
                :status, :payment_status, 'UNASSIGNED', :full_name, :email, :phone, :country,
                :occupation, :organization_name, :designation, :is_person_with_disability,
                :attending_days, :interested_tracks, :coupon_code, :fee_amount_inr, :discount_inr, :net_amount_inr,
                :submitted_at, :created_at, :updated_at
             )"
        );
        $stmtInsReg->execute([
            ':id'                        => $registrationId,
            ':registration_code'         => $registrationCode,
            ':idempotency_key'           => $idempotencyKey,
            ':edition_id'                => $edition['id'],
            ':user_id'                   => $userId,
            ':category_id'               => $category['id'],
            ':category_code'             => $categoryCode,
            ':status'                    => $status,
            ':payment_status'            => $paymentStatus,
            ':full_name'                 => $fullName,
            ':email'                     => $normalizedEmail,
            ':phone'                     => $phone,
            ':country'                   => $country,
            ':occupation'                => $occupation,
            ':organization_name'         => $organizationName !== '' ? $organizationName : null,
            ':designation'               => $designation !== '' ? $designation : null,
            ':is_person_with_disability' => $isPersonWithDisability,
            ':attending_days'            => jsonEncode($attendingDays),
            ':interested_tracks'         => jsonEncode($interestedTracks),
            ':coupon_code'               => $appliedCoupon ? (string)$appliedCoupon['code'] : null,
            ':fee_amount_inr'            => $feeAmountInr,
            ':discount_inr'              => $discountInr,
            ':net_amount_inr'            => $netAmountInr,
            ':submitted_at'              => $now,
            ':created_at'                => $now,
            ':updated_at'                => $now,
        ]);

        // Store sanitized form response snapshot (never store password or sensitive disability/accommodation free-text — FND-11)
        $safeAnswers = $body;
        unset(
            $safeAnswers['password'],
            $safeAnswers['csrfToken'],
            $safeAnswers['csrf_token'],
            $safeAnswers['isPersonWithDisability'],
            $safeAnswers['disabilityCategoryCodes'],
            $safeAnswers['disabilitySelfDescription'],
            $safeAnswers['accommodationTypes'],
            $safeAnswers['accommodationFreeText']
        );
        $stmtResp = $pdo->prepare(
            'INSERT INTO registration_responses (id, registration_id, answers, created_at, updated_at)
             VALUES (:id, :registration_id, :answers, :created_at, :updated_at)'
        );
        $stmtResp->execute([
            ':id'              => uuid4(),
            ':registration_id' => $registrationId,
            ':answers'         => jsonEncode($safeAnswers),
            ':created_at'      => $now,
            ':updated_at'      => $now,
        ]);

        // Role-specific details
        if ($categoryCode === 'DELEGATE' && $delegateDetails) {
            $delParams = [
                ':registration_id'            => $registrationId,
                ':gender'                     => !empty($delegateDetails['gender']) ? trim((string)$delegateDetails['gender']) : null,
                ':age'                        => isset($delegateDetails['age']) && $delegateDetails['age'] !== '' ? (int)$delegateDetails['age'] : null,
                ':city_state'                 => !empty($delegateDetails['cityState']) ? trim((string)$delegateDetails['cityState']) : null,
                ':para_sports_interests'      => jsonEncode(isset($delegateDetails['paraSportsInterests']) && is_array($delegateDetails['paraSportsInterests']) ? $delegateDetails['paraSportsInterests'] : []),
                ':outstation_travel_required' => !empty($delegateDetails['outstationTravelRequired']) ? 1 : 0,
                ':accommodation_needed'       => !empty($delegateDetails['accommodationNeeded']) ? 1 : 0,
                ':food_preference'            => !empty($delegateDetails['foodPreference']) ? trim((string)$delegateDetails['foodPreference']) : null,
                ':emergency_contact_name'     => !empty($delegateDetails['emergencyContactName']) ? trim((string)$delegateDetails['emergencyContactName']) : null,
                ':emergency_contact_phone'    => !empty($delegateDetails['emergencyContactPhone']) ? trim((string)$delegateDetails['emergencyContactPhone']) : null,
                ':guardian_name'              => !empty($delegateDetails['guardianName']) ? trim((string)$delegateDetails['guardianName']) : null,
                ':created_at'                 => $now,
            ];

            $stmtDel = $pdo->prepare(
                'INSERT INTO delegate_details (
                    id, registration_id, gender, age, city_state, para_sports_interests,
                    outstation_travel_required, accommodation_needed, food_preference,
                    emergency_contact_name, emergency_contact_phone, guardian_name, created_at
                 ) VALUES (
                    :id, :registration_id, :gender, :age, :city_state, :para_sports_interests,
                    :outstation_travel_required, :accommodation_needed, :food_preference,
                    :emergency_contact_name, :emergency_contact_phone, :guardian_name, :created_at
                 )'
            );
            $stmtDel->execute(array_merge([':id' => uuid4()], $delParams));
        }

        if ($categoryCode === 'VOLUNTEER') {
            $v = $volunteerDetails ?? [];
            $vAvail = isset($v['availabilityDays']) && is_array($v['availabilityDays']) && count($v['availabilityDays']) > 0
                ? $v['availabilityDays']
                : $attendingDays;

            $stmtVol = $pdo->prepare(
                'INSERT INTO volunteer_applications (
                    id, registration_id, age, gender, city_state, institution_name,
                    is_msu_student_or_alumni, crr_number, preferred_roles, availability_days,
                    languages_spoken, relevant_experience, emergency_contact_name, emergency_contact_phone,
                    created_at, updated_at
                 ) VALUES (
                    :id, :registration_id, :age, :gender, :city_state, :institution_name,
                    :is_msu_student_or_alumni, :crr_number, :preferred_roles, :availability_days,
                    :languages_spoken, :relevant_experience, :emergency_contact_name, :emergency_contact_phone,
                    :created_at, :updated_at
                 )'
            );
            $stmtVol->execute([
                ':id'                       => uuid4(),
                ':registration_id'          => $registrationId,
                ':age'                      => isset($v['age']) && $v['age'] !== '' ? (int)$v['age'] : null,
                ':gender'                   => !empty($v['gender']) ? trim((string)$v['gender']) : null,
                ':city_state'               => !empty($v['cityState']) ? trim((string)$v['cityState']) : null,
                ':institution_name'         => !empty($v['institutionName']) ? trim((string)$v['institutionName']) : ($organizationName !== '' ? $organizationName : null),
                ':is_msu_student_or_alumni' => !empty($v['isMsuStudentOrAlumni']) ? 1 : 0,
                ':crr_number'               => !empty($v['crrNumber']) ? trim((string)$v['crrNumber']) : null,
                ':preferred_roles'          => jsonEncode(isset($v['preferredRoles']) && is_array($v['preferredRoles']) ? $v['preferredRoles'] : []),
                ':availability_days'        => jsonEncode($vAvail),
                ':languages_spoken'         => jsonEncode(isset($v['languagesSpoken']) && is_array($v['languagesSpoken']) ? $v['languagesSpoken'] : []),
                ':relevant_experience'      => !empty($v['relevantExperience']) ? trim((string)$v['relevantExperience']) : null,
                ':emergency_contact_name'   => !empty($v['emergencyContactName']) ? trim((string)$v['emergencyContactName']) : null,
                ':emergency_contact_phone'  => !empty($v['emergencyContactPhone']) ? trim((string)$v['emergencyContactPhone']) : null,
                ':created_at'               => $now,
                ':updated_at'               => $now,
            ]);
        }

        if ($categoryCode === 'SLI') {
            $s = $sliDetails ?? [];
            $sAvail = isset($s['availabilityDays']) && is_array($s['availabilityDays']) && count($s['availabilityDays']) > 0
                ? $s['availabilityDays']
                : $attendingDays;

            $stmtSli = $pdo->prepare(
                'INSERT INTO sli_applications (
                    id, registration_id, city_state, based_in_gujarat, crr_number,
                    qualifications, training_institute, certification_level, years_of_experience,
                    professional_affiliation, areas_of_expertise, interpretation_preferences,
                    availability_days, video_demo_url, honorarium_or_travel_notes,
                    created_at, updated_at
                 ) VALUES (
                    :id, :registration_id, :city_state, :based_in_gujarat, :crr_number,
                    :qualifications, :training_institute, :certification_level, :years_of_experience,
                    :professional_affiliation, :areas_of_expertise, :interpretation_preferences,
                    :availability_days, :video_demo_url, :honorarium_or_travel_notes,
                    :created_at, :updated_at
                 )'
            );
            $stmtSli->execute([
                ':id'                         => uuid4(),
                ':registration_id'            => $registrationId,
                ':city_state'                 => !empty($s['cityState']) ? trim((string)$s['cityState']) : null,
                ':based_in_gujarat'           => (!isset($s['basedInGujarat']) || !empty($s['basedInGujarat'])) ? 1 : 0,
                ':crr_number'                 => !empty($s['crrNumber']) ? trim((string)$s['crrNumber']) : null,
                ':qualifications'             => !empty($s['qualifications']) ? trim((string)$s['qualifications']) : null,
                ':training_institute'         => !empty($s['trainingInstitute']) ? trim((string)$s['trainingInstitute']) : null,
                ':certification_level'        => !empty($s['certificationLevel']) ? trim((string)$s['certificationLevel']) : null,
                ':years_of_experience'        => isset($s['yearsOfExperience']) && $s['yearsOfExperience'] !== '' ? (int)$s['yearsOfExperience'] : null,
                ':professional_affiliation'   => !empty($s['professionalAffiliation']) ? trim((string)$s['professionalAffiliation']) : ($organizationName !== '' ? $organizationName : null),
                ':areas_of_expertise'         => jsonEncode(isset($s['areasOfExpertise']) && is_array($s['areasOfExpertise']) ? $s['areasOfExpertise'] : []),
                ':interpretation_preferences' => jsonEncode(isset($s['interpretationPreferences']) && is_array($s['interpretationPreferences']) ? $s['interpretationPreferences'] : []),
                ':availability_days'          => jsonEncode($sAvail),
                ':video_demo_url'             => !empty($s['videoDemoUrl']) ? trim((string)$s['videoDemoUrl']) : null,
                ':honorarium_or_travel_notes' => !empty($s['honorariumOrTravelNotes']) ? trim((string)$s['honorariumOrTravelNotes']) : null,
                ':created_at'                 => $now,
                ':updated_at'                 => $now,
            ]);
        }

        // Separate Privacy & Consent Records (DPDP Act 2023 compliant)
        $consents = [
            ['type' => 'ZERO_TOLERANCE_HARASSMENT', 'granted' => 1],
            ['type' => 'DATA_PROCESSING_DPDP',      'granted' => 1],
            ['type' => 'PHOTOGRAPHY_MEDIA',         'granted' => $consentMediaPhotography ? 1 : 0],
        ];
        $stmtConsent = $pdo->prepare(
            "INSERT INTO privacy_consent_records (
                id, user_id, registration_id, consent_type, consent_version, granted, ip_address, recorded_at
             ) VALUES (
                :id, :user_id, :registration_id, :consent_type, '1.0', :granted, :ip_address, :recorded_at
             )"
        );
        foreach ($consents as $cItem) {
            $stmtConsent->execute([
                ':id'              => uuid4(),
                ':user_id'         => $userId,
                ':registration_id' => $registrationId,
                ':consent_type'    => $cItem['type'],
                ':granted'         => $cItem['granted'],
                ':ip_address'      => $ipAddress,
                ':recorded_at'     => $now,
            ]);
        }

        // Protected Disability Disclosure (canonical `participant_disability_disclosures` ONLY — encrypted free-text)
        $encryptedDisabilityDesc = $disabilitySelfDescription !== ''
            ? pfgEncryptValue($disabilitySelfDescription, 'data')
            : null;
        if (
            $isPersonWithDisability === 'YES' &&
            (!empty($disabilityCategoryCodes) || $disabilitySelfDescription !== '')
        ) {
            if (!empty($disabilityCategoryCodes)) {
                $placeholders = implode(',', array_fill(0, count($disabilityCategoryCodes), '?'));
                $stmtDisLookup = $pdo->prepare(
                    "SELECT id, code FROM disability_categories WHERE code IN ({$placeholders})"
                );
                $stmtDisLookup->execute($disabilityCategoryCodes);
                $matchedCats = $stmtDisLookup->fetchAll(PDO::FETCH_ASSOC) ?: [];

                if (!empty($matchedCats)) {
                    $stmtInsDis = $pdo->prepare(
                        'INSERT INTO participant_disability_disclosures (
                            id, registration_id, disability_category_id, disability_code, category_code, self_description, notes, created_at
                         ) VALUES (
                            :id, :registration_id, :disability_category_id, :disability_code, :category_code, :self_description, :notes, :created_at
                         )'
                    );
                    foreach ($matchedCats as $idx => $mc) {
                        $selfDesc = ($idx === 0 && $encryptedDisabilityDesc !== null) ? $encryptedDisabilityDesc : null;
                        $stmtInsDis->execute([
                            ':id'                     => uuid4(),
                            ':registration_id'        => $registrationId,
                            ':disability_category_id' => $mc['id'],
                            ':disability_code'        => $mc['code'],
                            ':category_code'          => $mc['code'],
                            ':self_description'       => $selfDesc,
                            ':notes'                  => $selfDesc,
                            ':created_at'             => $now,
                        ]);
                    }
                } else {
                    $stmtInsDis = $pdo->prepare(
                        'INSERT INTO participant_disability_disclosures (
                            id, registration_id, disability_category_id, disability_code, category_code, self_description, notes, created_at
                         ) VALUES (
                            :id, :registration_id, NULL, :disability_code, :category_code, :self_description, :notes, :created_at
                         )'
                    );
                    foreach ($disabilityCategoryCodes as $idx => $dCode) {
                        $selfDesc = ($idx === 0 && $encryptedDisabilityDesc !== null) ? $encryptedDisabilityDesc : null;
                        $stmtInsDis->execute([
                            ':id'               => uuid4(),
                            ':registration_id'  => $registrationId,
                            ':disability_code'  => $dCode,
                            ':category_code'    => $dCode,
                            ':self_description' => $selfDesc,
                            ':notes'            => $selfDesc,
                            ':created_at'       => $now,
                        ]);
                    }
                }
            } else {
                $stmtInsDis = $pdo->prepare(
                    'INSERT INTO participant_disability_disclosures (
                        id, registration_id, disability_category_id, self_description, notes, created_at
                     ) VALUES (
                        :id, :registration_id, NULL, :self_description, :notes, :created_at
                     )'
                );
                $stmtInsDis->execute([
                    ':id'               => uuid4(),
                    ':registration_id'  => $registrationId,
                    ':self_description' => $encryptedDisabilityDesc,
                    ':notes'            => $encryptedDisabilityDesc,
                    ':created_at'       => $now,
                ]);
            }
        }

        // Protected Accommodation Requests (canonical `accommodation_requests` ONLY — encrypted free-text)
        if (!empty($accommodationTypes) || $accommodationFreeText !== '') {
            $firstCode = !empty($accommodationTypes) ? (string)$accommodationTypes[0] : null;
            $encryptedAccommText = $accommodationFreeText !== ''
                ? pfgEncryptValue($accommodationFreeText, 'data')
                : null;
            $stmtInsAcc = $pdo->prepare(
                "INSERT INTO accommodation_requests (
                    id, registration_id, user_id, accommodation_code, selected_types, free_text_details, notes, status, created_at, updated_at
                 ) VALUES (
                    :id, :registration_id, :user_id, :accommodation_code, :selected_types, :free_text_details, :notes, 'REQUESTED', :created_at, :updated_at
                 )"
            );
            $stmtInsAcc->execute([
                ':id'                 => uuid4(),
                ':registration_id'    => $registrationId,
                ':user_id'            => $userId,
                ':accommodation_code' => $firstCode,
                ':selected_types'     => jsonEncode($accommodationTypes),
                ':free_text_details'  => $encryptedAccommText,
                ':notes'              => $encryptedAccommText,
                ':created_at'         => $now,
                ':updated_at'         => $now,
            ]);
        }

        // Coupon redemption record (canonical `coupons` table ONLY)
        if ($appliedCoupon) {
            $stmtInsRed = $pdo->prepare(
                'INSERT INTO coupon_redemptions (id, coupon_id, registration_id, user_id, discount_applied_inr, redeemed_at)
                 VALUES (:id, :coupon_id, :registration_id, :user_id, :discount_applied_inr, :redeemed_at)'
            );
            $stmtInsRed->execute([
                ':id'                   => uuid4(),
                ':coupon_id'            => $appliedCoupon['id'],
                ':registration_id'      => $registrationId,
                ':user_id'              => $userId,
                ':discount_applied_inr' => $discountInr,
                ':redeemed_at'          => $now,
            ]);

            $stmtUpdC = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id');
            $stmtUpdC->execute([':id' => $appliedCoupon['id']]);
        }

        // D-01: Participant QR entry credential issuance is discontinued; Registration ID is the official identifier.
        $qrPass = null;
        $qrEmailed = false;
        $emailResult = null;

        $pdo->commit();
    } catch (Throwable $txErr) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $txErr;
    }

    if ($shouldSendConfirmationEmail) {
        $emailResult = sendRegistrationConfirmationEmail($pdo, [
            'to'               => $normalizedEmail,
            'fullName'         => $fullName,
            'registrationCode' => $registrationCode,
            'categoryName'     => (string)$category['name'],
            'status'           => $status,
            'recipientUserId'  => $userId,
            'idempotencyKey'   => 'reg-confirm:' . $registrationId,
        ]);
    } elseif (!empty($category['requires_approval'])) {
        $emailResult = sendApplicationReceivedEmail($pdo, [
            'to'               => $normalizedEmail,
            'fullName'         => $fullName,
            'registrationCode' => $registrationCode,
            'categoryName'     => (string)$category['name'],
            'recipientUserId'  => $userId,
            'idempotencyKey'   => 'app-received:' . $registrationId,
        ]);
    }

    pfgRecordAuditCompat(
        $pdo,
        $userId,
        'REGISTRATION_SUBMITTED',
        'registration',
        $registrationId,
        $ipAddress,
        $userAgent,
        [
            'registrationCode' => $registrationCode,
            'categoryCode'     => $categoryCode,
            'status'           => $status,
            'paymentStatus'    => $paymentStatus,
            'qrIssued'         => $qrPass !== null,
        ]
    );

    $registrationPayload = [
        'id'                     => $registrationId,
        'registrationCode'       => $registrationCode,
        'editionId'              => (string)$edition['id'],
        'userId'                 => $userId,
        'categoryId'             => (string)$category['id'],
        'categoryCode'           => $categoryCode,
        'status'                 => $status,
        'paymentStatus'          => $paymentStatus,
        'fullName'               => $fullName,
        'email'                  => $normalizedEmail,
        'phone'                  => $phone,
        'country'                => $country,
        'occupation'             => $occupation,
        'organizationName'       => $organizationName !== '' ? $organizationName : null,
        'designation'            => $designation !== '' ? $designation : null,
        'isPersonWithDisability' => $isPersonWithDisability,
        'attendingDays'          => $attendingDays,
        'interestedTracks'       => $interestedTracks,
        'submittedAt'            => $now,
        'createdAt'              => $now,
    ];

    pfgRegJson([
        'registration' => $registrationPayload,
        'category'     => [
            'code'             => (string)$category['code'],
            'name'             => (string)$category['name'],
            'requiresApproval' => !empty($category['requires_approval']),
            'requiresPayment'  => !empty($category['requires_payment']),
        ],
        'pricing'      => [
            'feeAmountInr' => $feeAmountInr,
            'discountInr'  => $discountInr,
            'netAmountInr' => $netAmountInr,
            'couponCode'   => $appliedCoupon ? (string)$appliedCoupon['code'] : null,
        ],
        'qrPass'         => $qrPass,
        'qrEmailed'      => $qrEmailed,
        'emailSentTo'    => $normalizedEmail,
        'emailDelivery'  => $emailResult,
        'sessionCreated' => false,
    ], 201);
} catch (Throwable $e) {
    error_log('POST /api/registrations error: ' . $e->getMessage());
    pfgRegJson([
        'error' => 'Unable to complete registration right now. Please try again.',
    ], 500);
}
