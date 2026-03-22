<?php
// Status endpoint — returns the requested HTTP status code
// Usage: ?code=404 or ?code=500
$code = (int)($_GET['code'] ?? 200);
http_response_code($code);
header('Content-Type: application/json');

echo json_encode([
    'status' => $code,
    'message' => "Responded with status {$code}",
]);
