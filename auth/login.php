<?php
declare(strict_types=1);

/**
 * /auth/login — legacy URL. Cleanly redirects to the participant sign-in page (/login),
 * preserving only a validated internal `next` destination. Staff destinations go to /staff/login.
 */

require_once dirname(__DIR__) . '/includes/config.php';

$nextRaw = isset($_GET['next']) ? (string)$_GET['next'] : (isset($_GET['redirect']) ? (string)$_GET['redirect'] : '');
$next = pfgValidateSafeRedirect($nextRaw, '');

$target = '/login';
if ($next !== '') {
    $isStaffArea = preg_match('#^/(admin|rgst|staff)(/|\?|$)#i', $next) === 1;
    $target = ($isStaffArea ? '/staff/login' : '/login') . '?next=' . rawurlencode($next);
}

header('Location: ' . $target, true, 302);
exit;
