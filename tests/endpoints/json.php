<?php
// JSON endpoint — returns a fixed JSON payload
header('Content-Type: application/json');

echo json_encode([
    'users' => [
        ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'active' => true],
        ['id' => 2, 'name' => 'Bob', 'role' => 'editor', 'active' => true],
        ['id' => 3, 'name' => 'Charlie', 'role' => 'viewer', 'active' => false],
    ],
]);
