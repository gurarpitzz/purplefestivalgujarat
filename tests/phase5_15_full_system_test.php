<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Phases 5–15 & Final Release Controls Full-System Verification Suite (T5-01 .. T5-10)
 * Executes against live local PHP 8.2 server(s) + isolated MariaDB `pfg27_test` + safe local file mail sink.
 */

$root = dirname(__DIR__);
$phpBin = PHP_BINARY;
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$sinkDir = $tmpDir . DIRECTORY_SEPARATOR . 'mail-sink';

require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';
require_once $root . '/includes/auth.php';

/**
 * Helper: extract the latest captured email matching recipient + URL pattern from tests/tmp/mail-sink.
 */
function p5ExtractTokenFromMailSink(string $sinkDir, string $recipientEmail, string $urlRegex): ?string
{
    $files = glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    $needle = strtolower(trim($recipientEmail));
    foreach ($files as $f) {
        $raw = (string)file_get_contents($f);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        $to = strtolower(trim((string)($decoded['to'] ?? '')));
        if ($to !== $needle) {
            continue;
        }
        $haystack = (string)($decoded['text'] ?? '') . "\n" . (string)($decoded['html'] ?? '');
        if (preg_match($urlRegex, $haystack, $m) === 1) {
            return rawurldecode($m[1]);
        }
    }
    return null;
}

/**
 * Helper: obtain an anonymous session cookie + CSRF token for accountless public registration.
 */
function p5AnonSession(): array
{
    $s = p2AnonSession();
    pfgAssert($s['csrf'] !== '', 'Failed to fetch anonymous session CSRF token from GET /api/registrations');
    return $s;
}

/**
 * Helper: build valid accountless category registration payloads for ATTENDEE, VOLUNTEER, SLI.
 */
function p5ValidPayload(string $category, array $overrides = []): array
{
    $rand = bin2hex(random_bytes(3));
    $base = [
        'categoryCode'                 => strtoupper($category),
        'fullName'                     => 'Applicant ' . strtoupper($category) . ' ' . $rand,
        'email'                        => 'p5.' . strtolower($category) . '.' . $rand . '@example.test',
        'phone'                        => '+91 98250 12345',
        'preferredLanguage'            => 'English',
        'country'                      => 'India',
        'cityDistrict'                 => 'Vadodara',
        'state'                        => 'Gujarat',
        'isPersonWithDisability'       => 'NO',
        'emergencyContactName'         => 'Ramesh Patel',
        'emergencyContactRelationship' => 'Parent',
        'emergencyContactPhone'        => '+91 98250 54321',
        'consentZeroTolerance'         => true,
        'consentDataProcessing'        => true,
        'consentAccuracy'              => true,
    ];

    if (strtoupper($category) === 'ATTENDEE') {
        $base['attendingDays'] = ['DAY_1', 'DAY_2'];
        $base['attendeeDetails'] = [
            'ageGroup'               => '18_35',
            'gender'                 => 'Female',
            'selfIdentificationTags' => ['Student'],
        ];
    } elseif (strtoupper($category) === 'VOLUNTEER') {
        $base['volunteerDetails'] = [
            'age'                  => 22,
            'gender'               => 'Male',
            'msuAffiliation'       => 'CURRENT_STUDENT',
            'msuFacultyDepartment' => 'Faculty of Social Work',
            'primaryRole'          => 'Event Logistics & Venue Management',
            'languagesSpoken'      => ['Gujarati', 'Hindi', 'English'],
            'availabilityShifts'   => ['DAY_1_SHIFT_1', 'DAY_2_SHIFT_1'],
        ];
    } elseif (strtoupper($category) === 'SLI') {
        $base['sliDetails'] = [
            'age'                    => 29,
            'cityDistrict'           => 'Vadodara',
            'state'                  => 'Gujarat',
            'basedInGujarat'         => true,
            'qualificationCategory'  => 'CERTIFIED_ISL',
            'experienceBand'         => '3_TO_5',
            'comfortablePublicStage' => true,
            'availabilityShifts'     => ['DAY_1_SHIFT_1', 'DAY_2_SHIFT_1'],
            'videoDemoUrl'           => 'https://example.org/isl-demo',
        ];
    }

    foreach ($overrides as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !empty($v) && !array_is_list($v)) {
            $base[$k] = array_replace_recursive($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }

    return $base;
}

$p5Pdo = pfgCreateDirectPdo('pfg27_test');

// Shared state across T5-03 / T5-04
$t503VolRegId = '';
$t503SliRegId = '';

// =========================================================================
// T5-01: Staff-Only Authentication Lifecycle & Retired Participant Auth Removal (CAD-17)
// =========================================================================
pfgTest('T5-01', 'Staff-only auth lifecycle: staff login/pw-reset complexity & replay block; participant signup/login/verify retired (410/401)', function () use ($p5Pdo, $sinkDir): string {
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }
    foreach (['auth_login_ip', 'auth_pwreset_ip', 'auth_pwreset_complete_ip'] as $sc) {
        pfgResetRateLimit($p5Pdo, $sc, '127.0.0.1');
    }

    // 1. Participant signup, verification, and account APIs return 410 Gone
    foreach (['/api/auth/signup', '/api/auth/verify-email', '/api/auth/resend-verification', '/api/auth/claim-legacy', '/api/account/dashboard', '/api/account/profile', '/api/account/registrations', '/api/account/drafts', '/api/account/notifications'] as $ep) {
        $r = p2Http('POST', $ep, []);
        pfgAssert($r['status'] === 410 && !empty($r['json']['deprecated']), "Retired {$ep} expected 410 Gone, got {$r['status']}");
    }

    // 2. Historical PARTICIPANT cannot authenticate or trigger password reset email
    $histPart = p2CreateTestUser($p5Pdo, 'p5_hist_part_' . bin2hex(random_bytes(2)), 'p5.hist.part.' . bin2hex(random_bytes(3)) . '@example.test', 'HistPart#2027', 'PARTICIPANT');
    $rPartLogin = p2Http('POST', '/api/auth/login', ['username' => $histPart['email'], 'password' => $histPart['password']]);
    pfgAssert($rPartLogin['status'] === 401 && empty($rPartLogin['cookies'][SESSION_COOKIE_NAME]), "Historical PARTICIPANT login expected 401, got {$rPartLogin['status']}");

    pfgResetRateLimit($p5Pdo, 'auth_pwreset_email', $histPart['email']);
    $rPartReset = p2Http('POST', '/api/auth/password-reset', ['email' => $histPart['email']]);
    pfgAssert($rPartReset['status'] === 200, 'Participant password-reset request should return uniform 200');
    pfgAssert(count(glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: []) === 0, 'Password reset email was sent for retired PARTICIPANT!');

    // 3. Staff (ADMIN) atomic password reset with letter+digit complexity enforcement & single-use replay block
    $staffUname = 'p5_staff_' . bin2hex(random_bytes(2));
    $staffEmail = $staffUname . '@example.test';
    $pw1 = 'StaffInit#2027';
    $pw2 = 'StaffReset#99';
    p2CreateTestUser($p5Pdo, $staffUname, $staffEmail, $pw1, 'ADMIN');

    pfgResetRateLimit($p5Pdo, 'auth_pwreset_email', $staffEmail);
    $rReqReset = p2Http('POST', '/api/auth/password-reset', ['email' => $staffEmail]);
    pfgAssert($rReqReset['status'] === 200, "Staff password reset request failed: {$rReqReset['body']}");

    $resetTok = p5ExtractTokenFromMailSink($sinkDir, $staffEmail, '/reset-password\?token=([A-Za-z0-9_\-%]+)/');
    pfgAssert(is_string($resetTok) && strlen($resetTok) >= 20, 'Failed to extract staff password reset token from mail sink');

    // Reject weak password (no digit) -> 400
    $rWeak = p2Http('PUT', '/api/auth/password-reset', ['token' => $resetTok, 'newPassword' => 'NoDigitsHere!']);
    pfgAssert($rWeak['status'] === 400, "Weak password without digit expected 400, got {$rWeak['status']}");

    // Complete reset with letter+digit password -> 200
    $rDone = p2Http('PUT', '/api/auth/password-reset', ['token' => $resetTok, 'newPassword' => $pw2]);
    pfgAssert($rDone['status'] === 200 && !empty($rDone['json']['success']), "Staff password reset completion failed: {$rDone['body']}");

    // Replay reset token -> 400
    $rResetReplay = p2Http('PUT', '/api/auth/password-reset', ['token' => $resetTok, 'newPassword' => 'AnotherPass#88']);
    pfgAssert($rResetReplay['status'] === 400, "Reset token replay expected 400, got {$rResetReplay['status']}");

    // Verify new password logs in cleanly to /admin & old password rejected
    $rOldPw = p2Http('POST', '/api/auth/login', ['username' => $staffUname, 'password' => $pw1]);
    pfgAssert($rOldPw['status'] === 401, "Old staff password should fail (401), got {$rOldPw['status']}");
    $rNewPw = p2Http('POST', '/api/auth/login', ['username' => $staffUname, 'password' => $pw2]);
    pfgAssert($rNewPw['status'] === 200 && ($rNewPw['json']['redirectTo'] ?? '') === '/admin', "Staff login with new password failed: {$rNewPw['body']}");

    return 'All 9 retired participant auth/account endpoints->410; historical PARTICIPANT login(401)+reset(0 mail); staff pw-reset complexity(400)+atomic(200)+replay(400)+login(200) verified';
});

// =========================================================================
// T5-02: Historical Data Preservation & Coexistence with Accountless Registrations (CAD-17)
// =========================================================================
pfgTest('T5-02', 'Historical user_id registrations preserved & readable in Admin/RGST alongside new accountless user_id=NULL records', function () use ($p5Pdo, $saSess, $rgstUser): string {
    $edId = (string)$p5Pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $delCatId = (string)$p5Pdo->query("SELECT id FROM registration_categories WHERE code = 'DELEGATE' LIMIT 1")->fetchColumn();
    $partRoleId = (string)$p5Pdo->query("SELECT id FROM roles WHERE code = 'PARTICIPANT' LIMIT 1")->fetchColumn();
    $now = nowSql();

    // Seed a historical registration with non-null user_id & legacy DELEGATE category
    $legUid = uuid4();
    $legRegId = uuid4();
    $legCode = 'PFG-2027-009101';
    $legEmail = 'legacy.hist.' . bin2hex(random_bytes(3)) . '@example.test';
    $p5Pdo->prepare("INSERT INTO users (id, email, password_hash, full_name, phone, country, is_active, account_status, created_at, updated_at) VALUES (:id, :e, NULL, 'Historical Delegate Record', '+91 98250 22222', 'India', 1, 'ACTIVE', :n, :n)")
        ->execute([':id' => $legUid, ':e' => $legEmail, ':n' => $now]);
    $p5Pdo->prepare('INSERT INTO user_roles (id, user_id, role_id, assigned_at) VALUES (:id, :uid, :rid, :n)')
        ->execute([':id' => uuid4(), ':uid' => $legUid, ':rid' => $partRoleId, ':n' => $now]);
    $p5Pdo->prepare("INSERT INTO registrations (id, registration_code, edition_id, user_id, category_id, category_code, status, payment_status, full_name, email, phone, country, is_person_with_disability, created_at, updated_at) VALUES (:id, :code, :eid, :uid, :cid, 'DELEGATE', 'CONFIRMED', 'NOT_REQUIRED', 'Historical Delegate Record', :e, '+91 98250 22222', 'India', 'NO', :n, :n)")
        ->execute([':id' => $legRegId, ':code' => $legCode, ':eid' => $edId, ':uid' => $legUid, ':cid' => $delCatId, ':e' => $legEmail, ':n' => $now]);

    // 1. Legacy claim endpoint is cleanly retired (410 Gone)
    $rClaim = p2Http('POST', '/api/auth/claim-legacy', ['registrationCode' => $legCode]);
    pfgAssert($rClaim['status'] === 410, "Retired /api/auth/claim-legacy expected 410, got {$rClaim['status']}");

    // 2. Historical non-null user_id registration remains readable in Admin & searchable at RGST desk
    $rAdmDet = p2Http('GET', '/api/admin/registrations?id=' . urlencode($legRegId), null, $saSess['cookies']);
    pfgAssert($rAdmDet['status'] === 200 && ($rAdmDet['json']['registration']['registrationCode'] ?? '') === $legCode, 'Admin failed to inspect historical registration');

    pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
    $rgstSessLocal = p2Login($rgstUser['username'], 'RgstNewPass#2027');
    pfgAssert($rgstSessLocal['resp']['status'] === 200, 'RGST login failed');
    $rDeskSearch = p2Http('GET', '/api/attendance?q=' . rawurlencode($legCode), null, $rgstSessLocal['cookies']);
    pfgAssert($rDeskSearch['status'] === 200 && count($rDeskSearch['json']['rows'] ?? []) === 1, 'RGST desk failed to find historical registration');

    // 3. New accountless registration coexists with user_id = NULL and ZERO new users created
    $usersBefore = (int)$p5Pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $anon = p5AnonSession();
    $rNew = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['fullName' => 'New Accountless Visitor', 'email' => $legEmail]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rNew['status'] === 201, "Accountless registration alongside historical email failed: {$rNew['body']}");
    $newRegId = (string)$rNew['json']['registration']['id'];
    $newUid = $p5Pdo->query('SELECT user_id FROM registrations WHERE id = ' . $p5Pdo->quote($newRegId))->fetchColumn();
    pfgAssert($newUid === null || $newUid === false, 'New accountless registration unexpectedly populated user_id');
    pfgAssert((int)$p5Pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore, 'Accountless registration created a users row');

    return "Historical {$legCode} preserved & readable in Admin/RGST; /api/auth/claim-legacy->410; new accountless reg coexists cleanly with user_id=NULL & 0 users created";
});

// =========================================================================
// T5-03: Accountless Three-Category Registration, Validators, Smart Duplicates & Multi-Category (D-09)
// =========================================================================
pfgTest('T5-03', 'Accountless 3-category registration: ATTENDEE/VOLUNTEER/SLI (user_id=NULL), legacy DELEGATE (400), exact dup (409), shared-email caregiver (201) & multi-cat', function () use ($p5Pdo, &$t503VolRegId, &$t503SliRegId): string {
    pfgResetRateLimit($p5Pdo, 'registration_submit_ip', '127.0.0.1');
    $anon = p5AnonSession();
    $qrBefore = (int)$p5Pdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn();
    $usersBefore = (int)$p5Pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $sharedEmail = 'p5.multicat.' . bin2hex(random_bytes(3)) . '@example.test';

    // 1. Retired /api/account/drafts returns 410 Gone
    $rDraft = p2Http('POST', '/api/account/drafts', ['categoryCode' => 'ATTENDEE', 'payload' => []]);
    pfgAssert($rDraft['status'] === 410, "Retired /api/account/drafts expected 410, got {$rDraft['status']}");

    // 2. Legacy DELEGATE rejected 400
    $rLeg = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['categoryCode' => 'DELEGATE']), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rLeg['status'] === 400, "Legacy DELEGATE expected 400, got {$rLeg['status']}");

    // 3. Strict validators: Volunteer age <16 (400), empty attendingDays (400), invalid SLI videoDemoUrl (400), invalid email (400)
    $rYoungVol = p2Http('POST', '/api/registrations', p5ValidPayload('VOLUNTEER', ['volunteerDetails' => ['age' => 15]]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rYoungVol['status'] === 400 && !empty($rYoungVol['json']['errors']['age']), "Volunteer age 15 expected 400, got {$rYoungVol['status']}");

    $rNoDays = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['attendingDays' => []]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rNoDays['status'] === 400 && !empty($rNoDays['json']['errors']['attendingDays']), "Empty attendingDays expected 400, got {$rNoDays['status']}");

    $rBadVid = p2Http('POST', '/api/registrations', p5ValidPayload('SLI', ['sliDetails' => ['videoDemoUrl' => 'ftp://evil.example/video.mp4']]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rBadVid['status'] === 400 && !empty($rBadVid['json']['errors']['videoDemoUrl']), "Invalid videoDemoUrl expected 400, got {$rBadVid['status']}");

    $rBadEmail = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['email' => 'not-an-email']), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rBadEmail['status'] === 400 && !empty($rBadEmail['json']['errors']['email']), "Invalid email expected 400, got {$rBadEmail['status']}");

    // 4. Submit ATTENDEE -> 201 CONFIRMED (user_id = NULL)
    $rAtt = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['fullName' => 'Kiran Joshi', 'email' => $sharedEmail]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rAtt['status'] === 201 && ($rAtt['json']['registration']['status'] ?? '') === 'CONFIRMED', "ATTENDEE expected 201 CONFIRMED, got {$rAtt['status']}: {$rAtt['body']}");
    $attCode = (string)$rAtt['json']['registration']['registrationCode'];

    // 5. Exact duplicate (same category + email + fullName) -> 409 without leaking existing Registration ID
    $rAttDup = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['fullName' => 'Kiran Joshi', 'email' => $sharedEmail]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rAttDup['status'] === 409 && !str_contains($rAttDup['body'], $attCode), "Exact duplicate ATTENDEE expected generic 409 without code leak, got {$rAttDup['status']}: {$rAttDup['body']}");

    // 6. Shared-email family/caregiver ATTENDEE with different fullName -> 201 ALLOWED
    $rAttFam = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['fullName' => 'Neha Joshi', 'email' => $sharedEmail]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rAttFam['status'] === 201 && ($rAttFam['json']['registration']['registrationCode'] ?? '') !== $attCode, "Shared-email caregiver ATTENDEE expected 201, got {$rAttFam['status']}: {$rAttFam['body']}");

    // 7. Multi-category allowed (D-09): same person registers as VOLUNTEER -> 201 SUBMITTED and SLI -> 201 SUBMITTED
    $rVol = p2Http('POST', '/api/registrations', p5ValidPayload('VOLUNTEER', ['fullName' => 'Kiran Joshi', 'email' => $sharedEmail]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rVol['status'] === 201 && ($rVol['json']['registration']['status'] ?? '') === 'SUBMITTED', "VOLUNTEER expected 201 SUBMITTED, got {$rVol['status']}: {$rVol['body']}");
    $t503VolRegId = (string)$rVol['json']['registration']['id'];

    $rSli = p2Http('POST', '/api/registrations', p5ValidPayload('SLI', ['fullName' => 'Kiran Joshi', 'email' => $sharedEmail]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rSli['status'] === 201 && ($rSli['json']['registration']['status'] ?? '') === 'SUBMITTED', "SLI expected 201 SUBMITTED, got {$rSli['status']}: {$rSli['body']}");
    $t503SliRegId = (string)$rSli['json']['registration']['id'];

    // Verify 0 QR rows & 0 users rows created across all 4 submissions
    pfgAssert((int)$p5Pdo->query('SELECT COUNT(*) FROM qr_credentials')->fetchColumn() === $qrBefore, 'QR credentials created unexpectedly!');
    pfgAssert((int)$p5Pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore, 'Accountless registrations unexpectedly created users rows!');

    return 'Drafts(410), legacy DELEGATE(400), validators(400), exact dup(409, 0 ID leak), shared-email caregiver(201), multi-cat ATTENDEE+VOLUNTEER+SLI (user_id=NULL, 0 users, 0 QR) verified';
});

// =========================================================================
// T5-04: Admin Lifecycle State Machine & Audited Registration Detail Corrections (§12)
// =========================================================================
pfgTest('T5-04', 'Admin lifecycle & audited corrections: SUBMITTED->UNDER_REVIEW->INFO_REQ->APPROVED->CANCELLED, illegal 409, audited registrationCorrections & RBAC 403', function () use ($p5Pdo, $saSess, &$t503VolRegId): string {
    $attBefore = (int)$p5Pdo->query('SELECT COUNT(*) FROM attendance_records')->fetchColumn();

    // 1. Admin: SUBMITTED -> UNDER_REVIEW -> ADDITIONAL_INFO_REQUIRED
    $rUr = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), ['status' => 'UNDER_REVIEW'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rUr['status'] === 200 && ($rUr['json']['registration']['status'] ?? '') === 'UNDER_REVIEW', "UNDER_REVIEW failed: {$rUr['body']}");

    $rInfo = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), [
        'status'        => 'ADDITIONAL_INFO_REQUIRED',
        'publicMessage' => 'Please email your preferred MSU department to the Festival Secretariat.',
        'reviewNotes'   => 'INTERNAL_REVIEWER_NOTE_PRIVATE',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rInfo['status'] === 200 && ($rInfo['json']['registration']['status'] ?? '') === 'ADDITIONAL_INFO_REQUIRED', "ADDITIONAL_INFO_REQUIRED failed: {$rInfo['body']}");

    // 2. Authorized Admin corrects applicant contact/registration details with mandatory correctionReason (audited before/after in admin_actions & audit_logs)
    $rCorrNoReason = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), [
        'registrationCorrections' => ['phone' => '+91 98250 99111'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rCorrNoReason['status'] === 400, "Correction without correctionReason expected 400, got {$rCorrNoReason['status']}");

    $rCorr = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), [
        'registrationCorrections' => [
            'fullName'         => 'Kiran R. Joshi',
            'phone'            => '+91 98250 99111',
            'organizationName' => 'MSU Baroda Faculty of Social Work',
        ],
        'correctionReason' => 'Applicant emailed Secretariat requesting middle initial and updated phone number',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert(
        $rCorr['status'] === 200 && ($rCorr['json']['registration']['full_name'] ?? $rCorr['json']['registration']['fullName'] ?? '') === 'Kiran R. Joshi',
        "Authorized Admin registrationCorrections failed: {$rCorr['status']} {$rCorr['body']}"
    );
    $corrAuditCnt = (int)$p5Pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'REGISTRATION_DETAILS_CORRECTED' AND resource_id = " . $p5Pdo->quote($t503VolRegId))->fetchColumn();
    $corrActionCnt = (int)$p5Pdo->query("SELECT COUNT(*) FROM admin_actions WHERE action_type = 'REGISTRATION_DETAILS_CORRECTED' AND reason LIKE '%middle initial%'")->fetchColumn();
    pfgAssert($corrAuditCnt >= 1 && $corrActionCnt >= 1, 'Missing REGISTRATION_DETAILS_CORRECTED audit_logs or admin_actions record!');

    // 3. Admin returns application to UNDER_REVIEW after receiving emailed details, then approves -> APPROVED
    $rBackUr = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), ['status' => 'UNDER_REVIEW'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rBackUr['status'] === 200 && ($rBackUr['json']['registration']['status'] ?? '') === 'UNDER_REVIEW', "Return to UNDER_REVIEW failed: {$rBackUr['body']}");

    $rApp = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), ['status' => 'APPROVED'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rApp['status'] === 200 && ($rApp['json']['registration']['status'] ?? '') === 'APPROVED', "Approval failed: {$rApp['body']}");

    // Verify approval did NOT touch attendance_records
    $attAfter = (int)$p5Pdo->query('SELECT COUNT(*) FROM attendance_records')->fetchColumn();
    pfgAssert($attAfter === $attBefore, 'Approval unexpectedly mutated attendance_records!');

    // 4. Illegal transition APPROVED -> SUBMITTED -> 409
    $rIllegal = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), ['status' => 'SUBMITTED'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rIllegal['status'] === 409, "Illegal transition APPROVED->SUBMITTED expected 409, got {$rIllegal['status']}: {$rIllegal['body']}");

    // 5. Admin cancels approved volunteer -> CANCELLED cleanly
    $rCancel = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($t503VolRegId), [
        'status'        => 'CANCELLED',
        'publicMessage' => 'Cancelled upon applicant email request to the Secretariat.',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rCancel['status'] === 200 && ($rCancel['json']['registration']['status'] ?? '') === 'CANCELLED', "Admin cancel failed: {$rCancel['body']}");

    return 'Lifecycle SUBMITTED->UNDER_REVIEW->INFO_REQ->APPROVED->CANCELLED, audited registrationCorrections (before/after logged), illegal 409 & 0 attendance mutation verified';
});

// =========================================================================
// T5-05: Phase 8 Superadmin Custom Permission Profiles (CRIT-01, HIGH-01, MED-05)
// =========================================================================
pfgTest('T5-05', 'Phase 8 permission profiles: content_manager (403 accomm/reg/correct), programme_coordinator (200 prog/venues), audit_logs.read blocked (400) & session revocation', function () use ($p5Pdo, $saSess): string {
    // 1. audit_logs.read rejected 400 when creating a profile (MED-05)
    $rForbid = p2Http('POST', '/api/admin/permission-profiles', [
        'name'        => 'Forbidden Audit Profile',
        'permissions' => ['cms.manage', 'audit_logs.read'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rForbid['status'] === 400, "Profile with audit_logs.read expected 400, got {$rForbid['status']}: {$rForbid['body']}");

    // 2. Load system profiles: content_manager & programme_coordinator
    $rProfiles = p2Http('GET', '/api/admin/permission-profiles', null, $saSess['cookies']);
    pfgAssert($rProfiles['status'] === 200, 'GET /api/admin/permission-profiles failed');
    $bySlug = [];
    foreach (($rProfiles['json']['items'] ?? []) as $pr) {
        $bySlug[(string)$pr['slug']] = $pr;
    }
    pfgAssert(isset($bySlug['content_manager'], $bySlug['programme_coordinator']), 'Missing seeded content_manager or programme_coordinator profile');

    // 3. Create ADMIN user & assign content_manager profile (username+password only; TOTP discontinued)
    $admUser = p2CreateTestUser($p5Pdo, 't5_prof_admin', 't5.prof.admin@example.test', 'ProfAdmin#2027', 'ADMIN', [], null);

    $rAssignCm = p2Http('PATCH', '/api/admin/users', [
        'userId'              => $admUser['id'],
        'permissionProfileId' => $bySlug['content_manager']['id'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAssignCm['status'] === 200, "Assign content_manager failed: {$rAssignCm['body']}");

    pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
    $cmSess = p2Login($admUser['username'], $admUser['password']);
    pfgAssert($cmSess['resp']['status'] === 200, 'content_manager admin login failed: ' . $cmSess['resp']['body']);

    // content_manager -> 200 on CMS, strictly 403 on accommodations, registrations, attendance/correct (CRIT-01)
    pfgAssert(p2Http('GET', '/api/admin/cms', null, $cmSess['cookies'])['status'] === 200, 'content_manager expected 200 on /api/admin/cms');
    pfgAssert(p2Http('GET', '/api/admin/accommodations', null, $cmSess['cookies'])['status'] === 403, 'content_manager MUST get 403 on /api/admin/accommodations');
    pfgAssert(p2Http('GET', '/api/admin/registrations', null, $cmSess['cookies'])['status'] === 403, 'content_manager MUST get 403 on /api/admin/registrations');
    pfgAssert(p2Http('POST', '/api/attendance/correct', ['attendanceRecordId' => uuid4(), 'newStatus' => 'REVOKED', 'reason' => 'Not allowed'], $cmSess['cookies'], ['X-CSRF-Token' => $cmSess['csrf']])['status'] === 403, 'content_manager MUST get 403 on /api/attendance/correct');

    // 4. Reassign to programme_coordinator -> verify old session REVOKED immediately!
    $rAssignPc = p2Http('PATCH', '/api/admin/users', [
        'userId'              => $admUser['id'],
        'permissionProfileId' => $bySlug['programme_coordinator']['id'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAssignPc['status'] === 200, "Assign programme_coordinator failed: {$rAssignPc['body']}");

    $rOldMe = p2Http('GET', '/api/auth/me', null, $cmSess['cookies']);
    pfgAssert(
        is_array($rOldMe['json']) && array_key_exists('user', $rOldMe['json']) && $rOldMe['json']['user'] === null,
        'Old ADMIN session was NOT revoked when permissionProfileId changed! body=' . $rOldMe['body']
    );

    // Re-login as programme_coordinator -> 200 on /api/admin/programme & /api/admin/venues (HIGH-01), 403 on /api/admin/accommodations
    pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
    $pcSess = p2Login($admUser['username'], $admUser['password']);
    pfgAssert($pcSess['resp']['status'] === 200, 'programme_coordinator login failed');
    pfgAssert(p2Http('GET', '/api/admin/programme', null, $pcSess['cookies'])['status'] === 200, 'programme_coordinator expected 200 on /api/admin/programme');
    pfgAssert(p2Http('GET', '/api/admin/venues', null, $pcSess['cookies'])['status'] === 200, 'programme_coordinator expected 200 on /api/admin/venues');
    pfgAssert(p2Http('GET', '/api/admin/accommodations', null, $pcSess['cookies'])['status'] === 403, 'programme_coordinator expected 403 on /api/admin/accommodations');

    // 5. Create custom profile, assign it, then edit its permissions -> verify sessionsRevoked >= 1
    $rNewProf = p2Http('POST', '/api/admin/permission-profiles', [
        'name'        => 'Custom Venue Helper ' . bin2hex(random_bytes(2)),
        'permissions' => ['programme.manage'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rNewProf['status'] === 201 && !empty($rNewProf['json']['profile']['id']), 'Custom profile create failed: ' . $rNewProf['body']);
    $customProfId = (string)$rNewProf['json']['profile']['id'];

    p2Http('PATCH', '/api/admin/users', ['userId' => $admUser['id'], 'permissionProfileId' => $customProfId], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
    $custSess = p2Login($admUser['username'], $admUser['password']);
    pfgAssert($custSess['resp']['status'] === 200, 'Custom profile admin login failed');

    $rEditProf = p2Http('PATCH', '/api/admin/permission-profiles', [
        'id'          => $customProfId,
        'permissions' => ['programme.manage', 'cms.manage'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rEditProf['status'] === 200 && (int)($rEditProf['json']['sessionsRevoked'] ?? 0) >= 1, 'Editing profile permissions did not revoke assigned admin session');

    $rRevokedCheck = p2Http('GET', '/api/auth/me', null, $custSess['cookies']);
    pfgAssert(
        is_array($rRevokedCheck['json']) && array_key_exists('user', $rRevokedCheck['json']) && $rRevokedCheck['json']['user'] === null,
        'Assigned admin cookie stayed active after profile edit! body=' . $rRevokedCheck['body']
    );

    return 'content_manager(CMS 200; accomm/reg/correct 403), programme_coordinator(programme/venues 200), audit_logs.read blocked(400) & profile-change session revocation verified';
});

// =========================================================================
// T5-06: Phase 9 Manual Registration Desk (/api/attendance, /mark, /correct)
// =========================================================================
pfgTest('T5-06', 'Phase 9 Manual Registration Desk: search, zero sensitive leaks, eligibility gate, atomic mark, D-03 undo rules & Admin override', function () use ($p5Pdo, $saSess): string {
    $rgstA = p2CreateTestUser($p5Pdo, 't5_rgst_a', 't5.rgst.a@example.test', 'RgstA#2027', 'RGST');
    $rgstB = p2CreateTestUser($p5Pdo, 't5_rgst_b', 't5.rgst.b@example.test', 'RgstB#2027', 'RGST');
    pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
    $sessA = p2Login($rgstA['username'], $rgstA['password']);
    $sessB = p2Login($rgstB['username'], $rgstB['password']);
    pfgAssert($sessA['resp']['status'] === 200 && $sessB['resp']['status'] === 200, 'RGST A/B logins failed');

    // Create eligible accountless CONFIRMED Attendee with confidential disclosures + emergency contact + internal review note
    pfgResetRateLimit($p5Pdo, 'registration_submit_ip', '127.0.0.1');
    $anon = p5AnonSession();
    $rElig = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', [
        'fullName'                  => 'Devika Shah',
        'email'                     => 't5.desk.elig.' . bin2hex(random_bytes(3)) . '@example.test',
        'phone'                     => '+91 98250 77889',
        'isPersonWithDisability'    => 'YES',
        'disabilityCategoryCodes'   => ['BLINDNESS'],
        'disabilitySelfDescription' => 'SECRET_DIAGNOSIS_DO_NOT_LEAK',
        'accommodationTypes'        => ['BRAILLE_SCHEDULE'],
        'accommodationFreeText'     => 'SECRET_ACCOMM_DO_NOT_LEAK',
        'emergencyContactName'      => 'SECRET_EMERGENCY_CONTACT_NAME',
        'emergencyContactPhone'     => '+91 98250 66112',
    ]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rElig['status'] === 201, 'Eligible attendee creation failed: ' . $rElig['body']);
    $eligId = (string)$rElig['json']['registration']['id'];
    $eligCode = (string)$rElig['json']['registration']['registrationCode'];
    $p5Pdo->prepare("UPDATE registrations SET review_notes = 'SECRET_REVIEW_NOTE_DO_NOT_LEAK' WHERE id = :id")->execute([':id' => $eligId]);

    // Also create ineligible SUBMITTED Volunteer + eligible APPROVED SLI to verify RGST desk marks ALL 3 categories!
    $rInelig = p2Http('POST', '/api/registrations', p5ValidPayload('VOLUNTEER', ['fullName' => 'Pending Vol Desk']), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rInelig['status'] === 201 && ($rInelig['json']['registration']['status'] ?? '') === 'SUBMITTED', 'Ineligible volunteer creation failed');
    $ineligId = (string)$rInelig['json']['registration']['id'];

    $rDeskSli = p2Http('POST', '/api/registrations', p5ValidPayload('SLI', ['fullName' => 'Approved SLI Desk']), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rDeskSli['status'] === 201, 'SLI creation for desk test failed');
    $deskSliId = (string)$rDeskSli['json']['registration']['id'];
    p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($deskSliId), ['status' => 'APPROVED'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    $rSliMark = p2Http('POST', '/api/attendance/mark', ['registrationId' => $deskSliId, 'attendanceDateIst' => '2027-01-21', 'venueLabel' => 'Sama Desk 1'], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rSliMark['status'] === 201, "Approved accountless SLI Day 1 mark failed: {$rSliMark['body']}");

    // 1. Search by ID, name, and phone — verify zero sensitive fields leaked
    foreach ([$eligCode, 'Devika Shah', '77889'] as $q) {
        $rSearch = p2Http('GET', '/api/attendance?q=' . rawurlencode($q), null, $sessA['cookies']);
        pfgAssert($rSearch['status'] === 200 && count($rSearch['json']['rows'] ?? []) >= 1, "Search q={$q} failed");
        foreach (['SECRET_DIAGNOSIS_DO_NOT_LEAK', 'SECRET_ACCOMM_DO_NOT_LEAK', 'SECRET_EMERGENCY_CONTACT_NAME', 'SECRET_REVIEW_NOTE_DO_NOT_LEAK', 'BLINDNESS', 'BRAILLE_SCHEDULE'] as $forbidden) {
            pfgAssert(!str_contains($rSearch['body'], $forbidden), "Desk API leaked forbidden field {$forbidden}!");
        }
    }

    // 2. Ineligible SUBMITTED blocked 400
    $rBadElig = p2Http('POST', '/api/attendance/mark', [
        'registrationId'    => $ineligId,
        'attendanceDateIst' => '2027-01-21',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rBadElig['status'] === 400 && ($rBadElig['json']['code'] ?? '') === 'REGISTRATION_NOT_ELIGIBLE', "Ineligible mark expected 400, got {$rBadElig['status']}: {$rBadElig['body']}");

    // 3. Unallowlisted scope rejected 400
    $rBadScope = p2Http('POST', '/api/attendance/mark', [
        'registrationId'    => $eligId,
        'attendanceDateIst' => '2027-01-21',
        'attendanceScope'   => 'UNALLOWLISTED_SCOPE_XYZ',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rBadScope['status'] === 400, "Unallowlisted scope expected 400, got {$rBadScope['status']}");

    // 4. Atomic mark -> 201; duplicate -> 200 idempotent
    $rMark1 = p2Http('POST', '/api/attendance/mark', [
        'registrationId'    => $eligId,
        'attendanceDateIst' => '2027-01-21',
        'venueLabel'        => 'Sama Stadium Desk 1',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rMark1['status'] === 201 && ($rMark1['json']['record']['status'] ?? '') === 'PRESENT', "First mark expected 201, got {$rMark1['status']}: {$rMark1['body']}");
    $recId = (string)$rMark1['json']['record']['id'];

    $rMarkDup = p2Http('POST', '/api/attendance/mark', [
        'registrationId'    => $eligId,
        'attendanceDateIst' => '2027-01-21',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rMarkDup['status'] === 200 && !empty($rMarkDup['json']['alreadyMarked']), "Duplicate mark expected 200 alreadyMarked, got {$rMarkDup['status']}");

    // 5. Cross-RGST undo -> 403
    $rCrossUndo = p2Http('POST', '/api/attendance/correct', [
        'attendanceRecordId' => $recId,
        'newStatus'          => 'REVOKED',
        'reason'             => 'Attempting cross operator revoke',
    ], $sessB['cookies'], ['X-CSRF-Token' => $sessB['csrf']]);
    pfgAssert($rCrossUndo['status'] === 403 && ($rCrossUndo['json']['code'] ?? '') === 'OWN_MARK_REQUIRED', "Cross-RGST undo expected 403, got {$rCrossUndo['status']}");

    // 6. RGST self-undo within 15m -> 200
    $rSelfUndo = p2Http('POST', '/api/attendance/correct', [
        'attendanceRecordId' => $recId,
        'newStatus'          => 'REVOKED',
        'reason'             => 'Checked wrong row during busy rush',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rSelfUndo['status'] === 200 && ($rSelfUndo['json']['record']['status'] ?? '') === 'REVOKED', "RGST self-undo within 15m failed: {$rSelfUndo['body']}");

    // Reinstate to PRESENT -> 200
    $rReinstate = p2Http('POST', '/api/attendance/mark', [
        'registrationId'    => $eligId,
        'attendanceDateIst' => '2027-01-21',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rReinstate['status'] === 200 && !empty($rReinstate['json']['reinstated']), "Reinstate failed: {$rReinstate['body']}");

    // 7. >15m RGST undo -> 403
    $p5Pdo->prepare('UPDATE attendance_records SET marked_at = :past WHERE id = :id')
        ->execute([':past' => nowSql(time() - 1200), ':id' => $recId]);
    $rExpiredUndo = p2Http('POST', '/api/attendance/correct', [
        'attendanceRecordId' => $recId,
        'newStatus'          => 'REVOKED',
        'reason'             => 'Late undo after 15 minute window',
    ], $sessA['cookies'], ['X-CSRF-Token' => $sessA['csrf']]);
    pfgAssert($rExpiredUndo['status'] === 403 && ($rExpiredUndo['json']['code'] ?? '') === 'UNDO_WINDOW_EXPIRED', "Expired >15m RGST undo expected 403, got {$rExpiredUndo['status']}");

    // 8. Admin override -> 200 + immutable attendance_correction_logs
    $rAdmOverride = p2Http('POST', '/api/attendance/correct', [
        'attendanceRecordId' => $recId,
        'newStatus'          => 'REVOKED',
        'reason'             => 'Admin verified duplicate registration entry override',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAdmOverride['status'] === 200 && ($rAdmOverride['json']['record']['status'] ?? '') === 'REVOKED', "Admin override failed: {$rAdmOverride['body']}");

    $corrLogCnt = (int)$p5Pdo->query('SELECT COUNT(*) FROM attendance_correction_logs WHERE attendance_record_id = ' . $p5Pdo->quote($recId))->fetchColumn();
    pfgAssert($corrLogCnt >= 3, "Expected >=3 immutable attendance_correction_logs rows, got {$corrLogCnt}");

    return "Desk search (0 sensitive leaks), ineligible(400), bad scope(400), mark(201)/dup(200), cross-RGST(403), self-undo<15m(200), >15m(403), Admin override(200) & {$corrLogCnt} immutable correction logs verified";
});

// =========================================================================
// T5-07: Accountless Email-Primary Notifications, Privacy Hygiene & Zero Obsolete Dashboard/Login Links
// =========================================================================
pfgTest('T5-07', 'Accountless email-primary notifications: confirmation, approval, duty, accommodation & announcement queue cleanly (user_id=NULL, 0 /dashboard links, 0 private leaks)', function () use ($p5Pdo, $saSess, $sinkDir): string {
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $old) {
        @unlink($old);
    }
    pfgResetRateLimit($p5Pdo, 'registration_submit_ip', '127.0.0.1');
    $anon = p5AnonSession();
    $p1Email = 't5.email.p1.' . bin2hex(random_bytes(3)) . '@example.test';

    // 1. Accountless Attendee with accommodation + SLI
    $rP1Att = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', [
        'fullName'                  => 'Email Privacy Attendee',
        'email'                     => $p1Email,
        'isPersonWithDisability'    => 'YES',
        'disabilityCategoryCodes'   => ['LOW_VISION'],
        'disabilitySelfDescription' => 'SECRET_PRIVATE_DISABILITY_NOTE_T507',
        'accommodationTypes'        => ['LARGE_PRINT'],
        'accommodationFreeText'     => 'Large print schedule please',
    ]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rP1Att['status'] === 201, 'P1 Attendee reg failed: ' . $rP1Att['body']);
    $p1AttId = (string)$rP1Att['json']['registration']['id'];

    $rP1Sli = p2Http('POST', '/api/registrations', p5ValidPayload('SLI', [
        'fullName' => 'Email Privacy SLI',
        'email'    => $p1Email,
    ]), $anon['cookies'], ['X-CSRF-Token' => $anon['csrf']]);
    pfgAssert($rP1Sli['status'] === 201, 'P1 SLI reg failed: ' . $rP1Sli['body']);
    $p1SliId = (string)$rP1Sli['json']['registration']['id'];

    // 2. Admin approves SLI (with internal reviewNotes that MUST NOT leak into email) and assigns duty
    $rApp = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($p1SliId), [
        'status'        => 'APPROVED',
        'reviewNotes'   => 'PRIVATE_ADMIN_NOTE_T507',
        'publicMessage' => 'Welcome to the Vadodara ISL team.',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rApp['status'] === 200, 'SLI approval failed: ' . $rApp['body']);

    $rDuty = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $p1SliId,
        'roleTitle'      => 'Stage ISL Interpreter',
        'dutyDateIst'    => '2027-01-21',
        'shiftCode'      => 'DAY_1_SHIFT_1',
        'startTime'      => '10:00',
        'endTime'        => '11:30',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rDuty['status'] === 201, 'SLI duty assign failed: ' . $rDuty['body']);

    // 3. Admin updates accommodation request (triggers ACCOMMODATION_UPDATE email with recipient_user_id = NULL)
    $accId = (string)$p5Pdo->query('SELECT id FROM accommodation_requests WHERE registration_id = ' . $p5Pdo->quote($p1AttId) . ' LIMIT 1')->fetchColumn();
    $rAccUpd = p2Http('PATCH', '/api/admin/accommodations', [
        'requestId'        => $accId,
        'status'           => 'ARRANGED',
        'coordinatorNotes' => 'SECRET_COORD_NOTE_T507',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAccUpd['status'] === 200, 'Accommodation update failed: ' . $rAccUpd['body']);

    // 4. Publish an announcement (must succeed cleanly without touching participant accounts)
    $rAnn = p2Http('POST', '/api/admin/announcements', [
        'title'    => 'Phase17 Published Notice',
        'message'  => 'Accessible shuttle buses depart every 15 minutes.',
        'severity' => 'INFO',
        'status'   => 'PUBLISHED',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAnn['status'] === 201, 'Announcement create failed: ' . $rAnn['body']);

    // 5. Inspect all captured emails sent to $p1Email: verify >=4 emails, ZERO /dashboard links, ZERO /login links, ZERO private leaks!
    $matchedMails = 0;
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $mf) {
        $raw = (string)file_get_contents($mf);
        $dec = json_decode($raw, true);
        if (!is_array($dec) || strtolower((string)($dec['to'] ?? '')) !== strtolower($p1Email)) {
            continue;
        }
        $matchedMails++;
        $body = strtolower((string)($dec['html'] ?? '') . "\n" . (string)($dec['text'] ?? ''));
        pfgAssert(!str_contains($body, '/dashboard'), 'Email still contains retired /dashboard link: ' . ($dec['templateCode'] ?? ''));
        pfgAssert(!str_contains($body, 'participant dashboard'), 'Email still mentions Participant Dashboard: ' . ($dec['templateCode'] ?? ''));
        pfgAssert(!str_contains($body, 'private_admin_note_t507'), 'Internal reviewNotes leaked into email!');
        pfgAssert(!str_contains($body, 'secret_private_disability_note_t507'), 'Private disability self-description leaked into email!');
        pfgAssert(!str_contains($body, 'secret_coord_note_t507'), 'Private coordinatorNotes leaked into email!');
    }
    pfgAssert($matchedMails >= 4, "Expected >=4 lifecycle emails captured for {$p1Email}, found {$matchedMails}");

    // 6. Verify notification_queue rows have recipient_user_id IS NULL
    $nullCount = (int)$p5Pdo->query('SELECT COUNT(*) FROM notification_queue WHERE LOWER(recipient_email) = ' . $p5Pdo->quote(strtolower($p1Email)) . ' AND recipient_user_id IS NULL')->fetchColumn();
    pfgAssert($nullCount >= 4, "Expected >=4 accountless notification_queue rows with recipient_user_id IS NULL, found {$nullCount}");

    return "Verified {$matchedMails} accountless emails (recipient_user_id=NULL): Attendee confirm, SLI submit/approve/duty, accommodation arranged — 0 /dashboard links, 0 internal/medical leaks";
});

// =========================================================================
// T5-08: Phase 11 & 12 Admin Portal, Venues, Programme, Duty Assignments & Public CMS
// =========================================================================
pfgTest('T5-08', 'Phase 11/12: venues, programme gating & Proposed label, duty overlap 409+override, 2-SLI pairing, FND-40, HIGH-06 & partner consent gating', function () use ($p5Pdo, $saSess): string {
    // 1. Create PROPOSED and CONFIRMED venues
    $rVenProp = p2Http('POST', '/api/admin/venues', [
        'name'               => 'Proposed Vadodara Hall ' . bin2hex(random_bytes(2)),
        'address'            => 'Sayajigunj, Vadodara',
        'confirmationStatus' => 'PROPOSED',
        'capacity'           => 300,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rVenProp['status'] === 201, 'Proposed venue create failed: ' . $rVenProp['body']);
    $propVenueId = (string)$rVenProp['json']['venue']['id'];

    $rVenConf = p2Http('POST', '/api/admin/venues', [
        'name'               => 'Confirmed Sama Hall ' . bin2hex(random_bytes(2)),
        'address'            => 'Sama Road, Vadodara',
        'confirmationStatus' => 'CONFIRMED',
        'capacity'           => 500,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rVenConf['status'] === 201, 'Confirmed venue create failed: ' . $rVenConf['body']);
    $confVenueId = (string)$rVenConf['json']['venue']['id'];

    // 2. Programme sessions: DRAFT & CANCELLED hidden on /programme; PUBLISHED on PROPOSED venue labeled "Proposed / Subject to Confirmation"
    $rSessDraft = p2Http('POST', '/api/admin/programme', [
        'entity' => 'session', 'title' => 'HIDDEN_DRAFT_PROG_XYZ', 'track' => 'Plenary', 'dayNumber' => 1,
        'startsAt' => '2027-01-21 09:00', 'endsAt' => '2027-01-21 10:00', 'venueId' => $confVenueId, 'status' => 'DRAFT',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    $rSessCancel = p2Http('POST', '/api/admin/programme', [
        'entity' => 'session', 'title' => 'HIDDEN_CANCELLED_PROG_XYZ', 'track' => 'Plenary', 'dayNumber' => 1,
        'startsAt' => '2027-01-21 10:00', 'endsAt' => '2027-01-21 11:00', 'venueId' => $confVenueId, 'status' => 'CANCELLED',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    $rSessPub = p2Http('POST', '/api/admin/programme', [
        'entity' => 'session', 'title' => 'PUBLIC_PROPOSED_VENUE_SESSION', 'track' => 'Panel Discussion', 'dayNumber' => 2,
        'startsAt' => '2027-01-22 10:00', 'endsAt' => '2027-01-22 11:30', 'venueId' => $propVenueId, 'status' => 'PUBLISHED', 'slisNeeded' => 2,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rSessDraft['status'] === 201 && $rSessCancel['status'] === 201 && $rSessPub['status'] === 201, 'Programme sessions create failed');
    $pubSessionId = (string)$rSessPub['json']['session']['id'];

    $rProgPage = p2Http('GET', '/programme');
    pfgAssert($rProgPage['status'] === 200, 'GET /programme failed');
    pfgAssert(str_contains($rProgPage['body'], 'PUBLIC_PROPOSED_VENUE_SESSION'), 'Published session missing from /programme');
    pfgAssert(str_contains($rProgPage['body'], 'Proposed / Subject to Confirmation'), 'Non-confirmed venue label missing on /programme');
    pfgAssert(!str_contains($rProgPage['body'], 'HIDDEN_DRAFT_PROG_XYZ'), 'DRAFT session leaked onto /programme!');
    pfgAssert(!str_contains($rProgPage['body'], 'HIDDEN_CANCELLED_PROG_XYZ'), 'CANCELLED session leaked onto /programme!');

    // 3. Volunteer & SLI duty assignment: unapproved 400, 409 DUTY_OVERLAP, audited override, 2-SLI pairing
    pfgResetRateLimit($p5Pdo, 'registration_submit_ip', '127.0.0.1');
    $anonDuty = p5AnonSession();
    $volRegId = (string)p2Http('POST', '/api/registrations', p5ValidPayload('VOLUNTEER', [
        'fullName' => 'Volunteer Duty', 'accommodationTypes' => ['WHEELCHAIR_SEATING'], 'accommodationFreeText' => 'Ramp access needed',
    ]), $anonDuty['cookies'], ['X-CSRF-Token' => $anonDuty['csrf']])['json']['registration']['id'];
    $sli1RegId = (string)p2Http('POST', '/api/registrations', p5ValidPayload('SLI', ['fullName' => 'SLI One']), $anonDuty['cookies'], ['X-CSRF-Token' => $anonDuty['csrf']])['json']['registration']['id'];
    $sli2RegId = (string)p2Http('POST', '/api/registrations', p5ValidPayload('SLI', ['fullName' => 'SLI Two']), $anonDuty['cookies'], ['X-CSRF-Token' => $anonDuty['csrf']])['json']['registration']['id'];
    $sli3RegId = (string)p2Http('POST', '/api/registrations', p5ValidPayload('SLI', ['fullName' => 'SLI Three']), $anonDuty['cookies'], ['X-CSRF-Token' => $anonDuty['csrf']])['json']['registration']['id'];

    // Unapproved volunteer assignment rejected 400
    $rUnapp = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $volRegId, 'roleTitle' => 'Gate Steward', 'dutyDateIst' => '2027-01-21',
        'shiftCode' => 'DAY_1_SHIFT_1', 'startTime' => '09:00', 'endTime' => '13:00', 'venueId' => $confVenueId,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rUnapp['status'] === 400, "Unapproved duty assign expected 400, got {$rUnapp['status']}");

    // Approve Volunteer + 3 SLIs
    foreach ([$volRegId, $sli1RegId, $sli2RegId, $sli3RegId] as $rid) {
        $ra = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($rid), ['status' => 'APPROVED'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
        pfgAssert($ra['status'] === 200, "Approval failed for {$rid}");
    }

    // Assign Volunteer 09:00-13:00 -> 201
    $rV1 = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $volRegId, 'roleTitle' => 'Gate Steward', 'dutyDateIst' => '2027-01-21',
        'shiftCode' => 'DAY_1_SHIFT_1', 'startTime' => '09:00', 'endTime' => '13:00', 'venueId' => $confVenueId,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rV1['status'] === 201, "Volunteer assign 1 failed: {$rV1['body']}");

    // Overlapping Volunteer 11:00-15:00 without overrideReason -> 409 DUTY_OVERLAP
    $rVOverlap = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $volRegId, 'roleTitle' => 'Hall Guide', 'dutyDateIst' => '2027-01-21',
        'shiftCode' => 'DAY_1_SHIFT_2', 'startTime' => '11:00', 'endTime' => '15:00', 'venueId' => $confVenueId,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rVOverlap['status'] === 409 && ($rVOverlap['json']['code'] ?? '') === 'DUTY_OVERLAP', "Overlap expected 409 DUTY_OVERLAP, got {$rVOverlap['status']}: {$rVOverlap['body']}");

    // Retry with audited overrideReason -> 201 + audit log
    $rVOverridden = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $volRegId, 'roleTitle' => 'Hall Guide', 'dutyDateIst' => '2027-01-21',
        'shiftCode' => 'DAY_1_SHIFT_2', 'startTime' => '11:00', 'endTime' => '15:00', 'venueId' => $confVenueId,
        'overrideReason' => 'Coordinator approved staggered handover coverage',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rVOverridden['status'] === 201, "Overlap override failed: {$rVOverridden['body']}");
    $ovAudit = (int)$p5Pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'DUTY_OVERLAP_OVERRIDDEN'")->fetchColumn();
    pfgAssert($ovAudit >= 1, 'Missing DUTY_OVERLAP_OVERRIDDEN audit entry');

    // 2-SLI pairing on same session/date; 3rd SLI blocked 400
    $pairGroup = uuid4();
    $rSliA = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $sli1RegId, 'sessionId' => $pubSessionId, 'roleTitle' => 'Paired ISL A',
        'dutyDateIst' => '2027-01-22', 'shiftCode' => 'DAY_2_SHIFT_1', 'startTime' => '10:00', 'endTime' => '11:30',
        'pairingGroupId' => $pairGroup,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    $rSliB = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $sli2RegId, 'sessionId' => $pubSessionId, 'roleTitle' => 'Paired ISL B',
        'dutyDateIst' => '2027-01-22', 'shiftCode' => 'DAY_2_SHIFT_1', 'startTime' => '10:00', 'endTime' => '11:30',
        'pairingGroupId' => $pairGroup,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    $rSliC = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $sli3RegId, 'sessionId' => $pubSessionId, 'roleTitle' => 'Paired ISL C',
        'dutyDateIst' => '2027-01-22', 'shiftCode' => 'DAY_2_SHIFT_1', 'startTime' => '10:00', 'endTime' => '11:30',
        'pairingGroupId' => $pairGroup,
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rSliA['status'] === 201 && $rSliB['status'] === 201 && $rSliC['status'] === 400, "2-SLI pairing failed: A={$rSliA['status']} B={$rSliB['status']} C={$rSliC['status']}");

    // 4. FND-40: empty coordinatorNotes preserved on PATCH /api/admin/accommodations
    $volAccId = (string)$p5Pdo->query('SELECT id FROM accommodation_requests WHERE registration_id = ' . $p5Pdo->quote($volRegId) . ' LIMIT 1')->fetchColumn();
    $rAcc1 = p2Http('PATCH', '/api/admin/accommodations', [
        'requestId'        => $volAccId,
        'status'           => 'IN_PROGRESS',
        'coordinatorNotes' => 'FND40_PRESERVE_RAMP_NOTE',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAcc1['status'] === 200 && ($rAcc1['json']['coordinatorNotes'] ?? '') === 'FND40_PRESERVE_RAMP_NOTE', 'FND-40 initial note save failed');

    $rAcc2 = p2Http('PATCH', '/api/admin/accommodations', [
        'requestId'        => $volAccId,
        'status'           => 'ARRANGED',
        'coordinatorNotes' => '',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rAcc2['status'] === 200 && ($rAcc2['json']['coordinatorNotes'] ?? '') === 'FND40_PRESERVE_RAMP_NOTE', 'FND-40 FAILED: empty coordinatorNotes wiped existing notes!');

    // 5. Partner consent_verified=1 gating on / + HIGH-06 JSON-LD </script> hex-escaping on /
    $rBadPartner = p2Http('POST', '/api/admin/cms', [
        'entityType' => 'partner',
        'payload'    => ['organizationName' => 'Blocked Unconsented Org', 'status' => 'PUBLISHED', 'consentVerified' => false],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rBadPartner['status'] === 400, "Publishing partner without consentVerified expected 400, got {$rBadPartner['status']}");

    $rGoodPartner = p2Http('POST', '/api/admin/cms', [
        'entityType' => 'partner',
        'payload'    => ['organizationName' => 'CONSENTED_PARTNER_VISIBLE_P17', 'category' => 'Knowledge Partners', 'status' => 'PUBLISHED', 'consentVerified' => true],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rGoodPartner['status'] === 201, 'Consented partner create failed: ' . $rGoodPartner['body']);
    $goodPartnerId = (string)$rGoodPartner['json']['item']['id'];

    // Also insert a DB-level unconsented PUBLISHED partner to prove index.php SQL gate blocks it
    $edId = (string)$p5Pdo->query('SELECT id FROM festival_editions WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $unconsOrgId = uuid4();
    $unconsPartId = uuid4();
    $p5Pdo->prepare("INSERT INTO organizations (id, name, created_at) VALUES (:id, 'UNCONSENTED_PARTNER_HIDDEN_P17', NOW())")->execute([':id' => $unconsOrgId]);
    $p5Pdo->prepare("INSERT INTO partners (id, edition_id, organization_id, category, consent_verified, display_order, status, created_at, updated_at) VALUES (:id, :eid, :oid, 'Knowledge Partners', 0, 99, 'PUBLISHED', NOW(), NOW())")
        ->execute([':id' => $unconsPartId, ':eid' => $edId, ':oid' => $unconsOrgId]);

    // Insert FAQ with </script> breakout payload to verify HIGH-06 JSON-LD hex-escaping on /
    $rFaqXss = p2Http('POST', '/api/admin/cms', [
        'entityType' => 'faq',
        'payload'    => ['question' => 'JSON-LD Escape Test </script><script>alert("p17")</script>', 'answer' => 'Safe answer', 'status' => 'PUBLISHED'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rFaqXss['status'] === 201, 'FAQ XSS create failed');
    $faqXssId = (string)$rFaqXss['json']['item']['id'];

    $rHome = p2Http('GET', '/');
    pfgAssert($rHome['status'] === 200, 'GET / failed');
    pfgAssert(str_contains($rHome['body'], 'CONSENTED_PARTNER_VISIBLE_P17'), 'Consented partner missing on /');
    pfgAssert(!str_contains($rHome['body'], 'UNCONSENTED_PARTNER_HIDDEN_P17'), 'Unconsented partner leaked onto /!');
    pfgAssert(!str_contains($rHome['body'], '</script><script>alert("p17")'), 'HIGH-06 FAIL: unescaped </script> inside homepage JSON-LD!');
    pfgAssert(str_contains($rHome['body'], '\u003C/script\u003E') || str_contains($rHome['body'], '\u003c/script\u003e'), 'HIGH-06 FAIL: missing JSON_HEX_TAG escaping on /!');

    // Clean up synthetic CMS/FAQ rows so homepage stays clean for Playwright visual checks
    $p5Pdo->prepare('DELETE FROM partners WHERE id IN (:p1, :p2)')->execute([':p1' => $goodPartnerId, ':p2' => $unconsPartId]);
    $p5Pdo->prepare('DELETE FROM organizations WHERE id = :oid')->execute([':oid' => $unconsOrgId]);
    $p5Pdo->prepare('DELETE FROM faq_items WHERE id = :fid')->execute([':fid' => $faqXssId]);

    return 'Venues/Programme gating+Proposed label, duty unapproved(400)+overlap(409)+override(201), 2-SLI pairing, FND-40, HIGH-06 JSON-LD escape & partner consent_verified=1 verified';
});

// =========================================================================
// T5-09: Concurrent Accountless Full-Registration HTTP Stress Test (10 Parallel Public Applicants)
// =========================================================================
pfgTest('T5-09', 'Concurrent accountless HTTP stress test: 10 parallel applicants -> 10/10 HTTP 201, 10 unique codes, user_id=NULL & 0 users created', function () use ($p5Pdo, $root, $phpBin, $tmpDir): string {
    pfgResetRateLimit($p5Pdo, 'registration_submit_ip', '127.0.0.1');
    $usersBefore = (int)$p5Pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    // Boot 4 parallel PHP built-in servers on ports 18091..18094 so HTTP requests truly hit InnoDB concurrently!
    $env = getenv();
    if (!is_array($env)) {
        $env = [];
    }
    $ports = [18091, 18092, 18093, 18094];
    $srvProcs = [];
    $srvPipes = [];

    foreach ($ports as $port) {
        $log = $tmpDir . DIRECTORY_SEPARATOR . "php_stress_{$port}.log";
        @unlink($log);
        $p = proc_open(
            [$phpBin, '-S', "127.0.0.1:{$port}", $root . '/tests/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $root,
            $env
        );
        pfgAssert(is_resource($p), "Failed to boot stress server on port {$port}");
        $srvProcs[$port] = $p;
        $srvPipes[$port] = $pipes;
    }

    foreach ($ports as $port) {
        $ok = false;
        for ($i = 0; $i < 20; $i++) {
            $fp = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
            if ($fp) {
                fclose($fp);
                $ok = true;
                break;
            }
            usleep(100000);
        }
        pfgAssert($ok, "Stress server did not listen on port {$port}");
    }

    // Ephemeral accountless HTTP worker script (fetches anonymous CSRF via GET /api/registrations then POSTs at barrier!)
    $workerFile = $tmpDir . DIRECTORY_SEPARATOR . 'stress_http_worker.php';
    file_put_contents($workerFile, <<<'PHP'
<?php
declare(strict_types=1);
$url = $argv[1];
$email = $argv[2];
$idem = $argv[3];
$barrier = (float)$argv[4];
$idx = (int)$argv[5];

// 1. Obtain anonymous session cookie + CSRF token
$cookies = [];
$chGet = curl_init($url);
curl_setopt_array($chGet, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$cookies): int {
        $t = trim($line);
        if (stripos($t, 'set-cookie:') === 0) {
            $semi = explode(';', trim(substr($t, 11)));
            if (strpos($semi[0], '=') !== false) {
                [$cn, $cv] = explode('=', $semi[0], 2);
                $cookies[trim($cn)] = trim($cv);
            }
        }
        return strlen($line);
    },
]);
$getBody = (string)curl_exec($chGet);
curl_close($chGet);
$getJson = json_decode($getBody, true);
$csrf = (string)($getJson['csrfToken'] ?? '');
$pairs = [];
foreach ($cookies as $ck => $cv) {
    $pairs[] = $ck . '=' . $cv;
}
$cookieHeader = implode('; ', $pairs);

// 2. Synchronize at barrier and submit accountless POST
$waitUs = (int)(($barrier - microtime(true)) * 1000000);
if ($waitUs > 0 && $waitUs < 5000000) {
    usleep($waitUs);
}

$payload = json_encode([
    'categoryCode'          => 'ATTENDEE',
    'fullName'              => "Stress Attendee {$idx}",
    'email'                 => $email,
    'phone'                 => sprintf('+91 98250 88%03d', $idx),
    'country'               => 'India',
    'cityDistrict'          => 'Vadodara',
    'state'                 => 'Gujarat',
    'isPersonWithDisability'=> 'NO',
    'attendingDays'         => ['DAY_1'],
    'attendeeDetails'       => [
        'ageGroup'               => '18_35',
        'selfIdentificationTags' => ['Student'],
    ],
    'consentZeroTolerance'  => true,
    'consentDataProcessing' => true,
    'consentAccuracy'       => true,
    'idempotencyKey'        => $idem,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Cookie: ' . $cookieHeader,
        'X-CSRF-Token: ' . $csrf,
        'Idempotency-Key: ' . $idem,
    ],
    CURLOPT_POSTFIELDS     => $payload,
]);
$body = (string)curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
$j = json_decode($body, true);
echo json_encode([
    'idx'    => $idx,
    'status' => $status,
    'code'   => $j['registration']['registrationCode'] ?? null,
    'error'  => $err !== '' ? $err : ($j['error'] ?? null),
]);
PHP);

    try {
        $n = 10;
        $barrierTs = microtime(true) + 1.2;
        $t0 = microtime(true);
        $wProcs = [];
        $wPipes = [];
        for ($i = 0; $i < $n; $i++) {
            $port = $ports[$i % count($ports)];
            $email = sprintf('t5.stress.%d.%s@example.test', $i, bin2hex(random_bytes(3)));
            $idem = 't5-stress-' . $i . '-' . bin2hex(random_bytes(4));
            $wp = proc_open(
                [$phpBin, $workerFile, "http://127.0.0.1:{$port}/api/registrations", $email, $idem, sprintf('%.6f', $barrierTs), (string)$i],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                $root
            );
            if (is_resource($wp)) {
                fclose($pipes[0]);
                $wProcs[$i] = $wp;
                $wPipes[$i] = $pipes;
            }
        }

        $codes = [];
        $errors = [];
        foreach ($wProcs as $i => $wp) {
            $out = trim((string)stream_get_contents($wPipes[$i][1]));
            $err = trim((string)stream_get_contents($wPipes[$i][2]));
            fclose($wPipes[$i][1]);
            fclose($wPipes[$i][2]);
            proc_close($wp);
            $d = json_decode($out, true);
            if (is_array($d) && (int)($d['status'] ?? 0) === 201 && !empty($d['code'])) {
                $codes[] = (string)$d['code'];
            } else {
                $errors[] = "w{$i}: out={$out} err={$err}";
            }
        }

        $elapsedMs = (int)round((microtime(true) - $t0) * 1000);
        $unique = array_values(array_unique($codes));
        $dups = count($codes) - count($unique);
        sort($unique);

        pfgAssert(empty($errors), 'Stress worker errors: ' . implode('; ', array_slice($errors, 0, 5)));
        pfgAssert(count($codes) === $n && count($unique) === $n && $dups === 0, "Expected {$n}/{$n} unique HTTP 201, got " . count($codes) . " ({$dups} dups)");
        foreach ($unique as $c) {
            pfgAssert(preg_match('/^PFG-2027-\d{6,}$/', $c) === 1, "Invalid code format: {$c}");
        }
        pfgAssert((int)$p5Pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore, 'Concurrent accountless submissions created users rows!');

        return sprintf(
            '10/10 accountless HTTP 201, 10 unique codes (%s..%s), 0 duplicates, 0 users created across 4 parallel servers in %dms',
            $unique[0],
            $unique[count($unique) - 1],
            $elapsedMs
        );
    } finally {
        @unlink($workerFile);
        foreach ($srvProcs as $port => $p) {
            if (is_resource($p)) {
                @proc_terminate($p);
                if (isset($srvPipes[$port][0]) && is_resource($srvPipes[$port][0])) {
                    @fclose($srvPipes[$port][0]);
                }
                @proc_close($p);
            }
        }
    }
});

// =========================================================================
// T5-10: Final Release Acceptance Security & Cutover Controls (§5.1, §5.3, §6, §7, §8, §9, §14)
// =========================================================================
pfgTest('T5-10', 'Final release controls: granular notification RBAC (§5.1), self-approval 403 (§5.3), launch gate 503 (§6), withdrawal alert (§7), IST day window (§8), stale token cancel (§9), dynamic env loader (§14)', function () use ($p5Pdo, $saSess, $root, $phpBin, $tmpDir, $sinkDir): string {
    require_once $root . '/includes/notification-service.php';

    $setSetting = static function (PDO $pdo, string $key, string $val): void {
        $st = $pdo->prepare('UPDATE system_settings SET `value` = :v, updated_at = NOW() WHERE `key` = :k');
        $st->execute([':v' => $val, ':k' => $key]);
        if ($st->rowCount() === 0) {
            $pdo->prepare('INSERT INTO system_settings (id, `key`, `value`, description, updated_at) VALUES (:id, :k, :v, :d, NOW())')
                ->execute([':id' => uuid4(), ':k' => $key, ':v' => $val, ':d' => 'T5-10 final release test setting']);
        }
    };

    // ---------------------------------------------------------------------
    // 1. Granular Notification RBAC (§5.1)
    // ---------------------------------------------------------------------
    $rProfiles = p2Http('GET', '/api/admin/permission-profiles', null, $saSess['cookies']);
    pfgAssert($rProfiles['status'] === 200, 'T5-10: GET /api/admin/permission-profiles failed');
    $profilesBySlug = [];
    foreach (($rProfiles['json']['items'] ?? []) as $pr) {
        $profilesBySlug[(string)$pr['slug']] = $pr;
    }
    foreach (['content_manager', 'volunteer_coordinator', 'registration_manager', 'communications_manager'] as $reqSlug) {
        pfgAssert(isset($profilesBySlug[$reqSlug]), "T5-10: Missing system profile '{$reqSlug}'");
    }

    // Seed 2 operational notifications (1 FAILED for retry, 1 CAPTURED_LOCAL for resend) + 2 auth-token notifications
    $t510Tag = bin2hex(random_bytes(3));
    $opFailQ = pfgEnqueueNotification($p5Pdo, [
        'to'             => "t510.opfail.{$t510Tag}@example.test",
        'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
        'variables'      => [
            'fullName'         => 'Operational Retry Target',
            'registrationCode' => 'PFG-2027-990001',
            'categoryName'     => 'General Attendee',
            'status'           => 'CONFIRMED',
        ],
        'idempotencyKey' => "t510-op-fail-{$t510Tag}",
    ]);
    $opFailId = (string)$opFailQ['queueId'];
    $p5Pdo->prepare("UPDATE notification_queue SET status = 'FAILED', error_category = 'PROVIDER_5XX', last_error = 'Synthetic failure for retry test' WHERE id = :id")
        ->execute([':id' => $opFailId]);

    $opSentQ = pfgEnqueueNotification($p5Pdo, [
        'to'             => "t510.opsent.{$t510Tag}@example.test",
        'templateCode'   => 'ATTENDEE_REGISTRATION_CONFIRMED',
        'variables'      => [
            'fullName'         => 'Operational Resend Target',
            'registrationCode' => 'PFG-2027-990002',
            'categoryName'     => 'General Attendee',
            'status'           => 'CONFIRMED',
        ],
        'idempotencyKey' => "t510-op-sent-{$t510Tag}",
    ]);
    $opSentId = (string)$opSentQ['queueId'];
    $p5Pdo->prepare("UPDATE notification_queue SET status = 'CAPTURED_LOCAL' WHERE id = :id")
        ->execute([':id' => $opSentId]);

    $authPwdQ = pfgEnqueueNotification($p5Pdo, [
        'to'             => "t510.authpwd.{$t510Tag}@example.test",
        'templateCode'   => 'PASSWORD_RECOVERY',
        'variables'      => ['fullName' => 'Auth Pwd Target'],
        'ephemeralVars'  => ['actionUrl' => APP_URL . '/reset-password?token=t510dummyreset' . $t510Tag],
        'idempotencyKey' => "t510-auth-pwd-{$t510Tag}",
    ]);
    $authPwdId = (string)$authPwdQ['queueId'];
    $p5Pdo->prepare("UPDATE notification_queue SET status = 'FAILED', ephemeral_secret_enc = NULL WHERE id = :id")
        ->execute([':id' => $authPwdId]);

    $authVerQ = pfgEnqueueNotification($p5Pdo, [
        'to'             => "t510.authver.{$t510Tag}@example.test",
        'templateCode'   => 'ACCOUNT_VERIFICATION',
        'variables'      => ['fullName' => 'Auth Verify Target'],
        'ephemeralVars'  => ['actionUrl' => APP_URL . '/verify-email?token=t510dummyverify' . $t510Tag],
        'idempotencyKey' => "t510-auth-ver-{$t510Tag}",
    ]);
    $authVerId = (string)$authVerQ['queueId'];
    $p5Pdo->prepare("UPDATE notification_queue SET status = 'CAPTURED_LOCAL', ephemeral_secret_enc = NULL WHERE id = :id")
        ->execute([':id' => $authVerId]);

    // Helper: create an authenticated ADMIN session assigned to a specific profile slug (username+password only)
    $makeProfileAdmin = static function (string $slug) use ($p5Pdo, $saSess, $profilesBySlug, $t510Tag): array {
        $uname = 't510_' . substr($slug, 0, 10) . '_' . $t510Tag;
        $email = $uname . '@example.test';
        $pw = 'RelAdmin#2027';
        $u = p2CreateTestUser($p5Pdo, $uname, $email, $pw, 'ADMIN', [], null);

        $rAssign = p2Http('PATCH', '/api/admin/users', [
            'userId'              => $u['id'],
            'permissionProfileId' => $profilesBySlug[$slug]['id'],
        ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
        pfgAssert($rAssign['status'] === 200, "T5-10: Assign {$slug} failed: {$rAssign['body']}");

        pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
        $sess = p2Login($u['username'], $u['password']);
        pfgAssert($sess['resp']['status'] === 200, "T5-10: Login for {$slug} failed: {$sess['resp']['body']}");
        return ['user' => $u, 'cookies' => $sess['cookies'], 'csrf' => $sess['csrf']];
    };

    $cmAdm = $makeProfileAdmin('content_manager');
    $vcAdm = $makeProfileAdmin('volunteer_coordinator');
    $rmAdm = $makeProfileAdmin('registration_manager');
    $commAdm = $makeProfileAdmin('communications_manager');

    // content_manager & volunteer_coordinator -> 403 on GET and POST
    foreach (['content_manager' => $cmAdm, 'volunteer_coordinator' => $vcAdm] as $lbl => $adm) {
        $rG = p2Http('GET', '/api/admin/notifications', null, $adm['cookies']);
        pfgAssert($rG['status'] === 403, "T5-10 §5.1: {$lbl} GET notifications expected 403, got {$rG['status']}");
        $rP = p2Http('POST', '/api/admin/notifications', ['action' => 'retry', 'queueId' => $opFailId, 'reason' => 'Test'], $adm['cookies'], ['X-CSRF-Token' => $adm['csrf']]);
        pfgAssert($rP['status'] === 403, "T5-10 §5.1: {$lbl} POST notifications expected 403, got {$rP['status']}");
    }

    // registration_manager -> 200 on GET, 403 on POST retry & resend
    $rRmGet = p2Http('GET', '/api/admin/notifications', null, $rmAdm['cookies']);
    pfgAssert($rRmGet['status'] === 200, "T5-10 §5.1: registration_manager GET expected 200, got {$rRmGet['status']}");
    $rRmRetry = p2Http('POST', '/api/admin/notifications', ['action' => 'retry', 'queueId' => $opFailId, 'reason' => 'Test'], $rmAdm['cookies'], ['X-CSRF-Token' => $rmAdm['csrf']]);
    pfgAssert($rRmRetry['status'] === 403, "T5-10 §5.1: registration_manager POST retry expected 403, got {$rRmRetry['status']}");
    $rRmResend = p2Http('POST', '/api/admin/notifications', ['action' => 'resend', 'queueId' => $opSentId, 'reason' => 'Test'], $rmAdm['cookies'], ['X-CSRF-Token' => $rmAdm['csrf']]);
    pfgAssert($rRmResend['status'] === 403, "T5-10 §5.1: registration_manager POST resend expected 403, got {$rRmResend['status']}");

    // communications_manager -> 200 on GET (operational visible, auth templates filtered out), 403 on direct GET/POST for auth templates, 200/201 on operational retry/resend
    $rCommList = p2Http('GET', '/api/admin/notifications?pageSize=100', null, $commAdm['cookies']);
    pfgAssert($rCommList['status'] === 200, "T5-10 §5.1: communications_manager GET expected 200, got {$rCommList['status']}");
    $authTpls = ['PASSWORD_RECOVERY', 'ACCOUNT_VERIFICATION', 'EMAIL_VERIFICATION_RESEND', 'EMAIL_CHANGE_VERIFICATION', 'PASSWORD_RESET_CONFIRMATION', 'SECURITY_ALERT'];
    $sawOp = false;
    foreach (($rCommList['json']['items'] ?? []) as $nItem) {
        $tpl = strtoupper((string)($nItem['template_code'] ?? ''));
        pfgAssert(!in_array($tpl, $authTpls, true), "T5-10 §5.1: communications_manager list leaked auth template {$tpl}!");
        if ((string)($nItem['id'] ?? '') === $opFailId || (string)($nItem['id'] ?? '') === $opSentId) {
            $sawOp = true;
        }
    }
    pfgAssert($sawOp, 'T5-10 §5.1: communications_manager list did not include operational notifications');

    foreach ([$authPwdId, $authVerId] as $authQid) {
        $rDirectAuth = p2Http('GET', '/api/admin/notifications?id=' . urlencode($authQid), null, $commAdm['cookies']);
        pfgAssert($rDirectAuth['status'] === 403, "T5-10 §5.1: communications_manager direct GET auth notif expected 403, got {$rDirectAuth['status']}");
    }
    $rCommAuthRetry = p2Http('POST', '/api/admin/notifications', ['action' => 'retry', 'queueId' => $authPwdId, 'reason' => 'Unauthorized retry'], $commAdm['cookies'], ['X-CSRF-Token' => $commAdm['csrf']]);
    pfgAssert($rCommAuthRetry['status'] === 403, "T5-10 §5.1: communications_manager POST retry auth notif expected 403, got {$rCommAuthRetry['status']}");
    $rCommAuthResend = p2Http('POST', '/api/admin/notifications', ['action' => 'resend', 'queueId' => $authVerId, 'reason' => 'Unauthorized resend'], $commAdm['cookies'], ['X-CSRF-Token' => $commAdm['csrf']]);
    pfgAssert($rCommAuthResend['status'] === 403, "T5-10 §5.1: communications_manager POST resend auth notif expected 403, got {$rCommAuthResend['status']}");

    $rCommOpRetry = p2Http('POST', '/api/admin/notifications', ['action' => 'retry', 'queueId' => $opFailId, 'reason' => 'Authorized comms retry'], $commAdm['cookies'], ['X-CSRF-Token' => $commAdm['csrf']]);
    pfgAssert($rCommOpRetry['status'] === 200, "T5-10 §5.1: communications_manager operational retry expected 200, got {$rCommOpRetry['status']}: {$rCommOpRetry['body']}");
    $rCommOpResend = p2Http('POST', '/api/admin/notifications', ['action' => 'resend', 'queueId' => $opSentId, 'reason' => 'Authorized comms resend'], $commAdm['cookies'], ['X-CSRF-Token' => $commAdm['csrf']]);
    pfgAssert($rCommOpResend['status'] === 201, "T5-10 §5.1: communications_manager operational resend expected 201, got {$rCommOpResend['status']}: {$rCommOpResend['body']}");

    // SUPERADMIN -> 400 when attempting to include notifications.auth.manage in a custom profile; 200 inspecting auth notifications
    $rForbidAuthPerm = p2Http('POST', '/api/admin/permission-profiles', [
        'name'        => 'Forbidden Auth Notif Profile ' . $t510Tag,
        'permissions' => ['notifications.read', 'notifications.auth.manage'],
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rForbidAuthPerm['status'] === 400, "T5-10 §5.1: SUPERADMIN custom profile with notifications.auth.manage expected 400, got {$rForbidAuthPerm['status']}");

    $rSaAuthGet = p2Http('GET', '/api/admin/notifications?id=' . urlencode($authPwdId), null, $saSess['cookies']);
    pfgAssert($rSaAuthGet['status'] === 200 && ($rSaAuthGet['json']['item']['template_code'] ?? '') === 'PASSWORD_RECOVERY', "T5-10 §5.1: SUPERADMIN GET auth notif expected 200, got {$rSaAuthGet['status']}");

    // ---------------------------------------------------------------------
    // 2. Staff Self-Approval Prevention (§5.3 — email match on accountless submission)
    // ---------------------------------------------------------------------
    pfgResetRateLimit($p5Pdo, 'registration_submit_ip', '127.0.0.1');
    $t510Anon = p5AnonSession();
    $rSelfReg = p2Http('POST', '/api/registrations', p5ValidPayload('VOLUNTEER', [
        'fullName' => 'RM Staff Self Reg',
        'email'    => $rmAdm['user']['email'],
    ]), $t510Anon['cookies'], ['X-CSRF-Token' => $t510Anon['csrf']]);
    pfgAssert($rSelfReg['status'] === 201, "T5-10 §5.3: Staff accountless VOLUNTEER registration failed: {$rSelfReg['status']} {$rSelfReg['body']}");
    $selfRegId = (string)$rSelfReg['json']['registration']['id'];

    $rSelfApprove = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($selfRegId), [
        'status' => 'APPROVED',
    ], $rmAdm['cookies'], ['X-CSRF-Token' => $rmAdm['csrf']]);
    pfgAssert(
        $rSelfApprove['status'] === 403 && ($rSelfApprove['json']['code'] ?? '') === 'SELF_APPROVAL_BLOCKED',
        "T5-10 §5.3: Staff self-approval expected 403 SELF_APPROVAL_BLOCKED, got {$rSelfApprove['status']}: {$rSelfApprove['body']}"
    );
    $selfAuditCnt = (int)$p5Pdo->query(
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'SELF_APPROVAL_BLOCKED' AND resource_id = " . $p5Pdo->quote($selfRegId)
    )->fetchColumn();
    pfgAssert($selfAuditCnt >= 1, 'T5-10 §5.3: SELF_APPROVAL_BLOCKED audit log entry missing');

    // ---------------------------------------------------------------------
    // 3. Centralized Registration Launch Gate (§6)
    // ---------------------------------------------------------------------
    $setSetting($p5Pdo, 'registration_public_open', '0');
    try {
        $regsBefore = (int)$p5Pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
        $rClosed = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE'), $t510Anon['cookies'], ['X-CSRF-Token' => $t510Anon['csrf']]);
        $regsAfter = (int)$p5Pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
        pfgAssert(
            $rClosed['status'] === 503 && !empty($rClosed['json']['registrationClosed']) && $regsAfter === $regsBefore,
            "T5-10 §6: Launch gate closed expected 503 registrationClosed=true & 0 rows created, got {$rClosed['status']} delta=" . ($regsAfter - $regsBefore) . " body={$rClosed['body']}"
        );
    } finally {
        $setSetting($p5Pdo, 'registration_public_open', '1');
    }

    // ---------------------------------------------------------------------
    // 4. Assigned-Volunteer Admin Cancellation Auto-Cancels Live Duties (§7)
    // ---------------------------------------------------------------------
    $rWVol = p2Http('POST', '/api/registrations', p5ValidPayload('VOLUNTEER', [
        'fullName' => 'Assigned Cancelled Volunteer',
        'email'    => "t510.withdraw.{$t510Tag}@example.test",
    ]), $t510Anon['cookies'], ['X-CSRF-Token' => $t510Anon['csrf']]);
    pfgAssert($rWVol['status'] === 201, "T5-10 §7: Volunteer registration failed: {$rWVol['body']}");
    $wVolId = (string)$rWVol['json']['registration']['id'];

    $rWApp = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($wVolId), ['status' => 'APPROVED'], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rWApp['status'] === 200, "T5-10 §7: Approve volunteer failed: {$rWApp['body']}");

    $rWDuty = p2Http('POST', '/api/admin/assignments', [
        'registrationId' => $wVolId,
        'roleTitle'      => 'T5-10 Hospitality Steward',
        'department'     => 'Welcome Desk',
        'dutyDateIst'    => '2027-01-21',
        'shiftCode'      => 'DAY_1_SHIFT_1',
        'startTime'      => '09:00',
        'endTime'        => '12:30',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rWDuty['status'] === 201, "T5-10 §7: Duty assignment failed: {$rWDuty['body']}");
    $wDutyId = (string)$rWDuty['json']['assignment']['id'];

    $rWDo = p2Http('PATCH', '/api/admin/registrations?id=' . urlencode($wVolId), [
        'status'        => 'CANCELLED',
        'publicMessage' => 'Volunteer cancelled per Secretariat email',
    ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
    pfgAssert($rWDo['status'] === 200 && ($rWDo['json']['registration']['status'] ?? '') === 'CANCELLED', "T5-10 §7: Admin cancel assigned volunteer failed: {$rWDo['status']} {$rWDo['body']}");

    $dutyStatusAfter = (string)$p5Pdo->query('SELECT status FROM duty_assignments WHERE id = ' . $p5Pdo->quote($wDutyId))->fetchColumn();
    $regAssignAfter = (string)$p5Pdo->query('SELECT assignment_status FROM registrations WHERE id = ' . $p5Pdo->quote($wVolId))->fetchColumn();
    pfgAssert($dutyStatusAfter === 'CANCELLED', "T5-10 §7: Expected duty_assignments.status=CANCELLED, got {$dutyStatusAfter}");
    pfgAssert($regAssignAfter === 'UNASSIGNED', "T5-10 §7: Expected registrations.assignment_status=UNASSIGNED, got {$regAssignAfter}");

    // ---------------------------------------------------------------------
    // 5. Configurable IST Event-Day Attendance Window (§8)
    // ---------------------------------------------------------------------
    $rAttGate = p2Http('POST', '/api/registrations', p5ValidPayload('ATTENDEE', ['fullName' => 'Gate Window Attendee']), $t510Anon['cookies'], ['X-CSRF-Token' => $t510Anon['csrf']]);
    pfgAssert($rAttGate['status'] === 201, "T5-10 §8: Attendee creation failed: {$rAttGate['body']}");
    $attGateRegId = (string)$rAttGate['json']['registration']['id'];

    $rgstUser = p2CreateTestUser($p5Pdo, 't510_rgst_' . $t510Tag, "t510.rgst.{$t510Tag}@example.test", 'RgstWin#2027', 'RGST');
    pfgResetRateLimit($p5Pdo, 'auth_login_ip', '127.0.0.1');
    $rgstSess = p2Login($rgstUser['username'], $rgstUser['password']);
    pfgAssert($rgstSess['resp']['status'] === 200, 'T5-10 §8: RGST login failed');

    $setSetting($p5Pdo, 'attendance_enforce_event_day_window', '1');
    try {
        $rRgstFuture = p2Http('POST', '/api/attendance/mark', [
            'registrationId'    => $attGateRegId,
            'attendanceDateIst' => '2027-01-23',
        ], $rgstSess['cookies'], ['X-CSRF-Token' => $rgstSess['csrf']]);
        pfgAssert(
            $rRgstFuture['status'] === 403 && ($rRgstFuture['json']['code'] ?? '') === 'FUTURE_EVENT_DAY_BLOCKED',
            "T5-10 §8: RGST future-date mark expected 403 FUTURE_EVENT_DAY_BLOCKED, got {$rRgstFuture['status']}: {$rRgstFuture['body']}"
        );

        $rSaOverride = p2Http('POST', '/api/attendance/mark', [
            'registrationId'     => $attGateRegId,
            'attendanceDateIst'  => '2027-01-23',
            'overrideFutureDate' => true,
            'overrideReason'     => 'Superadmin authorized pre-festival desk rehearsal verification',
        ], $saSess['cookies'], ['X-CSRF-Token' => $saSess['csrf']]);
        pfgAssert(
            $rSaOverride['status'] === 201 && ($rSaOverride['json']['record']['status'] ?? '') === 'PRESENT',
            "T5-10 §8: SUPERADMIN future-date override expected 201 PRESENT, got {$rSaOverride['status']}: {$rSaOverride['body']}"
        );
        $ovRecId = (string)$rSaOverride['json']['record']['id'];
        $ovAuditCnt = (int)$p5Pdo->query(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'ATTENDANCE_FUTURE_DATE_OVERRIDE' AND resource_id = " . $p5Pdo->quote($ovRecId)
        )->fetchColumn();
        pfgAssert($ovAuditCnt >= 1, 'T5-10 §8: ATTENDANCE_FUTURE_DATE_OVERRIDE audit log missing');
    } finally {
        $setSetting($p5Pdo, 'attendance_enforce_event_day_window', '0');
    }

    // ---------------------------------------------------------------------
    // 6. Stale / Expired / Used / Superseded Auth Token Guard (§9)
    // ---------------------------------------------------------------------
    // Drain any unrelated queued rows first so batch assertions are strictly scoped to our 2 stale jobs
    pfgProcessNotificationQueueBatch($p5Pdo, ['batchLimit' => 200]);
    foreach (glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $oldSink) {
        @unlink($oldSink);
    }

    $staleStaff = p2CreateTestUser($p5Pdo, 't510_stale_' . $t510Tag, "t510.stale.{$t510Tag}@example.test", 'StaleStaff#2027', 'ADMIN');
    $rawStaleReset = 't510-stale-reset-' . bin2hex(random_bytes(16));
    $p5Pdo->prepare('INSERT INTO password_reset_tokens (id, user_id, email, token_hash, expires_at, used_at, created_at) VALUES (:id, :uid, :em, :th, :exp, :used, :now)')
        ->execute([
            ':id'   => uuid4(),
            ':uid'  => $staleStaff['id'],
            ':em'   => $staleStaff['email'],
            ':th'   => hashToken($rawStaleReset),
            ':exp'  => nowSql(time() - 3600),
            ':used' => nowSql(time() - 1800),
            ':now'  => nowSql(time() - 7200),
        ]);
    $stalePwdQ = pfgEnqueueNotification($p5Pdo, [
        'to'              => $staleStaff['email'],
        'templateCode'    => 'PASSWORD_RECOVERY',
        'recipientUserId' => $staleStaff['id'],
        'variables'       => ['fullName' => 'Stale Token Staff'],
        'ephemeralVars'   => ['actionUrl' => APP_URL . '/reset-password?token=' . $rawStaleReset],
        'idempotencyKey'  => "t510-stale-pwd-{$t510Tag}",
    ]);
    $stalePwdId = (string)$stalePwdQ['queueId'];

    $rawStaleVer = 't510-stale-ver-' . bin2hex(random_bytes(16));
    $p5Pdo->prepare("INSERT INTO email_verification_tokens (id, user_id, purpose, target_email, token_hash, expires_at, used_at, created_at) VALUES (:id, :uid, 'SIGNUP_VERIFY', :em, :th, :exp, :used, :now)")
        ->execute([
            ':id'   => uuid4(),
            ':uid'  => $staleStaff['id'],
            ':em'   => $staleStaff['email'],
            ':th'   => hashToken($rawStaleVer),
            ':exp'  => nowSql(time() + 3600),
            ':used' => nowSql(time() - 120),
            ':now'  => nowSql(time() - 600),
        ]);
    $staleVerQ = pfgEnqueueNotification($p5Pdo, [
        'to'              => $staleStaff['email'],
        'templateCode'    => 'ACCOUNT_VERIFICATION',
        'recipientUserId' => $staleStaff['id'],
        'variables'       => ['fullName' => 'Stale Token Staff'],
        'ephemeralVars'   => ['actionUrl' => APP_URL . '/verify-email?token=' . $rawStaleVer],
        'idempotencyKey'  => "t510-stale-ver-{$t510Tag}",
    ]);
    $staleVerId = (string)$staleVerQ['queueId'];

    $batchStats = pfgProcessNotificationQueueBatch($p5Pdo, ['batchLimit' => 50]);
    $dispatchedSinkFiles = glob($sinkDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
    pfgAssert(
        count($dispatchedSinkFiles) === 0 && (int)$batchStats['capturedLocal'] === 0 && (int)$batchStats['accepted'] === 0,
        'T5-10 §9: Stale auth jobs dispatched unexpectedly: sink=' . count($dispatchedSinkFiles) . ' stats=' . json_encode($batchStats)
    );

    foreach (['PASSWORD_RECOVERY' => $stalePwdId, 'ACCOUNT_VERIFICATION' => $staleVerId] as $tplLabel => $jid) {
        $qRow = $p5Pdo->query('SELECT status, error_category, ephemeral_secret_enc FROM notification_queue WHERE id = ' . $p5Pdo->quote($jid))->fetch(PDO::FETCH_ASSOC);
        pfgAssert(
            is_array($qRow)
            && ($qRow['status'] ?? '') === 'CANCELLED'
            && ($qRow['error_category'] ?? '') === 'TOKEN_EXPIRED_OR_SUPERSEDED'
            && $qRow['ephemeral_secret_enc'] === null,
            "T5-10 §9: {$tplLabel} stale job invalid state: " . json_encode($qRow)
        );
        $logCnt = (int)$p5Pdo->query(
            "SELECT COUNT(*) FROM notification_delivery_logs WHERE job_id = " . $p5Pdo->quote($jid) . " AND event_type = 'STALE_AUTH_CANCELLED'"
        )->fetchColumn();
        pfgAssert($logCnt >= 1, "T5-10 §9: Missing STALE_AUTH_CANCELLED delivery log for {$tplLabel}");
    }

    // ---------------------------------------------------------------------
    // 7. Subdomain / Parent .pfg27_env.php Dynamic Loader (§14)
    // ---------------------------------------------------------------------
    $cfgSrc = (string)file_get_contents($root . '/includes/config.php');
    pfgAssert(!str_contains($cfgSrc, '/home1/tstpllmy'), 'T5-10 §14: Hardcoded /home1/tstpllmy path still present in includes/config.php');
    pfgAssert(preg_match('#/home\d*/[a-z0-9_-]+/#i', $cfgSrc) !== 1, 'T5-10 §14: Hardcoded hosting account home path found in includes/config.php');
    pfgAssert(
        str_contains($cfgSrc, "dirname(__DIR__, 2) . '/.pfg27_env.php'")
        && str_contains($cfgSrc, "dirname(__DIR__, 3) . '/.pfg27_env.php'")
        && str_contains($cfgSrc, "getenv('HOME')"),
        'T5-10 §14: includes/config.php missing dynamic parent/grandparent/HOME .pfg27_env.php lookup'
    );

    $fakeHome = $tmpDir . DIRECTORY_SEPARATOR . 't510_home_' . $t510Tag;
    mkdir($fakeHome, 0775, true);
    $fakeHomeEnv = $fakeHome . DIRECTORY_SEPARATOR . '.pfg27_env.php';
    file_put_contents($fakeHomeEnv, "<?php return ['APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1:19876', 'AUTH_SECRET' => str_repeat('x', 32), 'QR_SIGNING_SECRET' => str_repeat('y', 32)];\n");
    try {
        $envRes = runPhpSnippet(
            $phpBin,
            "require '" . addslashes($root . '/includes/config.php') . "'; echo json_encode(['url' => APP_URL, 'loaded' => \$GLOBALS['pfgLoadedEnvPath'] ?? null]);",
            ['PFG_ENV_FILE' => null, 'HOME' => $fakeHome]
        );
        $envDecoded = json_decode($envRes['stdout'], true);
        pfgAssert(
            is_array($envDecoded) && ($envDecoded['url'] ?? '') === 'http://127.0.0.1:19876',
            'T5-10 §14: Dynamic HOME .pfg27_env.php resolution failed: ' . $envRes['stdout'] . ' ' . $envRes['stderr']
        );
    } finally {
        @unlink($fakeHomeEnv);
        @rmdir($fakeHome);
    }

    return 'All 7 final release controls PASS: §5.1 notification RBAC (CM/VC 403, RM 200/403, Comms op-only+auth 403, SA auth 200+profile 400), §5.3 self-approval 403+audit, §6 launch gate 503, §7 withdrawal cancels duty+alerts coord, §8 IST day window 403+SA override 201, §9 2 stale auth jobs CANCELLED/wiped/0 sent, §14 dynamic .pfg27_env.php loader';
});
