<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Admin Content Management System (CMS) API
 * Endpoint: GET / POST / PATCH / DELETE /api/admin/cms (or /api/admin/cms.php)
 * Manages:
 *   - Homepage sections (cms_sections)
 *   - 3-Day Programme schedule (programme_items)
 *   - Organizations & Partners (organizations, partners — with mandatory consent verification gate!)
 *   - FAQs (faq_items)
 *   - Gallery & Media Assets (media_assets)
 *   - Announcements (announcements)
 *   - Registration Categories (registration_categories)
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function pfgCmsGetActiveEdition(PDO $pdo): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => FESTIVAL_EDITION_SLUG]);
    $ed = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ed) {
        return $ed;
    }
    $any = $pdo->query('SELECT * FROM festival_editions LIMIT 1');
    return $any ? ($any->fetch(PDO::FETCH_ASSOC) ?: null) : null;
}

function pfgCmsRecordRevision(PDO $pdo, string $entityType, string $entityId, $snapshot, string $changedBy, string $changeSummary): void
{
    try {
        $stmt = $pdo->prepare('
            SELECT MAX(version_number) FROM content_revisions
            WHERE entity_type = :etype AND entity_id = :eid
        ');
        $stmt->execute([':etype' => $entityType, ':eid' => $entityId]);
        $nextVer = ((int)$stmt->fetchColumn()) + 1;

        $ins = $pdo->prepare('
            INSERT INTO content_revisions (id, entity_type, entity_id, version_number, snapshot, changed_by, change_summary, created_at)
            VALUES (:id, :etype, :eid, :ver, :snap, :uid, :summary, :now)
        ');
        $ins->execute([
            ':id'      => uuid4(),
            ':etype'   => $entityType,
            ':eid'     => $entityId,
            ':ver'     => $nextVer,
            ':snap'    => jsonEncode($snapshot),
            ':uid'     => $changedBy,
            ':summary' => $changeSummary,
            ':now'     => nowSql(),
        ]);
    } catch (Throwable $e) {
        error_log('Content revision log error: ' . $e->getMessage());
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    $user = requireRole(['ADMIN', 'SUPERADMIN']);
    requirePermission(PERMISSIONS['CMS_MANAGE'], $user);
    if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        requireCsrfToken($user);
    }
    $pdo = getDb();
    $edition = pfgCmsGetActiveEdition($pdo);

    if (!$edition) {
        jsonResponse(['error' => 'No festival edition configured.'], 404);
    }
    $editionId = (string)$edition['id'];

    // =========================================================================
    // GET: Fetch Full CMS Snapshot
    // =========================================================================
    if ($method === 'GET') {
        // Pages & Sections
        $stPages = $pdo->prepare('SELECT * FROM cms_pages WHERE edition_id = :eid ORDER BY created_at ASC');
        $stPages->execute([':eid' => $editionId]);
        $pages = $stPages->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sections = [];
        if (count($pages) > 0) {
            $pageId = (string)$pages[0]['id'];
            $stSec = $pdo->prepare('SELECT * FROM cms_sections WHERE page_id = :pid ORDER BY display_order ASC');
            $stSec->execute([':pid' => $pageId]);
            foreach (($stSec->fetchAll(PDO::FETCH_ASSOC) ?: []) as $s) {
                $sections[] = [
                    'id'           => (string)$s['id'],
                    'pageId'       => (string)$s['page_id'],
                    'sectionKey'   => (string)$s['section_key'],
                    'title'        => $s['title'] ?? null,
                    'subtitle'     => $s['subtitle'] ?? null,
                    'body'         => $s['body'] ?? null,
                    'data'         => jsonDecode($s['data'] ?? null, null),
                    'displayOrder' => (int)($s['display_order'] ?? 0),
                    'status'       => (string)($s['status'] ?? 'PUBLISHED'),
                    'updatedAt'    => (string)($s['updated_at'] ?? ''),
                ];
            }
        }

        // Programme items
        $stProg = $pdo->prepare('SELECT * FROM programme_items WHERE edition_id = :eid ORDER BY day_number ASC, display_order ASC');
        $stProg->execute([':eid' => $editionId]);
        $programmeItems = [];
        foreach (($stProg->fetchAll(PDO::FETCH_ASSOC) ?: []) as $p) {
            $programmeItems[] = [
                'id'                    => (string)$p['id'],
                'editionId'             => (string)$p['edition_id'],
                'dayNumber'             => (int)$p['day_number'],
                'timeSlot'              => (string)$p['time_slot'],
                'title'                 => (string)$p['title'],
                'description'           => $p['description'] ?? null,
                'category'              => (string)$p['category'],
                'venueName'             => $p['venue_name'] ?? null,
                'speakers'              => jsonDecode($p['speakers'] ?? '[]', []),
                'accessibilityServices' => jsonDecode($p['accessibility_services'] ?? '[]', []),
                'displayOrder'          => (int)($p['display_order'] ?? 0),
                'status'                => (string)($p['status'] ?? 'PUBLISHED'),
                'updatedAt'             => (string)($p['updated_at'] ?? ''),
            ];
        }

        // Partners joined with Organizations
        $stPart = $pdo->prepare('
            SELECT
                p.id,
                p.edition_id,
                p.organization_id,
                p.category,
                p.consent_verified,
                p.display_order,
                p.status,
                p.updated_at,
                o.name AS organization_name,
                o.short_name,
                o.website_url,
                o.logo_url,
                o.logo_alt_text,
                o.description
            FROM partners p
            INNER JOIN organizations o ON p.organization_id = o.id
            WHERE p.edition_id = :eid
            ORDER BY p.display_order ASC
        ');
        $stPart->execute([':eid' => $editionId]);
        $partners = [];
        foreach (($stPart->fetchAll(PDO::FETCH_ASSOC) ?: []) as $pt) {
            $partners[] = [
                'id'               => (string)$pt['id'],
                'editionId'        => (string)$pt['edition_id'],
                'organizationId'   => (string)$pt['organization_id'],
                'organizationName' => (string)$pt['organization_name'],
                'shortName'        => $pt['short_name'] ?? null,
                'websiteUrl'       => $pt['website_url'] ?? null,
                'logoUrl'          => $pt['logo_url'] ?? null,
                'logoAltText'      => $pt['logo_alt_text'] ?? null,
                'description'      => $pt['description'] ?? null,
                'category'         => (string)$pt['category'],
                'consentVerified'  => !empty($pt['consent_verified']),
                'displayOrder'     => (int)($pt['display_order'] ?? 0),
                'status'           => (string)($pt['status'] ?? 'DRAFT'),
                'updatedAt'        => (string)($pt['updated_at'] ?? ''),
            ];
        }

        // FAQs
        $stFaq = $pdo->prepare('SELECT * FROM faq_items WHERE edition_id = :eid ORDER BY display_order ASC');
        $stFaq->execute([':eid' => $editionId]);
        $faqItems = [];
        foreach (($stFaq->fetchAll(PDO::FETCH_ASSOC) ?: []) as $f) {
            $faqItems[] = [
                'id'           => (string)$f['id'],
                'editionId'    => (string)$f['edition_id'],
                'category'     => (string)($f['category'] ?? 'General'),
                'question'     => (string)$f['question'],
                'answer'       => (string)$f['answer'],
                'displayOrder' => (int)($f['display_order'] ?? 0),
                'status'       => (string)($f['status'] ?? 'PUBLISHED'),
                'updatedAt'    => (string)($f['updated_at'] ?? ''),
            ];
        }

        // Gallery / Media Assets
        $mediaAssets = [];
        try {
            $stMedia = $pdo->query('SELECT * FROM media_assets ORDER BY created_at DESC');
            foreach (($stMedia ? $stMedia->fetchAll(PDO::FETCH_ASSOC) : []) as $m) {
                $mediaAssets[] = [
                    'id'                => (string)$m['id'],
                    'editionId'         => $m['edition_id'] ?? $editionId,
                    'fileName'          => (string)$m['file_name'],
                    'url'               => (string)$m['url'],
                    'mimeType'          => (string)($m['mime_type'] ?? 'image/jpeg'),
                    'sizeBytes'         => isset($m['size_bytes']) ? (int)$m['size_bytes'] : null,
                    'altText'           => (string)$m['alt_text'],
                    'category'          => (string)($m['category'] ?? 'GALLERY'),
                    'sourceAttribution' => $m['source_attribution'] ?? null,
                    'licenseInfo'       => $m['license_info'] ?? null,
                    'createdAt'         => (string)($m['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
        }

        // Announcements
        $announcements = [];
        try {
            $stAnn = $pdo->prepare('SELECT * FROM announcements WHERE edition_id = :eid ORDER BY created_at DESC');
            $stAnn->execute([':eid' => $editionId]);
            foreach (($stAnn->fetchAll(PDO::FETCH_ASSOC) ?: []) as $a) {
                $announcements[] = [
                    'id'        => (string)$a['id'],
                    'editionId' => (string)$a['edition_id'],
                    'title'     => (string)$a['title'],
                    'message'   => (string)$a['message'],
                    'severity'  => (string)($a['severity'] ?? 'INFO'),
                    'status'    => (string)($a['status'] ?? 'PUBLISHED'),
                    'createdAt' => (string)($a['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
        }

        // Registration Categories
        $stCat = $pdo->prepare('SELECT * FROM registration_categories WHERE edition_id = :eid ORDER BY display_order ASC');
        $stCat->execute([':eid' => $editionId]);
        $registrationCategories = [];
        foreach (($stCat->fetchAll(PDO::FETCH_ASSOC) ?: []) as $c) {
            $registrationCategories[] = [
                'id'                  => (string)$c['id'],
                'editionId'           => (string)$c['edition_id'],
                'code'                => (string)$c['code'],
                'name'                => (string)$c['name'],
                'description'         => $c['description'] ?? null,
                'requiresApproval'    => !empty($c['requires_approval']),
                'requiresPayment'     => !empty($c['requires_payment']),
                'feeAmountInr'        => (int)($c['fee_amount_inr'] ?? 0),
                'isActive'            => !empty($c['is_active']),
                'displayOrder'        => (int)($c['display_order'] ?? 0),
                'opensAt'             => $c['opens_at'] ?? null,
                'closesAt'            => $c['closes_at'] ?? null,
                'confirmationMessage' => $c['confirmation_message'] ?? null,
            ];
        }

        // Recent content revisions
        $recentRevisions = [];
        try {
            $stRev = $pdo->query('SELECT * FROM content_revisions ORDER BY created_at DESC LIMIT 25');
            foreach (($stRev ? $stRev->fetchAll(PDO::FETCH_ASSOC) : []) as $rv) {
                $recentRevisions[] = [
                    'id'            => (string)$rv['id'],
                    'entityType'    => (string)$rv['entity_type'],
                    'entityId'      => (string)$rv['entity_id'],
                    'versionNumber' => (int)$rv['version_number'],
                    'changeSummary' => $rv['change_summary'] ?? null,
                    'createdAt'     => (string)($rv['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
        }

        jsonResponse([
            'edition'                => $edition,
            'sections'               => $sections,
            'programmeItems'         => $programmeItems,
            'partners'               => $partners,
            'faqItems'               => $faqItems,
            'mediaAssets'            => $mediaAssets,
            'announcements'          => $announcements,
            'registrationCategories' => $registrationCategories,
            'recentRevisions'        => $recentRevisions,
        ]);
    }

    // =========================================================================
    // POST: Create CMS Entity (programme, partner, faq, announcement, media)
    // =========================================================================
    if ($method === 'POST') {
        $input = readRequestInput();
        $entityType = strtolower(trim((string)($input['entityType'] ?? '')));
        $payload = isset($input['payload']) && is_array($input['payload']) ? $input['payload'] : $input;
        $now = nowSql();

        if ($entityType === 'programme') {
            $dayNumber = max(1, min(10, (int)($payload['dayNumber'] ?? 1)));
            $timeSlot = trim((string)($payload['timeSlot'] ?? '10:00 – 11:30 IST'));
            $title = trim((string)($payload['title'] ?? ''));
            $description = trim((string)($payload['description'] ?? ''));
            $category = trim((string)($payload['category'] ?? 'Plenary'));
            $venueName = trim((string)($payload['venueName'] ?? ''));
            $accessibilityServices = isset($payload['accessibilityServices']) && is_array($payload['accessibilityServices'])
                ? $payload['accessibilityServices']
                : [];
            $displayOrder = (int)($payload['displayOrder'] ?? 0);
            $status = strtoupper(trim((string)($payload['status'] ?? 'PUBLISHED')));

            if ($title === '' || $timeSlot === '') {
                jsonResponse(['error' => 'Programme title and timeSlot are required.'], 400);
            }

            $id = uuid4();
            $stmt = $pdo->prepare('
                INSERT INTO programme_items (
                    id, edition_id, day_number, time_slot, title, description, category,
                    venue_name, accessibility_services, display_order, status, created_at, updated_at
                ) VALUES (
                    :id, :eid, :day, :slot, :title, :desc, :cat, :venue, :a11y, :ord, :status, :now, :now
                )
            ');
            $stmt->execute([
                ':id'     => $id,
                ':eid'    => $editionId,
                ':day'    => $dayNumber,
                ':slot'   => $timeSlot,
                ':title'  => $title,
                ':desc'   => $description !== '' ? $description : null,
                ':cat'    => $category,
                ':venue'  => $venueName !== '' ? $venueName : null,
                ':a11y'   => jsonEncode($accessibilityServices),
                ':ord'    => $displayOrder,
                ':status' => $status,
                ':now'    => $now,
            ]);

            $created = [
                'id'                    => $id,
                'editionId'             => $editionId,
                'dayNumber'             => $dayNumber,
                'timeSlot'              => $timeSlot,
                'title'                 => $title,
                'description'           => $description !== '' ? $description : null,
                'category'              => $category,
                'venueName'             => $venueName !== '' ? $venueName : null,
                'accessibilityServices' => $accessibilityServices,
                'displayOrder'          => $displayOrder,
                'status'                => $status,
            ];

            pfgCmsRecordRevision($pdo, 'programme_item', $id, $created, (string)$user['id'], "Created programme item \"{$title}\"");
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_PROGRAMME_CREATED', 'resourceType' => 'programme_item', 'resourceId' => $id, 'metadata' => ['title' => $title]]);

            jsonResponse(['item' => $created], 201);
        }

        if ($entityType === 'partner') {
            $orgName = trim((string)($payload['organizationName'] ?? $payload['name'] ?? ''));
            $shortName = trim((string)($payload['shortName'] ?? ''));
            $websiteUrl = trim((string)($payload['websiteUrl'] ?? ''));
            $logoUrl = trim((string)($payload['logoUrl'] ?? ''));
            $logoAltText = trim((string)($payload['logoAltText'] ?? ($orgName . ' logo')));
            $description = trim((string)($payload['description'] ?? ''));
            $category = trim((string)($payload['category'] ?? 'Knowledge Partners'));
            $consentVerified = !empty($payload['consentVerified']);
            $displayOrder = (int)($payload['displayOrder'] ?? 0);
            $status = strtoupper(trim((string)($payload['status'] ?? 'DRAFT')));

            if ($orgName === '') {
                jsonResponse(['error' => 'Organization name is required.'], 400);
            }
            if ($websiteUrl !== '') {
                $safeWeb = pfgValidateSafeUrl($websiteUrl, false);
                if ($safeWeb === null) {
                    jsonResponse(['error' => 'Invalid partner websiteUrl. Only http:// and https:// URLs are permitted.'], 400);
                }
                $websiteUrl = $safeWeb;
            }
            if ($logoUrl !== '') {
                $safeLogo = pfgValidateSafeUrl($logoUrl, true);
                if ($safeLogo === null) {
                    jsonResponse(['error' => 'Invalid partner logoUrl. Only http://, https://, or root-relative / paths are permitted.'], 400);
                }
                $logoUrl = $safeLogo;
            }
            if ($status === 'PUBLISHED' && !$consentVerified) {
                jsonResponse(['error' => 'Partner cannot be published without verified partner consent.'], 400);
            }

            $orgId = uuid4();
            $stOrg = $pdo->prepare('
                INSERT INTO organizations (id, name, short_name, website_url, logo_url, logo_alt_text, description, created_at)
                VALUES (:id, :name, :short, :web, :logo, :alt, :desc, :now)
            ');
            $stOrg->execute([
                ':id'    => $orgId,
                ':name'  => $orgName,
                ':short' => $shortName !== '' ? $shortName : null,
                ':web'   => $websiteUrl !== '' ? $websiteUrl : null,
                ':logo'  => $logoUrl !== '' ? $logoUrl : null,
                ':alt'   => $logoAltText,
                ':desc'  => $description !== '' ? $description : null,
                ':now'   => $now,
            ]);

            $partnerId = uuid4();
            $stPart = $pdo->prepare('
                INSERT INTO partners (id, edition_id, organization_id, category, consent_verified, display_order, status, created_at, updated_at)
                VALUES (:id, :eid, :oid, :cat, :consent, :ord, :status, :now, :now)
            ');
            $stPart->execute([
                ':id'      => $partnerId,
                ':eid'     => $editionId,
                ':oid'     => $orgId,
                ':cat'     => $category,
                ':consent' => $consentVerified ? 1 : 0,
                ':ord'     => $displayOrder,
                ':status'  => $status,
                ':now'     => $now,
            ]);

            $created = [
                'id'               => $partnerId,
                'editionId'        => $editionId,
                'organizationId'   => $orgId,
                'organizationName' => $orgName,
                'websiteUrl'       => $websiteUrl !== '' ? $websiteUrl : null,
                'logoUrl'          => $logoUrl !== '' ? $logoUrl : null,
                'category'         => $category,
                'consentVerified'  => $consentVerified,
                'displayOrder'     => $displayOrder,
                'status'           => $status,
            ];

            pfgCmsRecordRevision($pdo, 'partner', $partnerId, $created, (string)$user['id'], "Added partner \"{$orgName}\" ({$status})");
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_PARTNER_CREATED', 'resourceType' => 'partner', 'resourceId' => $partnerId, 'metadata' => ['organizationName' => $orgName, 'consentVerified' => $consentVerified, 'status' => $status]]);

            jsonResponse(['item' => $created], 201);
        }

        if ($entityType === 'faq') {
            $category = trim((string)($payload['category'] ?? 'General')) ?: 'General';
            $question = trim((string)($payload['question'] ?? ''));
            $answer = trim((string)($payload['answer'] ?? ''));
            $displayOrder = (int)($payload['displayOrder'] ?? 0);
            $status = strtoupper(trim((string)($payload['status'] ?? 'PUBLISHED')));

            if ($question === '' || $answer === '') {
                jsonResponse(['error' => 'FAQ question and answer are required.'], 400);
            }

            $id = uuid4();
            $stmt = $pdo->prepare('
                INSERT INTO faq_items (id, edition_id, category, question, answer, display_order, status, updated_at)
                VALUES (:id, :eid, :cat, :q, :a, :ord, :status, :now)
            ');
            $stmt->execute([
                ':id'     => $id,
                ':eid'    => $editionId,
                ':cat'    => $category,
                ':q'      => $question,
                ':a'      => $answer,
                ':ord'    => $displayOrder,
                ':status' => $status,
                ':now'    => $now,
            ]);

            $created = [
                'id'           => $id,
                'editionId'    => $editionId,
                'category'     => $category,
                'question'     => $question,
                'answer'       => $answer,
                'displayOrder' => $displayOrder,
                'status'       => $status,
            ];

            pfgCmsRecordRevision($pdo, 'faq_item', $id, $created, (string)$user['id'], 'Created FAQ');
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_FAQ_CREATED', 'resourceType' => 'faq_item', 'resourceId' => $id]);

            jsonResponse(['item' => $created], 201);
        }

        if ($entityType === 'media' || $entityType === 'gallery') {
            $fileName = trim((string)($payload['fileName'] ?? basename((string)($payload['url'] ?? 'venue.jpg'))));
            $url = trim((string)($payload['url'] ?? ''));
            $altText = trim((string)($payload['altText'] ?? ''));
            $category = strtoupper(trim((string)($payload['category'] ?? 'GALLERY')));
            $mimeType = trim((string)($payload['mimeType'] ?? 'image/jpeg'));

            if ($url === '' || $altText === '') {
                jsonResponse(['error' => 'Media URL and accessible altText are required.'], 400);
            }
            $safeMediaUrl = pfgValidateSafeUrl($url, true);
            if ($safeMediaUrl === null || $safeMediaUrl === '') {
                jsonResponse(['error' => 'Invalid media URL. Only http://, https://, or root-relative / paths are permitted.'], 400);
            }
            $url = $safeMediaUrl;

            $id = uuid4();
            $stmt = $pdo->prepare('
                INSERT INTO media_assets (id, edition_id, file_name, url, mime_type, alt_text, category, uploaded_by, created_at)
                VALUES (:id, :eid, :fname, :url, :mime, :alt, :cat, :uid, :now)
            ');
            $stmt->execute([
                ':id'    => $id,
                ':eid'   => $editionId,
                ':fname' => $fileName,
                ':url'   => $url,
                ':mime'  => $mimeType,
                ':alt'   => $altText,
                ':cat'   => $category,
                ':uid'   => $user['id'],
                ':now'   => $now,
            ]);

            $created = [
                'id'        => $id,
                'editionId' => $editionId,
                'fileName'  => $fileName,
                'url'       => $url,
                'mimeType'  => $mimeType,
                'altText'   => $altText,
                'category'  => $category,
                'createdAt' => $now,
            ];

            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_MEDIA_CREATED', 'resourceType' => 'media_asset', 'resourceId' => $id]);
            jsonResponse(['item' => $created], 201);
        }

        if ($entityType === 'announcement') {
            $title = trim((string)($payload['title'] ?? ''));
            $message = trim((string)($payload['message'] ?? ''));
            $severity = strtoupper(trim((string)($payload['severity'] ?? 'INFO')));
            $status = strtoupper(trim((string)($payload['status'] ?? 'PUBLISHED')));

            if ($title === '' || $message === '') {
                jsonResponse(['error' => 'Announcement title and message are required.'], 400);
            }

            $id = uuid4();
            $stmt = $pdo->prepare('
                INSERT INTO announcements (id, edition_id, title, message, severity, status, created_by, created_at)
                VALUES (:id, :eid, :title, :msg, :sev, :status, :uid, :now)
            ');
            $stmt->execute([
                ':id'     => $id,
                ':eid'    => $editionId,
                ':title'  => $title,
                ':msg'    => $message,
                ':sev'    => $severity,
                ':status' => $status,
                ':uid'    => $user['id'],
                ':now'    => $now,
            ]);

            $created = [
                'id'        => $id,
                'editionId' => $editionId,
                'title'     => $title,
                'message'   => $message,
                'severity'  => $severity,
                'status'    => $status,
                'createdAt' => $now,
            ];

            pfgCmsRecordRevision($pdo, 'announcement', $id, $created, (string)$user['id'], "Created announcement \"{$title}\"");
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_ANNOUNCEMENT_CREATED', 'resourceType' => 'announcement', 'resourceId' => $id]);

            jsonResponse(['item' => $created], 201);
        }

        jsonResponse(['error' => 'Unsupported CMS entityType for creation.'], 400);
    }

    // =========================================================================
    // PATCH: Update CMS Entity (section, programme, partner, faq, announcement, category, media)
    // =========================================================================
    if ($method === 'PATCH') {
        $input = readRequestInput();
        $entityType = strtolower(trim((string)($input['entityType'] ?? '')));
        $id = trim((string)($input['id'] ?? ''));
        $payload = isset($input['payload']) && is_array($input['payload']) ? $input['payload'] : $input;
        $now = nowSql();

        if ($entityType === '' || $id === '') {
            jsonResponse(['error' => 'entityType and id are required.'], 400);
        }

        if ($entityType === 'section') {
            $st = $pdo->prepare('SELECT * FROM cms_sections WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Section not found.'], 404);
            }

            $title = array_key_exists('title', $payload) ? (string)$payload['title'] : $existing['title'];
            $subtitle = array_key_exists('subtitle', $payload) ? (string)$payload['subtitle'] : $existing['subtitle'];
            $body = array_key_exists('body', $payload) ? (string)$payload['body'] : $existing['body'];
            $status = isset($payload['status']) ? strtoupper(trim((string)$payload['status'])) : $existing['status'];
            $dataJson = array_key_exists('data', $payload) ? jsonEncode($payload['data']) : $existing['data'];

            $up = $pdo->prepare('
                UPDATE cms_sections
                SET title = :title,
                    subtitle = :subtitle,
                    body = :body,
                    status = :status,
                    data = :data,
                    updated_by = :uid,
                    updated_at = :now
                WHERE id = :id
            ');
            $up->execute([
                ':title'    => $title,
                ':subtitle' => $subtitle,
                ':body'     => $body,
                ':status'   => $status,
                ':data'     => $dataJson,
                ':uid'      => $user['id'],
                ':now'      => $now,
                ':id'       => $id,
            ]);

            $updated = [
                'id'         => $id,
                'pageId'     => $existing['page_id'],
                'sectionKey' => $existing['section_key'],
                'title'      => $title,
                'subtitle'   => $subtitle,
                'body'       => $body,
                'status'     => $status,
                'updatedAt'  => $now,
            ];

            pfgCmsRecordRevision($pdo, 'cms_section', $id, $updated, (string)$user['id'], "Updated section \"{$existing['section_key']}\"");
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_SECTION_UPDATED', 'resourceType' => 'cms_section', 'resourceId' => $id, 'metadata' => ['sectionKey' => $existing['section_key']]]);

            jsonResponse(['item' => $updated]);
        }

        if ($entityType === 'programme') {
            $st = $pdo->prepare('SELECT * FROM programme_items WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Programme item not found.'], 404);
            }

            $dayNumber = isset($payload['dayNumber']) ? (int)$payload['dayNumber'] : (int)$existing['day_number'];
            $timeSlot = isset($payload['timeSlot']) ? trim((string)$payload['timeSlot']) : $existing['time_slot'];
            $title = isset($payload['title']) ? trim((string)$payload['title']) : $existing['title'];
            $description = array_key_exists('description', $payload) ? (string)$payload['description'] : $existing['description'];
            $category = isset($payload['category']) ? trim((string)$payload['category']) : $existing['category'];
            $venueName = array_key_exists('venueName', $payload) ? (string)$payload['venueName'] : $existing['venue_name'];
            $status = isset($payload['status']) ? strtoupper(trim((string)$payload['status'])) : $existing['status'];

            $up = $pdo->prepare('
                UPDATE programme_items
                SET day_number = :day,
                    time_slot = :slot,
                    title = :title,
                    description = :desc,
                    category = :cat,
                    venue_name = :venue,
                    status = :status,
                    updated_at = :now
                WHERE id = :id
            ');
            $up->execute([
                ':day'    => $dayNumber,
                ':slot'   => $timeSlot,
                ':title'  => $title,
                ':desc'   => $description,
                ':cat'    => $category,
                ':venue'  => $venueName,
                ':status' => $status,
                ':now'    => $now,
                ':id'     => $id,
            ]);

            $updated = [
                'id'          => $id,
                'dayNumber'   => $dayNumber,
                'timeSlot'    => $timeSlot,
                'title'       => $title,
                'description' => $description,
                'category'    => $category,
                'venueName'   => $venueName,
                'status'      => $status,
                'updatedAt'   => $now,
            ];

            pfgCmsRecordRevision($pdo, 'programme_item', $id, $updated, (string)$user['id'], "Updated programme item \"{$title}\"");
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_PROGRAMME_UPDATED', 'resourceType' => 'programme_item', 'resourceId' => $id]);

            jsonResponse(['item' => $updated]);
        }

        if ($entityType === 'partner') {
            $st = $pdo->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Partner not found.'], 404);
            }

            $nextConsent = array_key_exists('consentVerified', $payload)
                ? !empty($payload['consentVerified'])
                : !empty($existing['consent_verified']);
            $nextStatus = isset($payload['status'])
                ? strtoupper(trim((string)$payload['status']))
                : (string)$existing['status'];

            if ($nextStatus === 'PUBLISHED' && !$nextConsent) {
                jsonResponse(['error' => 'Partner consent must be verified before publishing.'], 400);
            }

            if (!empty($payload['organizationName']) || array_key_exists('websiteUrl', $payload)) {
                $stOrg = $pdo->prepare('SELECT * FROM organizations WHERE id = :oid LIMIT 1');
                $stOrg->execute([':oid' => $existing['organization_id']]);
                $org = $stOrg->fetch(PDO::FETCH_ASSOC);
                if ($org) {
                    $nextWebUrl = $org['website_url'];
                    if (array_key_exists('websiteUrl', $payload)) {
                        $rawWeb = trim((string)$payload['websiteUrl']);
                        if ($rawWeb === '') {
                            $nextWebUrl = null;
                        } else {
                            $safeWeb = pfgValidateSafeUrl($rawWeb, false);
                            if ($safeWeb === null) {
                                jsonResponse(['error' => 'Invalid partner websiteUrl. Only http:// and https:// URLs are permitted.'], 400);
                            }
                            $nextWebUrl = $safeWeb;
                        }
                    }
                    $upOrg = $pdo->prepare('UPDATE organizations SET name = :name, website_url = :web WHERE id = :oid');
                    $upOrg->execute([
                        ':name' => !empty($payload['organizationName']) ? trim((string)$payload['organizationName']) : $org['name'],
                        ':web'  => $nextWebUrl,
                        ':oid'  => $existing['organization_id'],
                    ]);
                }
            }

            $nextCat = isset($payload['category']) ? trim((string)$payload['category']) : $existing['category'];
            $nextOrder = isset($payload['displayOrder']) ? (int)$payload['displayOrder'] : (int)$existing['display_order'];

            $up = $pdo->prepare('
                UPDATE partners
                SET consent_verified = :consent,
                    status = :status,
                    category = :cat,
                    display_order = :ord,
                    updated_at = :now
                WHERE id = :id
            ');
            $up->execute([
                ':consent' => $nextConsent ? 1 : 0,
                ':status'  => $nextStatus,
                ':cat'     => $nextCat,
                ':ord'     => $nextOrder,
                ':now'     => $now,
                ':id'      => $id,
            ]);

            $updated = [
                'id'              => $id,
                'organizationId'  => $existing['organization_id'],
                'category'        => $nextCat,
                'consentVerified' => $nextConsent,
                'status'          => $nextStatus,
                'displayOrder'    => $nextOrder,
                'updatedAt'       => $now,
            ];

            pfgCmsRecordRevision($pdo, 'partner', $id, $updated, (string)$user['id'], "Updated partner status={$nextStatus}, consentVerified=" . ($nextConsent ? 'true' : 'false'));
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_PARTNER_UPDATED', 'resourceType' => 'partner', 'resourceId' => $id, 'metadata' => ['status' => $nextStatus, 'consentVerified' => $nextConsent]]);

            jsonResponse(['item' => $updated]);
        }

        if ($entityType === 'faq') {
            $st = $pdo->prepare('SELECT * FROM faq_items WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'FAQ not found.'], 404);
            }

            $cat = isset($payload['category']) ? trim((string)$payload['category']) : $existing['category'];
            $q = isset($payload['question']) ? trim((string)$payload['question']) : $existing['question'];
            $a = isset($payload['answer']) ? trim((string)$payload['answer']) : $existing['answer'];
            $status = isset($payload['status']) ? strtoupper(trim((string)$payload['status'])) : $existing['status'];

            $up = $pdo->prepare('
                UPDATE faq_items
                SET category = :cat, question = :q, answer = :a, status = :status, updated_at = :now
                WHERE id = :id
            ');
            $up->execute([
                ':cat'    => $cat,
                ':q'      => $q,
                ':a'      => $a,
                ':status' => $status,
                ':now'    => $now,
                ':id'     => $id,
            ]);

            $updated = [
                'id'        => $id,
                'category'  => $cat,
                'question'  => $q,
                'answer'    => $a,
                'status'    => $status,
                'updatedAt' => $now,
            ];

            pfgCmsRecordRevision($pdo, 'faq_item', $id, $updated, (string)$user['id'], 'Updated FAQ');
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_FAQ_UPDATED', 'resourceType' => 'faq_item', 'resourceId' => $id]);

            jsonResponse(['item' => $updated]);
        }

        if ($entityType === 'category') {
            $st = $pdo->prepare('SELECT * FROM registration_categories WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Registration category not found.'], 404);
            }

            $isActive = array_key_exists('isActive', $payload) ? (!empty($payload['isActive']) ? 1 : 0) : (int)$existing['is_active'];
            $fee = array_key_exists('feeAmountInr', $payload) ? max(0, (int)$payload['feeAmountInr']) : (int)$existing['fee_amount_inr'];
            $reqPay = array_key_exists('requiresPayment', $payload) ? (!empty($payload['requiresPayment']) ? 1 : 0) : (int)$existing['requires_payment'];
            $reqApp = array_key_exists('requiresApproval', $payload) ? (!empty($payload['requiresApproval']) ? 1 : 0) : (int)$existing['requires_approval'];

            $up = $pdo->prepare('
                UPDATE registration_categories
                SET is_active = :active,
                    fee_amount_inr = :fee,
                    requires_payment = :rpay,
                    requires_approval = :rapp
                WHERE id = :id
            ');
            $up->execute([
                ':active' => $isActive,
                ':fee'    => $fee,
                ':rpay'   => $reqPay,
                ':rapp'   => $reqApp,
                ':id'     => $id,
            ]);

            $updated = [
                'id'               => $id,
                'code'             => $existing['code'],
                'name'             => $existing['name'],
                'isActive'         => (bool)$isActive,
                'feeAmountInr'     => $fee,
                'requiresPayment'  => (bool)$reqPay,
                'requiresApproval' => (bool)$reqApp,
            ];

            pfgCmsRecordRevision($pdo, 'registration_category', $id, $updated, (string)$user['id'], "Updated category {$existing['code']}");
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_CATEGORY_UPDATED', 'resourceType' => 'registration_category', 'resourceId' => $id, 'metadata' => $updated]);

            jsonResponse(['item' => $updated]);
        }

        if ($entityType === 'media' || $entityType === 'gallery') {
            $st = $pdo->prepare('SELECT * FROM media_assets WHERE id = :id LIMIT 1');
            $st->execute([':id' => $id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                jsonResponse(['error' => 'Media asset not found.'], 404);
            }
            $altText = isset($payload['altText']) ? trim((string)$payload['altText']) : $existing['alt_text'];
            $category = isset($payload['category']) ? trim((string)$payload['category']) : $existing['category'];
            $up = $pdo->prepare('UPDATE media_assets SET alt_text = :alt, category = :cat WHERE id = :id');
            $up->execute([':alt' => $altText, ':cat' => $category, ':id' => $id]);
            $updatedMedia = ['id' => $id, 'altText' => $altText, 'category' => $category];
            pfgCmsRecordRevision($pdo, 'media_asset', $id, $updatedMedia, (string)$user['id'], 'Updated media asset metadata');
            logAudit(['actorUserId' => $user['id'], 'action' => 'CMS_MEDIA_UPDATED', 'resourceType' => 'media_asset', 'resourceId' => $id, 'metadata' => $updatedMedia]);
            jsonResponse(['item' => $updatedMedia]);
        }

        jsonResponse(['error' => 'Unsupported CMS entityType for update.'], 400);
    }

    // =========================================================================
    // DELETE: Remove CMS Entity (programme, partner, faq, announcement, media)
    // =========================================================================
    if ($method === 'DELETE') {
        $input = readRequestInput();
        $entityType = strtolower(trim((string)($_GET['entityType'] ?? $input['entityType'] ?? '')));
        $id = trim((string)($_GET['id'] ?? $input['id'] ?? ''));

        if ($entityType === '' || $id === '') {
            jsonResponse(['error' => 'entityType and id are required.'], 400);
        }

        $tableMap = [
            'programme'    => ['table' => 'programme_items', 'revType' => 'programme_item', 'auditAction' => 'CMS_PROGRAMME_DELETED'],
            'partner'      => ['table' => 'partners',        'revType' => 'partner',        'auditAction' => 'CMS_PARTNER_DELETED'],
            'faq'          => ['table' => 'faq_items',       'revType' => 'faq_item',       'auditAction' => 'CMS_FAQ_DELETED'],
            'announcement' => ['table' => 'announcements',   'revType' => 'announcement',   'auditAction' => 'CMS_ANNOUNCEMENT_DELETED'],
            'media'        => ['table' => 'media_assets',    'revType' => 'media_asset',    'auditAction' => 'CMS_MEDIA_DELETED'],
            'gallery'      => ['table' => 'media_assets',    'revType' => 'media_asset',    'auditAction' => 'CMS_MEDIA_DELETED'],
        ];

        if (!isset($tableMap[$entityType])) {
            jsonResponse(['error' => 'Unsupported entityType for deletion.'], 400);
        }

        $meta = $tableMap[$entityType];
        $st = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['error' => 'Record not found.'], 404);
        }

        $del = $pdo->prepare("DELETE FROM {$meta['table']} WHERE id = :id");
        $del->execute([':id' => $id]);

        pfgCmsRecordRevision($pdo, $meta['revType'], $id, ['deleted' => $existing], (string)$user['id'], "Deleted {$meta['revType']} {$id}");
        logAudit(['actorUserId' => $user['id'], 'action' => $meta['auditAction'], 'resourceType' => $meta['revType'], 'resourceId' => $id]);

        jsonResponse(['deleted' => true]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('/api/admin/cms.php error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to process CMS request.'], 500);
}
