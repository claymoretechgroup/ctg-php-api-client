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

    // CONSTRUCTOR :: STRING, ARRAY -> $this
    // Creates a client with a base URL and optional config
    public function __construct(string $baseUrl, array $config = []) {
        $this->_baseUrl = rtrim($baseUrl, '/');
        $this->_timeout = $config['timeout'] ?? 30;

        if (isset($config['headers'])) {
            $this->_headers = $config['headers'];
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
        return self::request(
            'GET',
            $this->_buildUrl($path),
            [],
            $params,
            $this->_mergeHeaders($headers),
            $this->_timeout
        );
    }

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // POST request with body, optional query parameters, and per-request headers
    public function POST(string $path, array $body = [], array $params = [], array $headers = []): array {
        return self::request(
            'POST',
            $this->_buildUrl($path),
            $body,
            $params,
            $this->_mergeHeaders($headers),
            $this->_timeout
        );
    }

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // PUT request with body, optional query parameters, and per-request headers
    public function PUT(string $path, array $body = [], array $params = [], array $headers = []): array {
        return self::request(
            'PUT',
            $this->_buildUrl($path),
            $body,
            $params,
            $this->_mergeHeaders($headers),
            $this->_timeout
        );
    }

    // :: STRING, ARRAY, ARRAY, ARRAY -> ARRAY
    // PATCH request with body, optional query parameters, and per-request headers
    public function PATCH(string $path, array $body = [], array $params = [], array $headers = []): array {
        return self::request(
            'PATCH',
            $this->_buildUrl($path),
            $body,
            $params,
            $this->_mergeHeaders($headers),
            $this->_timeout
        );
    }

    // :: STRING, ARRAY, ARRAY -> ARRAY
    // DELETE request with optional query parameters and per-request headers
    public function DELETE(string $path, array $params = [], array $headers = []): array {
        return self::request(
            'DELETE',
            $this->_buildUrl($path),
            [],
            $params,
            $this->_mergeHeaders($headers),
            $this->_timeout
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
                ['path' => $filePath]
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
        int    $timeout = 30
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

        // Build Content-Type if not provided and body is present
        if (!$isMultipart && !empty($body) && !self::_hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'application/json';
        }

        // Format headers for cURL — strip control characters to prevent CRLF injection
        $formatted = [];
        foreach ($headers as $name => $value) {
            $cleanName = str_replace(["\r", "\n", "\0"], '', $name);
            $cleanValue = str_replace(["\r", "\n", "\0"], '', $value);
            $formatted[] = "{$cleanName}: {$cleanValue}";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $formatted,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

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

    // :: ARRAY -> ARRAY<STRING, STRING>
    // Merge automatic + default + per-request headers
    private function _mergeHeaders(array $perRequest = []): array {
        $headers = [];

        // Automatic headers
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
    // Check if body contains any CURLFile instances
    private static function _hasFile(array $body): bool {
        foreach ($body as $value) {
            if ($value instanceof \CURLFile) {
                return true;
            }
        }
        return false;
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
