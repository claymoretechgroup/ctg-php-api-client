<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\ApiClient\CTGAPIClientError;

// Tests for CTGAPIClientError — construction, lookup, chainable handler

$config = ['output' => 'console'];

// ── Construction ────────────────────────────────────────────────

CTGTest::init('construct with type name')
    ->stage('create', fn($_) => new CTGAPIClientError('TIMEOUT', 'timed out', ['url' => 'http://x']))
    ->assert('code is 1001', fn($e) => $e->getCode(), 1001)
    ->assert('type is TIMEOUT', fn($e) => $e->type, 'TIMEOUT')
    ->assert('msg is set', fn($e) => $e->msg, 'timed out')
    ->assert('data is set', fn($e) => $e->data, ['url' => 'http://x'])
    ->start(null, $config);

CTGTest::init('construct with integer code')
    ->stage('create', fn($_) => new CTGAPIClientError(1000, 'refused'))
    ->assert('type is CONNECTION_FAILED', fn($e) => $e->type, 'CONNECTION_FAILED')
    ->assert('code is 1000', fn($e) => $e->getCode(), 1000)
    ->start(null, $config);

CTGTest::init('construct with unknown type throws')
    ->stage('attempt', function($_) {
        try {
            new CTGAPIClientError('NONEXISTENT');
            return 'no exception';
        } catch (\InvalidArgumentException $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn($r) => $r, 'threw')
    ->start(null, $config);

CTGTest::init('construction — unknown integer code throws')
    ->stage('attempt', function($_) {
        try {
            new CTGAPIClientError(99999);
            return 'no exception';
        } catch (\InvalidArgumentException $e) {
            return 'threw';
        }
    })
    ->assert('throws InvalidArgumentException', fn($r) => $r, 'threw')
    ->start(null, $config);

// ── Lookup ──────────────────────────────────────────────────────

CTGTest::init('lookup — name to code')
    ->stage('execute', fn($_) => CTGAPIClientError::lookup('TIMEOUT'))
    ->assert('returns 1001', fn($r) => $r, 1001)
    ->start(null, $config);

CTGTest::init('lookup — code to name')
    ->stage('execute', fn($_) => CTGAPIClientError::lookup(1001))
    ->assert('returns TIMEOUT', fn($r) => $r, 'TIMEOUT')
    ->start(null, $config);

CTGTest::init('lookup — unknown string returns null')
    ->stage('execute', fn($_) => CTGAPIClientError::lookup('NONEXISTENT'))
    ->assert('returns null', fn($r) => $r, null)
    ->start(null, $config);

CTGTest::init('lookup — unknown integer returns null')
    ->stage('execute', fn($_) => CTGAPIClientError::lookup(99999))
    ->assert('returns null', fn($r) => $r, null)
    ->start(null, $config);

CTGTest::init('lookup — all codes')
    ->stage('execute', fn($_) => [
        CTGAPIClientError::lookup('CONNECTION_FAILED'),
        CTGAPIClientError::lookup('TIMEOUT'),
        CTGAPIClientError::lookup('DNS_FAILED'),
        CTGAPIClientError::lookup('SSL_ERROR'),
        CTGAPIClientError::lookup('REQUEST_FAILED'),
        CTGAPIClientError::lookup('INVALID_URL'),
        CTGAPIClientError::lookup('INVALID_METHOD'),
        CTGAPIClientError::lookup('INVALID_BODY'),
        CTGAPIClientError::lookup('INVALID_HEADER'),
        CTGAPIClientError::lookup('HTTP_ERROR'),
    ])
    ->assert('CONNECTION_FAILED', fn($r) => $r[0], 1000)
    ->assert('TIMEOUT', fn($r) => $r[1], 1001)
    ->assert('DNS_FAILED', fn($r) => $r[2], 1002)
    ->assert('SSL_ERROR', fn($r) => $r[3], 1003)
    ->assert('REQUEST_FAILED', fn($r) => $r[4], 2000)
    ->assert('INVALID_URL', fn($r) => $r[5], 3000)
    ->assert('INVALID_METHOD', fn($r) => $r[6], 3001)
    ->assert('INVALID_BODY', fn($r) => $r[7], 3002)
    ->assert('INVALID_HEADER', fn($r) => $r[8], 3003)
    ->assert('HTTP_ERROR', fn($r) => $r[9], 4000)
    ->start(null, $config);

// ── Chainable on()/otherwise() ──────────────────────────────────

CTGTest::init('on — matches type name')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $matched = null;
        $e->on('TIMEOUT', function($err) use (&$matched) { $matched = $err->type; });
        return $matched;
    })
    ->assert('handler called', fn($r) => $r, 'TIMEOUT')
    ->start(null, $config);

CTGTest::init('on — matches by integer code')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $matched = null;
        $e->on(1001, function($err) use (&$matched) { $matched = $err->type; });
        return $matched;
    })
    ->assert('handler called via integer code', fn($r) => $r, 'TIMEOUT')
    ->start(null, $config);

CTGTest::init('on — short circuits')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $calls = [];
        $e->on('TIMEOUT', function($err) use (&$calls) { $calls[] = 'first'; })
          ->on('TIMEOUT', function($err) use (&$calls) { $calls[] = 'second'; });
        return $calls;
    })
    ->assert('only first called', fn($r) => $r, ['first'])
    ->start(null, $config);

CTGTest::init('otherwise — called when no match')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $matched = null;
        $e->on('CONNECTION_FAILED', function($err) use (&$matched) { $matched = 'on'; })
          ->otherwise(function($err) use (&$matched) { $matched = 'otherwise'; });
        return $matched;
    })
    ->assert('otherwise called', fn($r) => $r, 'otherwise')
    ->start(null, $config);

// ── on() — unknown type throws ──────────────────────────────────

CTGTest::init('on — unknown string type throws InvalidArgumentException')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        try {
            $e->on('NONEXISTENT_TYPE', fn($err) => null);
            return 'no exception';
        } catch (\InvalidArgumentException $ex) {
            return 'thrown';
        }
    })
    ->assert('throws', fn($r) => $r, 'thrown')
    ->start(null, $config);

CTGTest::init('on — unknown integer code throws InvalidArgumentException')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        try {
            $e->on(99999, fn($err) => null);
            return 'no exception';
        } catch (\InvalidArgumentException $ex) {
            return 'thrown';
        }
    })
    ->assert('throws', fn($r) => $r, 'thrown')
    ->start(null, $config);

// ── HTTP_ERROR type ─────────────────────────────────────────────

CTGTest::init('HTTP_ERROR — construct with response data')
    ->stage('create', fn($_) => new CTGAPIClientError('HTTP_ERROR', 'Status: 404', [
        'status' => 404,
        'ok' => false,
        'body' => ['error' => 'not found'],
    ]))
    ->assert('code is 4000', fn($e) => $e->getCode(), 4000)
    ->assert('type is HTTP_ERROR', fn($e) => $e->type, 'HTTP_ERROR')
    ->assert('data has status', fn($e) => $e->data['status'], 404)
    ->assert('data has body', fn($e) => $e->data['body']['error'], 'not found')
    ->start(null, $config);

CTGTest::init('HTTP_ERROR — chainable with transport errors')
    ->stage('execute', function($_) {
        $e = new CTGAPIClientError('HTTP_ERROR', 'Status: 401', [
            'status' => 401,
            'ok' => false,
            'body' => ['error' => 'unauthorized'],
        ]);
        $matched = null;
        $e->on('TIMEOUT', function($err) use (&$matched) { $matched = 'timeout'; })
          ->on('HTTP_ERROR', function($err) use (&$matched) { $matched = 'http:' . $err->data['status']; })
          ->otherwise(function($err) use (&$matched) { $matched = 'otherwise'; });
        return $matched;
    })
    ->assert('matched HTTP_ERROR with status', fn($r) => $r, 'http:401')
    ->start(null, $config);
