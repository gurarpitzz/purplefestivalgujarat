<?php
declare(strict_types=1);

/**
 * GET / POST /api/auth/mfa
 * Discontinued under the Owner-Directed Authentication Policy Revision (supersedes D-20).
 * TOTP/authenticator-based MFA is no longer active for any account role.
 * Historical MFA database columns remain intact and inactive without destructive migrations.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

jsonResponse([
    'error'      => 'Authenticator-based multi-factor authentication has been discontinued under the revised authentication policy.',
    'deprecated' => true,
], 410);
