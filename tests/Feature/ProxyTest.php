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
