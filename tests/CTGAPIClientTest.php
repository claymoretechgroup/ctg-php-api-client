<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiClient\CTGAPIClient;
use CTG\ApiClient\CTGAPIClientError;
use CTG\FnProg\CTGFnprog;

// Tests for CTGAPIClient — static request, instance methods, auth, headers, uploads
// Requires the staging web container running with test endpoints

$config = ['output' => 'console'];

$baseUrl = 'http://localhost';
$endpointBase = '/tests/endpoints';

// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('init — static factory')
    ->stage('create', fn($_) => CTGAPIClient::init($baseUrl))
    ->assert('returns CTGAPIClient', fn($r) => $r instanceof CTGAPIClient, true)
    ->start(null, $config);

CTGTest::init('init — with config')
    ->stage('create', fn($_) => CTGAPIClient::init($baseUrl, [
        'timeout' => 10,
        'headers' => ['X-Custom' => 'test'],
    ]))
    ->assert('returns CTGAPIClient', fn($r) => $r instanceof CTGAPIClient, true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// STATIC request()
// ═══════════════════════════════════════════════════════════════

CTGTest::init('static request — GET with full URL')
    ->stage('execute', fn($_) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php"
    ))
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('method is GET', fn($r) => $r['body']['method'], 'GET')
    ->start(null, $config);

CTGTest::init('static request — POST with body')
    ->stage('execute', fn($_) => CTGAPIClient::request(
        'POST', "http://localhost{$endpointBase}/echo.php",
        ['name' => 'Alice']
    ))
    ->assert('method is POST', fn($r) => $r['body']['method'], 'POST')
    ->assert('body sent', fn($r) => $r['body']['body']['name'], 'Alice')
    ->start(null, $config);

CTGTest::init('static request — with query params')
    ->stage('execute', fn($_) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], ['key' => 'value']
    ))
    ->assert('param sent', fn($r) => $r['body']['params']['key'], 'value')
    ->start(null, $config);

CTGTest::init('static request — with headers')
    ->stage('execute', fn($_) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], [], ['X-Static-Header' => 'static-value']
    ))
    ->assert('header sent', fn($r) => $r['body']['headers']['X-Static-Header'] ?? null, 'static-value')
    ->start(null, $config);

CTGTest::init('static request — with auth header')
    ->stage('execute', fn($_) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/auth.php",
        [], [], ['Authorization' => 'Bearer test-jwt-token-12345']
    ))
    ->assert('authenticated', fn($r) => $r['body']['authenticated'], true)
    ->start(null, $config);

CTGTest::init('static request — empty method throws')
    ->stage('attempt', function($_) {
        try {
            CTGAPIClient::request('', 'http://localhost/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_METHOD', fn($r) => $r, 'INVALID_METHOD')
    ->start(null, $config);

CTGTest::init('static request — custom timeout')
    ->stage('execute', fn($_) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], [], [], 5
    ))
    ->assert('request succeeded', fn($r) => $r['status'], 200)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// INSTANCE HTTP METHODS
// ═══════════════════════════════════════════════════════════════

CTGTest::init('GET — basic request')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('ok is true', fn($r) => $r['ok'], true)
    ->assert('body has method', fn($r) => $r['body']['method'], 'GET')
    ->start(null, $config);

CTGTest::init('GET — with query parameters')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php", ['role' => 'admin', 'active' => '1']))
    ->assert('params echoed', fn($r) => $r['body']['params']['role'], 'admin')
    ->assert('active param', fn($r) => $r['body']['params']['active'], '1')
    ->start(null, $config);

CTGTest::init('GET — with per-request headers')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php", [], ['X-Per-Request' => 'one-off']))
    ->assert('per-request header sent', fn($r) => $r['body']['headers']['X-Per-Request'] ?? null, 'one-off')
    ->start(null, $config);

CTGTest::init('GET — JSON endpoint')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/json.php"))
    ->assert('body is array', fn($r) => is_array($r['body']), true)
    ->assert('has users', fn($r) => count($r['body']['users']), 3)
    ->assert('first user is Alice', fn($r) => $r['body']['users'][0]['name'], 'Alice')
    ->start(null, $config);

CTGTest::init('POST — JSON body')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", [
            'name' => 'Alice',
            'email' => 'alice@test.com',
        ]))
    ->assert('method is POST', fn($r) => $r['body']['method'], 'POST')
    ->assert('body sent', fn($r) => $r['body']['body']['name'], 'Alice')
    ->assert('email sent', fn($r) => $r['body']['body']['email'], 'alice@test.com')
    ->start(null, $config);

CTGTest::init('POST — with query params and body')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", ['data' => 'value'], ['key' => 'abc']))
    ->assert('body sent', fn($r) => $r['body']['body']['data'], 'value')
    ->assert('param sent', fn($r) => $r['body']['params']['key'], 'abc')
    ->start(null, $config);

CTGTest::init('POST — with per-request headers')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", ['x' => 1], [], ['X-Idempotency-Key' => 'abc']))
    ->assert('header sent', fn($r) => $r['body']['headers']['X-Idempotency-Key'] ?? null, 'abc')
    ->start(null, $config);

CTGTest::init('PUT — JSON body')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->PUT("{$endpointBase}/echo.php", ['name' => 'Updated']))
    ->assert('method is PUT', fn($r) => $r['body']['method'], 'PUT')
    ->assert('body sent', fn($r) => $r['body']['body']['name'], 'Updated')
    ->start(null, $config);

CTGTest::init('PATCH — JSON body')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->PATCH("{$endpointBase}/echo.php", ['field' => 'patched']))
    ->assert('method is PATCH', fn($r) => $r['body']['method'], 'PATCH')
    ->assert('body sent', fn($r) => $r['body']['body']['field'], 'patched')
    ->start(null, $config);

CTGTest::init('DELETE — basic')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->DELETE("{$endpointBase}/echo.php"))
    ->assert('method is DELETE', fn($r) => $r['body']['method'], 'DELETE')
    ->start(null, $config);

CTGTest::init('DELETE — with query params')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->DELETE("{$endpointBase}/echo.php", ['force' => 'true']))
    ->assert('param sent', fn($r) => $r['body']['params']['force'], 'true')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// RESPONSE STRUCTURE
// ═══════════════════════════════════════════════════════════════

CTGTest::init('response — has all required keys')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('has status', fn($r) => isset($r['status']), true)
    ->assert('has ok', fn($r) => isset($r['ok']), true)
    ->assert('has headers', fn($r) => isset($r['headers']), true)
    ->assert('has body', fn($r) => isset($r['body']), true)
    ->assert('headers is array', fn($r) => is_array($r['headers']), true)
    ->assert('header keys lowercase', fn($r) => isset($r['headers']['content-type']), true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// HTTP STATUS CODES
// ═══════════════════════════════════════════════════════════════

CTGTest::init('status — 200 is ok')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 200]))
    ->assert('ok is true', fn($r) => $r['ok'], true)
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->start(null, $config);

CTGTest::init('status — 201 is ok')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 201]))
    ->assert('ok is true', fn($r) => $r['ok'], true)
    ->start(null, $config);

CTGTest::init('status — 400 is not ok')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 400]))
    ->assert('ok is false', fn($r) => $r['ok'], false)
    ->assert('status is 400', fn($r) => $r['status'], 400)
    ->assert('body still available', fn($r) => is_array($r['body']), true)
    ->start(null, $config);

CTGTest::init('status — 401 is not ok')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 401]))
    ->assert('ok is false', fn($r) => $r['ok'], false)
    ->assert('status is 401', fn($r) => $r['status'], 401)
    ->start(null, $config);

CTGTest::init('status — 404 is not ok')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 404]))
    ->assert('ok is false', fn($r) => $r['ok'], false)
    ->assert('status is 404', fn($r) => $r['status'], 404)
    ->start(null, $config);

CTGTest::init('status — 500 is not ok')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 500]))
    ->assert('ok is false', fn($r) => $r['ok'], false)
    ->assert('status is 500', fn($r) => $r['status'], 500)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// AUTHENTICATION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('auth — no token returns 401')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/auth.php"))
    ->assert('status is 401', fn($r) => $r['status'], 401)
    ->assert('error message', fn($r) => $r['body']['error'], 'No authorization header')
    ->start(null, $config);

CTGTest::init('auth — wrong token returns 403')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->setToken('wrong-token')
        ->GET("{$endpointBase}/auth.php"))
    ->assert('status is 403', fn($r) => $r['status'], 403)
    ->assert('error message', fn($r) => $r['body']['error'], 'Invalid token')
    ->start(null, $config);

CTGTest::init('auth — valid token returns 200')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->setToken('test-jwt-token-12345')
        ->GET("{$endpointBase}/auth.php"))
    ->assert('status is 200', fn($r) => $r['status'], 200)
    ->assert('authenticated', fn($r) => $r['body']['authenticated'], true)
    ->assert('token echoed', fn($r) => $r['body']['token'], 'test-jwt-token-12345')
    ->start(null, $config);

CTGTest::init('auth — token persists across requests')
    ->stage('create client', fn($_) => CTGAPIClient::init($baseUrl)
        ->setToken('test-jwt-token-12345'))
    ->stage('first request', fn($api) => ['api' => $api, 'r1' => $api->GET("{$endpointBase}/auth.php")])
    ->stage('second request', fn($ctx) => ['r1' => $ctx['r1'], 'r2' => $ctx['api']->GET("{$endpointBase}/auth.php")])
    ->assert('first authenticated', fn($r) => $r['r1']['body']['authenticated'], true)
    ->assert('second authenticated', fn($r) => $r['r2']['body']['authenticated'], true)
    ->start(null, $config);

CTGTest::init('auth — clearToken removes auth')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        $api = CTGAPIClient::init($baseUrl)->setToken('test-jwt-token-12345');
        $before = $api->GET("{$endpointBase}/auth.php");
        $api->clearToken();
        $after = $api->GET("{$endpointBase}/auth.php");
        return ['before' => $before, 'after' => $after];
    })
    ->assert('before: authenticated', fn($r) => $r['before']['status'], 200)
    ->assert('after: not authenticated', fn($r) => $r['after']['status'], 401)
    ->start(null, $config);

CTGTest::init('auth — getToken returns current token')
    ->stage('execute', function($_) use ($baseUrl) {
        $api = CTGAPIClient::init($baseUrl);
        $before = $api->getToken();
        $api->setToken('my-token');
        $during = $api->getToken();
        $api->clearToken();
        $after = $api->getToken();
        return ['before' => $before, 'during' => $during, 'after' => $after];
    })
    ->assert('initially null', fn($r) => $r['before'], null)
    ->assert('set to my-token', fn($r) => $r['during'], 'my-token')
    ->assert('cleared to null', fn($r) => $r['after'], null)
    ->start(null, $config);

CTGTest::init('auth — token sent with POST')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->setToken('test-jwt-token-12345')
        ->POST("{$endpointBase}/auth.php", ['data' => 'test']))
    ->assert('authenticated via POST', fn($r) => $r['body']['authenticated'], true)
    ->assert('method is POST', fn($r) => $r['body']['method'], 'POST')
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// HEADERS
// ═══════════════════════════════════════════════════════════════

CTGTest::init('headers — custom header sent')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->setHeader('X-Custom-Header', 'test-value')
        ->GET("{$endpointBase}/echo.php"))
    ->assert('header echoed', fn($r) => $r['body']['headers']['X-Custom-Header'] ?? null, 'test-value')
    ->start(null, $config);

CTGTest::init('headers — multiple headers')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->setHeaders(['X-First' => 'one', 'X-Second' => 'two'])
        ->GET("{$endpointBase}/echo.php"))
    ->assert('first header', fn($r) => $r['body']['headers']['X-First'] ?? null, 'one')
    ->assert('second header', fn($r) => $r['body']['headers']['X-Second'] ?? null, 'two')
    ->start(null, $config);

CTGTest::init('headers — removeHeader')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        $api = CTGAPIClient::init($baseUrl)->setHeader('X-Remove-Me', 'present');
        $before = $api->GET("{$endpointBase}/echo.php");
        $api->removeHeader('X-Remove-Me');
        $after = $api->GET("{$endpointBase}/echo.php");
        return ['before' => $before, 'after' => $after];
    })
    ->assert('present before', fn($r) => $r['before']['body']['headers']['X-Remove-Me'] ?? null, 'present')
    ->assert('gone after', fn($r) => $r['after']['body']['headers']['X-Remove-Me'] ?? null, null)
    ->start(null, $config);

CTGTest::init('headers — Content-Type auto-set for JSON')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", ['test' => 'data']))
    ->assert('content-type is json', fn($r) => str_contains(
        $r['body']['headers']['Content-Type'] ?? '', 'application/json'), true)
    ->start(null, $config);

// ── Per-request header merge behavior ───────────────────────────

CTGTest::init('per-request headers — override default for one call')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        $api = CTGAPIClient::init($baseUrl)->setHeader('X-Lang', 'en');
        $overridden = $api->GET("{$endpointBase}/echo.php", [], ['X-Lang' => 'fr']);
        $nextCall = $api->GET("{$endpointBase}/echo.php");
        return ['overridden' => $overridden, 'next' => $nextCall];
    })
    ->assert('overridden to fr', fn($r) => $r['overridden']['body']['headers']['X-Lang'] ?? null, 'fr')
    ->assert('next call back to en', fn($r) => $r['next']['body']['headers']['X-Lang'] ?? null, 'en')
    ->start(null, $config);

CTGTest::init('per-request headers — supplement defaults')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->setHeader('X-Default', 'default-val')
        ->GET("{$endpointBase}/echo.php", [], ['X-Extra' => 'extra-val']))
    ->assert('default present', fn($r) => $r['body']['headers']['X-Default'] ?? null, 'default-val')
    ->assert('extra present', fn($r) => $r['body']['headers']['X-Extra'] ?? null, 'extra-val')
    ->start(null, $config);

CTGTest::init('per-request headers — do not persist')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        $api = CTGAPIClient::init($baseUrl);
        $api->GET("{$endpointBase}/echo.php", [], ['X-One-Off' => 'temp']);
        $nextCall = $api->GET("{$endpointBase}/echo.php");
        return $nextCall;
    })
    ->assert('one-off header gone', fn($r) => $r['body']['headers']['X-One-Off'] ?? null, null)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// FILE UPLOADS
// ═══════════════════════════════════════════════════════════════

CTGTest::init('upload — file via upload()')
    ->stage('setup', function($_) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'test file content');
        return $tmp;
    })
    ->stage('upload', fn($tmp) => [
        'tmp' => $tmp,
        'result' => CTGAPIClient::init($baseUrl)
            ->upload("{$endpointBase}/upload.php", $tmp, ['title' => 'Test Doc'])
    ])
    ->assert('status is 200', fn($r) => $r['result']['status'], 200)
    ->assert('file received', fn($r) => isset($r['result']['body']['files']['file']), true)
    ->assert('file has size', fn($r) => $r['result']['body']['files']['file']['size'] > 0, true)
    ->assert('extra field sent', fn($r) => $r['result']['body']['fields']['title'], 'Test Doc')
    ->stage('cleanup', fn($r) => unlink($r['tmp']))
    ->start(null, $config);

CTGTest::init('upload — custom field name')
    ->stage('setup', function($_) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'avatar data');
        return $tmp;
    })
    ->stage('upload', fn($tmp) => [
        'tmp' => $tmp,
        'result' => CTGAPIClient::init($baseUrl)
            ->upload("{$endpointBase}/upload.php", $tmp, [], 'avatar')
    ])
    ->assert('file under avatar key', fn($r) => isset($r['result']['body']['files']['avatar']), true)
    ->stage('cleanup', fn($r) => unlink($r['tmp']))
    ->start(null, $config);

CTGTest::init('upload — CURLFile in POST auto-detects multipart')
    ->stage('setup', function($_) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'direct curlfile test');
        return $tmp;
    })
    ->stage('post', fn($tmp) => [
        'tmp' => $tmp,
        'result' => CTGAPIClient::init($baseUrl)
            ->POST("{$endpointBase}/upload.php", [
                'document' => new \CURLFile($tmp),
                'category' => 'reports',
            ])
    ])
    ->assert('file received', fn($r) => isset($r['result']['body']['files']['document']), true)
    ->assert('extra field sent', fn($r) => $r['result']['body']['fields']['category'], 'reports')
    ->stage('cleanup', fn($r) => unlink($r['tmp']))
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// URL NORMALIZATION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('URL — trailing slash on base, leading slash on path')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl . '/')
        ->GET("/{$endpointBase}/echo.php"))
    ->assert('request succeeded', fn($r) => $r['status'], 200)
    ->start(null, $config);

CTGTest::init('URL — no slashes')
    ->stage('execute', fn($_) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('request succeeded', fn($r) => $r['status'], 200)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// TRANSPORT ERRORS
// ═══════════════════════════════════════════════════════════════

CTGTest::init('error — connection refused')
    ->stage('attempt', function($_) {
        try {
            CTGAPIClient::init('http://localhost:19999')->GET('/nonexistent');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws CONNECTION_FAILED', fn($r) => $r, 'CONNECTION_FAILED')
    ->start(null, $config);

CTGTest::init('error — connection refused via static request')
    ->stage('attempt', function($_) {
        try {
            CTGAPIClient::request('GET', 'http://localhost:19999/nonexistent');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws CONNECTION_FAILED', fn($r) => $r, 'CONNECTION_FAILED')
    ->start(null, $config);

CTGTest::init('error — DNS failure')
    ->stage('attempt', function($_) {
        try {
            CTGAPIClient::init('http://this-domain-definitely-does-not-exist-xyz123.com')
                ->GET('/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return in_array($e->type, ['DNS_FAILED', 'CONNECTION_FAILED']);
        }
    })
    ->assert('throws DNS or connection error', fn($r) => $r, true)
    ->start(null, $config);

CTGTest::init('error — timeout')
    ->stage('attempt', function($_) {
        try {
            CTGAPIClient::init('http://10.255.255.1', ['timeout' => 1])
                ->GET('/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return in_array($e->type, ['TIMEOUT', 'CONNECTION_FAILED']);
        }
    })
    ->assert('throws timeout or connection error', fn($r) => $r, true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// HTTP_ERROR — CALLER-INITIATED
// ═══════════════════════════════════════════════════════════════

CTGTest::init('HTTP_ERROR — caller throws on non-ok response')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        try {
            $result = CTGAPIClient::init($baseUrl)
                ->GET("{$endpointBase}/status.php", ['code' => 404]);
            if (!$result['ok']) {
                throw new CTGAPIClientError('HTTP_ERROR',
                    "Status: {$result['status']}", $result);
            }
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return ['type' => $e->type, 'status' => $e->data['status']];
        }
    })
    ->assert('type is HTTP_ERROR', fn($r) => $r['type'], 'HTTP_ERROR')
    ->assert('status is 404', fn($r) => $r['status'], 404)
    ->start(null, $config);

CTGTest::init('HTTP_ERROR — chainable with transport errors')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        $matched = null;
        try {
            $result = CTGAPIClient::init($baseUrl)
                ->GET("{$endpointBase}/status.php", ['code' => 401]);
            if (!$result['ok']) {
                throw new CTGAPIClientError('HTTP_ERROR',
                    "Status: {$result['status']}", $result);
            }
        } catch (CTGAPIClientError $e) {
            $e->on('TIMEOUT', function($e) use (&$matched) { $matched = 'timeout'; })
              ->on('HTTP_ERROR', function($e) use (&$matched) { $matched = 'http:' . $e->data['status']; })
              ->otherwise(function($e) use (&$matched) { $matched = 'otherwise'; });
        }
        return $matched;
    })
    ->assert('matched HTTP_ERROR with status', fn($r) => $r, 'http:401')
    ->start(null, $config);

CTGTest::init('HTTP_ERROR — full response in data')
    ->stage('execute', function($_) use ($baseUrl, $endpointBase) {
        try {
            $result = CTGAPIClient::init($baseUrl)
                ->GET("{$endpointBase}/status.php", ['code' => 500]);
            if (!$result['ok']) {
                throw new CTGAPIClientError('HTTP_ERROR',
                    "Status: {$result['status']}", $result);
            }
            return null;
        } catch (CTGAPIClientError $e) {
            return $e->data;
        }
    })
    ->assert('data has status', fn($r) => $r['status'], 500)
    ->assert('data has ok', fn($r) => $r['ok'], false)
    ->assert('data has body', fn($r) => is_array($r['body']), true)
    ->assert('data has headers', fn($r) => is_array($r['headers']), true)
    ->start(null, $config);

// ═══════════════════════════════════════════════════════════════
// CTGFnprog INTEGRATION
// ═══════════════════════════════════════════════════════════════

CTGTest::init('CTGFnprog — pipe over response body')
    ->stage('execute', fn($_) => CTGFnprog::pipe([
        fn($_) => CTGAPIClient::init($baseUrl)->GET("{$endpointBase}/json.php"),
        fn($r) => $r['body']['users'],
        CTGFnprog::filter(fn($u) => $u['active']),
        CTGFnprog::sortBy('name'),
        CTGFnprog::pluck('name'),
    ])(null))
    ->assert('returns active names sorted', fn($r) => $r, ['Alice', 'Bob'])
    ->start(null, $config);

CTGTest::init('CTGFnprog — pick fields from API response')
    ->stage('execute', fn($_) => CTGFnprog::pipe([
        fn($_) => CTGAPIClient::init($baseUrl)->GET("{$endpointBase}/json.php"),
        fn($r) => $r['body']['users'],
        CTGFnprog::pick(['id', 'name']),
    ])(null))
    ->assert('first has only id and name', fn($r) => array_keys($r[0]), ['id', 'name'])
    ->start(null, $config);
