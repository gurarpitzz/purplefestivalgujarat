<?php
declare(strict_types=1);

/**
 * GET / PATCH (POST, PUT) /api/account/profile  — Phase 5 participant account management
 *
 * - Editable: fullName, phone, cityDistrict, state, country, preferredLanguage,
 *   emergencyContactName, emergencyContactRelationship, emergencyContactPhone.
 * - Password change: requires currentPassword (when a password exists), revokes all OTHER sessions and
 *   enqueues a SECURITY_ALERT.
 * - E-mail change: NEVER overwrites users.email directly. Stores users.pending_email, issues a single-use
 *   EMAIL_CHANGE token and mails an ephemeral confirmation link to the NEW address
 *   (promotion happens in POST /api/auth/verify-email). Uniform response whether or not the address is free.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__) . '/auth/_participant.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Loads the full profile projection for a user id.
 */
function pfgProfileLoad(PDO $pdo, string $userId, array $roles = []): ?array
{
    $stmt = $pdo->prepare('
        SELECT id, email, pending_email, email_verified_at, account_status, full_name, phone, country,
               city_district, state, preferred_language,
               emergency_contact_name, emergency_contact_relationship, emergency_contact_phone
        FROM users WHERE id = :id LIMIT 1
    ');
    $stmt->execute([':id' => $userId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    return [
        'id'                           => (string)$r['id'],
        'email'                        => (string)$r['email'],
        'emailVerified'                => !empty($r['email_verified_at']),
        'pendingEmail'                 => $r['pending_email'] !== null && $r['pending_email'] !== '' ? (string)$r['pending_email'] : null,
        'accountStatus'                => (string)($r['account_status'] ?? 'ACTIVE'),
        'fullName'                     => (string)$r['full_name'],
        'phone'                        => $r['phone'] !== null ? (string)$r['phone'] : null,
        'country'                      => (string)($r['country'] ?? 'India'),
        'cityDistrict'                 => $r['city_district'] ?? null,
        'state'                        => $r['state'] ?? null,
        'preferredLanguage'            => $r['preferred_language'] ?? null,
        'emergencyContactName'         => $r['emergency_contact_name'] ?? null,
        'emergencyContactRelationship' => $r['emergency_contact_relationship'] ?? null,
        'emergencyContactPhone'        => $r['emergency_contact_phone'] ?? null,
        'roles'                        => $roles,
    ];
}

function pfgProfileFail(string $message, int $status = 400, array $extra = []): void
{
    jsonResponse(array_merge(['error' => $message], $extra), $status);
}

try {
    $user = getCurrentUser();
    if (!$user) {
        pfgProfileFail('Authentication required.', 401);
    }

    $pdo = getDb();

    if ($method === 'GET') {
        $profile = pfgProfileLoad($pdo, (string)$user['id'], $user['roles'] ?? []);
        if (!$profile) {
            pfgProfileFail('Authentication required.', 401);
        }
        jsonResponse(['user' => $profile], 200);
    }

    if (!in_array($method, ['PATCH', 'POST', 'PUT'], true)) {
        pfgProfileFail('Method not allowed. Use PATCH or POST.', 405);
    }

    enforceStaffMfaGate($user);
    requireCsrfToken($user);

    $body = readRequestInput();
    $setClauses = ['updated_at = :updated_at'];
    $params = [
        ':updated_at' => nowSql(),
        ':id'         => $user['id'],
    ];
    $updatedFields = [];
    $errors = [];

    // Optional-text columns: key => [column, max length, label]
    $textFields = [
        'cityDistrict'                 => ['city_district', 120, 'City / district'],
        'state'                        => ['state', 100, 'State'],
        'preferredLanguage'            => ['preferred_language', 32, 'Preferred language'],
        'emergencyContactName'         => ['emergency_contact_name', 150, 'Emergency contact name'],
        'emergencyContactRelationship' => ['emergency_contact_relationship', 80, 'Emergency contact relationship'],
    ];

    if (array_key_exists('fullName', $body)) {
        $fullName = trim((string)$body['fullName']);
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 200) {
            $errors['fullName'] = 'Full name must be between 2 and 200 characters.';
        } else {
            $setClauses[] = 'full_name = :full_name';
            $params[':full_name'] = $fullName;
            $updatedFields[] = 'fullName';
        }
    }

    if (array_key_exists('phone', $body)) {
        $phone = trim((string)$body['phone']);
        if ($phone !== '' && !pfgAuthIsValidPhone($phone)) {
            $errors['phone'] = 'Please enter a valid phone number.';
        } else {
            $setClauses[] = 'phone = :phone';
            $params[':phone'] = $phone !== '' ? $phone : null;
            $updatedFields[] = 'phone';
        }
    }

    if (array_key_exists('country', $body)) {
        $country = trim((string)$body['country']);
        if ($country !== '' && (mb_strlen($country) < 2 || mb_strlen($country) > 100)) {
            $errors['country'] = 'Country must be between 2 and 100 characters.';
        } else {
            $setClauses[] = 'country = :country';
            $params[':country'] = $country !== '' ? $country : 'India';
            $updatedFields[] = 'country';
        }
    }

    foreach ($textFields as $key => [$col, $max, $label]) {
        if (!array_key_exists($key, $body)) {
            continue;
        }
        $val = trim((string)$body[$key]);
        if (mb_strlen($val) > $max) {
            $errors[$key] = "$label must not exceed $max characters.";
            continue;
        }
        $setClauses[] = "$col = :$col";
        $params[":$col"] = $val !== '' ? $val : null;
        $updatedFields[] = $key;
    }

    if (array_key_exists('emergencyContactPhone', $body)) {
        $ecp = trim((string)$body['emergencyContactPhone']);
        if ($ecp !== '' && !pfgAuthIsValidPhone($ecp)) {
            $errors['emergencyContactPhone'] = 'Please enter a valid emergency contact phone number.';
        } else {
            $setClauses[] = 'emergency_contact_phone = :ecp';
            $params[':ecp'] = $ecp !== '' ? $ecp : null;
            $updatedFields[] = 'emergencyContactPhone';
        }
    }

    // Password-sensitive operations need the stored hash.
    $dbUserStmt = $pdo->prepare('SELECT email, full_name, password_hash FROM users WHERE id = :id LIMIT 1');
    $dbUserStmt->execute([':id' => $user['id']]);
    $dbUser = $dbUserStmt->fetch(PDO::FETCH_ASSOC);
    if (!$dbUser) {
        pfgProfileFail('Authentication required.', 401);
    }
    $hasPassword = !empty($dbUser['password_hash']);
    $currentPassword = (string)($body['currentPassword'] ?? '');

    $verifyCurrent = static function () use ($pdo, $user, $hasPassword, $currentPassword, $dbUser): ?string {
        if (!$hasPassword) {
            return null;
        }
        if ($currentPassword === '') {
            return 'Please enter your current password to confirm this change.';
        }
        pfgRequireRateLimit($pdo, 'account_current_pw', (string)$user['id'], 10, 900, 900, (string)$user['id']);
        return verifyPassword($currentPassword, (string)$dbUser['password_hash'])
            ? null
            : 'Current password is incorrect.';
    };

    $passwordChanged = false;
    $newPassword = isset($body['newPassword']) ? (string)$body['newPassword'] : '';
    if ($newPassword !== '') {
        $pwErr = pfgAuthPasswordPolicyError($newPassword);
        if ($pwErr !== null) {
            $errors['newPassword'] = $pwErr;
        } else {
            $curErr = $verifyCurrent();
            if ($curErr !== null) {
                $errors['currentPassword'] = $curErr;
            } else {
                $setClauses[] = 'password_hash = :password_hash';
                $params[':password_hash'] = hashPassword($newPassword);
                $passwordChanged = true;
            }
        }
    }

    // E-mail change request (pending until verified through the new mailbox).
    $emailChangeRequested = false;
    $newEmail = '';
    if (array_key_exists('email', $body)) {
        $candidate = pfgAuthNormalizeEmail($body['email']);
        $currentEmail = pfgAuthNormalizeEmail($dbUser['email']);
        if ($candidate !== '' && $candidate !== $currentEmail) {
            if (pfgAuthHasStaffRole($user['roles'] ?? [])) {
                pfgProfileFail('Staff email addresses are managed by an administrator.', 403);
            }
            if (!pfgAuthIsValidEmail($candidate)) {
                $errors['email'] = 'Please enter a valid email address.';
            } else {
                $curErr = $passwordChanged ? null : $verifyCurrent();
                if ($curErr !== null) {
                    $errors['currentPassword'] = $curErr;
                } else {
                    $emailChangeRequested = true;
                    $newEmail = $candidate;
                }
            }
        }
    }

    if (!empty($errors)) {
        $first = (string)reset($errors);
        pfgProfileFail($first, 400, ['errors' => $errors]);
    }

    if ($emailChangeRequested) {
        pfgRequireRateLimit($pdo, 'account_email_change', (string)$user['id'], 5, 3600, 3600, (string)$user['id']);
    }

    $emailQueueId = null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = :id')->execute($params);

        if ($emailChangeRequested) {
            $dup = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = :e AND id <> :id LIMIT 1');
            $dup->execute([':e' => $newEmail, ':id' => $user['id']]);
            $taken = (bool)$dup->fetchColumn();
            if (!$taken) {
                $pdo->prepare('UPDATE users SET pending_email = :pe, updated_at = :now WHERE id = :id')
                    ->execute([':pe' => $newEmail, ':now' => nowSql(), ':id' => $user['id']]);
                $raw = pfgAuthIssueEmailToken($pdo, (string)$user['id'], 'EMAIL_CHANGE', $newEmail);
                $emailQueueId = pfgAuthEnqueueEmail($pdo, $newEmail, 'EMAIL_CHANGE_VERIFICATION', [
                    'fullName'      => (string)($dbUser['full_name'] ?: 'Participant'),
                    'expiresInText' => '30 minutes',
                ], ['actionUrl' => pfgAuthVerificationUrl($raw)], (string)$user['id']);
            }
        }
        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }

    pfgAuthDispatchLocal($pdo, $emailQueueId);

    if ($passwordChanged) {
        revokeAllUserSessions(
            (string)$user['id'],
            !empty($user['sessionId']) ? (string)$user['sessionId'] : null,
            $pdo,
            (string)$user['id'],
            'password_changed'
        );
        pfgAuthSendSecurityAlert(
            $pdo,
            (string)$dbUser['email'],
            (string)$user['id'],
            (string)$dbUser['full_name'],
            'Your account password was changed'
        );
    }

    if ($emailChangeRequested) {
        logAudit([
            'actorUserId'  => (string)$user['id'],
            'action'       => 'EMAIL_CHANGE_REQUESTED',
            'resourceType' => 'user',
            'resourceId'   => (string)$user['id'],
            'metadata'     => ['queued' => $emailQueueId !== null],
        ]);
    }

    logAudit([
        'actorUserId'  => (string)$user['id'],
        'action'       => $passwordChanged ? 'PASSWORD_CHANGED' : 'PROFILE_UPDATED',
        'resourceType' => 'user',
        'resourceId'   => (string)$user['id'],
        'metadata'     => [
            'updatedFields'   => $updatedFields,
            'passwordChanged' => $passwordChanged,
            'emailChangeRequested' => $emailChangeRequested,
        ],
    ]);

    $messages = [];
    $messages[] = $passwordChanged ? 'Your password and profile have been updated.' : 'Your profile has been updated.';
    if ($emailChangeRequested) {
        $messages[] = 'If the new email address can be used, a confirmation link has been sent to it. Your current email stays active until it is confirmed.';
    }

    $profilePayload = pfgProfileLoad($pdo, (string)$user['id'], $user['roles'] ?? []);
    if ($emailChangeRequested && is_array($profilePayload)) {
        // Anti-enumeration (MED-02): do not reflect collision-dependent pendingEmail state in PATCH response
        $profilePayload['pendingEmail'] = null;
    }

    jsonResponse([
        'success'            => true,
        'user'               => $profilePayload,
        'emailChangePending' => $emailChangeRequested,
        'message'            => implode(' ', $messages),
    ], 200);
} catch (Throwable $err) {
    error_log('[/api/account/profile] Error: ' . $err->getMessage());
    jsonResponse(['error' => 'Unable to update profile right now.'], 500);
}
