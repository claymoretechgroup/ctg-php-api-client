# CTGAPIClientError

Typed error class for API client failures. Extends `\Exception` with
a string type code, structured context data, and a chainable handler
pattern. Thrown automatically on transport failures. Available for
caller-initiated HTTP error handling via `HTTP_ERROR`.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| type | STRING | Error type name (e.g. `'TIMEOUT'`) |
| msg | STRING | Human-readable error message |
| data | MIXED | Structured context (URL, method, curl errno, or full response) |
| _handled | BOOL | Whether an `on()` handler has matched |

The `code` and `message` properties are inherited from `\Exception`
and accessible via `getCode()` and `getMessage()`.

---

### Error Codes

| Code | Type | Description |
|------|------|-------------|
| 1000 | CONNECTION_FAILED | Connection refused or host unreachable |
| 1001 | TIMEOUT | Request timed out |
| 1002 | DNS_FAILED | DNS resolution failed |
| 1003 | SSL_ERROR | SSL certificate or handshake error |
| 2000 | REQUEST_FAILED | cURL error (other) |
| 3000 | INVALID_URL | Malformed URL (cURL error 3) |
| 3001 | INVALID_METHOD | Invalid HTTP method (not in allowlist: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS) |
| 4000 | HTTP_ERROR | Caller-initiated — HTTP response with non-2xx status |

---

## Construction

### CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> ctgapiClientError

Creates a new error. Accepts either a type name (`'TIMEOUT'`) or
integer code (`1001`). The message defaults to the type name if not
provided. Throws `\InvalidArgumentException` if the type or code is
unknown.

```php
$e = new CTGAPIClientError('TIMEOUT', 'Request timed out', [
    'url' => 'https://api.example.com/users',
    'method' => 'GET',
]);
```

---

## Instance Methods

### ctgapiClientError.on :: STRING|INT, (ctgapiClientError -> VOID) -> $this

Handles the error if it matches the given type name or code. Chainable.
Short-circuits after the first match.

```php
try {
    $result = $api->GET('/users');
    if (!$result['ok']) {
        throw new CTGAPIClientError('HTTP_ERROR',
            "Status: {$result['status']}", $result);
    }
} catch (CTGAPIClientError $e) {
    $e->on('HTTP_ERROR', function($e) {
            match($e->data['status']) {
                401 => redirectToLogin(),
                404 => showNotFound(),
                default => logError($e),
            };
        })
      ->on('TIMEOUT', fn($e) => retryLater())
      ->otherwise(fn($e) => throw $e);
}
```

### ctgapiClientError.otherwise :: (ctgapiClientError -> VOID) -> VOID

Handles the error if no previous `on()` call matched. Not chainable.

```php
$e->on('TIMEOUT', fn($e) => retry())
  ->otherwise(fn($e) => throw $e);
```

---

## Static Methods

### CTGAPIClientError.lookup :: STRING|INT -> INT|STRING|NULL

Bidirectional lookup between type names and codes.

```php
CTGAPIClientError::lookup('TIMEOUT');  // 1001
CTGAPIClientError::lookup(1001);       // 'TIMEOUT'
CTGAPIClientError::lookup(4000);       // 'HTTP_ERROR'
```
