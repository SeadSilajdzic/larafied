<?php

declare(strict_types=1);

use Larafied\Services\RequestProxy;
use Larafied\Data\ProxyResponse;
use Larafied\Services\SsrfGuard;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function mockProxy(array $responses): void
{
    $mock    = new MockHandler($responses);
    $handler = HandlerStack::create($mock);
    $client  = new Client(['handler' => $handler]);

    app()->singleton(RequestProxy::class, function () use ($client) {
        return new RequestProxy(new SsrfGuard(), $client);
    });
}

it('forwards a get request and returns response', function () {
    mockProxy([new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')]);

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/users',
    ])
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('body', '{"ok":true}');
});

it('returns 422 for private ip ssrf attempt', function () {
    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'http://192.168.1.1/internal',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'ssrf');
});

it('returns 422 for file scheme', function () {
    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'file:///etc/passwd',
    ])
        ->assertUnprocessable();
});

it('validates method field', function () {
    $this->postJson('/larafied/api/proxy', [
        'method' => 'INVALID',
        'url'    => 'https://example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['method']);
});

it('handles non-2xx responses without throwing', function () {
    mockProxy([new Response(404, [], '{"message":"Not found"}')]);

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/missing',
    ])
        ->assertOk()
        ->assertJsonPath('status', 404);
});

// ─── Auth helpers ─────────────────────────────────────────────────────────────

it('injects Bearer token as Authorization header', function () {
    mockProxy([new Response(200, [], 'ok')]);

    $container = [];
    $history   = \GuzzleHttp\Middleware::history($container);
    $mock      = new MockHandler([new Response(200, [], 'ok')]);
    $handler   = HandlerStack::create($mock);
    $handler->push($history);
    $client = new Client(['handler' => $handler]);

    app()->singleton(RequestProxy::class, function () use ($client) {
        return new RequestProxy(new SsrfGuard(), $client);
    });

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/users',
        'auth'   => ['type' => 'bearer', 'token' => 'secret-token'],
    ])->assertOk();

    expect($container[0]['request']->getHeaderLine('Authorization'))
        ->toBe('Bearer secret-token');
});

it('injects Basic auth as Authorization header', function () {
    $container = [];
    $history   = \GuzzleHttp\Middleware::history($container);
    $mock      = new MockHandler([new Response(200, [], 'ok')]);
    $handler   = HandlerStack::create($mock);
    $handler->push($history);
    $client = new Client(['handler' => $handler]);

    app()->singleton(RequestProxy::class, function () use ($client) {
        return new RequestProxy(new SsrfGuard(), $client);
    });

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/users',
        'auth'   => ['type' => 'basic', 'username' => 'user', 'password' => 'pass'],
    ])->assertOk();

    expect($container[0]['request']->getHeaderLine('Authorization'))
        ->toBe('Basic '.base64_encode('user:pass'));
});

it('injects API key into a custom header', function () {
    $container = [];
    $history   = \GuzzleHttp\Middleware::history($container);
    $mock      = new MockHandler([new Response(200, [], 'ok')]);
    $handler   = HandlerStack::create($mock);
    $handler->push($history);
    $client = new Client(['handler' => $handler]);

    app()->singleton(RequestProxy::class, function () use ($client) {
        return new RequestProxy(new SsrfGuard(), $client);
    });

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/users',
        'auth'   => ['type' => 'apikey', 'key' => 'X-Api-Key', 'value' => 'my-key', 'in' => 'header'],
    ])->assertOk();

    expect($container[0]['request']->getHeaderLine('X-Api-Key'))
        ->toBe('my-key');
});

it('appends API key as a query parameter', function () {
    $container = [];
    $history   = \GuzzleHttp\Middleware::history($container);
    $mock      = new MockHandler([new Response(200, [], 'ok')]);
    $handler   = HandlerStack::create($mock);
    $handler->push($history);
    $client = new Client(['handler' => $handler]);

    app()->singleton(RequestProxy::class, function () use ($client) {
        return new RequestProxy(new SsrfGuard(), $client);
    });

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/users',
        'auth'   => ['type' => 'apikey', 'key' => 'api_key', 'value' => 'my-key', 'in' => 'query'],
    ])->assertOk();

    parse_str($container[0]['request']->getUri()->getQuery(), $query);
    expect($query['api_key'])->toBe('my-key');
});

it('does not inject auth when type is none', function () {
    $container = [];
    $history   = \GuzzleHttp\Middleware::history($container);
    $mock      = new MockHandler([new Response(200, [], 'ok')]);
    $handler   = HandlerStack::create($mock);
    $handler->push($history);
    $client = new Client(['handler' => $handler]);

    app()->singleton(RequestProxy::class, function () use ($client) {
        return new RequestProxy(new SsrfGuard(), $client);
    });

    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com/api/users',
        'auth'   => ['type' => 'none'],
    ])->assertOk();

    expect($container[0]['request']->hasHeader('Authorization'))->toBeFalse();
});

it('rejects invalid auth type', function () {
    $this->postJson('/larafied/api/proxy', [
        'method' => 'GET',
        'url'    => 'https://example.com',
        'auth'   => ['type' => 'oauth2'],
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['auth.type']);
});
