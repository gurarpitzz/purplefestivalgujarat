<?php
declare(strict_types=1);

/**
 * Local Native PHP Built-in Server Router (mirrors Apache .htaccess rules for automated testing).
 * Usage: C:\xampp\php\php.exe -S 127.0.0.1:8088 tests/router.php
 */

$root = dirname(__DIR__);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$trimmed = ltrim($uri, '/');

// 1. Enforce .htaccess blocked directories & sensitive files (HTTP 403)
if (
    preg_match('#^(database|includes|docs|audit|project-docs|tests|scripts|data|node_modules)(/|$)#i', $trimmed)
    || preg_match('#(^|/)\.(?!well-known/)#', $uri)
    || preg_match('#(^|/)(README\.md|\.env.*|\.pfg27_env.*|\.user\.ini|composer\.(json|lock)|package(-lock)?\.json)$#i', $trimmed)
    || preg_match('#\.(sql|sqlite|sqlite3|db|log|md|yml|yaml|bak|old|orig|swp|tsbuildinfo)$#i', $trimmed)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden\n";
    return true;
}

// 2. Explicit clean page & API mappings matching .htaccess
$exactRoutes = [
    ''                            => 'index.php',
    'login'                       => 'login.php',
    'register'                    => 'register.php',
    'verify-email'                => 'verify-email.php',
    'forgot-password'             => 'forgot-password.php',
    'reset-password'              => 'reset-password.php',
    'staff/login'                 => 'staff/login.php',
    'auth/login'                  => 'auth/login.php',
    'programme'                   => 'programme.php',
    'accessibility'               => 'accessibility.php',
    'apply'                       => 'apply/index.php',
    'apply/attendee'              => 'apply/attendee.php',
    'apply/volunteer'             => 'apply/volunteer.php',
    'apply/sli'                   => 'apply/sli.php',
    'dashboard'                   => 'dashboard/index.php',
    'dashboard/pass-print'        => 'dashboard/pass-print.php',
    'admin'                       => 'admin/index.php',
    'rgst'                        => 'rgst/index.php',
    'legal/privacy'               => 'legal/privacy.php',
    'legal/terms'                 => 'legal/terms.php',
    'legal/accessibility-statement' => 'legal/accessibility-statement.php',
    'api/registrations'           => 'api/registrations/index.php',
    'api/attendance'              => 'api/attendance/index.php',
    'api/attendance/mark'         => 'api/attendance/mark.php',
    'api/attendance/correct'      => 'api/attendance/correct.php',
    'api/checkins'                => 'api/checkins/index.php',
    'api/checkins/confirm'        => 'api/checkins/confirm.php',
    'api/coupons/validate'        => 'api/coupons/validate.php',
    'api/qr/verify'               => 'api/qr/verify.php',
    'api/qr/pass'                 => 'api/qr/pass.php',
];

$clean = rtrim($trimmed, '/');
if (isset($exactRoutes[$clean])) {
    $_SERVER['SCRIPT_NAME'] = '/' . $exactRoutes[$clean];
    require $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $exactRoutes[$clean]);
    return true;
}

if (preg_match('#^api/qr/([A-Za-z0-9_-]+)$#', $clean, $m)) {
    $_GET['registrationId'] = $m[1];
    $_SERVER['SCRIPT_NAME'] = '/api/qr/pass.php';
    require $root . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'qr' . DIRECTORY_SEPARATOR . 'pass.php';
    return true;
}

$fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $trimmed);

// 3. Static asset pass-through
if ($trimmed !== '' && is_file($fullPath) && !str_ends_with(strtolower($fullPath), '.php')) {
    return false;
}

// 4. Direct PHP file
if ($trimmed !== '' && is_file($fullPath) && str_ends_with(strtolower($fullPath), '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $trimmed;
    require $fullPath;
    return true;
}

// 5. Extensionless directory index.php or file.php
if ($clean !== '' && is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean) . DIRECTORY_SEPARATOR . 'index.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $clean . '/index.php';
    require $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean) . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}
if ($clean !== '' && is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean) . '.php')) {
    $_SERVER['SCRIPT_NAME'] = '/' . $clean . '.php';
    require $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean) . '.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 Not Found\n";
return true;

