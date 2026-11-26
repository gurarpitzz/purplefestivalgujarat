<?php
declare(strict_types=1);
/** RETIRED ENDPOINT (HTTP 410 Gone) — CAD-17 Accountless Public Registration. Zero DB access. */
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'error'           => 'Participant account accommodation editing has been discontinued. Contact purplefestivalvadodara@gmail.com with your Registration ID for accessibility updates.',
    'deprecated'      => true,
    'accountRequired' => false,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
