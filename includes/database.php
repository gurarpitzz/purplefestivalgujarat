<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — PDO Database Layer & Auto-Initializer
 * Supports MySQL 8.0 / MariaDB in production and automatic SQLite PDO fallback for local testing.
 */

require_once __DIR__ . '/config.php';

/**
 * PDO subclass that provides cross-driver compatibility (MySQL 8.0 / MariaDB & SQLite fallback)
 * and transparent table-alias normalization across all modules.
 */
class PfgPdo extends PDO
{
    public bool $isSqlite = false;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->isSqlite = stripos($dsn, 'sqlite:') === 0;
    }

    public function normalizeSql(string $sql): string
    {
        if ($this->isSqlite) {
            $sql = preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT OR IGNORE INTO', $sql) ?? $sql;
            $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'CURRENT_TIMESTAMP', $sql) ?? $sql;
            if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
                $sql = preg_replace('/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+.+$/is', '', $sql) ?? $sql;
                $sql = preg_replace('/^\s*INSERT\s+INTO\b/i', 'INSERT OR REPLACE INTO', $sql) ?? $sql;
            }
        } else {
            $sql = preg_replace('/\bINSERT\s+OR\s+IGNORE\s+INTO\b/i', 'INSERT IGNORE INTO', $sql) ?? $sql;
        }

        return $sql;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        return parent::prepare($this->normalizeSql((string)$query), $options);
    }

    #[\ReturnTypeWillChange]
    public function query($query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        $normalized = $this->normalizeSql((string)$query);
        if ($fetchMode === null) {
            return parent::query($normalized);
        }
        return parent::query($normalized, $fetchMode, ...$fetchModeArgs);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        return parent::exec($this->normalizeSql((string)$statement));
    }
}

if (!function_exists('getDb')) {
    /**
     * Returns the singleton PDO connection.
     * Enforces MySQL/MariaDB in staging/production (fails closed if DB credentials are missing — no silent SQLite fallback).
     */
    function getDb(bool $reset = false): PDO
    {
        static $pdo = null;
        if ($reset) {
            $pdo = null;
        }
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];

        $isLocalDev = defined('IS_LOCAL_DEV_ENV') && IS_LOCAL_DEV_ENV;
        $driver = defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'mysql';
        $hasMysqlCreds = (DB_NAME !== '' && DB_USER !== '');

        if ($driver === 'sqlite') {
            if (!$isLocalDev) {
                throw new RuntimeException('SQLite database driver is prohibited in staging and production environments.');
            }
            $sqlitePath = SQLITE_FALLBACK_PATH;
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $dsn = 'sqlite:' . $sqlitePath;
            $pdo = new PfgPdo($dsn, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            if (!$hasMysqlCreds) {
                throw new RuntimeException('Database credentials (DB_NAME and DB_USER) are not configured. Silent SQLite fallback is disabled.');
            }
            if (DB_SOCKET !== '') {
                $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', DB_SOCKET, DB_NAME);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            }
            $pdo = new PfgPdo($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET time_zone = '+05:30'");
        }

        ensureDatabaseInitialized($pdo);

        return $pdo;
    }
}

if (!function_exists('convertMysqlDdlToSqlite')) {
    /**
     * Converts our MySQL schema.sql DDL statements into SQLite-compatible CREATE TABLE IF NOT EXISTS statements.
     */
    function convertMysqlDdlToSqlite(string $mysqlSql): array
    {
        $lines = explode("\n", $mysqlSql);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0 || stripos($trim, 'SET ') === 0) {
                continue;
            }
            $cleanedLines[] = $line;
        }
        $joined = implode("\n", $cleanedLines);
        $rawStatements = explode(';', $joined);
        $sqliteStatements = [];
        $indexStatements = [];

        foreach ($rawStatements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?\s*\((.*)\)\s*ENGINE=/is', $stmt, $m)) {
                $tableName = $m[1];
                $body = $m[2];
                $bodyLines = preg_split('/,\r?\n/', $body) ?: [];
                $keepLines = [];
                foreach ($bodyLines as $bLine) {
                    $bTrim = trim($bLine);
                    if (stripos($bTrim, 'UNIQUE KEY ') === 0) {
                        if (preg_match('/UNIQUE\s+KEY\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]+)\)/i', $bTrim, $uk)) {
                            $indexStatements[] = sprintf(
                                'CREATE UNIQUE INDEX IF NOT EXISTS `%s` ON `%s` (%s)',
                                $uk[1],
                                $tableName,
                                $uk[2]
                            );
                        }
                        continue;
                    }
                    if (stripos($bTrim, 'KEY ') === 0) {
                        if (preg_match('/KEY\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]+)\)/i', $bTrim, $k)) {
                            $indexStatements[] = sprintf(
                                'CREATE INDEX IF NOT EXISTS `%s` ON `%s` (%s)',
                                $k[1],
                                $tableName,
                                $k[2]
                            );
                        }
                        continue;
                    }
                    if (stripos($bTrim, 'CONSTRAINT ') === 0) {
                        continue;
                    }
                    $bTrim = preg_replace('/\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $bTrim) ?? $bTrim;
                    $keepLines[] = '  ' . $bTrim;
                }
                $sqliteStatements[] = sprintf(
                    "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n)",
                    $tableName,
                    implode(",\n", $keepLines)
                );
            }
        }

        return array_merge($sqliteStatements, $indexStatements);
    }
}

if (!function_exists('ensureUsersUsernameColumn')) {
    /**
     * Ensures the optional `username` column and unique index exist on `users` for existing databases.
     */
    function ensureUsersUsernameColumn(PDO $pdo): void
    {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(100) DEFAULT NULL");
        } catch (Throwable $e) {
            // Column already exists
        }
        try {
            $pdo->exec("CREATE UNIQUE INDEX users_username_unique ON users (username)");
        } catch (Throwable $e) {
            // Index already exists
        }
    }
}

if (!function_exists('ensureDatabaseInitialized')) {
    /**
     * Automatically creates tables and seeds reference data on first run if `roles` table is missing or empty.
     */
    function ensureDatabaseInitialized(PDO $pdo): void
    {
        static $initializing = false;
        if ($initializing) {
            return;
        }
        $initializing = true;

        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM roles");
            $row = $stmt ? $stmt->fetch() : false;
            if ($row && (int)($row['cnt'] ?? 0) >= 4) {
                ensureUsersUsernameColumn($pdo);
                $initializing = false;
                return;
            }
        } catch (Throwable $e) {
            // Table does not exist yet; proceed to initialize schema.
        }

        $schemaPath = dirname(__DIR__) . '/database/schema.sql';
        if (is_file($schemaPath) && is_readable($schemaPath)) {
            $schemaSql = (string)file_get_contents($schemaPath);
            $isSqlite = ($pdo instanceof PfgPdo && $pdo->isSqlite)
                || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

            if ($isSqlite) {
                $statements = convertMysqlDdlToSqlite($schemaSql);
                foreach ($statements as $sql) {
                    $pdo->exec($sql);
                }
            } else {
                $cleanLines = [];
                foreach (explode("\n", $schemaSql) as $line) {
                    $trimLine = trim($line);
                    if ($trimLine === '' || strpos($trimLine, '--') === 0) {
                        continue;
                    }
                    $cleanLines[] = $line;
                }
                $statements = array_filter(array_map('trim', explode(';', implode("\n", $cleanLines))));
                foreach ($statements as $sql) {
                    if ($sql !== '') {
                        $pdo->exec($sql);
                    }
                }
            }
        }

        ensureUsersUsernameColumn($pdo);
        seedDatabase($pdo);
        $initializing = false;
    }
}

if (!function_exists('seedDatabase')) {
    /**
     * Idempotently seeds all reference data (roles, permissions, role_permissions,
     * gujarat-2027 edition, venues, check-in stations, 21 RPwD disabilities,
     * 13 accommodation types, 8 registration categories, partners, 3-day programme,
     * CMS home page & sections, FAQs, notification templates, settings, media assets).
     */
    function seedDatabase(PDO $pdo): void
    {
        $now = nowSql();

        // 1. Roles
        $roles = [
            ['id' => '10000000-0000-4000-8000-000000000001', 'code' => 'SUPERADMIN',  'name' => 'Superadmin',              'description' => 'Full system, security, CMS, user, and analytics authority.'],
            ['id' => '10000000-0000-4000-8000-000000000002', 'code' => 'ADMIN',       'name' => 'Festival Admin',          'description' => 'Day-to-day registration, volunteer/SLI review, attendance, and reporting.'],
            ['id' => '10000000-0000-4000-8000-000000000003', 'code' => 'RGST',        'name' => 'Registration Desk Staff', 'description' => 'Restricted reception-desk QR scanning and entry confirmation.'],
            ['id' => '10000000-0000-4000-8000-000000000004', 'code' => 'PARTICIPANT', 'name' => 'Participant',             'description' => 'Attendee, delegate, volunteer, or SLI professional account.'],
        ];
        $roleStmt = $pdo->prepare("INSERT IGNORE INTO roles (id, code, name, description, created_at) VALUES (:id, :code, :name, :description, :created_at)");
        foreach ($roles as $r) {
            $roleStmt->execute([
                ':id'          => $r['id'],
                ':code'        => $r['code'],
                ':name'        => $r['name'],
                ':description' => $r['description'],
                ':created_at'  => $now,
            ]);
        }

        $roleMap = [];
        foreach ($pdo->query("SELECT id, code FROM roles")->fetchAll() as $row) {
            $roleMap[$row['code']] = $row['id'];
        }

        // 2. Permissions
        $permValues = array_values(PERMISSIONS);
        $permStmt = $pdo->prepare("INSERT IGNORE INTO permissions (id, code, description, created_at) VALUES (:id, :code, :description, :created_at)");
        foreach ($permValues as $idx => $code) {
            $permId = sprintf('20000000-0000-4000-8000-%012d', $idx + 1);
            $permStmt->execute([
                ':id'          => $permId,
                ':code'        => $code,
                ':description' => $code,
                ':created_at'  => $now,
            ]);
        }

        $permMap = [];
        foreach ($pdo->query("SELECT id, code FROM permissions")->fetchAll() as $row) {
            $permMap[$row['code']] = $row['id'];
        }

        // 3. Role Permissions
        $rpStmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (id, role_id, permission_id) VALUES (:id, :role_id, :permission_id)");
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
            }
        }

        // 4. Festival Edition (gujarat-2027)
        $defaultEditionId = '00000000-0000-4000-8000-000000002027';
        $edStmt = $pdo->prepare("
            INSERT IGNORE INTO festival_editions
            (id, slug, name, tagline, city, state, start_date, end_date, registration_opens_at, registration_closes_at, attendance_policy, is_active, created_at, updated_at)
            VALUES (:id, :slug, :name, :tagline, :city, :state, :start_date, :end_date, :opens, :closes, 'ONCE_PER_DAY', 1, :now, :now)
        ");
        $edStmt->execute([
            ':id'         => $defaultEditionId,
            ':slug'       => FESTIVAL_EDITION_SLUG,
            ':name'       => FESTIVAL_NAME,
            ':tagline'    => FESTIVAL_TAGLINE,
            ':city'       => 'Vadodara',
            ':state'      => 'Gujarat',
            ':start_date' => '2027-01-21 09:00:00',
            ':end_date'   => '2027-01-23 20:30:00',
            ':opens'      => '2026-11-01 00:00:00',
            ':closes'     => '2027-01-20 23:59:59',
            ':now'        => $now,
        ]);

        $edLookup = $pdo->prepare("SELECT id FROM festival_editions WHERE slug = :slug LIMIT 1");
        $edLookup->execute([':slug' => FESTIVAL_EDITION_SLUG]);
        $editionRow = $edLookup->fetch();
        $editionId = $editionRow['id'] ?? $defaultEditionId;

        // 5. Venues
        $venuesCount = (int)$pdo->query("SELECT COUNT(*) FROM venues")->fetchColumn();
        $samaVenueId = '30000000-0000-4000-8000-000000000001';
        $msuVenueId  = '30000000-0000-4000-8000-000000000002';
        if ($venuesCount === 0) {
            $vStmt = $pdo->prepare("
                INSERT IGNORE INTO venues (id, edition_id, name, address, confirmation_status, accessibility_features, capacity, created_at)
                VALUES (:id, :edition_id, :name, :address, :status, :features, :capacity, :now)
            ");
            $venuesSeed = [
                [
                    'id'       => $samaVenueId,
                    'name'     => 'Sama Indoor Stadium',
                    'address'  => 'Sama, Vadodara, Gujarat',
                    'status'   => 'CONFIRMED',
                    'features' => jsonEncode(['Step-free entry', 'Wheelchair-accessible courts', 'Accessible restrooms', 'Para-sports ready']),
                    'capacity' => 2500,
                ],
                [
                    'id'       => $msuVenueId,
                    'name'     => 'The Maharaja Sayajirao University of Baroda (FSW & Dept. of Physical Education)',
                    'address'  => 'Pratapgunj, Vadodara, Gujarat',
                    'status'   => 'COMMITTED',
                    'features' => jsonEncode(['Campus access audit in progress', 'Accessible seminar halls', 'Sports pavilion']),
                    'capacity' => 3000,
                ],
                [
                    'id'       => '30000000-0000-4000-8000-000000000003',
                    'name'     => 'GMERS Medical College, Gotri',
                    'address'  => 'Gotri, Vadodara, Gujarat',
                    'status'   => 'PENDING',
                    'features' => jsonEncode(['Pending final venue confirmation']),
                    'capacity' => 1000,
                ],
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
            }
        }

        // 6. Check-in Stations
        $stationsCount = (int)$pdo->query("SELECT COUNT(*) FROM checkin_stations")->fetchColumn();
        if ($stationsCount === 0) {
            $stStmt = $pdo->prepare("
                INSERT IGNORE INTO checkin_stations (id, edition_id, venue_id, name, gate_code, is_active, created_at)
                VALUES (:id, :edition_id, :venue_id, :name, :gate_code, 1, :now)
            ");
            $stations = [
                ['id' => '31000000-0000-4000-8000-000000000001', 'venue_id' => $samaVenueId, 'name' => 'Sama Indoor Stadium — Main Gate A',               'gate_code' => 'SAMA-A'],
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
            }
        }

        // 7. All 21 RPwD Act 2016 Disability Categories
        $disStmt = $pdo->prepare("
            INSERT IGNORE INTO disability_categories (id, code, name, rpwd_group, description, accommodation_examples, display_order)
            VALUES (:id, :code, :name, :rpwd_group, :description, :examples, :display_order)
        ");
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
        }

        // 8. 13 Accommodation Types
        $accStmt = $pdo->prepare("
            INSERT IGNORE INTO accommodation_types (id, code, label, is_active, display_order)
            VALUES (:id, :code, :label, 1, :display_order)
        ");
        foreach (ACCOMMODATION_TYPES_SEED as $i => $a) {
            $accStmt->execute([
                ':id'            => sprintf('41000000-0000-4000-8000-%012d', $i + 1),
                ':code'          => $a['code'],
                ':label'         => $a['label'],
                ':display_order' => $i + 1,
            ]);
        }

        // 9. 8 Registration Categories
        $catSeed = [
            ['code' => 'ATTENDEE',      'name' => 'General Attendee',                                   'description' => 'Open festival access to cultural performances, exhibitions, para-sports, and inclusive sessions.',                                              'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 1],
            ['code' => 'DELEGATE',      'name' => 'Delegate (3-Day Conference & All-Access)',           'description' => 'Full access to conferences, workshops, panels, and networking. Delegate fee policy is managed by organizers in CMS.',                            'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 2],
            ['code' => 'VOLUNTEER',     'name' => 'Festival Volunteer',                                 'description' => 'Support accessibility desks, stage anchoring, para-sports, and participant hospitality. Requires organizer review.',                             'requires_approval' => 1, 'requires_payment' => 0, 'fee' => 0, 'order' => 3],
            ['code' => 'SLI',           'name' => 'SLI / Indian Sign Language Professional',            'description' => 'Qualified Indian Sign Language interpreters supporting plenaries, panels, workshops, and cultural stages. Requires verification.',              'requires_approval' => 1, 'requires_payment' => 0, 'fee' => 0, 'order' => 4],
            ['code' => 'STUDENT',       'name' => 'Student Participant',                                'description' => 'School, college, and university students participating in inclusive education, workshops, and youth tracks.',                                   'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 5],
            ['code' => 'PROFESSIONAL',  'name' => 'Professional / Educator / Practitioner',             'description' => 'Educators, healthcare professionals, accessibility specialists, and industry practitioners.',                                                   'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 6],
            ['code' => 'INSTITUTIONAL', 'name' => 'Institutional Representative',                       'description' => 'Representatives from DPOs, NGOs, universities, and civic institutions.',                                                                        'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 7],
            ['code' => 'ALLY',          'name' => 'Ally / Supporter',                                   'description' => 'Family members, caregivers, community allies, and inclusion advocates.',                                                                        'requires_approval' => 0, 'requires_payment' => 0, 'fee' => 0, 'order' => 8],
        ];
        $catStmt = $pdo->prepare("
            INSERT IGNORE INTO registration_categories
            (id, edition_id, code, name, description, requires_approval, requires_payment, fee_amount_inr, is_active, display_order, created_at)
            VALUES (:id, :edition_id, :code, :name, :description, :requires_approval, :requires_payment, :fee, 1, :display_order, :now)
        ");
        foreach ($catSeed as $i => $c) {
            $catStmt->execute([
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
            ]);
        }

        // 10. Organizations & Partners (Brochure Page 5 — 11 Confirmed Partners)
        $partnersCount = (int)$pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn();
        $hasFullBrochureSeed = (int)$pdo->query(
            "SELECT COUNT(*) FROM partners WHERE id = '61000000-0000-4000-8000-000000000011'"
        )->fetchColumn();
        if ($partnersCount === 0 || $hasFullBrochureSeed === 0) {
            $partnerDefs = [
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000004',
                    'part_id'     => '61000000-0000-4000-8000-000000000004',
                    'name'        => 'PRAANGAN, Vadodara',
                    'short_name'  => 'PRAANGAN',
                    'logo_url'    => '/assets/logos/praangan.jpg',
                    'logo_alt'    => 'PRAANGAN, Vadodara — A center for children with special needs logo',
                    'description' => 'A centre for children with special needs.',
                    'category'    => 'Organisers',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 1,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000001',
                    'part_id'     => '61000000-0000-4000-8000-000000000001',
                    'name'        => 'Brotherhood',
                    'short_name'  => 'Brotherhood',
                    'logo_url'    => '/assets/logos/brotherhood-transparent.png',
                    'logo_alt'    => 'Brotherhood official logo',
                    'description' => 'Rights and inclusion of persons with disabilities.',
                    'category'    => 'Organisers',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 2,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000002',
                    'part_id'     => '61000000-0000-4000-8000-000000000002',
                    'name'        => 'Global Inclusive Education Network',
                    'short_name'  => 'GIEN',
                    'logo_url'    => '/assets/logos/gien.jpg',
                    'logo_alt'    => 'Global Inclusive Education Network (GIEN) official logo',
                    'description' => 'Making education inclusive for every child.',
                    'category'    => 'Organisers',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 3,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000003',
                    'part_id'     => '61000000-0000-4000-8000-000000000003',
                    'name'        => 'Faculty of Social Work, MSU Baroda',
                    'short_name'  => 'MSU Baroda (FSW)',
                    'logo_url'    => '/assets/logos/msu-baroda.png',
                    'logo_alt'    => 'The Maharaja Sayajirao University of Baroda official crest',
                    'description' => 'Venue partner hosting plenary sessions, academic dialogues, and inclusive campus activities.',
                    'category'    => 'Venue Partners',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 4,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000006',
                    'part_id'     => '61000000-0000-4000-8000-000000000006',
                    'name'        => 'Department of Physical Education, MSU Baroda',
                    'short_name'  => 'MSU Baroda (DPE)',
                    'logo_url'    => '/assets/logos/msu-baroda.png',
                    'logo_alt'    => 'The Maharaja Sayajirao University of Baroda official crest',
                    'description' => 'Venue partner coordinating adaptive athletics and Para Sports events.',
                    'category'    => 'Venue Partners',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 5,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000007',
                    'part_id'     => '61000000-0000-4000-8000-000000000007',
                    'name'        => 'GMERS Medical College and Hospital, Gotri',
                    'short_name'  => 'GMERS Gotri',
                    'logo_url'    => '/assets/logos/gmers-gotri.png',
                    'logo_alt'    => 'GMERS Medical College and Hospital, Gotri official logo',
                    'description' => 'Venue and medical readiness partner supporting inclusive health and on-site care.',
                    'category'    => 'Venue Partners',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 6,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000008',
                    'part_id'     => '61000000-0000-4000-8000-000000000008',
                    'name'        => 'State Commissioner for Persons with Disabilities, Goa',
                    'short_name'  => 'SCPD Goa',
                    'logo_url'    => '/assets/logos/scpd-goa.png',
                    'logo_alt'    => 'State Commission for Persons with Disabilities - Goa logo',
                    'description' => 'Knowledge Partner sharing insights from India’s International Purple Fest in Goa.',
                    'category'    => 'Knowledge Partners',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 7,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000009',
                    'part_id'     => '61000000-0000-4000-8000-000000000009',
                    'name'        => 'Department for Empowerment of Persons with Disabilities, Goa',
                    'short_name'  => 'DEPwD Goa',
                    'logo_url'    => '/assets/logos/depwd-goa.png',
                    'logo_alt'    => 'Department for Empowerment of Persons with Disabilities, Government of Goa logo',
                    'description' => 'Knowledge Partner advancing cross-state accessibility standards and festival knowledge exchange.',
                    'category'    => 'Knowledge Partners',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 8,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000005',
                    'part_id'     => '61000000-0000-4000-8000-000000000005',
                    'name'        => 'CDSA, Tata Institute of Social Sciences, Mumbai',
                    'short_name'  => 'CDSA-TISS',
                    'logo_url'    => '/assets/logos/cdsa-tiss.jpg',
                    'logo_alt'    => 'CDSA, Tata Institute of Social Sciences, Mumbai logo',
                    'description' => 'Academic Partner advancing disability studies, social work research, and inclusive policy.',
                    'category'    => 'Academic Partner',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 9,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000010',
                    'part_id'     => '61000000-0000-4000-8000-000000000010',
                    'name'        => 'Gujarat National Law University, Gandhinagar',
                    'short_name'  => 'GNLU Gandhinagar',
                    'logo_url'    => '/assets/logos/gnlu.png',
                    'logo_alt'    => 'Gujarat National Law University, Gandhinagar official emblem',
                    'description' => 'Legal Partner supporting disability rights, RPwD Act 2016 awareness, and legal empowerment.',
                    'category'    => 'Legal Partner',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 10,
                ],
                [
                    'org_id'      => '60000000-0000-4000-8000-000000000011',
                    'part_id'     => '61000000-0000-4000-8000-000000000011',
                    'name'        => 'AYJNIHH, Mumbai',
                    'short_name'  => 'AYJNISHD(D)',
                    'logo_url'    => '/assets/logos/ayjnihh.png',
                    'logo_alt'    => 'Ali Yavar Jung National Institute of Speech and Hearing Disabilities (AYJNIHH), Mumbai official emblem',
                    'description' => 'Education Partner supporting speech, hearing, and Indian Sign Language inclusion.',
                    'category'    => 'Education Partner',
                    'consent'     => 1,
                    'status'      => 'PUBLISHED',
                    'order'       => 11,
                ],
            ];
            $checkOrgStmt = $pdo->prepare("SELECT COUNT(*) FROM organizations WHERE id = :id");
            $orgInsertStmt = $pdo->prepare("
                INSERT INTO organizations (id, name, short_name, logo_url, logo_alt_text, description, created_at)
                VALUES (:id, :name, :short_name, :logo_url, :logo_alt, :description, :now)
            ");
            $orgUpdateStmt = $pdo->prepare("
                UPDATE organizations
                SET name = :name, short_name = :short_name, logo_url = :logo_url, logo_alt_text = :logo_alt, description = :description
                WHERE id = :id
            ");
            $checkPartStmt = $pdo->prepare("SELECT COUNT(*) FROM partners WHERE id = :id");
            $partInsertStmt = $pdo->prepare("
                INSERT INTO partners (id, edition_id, organization_id, category, consent_verified, display_order, status, created_at, updated_at)
                VALUES (:id, :edition_id, :org_id, :category, :consent, :order, :status, :now, :now)
            ");
            $partUpdateStmt = $pdo->prepare("
                UPDATE partners
                SET category = :category, display_order = :order, consent_verified = :consent, status = :status, updated_at = :now
                WHERE id = :id
            ");
            foreach ($partnerDefs as $p) {
                $checkOrgStmt->execute([':id' => $p['org_id']]);
                if ((int)$checkOrgStmt->fetchColumn() > 0) {
                    $orgUpdateStmt->execute([
                        ':id'          => $p['org_id'],
                        ':name'        => $p['name'],
                        ':short_name'  => $p['short_name'],
                        ':logo_url'    => $p['logo_url'],
                        ':logo_alt'    => $p['logo_alt'],
                        ':description' => $p['description'],
                    ]);
                } else {
                    $orgInsertStmt->execute([
                        ':id'          => $p['org_id'],
                        ':name'        => $p['name'],
                        ':short_name'  => $p['short_name'],
                        ':logo_url'    => $p['logo_url'],
                        ':logo_alt'    => $p['logo_alt'],
                        ':description' => $p['description'],
                        ':now'         => $now,
                    ]);
                }

                $checkPartStmt->execute([':id' => $p['part_id']]);
                if ((int)$checkPartStmt->fetchColumn() > 0) {
                    $partUpdateStmt->execute([
                        ':id'       => $p['part_id'],
                        ':category' => $p['category'],
                        ':order'    => $p['order'],
                        ':consent'  => $p['consent'],
                        ':status'   => $p['status'],
                        ':now'      => $now,
                    ]);
                } else {
                    $partInsertStmt->execute([
                        ':id'         => $p['part_id'],
                        ':edition_id' => $editionId,
                        ':org_id'     => $p['org_id'],
                        ':category'   => $p['category'],
                        ':consent'    => $p['consent'],
                        ':order'      => $p['order'],
                        ':status'     => $p['status'],
                        ':now'        => $now,
                    ]);
                }
            }
        }

        // 11. 3-Day Programme Items
        $progCount = (int)$pdo->query("SELECT COUNT(*) FROM programme_items")->fetchColumn();
        if ($progCount === 0) {
            $progItems = [
                ['day' => 1, 'slot' => '08:30 – 09:30 IST', 'title' => 'Participant Check-in, Accessible Welcome & Registration Desk',      'cat' => 'Registration',         'venue' => 'Sama Indoor Stadium & MSU Baroda', 'desc' => 'QR pass verification, Braille & large-print programme distribution, ISL helpdesk, and wheelchair shuttle orientation.',                                                 'services' => ['ISL Interpretation', 'Braille Programme', 'Step-Free Access', 'Sighted Guides'],                             'order' => 1],
                ['day' => 1, 'slot' => '09:30 – 11:00 IST', 'title' => 'Inaugural Ceremony: Celebrating Accessibility, Dignity & Diversity', 'cat' => 'Plenary',              'venue' => 'Main Auditorium',                  'desc' => "Opening keynote addresses celebrating Gujarat's inaugural Purple Festival edition and India's inclusive future under Viksit Bharat@2047.",                             'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Audio Description', 'Wheelchair Seating'],                  'order' => 2],
                ['day' => 1, 'slot' => '11:30 – 13:00 IST', 'title' => 'Panel 1: Universal Design & Accessible Infrastructure for 2047',     'cat' => 'Panel Discussion',     'venue' => 'Hall A',                           'desc' => 'Architects, urban planners, and disability rights leaders examine barrier-free cities, public transit, and digital public infrastructure.',                            'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Wheelchair Seating'],                                       'order' => 3],
                ['day' => 1, 'slot' => '14:00 – 16:30 IST', 'title' => 'Para Sports Championships — Opening Heats (25 Adaptive Disciplines)','cat' => 'Inclusive Sports',     'venue' => 'Sama Indoor Stadium',              'desc' => 'Adaptive athletics, wheelchair basketball, boccia, blind cricket demonstrations, and inclusive youth sports.',                                                        'services' => ['Step-Free Courts', 'Wheelchair Charging Bay', 'Medical Support', 'ISL Volunteers'],                         'order' => 4],
                ['day' => 1, 'slot' => '14:30 – 17:00 IST', 'title' => 'Assistive Technology & Inclusive Innovation Expo',                   'cat' => 'Exhibition',           'venue' => 'Innovation Pavilion',              'desc' => 'Hands-on demonstrations of screen readers, tactile graphics, mobility aids, AAC devices, and Indian assistive startups.',                                             'services' => ['Tactile Demos', 'ISL Interpretation', 'Accessible Counters'],                                               'order' => 5],
                ['day' => 1, 'slot' => '18:30 – 20:30 IST', 'title' => 'Evening Cultural Showcase: Disability Arts & Inclusive Theatre',     'cat' => 'Cultural Performance', 'venue' => 'Open-Air Stage',                   'desc' => "Performances by artists with and without disabilities celebrating Gujarat's folk heritage, contemporary dance, and sign-language music.",                             'services' => ['ISL Interpretation', 'Audio Description', 'Seizure-Safe Lighting', 'Sensory Sanctuary Nearby'],             'order' => 6],
                ['day' => 2, 'slot' => '09:00 – 11:00 IST', 'title' => 'Panel 2: Inclusive Education & NEP 2020 Implementation',             'cat' => 'Inclusive Education',  'venue' => 'MSU Baroda — Hall A',              'desc' => 'Educators, researchers, and students discuss Universal Design for Learning (UDL), teacher preparedness, and accessible classrooms.',                                  'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Large-Print Handouts'],                                     'order' => 7],
                ['day' => 2, 'slot' => '11:30 – 13:00 IST', 'title' => 'Panel 3: Rights, Legal Empowerment & the RPwD Act 2016',             'cat' => 'Panel Discussion',     'venue' => 'Hall B',                           'desc' => 'Legal scholars and DPO representatives examine reasonable accommodation, employment equity, and digital accessibility compliance.',                                   'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Braille Summary'],                                          'order' => 8],
                ['day' => 2, 'slot' => '14:00 – 16:00 IST', 'title' => 'Inclusive Film Festival & Filmmaker Dialogues',                      'cat' => 'Disability Arts',      'venue' => 'Screening Hall',                   'desc' => 'Curated short films and documentaries on disability leadership with closed captions and audio description.',                                                          'services' => ['Closed Captions', 'Audio Description', 'ISL Q&A'],                                                          'order' => 9],
                ['day' => 2, 'slot' => '16:30 – 18:30 IST', 'title' => 'Sitaare Zameen Par — Inclusive Celebrity Exhibition Match',          'cat' => 'Inclusive Sports',     'venue' => 'Sama Indoor Stadium',              'desc' => 'Flagship unified exhibition match bringing together para-athletes, youth players, and guest personalities at Sama Indoor Stadium.',                                   'services' => ['Wheelchair Seating', 'ISL Interpretation', 'Audio Commentary', 'Accessible Restrooms'],                     'order' => 10],
                ['day' => 3, 'slot' => '09:30 – 11:30 IST', 'title' => 'Inclusive Livelihoods, Entrepreneurship & Employer Conclave',        'cat' => 'Networking',           'venue' => 'Hall A',                           'desc' => 'Connecting talented persons with disabilities with inclusive employers, startups, and skill-development ecosystems.',                                                 'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Quiet Interview Pods'],                                     'order' => 11],
                ['day' => 3, 'slot' => '12:00 – 14:00 IST', 'title' => 'Para Sports Finals & Medal Ceremonies',                              'cat' => 'Inclusive Sports',     'venue' => 'Sama Indoor Stadium',              'desc' => 'Championship finals across adaptive disciplines celebrating athletic excellence and inclusive sportsmanship.',                                                        'services' => ['Step-Free Podium', 'ISL Interpretation', 'Medical Support'],                                                'order' => 12],
                ['day' => 3, 'slot' => '15:30 – 17:30 IST', 'title' => 'Valedictory Ceremony & Vadodara Declaration on Inclusion',           'cat' => 'Plenary',              'venue' => 'Main Auditorium',                  'desc' => 'Closing keynote, volunteer and SLI recognition, and adoption of the Purple Festival Gujarat 2027 Inclusion Charter.',                                                 'services' => ['ISL Interpretation', 'Live Captioning (CART)', 'Braille Charter', 'Wheelchair Seating'],                    'order' => 13],
            ];
            $pStmt = $pdo->prepare("
                INSERT IGNORE INTO programme_items
                (id, edition_id, day_number, time_slot, title, description, category, venue_name, speakers, accessibility_services, display_order, status, created_at, updated_at)
                VALUES (:id, :edition_id, :day, :slot, :title, :desc, :cat, :venue, '[]', :services, :order, 'PUBLISHED', :now, :now)
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
            }
        }

        // 12. CMS Home Page & Sections
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

        $hpLookup = $pdo->prepare("SELECT id FROM cms_pages WHERE slug = 'home' LIMIT 1");
        $hpLookup->execute();
        $hpRow = $hpLookup->fetch();
        $actualHomePageId = $hpRow['id'] ?? $homePageId;

        $sections = [
            [
                'id'       => '81000000-0000-4000-8000-000000000001',
                'key'      => 'hero',
                'title'    => 'PURPLE FESTIVAL GUJARAT 2027',
                'subtitle' => 'Where inclusion becomes a celebration.',
                'body'     => "India's landmark festival of accessibility, human dignity, assistive innovation, para-sports, and universal design across all 21 benchmark disabilities under the RPwD Act, 2016.",
                'data'     => ['dates' => '21–23 January 2027', 'location' => 'Vadodara, Gujarat', 'primaryCtaLabel' => 'Apply Now', 'primaryCtaHref' => '/apply'],
                'order'    => 1,
            ],
            [
                'id'       => '81000000-0000-4000-8000-000000000002',
                'key'      => 'about',
                'title'    => 'Inclusion Is Not Charity. It Is Culture, Innovation, and Right.',
                'subtitle' => 'First Edition in Gujarat — Vadodara, January 2027',
                'body'     => 'Purple Festival Gujarat 2027 brings together 5,000+ attendees, 500+ participants with and without disabilities, educators, para-athletes, artists, technologists, and policy leaders for three days of barrier-free celebration across Vadodara.',
                'data'     => ['stats' => [
                    ['label' => 'Expected Attendees',      'value' => '5,000+'],
                    ['label' => 'Benchmark Disabilities',  'value' => '21'],
                    ['label' => 'Para Sports Disciplines', 'value' => '25'],
                    ['label' => 'Days of Celebration',     'value' => '3'],
                ]],
                'order'    => 2,
            ],
            [
                'id'       => '81000000-0000-4000-8000-000000000003',
                'key'      => 'viksit-bharat',
                'title'    => 'A Viksit Bharat Must Be an Inclusive Bharat.',
                'subtitle' => "Aligned with India's Vision for 2047",
                'body'     => "India's vision for Viksit Bharat@2047 is about progress that reaches every citizen. Purple Festival Gujarat 2027 advances accessible education, dignified livelihoods, assistive technology, and equal cultural participation as pillars of an inclusive nation.",
                'data'     => ['disclaimer' => 'Purple Festival Gujarat 2027 contributes to the national conversation around inclusive development under Viksit Bharat@2047.'],
                'order'    => 3,
            ],
            [
                'id'       => '81000000-0000-4000-8000-000000000004',
                'key'      => 'contact',
                'title'    => 'Connect with the Festival Secretariat',
                'subtitle' => 'Accessibility, Registration, and Partnership Inquiries',
                'body'     => 'Reach our team for accommodation requests, institutional delegations, volunteer coordination, or media inquiries.',
                'data'     => [
                    'email'  => 'purplefestivalvadodara@gmail.com',
                    'phones' => [
                        ['name' => 'Radha Tandon',  'number' => '+91 99743 83920'],
                        ['name' => 'Satish Kapoor', 'number' => '+91 92117 60202'],
                    ],
                    'city'   => 'Vadodara, Gujarat, India',
                ],
                'order'    => 4,
            ],
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
        }

        // 13. FAQs
        $faqCount = (int)$pdo->query("SELECT COUNT(*) FROM faq_items")->fetchColumn();
        if ($faqCount === 0) {
            $faqs = [
                ['cat' => 'Registration',       'q' => 'Do I need to disclose a disability to attend Purple Festival Gujarat 2027?',                   'a' => 'No. Disability disclosure is completely optional and never restricts which category you may apply for. Persons with disabilities can register as Attendees, Delegates, Volunteers, or SLI Professionals.', 'order' => 1],
                ['cat' => 'Accessibility',      'q' => 'How do I request Indian Sign Language (ISL), Braille, or wheelchair accommodations?',          'a' => 'You can select your accommodation requirements during registration or update them anytime in your Participant Dashboard. Your requests are visible only to authorized accessibility coordinators.',       'order' => 2],
                ['cat' => 'QR Pass & Check-in', 'q' => 'How will I receive my digital QR entry pass?',                                                 'a' => 'Once your registration is confirmed (or your Volunteer/SLI application is approved), your unique digital QR pass is issued automatically in your Participant Dashboard and can be downloaded as an accessible PDF.', 'order' => 3],
                ['cat' => 'Volunteers & SLI',   'q' => 'Does submitting a Volunteer or SLI application mean automatic approval?',                      'a' => 'No. Volunteer and Indian Sign Language Interpreter applications undergo review by festival coordinators. Once approved, you will receive your role assignment and official digital credential.',          'order' => 4],
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
            }
        }

        // 14. Notification Templates
        $ntStmt = $pdo->prepare("
            INSERT IGNORE INTO notification_templates (id, code, subject, body_html, body_text, is_active, updated_at)
            VALUES (:id, :code, :subject, :body_html, :body_text, 1, :now)
        ");
        $templates = [
            ['id' => '83000000-0000-4000-8000-000000000001', 'code' => 'REGISTRATION_CONFIRMED', 'subject' => 'Confirmed: Your Purple Festival Gujarat 2027 Registration ({{registrationCode}})', 'html' => '<p>Dear {{fullName}},</p><p>Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Log in to your Participant Dashboard to download your accessible QR pass.</p>', 'text' => 'Dear {{fullName}}, Your registration ({{registrationCode}}) for Purple Festival Gujarat 2027 is confirmed. Log in to your Participant Dashboard to download your accessible QR pass.'],
            ['id' => '83000000-0000-4000-8000-000000000002', 'code' => 'APPLICATION_SUBMITTED',  'subject' => 'Application Received: Purple Festival Gujarat 2027 ({{registrationCode}})',          'html' => '<p>Dear {{fullName}},</p><p>We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.</p>',                                           'text' => 'Dear {{fullName}}, We have received your {{category}} application ({{registrationCode}}). Our review team will notify you once reviewed.'],
            ['id' => '83000000-0000-4000-8000-000000000003', 'code' => 'APPLICATION_APPROVED',   'subject' => 'Approved: Welcome to Purple Festival Gujarat 2027 ({{registrationCode}})',            'html' => '<p>Dear {{fullName}},</p><p>Congratulations! Your {{category}} application ({{registrationCode}}) has been approved. Your digital QR credential and duty assignment are now available in your dashboard.</p>', 'text' => 'Dear {{fullName}}, Your {{category}} application ({{registrationCode}}) has been approved. Your digital QR credential and duty assignment are now available in your dashboard.'],
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
        }

        // 15. System Settings
        $ssStmt = $pdo->prepare("
            INSERT IGNORE INTO system_settings (id, `key`, `value`, description, updated_at)
            VALUES (:id, :key, :value, :description, :now)
        ");
        $ssStmt->execute([
            ':id'          => '84000000-0000-4000-8000-000000000001',
            ':key'         => 'payments.enabled',
            ':value'       => jsonEncode(['enabled' => false, 'reason' => 'Awaiting organizer fee confirmation and Razorpay production keys.']),
            ':description' => 'Master toggle for paid registrations.',
            ':now'         => $now,
        ]);
        $ssStmt->execute([
            ':id'          => '84000000-0000-4000-8000-000000000002',
            ':key'         => 'sli.require_crr',
            ':value'       => jsonEncode(['required' => false, 'gujaratPriority' => true]),
            ':description' => 'Configurable RCI CRR requirement for SLI applicants.',
            ':now'         => $now,
        ]);

        // 16. Media Assets
        $mediaCount = (int)$pdo->query("SELECT COUNT(*) FROM media_assets")->fetchColumn();
        if ($mediaCount === 0) {
            $mediaItems = [
                ['file' => 'purple-festival.jpg', 'url' => '/assets/logos/purple-festival.jpg', 'alt' => 'Purple Festival Gujarat 2027 official emblem showing three inclusive figures beneath hearts', 'cat' => 'LOGO',    'src' => 'Official Purple Festival Organizer Asset',       'lic' => 'Authorized Festival Trademark'],
                ['file' => 'venue-01.jpg',        'url' => '/assets/gallery/venue-01.jpg',      'alt' => 'Wide view of the Vadodara sports stadium and athletic ground prepared for inclusive para-sports', 'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-05.jpg',        'url' => '/assets/gallery/venue-05.jpg',      'alt' => 'Indoor pavilion and accessible court facility in Vadodara for Purple Festival Gujarat 2027',     'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-10.jpg',        'url' => '/assets/gallery/venue-10.jpg',      'alt' => 'Outdoor sports field and pavilion lawn at The Maharaja Sayajirao University of Baroda',          'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-15.jpg',        'url' => '/assets/gallery/venue-15.jpg',      'alt' => 'Accessible campus walkway and entrance corridor at Vadodara festival venue',                    'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-20.jpg',        'url' => '/assets/gallery/venue-20.jpg',      'alt' => 'Sports complex seating and shaded pavilion area for spectators and para-athletes',              'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
                ['file' => 'venue-25.jpg',        'url' => '/assets/gallery/venue-25.jpg',      'alt' => 'Campus assembly area and shaded courtyard for workshops and community exhibitions',              'cat' => 'GALLERY', 'src' => 'Organizer Venue Reconnaissance — MSU Baroda', 'lic' => 'Organizer Supplied'],
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
            }
        }
    }
}

if (!function_exists('getActiveEdition')) {
    /**
     * Returns the active festival edition row ('gujarat-2027').
     */
    function getActiveEdition(?PDO $pdo = null): ?array
    {
        $pdo = $pdo ?? getDb();
        $stmt = $pdo->prepare("SELECT * FROM festival_editions WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => FESTIVAL_EDITION_SLUG]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $stmt = $pdo->query("SELECT * FROM festival_editions WHERE is_active = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch() : false;
        return $row ?: null;
    }
}

if (!function_exists('queueNotification')) {
    /**
     * Enqueues a notification job in `notification_queue` (`notification_jobs`).
     */
    function queueNotification(array $params, ?PDO $pdo = null): string
    {
        $pdo = $pdo ?? getDb();
        $templateCode = (string)($params['templateCode'] ?? $params['template_code'] ?? 'GENERIC');
        $recipientUserId = $params['recipientUserId'] ?? $params['recipient_user_id'] ?? null;
        $recipientEmail = (string)($params['recipientEmail'] ?? $params['recipient_email'] ?? '');
        $variables = is_array($params['variables'] ?? null) ? $params['variables'] : [];
        $subject = (string)($params['subject'] ?? $params['fallbackSubject'] ?? 'Purple Festival Gujarat 2027 Notification');
        $bodyHtml = $params['bodyHtml'] ?? null;
        $bodyText = $params['bodyText'] ?? null;

        $tplStmt = $pdo->prepare("SELECT * FROM notification_templates WHERE code = :code AND is_active = 1 LIMIT 1");
        $tplStmt->execute([':code' => $templateCode]);
        $tpl = $tplStmt->fetch();
        if ($tpl) {
            $subject = (string)$tpl['subject'];
            $bodyHtml = (string)$tpl['body_html'];
            $bodyText = (string)$tpl['body_text'];
            foreach ($variables as $k => $v) {
                $placeholder = '{{' . $k . '}}';
                $subject = str_replace($placeholder, (string)$v, $subject);
                $bodyHtml = str_replace($placeholder, htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $bodyHtml);
                $bodyText = str_replace($placeholder, (string)$v, $bodyText);
            }
        }

        $jobId = uuid4();
        $now = nowSql();
        $ins = $pdo->prepare("
            INSERT INTO notification_queue
            (id, template_code, recipient_user_id, recipient_email, subject, body_html, body_text, payload, status, attempts, max_attempts, created_at, updated_at)
            VALUES (:id, :template_code, :recipient_user_id, :recipient_email, :subject, :body_html, :body_text, :payload, 'QUEUED', 0, 3, :now, :now)
        ");
        $ins->execute([
            ':id'                => $jobId,
            ':template_code'     => $templateCode,
            ':recipient_user_id' => $recipientUserId,
            ':recipient_email'   => $recipientEmail,
            ':subject'           => $subject,
            ':body_html'         => $bodyHtml,
            ':body_text'         => $bodyText,
            ':payload'           => jsonEncode($variables),
            ':now'               => $now,
        ]);

        return $jobId;
    }
}
