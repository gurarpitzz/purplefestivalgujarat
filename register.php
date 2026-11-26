<?php
declare(strict_types=1);

/**
 * /register -> register.php
 * CAD-17: Participant account creation is discontinued. Redirects directly to the
 * accountless public registration hub (/apply).
 */

header('Location: /apply', true, 302);
exit;
