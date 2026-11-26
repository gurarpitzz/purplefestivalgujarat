<?php
declare(strict_types=1);

/**
 * /login -> login.php
 * CAD-17: Participant login is discontinued; website login is strictly for authorized staff.
 * Redirects to /staff/login (preserving safe staff `next` destinations).
 */

require_once __DIR__ . '/includes/config.php';

$legacyToken = isset($_GET['resetToken']) ? trim((string)$_GET['resetToken']) : '';
if ($legacyToken !== '' && preg_match('/^[A-Za-z0-9_\-]{10,200}$/', $legacyToken) === 1) {
    header('Location: /reset-password?token=' . rawurlencode($legacyToken), true, 302);
    exit;
}

$nextRaw = isset($_GET['next']) ? (string)$_GET['next'] : (isset($_GET['redirect']) ? (string)$_GET['redirect'] : '');
$safeNext = pfgValidateSafeRedirect($nextRaw, '');
$target = '/staff/login';
if ($safeNext !== '' && preg_match('#^/(admin|rgst)(/|\?|$)#i', $safeNext)) {
    $target .= '?next=' . rawurlencode($safeNext);
}

header('Location: ' . $target, true, 302);
exit;
