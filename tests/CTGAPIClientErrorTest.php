<?php
declare(strict_types=1);


use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;
use CTG\ApiClient\CTGAPIClientError;

$pipelines = [];

// Tests for CTGAPIClientError — construction, lookup, chainable handler


// ── Construction ────────────────────────────────────────────────

$pipelines[] = CTGTest::init('construct with type name')
    ->stage('create', fn(CTGTestState $state) => new CTGAPIClientError('TIMEOUT', 'timed out', ['url' => 'http://x']))
    ->assert('code is 1001', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(1001))
    ->assert('type is TIMEOUT', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('TIMEOUT'))
    ->assert('msg is set', fn(CTGTestState $state) => $state->getSubject()->msg, CTGTestPredicates::equals('timed out'))
    ->assert('data is set', fn(CTGTestState $state) => $state->getSubject()->data, CTGTestPredicates::equals(['url' => 'http://x']))
    ;

$pipelines[] = CTGTest::init('construct with integer code')
    ->stage('create', fn(CTGTestState $state) => new CTGAPIClientError(1000, 'refused'))
    ->assert('type is CONNECTION_FAILED', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('CONNECTION_FAILED'))
    ->assert('code is 1000', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(1000))
    ;

$pipelines[] = CTGTest::init('construct with unknown type throws')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            new CTGAPIClientError('NONEXISTENT');
            return 'no exception';
        } catch (\InvalidArgumentException $e) {
            return 'threw';
        }
    })
    ->assert('throws', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('threw'))
    ;

$pipelines[] = CTGTest::init('construction — unknown integer code throws')
    ->stage('attempt', function(CTGTestState $state) {
        try {
            new CTGAPIClientError(99999);
            return 'no exception';
        } catch (\InvalidArgumentException $e) {
            return 'threw';
        }
    })
    ->assert('throws InvalidArgumentException', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('threw'))
    ;

// ── Lookup ──────────────────────────────────────────────────────

$pipelines[] = CTGTest::init('lookup — name to code')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClientError::lookup('TIMEOUT'))
    ->assert('returns 1001', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(1001))
    ;

$pipelines[] = CTGTest::init('lookup — code to name')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClientError::lookup(1001))
    ->assert('returns TIMEOUT', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('TIMEOUT'))
    ;

$pipelines[] = CTGTest::init('lookup — unknown string returns null')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClientError::lookup('NONEXISTENT'))
    ->assert('returns null', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('lookup — unknown integer returns null')
    ->stage('execute', fn(CTGTestState $state) => CTGAPIClientError::lookup(99999))
    ->assert('returns null', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::isNull())
    ;

$pipelines[] = CTGTest::init('lookup — all codes')
    ->stage('execute', fn(CTGTestState $state) => [
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
    ->assert('CONNECTION_FAILED', fn(CTGTestState $state) => $state->getSubject()[0], CTGTestPredicates::equals(1000))
    ->assert('TIMEOUT', fn(CTGTestState $state) => $state->getSubject()[1], CTGTestPredicates::equals(1001))
    ->assert('DNS_FAILED', fn(CTGTestState $state) => $state->getSubject()[2], CTGTestPredicates::equals(1002))
    ->assert('SSL_ERROR', fn(CTGTestState $state) => $state->getSubject()[3], CTGTestPredicates::equals(1003))
    ->assert('REQUEST_FAILED', fn(CTGTestState $state) => $state->getSubject()[4], CTGTestPredicates::equals(2000))
    ->assert('INVALID_URL', fn(CTGTestState $state) => $state->getSubject()[5], CTGTestPredicates::equals(3000))
    ->assert('INVALID_METHOD', fn(CTGTestState $state) => $state->getSubject()[6], CTGTestPredicates::equals(3001))
    ->assert('INVALID_BODY', fn(CTGTestState $state) => $state->getSubject()[7], CTGTestPredicates::equals(3002))
    ->assert('INVALID_HEADER', fn(CTGTestState $state) => $state->getSubject()[8], CTGTestPredicates::equals(3003))
    ->assert('HTTP_ERROR', fn(CTGTestState $state) => $state->getSubject()[9], CTGTestPredicates::equals(4000))
    ;

// ── Chainable on()/otherwise() ──────────────────────────────────

$pipelines[] = CTGTest::init('on — matches type name')
    ->stage('execute', function(CTGTestState $state) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $matched = null;
        $e->on('TIMEOUT', function($err) use (&$matched) { $matched = $err->type; });
        return $matched;
    })
    ->assert('handler called', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('TIMEOUT'))
    ;

$pipelines[] = CTGTest::init('on — matches by integer code')
    ->stage('execute', function(CTGTestState $state) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $matched = null;
        $e->on(1001, function($err) use (&$matched) { $matched = $err->type; });
        return $matched;
    })
    ->assert('handler called via integer code', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('TIMEOUT'))
    ;

$pipelines[] = CTGTest::init('on — short circuits')
    ->stage('execute', function(CTGTestState $state) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $calls = [];
        $e->on('TIMEOUT', function($err) use (&$calls) { $calls[] = 'first'; })
          ->on('TIMEOUT', function($err) use (&$calls) { $calls[] = 'second'; });
        return $calls;
    })
    ->assert('only first called', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals(['first']))
    ;

$pipelines[] = CTGTest::init('otherwise — called when no match')
    ->stage('execute', function(CTGTestState $state) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        $matched = null;
        $e->on('CONNECTION_FAILED', function($err) use (&$matched) { $matched = 'on'; })
          ->otherwise(function($err) use (&$matched) { $matched = 'otherwise'; });
        return $matched;
    })
    ->assert('otherwise called', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('otherwise'))
    ;

// ── on() — unknown type throws ──────────────────────────────────

$pipelines[] = CTGTest::init('on — unknown string type throws InvalidArgumentException')
    ->stage('execute', function(CTGTestState $state) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        try {
            $e->on('NONEXISTENT_TYPE', fn($err) => null);
            return 'no exception';
        } catch (\InvalidArgumentException $ex) {
            return 'thrown';
        }
    })
    ->assert('throws', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('thrown'))
    ;

$pipelines[] = CTGTest::init('on — unknown integer code throws InvalidArgumentException')
    ->stage('execute', function(CTGTestState $state) {
        $e = new CTGAPIClientError('TIMEOUT', 'timed out');
        try {
            $e->on(99999, fn($err) => null);
            return 'no exception';
        } catch (\InvalidArgumentException $ex) {
            return 'thrown';
        }
    })
    ->assert('throws', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('thrown'))
    ;

// ── HTTP_ERROR type ─────────────────────────────────────────────

$pipelines[] = CTGTest::init('HTTP_ERROR — construct with response data')
    ->stage('create', fn(CTGTestState $state) => new CTGAPIClientError('HTTP_ERROR', 'Status: 404', [
        'status' => 404,
        'ok' => false,
        'body' => ['error' => 'not found'],
    ]))
    ->assert('code is 4000', fn(CTGTestState $state) => $state->getSubject()->getCode(), CTGTestPredicates::equals(4000))
    ->assert('type is HTTP_ERROR', fn(CTGTestState $state) => $state->getSubject()->type, CTGTestPredicates::equals('HTTP_ERROR'))
    ->assert('data has status', fn(CTGTestState $state) => $state->getSubject()->data['status'], CTGTestPredicates::equals(404))
    ->assert('data has body', fn(CTGTestState $state) => $state->getSubject()->data['body']['error'], CTGTestPredicates::equals('not found'))
    ;

$pipelines[] = CTGTest::init('HTTP_ERROR — chainable with transport errors')
    ->stage('execute', function(CTGTestState $state) {
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
    ->assert('matched HTTP_ERROR with status', fn(CTGTestState $state) => $state->getSubject(), CTGTestPredicates::equals('http:401'))
    ;

return $pipelines;
