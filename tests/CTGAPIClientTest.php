<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiClient\CTGAPIClient;
use CTG\ApiClient\CTGAPIClientError;
use CTG\FnProg\CTGFnprog;

$pipelines = [];

// Tests for CTGAPIClient — static request, instance methods, auth, headers, uploads
// Requires the staging web container running with test endpoints


$baseUrl = 'http://localhost';
$endpointBase = '/tests/endpoints';

// ═══════════════════════════════════════════════════════════════
// CONSTRUCTION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('init — static factory')
    ->stage('create', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl))
    ->assert('returns CTGAPIClient', fn(CTGTestState $state) => $state->getSubject() instanceof CTGAPIClient, CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('init — with config')
    ->stage('create', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl, [
        'timeout' => 10,
        'headers' => ['X-Custom' => 'test'],
    ]))
    ->assert('returns CTGAPIClient', fn(CTGTestState $state) => $state->getSubject() instanceof CTGAPIClient, CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// STATIC request()
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('static request — GET with full URL')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php"
    ))
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('method is GET', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('GET'))
    ;

$pipelines[] = CTGTest::init('static request — POST with body')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'POST', "http://localhost{$endpointBase}/echo.php",
        ['name' => 'Alice']
    ))
    ->assert('method is POST', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('POST'))
    ->assert('body sent', fn(CTGTestState $state) => $state->getSubject()['body']['body']['name'], CTGTestPredicates::equals('Alice'))
    ;

$pipelines[] = CTGTest::init('static request — with query params')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], ['key' => 'value']
    ))
    ->assert('param sent', fn(CTGTestState $state) => $state->getSubject()['body']['params']['key'], CTGTestPredicates::equals('value'))
    ;

$pipelines[] = CTGTest::init('static request — with headers')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], [], ['X-Static-Header' => 'static-value']
    ))
    ->assert('header sent', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Static-Header'] ?? null, CTGTestPredicates::equals('static-value'))
    ;

$pipelines[] = CTGTest::init('static request — with auth header')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/auth.php",
        [], [], ['Authorization' => 'Bearer test-jwt-token-12345']
    ))
    ->assert('authenticated', fn(CTGTestState $state) => $state->getSubject()['body']['authenticated'], CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('static request — empty method throws')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::request('', 'http://localhost/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_METHOD', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_METHOD'))
    ;

$pipelines[] = CTGTest::init('static request — invalid method throws')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::request('BOGUS', 'http://localhost/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_METHOD', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_METHOD'))
    ;

$pipelines[] = CTGTest::init('static request — malformed URL throws INVALID_URL')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::request('GET', 'http://');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_URL', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_URL'))
    ;

$pipelines[] = CTGTest::init('static request — invalid body throws INVALID_BODY')
    ->stage('attempt', function(CTGTestState $state) use ($endpointBase){
        try {
            // Invalid UTF-8 sequence causes json_encode to fail
            CTGAPIClient::request('POST', "http://localhost{$endpointBase}/echo.php",
                ['data' => "\xB1\x31"]);
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_BODY', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_BODY'))
    ;

$pipelines[] = CTGTest::init('static request — nested CURLFile throws INVALID_BODY')
    ->stage('attempt', function(CTGTestState $state) use ($endpointBase){
        try {
            CTGAPIClient::request('POST', "http://localhost{$endpointBase}/echo.php",
                ['meta' => ['file' => new \CURLFile('/dev/null')]]);
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_BODY', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_BODY'))
    ;

$pipelines[] = CTGTest::init('static request — lowercase content-type not duplicated')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'POST', "http://localhost{$endpointBase}/echo.php",
        ['key' => 'value'], [], ['content-type' => 'text/plain']
    ))
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('sent content-type text/plain', fn(CTGTestState $state) => str_contains(
        $state->getSubject()['body']['headers']['content-type'] ?? $state->getSubject()['body']['headers']['Content-Type'] ?? '',
        'text/plain'
    ), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('static request — invalid header name throws INVALID_HEADER')
    ->stage('attempt', function(CTGTestState $state) use ($endpointBase){
        try {
            CTGAPIClient::request('GET', "http://localhost{$endpointBase}/echo.php",
                [], [], ["Invalid Header\r\n" => 'value']);
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_HEADER', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_HEADER'))
    ;

$pipelines[] = CTGTest::init('static request — header with spaces throws INVALID_HEADER')
    ->stage('attempt', function(CTGTestState $state) use ($endpointBase){
        try {
            CTGAPIClient::request('GET', "http://localhost{$endpointBase}/echo.php",
                [], [], ['Bad Name' => 'value']);
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_HEADER', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_HEADER'))
    ;

$pipelines[] = CTGTest::init('static request — custom timeout')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], [], [], 5
    ))
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// INSTANCE HTTP METHODS
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('GET — basic request')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('ok is true', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isTrue())
    ->assert('body has method', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('GET'))
    ;

$pipelines[] = CTGTest::init('GET — with query parameters')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php", ['role' => 'admin', 'active' => '1']))
    ->assert('params echoed', fn(CTGTestState $state) => $state->getSubject()['body']['params']['role'], CTGTestPredicates::equals('admin'))
    ->assert('active param', fn(CTGTestState $state) => $state->getSubject()['body']['params']['active'], CTGTestPredicates::equals('1'))
    ;

$pipelines[] = CTGTest::init('GET — with per-request headers')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php", [], ['X-Per-Request' => 'one-off']))
    ->assert('per-request header sent', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Per-Request'] ?? null, CTGTestPredicates::equals('one-off'))
    ;

$pipelines[] = CTGTest::init('GET — JSON endpoint')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/json.php"))
    ->assert('body is array', fn(CTGTestState $state) => is_array($state->getSubject()['body']), CTGTestPredicates::isTrue())
    ->assert('has users', fn(CTGTestState $state) => count($state->getSubject()['body']['users']), CTGTestPredicates::equals(3))
    ->assert('first user is Alice', fn(CTGTestState $state) => $state->getSubject()['body']['users'][0]['name'], CTGTestPredicates::equals('Alice'))
    ;

$pipelines[] = CTGTest::init('POST — JSON body')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", [
            'name' => 'Alice',
            'email' => 'alice@test.com',
        ]))
    ->assert('method is POST', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('POST'))
    ->assert('body sent', fn(CTGTestState $state) => $state->getSubject()['body']['body']['name'], CTGTestPredicates::equals('Alice'))
    ->assert('email sent', fn(CTGTestState $state) => $state->getSubject()['body']['body']['email'], CTGTestPredicates::equals('alice@test.com'))
    ;

$pipelines[] = CTGTest::init('POST — with query params and body')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", ['data' => 'value'], ['key' => 'abc']))
    ->assert('body sent', fn(CTGTestState $state) => $state->getSubject()['body']['body']['data'], CTGTestPredicates::equals('value'))
    ->assert('param sent', fn(CTGTestState $state) => $state->getSubject()['body']['params']['key'], CTGTestPredicates::equals('abc'))
    ;

$pipelines[] = CTGTest::init('POST — with per-request headers')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", ['x' => 1], [], ['X-Idempotency-Key' => 'abc']))
    ->assert('header sent', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Idempotency-Key'] ?? null, CTGTestPredicates::equals('abc'))
    ;

$pipelines[] = CTGTest::init('PUT — JSON body')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->PUT("{$endpointBase}/echo.php", ['name' => 'Updated']))
    ->assert('method is PUT', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('PUT'))
    ->assert('body sent', fn(CTGTestState $state) => $state->getSubject()['body']['body']['name'], CTGTestPredicates::equals('Updated'))
    ;

$pipelines[] = CTGTest::init('PATCH — JSON body')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->PATCH("{$endpointBase}/echo.php", ['field' => 'patched']))
    ->assert('method is PATCH', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('PATCH'))
    ->assert('body sent', fn(CTGTestState $state) => $state->getSubject()['body']['body']['field'], CTGTestPredicates::equals('patched'))
    ;

$pipelines[] = CTGTest::init('DELETE — basic')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->DELETE("{$endpointBase}/echo.php"))
    ->assert('method is DELETE', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('DELETE'))
    ;

$pipelines[] = CTGTest::init('DELETE — with query params')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->DELETE("{$endpointBase}/echo.php", ['force' => 'true']))
    ->assert('param sent', fn(CTGTestState $state) => $state->getSubject()['body']['params']['force'], CTGTestPredicates::equals('true'))
    ;

// ═══════════════════════════════════════════════════════════════
// RESPONSE STRUCTURE
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('response — has all required keys')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('has status', fn(CTGTestState $state) => isset($state->getSubject()['status']), CTGTestPredicates::isTrue())
    ->assert('has ok', fn(CTGTestState $state) => isset($state->getSubject()['ok']), CTGTestPredicates::isTrue())
    ->assert('has headers', fn(CTGTestState $state) => isset($state->getSubject()['headers']), CTGTestPredicates::isTrue())
    ->assert('has body', fn(CTGTestState $state) => isset($state->getSubject()['body']), CTGTestPredicates::isTrue())
    ->assert('headers is array', fn(CTGTestState $state) => is_array($state->getSubject()['headers']), CTGTestPredicates::isTrue())
    ->assert('header keys lowercase', fn(CTGTestState $state) => isset($state->getSubject()['headers']['content-type']), CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// HTTP STATUS CODES
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('status — 200 is ok')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 200]))
    ->assert('ok is true', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isTrue())
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

$pipelines[] = CTGTest::init('status — 201 is ok')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 201]))
    ->assert('ok is true', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('status — 400 is not ok')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 400]))
    ->assert('ok is false', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('status is 400', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(400))
    ->assert('body still available', fn(CTGTestState $state) => is_array($state->getSubject()['body']), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('status — 401 is not ok')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 401]))
    ->assert('ok is false', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('status is 401', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(401))
    ;

$pipelines[] = CTGTest::init('status — 404 is not ok')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 404]))
    ->assert('ok is false', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('status is 404', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(404))
    ;

$pipelines[] = CTGTest::init('status — 500 is not ok')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 500]))
    ->assert('ok is false', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('status is 500', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(500))
    ;

// ═══════════════════════════════════════════════════════════════
// AUTHENTICATION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('auth — no token returns 401')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/auth.php"))
    ->assert('status is 401', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(401))
    ->assert('error message', fn(CTGTestState $state) => $state->getSubject()['body']['error'], CTGTestPredicates::equals('No authorization header'))
    ;

$pipelines[] = CTGTest::init('auth — wrong token returns 403')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setToken('wrong-token')
        ->GET("{$endpointBase}/auth.php"))
    ->assert('status is 403', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(403))
    ->assert('error message', fn(CTGTestState $state) => $state->getSubject()['body']['error'], CTGTestPredicates::equals('Invalid token'))
    ;

$pipelines[] = CTGTest::init('auth — valid token returns 200')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setToken('test-jwt-token-12345')
        ->GET("{$endpointBase}/auth.php"))
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ->assert('authenticated', fn(CTGTestState $state) => $state->getSubject()['body']['authenticated'], CTGTestPredicates::isTrue())
    ->assert('token echoed', fn(CTGTestState $state) => $state->getSubject()['body']['token'], CTGTestPredicates::equals('test-jwt-token-12345'))
    ;

$pipelines[] = CTGTest::init('auth — token persists across requests')
    ->stage('create client', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setToken('test-jwt-token-12345'))
    ->stage('first request', fn(CTGTestState $state) => ['api' => $state->getSubject(), 'r1' => $state->getSubject()->GET("{$endpointBase}/auth.php")])
    ->stage('second request', fn(CTGTestState $state) => ['r1' => $state->getSubject()['r1'], 'r2' => $state->getSubject()['api']->GET("{$endpointBase}/auth.php")])
    ->assert('first authenticated', fn(CTGTestState $state) => $state->getSubject()['r1']['body']['authenticated'], CTGTestPredicates::isTrue())
    ->assert('second authenticated', fn(CTGTestState $state) => $state->getSubject()['r2']['body']['authenticated'], CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('auth — clearToken removes auth')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $api = CTGAPIClient::init($baseUrl)->setToken('test-jwt-token-12345');
        $before = $api->GET("{$endpointBase}/auth.php");
        $api->clearToken();
        $after = $api->GET("{$endpointBase}/auth.php");
        return ['before' => $before, 'after' => $after];
    })
    ->assert('before: authenticated', fn(CTGTestState $state) => $state->getSubject()['before']['status'], CTGTestPredicates::equals(200))
    ->assert('after: not authenticated', fn(CTGTestState $state) => $state->getSubject()['after']['status'], CTGTestPredicates::equals(401))
    ;

$pipelines[] = CTGTest::init('auth — getToken returns current token')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl){
        $api = CTGAPIClient::init($baseUrl);
        $before = $api->getToken();
        $api->setToken('my-token');
        $during = $api->getToken();
        $api->clearToken();
        $after = $api->getToken();
        return ['before' => $before, 'during' => $during, 'after' => $after];
    })
    ->assert('initially null', fn(CTGTestState $state) => $state->getSubject()['before'], CTGTestPredicates::isNull())
    ->assert('set to my-token', fn(CTGTestState $state) => $state->getSubject()['during'], CTGTestPredicates::equals('my-token'))
    ->assert('cleared to null', fn(CTGTestState $state) => $state->getSubject()['after'], CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('auth — token sent with POST')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setToken('test-jwt-token-12345')
        ->POST("{$endpointBase}/auth.php", ['data' => 'test']))
    ->assert('authenticated via POST', fn(CTGTestState $state) => $state->getSubject()['body']['authenticated'], CTGTestPredicates::isTrue())
    ->assert('method is POST', fn(CTGTestState $state) => $state->getSubject()['body']['method'], CTGTestPredicates::equals('POST'))
    ;

// ═══════════════════════════════════════════════════════════════
// HEADERS
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('headers — custom header sent')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setHeader('X-Custom-Header', 'test-value')
        ->GET("{$endpointBase}/echo.php"))
    ->assert('header echoed', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Custom-Header'] ?? null, CTGTestPredicates::equals('test-value'))
    ;

$pipelines[] = CTGTest::init('headers — multiple headers')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setHeaders(['X-First' => 'one', 'X-Second' => 'two'])
        ->GET("{$endpointBase}/echo.php"))
    ->assert('first header', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-First'] ?? null, CTGTestPredicates::equals('one'))
    ->assert('second header', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Second'] ?? null, CTGTestPredicates::equals('two'))
    ;

$pipelines[] = CTGTest::init('headers — removeHeader')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $api = CTGAPIClient::init($baseUrl)->setHeader('X-Remove-Me', 'present');
        $before = $api->GET("{$endpointBase}/echo.php");
        $api->removeHeader('X-Remove-Me');
        $after = $api->GET("{$endpointBase}/echo.php");
        return ['before' => $before, 'after' => $after];
    })
    ->assert('present before', fn(CTGTestState $state) => $state->getSubject()['before']['body']['headers']['X-Remove-Me'] ?? null, CTGTestPredicates::equals('present'))
    ->assert('gone after', fn(CTGTestState $state) => $state->getSubject()['after']['body']['headers']['X-Remove-Me'] ?? null, CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('headers — Content-Type auto-set for JSON')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->POST("{$endpointBase}/echo.php", ['test' => 'data']))
    ->assert('content-type is json', fn(CTGTestState $state) => str_contains(
        $state->getSubject()['body']['headers']['Content-Type'] ?? '', 'application/json'), CTGTestPredicates::isTrue())
    ;

// ── Per-request header merge behavior ───────────────────────────

$pipelines[] = CTGTest::init('per-request headers — override default for one call')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $api = CTGAPIClient::init($baseUrl)->setHeader('X-Lang', 'en');
        $overridden = $api->GET("{$endpointBase}/echo.php", [], ['X-Lang' => 'fr']);
        $nextCall = $api->GET("{$endpointBase}/echo.php");
        return ['overridden' => $overridden, 'next' => $nextCall];
    })
    ->assert('overridden to fr', fn(CTGTestState $state) => $state->getSubject()['overridden']['body']['headers']['X-Lang'] ?? null, CTGTestPredicates::equals('fr'))
    ->assert('next call back to en', fn(CTGTestState $state) => $state->getSubject()['next']['body']['headers']['X-Lang'] ?? null, CTGTestPredicates::equals('en'))
    ;

$pipelines[] = CTGTest::init('per-request headers — supplement defaults')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setHeader('X-Default', 'default-val')
        ->GET("{$endpointBase}/echo.php", [], ['X-Extra' => 'extra-val']))
    ->assert('default present', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Default'] ?? null, CTGTestPredicates::equals('default-val'))
    ->assert('extra present', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-Extra'] ?? null, CTGTestPredicates::equals('extra-val'))
    ;

$pipelines[] = CTGTest::init('per-request headers — do not persist')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $api = CTGAPIClient::init($baseUrl);
        $api->GET("{$endpointBase}/echo.php", [], ['X-One-Off' => 'temp']);
        $nextCall = $api->GET("{$endpointBase}/echo.php");
        return $nextCall;
    })
    ->assert('one-off header gone', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['X-One-Off'] ?? null, CTGTestPredicates::isNull())
    ;

// ═══════════════════════════════════════════════════════════════
// FILE UPLOADS
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('upload — file via upload()')
    ->stage('setup', function(CTGTestState $state) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'test file content');
        return $tmp;
    })
    ->stage('upload', fn(CTGTestState $state) => [
        'tmp' => $state->getSubject(),
        'result' => CTGAPIClient::init($baseUrl)
            ->upload("{$endpointBase}/upload.php", $state->getSubject(), ['title' => 'Test Doc'])
    ])
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['result']['status'], CTGTestPredicates::equals(200))
    ->assert('file received', fn(CTGTestState $state) => isset($state->getSubject()['result']['body']['files']['file']), CTGTestPredicates::isTrue())
    ->assert('file has size', fn(CTGTestState $state) => $state->getSubject()['result']['body']['files']['file']['size'] > 0, CTGTestPredicates::isTrue())
    ->assert('extra field sent', fn(CTGTestState $state) => $state->getSubject()['result']['body']['fields']['title'], CTGTestPredicates::equals('Test Doc'))
    ->stage('cleanup', fn(CTGTestState $state) => unlink($state->getSubject()['tmp']))
    ;

$pipelines[] = CTGTest::init('upload — custom field name')
    ->stage('setup', function(CTGTestState $state) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'avatar data');
        return $tmp;
    })
    ->stage('upload', fn(CTGTestState $state) => [
        'tmp' => $state->getSubject(),
        'result' => CTGAPIClient::init($baseUrl)
            ->upload("{$endpointBase}/upload.php", $state->getSubject(), [], 'avatar')
    ])
    ->assert('file under avatar key', fn(CTGTestState $state) => isset($state->getSubject()['result']['body']['files']['avatar']), CTGTestPredicates::isTrue())
    ->stage('cleanup', fn(CTGTestState $state) => unlink($state->getSubject()['tmp']))
    ;

$pipelines[] = CTGTest::init('upload — CURLFile in POST auto-detects multipart')
    ->stage('setup', function(CTGTestState $state) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'direct curlfile test');
        return $tmp;
    })
    ->stage('post', fn(CTGTestState $state) => [
        'tmp' => $state->getSubject(),
        'result' => CTGAPIClient::init($baseUrl)
            ->POST("{$endpointBase}/upload.php", [
                'document' => new \CURLFile($state->getSubject()),
                'category' => 'reports',
            ])
    ])
    ->assert('file received', fn(CTGTestState $state) => isset($state->getSubject()['result']['body']['files']['document']), CTGTestPredicates::isTrue())
    ->assert('extra field sent', fn(CTGTestState $state) => $state->getSubject()['result']['body']['fields']['category'], CTGTestPredicates::equals('reports'))
    ->stage('cleanup', fn(CTGTestState $state) => unlink($state->getSubject()['tmp']))
    ;

// ═══════════════════════════════════════════════════════════════
// URL NORMALIZATION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('URL — trailing slash on base, leading slash on path')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl . '/')
        ->GET("/{$endpointBase}/echo.php"))
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

$pipelines[] = CTGTest::init('URL — no slashes')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// TRANSPORT ERRORS
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('error — connection refused')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::init('http://localhost:19999')->GET('/nonexistent');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws CONNECTION_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CONNECTION_FAILED'))
    ;

$pipelines[] = CTGTest::init('error — connection refused via static request')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::request('GET', 'http://localhost:19999/nonexistent');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws CONNECTION_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('CONNECTION_FAILED'))
    ;

$pipelines[] = CTGTest::init('error — DNS failure')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::init('http://this-domain-definitely-does-not-exist-xyz123.com')
                ->GET('/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return in_array($e->type, ['DNS_FAILED', 'CONNECTION_FAILED']);
        }
    })
    ->assert('throws DNS or connection error', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

$pipelines[] = CTGTest::init('error — timeout')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::init('http://10.255.255.1', ['timeout' => 1])
                ->GET('/test');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return in_array($e->type, ['TIMEOUT', 'CONNECTION_FAILED']);
        }
    })
    ->assert('throws timeout or connection error', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// HTTP_ERROR — CALLER-INITIATED
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('HTTP_ERROR — caller throws on non-ok response')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
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
    ->assert('type is HTTP_ERROR', fn(CTGTestState $state) => $state->getSubject()['type'], CTGTestPredicates::equals('HTTP_ERROR'))
    ->assert('status is 404', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(404))
    ;

$pipelines[] = CTGTest::init('HTTP_ERROR — chainable with transport errors')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
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
    ->assert('matched HTTP_ERROR with status', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('http:401'))
    ;

$pipelines[] = CTGTest::init('HTTP_ERROR — full response in data')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
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
    ->assert('data has status', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(500))
    ->assert('data has ok', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('data has body', fn(CTGTestState $state) => is_array($state->getSubject()['body']), CTGTestPredicates::isTrue())
    ->assert('data has headers', fn(CTGTestState $state) => is_array($state->getSubject()['headers']), CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// METHOD VALIDATION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('static request — HEAD is valid method')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'HEAD', "http://localhost{$endpointBase}/echo.php"
    ))
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

$pipelines[] = CTGTest::init('static request — OPTIONS is valid method')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'OPTIONS', "http://localhost{$endpointBase}/echo.php"
    ))
    ->assert('status is 200', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// HEADER VALIDATION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('static request — CRLF stripped from header values')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::request(
        'GET', "http://localhost{$endpointBase}/echo.php",
        [], [], ['X-Test-Header' => "safe\r\nX-Injected: evil"]
    ))
    ->assert('echoed value has no CRLF', fn(CTGTestState $state) => str_contains($state->getSubject()['body']['headers']['X-Test-Header'] ?? '', "\r\n"), CTGTestPredicates::isFalse())
    ->assert('no injected header', fn(CTGTestState $state) => isset($state->getSubject()['body']['headers']['X-Injected']), CTGTestPredicates::isFalse())
    ;

// ═══════════════════════════════════════════════════════════════
// CONTENT-TYPE
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('static request — multipart body skips Content-Type auto-set')
    ->stage('setup', function(CTGTestState $state) {
        $tmp = tempnam('/tmp', 'ctg_test_');
        file_put_contents($tmp, 'multipart test content');
        return $tmp;
    })
    ->stage('execute', fn(CTGTestState $state) => [
        'tmp' => $state->getSubject(),
        'result' => CTGAPIClient::request(
            'POST', "http://localhost{$endpointBase}/upload.php",
            ['file' => new \CURLFile($state->getSubject())]
        ),
    ])
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['result']['status'], CTGTestPredicates::equals(200))
    ->assert('file was received (not JSON-encoded)', fn(CTGTestState $state) => isset($state->getSubject()['result']['body']['files']) || isset($state->getSubject()['result']['body']['file']), CTGTestPredicates::isTrue())
    ->stage('cleanup', fn(CTGTestState $state) => unlink($state->getSubject()['tmp']))
    ;

// ═══════════════════════════════════════════════════════════════
// UPLOAD ERRORS
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('upload — missing file throws REQUEST_FAILED')
    ->stage('attempt', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        try {
            CTGAPIClient::init($baseUrl)
                ->upload("{$endpointBase}/upload.php", '/nonexistent/file.txt');
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws REQUEST_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('REQUEST_FAILED'))
    ;

// ═══════════════════════════════════════════════════════════════
// HEADER MERGE PRIORITY
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('headers — default Authorization overrides automatic token')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->setToken('my-jwt-token')
        ->setHeader('Authorization', 'Basic xyz')
        ->GET("{$endpointBase}/echo.php"))
    ->assert('uses Basic not Bearer', fn(CTGTestState $state) => $state->getSubject()['body']['headers']['Authorization'] ?? null, CTGTestPredicates::equals('Basic xyz'))
    ;

// ═══════════════════════════════════════════════════════════════
// RESPONSE STATUS — 3xx
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('response — 3xx returns ok false')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 301]))
    ->assert('ok is false', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('status is 301', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(301))
    ;

// ═══════════════════════════════════════════════════════════════
// RESPONSE BODY PARSING
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('response — non-JSON body returns raw string')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/redirect.php"))
    ->assert('body is string', fn(CTGTestState $state) => is_string($state->getSubject()['body']), CTGTestPredicates::isTrue())
    ->assert('body is raw text', fn(CTGTestState $state) => $state->getSubject()['body'], CTGTestPredicates::equals('redirecting'))
    ;

$pipelines[] = CTGTest::init('response — empty body returns empty string')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/status.php", ['code' => 204]))
    ->assert('body is empty string', fn(CTGTestState $state) => $state->getSubject()['body'], CTGTestPredicates::equals(''))
    ;

// ═══════════════════════════════════════════════════════════════
// RESPONSE HEADER PARSING
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('response — duplicate headers comma-joined')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/headers.php"))
    ->assert('x-duplicate is comma-joined', fn(CTGTestState $state) => $state->getSubject()['headers']['x-duplicate'] ?? null, CTGTestPredicates::equals('value1, value2'))
    ;

$pipelines[] = CTGTest::init('response — set-cookie collected as array')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/headers.php"))
    ->assert('set-cookie is array', fn(CTGTestState $state) => is_array($state->getSubject()['headers']['set-cookie'] ?? null), CTGTestPredicates::isTrue())
    ->assert('has two cookies', fn(CTGTestState $state) => count($state->getSubject()['headers']['set-cookie'] ?? []), CTGTestPredicates::equals(2))
    ->assert('first cookie', fn(CTGTestState $state) => $state->getSubject()['headers']['set-cookie'][0] ?? null, CTGTestPredicates::equals('session=abc; Path=/'))
    ->assert('second cookie', fn(CTGTestState $state) => $state->getSubject()['headers']['set-cookie'][1] ?? null, CTGTestPredicates::equals('theme=dark; Path=/'))
    ;

// ═══════════════════════════════════════════════════════════════
// REDIRECT POLICY
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('response — 3xx not followed, Location header present')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/redirect.php"))
    ->assert('status is 302', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(302))
    ->assert('ok is false', fn(CTGTestState $state) => $state->getSubject()['ok'], CTGTestPredicates::isFalse())
    ->assert('location header present', fn(CTGTestState $state) => isset($state->getSubject()['headers']['location']), CTGTestPredicates::isTrue())
    ->assert('location points to echo', fn(CTGTestState $state) => $state->getSubject()['headers']['location'] ?? null, CTGTestPredicates::equals('/tests/endpoints/echo.php'))
    ;

// ═══════════════════════════════════════════════════════════════
// TRANSPORT ERROR DATA
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('transport error — data contains url and method')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            CTGAPIClient::init('http://localhost:19999')->GET('/nonexistent');
            return null;
        } catch (CTGAPIClientError $e) {
            return $e->data;
        }
    })
    ->assert('data has url', fn(CTGTestState $state) => isset($state->getSubject()['url']), CTGTestPredicates::isTrue())
    ->assert('data has method', fn(CTGTestState $state) => isset($state->getSubject()['method']), CTGTestPredicates::isTrue())
    ->assert('data has curl_errno', fn(CTGTestState $state) => isset($state->getSubject()['curl_errno']), CTGTestPredicates::isTrue())
    ->assert('method is GET', fn(CTGTestState $state) => $state->getSubject()['method'], CTGTestPredicates::equals('GET'))
    ;

// ═══════════════════════════════════════════════════════════════
// ERROR DATA SAFETY
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('transport error — data does not contain auth or cookie headers')
    ->stage('attempt', function(CTGTestState $state) use ($baseUrl){
        try {
            CTGAPIClient::init('http://localhost:19999')
                ->setToken('secret-token-12345')
                ->setHeader('Cookie', 'session=secret-session-id')
                ->GET('/nonexistent');
            return null;
        } catch (CTGAPIClientError $e) {
            return $e->data;
        }
    })
    ->assert('has url', fn(CTGTestState $state) => isset($state->getSubject()['url']), CTGTestPredicates::isTrue())
    ->assert('has method', fn(CTGTestState $state) => isset($state->getSubject()['method']), CTGTestPredicates::isTrue())
    ->assert('no authorization key', fn(CTGTestState $state) => isset($state->getSubject()['authorization']), CTGTestPredicates::isFalse())
    ->assert('no cookie key', fn(CTGTestState $state) => isset($state->getSubject()['cookie']), CTGTestPredicates::isFalse())
    ->assert('no headers key', fn(CTGTestState $state) => isset($state->getSubject()['headers']), CTGTestPredicates::isFalse())
    ->assert('url does not contain token', fn(CTGTestState $state) => str_contains($state->getSubject()['url'] ?? '', 'secret-token'), CTGTestPredicates::isFalse())
    ;

// ═══════════════════════════════════════════════════════════════
// USER-AGENT DEFAULT
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('request — default User-Agent header sent')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl)
        ->GET("{$endpointBase}/echo.php"))
    ->assert('User-Agent contains CTGAPIClient', fn(CTGTestState $state) => str_contains(
        $state->getSubject()['body']['headers']['User-Agent'] ?? '', 'CTGAPIClient'), CTGTestPredicates::isTrue())
    ;

// ═══════════════════════════════════════════════════════════════
// PROXY BEHAVIOR
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('request — HTTP_PROXY env not honored')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $old = getenv('HTTP_PROXY');
        putenv('HTTP_PROXY=http://invalid-proxy:9999');
        try {
            $result = CTGAPIClient::init($baseUrl)
                ->GET("{$endpointBase}/echo.php");
        } finally {
            $old === false ? putenv('HTTP_PROXY') : putenv("HTTP_PROXY={$old}");
        }
        return $result;
    })
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

$pipelines[] = CTGTest::init('request — HTTPS_PROXY env not honored')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $old = getenv('HTTPS_PROXY');
        putenv('HTTPS_PROXY=http://invalid-proxy:9999');
        try {
            $result = CTGAPIClient::init($baseUrl)
                ->GET("{$endpointBase}/echo.php");
        } finally {
            $old === false ? putenv('HTTPS_PROXY') : putenv("HTTPS_PROXY={$old}");
        }
        return $result;
    })
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

$pipelines[] = CTGTest::init('request — ALL_PROXY env not honored')
    ->stage('execute', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        $old = getenv('ALL_PROXY');
        putenv('ALL_PROXY=http://invalid-proxy:9999');
        try {
            $result = CTGAPIClient::init($baseUrl)
                ->GET("{$endpointBase}/echo.php");
        } finally {
            $old === false ? putenv('ALL_PROXY') : putenv("ALL_PROXY={$old}");
        }
        return $result;
    })
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// SSRF ALLOWLIST
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('ssrf — disallowed host throws INVALID_URL')
    ->stage('attempt', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        try {
            CTGAPIClient::init($baseUrl, [
                'allowed_hosts' => ['api.example.com'],
            ])->GET("{$endpointBase}/echo.php");
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_URL', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_URL'))
    ;

$pipelines[] = CTGTest::init('ssrf — disallowed scheme throws INVALID_URL')
    ->stage('attempt', function(CTGTestState $state) use ($endpointBase){
        try {
            CTGAPIClient::init('http://localhost', [
                'allowed_schemes' => ['https'],
            ])->GET("{$endpointBase}/echo.php");
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws INVALID_URL', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('INVALID_URL'))
    ;

$pipelines[] = CTGTest::init('ssrf — allowed host succeeds')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl, [
        'allowed_hosts' => ['localhost'],
    ])->GET("{$endpointBase}/echo.php"))
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// MAX RESPONSE BYTES
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('response size — exceeds limit throws REQUEST_FAILED')
    ->stage('attempt', function(CTGTestState $state) use ($baseUrl, $endpointBase){
        try {
            CTGAPIClient::init($baseUrl, [
                'max_response_bytes' => 1,
            ])->GET("{$endpointBase}/echo.php");
            return 'no exception';
        } catch (CTGAPIClientError $e) {
            return $e->type;
        }
    })
    ->assert('throws REQUEST_FAILED', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('REQUEST_FAILED'))
    ;

$pipelines[] = CTGTest::init('response size — under limit succeeds')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClient::init($baseUrl, [
        'max_response_bytes' => 1048576,
    ])->GET("{$endpointBase}/echo.php"))
    ->assert('request succeeded', fn(CTGTestState $state) => $state->getSubject()['status'], CTGTestPredicates::equals(200))
    ;

// ═══════════════════════════════════════════════════════════════
// CTGFnprog INTEGRATION
// ═══════════════════════════════════════════════════════════════

$pipelines[] = CTGTest::init('CTGFnprog — pipe over response body')
    ->stage('execute', fn(CTGTestState $state) => CTGFnprog::pipe([
        fn($_) => CTGAPIClient::init($baseUrl)->GET("{$endpointBase}/json.php"),
        fn($r) => $r['body']['users'],
        CTGFnprog::filter(fn($u) => $u['active']),
        CTGFnprog::sortBy('name'),
        CTGFnprog::pluck('name'),
    ])(null))
    ->assert('returns active names sorted', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(['Alice', 'Bob']))
    ;

$pipelines[] = CTGTest::init('CTGFnprog — pick fields from API response')
    ->stage('execute', fn(CTGTestState $state) => CTGFnprog::pipe([
        fn($_) => CTGAPIClient::init($baseUrl)->GET("{$endpointBase}/json.php"),
        fn($r) => $r['body']['users'],
        CTGFnprog::pick(['id', 'name']),
    ])(null))
    ->assert('first has only id and name', fn(CTGTestState $state) => array_keys($state->getSubject()[0]), CTGTestPredicates::equals(['id', 'name']))
    ;

return $pipelines;
