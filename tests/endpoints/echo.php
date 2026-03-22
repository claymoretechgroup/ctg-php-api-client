<?php
// Echo endpoint — returns request method, headers, body, params, and files
header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true);

echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => $jsonBody ?? $rawBody,
    'params' => $_GET,
    'files' => array_map(function($file) {
        return [
            'name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'error' => $file['error'],
        ];
    }, $_FILES),
]);
