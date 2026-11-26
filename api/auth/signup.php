<?php
declare(strict_types=1);

/**
 * POST /api/auth/signup  (Phase 5 — verified participant onboarding)
 *
 * - Collects ONLY account basics (no medical / disability data).
 * - Rate-limited per IP and per e-mail.
 * - Anti-enumeration: an already-registered e-mail never leaks and is never overwritten; callers receive a
 *   uniform 202 generic response (an unverified participant is safely re-sent a fresh verification link).
 * - New accounts start as PENDING_VERIFICATION and cannot sign in until the e-mail is verified.
 * - The raw verification token is ONLY delivered via the encrypted ephemeral e-mail channel (never in JSON).
 */

require_once __DIR__ . '/_participant.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    jsonResponse(['error' => 'Method not allowed.'], 405);
}

try {
    $pdo = getDb();
    $clientIp = getClientIp() ?: 'unknown';
    pfgRequireRateLimit($pdo, 'auth_signup_ip', $clientIp, 20, 3600, 3600);

    $input = readRequestInput();
    $fullName = trim((string)($input['fullName'] ?? ''));
    $email = pfgAuthNormalizeEmail($input['email'] ?? '');
    $phone = trim((string)($input['phone'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $confirmPassword = (string)($input['confirmPassword'] ?? '');
    $consent = pfgAuthBoolish($input['consentAccepted'] ?? false);

    $errors = [];
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 200) {
        $errors['fullName'] = 'Please enter your full name (2 to 200 characters).';
    }
    if (!pfgAuthIsValidEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!pfgAuthIsValidPhone($phone)) {
        $errors['phone'] = 'Please enter a valid phone number (7 to 20 digits; +, spaces, dashes and brackets allowed).';
    }
    $pwError = pfgAuthPasswordPolicyError($password);
    if ($pwError !== null) {
        $errors['password'] = $pwError;
    }
    if (!hash_equals($password, $confirmPassword)) {
        $errors['confirmPassword'] = 'The two passwords do not match.';
    }
    if (!$consent) {
        $errors['consentAccepted'] = 'You must accept the privacy notice and terms to create an account.';
    }
    if (!empty($errors)) {
        jsonResponse(['error' => 'Please correct the highlighted fields.', 'errors' => $errors], 400);
    }

    pfgRequireRateLimit($pdo, 'auth_signup_email', $email, 5, 3600, 3600);

    // Always pay the bcrypt cost so new / existing e-mail paths take comparable time.
    $passwordHash = hashPassword($password);

    $genericBody = [
        'success'                   => true,
        'emailVerificationRequired' => true,
        'message'                   => PFG_AUTH_GENERIC_VERIFY_MESSAGE,
    ];

    $stmt = $pdo->prepare('SELECT id, full_name, email_verified_at, account_status, is_active, deleted_at FROM users WHERE LOWER(email) = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Never reveal existence. Only a still-unverified, active, participant-only account is re-sent a link.
        $eligibleForResend = empty($existing['deleted_at'])
            && !empty($existing['is_active'])
            && empty($existing['email_verified_at'])
            && strtoupper((string)($existing['account_status'] ?? '')) === 'PENDING_VERIFICATION'
            && !pfgAuthHasStaffRole(pfgAuthLoadRoles($pdo, (string)$existing['id']));

        if ($eligibleForResend) {
            $rl = pfgCheckRateLimit($pdo, 'auth_verify_resend_email', $email, 3, 3600, 3600, true);
            if (!empty($rl['allowed'])) {
                $queueId = null;
                $pdo->beginTransaction();
                try {
                    $raw = pfgAuthIssueEmailToken($pdo, (string)$existing['id'], 'SIGNUP_VERIFY', $email);
                    $queueId = pfgAuthEnqueueEmail($pdo, $email, 'EMAIL_VERIFICATION_RESEND', [
                        'fullName'      => (string)($existing['full_name'] ?: 'Participant'),
                        'expiresInText' => '30 minutes',
                    ], ['actionUrl' => pfgAuthVerificationUrl($raw)], (string)$existing['id']);
                    $pdo->commit();
                } catch (Throwable $inner) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $inner;
                }
                pfgAuthDispatchLocal($pdo, $queueId);
                logAudit([
                    'actorUserId'  => null,
                    'action'       => 'EMAIL_VERIFICATION_RESENT',
                    'resourceType' => 'user',
                    'resourceId'   => (string)$existing['id'],
                    'metadata'     => ['via' => 'signup_existing_unverified'],
                ]);
            }
        } else {
            logAudit([
                'actorUserId'  => null,
                'action'       => 'SIGNUP_DUPLICATE_EMAIL',
                'resourceType' => 'auth',
                'resourceId'   => null,
                'metadata'     => ['reason' => 'existing_account_untouched'],
            ]);
        }
        jsonResponse($genericBody, 202);
    }

    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'PARTICIPANT' LIMIT 1");
    $roleStmt->execute();
    $roleId = (string)$roleStmt->fetchColumn();
    if ($roleId === '') {
        throw new RuntimeException('PARTICIPANT role is not seeded.');
    }

    $userId = uuid4();
    $now = nowSql();
    $queueId = null;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO users (
                id, username, email, email_verified_at, password_hash, full_name, phone, country,
                is_active, account_status, account_consent_at, created_at, updated_at
            ) VALUES (
                :id, NULL, :email, NULL, :pw, :name, :phone, 'India',
                1, 'PENDING_VERIFICATION', :consent, :now, :now2
            )
        ")->execute([
            ':id'      => $userId,
            ':email'   => $email,
            ':pw'      => $passwordHash,
            ':name'    => $fullName,
            ':phone'   => $phone,
            ':consent' => $now,
            ':now'     => $now,
            ':now2'    => $now,
        ]);

        $pdo->prepare('INSERT INTO user_roles (id, user_id, role_id, custom_permissions, assigned_at) VALUES (:id, :uid, :rid, NULL, :now)')
            ->execute([':id' => uuid4(), ':uid' => $userId, ':rid' => $roleId, ':now' => $now]);

        $raw = pfgAuthIssueEmailToken($pdo, $userId, 'SIGNUP_VERIFY', $email);
        $queueId = pfgAuthEnqueueEmail($pdo, $email, 'ACCOUNT_VERIFICATION', [
            'fullName'      => $fullName,
            'expiresInText' => '30 minutes',
        ], ['actionUrl' => pfgAuthVerificationUrl($raw)], $userId);

        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (pfgAuthIsDuplicateKey($inner)) {
            // Concurrent signup for the same e-mail: respond exactly like the "already exists" path.
            jsonResponse($genericBody, 202);
        }
        throw $inner;
    }

    pfgAuthDispatchLocal($pdo, $queueId);

    logAudit([
        'actorUserId'  => $userId,
        'action'       => 'PARTICIPANT_SIGNUP',
        'resourceType' => 'user',
        'resourceId'   => $userId,
        'metadata'     => ['accountStatus' => 'PENDING_VERIFICATION'],
    ]);

    jsonResponse($genericBody, 201);
} catch (Throwable $e) {
    error_log('POST /api/auth/signup error: ' . $e->getMessage());
    jsonResponse(['error' => 'Unable to process request right now.'], 500);
}
