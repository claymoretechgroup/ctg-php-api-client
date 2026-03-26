<?php
declare(strict_types=1);
// Redirect endpoint — returns a 302 redirect for testing redirect policy

http_response_code(302);
header('Location: /tests/endpoints/echo.php');
header('Content-Type: text/plain');

echo 'redirecting';
