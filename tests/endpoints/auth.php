<?php
declare(strict_types=1);
// Auth endpoint — validates Bearer token presence and value
// Returns 401 if missing, 403 if wrong, 200 if valid
// The valid token for testing is "test-jwt-token-12345"
header('Content-Type: application/json');

$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(['error' => 'No authorization header']);
    exit;
}

if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid authorization scheme']);
    exit;
}

$token = substr($authHeader, 7);

if ($token !== 'test-jwt-token-12345') {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'token' => $token,
    'method' => $_SERVER['REQUEST_METHOD'],
]);
