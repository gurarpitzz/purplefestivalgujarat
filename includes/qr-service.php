<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Cryptographic QR Pass & Check-In Service
 * Exact PHP port of src/lib/qr.ts.
 *
 * Security & Privacy Guarantees:
 * - Opaque QR token format: PFG27.<24-byte-base64url-nonce>.<16-char-HMAC-SHA256-sig>
 * - Zero PII is embedded in the QR payload.
 * - Only token_hash = hash_hmac('sha256', $rawToken, $secret) is stored in qr_credentials.token_hash.
 * - verifyCredentialInput() strictly enforces data minimization: NEVER returns disability status,
 *   disability categories, medical notes, accommodation disclosures, email, or phone number.
 * - confirmParticipantCheckin() uses a database transaction + UNIQUE(credential_id, entitlement_scope)
 *   to prevent duplicate check-ins under concurrent requests.
 */

require_once __DIR__ . '/config.php';
if (is_file(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
}

if (!function_exists('pfgBase64UrlEncode')) {
    function pfgBase64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

if (!function_exists('getQrSigningSecret')) {
    function getQrSigningSecret(): string
    {
        if (defined('QR_SIGNING_SECRET') && (string)QR_SIGNING_SECRET !== '') {
            return (string)QR_SIGNING_SECRET;
        }
        return pfgEnv('QR_SIGNING_SECRET', 'dev-qr-secret-change-in-production');
    }
}

if (!function_exists('hashQrToken')) {
    /**
     * Computes HMAC-SHA256 hex digest of a raw QR token using QR_SIGNING_SECRET.
     */
    function hashQrToken(string $rawToken): string
    {
        return hash_hmac('sha256', trim($rawToken), getQrSigningSecret());
    }
}

if (!function_exists('generateOpaqueQrToken')) {
    /**
     * Generates an opaque, cryptographically signed QR token containing ZERO PII.
     * Format: PFG27.<24-byte-base64url-nonce>.<16-char-HMAC-SHA256-sig>
     *
     * When $seed is omitted, uses 24 cryptographically random bytes.
     * When $seed is provided (e.g. credential UUID + code), derives the 24-byte nonce via
     * keyed HMAC-SHA256 so the authenticated pass holder can render their PFG27 token
     * without storing raw_token in the database.
     */
    function generateOpaqueQrToken(?string $seed = null): string
    {
        $secret = getQrSigningSecret();
        if ($seed !== null && $seed !== '') {
            $nonceBytes = substr(hash_hmac('sha256', 'pfg27-qr-nonce:' . $seed, $secret, true), 0, 24);
        } else {
            $nonceBytes = random_bytes(24);
        }

        $nonce = pfgBase64UrlEncode($nonceBytes);
        $sigFull = pfgBase64UrlEncode(hash_hmac('sha256', $nonce, $secret, true));
        $sig = substr($sigFull, 0, 16);

        return "PFG27.{$nonce}.{$sig}";
    }
}

if (!function_exists('isValidQrTokenFormat')) {
    /**
     * Validates a PFG27.<nonce>.<sig> opaque QR token using constant-time HMAC comparison.
     */
    function isValidQrTokenFormat(string $token): bool
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || $parts[0] !== 'PFG27') {
            return false;
        }
        [, $nonce, $sig] = $parts;
        if ($nonce === '' || $sig === '' || strlen($sig) !== 16) {
            return false;
        }
        // 24-byte base64url nonce is 32 characters of [A-Za-z0-9_-]
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)) {
            return false;
        }
        $expectedFull = pfgBase64UrlEncode(hash_hmac('sha256', $nonce, getQrSigningSecret(), true));
        $expected = substr($expectedFull, 0, 16);

        return hash_equals($expected, $sig);
    }
}

if (!function_exists('reconstructQrTokenForCredential')) {
    /**
     * Reconstructs the opaque PFG27.<nonce>.<sig> token for an active credential
     * (verifying that its HMAC hash matches token_hash), falling back to credential_code
     * if the credential was seeded with a non-deterministic token.
     */
    function reconstructQrTokenForCredential(array $credential): string
    {
        $id = (string)($credential['id'] ?? '');
        $code = (string)($credential['credential_code'] ?? $credential['credentialCode'] ?? '');
        $storedHash = (string)($credential['token_hash'] ?? $credential['tokenHash'] ?? '');

        if ($id !== '' && $code !== '' && $storedHash !== '') {
            $candidate = generateOpaqueQrToken($id . ':' . $code);
            if (hash_equals($storedHash, hashQrToken($candidate))) {
                return $candidate;
            }
        }
        return $code;
    }
}

if (!function_exists('generateQrSvgDataUrl')) {
    /**
     * Generates a lightweight SVG data URL placeholder/badge or server-side notice;
     * client pages render the full ISO/IEC 18004 QR matrix via /assets/js/qrcode-gen.js.
     */
    function generateQrSvgDataUrl(string $payload): string
    {
        $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="240" height="240">'
            . '<rect width="240" height="240" fill="#F9F7FC"/>'
            . '<rect x="16" y="16" width="56" height="56" fill="none" stroke="#17151C" stroke-width="8"/>'
            . '<rect x="32" y="32" width="24" height="24" fill="#17151C"/>'
            . '<rect x="168" y="16" width="56" height="56" fill="none" stroke="#17151C" stroke-width="8"/>'
            . '<rect x="184" y="32" width="24" height="24" fill="#17151C"/>'
            . '<rect x="16" y="168" width="56" height="56" fill="none" stroke="#17151C" stroke-width="8"/>'
            . '<rect x="32" y="184" width="24" height="24" fill="#17151C"/>'
            . '<text x="120" y="125" text-anchor="middle" font-family="monospace" font-size="11" fill="#17151C">'
            . $escaped
            . '</text></svg>';
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
    }
}

if (!function_exists('pfgResolvePdo')) {
    function pfgResolvePdo($maybePdo = null): PDO
    {
        if ($maybePdo instanceof PDO) {
            return $maybePdo;
        }
        if (function_exists('getDb')) {
            return getDb();
        }
        throw new RuntimeException('Database connection (PDO) is not available.');
    }
}

if (!function_exists('pfgFormatCredentialRow')) {
    /**
     * Normalizes a qr_credentials row with both snake_case and camelCase keys.
     */
    function pfgFormatCredentialRow(array $row): array
    {
        return [
            'id'                       => $row['id'],
            'credential_code'          => $row['credential_code'],
            'credentialCode'           => $row['credential_code'],
            'token_hash'               => $row['token_hash'],
            'tokenHash'                => $row['token_hash'],
            'registration_id'          => $row['registration_id'],
            'registrationId'           => $row['registration_id'],
            'user_id'                  => $row['user_id'],
            'userId'                   => $row['user_id'],
            'edition_id'               => $row['edition_id'],
            'editionId'                => $row['edition_id'],
            'category_code'            => $row['category_code'],
            'categoryCode'             => $row['category_code'],
            'status'                   => $row['status'],
            'issued_at'                => $row['issued_at'],
            'issuedAt'                 => $row['issued_at'],
            'issuedAtIst'              => formatIstTimestamp($row['issued_at'] ?? null),
            'revoked_at'               => $row['revoked_at'] ?? null,
            'revokedAt'                => $row['revoked_at'] ?? null,
            'revoked_by'               => $row['revoked_by'] ?? null,
            'revokedBy'                => $row['revoked_by'] ?? null,
            'revocation_reason'        => $row['revocation_reason'] ?? null,
            'revocationReason'         => $row['revocation_reason'] ?? null,
            'replaced_by_credential_id'=> $row['replaced_by_credential_id'] ?? null,
            'replacedByCredentialId'   => $row['replaced_by_credential_id'] ?? null,
        ];
    }
}

if (!function_exists('pfgRecordCredentialEvent')) {
    function pfgRecordCredentialEvent(
        PDO $pdo,
        string $credentialId,
        ?string $actorUserId,
        string $eventType,
        array $details = []
    ): void {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO credential_events (id, credential_id, actor_user_id, event_type, details, created_at)
                 VALUES (:id, :credential_id, :actor_user_id, :event_type, :details, :created_at)'
            );
            $stmt->execute([
                ':id'            => uuid4(),
                ':credential_id' => $credentialId,
                ':actor_user_id' => ($actorUserId !== null && $actorUserId !== '') ? $actorUserId : null,
                ':event_type'    => $eventType,
                ':details'       => jsonEncode($details),
                ':created_at'    => nowSql(),
            ]);
        } catch (Throwable $e) {
            // Do not fail core credential operation if event table is unavailable
        }
    }
}

if (!function_exists('generateUniqueRegistrationCode')) {
    /**
     * Generates a unique registration code in format PFG-2027-000001-XXXX.
     * Accepts optional PDO instance (defaults to getDb()).
     */
    function generateUniqueRegistrationCode(?PDO $pdo = null): string
    {
        $db = pfgResolvePdo($pdo);
        $countStmt = $db->query('SELECT COUNT(*) FROM registrations');
        $seq = ((int)$countStmt->fetchColumn()) + 1;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $padded = str_pad((string)($seq + $attempt), 6, '0', STR_PAD_LEFT);
            $candidate = "PFG-2027-{$padded}-{$suffix}";

            $chk = $db->prepare('SELECT id FROM registrations WHERE registration_code = :code LIMIT 1');
            $chk->execute([':code' => $candidate]);
            if (!$chk->fetch()) {
                return $candidate;
            }
        }

        return 'PFG-2027-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}

if (!function_exists('issueCredentialForRegistration')) {
    /**
     * Issues a new ACTIVE QR credential for an eligible (CONFIRMED or APPROVED) registration.
     * Never issues active credentials to rejected, draft, or unpaid registrations where payment is required.
     *
     * Supports both signatures:
     *   issueCredentialForRegistration(PDO $pdo, string $registrationId, ?string $issuedByUserId = null)
     *   issueCredentialForRegistration(string $registrationId, ?string $issuedByUserId = null)
     */
    function issueCredentialForRegistration(
        $pdoOrRegistrationId,
        ?string $registrationIdOrActor = null,
        ?string $issuedByUserId = null
    ): array {
        if ($pdoOrRegistrationId instanceof PDO) {
            $pdo = $pdoOrRegistrationId;
            $registrationId = (string)$registrationIdOrActor;
            $actorUserId = $issuedByUserId;
        } else {
            $pdo = pfgResolvePdo();
            $registrationId = (string)$pdoOrRegistrationId;
            $actorUserId = $registrationIdOrActor;
        }

        if (trim($registrationId) === '') {
            throw new InvalidArgumentException('Registration ID is required.');
        }

        $regStmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id LIMIT 1');
        $regStmt->execute([':id' => $registrationId]);
        $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
            throw new RuntimeException('Registration not found.');
        }

        $eligibleStatuses = ['CONFIRMED', 'APPROVED'];
        if (!in_array($reg['status'], $eligibleStatuses, true)) {
            throw new RuntimeException(
                "Cannot issue QR credential while registration status is {$reg['status']}."
            );
        }

        if (!in_array($reg['payment_status'], ['NOT_REQUIRED', 'CAPTURED'], true)) {
            throw new RuntimeException(
                "Cannot issue QR credential while payment status is {$reg['payment_status']}."
            );
        }

        // Fetch all existing credentials for this registration to guarantee unique reissue codes even after revocation
        $existingStmt = $pdo->prepare(
            'SELECT * FROM qr_credentials WHERE registration_id = :reg_id ORDER BY issued_at ASC'
        );
        $existingStmt->execute([':reg_id' => $reg['id']]);
        $existingCreds = $existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $activeCreds = array_values(array_filter(
            $existingCreds,
            static fn(array $c): bool => ($c['status'] ?? '') === 'ACTIVE'
        ));

        $credentialCode = (string)$reg['registration_code'];
        $finalCode = count($existingCreds) > 0
            ? $credentialCode . '-R' . (count($existingCreds) + 1)
            : $credentialCode;

        $credentialId = uuid4();
        $rawToken = generateOpaqueQrToken($credentialId . ':' . $finalCode);
        $tokenHash = hashQrToken($rawToken);
        $now = nowSql();

        // Store ONLY token_hash in qr_credentials.token_hash (zero raw token / zero PII stored)
        $insertStmt = $pdo->prepare(
            'INSERT INTO qr_credentials (
                id, credential_code, token_hash, registration_id, user_id, edition_id, category_code, status, issued_at
            ) VALUES (
                :id, :credential_code, :token_hash, :registration_id, :user_id, :edition_id, :category_code, :status, :issued_at
            )'
        );
        $insertStmt->execute([
            ':id'              => $credentialId,
            ':credential_code' => $finalCode,
            ':token_hash'      => $tokenHash,
            ':registration_id' => $reg['id'],
            ':user_id'         => $reg['user_id'],
            ':edition_id'      => $reg['edition_id'],
            ':category_code'   => $reg['category_code'],
            ':status'          => 'ACTIVE',
            ':issued_at'       => $now,
        ]);

        // Supersede any previously active credentials
        if (count($activeCreds) > 0) {
            $revokeStmt = $pdo->prepare(
                'UPDATE qr_credentials
                 SET status = :status,
                     revoked_at = :revoked_at,
                     revoked_by = :revoked_by,
                     revocation_reason = :reason,
                     replaced_by_credential_id = :replaced_by
                 WHERE id = :id'
            );
            foreach ($activeCreds as $old) {
                $revokeStmt->execute([
                    ':status'      => 'REVOKED',
                    ':revoked_at'  => $now,
                    ':revoked_by'  => ($actorUserId !== null && $actorUserId !== '') ? $actorUserId : null,
                    ':reason'      => 'Superseded by reissued credential',
                    ':replaced_by' => $credentialId,
                    ':id'          => $old['id'],
                ]);
            }
        }

        pfgRecordCredentialEvent(
            $pdo,
            $credentialId,
            $actorUserId,
            count($activeCreds) > 0 ? 'REISSUED' : 'ISSUED',
            ['credentialCode' => $finalCode, 'registrationId' => $reg['id']]
        );

        $createdRow = [
            'id'                        => $credentialId,
            'credential_code'           => $finalCode,
            'token_hash'                => $tokenHash,
            'registration_id'           => $reg['id'],
            'user_id'                   => $reg['user_id'],
            'edition_id'                => $reg['edition_id'],
            'category_code'             => $reg['category_code'],
            'status'                    => 'ACTIVE',
            'issued_at'                 => $now,
            'revoked_at'                => null,
            'revoked_by'                => null,
            'revocation_reason'         => null,
            'replaced_by_credential_id' => null,
        ];

        return [
            'credential'     => pfgFormatCredentialRow($createdRow),
            'credentialCode' => $finalCode,
            'rawToken'       => $rawToken,
            'token'          => $rawToken,
            'qrPayload'      => $rawToken,
            'qrDataUrl'      => generateQrSvgDataUrl($rawToken),
        ];
    }
}

if (!function_exists('revokeCredential')) {
    /**
     * Revokes an existing QR credential and logs a REVOKED credential event.
     *
     * Supports both argument conventions:
     *   revokeCredential(PDO $pdo, string $credentialId, string $reason, ?string $actorUserId = null)
     *   revokeCredential(PDO $pdo, string $credentialId, ?string $actorUserId, string $reason)
     *   revokeCredential(string $credentialId, ?string $actorUserId, string $reason)
     */
    function revokeCredential(
        $pdoOrCredentialId,
        $arg2 = null,
        $arg3 = null,
        $arg4 = null
    ): ?array {
        if ($pdoOrCredentialId instanceof PDO) {
            $pdo = $pdoOrCredentialId;
            $credentialId = (string)$arg2;
            $firstStr = $arg3 !== null ? (string)$arg3 : '';
            $secondStr = $arg4 !== null ? (string)$arg4 : null;
        } else {
            $pdo = pfgResolvePdo();
            $credentialId = (string)$pdoOrCredentialId;
            $firstStr = $arg2 !== null ? (string)$arg2 : '';
            $secondStr = $arg3 !== null ? (string)$arg3 : null;
        }

        // Detect if $firstStr is a UUID ($actorUserId) and $secondStr is the reason
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if ($secondStr !== null && preg_match($uuidPattern, $firstStr) && !preg_match($uuidPattern, $secondStr)) {
            $actorUserId = $firstStr;
            $reason = $secondStr;
        } else {
            $reason = $firstStr !== '' ? $firstStr : 'Revoked by administrator';
            $actorUserId = ($secondStr !== null && $secondStr !== '') ? $secondStr : null;
        }

        $now = nowSql();
        $stmt = $pdo->prepare(
            'UPDATE qr_credentials
             SET status = :status,
                 revoked_at = :revoked_at,
                 revoked_by = :revoked_by,
                 revocation_reason = :reason
             WHERE id = :id'
        );
        $stmt->execute([
            ':status'     => 'REVOKED',
            ':revoked_at' => $now,
            ':revoked_by' => $actorUserId,
            ':reason'     => $reason,
            ':id'         => $credentialId,
        ]);

        $sel = $pdo->prepare('SELECT * FROM qr_credentials WHERE id = :id LIMIT 1');
        $sel->execute([':id' => $credentialId]);
        $updated = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$updated) {
            return null;
        }

        pfgRecordCredentialEvent($pdo, $credentialId, $actorUserId, 'REVOKED', ['reason' => $reason]);

        return pfgFormatCredentialRow($updated);
    }
}

if (!function_exists('pfgResolveEntitlementScope')) {
    /**
     * Resolves an attendance entitlement scope key from an override or the festival edition's attendance policy.
     */
    function pfgResolveEntitlementScope(PDO $pdo, string $editionId, $scopeOption = null): string
    {
        $override = null;
        if (is_string($scopeOption) && trim($scopeOption) !== '') {
            $override = trim($scopeOption);
        } elseif (is_array($scopeOption)) {
            if (!empty($scopeOption['scopeOverride']) && is_string($scopeOption['scopeOverride'])) {
                $override = trim($scopeOption['scopeOverride']);
            } elseif (!empty($scopeOption['entitlementScope']) && is_string($scopeOption['entitlementScope'])) {
                $override = trim($scopeOption['entitlementScope']);
            }
        }

        if ($override !== null && $override !== '') {
            return $override;
        }

        $edStmt = $pdo->prepare('SELECT attendance_policy FROM festival_editions WHERE id = :id LIMIT 1');
        $edStmt->execute([':id' => $editionId]);
        $ed = $edStmt->fetch(PDO::FETCH_ASSOC);

        if ($ed && ($ed['attendance_policy'] ?? '') === 'ONCE_PER_FESTIVAL') {
            return 'FESTIVAL';
        }
        return getIstDateString();
    }
}

if (!function_exists('verifyCredentialInput')) {
    /**
     * Verifies a scanned QR token (PFG27.<nonce>.<sig>) OR manual credential/registration code.
     *
     * CRITICAL SECURITY & DATA MINIMIZATION GUARANTEE:
     * Returns ONLY registrationCode, participantName, categoryCode, categoryName,
     * registrationStatus, credentialStatus, attendingDays, and alreadyCheckedIn (plus
     * check-in status metadata). NEVER returns disability status, disability categories,
     * medical notes, accommodation disclosures, email, or phone number!
     *
     * Supports both signatures:
     *   verifyCredentialInput(PDO $pdo, string $scanInput, $entitlementScope = null)
     *   verifyCredentialInput(string $scanInput, $options = null)
     */
    function verifyCredentialInput($pdoOrInput, $arg2 = null, $arg3 = null): array
    {
        if ($pdoOrInput instanceof PDO) {
            $pdo = $pdoOrInput;
            $rawInput = (string)$arg2;
            $scopeOption = $arg3;
        } else {
            $pdo = pfgResolvePdo();
            $rawInput = (string)$pdoOrInput;
            $scopeOption = $arg2;
        }

        $cleaned = trim($rawInput);
        if ($cleaned === '') {
            return [
                'valid'              => false,
                'reason'             => 'NOT_FOUND',
                'message'            => 'Empty credential input.',
                'alreadyCheckedIn'   => false,
                'attendanceStatus'   => 'INELIGIBLE',
                'registrationCode'   => null,
                'participantName'    => null,
                'categoryCode'       => null,
                'categoryName'       => null,
                'registrationStatus' => null,
                'credentialStatus'   => null,
                'attendingDays'      => [],
            ];
        }

        $credential = null;

        if (str_starts_with($cleaned, 'PFG27.')) {
            if (!isValidQrTokenFormat($cleaned)) {
                return [
                    'valid'              => false,
                    'reason'             => 'NOT_FOUND',
                    'message'            => 'Invalid or tampered QR credential signature.',
                    'alreadyCheckedIn'   => false,
                    'attendanceStatus'   => 'INELIGIBLE',
                    'registrationCode'   => null,
                    'participantName'    => null,
                    'categoryCode'       => null,
                    'categoryName'       => null,
                    'registrationStatus' => null,
                    'credentialStatus'   => null,
                    'attendingDays'      => [],
                ];
            }
            $tokenHash = hashQrToken($cleaned);
            $stmt = $pdo->prepare('SELECT * FROM qr_credentials WHERE token_hash = :hash LIMIT 1');
            $stmt->execute([':hash' => $tokenHash]);
            $credential = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $upper = strtoupper($cleaned);
            $stmt = $pdo->prepare(
                'SELECT * FROM qr_credentials WHERE UPPER(credential_code) = :code ORDER BY issued_at DESC LIMIT 1'
            );
            $stmt->execute([':code' => $upper]);
            $credential = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$credential) {
                $regStmt = $pdo->prepare(
                    'SELECT id FROM registrations WHERE UPPER(registration_code) = :code AND deleted_at IS NULL LIMIT 1'
                );
                $regStmt->execute([':code' => $upper]);
                $regByCode = $regStmt->fetch(PDO::FETCH_ASSOC);
                if ($regByCode) {
                    $credByRegStmt = $pdo->prepare(
                        'SELECT * FROM qr_credentials WHERE registration_id = :reg_id ORDER BY issued_at DESC LIMIT 1'
                    );
                    $credByRegStmt->execute([':reg_id' => $regByCode['id']]);
                    $credential = $credByRegStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }

        if (!$credential) {
            return [
                'valid'              => false,
                'reason'             => 'NOT_FOUND',
                'message'            => 'No festival credential found matching this QR code or Registration ID.',
                'alreadyCheckedIn'   => false,
                'attendanceStatus'   => 'INELIGIBLE',
                'registrationCode'   => null,
                'participantName'    => null,
                'categoryCode'       => null,
                'categoryName'       => null,
                'registrationStatus' => null,
                'credentialStatus'   => null,
                'attendingDays'      => [],
            ];
        }

        // Strict column projection on registrations: NEVER select disability, medical, email, or phone columns
        $regStmt = $pdo->prepare(
            'SELECT r.id, r.registration_code, r.category_id, r.category_code, r.status, r.payment_status,
                    r.full_name, r.attending_days, c.name AS category_name
             FROM registrations r
             LEFT JOIN registration_categories c ON c.id = r.category_id
             WHERE r.id = :id AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $regStmt->execute([':id' => $credential['registration_id']]);
        $reg = $regStmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
            return [
                'valid'              => false,
                'reason'             => 'NOT_FOUND',
                'message'            => 'Associated registration record missing.',
                'alreadyCheckedIn'   => false,
                'attendanceStatus'   => 'INELIGIBLE',
                'registrationCode'   => null,
                'participantName'    => null,
                'categoryCode'       => null,
                'categoryName'       => null,
                'registrationStatus' => null,
                'credentialStatus'   => null,
                'attendingDays'      => [],
            ];
        }

        $attendingDays = jsonDecode($reg['attending_days'] ?? '[]', []);
        if (!is_array($attendingDays)) {
            $attendingDays = [];
        }
        $categoryName = !empty($reg['category_name']) ? (string)$reg['category_name'] : (string)$reg['category_code'];

        $baseMinData = [
            'credentialId'       => (string)$credential['id'],
            'credentialCode'     => (string)$credential['credential_code'],
            'registrationId'     => (string)$reg['id'],
            'registrationCode'   => (string)$reg['registration_code'],
            'participantName'    => (string)$reg['full_name'],
            'categoryCode'       => (string)$reg['category_code'],
            'categoryName'       => $categoryName,
            'credentialStatus'   => (string)$credential['status'],
            'registrationStatus' => (string)$reg['status'],
            'attendingDays'      => array_values($attendingDays),
        ];

        if ($credential['status'] !== 'ACTIVE') {
            $revReason = !empty($credential['revocation_reason'])
                ? ': ' . $credential['revocation_reason']
                : '.';
            return array_merge($baseMinData, [
                'valid'            => false,
                'reason'           => 'REVOKED',
                'message'          => "Credential is {$credential['status']}{$revReason}",
                'alreadyCheckedIn' => false,
                'attendanceStatus' => 'INELIGIBLE',
                'entitlementScope' => null,
                'existingCheckin'  => null,
            ]);
        }

        if (!in_array($reg['status'], ['CONFIRMED', 'APPROVED'], true)) {
            return array_merge($baseMinData, [
                'valid'            => false,
                'reason'           => 'INELIGIBLE_REGISTRATION',
                'message'          => "Registration status is {$reg['status']} and not eligible for entry.",
                'alreadyCheckedIn' => false,
                'attendanceStatus' => 'INELIGIBLE',
                'entitlementScope' => null,
                'existingCheckin'  => null,
            ]);
        }

        if (!in_array($reg['payment_status'], ['NOT_REQUIRED', 'CAPTURED'], true)) {
            return array_merge($baseMinData, [
                'valid'            => false,
                'reason'           => 'PAYMENT_PENDING',
                'message'          => "Payment status is {$reg['payment_status']}. Entry requires verified payment.",
                'alreadyCheckedIn' => false,
                'attendanceStatus' => 'INELIGIBLE',
                'entitlementScope' => null,
                'existingCheckin'  => null,
            ]);
        }

        $entitlementScope = pfgResolveEntitlementScope($pdo, (string)$credential['edition_id'], $scopeOption);

        $chkStmt = $pdo->prepare(
            'SELECT checked_in_at, venue_label, checkin_date_ist
             FROM checkins
             WHERE credential_id = :cred_id AND entitlement_scope = :scope
             LIMIT 1'
        );
        $chkStmt->execute([
            ':cred_id' => $credential['id'],
            ':scope'   => $entitlementScope,
        ]);
        $existingCheckin = $chkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCheckin) {
            return array_merge($baseMinData, [
                'valid'            => false,
                'reason'           => 'ALREADY_CHECKED_IN',
                'message'          => 'ALREADY CHECKED IN for this attendance period.',
                'alreadyCheckedIn' => true,
                'attendanceStatus' => 'ALREADY_CHECKED_IN',
                'entitlementScope' => $entitlementScope,
                'existingCheckin'  => [
                    'checkedInAt'    => (string)$existingCheckin['checked_in_at'],
                    'checkedInAtIst' => formatIstTimestamp($existingCheckin['checked_in_at']),
                    'venueLabel'     => (string)$existingCheckin['venue_label'],
                    'checkinDateIst' => (string)$existingCheckin['checkin_date_ist'],
                ],
            ]);
        }

        return array_merge($baseMinData, [
            'valid'            => true,
            'reason'           => 'VALID',
            'message'          => 'Valid credential. Ready to confirm entry.',
            'alreadyCheckedIn' => false,
            'attendanceStatus' => 'NOT_CHECKED_IN',
            'entitlementScope' => $entitlementScope,
            'existingCheckin'  => null,
        ]);
    }
}

if (!function_exists('confirmParticipantCheckin')) {
    /**
     * Explicitly confirms a participant check-in using a database transaction and the
     * unique index checkins_credential_scope_unique (credential_id, entitlement_scope)
     * to prevent duplicate check-ins even under concurrent requests.
     *
     * Supports both signatures:
     *   confirmParticipantCheckin(PDO $pdo, array $params)
     *   confirmParticipantCheckin(array $params)
     */
    function confirmParticipantCheckin($pdoOrParams, ?array $maybeParams = null): array
    {
        if ($pdoOrParams instanceof PDO) {
            $pdo = $pdoOrParams;
            $params = $maybeParams ?? [];
        } else {
            $pdo = pfgResolvePdo();
            $params = is_array($pdoOrParams) ? $pdoOrParams : [];
        }

        $credentialId = trim((string)($params['credentialId'] ?? $params['credential_id'] ?? ''));
        $operatorUserId = trim((string)($params['operatorUserId'] ?? $params['operator_user_id'] ?? ''));
        $venueLabel = trim((string)($params['venueLabel'] ?? $params['venue_label'] ?? 'Main Gate — Sama Indoor Stadium'));
        $stationId = !empty($params['stationId']) ? trim((string)$params['stationId']) : (!empty($params['station_id']) ? trim((string)$params['station_id']) : null);
        $method = trim((string)($params['method'] ?? 'QR_CAMERA'));
        if (!in_array($method, ['QR_CAMERA', 'USB_SCANNER', 'MANUAL_ID'], true)) {
            $method = 'QR_CAMERA';
        }
        $scopeOverride = $params['scopeOverride'] ?? $params['entitlementScope'] ?? $params['entitlement_scope'] ?? null;

        if ($credentialId === '' || $operatorUserId === '') {
            return [
                'success' => false,
                'message' => 'Credential ID and operator ID are required.',
            ];
        }

        $credStmt = $pdo->prepare('SELECT * FROM qr_credentials WHERE id = :id LIMIT 1');
        $credStmt->execute([':id' => $credentialId]);
        $credential = $credStmt->fetch(PDO::FETCH_ASSOC);

        if (!$credential || ($credential['status'] ?? '') !== 'ACTIVE') {
            return [
                'success' => false,
                'message' => 'Credential is not active.',
            ];
        }

        $todayIst = getIstDateString();
        $entitlementScope = pfgResolveEntitlementScope($pdo, (string)$credential['edition_id'], $scopeOverride);

        $startedTx = false;
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTx = true;
            }

            $checkinId = uuid4();
            $checkedInAt = nowSql();

            $insStmt = $pdo->prepare(
                'INSERT INTO checkins (
                    id, edition_id, credential_id, registration_id, user_id, operator_user_id,
                    station_id, venue_label, entitlement_scope, checkin_date_ist, method, result, checked_in_at
                ) VALUES (
                    :id, :edition_id, :credential_id, :registration_id, :user_id, :operator_user_id,
                    :station_id, :venue_label, :entitlement_scope, :checkin_date_ist, :method, :result, :checked_in_at
                )'
            );
            $insStmt->execute([
                ':id'                => $checkinId,
                ':edition_id'        => $credential['edition_id'],
                ':credential_id'     => $credential['id'],
                ':registration_id'   => $credential['registration_id'],
                ':user_id'           => $credential['user_id'],
                ':operator_user_id'  => $operatorUserId,
                ':station_id'        => ($stationId !== null && $stationId !== '') ? $stationId : null,
                ':venue_label'       => $venueLabel !== '' ? $venueLabel : 'Main Gate — Sama Indoor Stadium',
                ':entitlement_scope' => $entitlementScope,
                ':checkin_date_ist'  => $todayIst,
                ':method'            => $method,
                ':result'            => 'CHECKED_IN',
                ':checked_in_at'     => $checkedInAt,
            ]);

            pfgRecordCredentialEvent($pdo, (string)$credential['id'], $operatorUserId, 'CHECKED_IN', [
                'venueLabel'       => $venueLabel,
                'entitlementScope' => $entitlementScope,
                'method'           => $method,
            ]);

            if ($startedTx && $pdo->inTransaction()) {
                $pdo->commit();
            }

            $inserted = [
                'id'                => $checkinId,
                'edition_id'        => $credential['edition_id'],
                'editionId'         => $credential['edition_id'],
                'credential_id'     => $credential['id'],
                'credentialId'      => $credential['id'],
                'registration_id'   => $credential['registration_id'],
                'registrationId'    => $credential['registration_id'],
                'user_id'           => $credential['user_id'],
                'userId'            => $credential['user_id'],
                'operator_user_id'  => $operatorUserId,
                'operatorUserId'    => $operatorUserId,
                'station_id'        => $stationId,
                'stationId'         => $stationId,
                'venue_label'       => $venueLabel,
                'venueLabel'        => $venueLabel,
                'entitlement_scope' => $entitlementScope,
                'entitlementScope'  => $entitlementScope,
                'checkin_date_ist'  => $todayIst,
                'checkinDateIst'    => $todayIst,
                'method'            => $method,
                'result'            => 'CHECKED_IN',
                'checked_in_at'     => $checkedInAt,
                'checkedInAt'       => $checkedInAt,
                'checkedInAtIst'    => formatIstTimestamp($checkedInAt),
            ];

            return [
                'success' => true,
                'message' => 'Participant checked in successfully.',
                'checkin' => $inserted,
            ];
        } catch (Throwable $err) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $msg = strtolower($err->getMessage());
            $code = (string)$err->getCode();
            $isDuplicate = $code === '23000'
                || $code === '23505'
                || str_contains($msg, 'unique')
                || str_contains($msg, 'duplicate')
                || str_contains($msg, '1062');

            if ($isDuplicate) {
                pfgRecordCredentialEvent($pdo, (string)$credential['id'], $operatorUserId, 'DUPLICATE_ATTEMPT', [
                    'venueLabel'       => $venueLabel,
                    'entitlementScope' => $entitlementScope,
                ]);

                $existStmt = $pdo->prepare(
                    'SELECT checked_in_at, venue_label, checkin_date_ist
                     FROM checkins
                     WHERE credential_id = :cred_id AND entitlement_scope = :scope
                     LIMIT 1'
                );
                $existStmt->execute([
                    ':cred_id' => $credential['id'],
                    ':scope'   => $entitlementScope,
                ]);
                $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'success'         => false,
                    'duplicate'       => true,
                    'message'         => 'ALREADY CHECKED IN — Duplicate check-in prevented.',
                    'existingCheckin' => $existing ? [
                        'checkedInAt'    => (string)$existing['checked_in_at'],
                        'checkedInAtIst' => formatIstTimestamp($existing['checked_in_at']),
                        'venueLabel'     => (string)$existing['venue_label'],
                        'checkinDateIst' => (string)$existing['checkin_date_ist'],
                    ] : null,
                ];
            }

            throw $err;
        }
    }
}
