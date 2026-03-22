# CTGAPIClient

Minimal REST API client built on cURL. Instance methods manage base URL,
default headers, and JWT token state. The static `request()` method
handles all cURL execution with no state dependency.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _baseUrl | STRING | API root URL, prepended to all request paths |
| _timeout | INT | Request timeout in seconds |
| _headers | ARRAY<STRING, STRING> | Default headers sent with every request |
| _token | ?STRING | JWT bearer token, null when not set |

---

## Construction

### CONSTRUCTOR :: STRING, ARRAY -> ctgapiClient

Creates a new client with a base URL and optional config. Config
supports `timeout` (default 30) and `headers` (default headers for
all requests). Trailing slashes on the base URL are normalized.

```php
$api = new CTGAPIClient('https://api.example.com', [
    'timeout' => 10,
    'headers' => ['Accept-Language' => 'en'],
]);
```

### CTGAPIClient.init :: STRING, ARRAY -> ctgapiClient

Static factory method. Returns `new static(...)` so subclasses
inherit the factory correctly.

```php
$api = CTGAPIClient::init('https://api.example.com');
```

---

## Instance Methods

### ctgapiClient.setToken :: STRING -> $this

Sets the JWT bearer token. Included as `Authorization: Bearer <token>`
on all subsequent requests until cleared. Setting a new token replaces
the previous one. Chainable.

```php
$api = CTGAPIClient::init('https://api.example.com')
    ->setToken($jwt);
```

### ctgapiClient.clearToken :: VOID -> $this

Removes the current bearer token. Subsequent requests will not include
an Authorization header. Chainable.

```php
$api->clearToken();
```

### ctgapiClient.getToken :: VOID -> ?STRING

Returns the current bearer token, or null if not set.

```php
$token = $api->getToken();
```

### ctgapiClient.setHeader :: STRING, STRING -> $this

Sets a default header included with every subsequent request. If the
header already exists, its value is replaced. Chainable.

```php
$api->setHeader('X-Api-Key', 'abc123');
```

### ctgapiClient.setHeaders :: ARRAY<STRING, STRING> -> $this

Sets multiple default headers at once. Chainable.

```php
$api->setHeaders([
    'X-Api-Key' => 'abc123',
    'Accept-Language' => 'en',
]);
```

### ctgapiClient.removeHeader :: STRING -> $this

Removes a default header. Chainable.

```php
$api->removeHeader('Accept-Language');
```

### ctgapiClient.GET :: STRING, ARRAY, ARRAY -> ARRAY

GET request. Builds the full URL from the base URL and path, merges
default and per-request headers, and delegates to the static
`request()`. Query parameters are URL-encoded and appended. Per-request
headers override defaults for this call only.

```php
$users = $api->GET('/users', ['role' => 'admin'], ['If-None-Match' => '"etag"']);
```

### ctgapiClient.POST :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY

POST request with a JSON body. If the body contains a `CURLFile`
instance, automatically switches to `multipart/form-data`.

```php
$result = $api->POST('/users', ['name' => 'Alice', 'email' => 'alice@test.com']);
```

### ctgapiClient.PUT :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY

PUT request with a JSON body. Same multipart detection as POST.

```php
$result = $api->PUT('/users/42', ['name' => 'Alicia']);
```

### ctgapiClient.PATCH :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY

PATCH request with a JSON body. Same multipart detection as POST.

```php
$result = $api->PATCH('/users/42', ['name' => 'Alicia']);
```

### ctgapiClient.DELETE :: STRING, ARRAY, ARRAY -> ARRAY

DELETE request with optional query parameters and per-request headers.

```php
$result = $api->DELETE('/users/42', ['force' => 'true']);
```

### ctgapiClient.upload :: STRING, STRING, ARRAY, STRING -> ARRAY

Uploads a file. Wraps `CURLFile` construction and delegates to
`POST()`. The `fieldName` argument sets the form field name for the
file (default `'file'`). Extra form fields are sent alongside the
file as `multipart/form-data`.

```php
$result = $api->upload('/documents', '/tmp/report.pdf', [
    'title' => 'Q4 Report',
], 'attachment');
```

---

## Static Methods

### CTGAPIClient.request :: STRING, STRING, ARRAY, ARRAY, ARRAY, INT -> ARRAY

The stateless primitive. Executes a cURL request with the given method,
full URL, body, query parameters, headers, and timeout. All instance
methods delegate to this. Can be called directly for one-off requests
without creating a client instance.

Returns the standard response structure: `{status, ok, headers, body}`.
Throws `CTGAPIClientError` on transport failures (connection, timeout,
DNS, SSL). Does not throw on HTTP error responses (4xx, 5xx).

JSON-encodes the body unless it contains a `CURLFile`, in which case
cURL sends it as `multipart/form-data`. Sets `Content-Type: application/json`
automatically for JSON bodies unless the caller provides their own.

```php
$result = CTGAPIClient::request('GET', 'https://api.example.com/health');

$result = CTGAPIClient::request('POST', 'https://api.example.com/webhook', [
    'event' => 'deploy',
], [], [
    'Authorization' => 'Bearer ' . $token,
], 10);
```
