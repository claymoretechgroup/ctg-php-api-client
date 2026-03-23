<?php
declare(strict_types=1);
// Upload endpoint — accepts file upload and returns file info
header('Content-Type: application/json');

if (empty($_FILES)) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$result = [
    'files' => [],
    'fields' => $_POST,
];

foreach ($_FILES as $fieldName => $file) {
    $result['files'][$fieldName] = [
        'name' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'error' => $file['error'],
    ];
}

echo json_encode($result);
