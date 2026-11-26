<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phase 3 Migration 004
 * 1. Non-Destructive Duplicate Table Consolidation (6 legacy duplicate pairs -> canonical tables)
 *    - NEVER drops legacy tables; NEVER silently overwrites conflicting canonical rows (reports conflicts).
 * 2. Registration Sequence Floor Synchronization (ensures next atomic Registration ID > max legacy code).
 * 3. Historical Sensitive Data Encryption & Secret Redaction (FND-11, FND-14, FND-22):
 *    - Encrypts legacy plaintext disability & accommodation free-text without double-encryption.
 *    - Migrates legacy plaintext `users.mfa_secret` -> `users.mfa_secret_encrypted` and clears `mfa_secret`.
 *    - Redacts legacy reusable secrets from `notification_queue.payload` and `registration_responses.answers`
 *      without deleting any historical rows.
 */

require_once dirname(__DIR__, 2) . '/includes/registration-id.php';

return static function (PDO $pdo): array {
    $report = [
        'consolidated' => [
            'accommodation_requests'             => 0,
            'participant_disability_disclosures' => 0,
            'delegate_details'                   => 0,
            'coupons'                            => 0,
            'payment_orders'                     => 0,
            'notification_queue'                 => 0,
        ],
        'conflicts' => [],
        'sequenceFloor' => null,
        'encryptedFields' => [
            'accommodation_requests'             => 0,
            'accommodation_assignments'          => 0,
            'participant_disability_disclosures' => 0,
            'registration_accommodations_legacy' => 0,
            'registration_disabilities_legacy'   => 0,
            'users_mfa_secret_migrated'          => 0,
        ],
        'redactedRows' => [
            'notification_queue'     => 0,
            'registration_responses' => 0,
        ],
    ];

    // =========================================================================
    // 1. NON-DESTRUCTIVE DUPLICATE CONSOLIDATION
    // =========================================================================

    // 1a. registration_accommodations -> accommodation_requests
    if (pfgDbTableExists($pdo, 'registration_accommodations') && pfgDbTableExists($pdo, 'accommodation_requests')) {
        $legacyRows = $pdo->query('SELECT * FROM registration_accommodations')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkById = $pdo->prepare('SELECT id, status, free_text_details, coordinator_notes FROM accommodation_requests WHERE id = :id LIMIT 1');
        $checkByReg = $pdo->prepare('SELECT id, status, free_text_details, coordinator_notes FROM accommodation_requests WHERE registration_id = :rid ORDER BY created_at DESC LIMIT 1');
        $insAcc = $pdo->prepare('
            INSERT IGNORE INTO accommodation_requests
            (id, registration_id, user_id, accommodation_code, selected_types, free_text_details, notes, status, assigned_to_user_id, coordinator_notes, created_at, updated_at)
            VALUES (:id, :rid, :uid, :code, :types, :ft, :notes, :status, :assigned, :cnotes, :cat, :uat)
        ');

        foreach ($legacyRows as $lr) {
            $checkById->execute([':id' => $lr['id']]);
            $sameId = $checkById->fetch(PDO::FETCH_ASSOC);
            if ($sameId) {
                if ((string)($sameId['status'] ?? '') !== (string)($lr['status'] ?? '')) {
                    $report['conflicts'][] = [
                        'pair'           => 'registration_accommodations -> accommodation_requests',
                        'registrationId' => $lr['registration_id'],
                        'legacyId'       => $lr['id'],
                        'reason'         => 'Status mismatch on matching ID; preserved canonical row.',
                    ];
                }
                continue;
            }

            $checkByReg->execute([':rid' => $lr['registration_id']]);
            $sameReg = $checkByReg->fetch(PDO::FETCH_ASSOC);
            if ($sameReg) {
                if ((string)($sameReg['status'] ?? '') !== (string)($lr['status'] ?? '')) {
                    $report['conflicts'][] = [
                        'pair'           => 'registration_accommodations -> accommodation_requests',
                        'registrationId' => $lr['registration_id'],
                        'legacyId'       => $lr['id'],
                        'canonicalId'    => $sameReg['id'],
                        'reason'         => 'Distinct UUID mirror row with status mismatch; preserved canonical row without overwrite.',
                    ];
                }
                continue;
            }

            $insAcc->execute([
                ':id'       => $lr['id'],
                ':rid'      => $lr['registration_id'],
                ':uid'      => $lr['user_id'] ?? null,
                ':code'     => $lr['accommodation_code'] ?? null,
                ':types'    => $lr['selected_types'] ?? null,
                ':ft'       => $lr['free_text_details'] ?? null,
                ':notes'    => $lr['notes'] ?? null,
                ':status'   => $lr['status'] ?? 'REQUESTED',
                ':assigned' => $lr['assigned_to_user_id'] ?? null,
                ':cnotes'   => $lr['coordinator_notes'] ?? null,
                ':cat'      => $lr['created_at'] ?? nowSql(),
                ':uat'      => $lr['updated_at'] ?? nowSql(),
            ]);
            $report['consolidated']['accommodation_requests'] += $insAcc->rowCount();
        }
    }

    // 1b. registration_disabilities -> participant_disability_disclosures
    if (pfgDbTableExists($pdo, 'registration_disabilities') && pfgDbTableExists($pdo, 'participant_disability_disclosures')) {
        $legacyRows = $pdo->query('SELECT * FROM registration_disabilities')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkDis = $pdo->prepare('
            SELECT id FROM participant_disability_disclosures
            WHERE id = :id
               OR (registration_id = :rid AND COALESCE(disability_code, category_code, \'\') = :code)
            LIMIT 1
        ');
        $insDis = $pdo->prepare('
            INSERT IGNORE INTO participant_disability_disclosures
            (id, registration_id, disability_category_id, disability_code, category_code, self_description, notes, created_at)
            VALUES (:id, :rid, :dcid, :dcode, :ccode, :sdesc, :notes, :cat)
        ');
        foreach ($legacyRows as $lr) {
            $codeKey = (string)($lr['disability_code'] ?? $lr['category_code'] ?? '');
            $checkDis->execute([':id' => $lr['id'], ':rid' => $lr['registration_id'], ':code' => $codeKey]);
            if ($checkDis->fetch(PDO::FETCH_ASSOC)) {
                continue;
            }
            $insDis->execute([
                ':id'    => $lr['id'],
                ':rid'   => $lr['registration_id'],
                ':dcid'  => $lr['disability_category_id'] ?? null,
                ':dcode' => $lr['disability_code'] ?? null,
                ':ccode' => $lr['category_code'] ?? null,
                ':sdesc' => $lr['self_description'] ?? null,
                ':notes' => $lr['notes'] ?? null,
                ':cat'   => $lr['created_at'] ?? nowSql(),
            ]);
            $report['consolidated']['participant_disability_disclosures'] += $insDis->rowCount();
        }
    }

    // 1c. delegate_profiles -> delegate_details
    if (pfgDbTableExists($pdo, 'delegate_profiles') && pfgDbTableExists($pdo, 'delegate_details')) {
        $legacyRows = $pdo->query('SELECT * FROM delegate_profiles')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkDel = $pdo->prepare('SELECT id FROM delegate_details WHERE registration_id = :rid LIMIT 1');
        $insDel = $pdo->prepare('
            INSERT IGNORE INTO delegate_details
            (id, registration_id, gender, age, city_state, para_sports_interests, outstation_travel_required, accommodation_needed, food_preference, emergency_contact_name, emergency_contact_phone, guardian_name, created_at)
            VALUES (:id, :rid, :gender, :age, :cs, :psi, :otr, :an, :fp, :ecn, :ecp, :gn, :cat)
        ');
        foreach ($legacyRows as $lr) {
            $checkDel->execute([':rid' => $lr['registration_id']]);
            if ($checkDel->fetch(PDO::FETCH_ASSOC)) {
                continue;
            }
            $insDel->execute([
                ':id'     => $lr['id'],
                ':rid'    => $lr['registration_id'],
                ':gender' => $lr['gender'] ?? null,
                ':age'    => $lr['age'] ?? null,
                ':cs'     => $lr['city_state'] ?? null,
                ':psi'    => $lr['para_sports_interests'] ?? null,
                ':otr'    => $lr['outstation_travel_required'] ?? 0,
                ':an'     => $lr['accommodation_needed'] ?? 0,
                ':fp'     => $lr['food_preference'] ?? null,
                ':ecn'    => $lr['emergency_contact_name'] ?? null,
                ':ecp'    => $lr['emergency_contact_phone'] ?? null,
                ':gn'     => $lr['guardian_name'] ?? null,
                ':cat'    => $lr['created_at'] ?? nowSql(),
            ]);
            $report['consolidated']['delegate_details'] += $insDel->rowCount();
        }
    }

    // 1d. coupon_codes -> coupons
    if (pfgDbTableExists($pdo, 'coupon_codes') && pfgDbTableExists($pdo, 'coupons')) {
        $legacyRows = $pdo->query('SELECT * FROM coupon_codes')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkC = $pdo->prepare('SELECT id, used_count, is_active FROM coupons WHERE id = :id OR UPPER(code) = :code LIMIT 1');
        $insC = $pdo->prepare('
            INSERT IGNORE INTO coupons
            (id, edition_id, code, description, discount_type, discount_value, eligible_categories, valid_from, valid_until, max_uses, used_count, max_uses_per_user, is_active, created_by, created_at)
            VALUES (:id, :eid, :code, :desc, :dtype, :dval, :cats, :vf, :vu, :mu, :uc, :mupu, :act, :cby, :cat)
        ');
        foreach ($legacyRows as $lr) {
            $checkC->execute([':id' => $lr['id'], ':code' => strtoupper((string)$lr['code'])]);
            $existing = $checkC->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ((int)$existing['used_count'] !== (int)$lr['used_count'] || (int)$existing['is_active'] !== (int)$lr['is_active']) {
                    $report['conflicts'][] = [
                        'pair'   => 'coupon_codes -> coupons',
                        'code'   => $lr['code'],
                        'reason' => 'used_count or is_active differs; preserved canonical coupons row.',
                    ];
                }
                continue;
            }
            $insC->execute([
                ':id'    => $lr['id'],
                ':eid'   => $lr['edition_id'] ?? null,
                ':code'  => $lr['code'],
                ':desc'  => $lr['description'] ?? null,
                ':dtype' => $lr['discount_type'],
                ':dval'  => $lr['discount_value'],
                ':cats'  => $lr['eligible_categories'] ?? null,
                ':vf'    => $lr['valid_from'] ?? null,
                ':vu'    => $lr['valid_until'] ?? null,
                ':mu'    => $lr['max_uses'] ?? null,
                ':uc'    => $lr['used_count'] ?? 0,
                ':mupu'  => $lr['max_uses_per_user'] ?? 1,
                ':act'   => $lr['is_active'] ?? 1,
                ':cby'   => $lr['created_by'] ?? null,
                ':cat'   => $lr['created_at'] ?? nowSql(),
            ]);
            $report['consolidated']['coupons'] += $insC->rowCount();
        }
    }

    // 1e. payments -> payment_orders
    if (pfgDbTableExists($pdo, 'payments') && pfgDbTableExists($pdo, 'payment_orders')) {
        $legacyRows = $pdo->query('SELECT * FROM payments')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkP = $pdo->prepare('SELECT id FROM payment_orders WHERE id = :id LIMIT 1');
        $insP = $pdo->prepare('
            INSERT IGNORE INTO payment_orders
            (id, registration_id, user_id, provider, provider_order_id, provider_payment_id, amount_inr, discount_inr, net_amount_inr, currency, status, receipt_number, raw_payload, created_at, updated_at)
            VALUES (:id, :rid, :uid, :prov, :poid, :ppid, :amt, :disc, :net, :cur, :st, :rcpt, :raw, :cat, :uat)
        ');
        foreach ($legacyRows as $lr) {
            $checkP->execute([':id' => $lr['id']]);
            if ($checkP->fetch(PDO::FETCH_ASSOC)) {
                continue;
            }
            $insP->execute([
                ':id'   => $lr['id'],
                ':rid'  => $lr['registration_id'],
                ':uid'  => $lr['user_id'] ?? null,
                ':prov' => $lr['provider'] ?? 'RAZORPAY',
                ':poid' => $lr['provider_order_id'] ?? null,
                ':ppid' => $lr['provider_payment_id'] ?? null,
                ':amt'  => $lr['amount_inr'],
                ':disc' => $lr['discount_inr'] ?? 0,
                ':net'  => $lr['net_amount_inr'],
                ':cur'  => $lr['currency'] ?? 'INR',
                ':st'   => $lr['status'] ?? 'PENDING',
                ':rcpt' => $lr['receipt_number'] ?? null,
                ':raw'  => $lr['raw_payload'] ?? null,
                ':cat'  => $lr['created_at'] ?? nowSql(),
                ':uat'  => $lr['updated_at'] ?? nowSql(),
            ]);
            $report['consolidated']['payment_orders'] += $insP->rowCount();
        }
    }

    // 1f. notification_jobs -> notification_queue
    if (pfgDbTableExists($pdo, 'notification_jobs') && pfgDbTableExists($pdo, 'notification_queue')) {
        $legacyRows = $pdo->query('SELECT * FROM notification_jobs')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $checkN = $pdo->prepare('SELECT id FROM notification_queue WHERE id = :id LIMIT 1');
        $insN = $pdo->prepare('
            INSERT IGNORE INTO notification_queue
            (id, template_code, recipient_user_id, recipient_email, subject, body_html, body_text, payload, status, attempts, max_attempts, last_error, next_retry_at, sent_at, created_at, updated_at)
            VALUES (:id, :tc, :uid, :email, :subj, :bh, :bt, :pay, :st, :att, :matt, :lerr, :nrt, :sat, :cat, :uat)
        ');
        foreach ($legacyRows as $lr) {
            $checkN->execute([':id' => $lr['id']]);
            if ($checkN->fetch(PDO::FETCH_ASSOC)) {
                continue;
            }
            $insN->execute([
                ':id'    => $lr['id'],
                ':tc'    => $lr['template_code'],
                ':uid'   => $lr['recipient_user_id'] ?? null,
                ':email' => $lr['recipient_email'],
                ':subj'  => $lr['subject'],
                ':bh'    => $lr['body_html'] ?? null,
                ':bt'    => $lr['body_text'] ?? null,
                ':pay'   => $lr['payload'] ?? null,
                ':st'    => $lr['status'] ?? 'QUEUED',
                ':att'   => $lr['attempts'] ?? 0,
                ':matt'  => $lr['max_attempts'] ?? 3,
                ':lerr'  => $lr['last_error'] ?? null,
                ':nrt'   => $lr['next_retry_at'] ?? null,
                ':sat'   => $lr['sent_at'] ?? null,
                ':cat'   => $lr['created_at'] ?? nowSql(),
                ':uat'   => $lr['updated_at'] ?? nowSql(),
            ]);
            $report['consolidated']['notification_queue'] += $insN->rowCount();
        }
    }

    // =========================================================================
    // 2. SYNCHRONIZE ATOMIC REGISTRATION SEQUENCE FLOOR ABOVE LEGACY RECORDS
    // =========================================================================
    if (pfgDbTableExists($pdo, 'registration_number_sequences')) {
        $report['sequenceFloor'] = syncRegistrationSequenceFloor($pdo);
    }

    // =========================================================================
    // 3. HISTORICAL SENSITIVE DATA ENCRYPTION & SECRET REDACTION (IDEMPOTENT)
    // =========================================================================
    $encryptTableColumns = static function (PDO $pdo, string $table, array $columns, string $purpose = 'data'): int {
        if (!pfgDbTableExists($pdo, $table)) {
            return 0;
        }
        $selectCols = implode(', ', array_map(static fn(string $c): string => "`{$c}`", array_merge(['id'], $columns)));
        $rows = $pdo->query("SELECT {$selectCols} FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $updatedCount = 0;

        foreach ($rows as $row) {
            $sets = [];
            $params = [':id' => $row['id']];
            foreach ($columns as $col) {
                $val = $row[$col] ?? null;
                if (is_string($val) && trim($val) !== '' && !str_starts_with($val, 'enc:v1:')) {
                    $sets[] = "`{$col}` = :{$col}";
                    $params[":{$col}"] = pfgEncryptValue($val, $purpose);
                }
            }
            if (!empty($sets)) {
                $sql = sprintf('UPDATE `%s` SET %s WHERE `id` = :id', $table, implode(', ', $sets));
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $updatedCount++;
            }
        }
        return $updatedCount;
    };

    $report['encryptedFields']['accommodation_requests'] = $encryptTableColumns(
        $pdo,
        'accommodation_requests',
        ['free_text_details', 'notes', 'coordinator_notes'],
        'data'
    );
    $report['encryptedFields']['accommodation_assignments'] = $encryptTableColumns(
        $pdo,
        'accommodation_assignments',
        ['coordinator_notes'],
        'data'
    );
    $report['encryptedFields']['participant_disability_disclosures'] = $encryptTableColumns(
        $pdo,
        'participant_disability_disclosures',
        ['self_description', 'notes'],
        'data'
    );
    // Also protect archived legacy mirror tables so zero plaintext disability/accommodation free-text remains at rest
    $report['encryptedFields']['registration_accommodations_legacy'] = $encryptTableColumns(
        $pdo,
        'registration_accommodations',
        ['free_text_details', 'notes', 'coordinator_notes'],
        'data'
    );
    $report['encryptedFields']['registration_disabilities_legacy'] = $encryptTableColumns(
        $pdo,
        'registration_disabilities',
        ['self_description', 'notes'],
        'data'
    );

    // 3b. Migrate legacy plaintext users.mfa_secret -> users.mfa_secret_encrypted & clear mfa_secret
    if (pfgDbTableExists($pdo, 'users')) {
        $mfaRows = $pdo->query("SELECT id, mfa_secret, mfa_secret_encrypted FROM users WHERE mfa_secret IS NOT NULL AND mfa_secret <> ''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $updMfa = $pdo->prepare('UPDATE users SET mfa_secret_encrypted = :enc, mfa_secret = NULL WHERE id = :id');
        foreach ($mfaRows as $mr) {
            $enc = !empty($mr['mfa_secret_encrypted']) && str_starts_with((string)$mr['mfa_secret_encrypted'], 'enc:v1:')
                ? (string)$mr['mfa_secret_encrypted']
                : pfgEncryptValue((string)$mr['mfa_secret'], 'mfa');
            $updMfa->execute([':enc' => $enc, ':id' => $mr['id']]);
            $report['encryptedFields']['users_mfa_secret_migrated']++;
        }
    }

    // 3c. Redact legacy reusable secrets from notification_queue without deleting rows
    if (pfgDbTableExists($pdo, 'notification_queue')) {
        $nqRows = $pdo->query('SELECT id, payload, body_html, body_text FROM notification_queue')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $updNq = $pdo->prepare('UPDATE notification_queue SET payload = :pay, body_html = :bh, body_text = :bt WHERE id = :id');
        foreach ($nqRows as $nr) {
            $changed = false;
            $payloadStr = $nr['payload'];
            if (is_string($payloadStr) && $payloadStr !== '') {
                $decoded = json_decode($payloadStr, true);
                if (is_array($decoded)) {
                    $scrubbed = pfgScrubSensitiveArray($decoded);
                    $scrubbedJson = jsonEncode($scrubbed);
                    // Also redact any embedded opaque PFG27.<nonce>.<sig> token or reset-password token query
                    $scrubbedJson = preg_replace('/PFG27\.[A-Za-z0-9_-]{16,64}\.[A-Za-z0-9_-]{16}/', '[REDACTED_TOKEN]', $scrubbedJson) ?? $scrubbedJson;
                    $scrubbedJson = preg_replace('/(token=)([A-Za-z0-9._-]{16,})/i', '$1[REDACTED]', $scrubbedJson) ?? $scrubbedJson;
                    if ($scrubbedJson !== $payloadStr) {
                        $payloadStr = $scrubbedJson;
                        $changed = true;
                    }
                }
            }

            $bodyHtml = $nr['body_html'];
            $bodyText = $nr['body_text'];
            foreach (['bodyHtml', 'bodyText'] as $varName) {
                $val = $$varName;
                if (is_string($val) && $val !== '') {
                    $cleaned = preg_replace('/PFG27\.[A-Za-z0-9_-]{16,64}\.[A-Za-z0-9_-]{16}/', '[REDACTED_TOKEN]', $val) ?? $val;
                    $cleaned = preg_replace('/(reset-password\?token=)([A-Za-z0-9._-]{16,})/i', '$1[REDACTED]', $cleaned) ?? $cleaned;
                    if ($cleaned !== $val) {
                        $$varName = $cleaned;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $updNq->execute([
                    ':pay' => $payloadStr,
                    ':bh'  => $bodyHtml,
                    ':bt'  => $bodyText,
                    ':id'  => $nr['id'],
                ]);
                $report['redactedRows']['notification_queue']++;
            }
        }
    }

    // 3d. Scrub legacy sensitive disability/accommodation/password keys from registration_responses.answers
    if (pfgDbTableExists($pdo, 'registration_responses')) {
        $rrRows = $pdo->query('SELECT id, answers FROM registration_responses')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $updRr = $pdo->prepare('UPDATE registration_responses SET answers = :ans WHERE id = :id');
        $sensitiveKeys = [
            'password',
            'csrfToken',
            'csrf_token',
            'isPersonWithDisability',
            'disabilityCategoryCodes',
            'disabilitySelfDescription',
            'accommodationTypes',
            'accommodationFreeText',
        ];
        foreach ($rrRows as $rr) {
            if (!is_string($rr['answers']) || $rr['answers'] === '') {
                continue;
            }
            $decoded = json_decode($rr['answers'], true);
            if (!is_array($decoded)) {
                continue;
            }
            $dirty = false;
            foreach ($sensitiveKeys as $sk) {
                if (array_key_exists($sk, $decoded)) {
                    unset($decoded[$sk]);
                    $dirty = true;
                }
            }
            if ($dirty) {
                $updRr->execute([
                    ':ans' => jsonEncode($decoded),
                    ':id'  => $rr['id'],
                ]);
                $report['redactedRows']['registration_responses']++;
            }
        }
    }

    return $report;
};
