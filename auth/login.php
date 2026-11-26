<?php
declare(strict_types=1);

/**
 * /auth/login -> auth/login.php
 * CAD-17: Redirects cleanly to the Staff Portal sign-in (/staff/login).
 */

require_once dirname(__DIR__) . '/includes/config.php';

$nextRaw = isset($_GET['next']) ? (string)$_GET['next'] : (isset($_GET['redirect']) ? (string)$_GET['redirect'] : '');
$safeNext = pfgValidateSafeRedirect($nextRaw, '');
$target = '/staff/login';
if ($safeNext !== '' && preg_match('#^/(admin|rgst)(/|\?|$)#i', $safeNext)) {
    $target .= '?next=' . rawurlencode($safeNext);
}

header('Location: ' . $target, true, 302);
exit;
