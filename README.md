# ctg-php-api-client

`ctg-php-api-client` is a minimal REST API client for PHP built on cURL.
It provides a static `request()` primitive for one-off calls and instance
methods for managed API sessions with base URLs, default headers, and JWT
authentication. File uploads are handled via multipart auto-detection.
HTTP error responses return as data; transport failures throw.

**Key Features:**

* **Static primitive** — `request()` handles all cURL execution with no
  state, callable without a client instance
* **Instance convenience** — base URL, default headers, and JWT token
  managed on the client, applied to every request
* **Per-request headers** — override or supplement defaults for a single
  call without modifying client state
* **JWT built in** — `setToken()` adds `Authorization: Bearer` to all
  requests until cleared
* **File uploads** — `upload()` convenience method, plus auto-detection
  of `CURLFile` in POST/PUT/PATCH bodies
* **No exceptions on HTTP errors** — 4xx/5xx responses return normally
  with `ok => false`. Caller can opt into `HTTP_ERROR` for unified
  error handling

## Install

Add the GitHub repository to your `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/claymoretechgroup/ctg-php-api-client" }
    ]
}
```

Then require the package:

```
composer require ctg/php-api-client
```

## Examples

### One-Off Request (Static)

Make a request without creating a client instance:

```php
use CTG\ApiClient\CTGAPIClient;

$result = CTGAPIClient::request('GET', 'https://api.example.com/health');

$result = CTGAPIClient::request('POST', 'https://api.example.com/webhook', [
    'event' => 'deploy',
], [], [
    'Authorization' => 'Bearer ' . $token,
]);
```

### Client Instance

Set up a client for repeated calls against the same API:

```php
$api = CTGAPIClient::init('https://api.example.com')
    ->setToken($jwt)
    ->setHeader('Accept-Language', 'en');

$users = $api->GET('/users');
$admins = $api->GET('/users', ['role' => 'admin']);
```

### CRUD Operations

```php
$user = $api->POST('/users', [
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

$updated = $api->PUT('/users/42', [
    'name' => 'Alicia',
]);

$patched = $api->PATCH('/users/42', [
    'name' => 'Alicia',
]);

$deleted = $api->DELETE('/users/42');
```

### Per-Request Headers

Override or supplement defaults for a single call:

```php
$api = CTGAPIClient::init('https://api.example.com')
    ->setHeader('Accept-Language', 'en');

// Override for one call — defaults unchanged after
$result = $api->GET('/users', [], ['Accept-Language' => 'fr']);

// Add a one-off header
$result = $api->POST('/orders', $data, [], ['X-Idempotency-Key' => $key]);
```

### File Uploads

```php
$result = $api->upload('/documents', '/tmp/report.pdf', [
    'title' => 'Q4 Report',
    'category' => 'financial',
]);

// Or use CURLFile directly in POST — multipart auto-detected
$result = $api->POST('/documents', [
    'file' => new \CURLFile('/tmp/report.pdf'),
    'title' => 'Q4 Report',
]);
```

### Response Structure

Every request returns the same shape:

```php
$result = $api->GET('/users');

// $result['status']  — 200
// $result['ok']      — true
// $result['headers'] — ['content-type' => 'application/json', ...]
// $result['body']    — [...parsed JSON...]
```

### Handling HTTP Errors

HTTP errors return as data. Escalate to exceptions when you want
unified error handling:

```php
use CTG\ApiClient\CTGAPIClientError;

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

### Integration with CTGFnprog

Response bodies are arrays — ready for pipeline transforms:

```php
use CTG\FnProg\CTGFnprog;

$activeAdmins = CTGFnprog::pipe([
    fn($_) => $api->GET('/users'),
    fn($r) => $r['body'],
    CTGFnprog::filter(fn($u) => $u['active'] && $u['role'] === 'admin'),
    CTGFnprog::pick(['id', 'name', 'email']),
    CTGFnprog::sortBy('name'),
])();
```

## Notice

`ctg-php-api-client` is under active development. The core API is
stable.
