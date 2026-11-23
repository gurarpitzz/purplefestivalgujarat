<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Registrations, Volunteer/SLI Review, QR Issuance & CSV Export API
 * Endpoints:
 *   GET    /api/admin/registrations (list + filter + search + ?export=csv / ?format=csv)
 *   GET    /api/admin/registrations?id=<uuid> or /api/admin/registrations/<uuid> (single detail)
 *   PATCH  /api/admin/registrations (or POST) — status review, volunteer/SLI assignment, QR issue/reissue/revoke
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qr-service.php';
require_once __DIR__ . '/../../includes/mailer.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Helper to record an audit log entry safely regardless of auth.php helper signature.
 */
function pfgAdminRecordAudit(PDO $pdo, ?string $actorUserId, string $action, string $resourceType, ?string $resourceId = null, array $metadata = []): void
{
    if (function_exists('recordAuditLog')) {
        try {
            recordAuditLog([
                'actorUserId'  => $actorUserId,
                'action'       => $action,
                'resourceType' => $resourceType,
                'resourceId'   => $resourceId,
                'ipAddress'    => getClientIp(),
                'userAgent'    => getClientUserAgent(),
                'metadata'     => $metadata,
            ]);
            return;
        } catch (Throwable $e) {
            // Fall through to direct insert
        }
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (id, actor_user_id, action, resource_type, resource_id, ip_address, user_agent, metadata, created_at)
            VALUES (:id, :actor, :action, :rtype, :rid, :ip, :ua, :meta, :now)
        ");
        $stmt->execute([
            ':id'     => uuid4(),
            ':actor'  => $actorUserId,
            ':action' => $action,
            ':rtype'  => $resourceType,
            ':rid'    => $resourceId,
            ':ip'     => getClientIp(),
            ':ua'     => getClientUserAgent(),
            ':meta'   => jsonEncode($metadata),
            ':now'    => nowSql(),
        ]);
    } catch (Throwable $e) {
        error_log('Audit log insert failed: ' . $e->getMessage());
    }
}

/**
 * Extracts registration ID from query parameter, request body, or URL path `/api/admin/registrations/<id>`.
 */
function pfgResolveRegistrationId(array $input = []): string
{
    if (!empty($_GET['id']) && is_string($_GET['id'])) {
        return trim($_GET['id']);
    }
    if (!empty($_GET['registrationId']) && is_string($_GET['registrationId'])) {
        return trim($_GET['registrationId']);
    }
    if (!empty($input['id']) && is_string($input['id'])) {
        return trim($input['id']);
    }
    if (!empty($input['registrationId']) && is_string($input['registrationId'])) {
        return trim($input['registrationId']);
    }
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (is_string($pathInfo) && trim($pathInfo, '/') !== '') {
        return trim($pathInfo, '/');
    }
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/api/admin/registrations(?:\.php)?/([A-Za-z0-9_-]{8,64})$#', $uriPath, $m)) {
        return $m[1];
    }
    return '';
}

function pfgFormatVolunteerRow(?array $v): ?array
{
    if (!$v) {
        return null;
    }
    return [
        'id'                     => (string)($v['id'] ?? ''),
        'registrationId'         => (string)($v['registration_id'] ?? ''),
        'age'                    => isset($v['age']) ? (int)$v['age'] : null,
        'gender'                 => $v['gender'] ?? null,
        'cityState'              => $v['city_state'] ?? null,
        'institutionName'        => $v['institution_name'] ?? null,
        'isMsuStudentOrAlumni'   => !empty($v['is_msu_student_or_alumni']),
        'crrNumber'              => $v['crr_number'] ?? null,
        'preferredRoles'         => jsonDecode($v['preferred_roles'] ?? '[]', []),
        'availabilityDays'       => jsonDecode($v['availability_days'] ?? '[]', []),
        'languagesSpoken'        => jsonDecode($v['languages_spoken'] ?? '[]', []),
        'relevantExperience'     => $v['relevant_experience'] ?? null,
        'emergencyContactName'   => $v['emergency_contact_name'] ?? null,
        'emergencyContactPhone'  => $v['emergency_contact_phone'] ?? null,
        'documentUrl'            => $v['document_url'] ?? null,
        'assignedDepartment'     => $v['assigned_department'] ?? null,
        'assignedResponsibility' => $v['assigned_responsibility'] ?? null,
        'assignedSupervisor'     => $v['assigned_supervisor'] ?? null,
        'assignedVenue'          => $v['assigned_venue'] ?? null,
        'assignedShift'          => $v['assigned_shift'] ?? null,
        'reportingTime'          => $v['reporting_time'] ?? null,
        'assignmentNotes'        => $v['assignment_notes'] ?? null,
        'createdAt'              => $v['created_at'] ?? null,
        'updatedAt'              => $v['updated_at'] ?? null,
    ];
}

function pfgFormatSliRow(?array $s): ?array
{
    if (!$s) {
        return null;
    }
    return [
        'id'                        => (string)($s['id'] ?? ''),
        'registrationId'            => (string)($s['registration_id'] ?? ''),
        'cityState'                 => $s['city_state'] ?? null,
        'basedInGujarat'            => !isset($s['based_in_gujarat']) || !empty($s['based_in_gujarat']),
        'crrNumber'                 => $s['crr_number'] ?? null,
        'qualifications'            => $s['qualifications'] ?? null,
        'trainingInstitute'         => $s['training_institute'] ?? null,
        'certificationLevel'        => $s['certification_level'] ?? null,
        'yearsOfExperience'         => isset($s['years_of_experience']) ? (int)$s['years_of_experience'] : null,
        'professionalAffiliation'   => $s['professional_affiliation'] ?? null,
        'areasOfExpertise'          => jsonDecode($s['areas_of_expertise'] ?? '[]', []),
        'interpretationPreferences' => jsonDecode($s['interpretation_preferences'] ?? '[]', []),
        'availabilityDays'          => jsonDecode($s['availability_days'] ?? '[]', []),
        'videoDemoUrl'              => $s['video_demo_url'] ?? null,
        'documentUrl'               => $s['document_url'] ?? null,
        'honorariumOrTravelNotes'   => $s['honorarium_or_travel_notes'] ?? null,
        'assignedSessions'          => jsonDecode($s['assigned_sessions'] ?? '[]', []),
        'assignedVenue'             => $s['assigned_venue'] ?? null,
        'dutyTimes'                 => $s['duty_times'] ?? null,
        'assignmentNotes'           => $s['assignment_notes'] ?? null,
        'createdAt'                 => $s['created_at'] ?? null,
        'updatedAt'                 => $s['updated_at'] ?? null,
    ];
}

function pfgFormatRegistrationRow(array $r, bool $canReadAccomm = true): array
{
    return [
        'id'                     => (string)$r['id'],
        'registrationCode'       => (string)$r['registration_code'],
        'editionId'              => (string)($r['edition_id'] ?? ''),
        'userId'                 => (string)($r['user_id'] ?? ''),
        'categoryId'             => (string)($r['category_id'] ?? ''),
        'categoryCode'           => (string)$r['category_code'],
        'status'                 => (string)$r['status'],
        'paymentStatus'          => (string)($r['payment_status'] ?? 'NOT_REQUIRED'),
        'fullName'               => (string)$r['full_name'],
        'email'                  => (string)$r['email'],
        'phone'                  => (string)$r['phone'],
        'country'                => (string)($r['country'] ?? 'India'),
        'occupation'             => $r['occupation'] ?? null,
        'organizationName'       => $r['organization_name'] ?? null,
        'designation'            => $r['designation'] ?? null,
        'isPersonWithDisability' => $canReadAccomm ? ($r['is_person_with_disability'] ?? 'PREFER_NOT_TO_SAY') : 'RESTRICTED',
        'attendingDays'          => jsonDecode($r['attending_days'] ?? '[]', []),
        'interestedTracks'       => jsonDecode($r['interested_tracks'] ?? '[]', []),
        'reviewerId'             => $r['reviewer_id'] ?? null,
        'reviewNotes'            => $r['review_notes'] ?? null,
        'reviewedAt'             => $r['reviewed_at'] ?? null,
        'submittedAt'            => $r['submitted_at'] ?? null,
        'createdAt'              => $r['created_at'] ?? null,
        'updatedAt'              => $r['updated_at'] ?? null,
    ];
}

function pfgEscapeCsvCell($value): string
{
    $str = $value === null ? '' : (string)$value;
    if (preg_match('/[",\r\n]/', $str)) {
        return '"' . str_replace('"', '""', $str) . '"';
    }
    return $str;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    $pdo = getDb();
    $canReadAccomm = hasPermission($user, PERMISSIONS['ACCOMMODATIONS_READ']);
    $canReadPayments = hasPermission($user, PERMISSIONS['PAYMENTS_READ']);

    // =========================================================================
    // GET: Single Registration Detail OR Filterable List / CSV Export
    // =========================================================================
    if ($method === 'GET') {
        $singleId = pfgResolveRegistrationId();

        if ($singleId !== '') {
            $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([':id' => $singleId]);
            $reg = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reg) {
                jsonResponse(['error' => 'Registration not found.'], 404);
            }

            // Delegate details
            $delegateRow = null;
            foreach (['delegate_details', 'delegate_profiles'] as $delTable) {
                try {
                    $stDel = $pdo->prepare("SELECT * FROM {$delTable} WHERE registration_id = :rid LIMIT 1");
                    $stDel->execute([':rid' => $singleId]);
                    $found = $stDel->fetch(PDO::FETCH_ASSOC);
                    if ($found) {
                        $delegateRow = [
                            'id'                       => (string)$found['id'],
                            'registrationId'           => (string)$found['registration_id'],
                            'gender'                   => $found['gender'] ?? null,
                            'age'                      => isset($found['age']) ? (int)$found['age'] : null,
                            'cityState'                => $found['city_state'] ?? null,
                            'paraSportsInterests'      => jsonDecode($found['para_sports_interests'] ?? '[]', []),
                            'outstationTravelRequired' => !empty($found['outstation_travel_required']),
                            'accommodationNeeded'      => !empty($found['accommodation_needed']),
                            'foodPreference'           => $found['food_preference'] ?? null,
                            'emergencyContactName'     => $found['emergency_contact_name'] ?? null,
                            'emergencyContactPhone'    => $found['emergency_contact_phone'] ?? null,
                            'guardianName'             => $found['guardian_name'] ?? null,
                        ];
                        break;
                    }
                } catch (Throwable $e) {
                }
            }

            // Volunteer details
            $stVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
            $stVol->execute([':rid' => $singleId]);
            $volRow = pfgFormatVolunteerRow($stVol->fetch(PDO::FETCH_ASSOC) ?: null);

            // SLI details
            $stSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
            $stSli->execute([':rid' => $singleId]);
            $sliRow = pfgFormatSliRow($stSli->fetch(PDO::FETCH_ASSOC) ?: null);

            // Credentials
            $stCred = $pdo->prepare('SELECT * FROM qr_credentials WHERE registration_id = :rid ORDER BY issued_at DESC');
            $stCred->execute([':rid' => $singleId]);
            $credRows = $stCred->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $credentials = array_map(static function (array $cr): array {
                $formatted = pfgFormatCredentialRow($cr);
                $formatted['qrPayload'] = reconstructQrTokenForCredential($cr);
                return $formatted;
            }, $credRows);

            // Check-ins
            $stChk = $pdo->prepare('SELECT * FROM checkins WHERE registration_id = :rid ORDER BY checked_in_at DESC');
            $stChk->execute([':rid' => $singleId]);
            $chkRows = $stChk->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $checkins = array_map(static function (array $c): array {
                return [
                    'id'               => (string)$c['id'],
                    'credentialId'     => (string)$c['credential_id'],
                    'registrationId'   => (string)$c['registration_id'],
                    'venueLabel'       => (string)($c['venue_label'] ?? 'Main Gate'),
                    'entitlementScope' => (string)($c['entitlement_scope'] ?? ''),
                    'checkinDateIst'   => (string)($c['checkin_date_ist'] ?? ''),
                    'method'           => (string)($c['method'] ?? 'QR_CAMERA'),
                    'result'           => (string)($c['result'] ?? 'CHECKED_IN'),
                    'checkedInAt'      => (string)($c['checked_in_at'] ?? ''),
                ];
            }, $chkRows);

            // Payments
            $paymentOrders = [];
            if ($canReadPayments) {
                foreach (['payment_orders', 'payments'] as $payTbl) {
                    try {
                        $stPay = $pdo->prepare("SELECT * FROM {$payTbl} WHERE registration_id = :rid ORDER BY created_at DESC");
                        $stPay->execute([':rid' => $singleId]);
                        $pRows = $stPay->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (count($pRows) > 0) {
                            foreach ($pRows as $pr) {
                                $paymentOrders[] = [
                                    'id'              => (string)$pr['id'],
                                    'registrationId'  => (string)$pr['registration_id'],
                                    'provider'        => (string)($pr['provider'] ?? 'RAZORPAY'),
                                    'providerOrderId' => (string)($pr['provider_order_id'] ?? ''),
                                    'amountInr'       => (int)($pr['amount_inr'] ?? 0),
                                    'discountInr'     => (int)($pr['discount_inr'] ?? 0),
                                    'netAmountInr'    => (int)($pr['net_amount_inr'] ?? 0),
                                    'status'          => (string)($pr['status'] ?? 'PENDING'),
                                    'createdAt'       => (string)($pr['created_at'] ?? ''),
                                ];
                            }
                            break;
                        }
                    } catch (Throwable $e) {
                    }
                }
            }

            // Accommodations & Disability Disclosures (only when permitted)
            $accommodations = null;
            $disabilityDisclosures = null;

            if ($canReadAccomm) {
                $accommMap = [];
                foreach (['accommodation_requests', 'registration_accommodations'] as $acTbl) {
                    try {
                        $stAc = $pdo->prepare("SELECT * FROM {$acTbl} WHERE registration_id = :rid");
                        $stAc->execute([':rid' => $singleId]);
                        $aRows = $stAc->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        foreach ($aRows as $ar) {
                            $aid = (string)$ar['id'];
                            if (!isset($accommMap[$aid])) {
                                $types = jsonDecode($ar['selected_types'] ?? '[]', []);
                                if (empty($types) && !empty($ar['accommodation_code'])) {
                                    $types = [(string)$ar['accommodation_code']];
                                }
                                $accommMap[$aid] = [
                                    'id'               => $aid,
                                    'registrationId'   => (string)$ar['registration_id'],
                                    'selectedTypes'    => $types,
                                    'freeTextDetails'  => $ar['free_text_details'] ?? $ar['notes'] ?? null,
                                    'status'           => (string)($ar['status'] ?? 'REQUESTED'),
                                    'coordinatorNotes' => $ar['coordinator_notes'] ?? null,
                                    'updatedAt'        => $ar['updated_at'] ?? null,
                                ];
                            }
                        }
                    } catch (Throwable $e) {
                    }
                }
                $accommodations = array_values($accommMap);

                $disclosures = [];
                foreach (['participant_disability_disclosures', 'registration_disabilities'] as $disTbl) {
                    try {
                        $stDis = $pdo->prepare("
                            SELECT d.id, d.self_description, d.notes, d.disability_code,
                                   dc.code AS category_code, dc.name AS category_name, dc.rpwd_group
                            FROM {$disTbl} d
                            LEFT JOIN disability_categories dc ON d.disability_category_id = dc.id OR d.disability_code = dc.code
                            WHERE d.registration_id = :rid
                        ");
                        $stDis->execute([':rid' => $singleId]);
                        $dRows = $stDis->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (count($dRows) > 0) {
                            foreach ($dRows as $dr) {
                                $disclosures[] = [
                                    'id'              => (string)$dr['id'],
                                    'selfDescription' => $dr['self_description'] ?? $dr['notes'] ?? null,
                                    'categoryCode'    => $dr['category_code'] ?? $dr['disability_code'] ?? null,
                                    'categoryName'    => $dr['category_name'] ?? $dr['disability_code'] ?? null,
                                    'rpwdGroup'       => $dr['rpwd_group'] ?? null,
                                ];
                            }
                            break;
                        }
                    } catch (Throwable $e) {
                    }
                }
                $disabilityDisclosures = $disclosures;
            }

            jsonResponse([
                'registration'             => pfgFormatRegistrationRow($reg, $canReadAccomm),
                'delegateDetails'          => $delegateRow,
                'volunteerDetails'         => $volRow,
                'sliDetails'               => $sliRow,
                'credentials'              => $credentials,
                'checkins'                 => $checkins,
                'paymentOrders'            => $paymentOrders,
                'accommodationsRestricted' => !$canReadAccomm,
                'accommodations'           => $accommodations,
                'disabilityDisclosures'    => $disabilityDisclosures,
            ]);
        }

        // LIST / FILTER / EXPORT
        $search = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
        $category = strtoupper(trim((string)($_GET['category'] ?? '')));
        $statusParam = strtoupper(trim((string)($_GET['status'] ?? '')));
        $paymentStatus = strtoupper(trim((string)($_GET['paymentStatus'] ?? '')));
        $country = trim((string)($_GET['country'] ?? ''));
        $checkinStatus = strtoupper(trim((string)($_GET['checkinStatus'] ?? '')));
        $hasAccommodations = strtolower(trim((string)($_GET['hasAccommodations'] ?? '')));
        $sortBy = trim((string)($_GET['sortBy'] ?? 'createdAt'));
        $sortOrder = strtolower(trim((string)($_GET['sortOrder'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';

        $exportFormat = strtolower(trim((string)($_GET['export'] ?? $_GET['format'] ?? '')));
        $includeAccommodations = ($_GET['includeAccommodations'] ?? '') === 'true' || ($_GET['includeAccommodations'] ?? '') === '1';

        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = min(200, max(1, (int)($_GET['pageSize'] ?? 25)));

        if (in_array($exportFormat, ['csv', 'xlsx'], true)) {
            if (!hasPermission($user, PERMISSIONS['REGISTRATIONS_EXPORT'])) {
                jsonResponse(['error' => 'Export permission required.'], 403);
            }
            if ($includeAccommodations && !$canReadAccomm) {
                jsonResponse(['error' => 'Accommodations read permission required for accommodation export.'], 403);
            }
        }

        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $where[] = '(LOWER(full_name) LIKE :q OR LOWER(email) LIKE :q OR LOWER(registration_code) LIKE :q OR LOWER(phone) LIKE :q OR LOWER(COALESCE(organization_name, \'\')) LIKE :q)';
            $params[':q'] = '%' . strtolower($search) . '%';
        }

        if ($category !== '') {
            $where[] = 'UPPER(category_code) = :category';
            $params[':category'] = $category;
        }

        if ($statusParam !== '') {
            $statuses = array_values(array_filter(array_map('trim', explode(',', $statusParam))));
            if (count($statuses) === 1) {
                $where[] = 'UPPER(status) = :status0';
                $params[':status0'] = $statuses[0];
            } elseif (count($statuses) > 1) {
                $ph = [];
                foreach ($statuses as $idx => $stVal) {
                    $key = ':status' . $idx;
                    $ph[] = $key;
                    $params[$key] = $stVal;
                }
                $where[] = 'UPPER(status) IN (' . implode(', ', $ph) . ')';
            }
        }

        if ($paymentStatus !== '') {
            $where[] = 'UPPER(payment_status) = :pay_status';
            $params[':pay_status'] = $paymentStatus;
        }

        if ($country !== '') {
            $where[] = 'LOWER(country) LIKE :country';
            $params[':country'] = '%' . strtolower($country) . '%';
        }

        $orderCol = match ($sortBy) {
            'fullName'         => 'full_name',
            'registrationCode' => 'registration_code',
            'status'           => 'status',
            'categoryCode'     => 'category_code',
            default            => 'created_at',
        };

        $sql = 'SELECT * FROM registrations WHERE ' . implode(' AND ', $where) . " ORDER BY {$orderCol} {$sortOrder}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Bulk load related checkins, credentials, accommodations, volunteer & SLI records
        $checkinsByReg = [];
        $credByReg = [];
        $accommByReg = [];
        $volByReg = [];
        $sliByReg = [];

        if (count($rows) > 0) {
            $stmtChk = $pdo->query('SELECT registration_id, checked_in_at, venue_label FROM checkins ORDER BY checked_in_at DESC');
            foreach (($stmtChk ? $stmtChk->fetchAll(PDO::FETCH_ASSOC) : []) as $c) {
                $rid = (string)$c['registration_id'];
                if (!isset($checkinsByReg[$rid])) {
                    $checkinsByReg[$rid] = [
                        'count'     => 1,
                        'lastAt'    => (string)$c['checked_in_at'],
                        'lastVenue' => (string)($c['venue_label'] ?? 'Main Gate'),
                    ];
                } else {
                    $checkinsByReg[$rid]['count']++;
                }
            }

            $stmtCr = $pdo->query('SELECT registration_id, status, credential_code, issued_at FROM qr_credentials ORDER BY issued_at DESC');
            foreach (($stmtCr ? $stmtCr->fetchAll(PDO::FETCH_ASSOC) : []) as $cr) {
                $rid = (string)$cr['registration_id'];
                if (!isset($credByReg[$rid])) {
                    $credByReg[$rid] = [
                        'status' => (string)$cr['status'],
                        'code'   => (string)$cr['credential_code'],
                    ];
                }
            }

            foreach (['accommodation_requests', 'registration_accommodations'] as $acTbl) {
                try {
                    $stmtAc = $pdo->query("SELECT registration_id, status, selected_types, accommodation_code, free_text_details, notes FROM {$acTbl}");
                    foreach (($stmtAc ? $stmtAc->fetchAll(PDO::FETCH_ASSOC) : []) as $ac) {
                        $rid = (string)$ac['registration_id'];
                        $types = jsonDecode($ac['selected_types'] ?? '[]', []);
                        if (empty($types) && !empty($ac['accommodation_code'])) {
                            $types = [(string)$ac['accommodation_code']];
                        }
                        if (!isset($accommByReg[$rid])) {
                            $accommByReg[$rid] = [
                                'status'          => (string)($ac['status'] ?? 'REQUESTED'),
                                'selectedTypes'   => is_array($types) ? $types : [],
                                'freeTextDetails' => $ac['free_text_details'] ?? $ac['notes'] ?? null,
                            ];
                        } else {
                            $accommByReg[$rid]['selectedTypes'] = array_values(array_unique(array_merge(
                                $accommByReg[$rid]['selectedTypes'],
                                is_array($types) ? $types : []
                            )));
                        }
                    }
                } catch (Throwable $e) {
                }
            }

            $stmtVol = $pdo->query('SELECT * FROM volunteer_applications');
            foreach (($stmtVol ? $stmtVol->fetchAll(PDO::FETCH_ASSOC) : []) as $v) {
                $volByReg[(string)$v['registration_id']] = pfgFormatVolunteerRow($v);
            }

            $stmtSli = $pdo->query('SELECT * FROM sli_applications');
            foreach (($stmtSli ? $stmtSli->fetchAll(PDO::FETCH_ASSOC) : []) as $s) {
                $sliByReg[(string)$s['registration_id']] = pfgFormatSliRow($s);
            }
        }

        $enriched = [];
        foreach ($rows as $r) {
            $rid = (string)$r['id'];
            $ck = $checkinsByReg[$rid] ?? null;
            $cred = $credByReg[$rid] ?? null;
            $accomm = $accommByReg[$rid] ?? null;

            $item = pfgFormatRegistrationRow($r, $canReadAccomm);
            $item['checkedIn'] = $ck !== null;
            $item['checkinCount'] = $ck['count'] ?? 0;
            $item['lastCheckinAt'] = $ck['lastAt'] ?? null;
            $item['lastCheckinVenue'] = $ck['lastVenue'] ?? null;
            $item['credentialStatus'] = $cred['status'] ?? null;
            $item['credentialCode'] = $cred['code'] ?? null;
            $item['hasAccommodationRequest'] = $accomm !== null;
            $item['accommodationStatus'] = $accomm['status'] ?? null;
            if ($canReadAccomm) {
                $item['accommodationTypes'] = $accomm['selectedTypes'] ?? [];
                $item['accommodationFreeText'] = $accomm['freeTextDetails'] ?? null;
            }
            $item['volunteerDetails'] = $volByReg[$rid] ?? null;
            $item['sliDetails'] = $sliByReg[$rid] ?? null;

            if ($checkinStatus === 'CHECKED_IN' && !$item['checkedIn']) {
                continue;
            }
            if ($checkinStatus === 'NOT_CHECKED_IN' && $item['checkedIn']) {
                continue;
            }
            if ($hasAccommodations === 'true' && !$item['hasAccommodationRequest']) {
                continue;
            }
            if ($hasAccommodations === 'false' && $item['hasAccommodationRequest']) {
                continue;
            }

            $enriched[] = $item;
        }

        // CSV / XLSX Export Mode
        if (in_array($exportFormat, ['csv', 'xlsx'], true)) {
            $exportRows = [];
            foreach ($enriched as $r) {
                $base = [
                    'Registration Code'  => $r['registrationCode'],
                    'Full Name'          => $r['fullName'],
                    'Email'              => $r['email'],
                    'Phone'              => $r['phone'],
                    'Country'            => $r['country'],
                    'Category'           => $r['categoryCode'],
                    'Status'             => $r['status'],
                    'Payment Status'     => $r['paymentStatus'],
                    'Occupation'         => $r['occupation'] ?? '',
                    'Organization'       => $r['organizationName'] ?? '',
                    'Designation'        => $r['designation'] ?? '',
                    'Attending Days'     => implode(', ', is_array($r['attendingDays']) ? $r['attendingDays'] : []),
                    'Credential Code'    => $r['credentialCode'] ?? '',
                    'Credential Status'  => $r['credentialStatus'] ?? '',
                    'Checked In'         => $r['checkedIn'] ? 'Yes' : 'No',
                    'Check-in Count'     => $r['checkinCount'],
                    'Last Check-in Venue'=> $r['lastCheckinVenue'] ?? '',
                    'Submitted At'       => $r['submittedAt'] ?? '',
                ];
                if ($includeAccommodations && $canReadAccomm) {
                    $base['Accommodation Requested'] = $r['hasAccommodationRequest'] ? 'Yes' : 'No';
                    $base['Accommodation Status'] = $r['accommodationStatus'] ?? '';
                    $base['Accommodation Types'] = implode('; ', $r['accommodationTypes'] ?? []);
                    $base['Accommodation Details'] = $r['accommodationFreeText'] ?? '';
                }
                $exportRows[] = $base;
            }

            pfgAdminRecordAudit($pdo, (string)$user['id'], 'REGISTRATIONS_EXPORTED', 'registration', null, [
                'format'                => $exportFormat,
                'rowCount'              => count($exportRows),
                'includeAccommodations' => $includeAccommodations && $canReadAccomm,
            ]);

            $dateSlug = date('Y-m-d');
            $headers = count($exportRows) > 0
                ? array_keys($exportRows[0])
                : ['Registration Code', 'Full Name', 'Email', 'Category', 'Status'];

            $lines = [implode(',', array_map('pfgEscapeCsvCell', $headers))];
            foreach ($exportRows as $er) {
                $cells = [];
                foreach ($headers as $h) {
                    $cells[] = pfgEscapeCsvCell($er[$h] ?? '');
                }
                $lines[] = implode(',', $cells);
            }
            $csvContent = implode("\r\n", $lines);

            http_response_code(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pfg2027-registrations-' . $dateSlug . '.csv"');
            echo $csvContent;
            exit;
        }

        $total = count($enriched);
        $totalPages = max(1, (int)ceil($total / $pageSize));
        $offset = ($page - 1) * $pageSize;
        $pagedItems = array_slice($enriched, $offset, $pageSize);

        jsonResponse([
            'items'      => $pagedItems,
            'pagination' => [
                'page'       => $page,
                'pageSize'   => $pageSize,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    // =========================================================================
    // PATCH / POST: Update Registration Status, Volunteer/SLI Duty, or QR Pass
    // =========================================================================
    if ($method === 'PATCH' || $method === 'POST') {
        $input = readRequestInput();
        $id = pfgResolveRegistrationId($input);

        if ($id === '') {
            jsonResponse(['error' => 'Registration ID is required.'], 400);
        }

        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['error' => 'Registration not found.'], 404);
        }

        $allowedStatuses = [
            'DRAFT',
            'SUBMITTED',
            'UNDER_REVIEW',
            'ADDITIONAL_INFO_REQUIRED',
            'CONFIRMED',
            'APPROVED',
            'WAITLISTED',
            'REJECTED',
            'WITHDRAWN',
            'CANCELLED',
        ];

        $newStatus = isset($input['status']) && is_string($input['status'])
            ? strtoupper(trim($input['status']))
            : null;
        if ($newStatus !== null && $newStatus !== '' && !in_array($newStatus, $allowedStatuses, true)) {
            jsonResponse(['error' => "Invalid status value: {$newStatus}"], 400);
        }

        $reviewNotes = array_key_exists('reviewNotes', $input)
            ? (string)$input['reviewNotes']
            : null;

        $beforeState = [
            'status'      => $existing['status'],
            'reviewNotes' => $existing['review_notes'],
        ];

        $now = nowSql();
        $nextStatus = ($newStatus !== null && $newStatus !== '') ? $newStatus : (string)$existing['status'];
        $nextNotes = $reviewNotes !== null ? $reviewNotes : $existing['review_notes'];
        $reviewerId = ($newStatus !== null || $reviewNotes !== null) ? (string)$user['id'] : $existing['reviewer_id'];
        $reviewedAt = ($newStatus !== null || $reviewNotes !== null) ? $now : $existing['reviewed_at'];

        $upStmt = $pdo->prepare("
            UPDATE registrations
            SET status = :status,
                review_notes = :review_notes,
                reviewer_id = :reviewer_id,
                reviewed_at = :reviewed_at,
                updated_at = :updated_at
            WHERE id = :id
        ");
        $upStmt->execute([
            ':status'       => $nextStatus,
            ':review_notes' => $nextNotes,
            ':reviewer_id'  => $reviewerId,
            ':reviewed_at'  => $reviewedAt,
            ':updated_at'   => $now,
            ':id'           => $id,
        ]);

        // Refresh registration row
        $stmt->execute([':id' => $id]);
        $updatedReg = $stmt->fetch(PDO::FETCH_ASSOC);

        // Volunteer assignment update
        $updatedVolunteer = null;
        if (!empty($input['volunteerAssignment']) && is_array($input['volunteerAssignment'])) {
            $va = $input['volunteerAssignment'];
            $stVol = $pdo->prepare('SELECT * FROM volunteer_applications WHERE registration_id = :rid LIMIT 1');
            $stVol->execute([':rid' => $id]);
            $existingVol = $stVol->fetch(PDO::FETCH_ASSOC);

            if ($existingVol) {
                $upVol = $pdo->prepare("
                    UPDATE volunteer_applications
                    SET assigned_department = :dept,
                        assigned_responsibility = :resp,
                        assigned_supervisor = :sup,
                        assigned_venue = :venue,
                        assigned_shift = :shift,
                        reporting_time = :rtime,
                        assignment_notes = :notes,
                        updated_at = :now
                    WHERE id = :id
                ");
                $upVol->execute([
                    ':dept'  => $va['assignedDepartment'] ?? $existingVol['assigned_department'],
                    ':resp'  => $va['assignedResponsibility'] ?? $existingVol['assigned_responsibility'],
                    ':sup'   => $va['assignedSupervisor'] ?? $existingVol['assigned_supervisor'],
                    ':venue' => $va['assignedVenue'] ?? $existingVol['assigned_venue'],
                    ':shift' => $va['assignedShift'] ?? $existingVol['assigned_shift'],
                    ':rtime' => $va['reportingTime'] ?? $existingVol['reporting_time'],
                    ':notes' => $va['assignmentNotes'] ?? $existingVol['assignment_notes'],
                    ':now'   => $now,
                    ':id'    => $existingVol['id'],
                ]);
            } else {
                $insVol = $pdo->prepare("
                    INSERT INTO volunteer_applications (
                        id, registration_id, assigned_department, assigned_responsibility,
                        assigned_supervisor, assigned_venue, assigned_shift, reporting_time,
                        assignment_notes, created_at, updated_at
                    ) VALUES (
                        :id, :rid, :dept, :resp, :sup, :venue, :shift, :rtime, :notes, :now, :now
                    )
                ");
                $insVol->execute([
                    ':id'    => uuid4(),
                    ':rid'   => $id,
                    ':dept'  => $va['assignedDepartment'] ?? null,
                    ':resp'  => $va['assignedResponsibility'] ?? null,
                    ':sup'   => $va['assignedSupervisor'] ?? null,
                    ':venue' => $va['assignedVenue'] ?? null,
                    ':shift' => $va['assignedShift'] ?? null,
                    ':rtime' => $va['reportingTime'] ?? null,
                    ':notes' => $va['assignmentNotes'] ?? null,
                    ':now'   => $now,
                ]);
            }
            $stVol->execute([':rid' => $id]);
            $updatedVolunteer = pfgFormatVolunteerRow($stVol->fetch(PDO::FETCH_ASSOC) ?: null);
        }

        // SLI assignment update
        $updatedSli = null;
        if (!empty($input['sliAssignment']) && is_array($input['sliAssignment'])) {
            $sa = $input['sliAssignment'];
            $stSli = $pdo->prepare('SELECT * FROM sli_applications WHERE registration_id = :rid LIMIT 1');
            $stSli->execute([':rid' => $id]);
            $existingSli = $stSli->fetch(PDO::FETCH_ASSOC);

            $sessionsJson = isset($sa['assignedSessions']) && is_array($sa['assignedSessions'])
                ? jsonEncode($sa['assignedSessions'])
                : ($existingSli['assigned_sessions'] ?? '[]');

            if ($existingSli) {
                $upSli = $pdo->prepare("
                    UPDATE sli_applications
                    SET assigned_sessions = :sessions,
                        assigned_venue = :venue,
                        duty_times = :duty,
                        assignment_notes = :notes,
                        updated_at = :now
                    WHERE id = :id
                ");
                $upSli->execute([
                    ':sessions' => $sessionsJson,
                    ':venue'    => $sa['assignedVenue'] ?? $existingSli['assigned_venue'],
                    ':duty'     => $sa['dutyTimes'] ?? $existingSli['duty_times'],
                    ':notes'    => $sa['assignmentNotes'] ?? $existingSli['assignment_notes'],
                    ':now'      => $now,
                    ':id'       => $existingSli['id'],
                ]);
            } else {
                $insSli = $pdo->prepare("
                    INSERT INTO sli_applications (
                        id, registration_id, assigned_sessions, assigned_venue, duty_times,
                        assignment_notes, created_at, updated_at
                    ) VALUES (
                        :id, :rid, :sessions, :venue, :duty, :notes, :now, :now
                    )
                ");
                $insSli->execute([
                    ':id'       => uuid4(),
                    ':rid'      => $id,
                    ':sessions' => $sessionsJson,
                    ':venue'    => $sa['assignedVenue'] ?? null,
                    ':duty'     => $sa['dutyTimes'] ?? null,
                    ':notes'    => $sa['assignmentNotes'] ?? null,
                    ':now'      => $now,
                ]);
            }
            $stSli->execute([':rid' => $id]);
            $updatedSli = pfgFormatSliRow($stSli->fetch(PDO::FETCH_ASSOC) ?: null);
        }

        // Credential issuance / revocation logic
        $credentialAction = isset($input['credentialAction']) && is_string($input['credentialAction'])
            ? strtoupper(trim($input['credentialAction']))
            : '';
        $revocationReason = trim((string)($input['revocationReason'] ?? $reviewNotes ?? 'Revoked by festival administrator'));
        $credentialResult = null;

        $transitionedToApproved = in_array($nextStatus, ['APPROVED', 'CONFIRMED'], true)
            && !in_array((string)$existing['status'], ['APPROVED', 'CONFIRMED'], true);

        $emailDeliveryResult = null;
        $issuedEmailToken = '';
        $issuedEmailCredCode = '';

        if ($credentialAction === 'REVOKE') {
            if (!hasPermission($user, PERMISSIONS['CREDENTIALS_REVOKE'])) {
                jsonResponse(['error' => 'Permission required to revoke credentials.'], 403);
            }
            $stAct = $pdo->prepare("SELECT id FROM qr_credentials WHERE registration_id = :rid AND status = 'ACTIVE'");
            $stAct->execute([':rid' => $id]);
            foreach (($stAct->fetchAll(PDO::FETCH_ASSOC) ?: []) as $ac) {
                revokeCredential($pdo, (string)$ac['id'], $revocationReason, (string)$user['id']);
            }
            $credentialResult = ['action' => 'REVOKED'];
        } elseif ($credentialAction === 'ISSUE' || $credentialAction === 'REISSUE') {
            if (!hasPermission($user, PERMISSIONS['CREDENTIALS_ISSUE'])) {
                jsonResponse(['error' => 'Permission required to issue credentials.'], 403);
            }
            try {
                $issued = issueCredentialForRegistration($pdo, $id, (string)$user['id']);
                $issuedEmailCredCode = (string)($issued['credential']['credentialCode'] ?? $issued['credentialCode'] ?? '');
                $issuedEmailToken = (string)($issued['rawToken'] ?? '');
                $credentialResult = [
                    'action'         => $credentialAction,
                    'credentialCode' => $issuedEmailCredCode !== '' ? $issuedEmailCredCode : null,
                    'credentialId'   => $issued['credential']['id'] ?? null,
                    'qrPayload'      => $issuedEmailToken !== '' ? $issuedEmailToken : null,
                ];
            } catch (Throwable $e) {
                $credentialResult = [
                    'action'  => 'SKIPPED',
                    'warning' => $e->getMessage(),
                ];
            }
        } elseif ($transitionedToApproved) {
            $stAct = $pdo->prepare("SELECT id, credential_code FROM qr_credentials WHERE registration_id = :rid AND status = 'ACTIVE' LIMIT 1");
            $stAct->execute([':rid' => $id]);
            $existingActive = $stAct->fetch(PDO::FETCH_ASSOC);

            if (!$existingActive) {
                try {
                    $issued = issueCredentialForRegistration($pdo, $id, (string)$user['id']);
                    $issuedEmailCredCode = (string)($issued['credential']['credentialCode'] ?? $issued['credentialCode'] ?? '');
                    $issuedEmailToken = (string)($issued['rawToken'] ?? '');
                    $credentialResult = [
                        'action'         => 'AUTO_ISSUED',
                        'credentialCode' => $issuedEmailCredCode !== '' ? $issuedEmailCredCode : null,
                        'credentialId'   => $issued['credential']['id'] ?? null,
                        'qrPayload'      => $issuedEmailToken !== '' ? $issuedEmailToken : null,
                    ];
                } catch (Throwable $e) {
                    $credentialResult = [
                        'action'  => 'AUTO_ISSUE_SKIPPED',
                        'warning' => $e->getMessage(),
                    ];
                }
            } else {
                $issuedEmailCredCode = (string)$existingActive['credential_code'];
            }
        }

        // Send QR Pass email whenever credential is issued/reissued or registration transitions to approved/confirmed
        if ($issuedEmailToken !== '' || $transitionedToApproved) {
            try {
                $dutyDetails = null;
                if ($updatedVolunteer) {
                    $dutyDetails = [
                        'Department' => $updatedVolunteer['assignedDepartment'] ?? null,
                        'Venue'      => $updatedVolunteer['assignedVenue'] ?? null,
                        'Shift'      => $updatedVolunteer['assignedShift'] ?? null,
                        'Reporting'  => $updatedVolunteer['reportingTime'] ?? null,
                    ];
                } elseif ($updatedSli) {
                    $dutyDetails = [
                        'Assigned Venue'    => $updatedSli['assignedVenue'] ?? null,
                        'Duty Times'        => $updatedSli['dutyTimes'] ?? null,
                        'Assigned Sessions' => $updatedSli['assignedSessions'] ?? null,
                    ];
                }

                $emailDeliveryResult = sendRegistrationQrPassEmail($pdo, [
                    'to'               => (string)$updatedReg['email'],
                    'fullName'         => (string)$updatedReg['full_name'],
                    'registrationCode' => (string)$updatedReg['registration_code'],
                    'credentialCode'   => $issuedEmailCredCode !== '' ? $issuedEmailCredCode : (string)$updatedReg['registration_code'],
                    'categoryName'     => (string)$updatedReg['category_code'],
                    'status'           => (string)$updatedReg['status'],
                    'rawToken'         => $issuedEmailToken,
                    'recipientUserId'  => $updatedReg['user_id'] ?? null,
                    'dutyDetails'      => $dutyDetails,
                ]);
            } catch (Throwable $e) {
                error_log('/api/admin/registrations.php email send error: ' . $e->getMessage());
            }
        }

        // Record admin action & audit log
        try {
            $actStmt = $pdo->prepare("
                INSERT INTO admin_actions (id, admin_user_id, target_user_id, action_type, reason, before_state, after_state, created_at)
                VALUES (:id, :admin_id, :target_id, :action_type, :reason, :before_state, :after_state, :now)
            ");
            $actStmt->execute([
                ':id'           => uuid4(),
                ':admin_id'     => $user['id'],
                ':target_id'    => $updatedReg['user_id'] ?? null,
                ':action_type'  => $credentialAction !== ''
                    ? "CREDENTIAL_{$credentialAction}"
                    : ($newStatus ? "REGISTRATION_STATUS_{$nextStatus}" : 'REGISTRATION_UPDATED'),
                ':reason'       => $reviewNotes ?? ($input['revocationReason'] ?? null),
                ':before_state' => jsonEncode($beforeState),
                ':after_state'  => jsonEncode([
                    'status'              => $updatedReg['status'],
                    'reviewNotes'         => $updatedReg['review_notes'],
                    'volunteerAssignment' => $input['volunteerAssignment'] ?? null,
                    'sliAssignment'       => $input['sliAssignment'] ?? null,
                    'credentialResult'    => $credentialResult,
                ]),
                ':now'          => $now,
            ]);
        } catch (Throwable $e) {
        }

        pfgAdminRecordAudit(
            $pdo,
            (string)$user['id'],
            $newStatus ? "REGISTRATION_{$nextStatus}" : 'REGISTRATION_UPDATED',
            'registration',
            $id,
            [
                'registrationCode' => $updatedReg['registration_code'],
                'previousStatus'   => $existing['status'],
                'newStatus'        => $updatedReg['status'],
                'credentialAction' => $credentialAction ?: null,
            ]
        );

        jsonResponse([
            'registration'     => pfgFormatRegistrationRow($updatedReg, $canReadAccomm),
            'volunteerDetails' => $updatedVolunteer,
            'sliDetails'       => $updatedSli,
            'credentialResult' => $credentialResult,
            'emailDelivery'    => $emailDeliveryResult,
        ]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/registrations.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process registration admin request.'], 500);
}
