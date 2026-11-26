<?php
declare(strict_types=1);

/**
 * Purple Festival Gujarat 2027 — Core Configuration & Domain Constants
 * Native PHP + MySQL 8.0 / MariaDB (with automatic local SQLite PDO fallback)
 */

date_default_timezone_set('Asia/Kolkata');

// Enforce safe error handling across all Bluehost PHP handlers (FPM, LSAPI, CGI, mod_php)
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');

// ============================================================================
// 1. ENVIRONMENT & SECRETS LOADING (Bluehost / Local / Isolated Test Env)
// ============================================================================

$GLOBALS['pfgLoadedEnv'] = [];

$explicitEnvFile = getenv('PFG_ENV_FILE');
$candidateEnvFiles = [];
if (is_string($explicitEnvFile) && trim($explicitEnvFile) !== '') {
    $candidateEnvFiles[] = trim($explicitEnvFile);
} else {
    $candidateEnvFiles = [
        '/home1/tstpllmy/.pfg27_env.php',
        dirname(__DIR__, 2) . '/.pfg27_env.php',
        dirname(__DIR__) . '/.env.php',
    ];
}

foreach ($candidateEnvFiles as $envFile) {
    if (is_string($envFile) && is_file($envFile) && is_readable($envFile)) {
        $loaded = include $envFile;
        if (is_array($loaded)) {
            $GLOBALS['pfgLoadedEnv'] = array_merge($GLOBALS['pfgLoadedEnv'], $loaded);
        }
        break;
    }
}

if (!function_exists('pfgEnv')) {
    /**
     * Reads an environment setting from loaded .pfg27_env.php / .env.php array,
     * $_ENV, $_SERVER, or getenv().
     */
    function pfgEnv(string $key, string $default = ''): string
    {
        $loadedEnv = $GLOBALS['pfgLoadedEnv'] ?? [];

        if (is_array($loadedEnv) && array_key_exists($key, $loadedEnv) && $loadedEnv[$key] !== null && $loadedEnv[$key] !== '') {
            return (string)$loadedEnv[$key];
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }
        if (isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) && (string)$_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return (string)$val;
        }
        return $default;
    }
}

if (!defined('APP_ENV')) {
    $envRaw = strtolower(trim(pfgEnv('APP_ENV', '')));
    if ($envRaw === '') {
        $hostCheck = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $isLocalHost = PHP_SAPI === 'cli'
            || str_starts_with($hostCheck, 'localhost')
            || str_starts_with($hostCheck, '127.0.0.1');
        $envRaw = $isLocalHost ? 'development' : 'production';
    }
    define('APP_ENV', $envRaw);
}

if (!defined('IS_LOCAL_DEV_ENV')) {
    define('IS_LOCAL_DEV_ENV', in_array(APP_ENV, ['development', 'testing'], true));
}

if (!function_exists('pfgValidateSecret')) {
    /**
     * Enforces fail-closed secret validation: rejects empty, short, or known placeholder secrets.
     */
    function pfgValidateSecret(string $name, string $value): string
    {
        $trimmed = trim($value);
        $blockedPlaceholders = [
            'pfg2027-prod-auth-secret-change-in-env',
            'pfg2027-prod-qr-signing-secret-change-in-env',
            'dev-qr-secret-change-in-production',
            'changeme',
            'secret',
            'replace-me',
            'your_existing_auth_secret',
            'your_existing_qr_secret',
        ];
        if ($trimmed === '' || strlen($trimmed) < 16 || in_array(strtolower($trimmed), $blockedPlaceholders, true)) {
            throw new RuntimeException(
                sprintf('Critical security misconfiguration: %s must be configured with a strong secret (>= 16 chars) outside public_html.', $name)
            );
        }
        return $trimmed;
    }
}

if (!defined('DB_DRIVER')) {
    define('DB_DRIVER', strtolower(trim(pfgEnv('DB_DRIVER', 'mysql'))));
}
if (!defined('DB_HOST')) {
    define('DB_HOST', pfgEnv('DB_HOST', 'localhost'));
}
if (!defined('DB_PORT')) {
    define('DB_PORT', pfgEnv('DB_PORT', '3306'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', pfgEnv('DB_NAME', ''));
}
if (!defined('DB_USER')) {
    define('DB_USER', pfgEnv('DB_USER', ''));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', pfgEnv('DB_PASS', ''));
}
if (!defined('DB_SOCKET')) {
    define('DB_SOCKET', pfgEnv('DB_SOCKET', ''));
}
if (!defined('SQLITE_FALLBACK_PATH')) {
    define('SQLITE_FALLBACK_PATH', pfgEnv('SQLITE_FALLBACK_PATH', dirname(__DIR__) . '/tests/tmp/pfg27_test.sqlite'));
}
if (!defined('AUTH_SECRET')) {
    define('AUTH_SECRET', pfgValidateSecret('AUTH_SECRET', pfgEnv('AUTH_SECRET', '')));
}
if (!defined('QR_SIGNING_SECRET')) {
    define('QR_SIGNING_SECRET', pfgValidateSecret('QR_SIGNING_SECRET', pfgEnv('QR_SIGNING_SECRET', '')));
}
if (!defined('MFA_ENCRYPTION_KEY')) {
    $rawMfaKey = trim(pfgEnv('MFA_ENCRYPTION_KEY', ''));
    if ($rawMfaKey !== '') {
        define('MFA_ENCRYPTION_KEY', pfgValidateSecret('MFA_ENCRYPTION_KEY', $rawMfaKey));
        define('MFA_ENCRYPTION_KEY_DEDICATED', true);
    } else {
        // Domain-separated HKDF-style derivation from AUTH_SECRET when dedicated env key is not yet provisioned
        define('MFA_ENCRYPTION_KEY', hash_hmac('sha256', 'pfg27:mfa-encryption-key:v1', AUTH_SECRET));
        define('MFA_ENCRYPTION_KEY_DEDICATED', false);
    }
}
if (!defined('DATA_ENCRYPTION_KEY')) {
    $rawDataKey = trim(pfgEnv('DATA_ENCRYPTION_KEY', ''));
    if ($rawDataKey !== '') {
        define('DATA_ENCRYPTION_KEY', pfgValidateSecret('DATA_ENCRYPTION_KEY', $rawDataKey));
        define('DATA_ENCRYPTION_KEY_DEDICATED', true);
    } else {
        define('DATA_ENCRYPTION_KEY', hash_hmac('sha256', 'pfg27:sensitive-data-encryption-key:v1', AUTH_SECRET));
        define('DATA_ENCRYPTION_KEY_DEDICATED', false);
    }
}
if (!defined('APP_URL')) {
    $configuredUrl = trim(pfgEnv('APP_URL', pfgEnv('NEXT_PUBLIC_APP_URL', '')));
    if ($configuredUrl === '') {
        if (!IS_LOCAL_DEV_ENV) {
            throw new RuntimeException('Critical configuration error: APP_URL must be explicitly set in .pfg27_env.php (Host header fallback is disabled in staging/production).');
        }
        $detectedHost = (string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080');
        if (!preg_match('/^(localhost|127\.0\.0\.1)(:\d{1,5})?$/i', $detectedHost)) {
            $detectedHost = '127.0.0.1:8080';
        }
        $configuredUrl = 'http://' . $detectedHost;
    }
    define('APP_URL', rtrim($configuredUrl, '/'));
}

if (!defined('SESSION_COOKIE_NAME')) {
    define('SESSION_COOKIE_NAME', 'pfg_session');
}
if (!defined('SESSION_DURATION_SECONDS')) {
    define('SESSION_DURATION_SECONDS', 60 * 60 * 24 * 7); // 7 days
}
if (!defined('MAIL_DRIVER')) {
    // In development/testing default to safe local file sink; never contact Resend or PHP mail()
    define('MAIL_DRIVER', strtolower(trim(pfgEnv('MAIL_DRIVER', IS_LOCAL_DEV_ENV ? 'file' : 'resend'))));
}
if (!defined('MAIL_SINK_DIR')) {
    define('MAIL_SINK_DIR', pfgEnv('MAIL_SINK_DIR', dirname(__DIR__) . '/tests/tmp/mail-sink'));
}
if (!defined('RESEND_API_KEY')) {
    define('RESEND_API_KEY', pfgEnv('RESEND_API_KEY', ''));
}
if (!defined('EMAIL_FROM')) {
    define('EMAIL_FROM', pfgEnv('EMAIL_FROM', 'Purple Festival Gujarat 2027 <noreply@purplefestivalgujarat.com>'));
}

// ============================================================================
// 2. CORE HELPER FUNCTIONS
// ============================================================================

if (!function_exists('uuid4')) {
    /**
     * Generates a RFC 4122 v4 UUID string (36 chars).
     */
    function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('nowSql')) {
    /**
     * Returns current timestamp formatted for MySQL / SQLite DATETIME columns (YYYY-MM-DD HH:MM:SS).
     */
    function nowSql(?int $timestamp = null): string
    {
        return date('Y-m-d H:i:s', $timestamp ?? time());
    }
}

if (!function_exists('nowIso')) {
    /**
     * Returns ISO-8601 timestamp string in Asia/Kolkata.
     */
    function nowIso(?int $timestamp = null): string
    {
        return date('c', $timestamp ?? time());
    }
}

if (!function_exists('getIstDateString')) {
    /**
     * Formats a timestamp or date string into IST (Asia/Kolkata) YYYY-MM-DD for attendance day scoping.
     */
    function getIstDateString($date = null): string
    {
        $tz = new DateTimeZone('Asia/Kolkata');
        if ($date instanceof DateTimeInterface) {
            $dt = (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone($tz);
            return $dt->format('Y-m-d');
        }
        if (is_int($date)) {
            return (new DateTimeImmutable('@' . $date))->setTimezone($tz)->format('Y-m-d');
        }
        if (is_string($date) && trim($date) !== '') {
            try {
                return (new DateTimeImmutable($date, $tz))->setTimezone($tz)->format('Y-m-d');
            } catch (Throwable $e) {
                // fall through
            }
        }
        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }
}

if (!function_exists('formatIstTimestamp')) {
    /**
     * Formats a Date/string into human-readable IST timestamp for reception desk operators.
     */
    function formatIstTimestamp($date = null): string
    {
        $tz = new DateTimeZone('Asia/Kolkata');
        try {
            if ($date instanceof DateTimeInterface) {
                $dt = (new DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone($tz);
            } elseif (is_int($date)) {
                $dt = (new DateTimeImmutable('@' . $date))->setTimezone($tz);
            } elseif (is_string($date) && trim($date) !== '') {
                $dt = (new DateTimeImmutable($date, $tz))->setTimezone($tz);
            } else {
                $dt = new DateTimeImmutable('now', $tz);
            }
            return $dt->format('d M Y, h:i:s a') . ' IST';
        } catch (Throwable $e) {
            return date('d M Y, h:i:s a') . ' IST';
        }
    }
}

if (!function_exists('jsonEncode')) {
    /**
     * Safely encodes PHP values as UTF-8 JSON for database storage or API output.
     */
    function jsonEncode($value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded !== false ? $encoded : 'null';
    }
}

if (!function_exists('pfgSafeJsonForScript')) {
    /**
     * Safely encodes PHP values for embedding inside HTML <script> tags,
     * neutralizing </script>, HTML tags, quotes, and ampersands while preserving UTF-8 (Gujarati/Hindi).
     */
    function pfgSafeJsonForScript($value): string
    {
        $encoded = json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        return $encoded !== false ? $encoded : 'null';
    }
}

if (!function_exists('pfgValidateSafeUrl')) {
    /**
     * Validates that a URL uses an allowed scheme (http/https) or clean relative path (when permitted).
     * Rejects javascript:, data:, vbscript:, protocol-relative //, backslashes, and control characters.
     */
    function pfgValidateSafeUrl(string $url, bool $allowRelative = false): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed)) {
            return null;
        }
        if ($allowRelative && str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//')) {
            return $trimmed;
        }
        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string)parse_url($trimmed, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = (string)parse_url($trimmed, PHP_URL_HOST);
        if ($host === '') {
            return null;
        }
        return $trimmed;
    }
}

if (!function_exists('pfgValidateSafeRedirect')) {
    /**
     * Validates an internal redirect path (prevents open redirects, //host, /\, and CRLF injection).
     */
    function pfgValidateSafeRedirect(?string $path, string $fallback = '/admin'): string
    {
        if (!is_string($path)) {
            return $fallback;
        }
        $trimmed = trim($path);
        if ($trimmed === '' || !str_starts_with($trimmed, '/') || str_starts_with($trimmed, '//')) {
            return $fallback;
        }
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $trimmed)) {
            return $fallback;
        }
        if (preg_match('#^/[a-zA-Z0-9/_\-.?=&%+]*$#', $trimmed) !== 1) {
            return $fallback;
        }
        return $trimmed;
    }
}

if (!function_exists('pfgDeriveEncryptionKeyBytes')) {
    function pfgDeriveEncryptionKeyBytes(string $purpose): string
    {
        $source = $purpose === 'mfa' ? (string)MFA_ENCRYPTION_KEY : (string)DATA_ENCRYPTION_KEY;
        return hash_hmac('sha256', 'pfg27:aes256gcm:' . $purpose, $source, true);
    }
}

if (!function_exists('pfgEncryptValue')) {
    /**
     * Authenticated AES-256-GCM encryption with random 96-bit IV and 128-bit tag.
     * Format: enc:v1:<base64(iv[12] . tag[16] . ciphertext)>
     */
    function pfgEncryptValue(string $plaintext, string $purpose = 'data'): string
    {
        if ($plaintext === '') {
            return '';
        }
        if (str_starts_with($plaintext, 'enc:v1:')) {
            return $plaintext;
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL extension is required for authenticated encryption.');
        }
        $key = pfgDeriveEncryptionKeyBytes($purpose);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'pfg27:' . $purpose,
            16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Authenticated encryption failed.');
        }
        return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
    }
}

if (!function_exists('pfgDecryptValue')) {
    /**
     * Decrypts an enc:v1: AES-256-GCM payload; returns legacy plaintext untouched if not encrypted.
     */
    function pfgDecryptValue(?string $stored, string $purpose = 'data'): ?string
    {
        if ($stored === null || $stored === '') {
            return $stored;
        }
        if (!str_starts_with($stored, 'enc:v1:')) {
            return $stored;
        }
        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL extension is required for authenticated decryption.');
        }
        $raw = base64_decode(substr($stored, 7), true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $key = pfgDeriveEncryptionKeyBytes($purpose);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'pfg27:' . $purpose
        );
        return $plaintext === false ? null : $plaintext;
    }
}

if (!function_exists('pfgScrubSensitiveArray')) {
    /**
     * Recursively redacts secret/token/password keys before persisting audit or notification metadata.
     */
    function pfgScrubSensitiveArray(array $data): array
    {
        $clean = [];
        foreach ($data as $k => $v) {
            $keyStr = is_string($k) ? strtolower($k) : '';
            if (
                $keyStr !== '' &&
                preg_match('/(password|secret|rawtoken|reseturl|reset_url|token_hash|tokenhash|mfa_secret|otpauth|recovery_code|recoverycode|authorization|cookie|api_key|apikey)/i', $keyStr)
            ) {
                $clean[$k] = '[REDACTED]';
                continue;
            }
            if (is_array($v)) {
                $clean[$k] = pfgScrubSensitiveArray($v);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }
}

if (!function_exists('jsonDecode')) {
    /**
     * Safely decodes a JSON column or value, returning $default if null/invalid.
     */
    function jsonDecode($value, $default = [])
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE && $decoded !== null) ? $decoded : $default;
    }
}

if (!function_exists('getClientIp')) {
    function getClientIp(): ?string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if ($ip !== '') {
                return substr($ip, 0, 64);
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return substr(trim((string)$_SERVER['HTTP_X_REAL_IP']), 0, 64);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return substr(trim((string)$_SERVER['REMOTE_ADDR']), 0, 64);
        }
        return null;
    }
}

if (!function_exists('getClientUserAgent')) {
    function getClientUserAgent(): ?string
    {
        return !empty($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null;
    }
}

if (!function_exists('readRequestInput')) {
    /**
     * Reads request payload from JSON (php://input) or standard form POST ($_POST).
     */
    function readRequestInput(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }
        return [];
    }
}

// ============================================================================
// 3. FESTIVAL CONSTANTS & RPwD ACT 2016 TAXONOMY
// ============================================================================

if (!defined('FESTIVAL_EDITION_SLUG')) {
    define('FESTIVAL_EDITION_SLUG', 'gujarat-2027');
}
if (!defined('FESTIVAL_NAME')) {
    define('FESTIVAL_NAME', 'Purple Festival Gujarat 2027');
}
if (!defined('FESTIVAL_TAGLINE')) {
    define('FESTIVAL_TAGLINE', 'Where inclusion becomes a celebration.');
}
if (!defined('FESTIVAL_DATES_DISPLAY')) {
    define('FESTIVAL_DATES_DISPLAY', '21–23 January 2027');
}
if (!defined('FESTIVAL_LOCATION_DISPLAY')) {
    define('FESTIVAL_LOCATION_DISPLAY', 'Vadodara, Gujarat');
}

if (!defined('PERMISSIONS')) {
    define('PERMISSIONS', [
        'ACCOUNT_READ_OWN'       => 'account.read.own',
        'REGISTRATIONS_READ_ALL' => 'registrations.read.all',
        'REGISTRATIONS_WRITE'    => 'registrations.write',
        'REGISTRATIONS_EXPORT'   => 'registrations.export',
        'VOLUNTEERS_REVIEW'      => 'volunteers.review',
        'SLI_REVIEW'             => 'sli.review',
        'ACCOMMODATIONS_READ'    => 'accommodations.read',
        'ACCOMMODATIONS_MANAGE'  => 'accommodations.manage',
        'CMS_MANAGE'             => 'cms.manage',
        'ADMIN_ACCOUNTS_MANAGE'  => 'admin_accounts.manage',
        'CREDENTIALS_ISSUE'      => 'credentials.issue',
        'CREDENTIALS_REVOKE'     => 'credentials.revoke',
        'QR_SCAN'                => 'qr.scan',
        'CHECKINS_CONFIRM'       => 'checkins.confirm',
        'ATTENDANCE_READ_FULL'   => 'attendance.read.full',
        'ATTENDANCE_READ_OWN'    => 'attendance.read.own',
        'COUPONS_MANAGE'         => 'coupons.manage',
        'PAYMENTS_READ'          => 'payments.read',
        'AUDIT_LOGS_READ'        => 'audit_logs.read',
        'SETTINGS_MANAGE'        => 'settings.manage',
    ]);
}

if (!defined('DEFAULT_ROLE_PERMISSIONS')) {
    define('DEFAULT_ROLE_PERMISSIONS', [
        'SUPERADMIN' => array_values(PERMISSIONS),
        'ADMIN' => [
            PERMISSIONS['ACCOUNT_READ_OWN'],
            PERMISSIONS['REGISTRATIONS_READ_ALL'],
            PERMISSIONS['REGISTRATIONS_WRITE'],
            PERMISSIONS['REGISTRATIONS_EXPORT'],
            PERMISSIONS['VOLUNTEERS_REVIEW'],
            PERMISSIONS['SLI_REVIEW'],
            PERMISSIONS['ACCOMMODATIONS_READ'],
            PERMISSIONS['ACCOMMODATIONS_MANAGE'],
            PERMISSIONS['CREDENTIALS_ISSUE'],
            PERMISSIONS['CREDENTIALS_REVOKE'],
            PERMISSIONS['QR_SCAN'],
            PERMISSIONS['CHECKINS_CONFIRM'],
            PERMISSIONS['ATTENDANCE_READ_FULL'],
            PERMISSIONS['ATTENDANCE_READ_OWN'],
            PERMISSIONS['COUPONS_MANAGE'],
            PERMISSIONS['PAYMENTS_READ'],
        ],
        'RGST' => [
            PERMISSIONS['ACCOUNT_READ_OWN'],
            PERMISSIONS['QR_SCAN'],
            PERMISSIONS['CHECKINS_CONFIRM'],
            PERMISSIONS['ATTENDANCE_READ_OWN'],
        ],
        'PARTICIPANT' => [
            PERMISSIONS['ACCOUNT_READ_OWN'],
        ],
    ]);
}

if (!defined('RPWD_21_DISABILITIES')) {
    define('RPWD_21_DISABILITIES', [
        [
            'code' => 'BLINDNESS',
            'name' => 'Blindness',
            'rpwdGroup' => 'Visual Impairment',
            'description' => 'Total absence of sight, or visual acuity not exceeding 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 10 degrees.',
            'accommodationExamples' => [
                'Braille schedules and signage',
                'Screen-reader-compatible digital materials',
                'Sighted guides and tactile floor pathways',
                'Audio description during stage performances',
            ],
        ],
        [
            'code' => 'LOW_VISION',
            'name' => 'Low Vision',
            'rpwdGroup' => 'Visual Impairment',
            'description' => 'Visual acuity not exceeding 6/18 or less than 20/60 up to 3/60 or 10/200 (Snellen) in the better eye with best possible corrections, or limitation of the field of vision subtending an angle of less than 40 degrees up to 10 degrees.',
            'accommodationExamples' => [
                'Large-print schedules and high-contrast signage',
                'Front-row seating near screens',
                'Adjustable text size and high-contrast digital access',
            ],
        ],
        [
            'code' => 'LEPROSY_CURED',
            'name' => 'Leprosy Cured Persons',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'Persons cured of leprosy who continue to experience loss of sensation in hands or feet, paresis in the eye and eyelid, or manifest deformity that limits daily participation.',
            'accommodationExamples' => [
                'Ergonomic seating and minimal-grip utensils',
                'Accessible washrooms and step-free routes',
                'Dignified, stigma-free assistance desks',
            ],
        ],
        [
            'code' => 'HEARING_IMPAIRMENT',
            'name' => 'Hearing Impairment (Deaf and Hard of Hearing)',
            'rpwdGroup' => 'Hearing Impairment',
            'description' => 'Includes Deaf persons (70 dB or greater loss in speech frequencies in both ears) and Hard of Hearing persons (60 dB to 70 dB loss in speech frequencies in both ears).',
            'accommodationExamples' => [
                'Qualified Indian Sign Language (ISL) interpretation',
                'Live captioning (CART) on venue screens',
                'Visual alerts and well-lit front seating for lip-reading',
            ],
        ],
        [
            'code' => 'LOCOMOTOR_DISABILITY',
            'name' => 'Locomotor Disability',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'Inability to execute distinctive activities associated with movement of self and objects resulting from affliction of musculoskeletal or nervous system.',
            'accommodationExamples' => [
                'Step-free ramps (1:12 gradient) and elevators',
                'Wheelchair-accessible seating and charging bays',
                'Accessible restrooms and battery-operated venue shuttles',
            ],
        ],
        [
            'code' => 'DWARFISM',
            'name' => 'Dwarfism',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'A medical or genetic condition resulting in an adult height of 4 feet 10 inches (147 centimeters) or less.',
            'accommodationExamples' => [
                'Lowered registration and refreshment counters',
                'Accessible microphones and seating options',
                'Step-free access across all stages and pavilions',
            ],
        ],
        [
            'code' => 'INTELLECTUAL_DISABILITY',
            'name' => 'Intellectual Disability',
            'rpwdGroup' => 'Intellectual Disability',
            'description' => 'A condition characterized by significant limitation both in intellectual functioning (reasoning, learning, problem solving) and in adaptive behavior covering everyday social and practical skills.',
            'accommodationExamples' => [
                'Easy-read plain-language schedules with pictograms',
                'Trained orientation volunteers and buddy support',
                'Predictable signage and calm rest areas',
            ],
        ],
        [
            'code' => 'MENTAL_ILLNESS',
            'name' => 'Mental Illness',
            'rpwdGroup' => 'Mental Behavior',
            'description' => 'A substantial disorder of thinking, mood, perception, orientation, or memory that grossly impairs judgment, behavior, capacity to recognize reality, or ability to meet the ordinary demands of life.',
            'accommodationExamples' => [
                'Quiet sensory-friendly sanctuary spaces',
                'Flexible breaks without losing seating',
                'Trained psychosocial first-aid volunteers',
            ],
        ],
        [
            'code' => 'AUTISM_SPECTRUM_DISORDER',
            'name' => 'Autism Spectrum Disorder',
            'rpwdGroup' => 'Intellectual / Neurodevelopmental',
            'description' => 'A neuro-developmental condition typically appearing in the first three years of life that significantly affects verbal and non-verbal communication, social interaction, and sensory processing.',
            'accommodationExamples' => [
                'Low-stimulation sensory sanctuary rooms',
                'Noise-dampening headphones and visual schedules',
                'Advance venue walkthroughs and reduced-queue entry',
            ],
        ],
        [
            'code' => 'CEREBRAL_PALSY',
            'name' => 'Cerebral Palsy',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'A group of non-progressive neurological conditions affecting body movements and muscle coordination caused by damage to one or more specific areas of the brain.',
            'accommodationExamples' => [
                'Wide wheelchair pathways and supportive seating',
                'AAC (Augmentative & Alternative Communication) patience',
                'Accessible restrooms with caregiver space',
            ],
        ],
        [
            'code' => 'MUSCULAR_DYSTROPHY',
            'name' => 'Muscular Dystrophy',
            'rpwdGroup' => 'Locomotor Disability',
            'description' => 'A group of hereditary genetic muscle diseases that weaken the muscles that move the human body, characterized by progressive skeletal muscle weakness.',
            'accommodationExamples' => [
                'Power wheelchair charging bays',
                'Short-distance accessible drop-off points',
                'Climate-controlled rest zones',
            ],
        ],
        [
            'code' => 'CHRONIC_NEUROLOGICAL',
            'name' => 'Chronic Neurological Conditions',
            'rpwdGroup' => 'Chronic Neurological',
            'description' => 'Conditions originating in the central or peripheral nervous system (such as epilepsy, stroke aftermath, or neurodegenerative conditions) requiring ongoing management.',
            'accommodationExamples' => [
                'Seizure-safe lighting (no flashing strobes)',
                'Priority shaded seating and hydration stations',
                'On-site medical emergency response desk',
            ],
        ],
        [
            'code' => 'SPECIFIC_LEARNING_DISABILITIES',
            'name' => 'Specific Learning Disabilities',
            'rpwdGroup' => 'Intellectual / Neurodevelopmental',
            'description' => 'A heterogeneous group of conditions wherein there is a deficit in processing language, spoken or written, manifesting as difficulty in comprehending, reading, writing, spelling, or mathematical calculation.',
            'accommodationExamples' => [
                'Readable sans-serif typography and uncluttered layouts',
                'Audio and visual alternatives to dense text',
                'Untimed, save-and-resume registration workflows',
            ],
        ],
        [
            'code' => 'MULTIPLE_SCLEROSIS',
            'name' => 'Multiple Sclerosis',
            'rpwdGroup' => 'Chronic Neurological',
            'description' => 'An inflammatory, nervous system disease in which the myelin sheaths around the axons of nerve cells of the brain and spinal cord are damaged, affecting communication between brain and body.',
            'accommodationExamples' => [
                'Cooling and shaded rest areas to prevent heat fatigue',
                'Mobility assistance and priority seating',
                'Accessible restrooms near every hall',
            ],
        ],
        [
            'code' => 'SPEECH_AND_LANGUAGE',
            'name' => 'Speech and Language Disability',
            'rpwdGroup' => 'Speech & Language',
            'description' => 'A permanent disability arising out of conditions such as laryngectomy or aphasia affecting one or more components of speech and language.',
            'accommodationExamples' => [
                'Text, email, and digital chat support at help desks',
                'Extended speaking time and AAC-friendly microphones',
                'Written Q&A options during panel discussions',
            ],
        ],
        [
            'code' => 'THALASSEMIA',
            'name' => 'Thalassemia',
            'rpwdGroup' => 'Blood Disorder',
            'description' => 'A group of inherited disorders characterized by reduced or absent amounts of hemoglobin.',
            'accommodationExamples' => [
                'Priority seating to reduce standing fatigue',
                'Hydration and dietary accommodation support',
                'Quick access to medical assistance desks',
            ],
        ],
        [
            'code' => 'HEMOPHILIA',
            'name' => 'Hemophilia',
            'rpwdGroup' => 'Blood Disorder',
            'description' => 'An inheritable disease, usually affecting males, characterized by impairment of the normal clotting ability of blood.',
            'accommodationExamples' => [
                'Safe, uncrowded pathways and priority entry lanes',
                'On-site medical room awareness and rapid first aid',
                'Accessible seating near aisles',
            ],
        ],
        [
            'code' => 'SICKLE_CELL_DISEASE',
            'name' => 'Sickle Cell Disease',
            'rpwdGroup' => 'Blood Disorder',
            'description' => 'A hemolytic disorder characterized by chronic anemia, painful events, and various complications due to associated tissue and organ damage.',
            'accommodationExamples' => [
                'Shaded rest lounges and continuous hydration stations',
                'Minimal walking distances with shuttle support',
                'Medical assistance readiness',
            ],
        ],
        [
            'code' => 'MULTIPLE_DISABILITIES',
            'name' => 'Multiple Disabilities (including Deafblindness)',
            'rpwdGroup' => 'Multiple Disabilities',
            'description' => 'Two or more of the specified disabilities occurring together, including deafblindness — a combination of hearing and visual impairments causing severe communication and developmental needs.',
            'accommodationExamples' => [
                'Tactile sign language interpreters and intervener support',
                'Combined physical, sensory, and communication accommodations',
                'Dedicated companion / escort seating',
            ],
        ],
        [
            'code' => 'ACID_ATTACK_VICTIM',
            'name' => 'Acid Attack Victim',
            'rpwdGroup' => 'Locomotor / Physical',
            'description' => 'A person disfigured due to violent assaults by throwing of acid or similar corrosive substance.',
            'accommodationExamples' => [
                'Climate-controlled, sun-protected spaces',
                'Visual or mobility accommodations as individually requested',
                'Respectful, privacy-preserving check-in and support',
            ],
        ],
        [
            'code' => 'PARKINSONS_DISEASE',
            'name' => "Parkinson's Disease",
            'rpwdGroup' => 'Chronic Neurological',
            'description' => 'A progressive disease of the nervous system marked by tremor, muscular rigidity, and slow, imprecise movement.',
            'accommodationExamples' => [
                'Large touch targets and keyboard-accessible controls',
                'Seated check-in desks and handrails along walkways',
                'Untimed forms and mobility assistance',
            ],
        ],
    ]);
}

if (!defined('ACCOMMODATION_TYPES_SEED')) {
    define('ACCOMMODATION_TYPES_SEED', [
        ['code' => 'ISL_INTERPRETER',        'label' => 'Indian Sign Language (ISL) Interpreter'],
        ['code' => 'TACTILE_INTERPRETATION', 'label' => 'Tactile Interpretation (Deafblind support)'],
        ['code' => 'BRAILLE_SCHEDULE',       'label' => 'Braille Schedule & Programme'],
        ['code' => 'LARGE_PRINT',            'label' => 'Large-Print Material'],
        ['code' => 'SCREEN_READER_DIGITAL',  'label' => 'Screen-Reader-Compatible Digital Material'],
        ['code' => 'WHEELCHAIR_SEATING',     'label' => 'Wheelchair-Accessible Seating'],
        ['code' => 'WHEELCHAIR_CHARGING',    'label' => 'Wheelchair Charging Bay'],
        ['code' => 'SIGHTED_GUIDE',          'label' => 'Sighted Guide'],
        ['code' => 'SENSORY_SANCTUARY',      'label' => 'Sensory Sanctuary Access'],
        ['code' => 'CAPTIONING',             'label' => 'Live Captioning (CART)'],
        ['code' => 'MOBILITY_ASSISTANCE',    'label' => 'Mobility Assistance / Venue Shuttle'],
        ['code' => 'DIETARY',                'label' => 'Dietary Accommodation'],
        ['code' => 'OTHER',                  'label' => 'Other Accessibility Requirement'],
    ]);
}

if (!defined('OCCUPATION_OPTIONS')) {
    define('OCCUPATION_OPTIONS', [
        'Student',
        'Teacher',
        'Academic',
        'Researcher',
        'NGO Professional',
        'Government Official',
        'Healthcare Professional',
        'Corporate Professional',
        'Accessibility Professional',
        'Artist',
        'Entrepreneur',
        'Other',
    ]);
}

if (!defined('FESTIVAL_DAYS')) {
    define('FESTIVAL_DAYS', [
        ['code' => 'DAY_1', 'label' => 'Day 1 — 21 January 2027', 'isoDate' => '2027-01-21'],
        ['code' => 'DAY_2', 'label' => 'Day 2 — 22 January 2027', 'isoDate' => '2027-01-22'],
        ['code' => 'DAY_3', 'label' => 'Day 3 — 23 January 2027', 'isoDate' => '2027-01-23'],
    ]);
}
