<?php
declare(strict_types=1);

namespace CTG\ApiClient;

// Minimal REST API client with JWT auth and file uploads
class CTGAPIClient {

    /* Instance Properties */
    private string $_baseUrl;
    private int $_timeout;
    private array $_headers = [];
    private ?string $_token = null;
    private ?array $_allowedSchemes = null;
    private ?array $_allowedHosts = null;
    private ?int $_maxResponseBytes = null;

    // CONSTRUCTOR :: STRING, ARRAY -> $this
    // Creates a client with a base URL and optional config
    public function __construct(string $baseUrl, array $config = []) {
        $this->_baseUrl = rtrim($baseUrl, '/');
        $this->_timeout = $config['timeout'] ?? 30;

        if (isset($config['headers'])) {
            $this->_headers = $config['headers'];
        }
        if (isset($config['allowed_schemes'])) {
            $this->_allowedSchemes = array_map('strtolower', $config['allowed_schemes']);
        }
        if (isset($config['allowed_hosts'])) {
            $this->_allowedHosts = array_map('strtolower', $config['allowed_hosts']);
        }
        if (isset($config['max_response_bytes'])) {
            $this->_maxResponseBytes = (int)$config['max_response_bytes'];
        }
    }

    /**
     *
     * Instance Methods
     *
     */

    // :: STRING -> $this
    // Set the JWT bearer token for subsequent requests
    public function setToken(string $token): static {
        $this->_token = $token;
        return $this;
    }

    // :: VOID -> $this
    // Clear the current bearer token
    public function clearToken(): static {
        $this->_token = null;
        return $this;
    }

    // :: VOID -> ?STRING
    // Get the current bearer token
    public function getToken(): ?string {
        return $this->_token;
    }

    // :: STRING, STRING -> $this
    // Set a default header for all subsequent requests
    public function setHeader(string $name, string $value): static {
        $this->_headers[$name] = $value;
        return $this;
    }

    // :: ARRAY<STRING, STRING> -> $this
    // Set multiple default headers at once
    public function setHeaders(array $headers): static {
        foreach ($headers as $name => $value) {
            $this->_headers[$name] = $value;
        }
        return $this;
    }

    // :: STRING -> $this
    // Remove a default header
    public function removeHeader(string $name): static {
        unset($this->_headers[$name]);
        return $this;
    }

    // :: STRING, ARRAY, ARRAY -> ARRAY
    // GET request with optional query parameters and per-request headers
    public function GET(string $path, array $params = [], array $headers = []): array {
        return $this->_instanceRequest(
            'GET',
            $this->_buildUrl($path),
            [],
            $params,
            $this->_mergeHeaders($headers)
        );
    }

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // POST request with body, optional query parameters, and per-request headers
    public function POST(string $path, array $body = [], array $params = [], array $headers = []): array {
        return $this->_instanceRequest(
            'POST',
            $this->_buildUrl($path),
            $body,
            $params,
            $this->_mergeHeaders($headers)
        );
    }

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // PUT request with body, optional query parameters, and per-request headers
    public function PUT(string $path, array $body = [], array $params = [], array $headers = []): array {
        return $this->_instanceRequest(
            'PUT',
            $this->_buildUrl($path),
            $body,
            $params,
            $this->_mergeHeaders($headers)
        );
    }

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // PATCH request with body, optional query parameters, and per-request headers
    public function PATCH(string $path, array $body = [], array $params = [], array $headers = []): array {
        return $this->_instanceRequest(
            'PATCH',
            $this->_buildUrl($path),
            $body,
            $params,
            $this->_mergeHeaders($headers)
        );
    }

    // :: STRING, ARRAY, ARRAY -> ARRAY
    // DELETE request with optional query parameters and per-request headers
    public function DELETE(string $path, array $params = [], array $headers = []): array {
        return $this->_instanceRequest(
            'DELETE',
            $this->_buildUrl($path),
            [],
            $params,
            $this->_mergeHeaders($headers)
        );
    }

    // :: STRING, STRING, ARRAY, STRING -> ARRAY
    // Upload a file with optional extra form fields
    public function upload(
        string $path,
        string $filePath,
        array  $fields = [],
        string $fieldName = 'file'
    ): array {
        if (!is_file($filePath)) {
            throw new CTGAPIClientError('REQUEST_FAILED',
                "Upload file not found: {$filePath}",
                ['file_path' => $filePath]
            );
        }
        $fields[$fieldName] = new \CURLFile($filePath);
        return $this->POST($path, $fields);
    }

    /**
     *
     * Static Methods
     *
     */

    // Static Factory Method :: STRING, ARRAY -> ctgapiClient
    // Creates and returns a new CTGAPIClient instance
    public static function init(string $baseUrl, array $config = []): static {
        return new static($baseUrl, $config);
    }

    // :: STRING, STRING, ARRAY, ARRAY, ARRAY, INT -> ARRAY
    // Stateless cURL execution — the primitive everything delegates to
    public static function request(
        string $method,
        string $url,
        array  $body = [],
        array  $params = [],
        array  $headers = [],
        int    $timeout = 30,
        array  $opts = []
    ): array {
        $method = strtoupper(trim($method));
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        if (!in_array($method, $allowedMethods, true)) {
            throw new CTGAPIClientError('INVALID_METHOD',
                "Invalid HTTP method: {$method}. Allowed: " . implode(', ', $allowedMethods),
                ['method' => $method]
            );
        }

        if (!empty($params)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        $isMultipart = self::_hasFile($body);

        // Auto-set User-Agent if not provided
        if (!self::_hasHeader($headers, 'User-Agent')) {
            $headers['User-Agent'] = 'CTGAPIClient/1.0';
        }

        // Build Content-Type if not provided and body is present
        if (!$isMultipart && !empty($body) && !self::_hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'application/json';
        }

        // Format headers for cURL — validate names per RFC 7230, strip control chars from values
        $formatted = [];
        foreach ($headers as $name => $value) {
            if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\-.^_`|~]+$/', $name)) {
                throw new CTGAPIClientError('INVALID_HEADER',
                    "Invalid header name: {$name}",
                    ['header' => $name]
                );
            }
            $cleanValue = str_replace(["\r", "\n", "\0"], '', $value);
            $formatted[] = "{$name}: {$cleanValue}";
        }

        $ch = curl_init();
        $curlOpts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $formatted,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_PROXY          => '',
        ];

        if (isset($opts['max_response_bytes'])) {
            $curlOpts[CURLOPT_MAXFILESIZE] = $opts['max_response_bytes'];
        }

        curl_setopt_array($ch, $curlOpts);

        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if ($isMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                $encoded = json_encode($body);
                if ($encoded === false) {
                    curl_close($ch);
                    throw new CTGAPIClientError('INVALID_BODY',
                        'Failed to encode request body as JSON: ' . json_last_error_msg(),
                        ['body' => $body, 'json_error' => json_last_error_msg()]
                    );
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
            }
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            $type = match($errno) {
                CURLE_COULDNT_CONNECT    => 'CONNECTION_FAILED',
                CURLE_OPERATION_TIMEDOUT => 'TIMEOUT',
                CURLE_COULDNT_RESOLVE_HOST => 'DNS_FAILED',
                CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM => 'SSL_ERROR',
                CURLE_URL_MALFORMAT      => 'INVALID_URL',
                default                  => 'REQUEST_FAILED',
            };

            throw new CTGAPIClientError($type, $error, [
                'url' => $url,
                'method' => $method,
                'curl_errno' => $errno,
            ]);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerStr = substr($response, 0, $headerSize);
        $bodyStr   = substr($response, $headerSize);

        $parsedHeaders = self::_parseHeaders($headerStr);
        $parsedBody = self::_parseBody($bodyStr);

        return [
            'status' => $statusCode,
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'headers' => $parsedHeaders,
            'body' => $parsedBody,
        ];
    }

    /**
     *
     * Private Methods
     *
     */

    // :: STRING -> STRING
    // Build full URL from base URL and path
    private function _buildUrl(string $path): string {
        return $this->_baseUrl . '/' . ltrim($path, '/');
    }

    // :: STRING, STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // Instance-level request with SSRF check and max_response_bytes
    private function _instanceRequest(
        string $method,
        string $url,
        array  $body = [],
        array  $params = [],
        array  $headers = []
    ): array {
        $this->_checkSsrf($url);

        $opts = [];
        if ($this->_maxResponseBytes !== null) {
            $opts['max_response_bytes'] = $this->_maxResponseBytes;
        }

        return self::request($method, $url, $body, $params, $headers, $this->_timeout, $opts);
    }

    // :: STRING -> VOID
    // Check URL against SSRF allowlists
    private function _checkSsrf(string $url): void {
        $parsed = parse_url($url);

        if ($this->_allowedSchemes !== null) {
            $scheme = strtolower($parsed['scheme'] ?? '');
            if (!in_array($scheme, $this->_allowedSchemes, true)) {
                throw new CTGAPIClientError('INVALID_URL',
                    "Disallowed URL scheme: {$scheme}",
                    ['url' => $url, 'scheme' => $scheme, 'allowed' => $this->_allowedSchemes]
                );
            }
        }

        if ($this->_allowedHosts !== null) {
            $host = strtolower($parsed['host'] ?? '');
            if (!in_array($host, $this->_allowedHosts, true)) {
                throw new CTGAPIClientError('INVALID_URL',
                    "Disallowed host: {$host}",
                    ['url' => $url, 'host' => $host, 'allowed' => $this->_allowedHosts]
                );
            }
        }
    }

    // :: ARRAY -> ARRAY<STRING, STRING>
    // Merge automatic + default + per-request headers
    private function _mergeHeaders(array $perRequest = []): array {
        $headers = [];

        // Automatic headers
        $headers['User-Agent'] = 'CTGAPIClient/1.0';
        if ($this->_token !== null) {
            $headers['Authorization'] = "Bearer {$this->_token}";
        }

        // Defaults (override automatic)
        foreach ($this->_headers as $name => $value) {
            $headers[$name] = $value;
        }

        // Per-request (highest priority)
        foreach ($perRequest as $name => $value) {
            $headers[$name] = $value;
        }

        return $headers;
    }

    // :: ARRAY, STRING -> BOOL
    // Case-insensitive check for a header key in an associative array
    private static function _hasHeader(array $headers, string $name): bool {
        $lower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $lower) {
                return true;
            }
        }
        return false;
    }

    // :: ARRAY -> BOOL
    // Check if body contains any CURLFile instances at top level.
    // Also rejects nested CURLFile since cURL cannot process them.
    private static function _hasFile(array $body): bool {
        $hasTopLevel = false;
        foreach ($body as $value) {
            if ($value instanceof \CURLFile) {
                $hasTopLevel = true;
            } elseif (is_array($value)) {
                self::_rejectNestedFile($value);
            }
        }
        return $hasTopLevel;
    }

    // :: ARRAY -> VOID
    // Throws if a CURLFile is found nested inside an array.
    // cURL only processes top-level values for multipart uploads.
    private static function _rejectNestedFile(array $data): void {
        foreach ($data as $value) {
            if ($value instanceof \CURLFile) {
                throw new CTGAPIClientError('INVALID_BODY',
                    'CURLFile must be a top-level body value. Nested files are not supported by cURL multipart.',
                    ['value' => $value]
                );
            }
            if (is_array($value)) {
                self::_rejectNestedFile($value);
            }
        }
    }

    // :: STRING -> ARRAY<STRING, STRING|ARRAY>
    // Parse raw header string into associative array with lowercase keys.
    // Duplicate headers are comma-joined per RFC 7230, except Set-Cookie
    // which is collected as an array (Set-Cookie values can contain commas).
    private static function _parseHeaders(string $headerStr): array {
        $headers = [];
        $lines = explode("\r\n", $headerStr);
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $key = strtolower(trim($name));
                $val = trim($value);

                if (!isset($headers[$key])) {
                    $headers[$key] = $val;
                } elseif ($key === 'set-cookie') {
                    if (!is_array($headers[$key])) {
                        $headers[$key] = [$headers[$key]];
                    }
                    $headers[$key][] = $val;
                } else {
                    $headers[$key] .= ', ' . $val;
                }
            }
        }
        return $headers;
    }

    // :: STRING -> MIXED
    // Parse response body — JSON decode if possible, raw string otherwise
    private static function _parseBody(string $bodyStr): mixed {
        if ($bodyStr === '') {
            return '';
        }
        $decoded = json_decode($bodyStr, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        return $bodyStr;
    }
}
