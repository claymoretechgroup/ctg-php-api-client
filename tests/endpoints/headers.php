<?php
declare(strict_types=1);
// Headers endpoint — returns responses with duplicate headers for testing header parsing
// Returns hardcoded duplicate headers and multiple Set-Cookie headers

header('X-Duplicate: value1');
header('X-Duplicate: value2', false);  // false = don't replace
header('Set-Cookie: session=abc; Path=/', false);
header('Set-Cookie: theme=dark; Path=/', false);
header('Content-Type: application/json');

echo json_encode(['ok' => true]);
