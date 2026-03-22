# ctg-php-api-client — Library Specification

## Overview

A minimal HTTP client for consuming RESTful APIs from PHP. Built on
cURL with JSON as the default content type. Supports all REST methods,
JWT bearer token authentication, file uploads, configurable headers,
and base URL prefixing. Responses are returned as structured arrays
with status, headers, and parsed body.

No Guzzle. No PSR-7. No middleware stack. One class, configure it,
call it, get data back.

---

## Design Principles

1. **Static primitive, instance convenience** — `request()` is a
   static method that handles raw cURL execution with no state.
   Instance methods add base URL, default headers, and token
   management on top
2. **Base URL + path** — set the API root once, append resource paths
   per request
3. **JSON by default** — request bodies are encoded, response bodies
   are decoded automatically
4. **Multipart when needed** — if the body contains a `CURLFile`,
   auto-switch to `multipart/form-data`
5. **JWT built in** — set a bearer token and it's included on every
   request until cleared
6. **Per-request headers** — every method accepts an optional headers
   array that merges on top of defaults for that call only
7. **Structured responses** — every request returns the same shape:
   status code, headers, body, and success flag
8. **No exceptions on HTTP errors** — a 404 is a valid response, not
   an exception. Exceptions are for transport failures (timeout,
   DNS, connection refused)
9. **Composable with CTGFnprog** — response bodies are arrays, ready
   for pipeline transforms

---

## Class Interface

```php
namespace CTG\ApiClient;

class CTGAPIClient
{
    // ─── Construction ──────────────────────────────────────

    // CONSTRUCTOR :: STRING, ARRAY -> $this
    // Creates a client with a base URL and optional config
    public function __construct(string $baseUrl, array $config = []);

    // Static Factory Method :: STRING, ARRAY -> ctgapiClient
    public static function init(string $baseUrl, array $config = []): static;

    // ─── Authentication ────────────────────────────────────

    // :: STRING -> $this
    // Set the JWT bearer token for subsequent requests
    public function setToken(string $token): static;

    // :: VOID -> $this
    // Clear the current bearer token
    public function clearToken(): static;

    // :: VOID -> ?STRING
    // Get the current bearer token
    public function getToken(): ?string;

    // ─── Headers ───────────────────────────────────────────

    // :: STRING, STRING -> $this
    // Set a default header for all subsequent requests
    public function setHeader(string $name, string $value): static;

    // :: ARRAY<STRING, STRING> -> $this
    // Set multiple default headers at once
    public function setHeaders(array $headers): static;

    // :: STRING -> $this
    // Remove a default header
    public function removeHeader(string $name): static;

    // ─── HTTP Methods (Instance) ───────────────────────────

    // :: STRING, ARRAY, ARRAY -> ARRAY
    // GET request with optional query parameters and per-request headers
    public function GET(string $path, array $params = [], array $headers = []): array;

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // POST request with body, optional query parameters, and per-request headers
    public function POST(string $path, array $body = [], array $params = [], array $headers = []): array;

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // PUT request with body, optional query parameters, and per-request headers
    public function PUT(string $path, array $body = [], array $params = [], array $headers = []): array;

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // PATCH request with body, optional query parameters, and per-request headers
    public function PATCH(string $path, array $body = [], array $params = [], array $headers = []): array;

    // :: STRING, ARRAY, ARRAY -> ARRAY
    // DELETE request with optional query parameters and per-request headers
    public function DELETE(string $path, array $params = [], array $headers = []): array;

    // ─── Uploads ───────────────────────────────────────────

    // :: STRING, STRING, ARRAY, STRING -> ARRAY
    // Upload a file with optional extra form fields
    public function upload(
        string $path,
        string $filePath,
        array  $fields = [],
        string $fieldName = 'file'
    ): array;

    // ─── Static Request ────────────────────────────────────

    // :: STRING, STRING, ARRAY, ARRAY, ARRAY, INT -> ARRAY
    // Stateless cURL execution — the primitive everything delegates to
    public static function request(
        string $method,
        string $url,
        array  $body = [],
        array  $params = [],
        array  $headers = [],
        int    $timeout = 30
    ): array;
}
```

---

## Two Entry Points

### Instance — Managed State

The instance holds base URL, default headers, token, and timeout.
Instance methods (`GET`, `POST`, etc.) merge per-request headers on
top of defaults, prepend the base URL, inject the Authorization
header, and delegate to the static `request()`.

```php
$api = CTGAPIClient::init('https://api.example.com')
    ->setToken($jwt)
    ->setHeader('Accept-Language', 'en');

$users = $api->GET('/users');
$result = $api->POST('/users', ['name' => 'Alice']);
```

### Static — Stateless One-Off

The static `request()` method takes everything it needs — full URL,
method, body, params, headers, timeout. No instance, no setup. Useful
for fire-and-forget calls or scripts that don't need a client instance.

```php
$result = CTGAPIClient::request('GET', 'https://api.example.com/users', [], [], [
    'Authorization' => 'Bearer ' . $jwt,
    'Accept-Language' => 'en',
]);

$result = CTGAPIClient::request('POST', 'https://httpbin.org/post', [
    'name' => 'Alice',
]);
```

### How Instance Methods Delegate

Each instance method builds the full URL, merges headers (defaults +
token + per-request), and calls the static `request()`:

```php
public function GET(string $path, array $params = [], array $headers = []): array {
    return self::request(
        'GET',
        $this->_buildUrl($path),
        [],
        $params,
        $this->_mergeHeaders($headers),
        $this->_timeout
    );
}
```

---

## Constructor & Factory

```php
// Basic — just a base URL
$api = CTGAPIClient::init('https://api.example.com');

// With options
$api = CTGAPIClient::init('https://api.example.com', [
    'timeout' => 30,              // request timeout in seconds (default: 30)
    'headers' => [                // default headers for all requests
        'Accept-Language' => 'en',
    ],
]);

// With JWT from the start
$api = CTGAPIClient::init('https://api.example.com')
    ->setToken($jwt);
```

The base URL is stored and prepended to every request path. Trailing
slashes on the base URL and leading slashes on paths are normalized
so `init('https://api.example.com/')` and `->GET('/users')` produce
`https://api.example.com/users`.

---

## Authentication

### Bearer Token (JWT)

```php
$api = CTGAPIClient::init('https://api.example.com');

// Set token — included as Authorization: Bearer <token> on all requests
$api->setToken($jwt);

// Make authenticated requests
$users = $api->GET('/users');
$profile = $api->GET('/me');

// Clear token
$api->clearToken();

// Check current token
$token = $api->getToken(); // null after clearing
```

The token is sent as `Authorization: Bearer <token>`. Setting a new
token replaces the previous one. Clearing the token removes the
Authorization header from subsequent requests.

---

## Per-Request Headers

Every instance method accepts an optional `$headers` array as its
last parameter. These merge on top of the client's default headers
for that single request without modifying the client's state.

```php
$api = CTGAPIClient::init('https://api.example.com')
    ->setHeader('Accept-Language', 'en');

// Per-request header — overrides or supplements defaults
$result = $api->GET('/users', [], ['X-Request-Id' => 'abc123']);

// Override a default for one call
$result = $api->GET('/users', [], ['Accept-Language' => 'fr']);

// Defaults are unchanged for the next call
$result = $api->GET('/users');  // Accept-Language is still 'en'
```

Per-request headers take precedence over defaults when both specify
the same header name.

---

## Request Methods

All HTTP methods return the same response structure. Methods with
bodies (POST, PUT, PATCH) JSON-encode the body automatically unless
the body contains a `CURLFile` instance, in which case it is sent as
`multipart/form-data`.

### GET

```php
$users = $api->GET('/users');

// With query parameters
$admins = $api->GET('/users', ['role' => 'admin', 'active' => 1]);

// With per-request headers
$result = $api->GET('/users', [], ['If-None-Match' => '"etag123"']);
```

### POST

```php
// JSON body (default)
$result = $api->POST('/users', [
    'email' => 'alice@example.com',
    'name' => 'Alice',
    'role' => 'admin'
]);

// Multipart — auto-detected when body contains CURLFile
$result = $api->POST('/documents', [
    'file' => new \CURLFile('/tmp/report.pdf'),
    'title' => 'Q4 Report',
]);

// With per-request header
$result = $api->POST('/users', $data, [], ['X-Idempotency-Key' => $key]);
```

### PUT

```php
$result = $api->PUT('/users/42', [
    'email' => 'alice@example.com',
    'name' => 'Alicia',
]);
```

### PATCH

```php
$result = $api->PATCH('/users/42', [
    'name' => 'Alicia',
]);
```

### DELETE

```php
$result = $api->DELETE('/users/42');

// With query parameters
$result = $api->DELETE('/users/42', ['force' => 'true']);
```

### Static request()

For one-off requests without a client instance, or non-standard
HTTP methods:

```php
// One-off with full URL
$result = CTGAPIClient::request('GET', 'https://api.example.com/health');

// Non-standard method via instance (delegates to static)
$result = CTGAPIClient::request('OPTIONS', 'https://api.example.com/users');

// With headers and timeout
$result = CTGAPIClient::request('POST', 'https://api.example.com/webhook', [
    'event' => 'deploy',
], [], [
    'Authorization' => 'Bearer ' . $token,
    'X-Webhook-Secret' => $secret,
], 10);
```

---

## File Uploads

### upload() Convenience Method

Wraps `CURLFile` construction so callers don't need to build it:

```php
// Simple upload
$result = $api->upload('/documents', '/tmp/report.pdf');

// With extra form fields
$result = $api->upload('/documents', '/tmp/report.pdf', [
    'title' => 'Q4 Report',
    'category' => 'financial',
]);

// Custom field name (default is 'file')
$result = $api->upload('/avatars', '/tmp/photo.jpg', [], 'avatar');
```

### Implementation

`upload()` delegates to `POST()` with a `CURLFile` in the body:

```php
public function upload(
    string $path,
    string $filePath,
    array  $fields = [],
    string $fieldName = 'file'
): array {
    $fields[$fieldName] = new \CURLFile($filePath);
    return $this->POST($path, $fields);
}
```

### Multipart Detection

`request()` checks whether the body contains any `CURLFile` instances.
If so, the body is passed directly to cURL (which handles multipart
encoding natively). If not, the body is JSON-encoded:

```php
if (self::_hasFile($body)) {
    // Pass body as-is — cURL handles multipart/form-data
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
} else {
    // JSON-encode
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    // Content-Type: application/json set in headers
}
```

---

## Response Structure

Every request returns the same shape:

```php
[
    'status' => 200,
    'ok' => true,
    'headers' => [
        'content-type' => 'application/json',
        'x-request-id' => 'abc123',
    ],
    'body' => [
        'id' => 42,
        'email' => 'alice@example.com',
        'name' => 'Alice',
    ]
]
```

| Key | Type | Description |
|-----|------|-------------|
| `status` | INT | HTTP status code |
| `ok` | BOOL | `true` if status is 200-299 |
| `headers` | ARRAY<STRING, STRING> | Response headers (lowercase keys) |
| `body` | MIXED | Parsed JSON body, or raw string if not JSON |

The `ok` flag is a convenience — the client does not throw on HTTP
error codes. A 404 response has `ok => false` and `status => 404`
with the response body available for inspection.

---

## Error Handling — CTGAPIClientError

Transport-level failures throw `CTGAPIClientError`. HTTP error
responses (4xx, 5xx) do not throw — they return normally with
`ok => false`.

### CTGAPIClientError Class

```php
namespace CTG\ApiClient;

class CTGAPIClientError extends \Exception
{
    const TYPES = [
        'CONNECTION_FAILED'  => 1000,
        'TIMEOUT'            => 1001,
        'DNS_FAILED'         => 1002,
        'SSL_ERROR'          => 1003,
        'REQUEST_FAILED'     => 2000,
        'INVALID_JSON'       => 2001,
        'INVALID_URL'        => 3000,
        'INVALID_METHOD'     => 3001,
        'HTTP_ERROR'         => 4000,
    ];

    public readonly string $type;
    public readonly string $msg;
    public readonly mixed  $data;

    private bool $_handled = false;

    // CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> $this
    public function __construct(
        string|int $type,
        ?string    $msg = null,
        mixed      $data = null
    );

    // :: STRING|INT -> INT|STRING|NULL
    public static function lookup(string|int $key): int|string|null;

    // :: STRING|INT, (ctgapiClientError -> VOID) -> $this
    public function on(string|int $type, callable $handler): static;

    // :: (ctgapiClientError -> VOID) -> VOID
    public function otherwise(callable $handler): void;
}
```

### When Exceptions Are Thrown

| Scenario | Error Type |
|----------|-----------|
| Connection refused, host unreachable | `CONNECTION_FAILED` |
| Request timed out | `TIMEOUT` |
| DNS resolution failed | `DNS_FAILED` |
| SSL certificate error | `SSL_ERROR` |
| cURL error (other) | `REQUEST_FAILED` |
| Malformed URL constructed | `INVALID_URL` |
| Empty or invalid HTTP method | `INVALID_METHOD` |

### When Exceptions Are NOT Thrown (Automatically)

HTTP error responses (4xx, 5xx) return normally. The library never
throws on HTTP status codes — that decision belongs to the caller.

| Scenario | Returned |
|----------|---------|
| 400 Bad Request | `['status' => 400, 'ok' => false, 'body' => ...]` |
| 401 Unauthorized | `['status' => 401, 'ok' => false, 'body' => ...]` |
| 403 Forbidden | `['status' => 403, 'ok' => false, 'body' => ...]` |
| 404 Not Found | `['status' => 404, 'ok' => false, 'body' => ...]` |
| 500 Internal Server Error | `['status' => 500, 'ok' => false, 'body' => ...]` |

### Caller-Initiated HTTP Error Handling

Callers can check `ok` and throw `HTTP_ERROR` to use the same
chainable error handling pattern for both transport and HTTP failures.
The full response is available in `$e->data`:

```php
try {
    $result = $api->GET('/users');
    if (!$result['ok']) {
        throw new CTGAPIClientError('HTTP_ERROR',
            "Request failed with status {$result['status']}",
            $result
        );
    }
    // use $result['body']
} catch (CTGAPIClientError $e) {
    $e->on('HTTP_ERROR', function($e) {
            match($e->data['status']) {
                401 => redirectToLogin(),
                403 => showForbidden(),
                404 => showNotFound(),
                default => logError($e),
            };
        })
      ->on('TIMEOUT', fn($e) => retryLater())
      ->on('CONNECTION_FAILED', fn($e) => useCache())
      ->otherwise(fn($e) => throw $e);
}
```

This keeps the library unopinionated — it returns every HTTP response
as data — while giving callers a clean path to escalate HTTP errors
into the same error handling flow as transport failures.

---

## Headers

### Default Headers

```php
$api = CTGAPIClient::init('https://api.example.com')
    ->setHeader('X-Api-Key', 'abc123')
    ->setHeader('Accept-Language', 'en');

// Or set multiple at once
$api->setHeaders([
    'X-Api-Key' => 'abc123',
    'Accept-Language' => 'en',
]);

// Remove a default header
$api->removeHeader('Accept-Language');
```

### Header Merge Order

When a request is made, headers are merged in this order (later
entries override earlier ones with the same name):

1. **Automatic headers** — `Content-Type: application/json` for JSON
   bodies, `Authorization: Bearer <token>` when a token is set
2. **Default headers** — set via `setHeader()` / `setHeaders()`
3. **Per-request headers** — passed as the last argument to `GET()`,
   `POST()`, etc.

This means per-request headers always win, defaults override
automatic headers, and automatic headers are the baseline.

---

## Integration with CTGFnprog

Response bodies are arrays — ready for CTGFnprog transforms:

```php
use CTG\FnProg\CTGFnprog;

$api = CTGAPIClient::init('https://api.example.com')
    ->setToken($jwt);

$activeAdmins = CTGFnprog::pipe([
    fn($_) => $api->GET('/users'),
    fn($r) => $r['body'],
    CTGFnprog::filter(fn($u) => $u['active'] && $u['role'] === 'admin'),
    CTGFnprog::pick(['id', 'name', 'email']),
    CTGFnprog::sortBy('name'),
])();
```

---

## Testing Strategy

Tests run against the staging Apache environment. Test endpoint
scripts live in `tests/endpoints/` and are served by the staging
web container.

### Test Endpoint Scripts

Simple PHP scripts that simulate API behavior:

```
tests/endpoints/
├── echo.php          # Returns request method, headers, body, params
├── status.php        # Returns a specific HTTP status code (?code=404)
├── auth.php          # Validates Bearer token, returns 401 if missing
├── upload.php        # Accepts file upload, returns file info
└── json.php          # Returns a fixed JSON payload
```

These scripts are mounted into the staging web container via the
volume mapping. Each script returns a JSON response so the test
can verify both the request and response handling.

### echo.php Example

```php
<?php
header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'body' => json_decode(file_get_contents('php://input'), true),
    'params' => $_GET,
    'files' => $_FILES,
]);
```

---

## Internal Implementation

### Static request() — The Primitive

All cURL logic lives in the static `request()` method. It accepts
everything needed for a complete HTTP request — no instance state.

```php
public static function request(
    string $method,
    string $url,
    array  $body = [],
    array  $params = [],
    array  $headers = [],
    int    $timeout = 30
): array;
```

**cURL configuration:**

```php
CURLOPT_RETURNTRANSFER => true
CURLOPT_FOLLOWLOCATION => true
CURLOPT_MAXREDIRS      => 5
CURLOPT_TIMEOUT        => $timeout
CURLOPT_HEADER         => true
CURLOPT_HTTPHEADER     => [...]
CURLOPT_CUSTOMREQUEST  => $method
```

**Query parameters** are appended to the URL:

```php
if (!empty($params)) {
    $url .= '?' . http_build_query($params);
}
```

**Body encoding** — multipart if CURLFile detected, JSON otherwise:

```php
if (self::_hasFile($body)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
} else {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
}
```

**Content-Type** is set to `application/json` automatically for JSON
bodies, unless the caller provides their own `Content-Type` header.
For multipart bodies, cURL sets the Content-Type with the boundary
automatically.

### Instance URL Building

```php
private function _buildUrl(string $path): string {
    return rtrim($this->_baseUrl, '/') . '/' . ltrim($path, '/');
}
```

### Instance Header Merging

```php
private function _mergeHeaders(array $perRequest = []): array {
    $headers = [];

    // Automatic headers
    if ($this->_token !== null) {
        $headers['Authorization'] = "Bearer {$this->_token}";
    }

    // Defaults
    foreach ($this->_headers as $name => $value) {
        $headers[$name] = $value;
    }

    // Per-request (highest priority)
    foreach ($perRequest as $name => $value) {
        $headers[$name] = $value;
    }

    return $headers;
}
```

### Response Parsing

```php
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headerStr  = substr($response, 0, $headerSize);
$bodyStr    = substr($response, $headerSize);

// Parse headers into associative array (lowercase keys)
// Decode body as JSON, fall back to raw string
```

### Multipart Detection

```php
private static function _hasFile(array $body): bool {
    foreach ($body as $value) {
        if ($value instanceof \CURLFile) {
            return true;
        }
    }
    return false;
}
```

### cURL Error Mapping

```php
$errno = curl_errno($ch);
$type = match($errno) {
    CURLE_COULDNT_CONNECT
        => 'CONNECTION_FAILED',
    CURLE_OPERATION_TIMEDOUT
        => 'TIMEOUT',
    CURLE_COULDNT_RESOLVE_HOST
        => 'DNS_FAILED',
    CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM
        => 'SSL_ERROR',
    default
        => 'REQUEST_FAILED',
};
```

---

## File Structure

```
ctg-php-api-client/
├── composer.json
├── docs/
│   └── spec.md
├── src/
│   ├── CTGAPIClient.php
│   └── CTGAPIClientError.php
├── tests/
│   ├── endpoints/
│   │   ├── echo.php
│   │   ├── status.php
│   │   ├── auth.php
│   │   ├── upload.php
│   │   └── json.php
│   ├── CTGAPIClientErrorTest.php
│   └── CTGAPIClientTest.php
├── staging/
└── README.md
```

### composer.json

```json
{
    "name": "ctg/php-api-client",
    "description": "Minimal REST API client with JWT auth and file uploads for PHP",
    "type": "library",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "CTG\\ApiClient\\": "src/"
        }
    },
    "require": {
        "php": ">=8.1",
        "ext-curl": "*",
        "ext-json": "*"
    }
}
```

---

## Implementation Order

1. **CTGAPIClientError** — standalone error class (same pattern as CTGDBError)
2. **Test endpoint scripts** — echo, status, auth, upload, json
3. **Static request()** — core cURL execution, multipart detection,
   response parsing, error mapping
4. **Constructor + init()** — base URL, config, header/token storage
5. **Header management** — setHeader, setHeaders, removeHeader
6. **Token management** — setToken, clearToken, getToken
7. **Instance HTTP methods** — GET, POST, PUT, PATCH, DELETE (build
   URL, merge headers, delegate to static request())
8. **upload()** — convenience wrapper around POST() with CURLFile
