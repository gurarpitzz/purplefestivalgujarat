<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Safe Idempotent Reference Data Seeder (Phase 3C)
 *
 * Strict Operational Safety Rules:
 * - Safe to run repeatedly on fresh OR upgraded databases.
 * - Uses INSERT IGNORE / insert-if-missing semantics ONLY.
 * - NEVER overwrites organizer-modified CMS pages/sections, FAQs, system settings,
 *   partner visibility/status, or registration category configurations.
 * - NEVER auto-publishes unconfirmed partners or overwrites partner consent flags.
 * - NEVER creates default admin/superadmin accounts or weak passwords.
 * - Clearly tags the 3 primary participant categories (ATTENDEE, SLI, VOLUNTEER)
 *   as `PRIMARY` and preserves historical categories as `LEGACY` without deletion.
 * - Removes outdated QR entry-pass instructions from newly seeded FAQs & templates (D-01).
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/migrations.php';
require_once dirname(__DIR__) . '/includes/registration-id.php';

if (!function_exists('pfgRunSafeReferenceSeed')) {
    function pfgRunSafeReferenceSeed(PDO $pdo): array
    {
        $now = nowSql();
        $inserted = [
            'roles'                  => 0,
            'permissions'            => 0,
            'role_permissions'       => 0,
            'festival_editions'      => 0,
            'venues'                 => 0,
            'checkin_stations'       => 0,
            'disability_categories'  => 0,
            'accommodation_types'    => 0,
            'registration_categories'=> 0,
            'organizations'          => 0,
            'partners'               => 0,
            'programme_items'        => 0,
            'cms_pages'              => 0,
            'cms_sections'           => 0,
            'faq_items'              => 0,
            'notification_templates' => 0,
            'system_settings'        => 0,
            'media_assets'           => 0,
        ];

        // 1. Roles (4 core roles: SUPERADMIN, ADMIN, RGST, PARTICIPANT)
        $roles = [
            ['id' => '10000000-0000-4000-8000-000000000001', 'code' => 'SUPERADMIN',  'name' => 'Superadmin',              'description' => 'Full system, security, CMS, administrative accounts, role profiles, and analytics authority.'],
            ['id' => '10000000-0000-4000-8000-000000000002', 'code' => 'ADMIN',       'name' => 'Festival Admin',          'description' => 'Day-to-day registration, volunteer/SLI review, accommodations, attendance, and reporting.'],
            ['id' => '10000000-0000-4000-8000-000000000003', 'code' => 'RGST',        'name' => 'Registration Desk Staff', 'description' => 'Restricted event-day manual attendance verification and check-in confirmation.'],
            ['id' => '10000000-0000-4000-8000-000000000004', 'code' => 'PARTICIPANT', 'name' => 'Participant',             'description' => 'Attendee, SLI professional, or Volunteer participant account.'],
        ];
        $roleStmt = $pdo->prepare('INSERT IGNORE INTO roles (id, code, name, description, created_at) VALUES (:id, :code, :name, :description, :now)');
        foreach ($roles as $r) {
            $roleStmt->execute([
                ':id'          => $r['id'],
                ':code'        => $r['code'],
                ':name'        => $r['name'],
                ':description' => $r['description'],
                ':now'         => $now,
            ]);
            $inserted['roles'] += $roleStmt->rowCount();
        }

        $roleMap = [];
        foreach ($pdo->query('SELECT id, code FROM roles')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roleMap[$row['code']] = $row['id'];
        }

        // 2. Permissions
        $permValues = array_values(PERMISSIONS);
        $permStmt = $pdo->prepare('INSERT IGNORE INTO permissions (id, code, description, created_at) VALUES (:id, :code, :description, :now)');
        foreach ($permValues as $idx => $code) {
            $permStmt->execute([
                ':id'          => sprintf('20000000-0000-4000-8000-%012d', $idx + 1),
                ':code'        => $code,
                ':description' => $code,
                ':now'         => $now,
            ]);
            $inserted['permissions'] += $permStmt->rowCount();
        }

        $permMap = [];
        foreach ($pdo->query('SELECT id, code FROM permissions')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $permMap[$row['code']] = $row['id'];
        }

        // 3. Default Role Permissions (INSERT IGNORE preserves any custom Superadmin edits)
        $rpStmt = $pdo->prepare('INSERT IGNORE INTO role_permissions (id, role_id, permission_id) VALUES (:id, :role_id, :permission_id)');
        $rpCounter = 1;
        foreach (DEFAULT_ROLE_PERMISSIONS as $roleCode => $permCodes) {
            $roleId = $roleMap[$roleCode] ?? null;
            if (!$roleId) {
                continue;
            }
            foreach ($permCodes as $pCode) {
                $permId = $permMap[$pCode] ?? null;
                if (!$permId) {
                    continue;
                }
                $rpStmt->execute([
                    ':id'            => sprintf('21000000-0000-4000-8000-%012d', $rpCounter++),
                    ':role_id'       => $roleId,
                    ':permission_id' => $permId,
                ]);
                $inserted['role_permissions'] += $rpStmt->rowCount();
            }
        }

        // 4. Active Festival Edition (gujarat-2027)
        $defaultEditionId = '00000000-0000-4000-8000-000000002027';
        $edStmt = $pdo->prepare("
            INSERT IGNORE INTO festival_editions
            (id, slug, name, tagline, city, state, start_date, end_date, registration_opens_at, registration_closes_at, attendance_policy, is_active, created_at, updated_at)
            VALUES (:id, :slug, :name, :tagline, 'Vadodara', 'Gujarat', '2027-01-21 09:00:00', '2027-01-23 20:30:00', '2026-11-01 00:00:00', '2027-01-20 23:59:59', 'ONCE_PER_DAY', 1, :now, :now)
        ");
        $edStmt->execute([
            ':id'      => $defaultEditionId,
            ':slug'    => FESTIVAL_EDITION_SLUG,
            ':name'    => FESTIVAL_NAME,
            ':tagline' => FESTIVAL_TAGLINE,
            ':now'     => $now,
        ]);
        $inserted['festival_editions'] += $edStmt->rowCount();

        $edLookup = $pdo->prepare('SELECT id FROM festival_editions WHERE slug = :slug LIMIT 1');
        $edLookup->execute([':slug' => FESTIVAL_EDITION_SLUG]);
        $editionId = (string)($edLookup->fetchColumn() ?: $defaultEditionId);

        // 5. Venues (only seed when table is empty so organizer venue edits are never overwritten)
        $samaVenueId = '30000000-0000-4000-8000-000000000001';
        $msuVenueId = '30000000-0000-4000-8000-000000000002';
        if ((int)$pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn() === 0) {
            $vStmt = $pdo->prepare('
                INSERT IGNORE INTO venues (id, edition_id, name, address, confirmation_status, accessibility_features, capacity, created_at)
                VALUES (:id, :edition_id, :name, :address, :status, :features, :capacity, :now)
            ');
            $venuesSeed = [
                ['id' => $samaVenueId, 'name' => 'Sama Indoor Stadium', 'address' => 'Sama, Vadodara, Gujarat', 'status' => 'CONFIRMED', 'features' => jsonEncode(['Step-free entry', 'Wheelchair-accessible courts', 'Accessible restrooms', 'Para-sports ready']), 'capacity' => 2500],
                ['id' => $msuVenueId, 'name' => 'The Maharaja Sayajirao University of Baroda (FSW & Dept. of Physical Education)', 'address' => 'Pratapgunj, Vadodara, Gujarat', 'status' => 'COMMITTED', 'features' => jsonEncode(['Campus access audit in progress', 'Accessible seminar halls', 'Sports pavilion']), 'capacity' => 3000],
                ['id' => '30000000-0000-4000-8000-000000000003', 'name' => 'GMERS Medical College, Gotri', 'address' => 'Gotri, Vadodara, Gujarat', 'status' => 'PENDING', 'features' => jsonEncode(['Pending final venue confirmation']), 'capacity' => 1000],
            ];
            foreach ($venuesSeed as $v) {
                $vStmt->execute([
                    ':id'         => $v['id'],
                    ':edition_id' => $editionId,
                    ':name'       => $v['name'],
                    ':address'    => $v['address'],
                    ':status'     => $v['status'],
                    ':features'   => $v['features'],
                    ':capacity'   => $v['capacity'],
                    ':now'        => $now,
                ]);
                $inserted['venues'] += $vStmt->rowCount();
            }
        }

        // 6. Registration Desk / Check-in Stations (insert-if-empty)
        if ((int)$pdo->query('SELECT COUNT(*) FROM checkin_stations')->fetchColumn() === 0) {
            $stStmt = $pdo->prepare('
                INSERT IGNORE INTO checkin_stations (id, edition_id, venue_id, name, gate_code, is_active, created_at)
                VALUES (:id, :edition_id, :venue_id, :name, :gate_code, 1, :now)
            ');
            $stations = [
                ['id' => '31000000-0000-4000-8000-000000000001', 'venue_id' => $samaVenueId, 'name' => 'Sama Indoor Stadium — Main Registration Desk A', 'gate_code' => 'SAMA-A'],
                ['id' => '31000000-0000-4000-8000-000000000002', 'venue_id' => $samaVenueId, 'name' => 'Sama Indoor Stadium — Accessible Priority Desk', 'gate_code' => 'SAMA-ACC'],
                ['id' => '31000000-0000-4000-8000-000000000003', 'venue_id' => $msuVenueId,  'name' => 'MSU Baroda — Central Registration Desk',          'gate_code' => 'MSU-MAIN'],
            ];
            foreach ($stations as $st) {
                $stStmt->execute([
                    ':id'         => $st['id'],
                    ':edition_id' => $editionId,
                    ':venue_id'   => $st['venue_id'],
                    ':name'       => $st['name'],
                    ':gate_code'  => $st['gate_code'],
                    ':now'        => $now,
                ]);
                $inserted['checkin_stations'] += $stStmt->rowCount();
            }
        }

        // 7. All 21 RPwD Act 2016 Disability Categories
        $disStmt = $pdo->prepare('
            INSERT IGNORE INTO disability_categories (id, code, name, rpwd_group, description, accommodation_examples, display_order)
            VALUES (:id, :code, :name, :rpwd_group, :description, :examples, :display_order)
        ');
        foreach (RPWD_21_DISABILITIES as $i => $d) {
            $disStmt->execute([
                ':id'            => sprintf('40000000-0000-4000-8000-%012d', $i + 1),
                ':code'          => $d['code'],
                ':name'          => $d['name'],
                ':rpwd_group'    => $d['rpwdGroup'],
                ':description'   => $d['description'],
                ':examples'      => jsonEncode($d['accommodationExamples']),
                ':display_order' => $i + 1,
            ]);
            $inserted['disability_categories'] += $disStmt->rowCount();
        }

        // 8. 13 Accessibility Accommodation Types
        $accStmt = $pdo->prepare('
            INSERT IGNORE INTO accommodation_types (id, code, label, is_active, display_order)
            VALUES (:id, :code, :label, 1, :display_order)
        ');
        foreach (ACCOMMODATION_TYPES_SEED as $i => $a) {
            $accStmt->execute([
                ':id'            => sprintf('41000000-0000-4000-8000-%012d', $i + 1),
                ':code'          => $a['code'],
                ':label'         => $a['label'],
                ':display_order' => $i + 1,
            ]);
            $inserted['accommodation_types'] += $accStmt->rowCount();
        }

        // 9. Registration Categories (3 PRIMARY: ATTENDEE, SLI, VOLUNTEER + 5 preserved LEGACY rows)
        $hasTierCol = pfgDbColumnExists($pdo, 'registration_categories', 'category_tier');
        $catSeed = [
            ['code' => 'ATTENDEE',      'tier' => 'PRIMARY', 'name' => 'General Attendee',                                 'description' => 'Open festival access to cultural performances, exhibitions, para-sports, and inclusive sessions.',                                            'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 1],
            ['code' => 'DELEGATE',      'tier' => 'LEGACY',  'name' => 'Delegate (3-Day Conference & All-Access)',         'description' => 'Historical delegate classification retained for backward compatibility pending organizer decision D-06.',                                      'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 2],
            ['code' => 'VOLUNTEER',     'tier' => 'PRIMARY', 'name' => 'Festival Volunteer',                               'description' => 'Support accessibility desks, stage anchoring, para-sports, and participant hospitality. Requires organizer review.',                           'requires_approval' => 1, 'requires_payment' => 0, 'fee' => 0, 'order' => 3],
            ['code' => 'SLI',           'tier' => 'PRIMARY', 'name' => 'SLI / Indian Sign Language Professional',          'description' => 'Qualified Indian Sign Language interpreters supporting plenaries, panels, workshops, and cultural stages. Requires verification.',            'requires_approval' => 1, 'requires_payment' => 0, 'fee' => 0, 'order' => 4],
            ['code' => 'STUDENT',       'tier' => 'LEGACY',  'name' => 'Student Participant',                              'description' => 'Historical student classification preserved for legacy registration compatibility.',                                                            'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 5],
            ['code' => 'PROFESSIONAL',  'tier' => 'LEGACY',  'name' => 'Professional / Educator / Practitioner',           'description' => 'Historical professional classification preserved for legacy registration compatibility.',                                                       'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 6],
            ['code' => 'INSTITUTIONAL', 'tier' => 'LEGACY',  'name' => 'Institutional Representative',                     'description' => 'Historical institutional classification preserved for legacy registration compatibility.',                                                      'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 7],
            ['code' => 'ALLY',          'tier' => 'LEGACY',  'name' => 'Ally / Supporter',                                 'description' => 'Historical ally/supporter classification preserved for legacy registration compatibility.',                                                     'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 8],
        ];
        if ($hasTierCol) {
            $catStmt = $pdo->prepare('
                INSERT IGNORE INTO registration_categories
                (id, edition_id, code, category_tier, name, description, requires_approval, requires_payment, fee_amount_inr, is_active, display_order, created_at)
                VALUES (:id, :edition_id, :code, :tier, :name, :description, :requires_approval, :requires_payment, :fee, 1, :display_order, :now)
            ');
        } else {
            $catStmt = $pdo->prepare('
                INSERT IGNORE INTO registration_categories
                (id, edition_id, code, name, description, requires_approval, requires_payment, fee_amount_inr, is_active, display_order, created_at)
                VALUES (:id, :edition_id, :code, :name, :description, :requires_approval, :requires_payment, :fee, 1, :display_order, :now)
            ');
        }
        foreach ($catSeed as $i => $c) {
            $params = [
                ':id'                => sprintf('50000000-0000-4000-8000-%012d', $i + 1),
                ':edition_id'        => $editionId,
                ':code'              => $c['code'],
                ':name'              => $c['name'],
                ':description'       => $c['description'],
                ':requires_approval' => $c['requires_approval'],
                ':requires_payment'  => $c['requires_payment'],
                ':fee'               => $c['fee'],
                ':display_order'     => $c['order'],
                ':now'               => $now,
            ];
            if ($hasTierCol) {
                $params[':tier'] = $c['tier'];
            }
            $catStmt->execute($params);
            $inserted['registration_categories'] += $catStmt->rowCount();
        }

        // 10. Organizations & Brochure Partners (INSERT IGNORE ONLY — NEVER overwrites organizer edits/status!)
        if ((int)$pdo->query('SELECT COUNT(*) FROM partners')->fetchColumn() === 0) {
            $partnerDefs = [
                ['org_id' => '60000000-0000-4000-8000-000000000004', 'part_id' => '61000000-0000-4000-8000-000000000004', 'name' => 'PRAANGAN, Vadodara', 'short_name' => 'PRAANGAN', 'logo_url' => '/assets/logos/praangan.jpg', 'logo_alt' => 'PRAANGAN, Vadodara logo', 'description' => 'A centre for children with special needs.', 'category' => 'Organisers', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 1],
                ['org_id' => '60000000-0000-4000-8000-000000000001', 'part_id' => '61000000-0000-4000-8000-000000000001', 'name' => 'Brotherhood', 'short_name' => 'Brotherhood', 'logo_url' => '/assets/logos/brotherhood-transparent.png', 'logo_alt' => 'Brotherhood official logo', 'description' => 'Rights and inclusion of persons with disabilities.', 'category' => 'Organisers', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 2],
                ['org_id' => '60000000-0000-4000-8000-000000000002', 'part_id' => '61000000-0000-4000-8000-000000000002', 'name' => 'Global Inclusive Education Network', 'short_name' => 'GIEN', 'logo_url' => '/assets/logos/gien.jpg', 'logo_alt' => 'Global Inclusive Education Network (GIEN) logo', 'description' => 'Making education inclusive for every child.', 'category' => 'Organisers', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 3],
                ['org_id' => '60000000-0000-4000-8000-000000000003', 'part_id' => '61000000-0000-4000-8000-000000000003', 'name' => 'Faculty of Social Work, MSU Baroda', 'short_name' => 'MSU Baroda (FSW)', 'logo_url' => '/assets/logos/msu-baroda.png', 'logo_alt' => 'The Maharaja Sayajirao University of Baroda crest', 'description' => 'Venue partner hosting plenary sessions, academic dialogues, and inclusive campus activities.', 'category' => 'Venue Partners', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 4],
                ['org_id' => '60000000-0000-4000-8000-000000000006', 'part_id' => '61000000-0000-4000-8000-000000000006', 'name' => 'Department of Physical Education, MSU Baroda', 'short_name' => 'MSU Baroda (DPE)', 'logo_url' => '/assets/logos/msu-baroda.png', 'logo_alt' => 'The Maharaja Sayajirao University of Baroda crest', 'description' => 'Venue partner coordinating adaptive athletics and Para Sports events.', 'category' => 'Venue Partners', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 5],
                ['org_id' => '60000000-0000-4000-8000-000000000007', 'part_id' => '61000000-0000-4000-8000-000000000007', 'name' => 'GMERS Medical College and Hospital, Gotri', 'short_name' => 'GMERS Gotri', 'logo_url' => '/assets/logos/gmers-gotri.png', 'logo_alt' => 'GMERS Medical College and Hospital, Gotri logo', 'description' => 'Venue and medical readiness partner supporting inclusive health and on-site care.', 'category' => 'Venue Partners', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 6],
                ['org_id' => '60000000-0000-4000-8000-000000000008', 'part_id' => '61000000-0000-4000-8000-000000000008', 'name' => 'State Commissioner for Persons with Disabilities, Goa', 'short_name' => 'SCPD Goa', 'logo_url' => '/assets/logos/scpd-goa.png', 'logo_alt' => 'State Commission for Persons with Disabilities - Goa logo', 'description' => 'Knowledge Partner sharing insights from India’s International Purple Fest in Goa.', 'category' => 'Knowledge Partners', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 7],
                ['org_id' => '60000000-0000-4000-8000-000000000009', 'part_id' => '61000000-0000-4000-8000-000000000009', 'name' => 'Department for Empowerment of Persons with Disabilities, Goa', 'short_name' => 'DEPwD Goa', 'logo_url' => '/assets/logos/depwd-goa.png', 'logo_alt' => 'Department for Empowerment of Persons with Disabilities, Government of Goa logo', 'description' => 'Knowledge Partner advancing cross-state accessibility standards and festival knowledge exchange.', 'category' => 'Knowledge Partners', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 8],
                ['org_id' => '60000000-0000-4000-8000-000000000005', 'part_id' => '61000000-0000-4000-8000-000000000005', 'name' => 'CDSA, Tata Institute of Social Sciences, Mumbai', 'short_name' => 'CDSA-TISS', 'logo_url' => '/assets/logos/cdsa-tiss.jpg', 'logo_alt' => 'CDSA, Tata Institute of Social Sciences, Mumbai logo', 'description' => 'Academic Partner advancing disability studies, social work research, and inclusive policy.', 'category' => 'Academic Partner', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 9],
                ['org_id' => '60000000-0000-4000-8000-000000000010', 'part_id' => '61000000-0000-4000-8000-000000000010', 'name' => 'Gujarat National Law University, Gandhinagar', 'short_name' => 'GNLU Gandhinagar', 'logo_url' => '/assets/logos/gnlu.png', 'logo_alt' => 'Gujarat National Law University, Gandhinagar emblem', 'description' => 'Legal Partner supporting disability rights, RPwD Act 2016 awareness, and legal empowerment.', 'category' => 'Legal Partner', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 10],
                ['org_id' => '60000000-0000-4000-8000-000000000011', 'part_id' => '61000000-0000-4000-8000-000000000011', 'name' => 'AYJNIHH, Mumbai', 'short_name' => 'AYJNISHD(D)', 'logo_url' => '/assets/logos/ayjnihh.png', 'logo_alt' => 'Ali Yavar Jung National Institute of Speech and Hearing Disabilities (AYJNIHH), Mumbai emblem', 'description' => 'Education Partner supporting speech, hearing, and Indian Sign Language inclusion.', 'category' => 'Education Partner', 'consent' => 1, 'status' => 'PUBLISHED', 'order' => 11],
            ];
            $orgIns = $pdo->prepare('
                INSERT IGNORE INTO organizations (id, name, short_name, logo_url, logo_alt_text, description, created_at)
                VALUES (:id, :name, :short_name, :logo_url, :logo_alt, :description, :now)
            ');
            $partIns = $pdo->prepare('
                INSERT IGNORE INTO partners (id, edition_id, organization_id, category, consent_verified, display_order, status, created_at, updated_at)
                VALUES (:id, :edition_id, :org_id, :category, :consent, :order, :status, :now, :now)
            ');
            foreach ($partnerDefs as $p) {
                $orgIns->execute([
                    ':id'          => $p['org_id'],
                    ':name'        => $p['name'],
                    ':short_name'  => $p['short_name'],
                    ':logo_url'    => $p['logo_url'],
                    ':logo_alt'    => $p['logo_alt'],
                    ':description' => $p['description'],
                    ':now'         => $now,
                ]);
                $inserted['organizations'] += $orgIns->rowCount();

                $partIns->execute([
                    ':id'         => $p['part_id'],
                    ':edition_id' => $editionId,
                    ':org_id'     => $p['org_id'],
                    ':category'   => $p['category'],
                    ':consent'    => $p['consent'],
                    ':order'      => $p['order'],
                    ':status'     => $p['status'],
                    ':now'        => $now,
                ]);
                $inserted['partners'] += $partIns->rowCount();
            }
        }

        // 11. 3-Day Programme Items (insert-if-empty; reflects manual registration desk per D-01)
        if ((int)$pdo->query('SELECT COUNT(*) FROM programme_items')->fetchColumn() === 0) {
            $progItems = [
                ['day' => 1, 'slot' => '08:30 – 09:30 IST', 'title' => 'Participant Check-in, Accessible Welcome & Registration Desk', 'cat' => 'Registration', 'venue' => 'Sama Indoor Stadium & MSU Baroda', 'desc' => 'Registration ID verification at the reception desk, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.', 'services' => ['ISL Interpretation', 'Braille Programme', 'Step-Free Access', 'Sighted Guides'], 'order' => 1],
                ['day' => 1, 'slot' => '09:30 – 11:00 IST', 'title' => 'Inaugural Ceremony: Celebrating Accessibility, Dignity & Diversity', 'cat' => 'Plenary', 'venue' => 'Main Auditorium', 'desc' => "Opening keynote addresses celebrating Gujarat's inaugural Purple Festival edition and India's inclusive future under Viksit Bharat@2047.", 'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Audio Description', 'Wheelchair Seating'], 'order' => 2],
                ['day' => 1, 'slot' => '11:30 – 13:00 IST', 'title' => 'Panel 1: Universal Design & Accessible Infrastructure for 2047', 'cat' => 'Panel Discussion', 'venue' => 'Hall A', 'desc' => 'Architects, urban planners, and disability rights leaders examine barrier-free cities, public transit, and digital public infrastructure.', 'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Wheelchair Seating'], 'order' => 3],
                ['day' => 1, 'slot' => '14:00 – 16:30 IST', 'title' => 'Para Sports Championships — Opening Heats (25 Adaptive Disciplines)', 'cat' => 'Inclusive Sports', 'venue' => 'Sama Indoor Stadium', 'desc' => 'Adaptive athletics, wheelchair basketball, boccia, blind cricket demonstrations, and inclusive youth sports.', 'services' => ['Step-Free Courts', 'Wheelchair Charging Bay', 'Medical Support', 'ISL Volunteers'], 'order' => 4],
                ['day' => 1, 'slot' => '14:30 – 17:00 IST', 'title' => 'Assistive Technology & Inclusive Innovation Expo', 'cat' => 'Exhibition', 'venue' => 'Innovation Pavilion', 'desc' => 'Hands-on demonstrations of screen readers, tactile graphics, mobility aids, AAC devices, and Indian assistive startups.', 'services' => ['Tactile Demos', 'ISL Interpretation', 'Accessible Counters'], 'order' => 5],
                ['day' => 1, 'slot' => '18:30 – 20:30 IST', 'title' => 'Evening Cultural Showcase: Disability Arts & Inclusive Theatre', 'cat' => 'Cultural Performance', 'venue' => 'Open-Air Stage', 'desc' => "Performances by artists with and without disabilities celebrating Gujarat's folk heritage, contemporary dance, and sign-language music.", 'services' => ['ISL Interpretation', 'Audio Description', 'Sensory Quiet Zone Nearby'], 'order' => 6],
                ['day' => 2, 'slot' => '09:30 – 11:00 IST', 'title' => 'Plenary 2: Inclusive Education, RPwD Act 2016 & NEP Synergy', 'cat' => 'Plenary', 'venue' => 'MSU Baroda Auditorium', 'desc' => 'Educators, GIEN delegates, and special educators discuss universal design for learning across schools and universities.', 'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Easy-Read Handouts'], 'order' => 1],
                ['day' => 2, 'slot' => '11:30 – 13:00 IST', 'title' => 'Workshop: Indian Sign Language (ISL) Interpretation Standards & Deaf Leadership', 'cat' => 'Workshop', 'venue' => 'Seminar Hall B', 'desc' => 'Interactive session led by Deaf educators and RCI-certified ISL interpreters on ethical interpretation and community access.', 'services' => ['Deaf-Led Session', 'ISL Interpretation', 'Visual Aids'], 'order' => 2],
                ['day' => 2, 'slot' => '14:00 – 17:00 IST', 'title' => 'Inclusive Film Festival & Accessible Media Screenings', 'cat' => 'Film & Media', 'venue' => 'Screening Hall', 'desc' => 'Curated disability cinema from India and abroad featuring open captions, audio description tracks, and filmmaker Q&As.', 'services' => ['Open Captions', 'Audio Description', 'ISL Q&A'], 'order' => 3],
                ['day' => 2, 'slot' => '14:00 – 17:30 IST', 'title' => 'Inclusive Livelihoods, Skill Showcase & Purple Bazaar', 'cat' => 'Livelihoods & Expo', 'venue' => 'Community Pavilion', 'desc' => 'Artisans, entrepreneurs with disabilities, and inclusive employers connect through product exhibitions and career dialogues.', 'services' => ['Step-Free Stalls', 'ISL Volunteers', 'Lowered Counters'], 'order' => 4],
                ['day' => 2, 'slot' => '18:00 – 20:00 IST', 'title' => 'Sensory-Friendly Music & Community Garba Evening', 'cat' => 'Cultural Performance', 'venue' => 'Central Courtyard', 'desc' => 'Adaptive, joyful community celebration with regulated acoustics, strobe-free lighting, and inclusive dance circles.', 'services' => ['No Flashing Lights', 'Noise-Dampening Zone', 'Wheelchair Friendly'], 'order' => 5],
                ['day' => 3, 'slot' => '09:30 – 11:30 IST', 'title' => 'Para Sports Finals & Inclusive Medal Ceremonies', 'cat' => 'Inclusive Sports', 'venue' => 'Sama Indoor Stadium', 'desc' => 'Championship finals across adaptive sports categories with dignified podium accessibility.', 'services' => ['Step-Free Podium', 'Audio Commentary', 'ISL Interpretation'], 'order' => 1],
                ['day' => 3, 'slot' => '12:00 – 13:30 IST', 'title' => 'Youth Leadership Dialogue & Self-Advocate Assembly', 'cat' => 'Assembly', 'venue' => 'Main Auditorium', 'desc' => 'Young leaders with disabilities draft the Vadodara Inclusion Charter for Gujarat 2027–2047.', 'services' => ['ISL Interpretation', 'Live Captioning', 'AAC Friendly Mic'], 'order' => 2],
                ['day' => 3, 'slot' => '15:30 – 17:30 IST', 'title' => 'Valedictory Ceremony, Volunteer & SLI Recognition & Closing Note', 'cat' => 'Plenary', 'venue' => 'Main Auditorium', 'desc' => 'Celebrating volunteers, ISL interpreters, partner institutions, and participants from across India.', 'services' => ['ISL Interpretation', 'Audio Description', 'Live Captioning (CART)'], 'order' => 3],
            ];
            $pStmt = $pdo->prepare("
                INSERT IGNORE INTO programme_items (id, edition_id, day_number, time_slot, title, description, category, venue_name, accessibility_services, display_order, status, updated_at)
                VALUES (:id, :edition_id, :day, :slot, :title, :desc, :cat, :venue, :services, :order, 'PUBLISHED', :now)
            ");
            foreach ($progItems as $i => $item) {
                $pStmt->execute([
                    ':id'         => sprintf('70000000-0000-4000-8000-%012d', $i + 1),
                    ':edition_id' => $editionId,
                    ':day'        => $item['day'],
                    ':slot'       => $item['slot'],
                    ':title'      => $item['title'],
                    ':desc'       => $item['desc'],
                    ':cat'        => $item['cat'],
                    ':venue'      => $item['venue'],
                    ':services'   => jsonEncode($item['services']),
                    ':order'      => $item['order'],
                    ':now'        => $now,
                ]);
                $inserted['programme_items'] += $pStmt->rowCount();
            }
        }

        // 12. CMS Home Page & Sections (INSERT IGNORE preserves any organizer CMS edits)
        $homePageId = '80000000-0000-4000-8000-000000000001';
        $cpStmt = $pdo->prepare("
            INSERT IGNORE INTO cms_pages (id, edition_id, slug, title, meta_description, status, created_at, updated_at)
            VALUES (:id, :edition_id, 'home', :title, :meta, 'PUBLISHED', :now, :now)
        ");
        $cpStmt->execute([
            ':id'         => $homePageId,
            ':edition_id' => $editionId,
            ':title'      => FESTIVAL_NAME,
            ':meta'       => 'Celebrating Accessibility, Inclusion, Dignity and Diversity — 21–23 January 2027 in Vadodara, Gujarat.',
            ':now'        => $now,
        ]);
        $inserted['cms_pages'] += $cpStmt->rowCount();

        $actualHomePageId = (string)($pdo->query("SELECT id FROM cms_pages WHERE slug = 'home' LIMIT 1")->fetchColumn() ?: $homePageId);

        $sections = [
            ['id' => '81000000-0000-4000-8000-000000000001', 'key' => 'hero', 'title' => 'PURPLE FESTIVAL GUJARAT 2027', 'subtitle' => 'Where inclusion becomes a celebration.', 'body' => "India's landmark festival of accessibility, human dignity, assistive innovation, para-sports, and universal design across all 21 benchmark disabilities under the RPwD Act, 2016.", 'data' => ['dates' => '21–23 January 2027', 'location' => 'Vadodara, Gujarat', 'primaryCtaLabel' => 'Apply Now', 'primaryCtaHref' => '/apply'], 'order' => 1],
            ['id' => '81000000-0000-4000-8000-000000000002', 'key' => 'about', 'title' => 'Inclusion Is Not Charity. It Is Culture, Innovation, and Right.', 'subtitle' => 'First Edition in Gujarat — Vadodara, January 2027', 'body' => 'Purple Festival Gujarat 2027 brings together 5,000+ attendees, 500+ participants with and without disabilities, educators, para-athletes, artists, technologists, and policy leaders for three days of barrier-free celebration across Vadodara.', 'data' => ['stats' => [['label' => 'Expected Attendees', 'value' => '5,000+'], ['label' => 'Benchmark Disabilities', 'value' => '21'], ['label' => 'Para Sports Disciplines', 'value' => '25'], ['label' => 'Days of Celebration', 'value' => '3']]], 'order' => 2],
            ['id' => '81000000-0000-4000-8000-000000000003', 'key' => 'viksit-bharat', 'title' => 'A Viksit Bharat Must Be an Inclusive Bharat.', 'subtitle' => "Aligned with India's Vision for 2047", 'body' => "India's vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation.", 'data' => ['disclaimer' => 'Purple Festival Gujarat 2027 contributes to the national conversation around inclusive development under Viksit Bharat@2047.'], 'order' => 3],
            ['id' => '81000000-0000-4000-8000-000000000004', 'key' => 'contact', 'title' => 'Connect with the Festival Secretariat', 'subtitle' => 'Accessibility, Registration, and Partnership Inquiries', 'body' => 'Reach our team for accommodation requests, institutional delegations, volunteer coordination, or media inquiries.', 'data' => ['email' => 'purplefestivalvadodara@gmail.com', 'phones' => [['name' => 'Radha Tandon', 'number' => '+91 99743 83920'], ['name' => 'Satish Kapoor', 'number' => '+91 92117 60202']], 'city' => 'Vadodara, Gujarat, India'], 'order' => 4],
        ];
        $csStmt = $pdo->prepare("
            INSERT IGNORE INTO cms_sections (id, page_id, section_key, title, subtitle, body, data, display_order, status, updated_at)
            VALUES (:id, :page_id, :key, :title, :subtitle, :body, :data, :order, 'PUBLISHED', :now)
        ");
        foreach ($sections as $s) {
            $csStmt->execute([
                ':id'       => $s['id'],
                ':page_id'  => $actualHomePageId,
                ':key'      => $s['key'],
                ':title'    => $s['title'],
                ':subtitle' => $s['subtitle'],
                ':body'     => $s['body'],
                ':data'     => jsonEncode($s['data']),
                ':order'    => $s['order'],
                ':now'      => $now,
            ]);
            $inserted['cms_sections'] += $csStmt->rowCount();
        }

        // 13. FAQs (insert-if-empty; reflects official Registration ID & manual check-in per D-01)
        if ((int)$pdo->query('SELECT COUNT(*) FROM faq_items')->fetchColumn() === 0) {
            $faqs = [
                ['cat' => 'Registration',       'q' => 'Do I need to disclose a disability to attend Purple Festival Gujarat 2027?', 'a' => 'No. Disability disclosure is completely optional and never restricts which category you may apply for. Persons with disabilities can register as Attendees, Volunteers, or SLI Professionals.', 'order' => 1],
                ['cat' => 'Accessibility',      'q' => 'How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?', 'a' => 'You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Your requests are visible only to authorized accessibility coordinators.', 'order' => 2],
                ['cat' => 'Check-in & Attendance', 'q' => 'How does check-in work at the festival venue?', 'a' => 'Once your registration is confirmed (or your Volunteer/SLI application is approved), quote your official Registration ID (for example PFG-2027-000100) or registered name at the Registration Desk in Vadodara for quick verification and attendance check-in.', 'order' => 3],
                ['cat' => 'Volunteers & SLI',   'q' => 'Does submitting a Volunteer or SLI application mean automatic approval?', 'a' => 'No. Volunteer and Indian Sign Language Interpreter applications undergo review by festival coordinators. Once approved, you will receive your official confirmation and duty schedule.', 'order' => 4],
            ];
            $faqStmt = $pdo->prepare("
                INSERT IGNORE INTO faq_items (id, edition_id, category, question, answer, display_order, status, updated_at)
                VALUES (:id, :edition_id, :cat, :q, :a, :order, 'PUBLISHED', :now)
            ");
            foreach ($faqs as $i => $f) {
                $faqStmt->execute([
                    ':id'         => sprintf('82000000-0000-4000-8000-%012d', $i + 1),
                    ':edition_id' => $editionId,
                    ':cat'        => $f['cat'],
                    ':q'          => $f['q'],
                    ':a'          => $f['a'],
                    ':order'      => $f['order'],
                    ':now'        => $now,
                ]);
                $inserted['faq_items'] += $faqStmt->rowCount();
            }
        }

        // 14. Notification Templates (INSERT IGNORE — never overwrites organizer edits; D-01 compliant)
        $ntStmt = $pdo->prepare('
            INSERT IGNORE INTO notification_templates (id, code, subject, body_html, body_text, is_active, updated_at)
            VALUES (:id, :code, :subject, :body_html, :body_text, 1, :now)
        ');
        $templates = [
            ['id' => '83000000-0000-4000-8000-000000000001', 'code' => 'REGISTRATION_CONFIRMED', 'subject' => 'Confirmed: Your Purple Festival Gujarat 2027 Registration ({{registrationCode}})', 'html' => '<p>Dear {{fullName}},</p><p>Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Please quote your official Registration ID at the Registration Desk in Vadodara.</p>', 'text' => 'Dear {{fullName}}, Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Please quote your official Registration ID at the Registration Desk in Vadodara.'],
            ['id' => '83000000-0000-4000-8000-000000000002', 'code' => 'APPLICATION_SUBMITTED',  'subject' => 'Application Received: Purple Festival Gujarat 2027 ({{registrationCode}})', 'html' => '<p>Dear {{fullName}},</p><p>We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.</p>', 'text' => 'Dear {{fullName}}, We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.'],
            ['id' => '83000000-0000-4000-8000-000000000003', 'code' => 'APPLICATION_APPROVED',   'subject' => 'Approved: Welcome to Purple Festival Gujarat 2027 ({{registrationCode}})', 'html' => '<p>Dear {{fullName}},</p><p>Congratulations! Your {{category}} application ({{registrationCode}}) has been approved. Your Registration ID and duty schedule are available in your Participant Dashboard.</p>', 'text' => 'Dear {{fullName}}, Your {{category}} application ({{registrationCode}}) has been approved. Your Registration ID and duty schedule are available in your Participant Dashboard.'],
        ];
        foreach ($templates as $t) {
            $ntStmt->execute([
                ':id'        => $t['id'],
                ':code'      => $t['code'],
                ':subject'   => $t['subject'],
                ':body_html' => $t['html'],
                ':body_text' => $t['text'],
                ':now'       => $now,
            ]);
            $inserted['notification_templates'] += $ntStmt->rowCount();
        }

        // 15. System Settings (INSERT IGNORE preserves any organizer settings changes)
        $ssStmt = $pdo->prepare('
            INSERT IGNORE INTO system_settings (id, `key`, `value`, description, updated_at)
            VALUES (:id, :key, :value, :description, :now)
        ');
        $settingsSeed = [
            ['id' => '84000000-0000-4000-8000-000000000001', 'key' => 'payments.enabled', 'value' => jsonEncode(['enabled' => false, 'reason' => 'Awaiting organizer fee confirmation and payment gateway keys (Decision D-05).']), 'description' => 'Master toggle for paid registrations.'],
            ['id' => '84000000-0000-4000-8000-000000000002', 'key' => 'sli.require_crr', 'value' => jsonEncode(['required' => false, 'gujaratPriority' => true]), 'description' => 'Configurable RCI CRR requirement for SLI applicants.'],
            ['id' => '84000000-0000-4000-8000-000000000003', 'key' => 'attendance.mode', 'value' => jsonEncode(['mode' => 'MANUAL_DESK', 'qrIssuanceEnabled' => false, 'decision' => 'D-01']), 'description' => 'Event-day attendance mode (Decision D-01: manual desk verification).'],
        ];
        foreach ($settingsSeed as $s) {
            $ssStmt->execute([
                ':id'          => $s['id'],
                ':key'         => $s['key'],
                ':value'       => $s['value'],
                ':description' => $s['description'],
                ':now'         => $now,
            ]);
            $inserted['system_settings'] += $ssStmt->rowCount();
        }

        // 16. Media Assets (insert-if-empty)
        if ((int)$pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn() === 0) {
            $mediaItems = [
                ['file' => 'purple-festival.jpg', 'url' => '/assets/logos/purple-festival.jpg', 'alt' => 'Purple Festival Gujarat 2027 official emblem showing three inclusive figures beneath hearts', 'cat' => 'LOGO', 'src' => 'Official Purple Festival Organizer Asset', 'lic' => 'Authorized Festival Trademark'],
                ['file' => 'venue-01.jpg', 'url' => '/assets/gallery/venue-01.jpg', 'alt' => 'Wide view of the Vadodara sports stadium and athletic ground prepared for inclusive para-sports', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-05.jpg', 'url' => '/assets/gallery/venue-05.jpg', 'alt' => 'Indoor pavilion and accessible court facility in Vadodara for Purple Festival Gujarat 2027', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-10.jpg', 'url' => '/assets/gallery/venue-10.jpg', 'alt' => 'Outdoor sports field and pavilion lawn at The Maharaja Sayajirao University of Baroda', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-15.jpg', 'url' => '/assets/gallery/venue-15.jpg', 'alt' => 'Accessible campus walkway and entrance corridor at Vadodara festival venue', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-20.jpg', 'url' => '/assets/gallery/venue-20.jpg', 'alt' => 'Sports complex seating and shaded pavilion area for spectators and para-athletes', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-25.jpg', 'url' => '/assets/gallery/venue-25.jpg', 'alt' => 'Campus assembly area and shaded courtyard for workshops and community exhibitions', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
            ];
            $mStmt = $pdo->prepare("
                INSERT IGNORE INTO media_assets (id, edition_id, file_name, url, mime_type, alt_text, category, source_attribution, license_info, created_at)
                VALUES (:id, :edition_id, :file, :url, 'image/jpeg', :alt, :cat, :src, :lic, :now)
            ");
            foreach ($mediaItems as $i => $m) {
                $mStmt->execute([
                    ':id'         => sprintf('85000000-0000-4000-8000-%012d', $i + 1),
                    ':edition_id' => $editionId,
                    ':file'       => $m['file'],
                    ':url'        => $m['url'],
                    ':alt'        => $m['alt'],
                    ':cat'        => $m['cat'],
                    ':src'        => $m['src'],
                    ':lic'        => $m['lic'],
                    ':now'        => $now,
                ]);
                $inserted['media_assets'] += $mStmt->rowCount();
            }
        }

        // 17. Ensure atomic sequence floor stays synced above any existing registrations
        if (pfgDbTableExists($pdo, 'registration_number_sequences')) {
            syncRegistrationSequenceFloor($pdo, $editionId);
        }

        return $inserted;
    }
}

// Allow direct CLI invocation: `php database/seed.php`
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $pdo = pfgCreateDirectPdo();
        $report = pfgRunSafeReferenceSeed($pdo);
        echo '[SEED] Completed safely: ' . jsonEncode($report) . "\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'SEED ERROR: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

