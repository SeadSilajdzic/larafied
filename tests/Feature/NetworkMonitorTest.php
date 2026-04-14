<?php

declare(strict_types=1);

use Larafied\Http\Middleware\NetworkMonitorMiddleware;
use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    $this->app['config']->set('app.env', 'local');
    $this->tempPath = sys_get_temp_dir().'/la-net-'.uniqid();
    $this->app->singleton(\Larafied\Contracts\StorageDriverContract::class, function () {
        return new \Larafied\Storage\Drivers\SqliteDriver($this->tempPath);
    });
    $this->app->singleton(WorkspaceStorage::class, function ($app) {
        return new WorkspaceStorage($app->make(\Larafied\Contracts\StorageDriverContract::class));
    });
});

afterEach(function () {
    $this->app->forgetInstance(\Larafied\Contracts\StorageDriverContract::class);
    $this->app->forgetInstance(WorkspaceStorage::class);

    $db = $this->tempPath.DIRECTORY_SEPARATOR.'workspace.db';
    if (file_exists($db)) { @unlink($db); }
    if (is_dir($this->tempPath)) { @rmdir($this->tempPath); }
});

// ── GET network/config ────────────────────────────────────────────────────────

it('returns enabled=false when network monitor is disabled in config', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', false);

    $this->getJson('/larafied/api/network/config')
        ->assertOk()
        ->assertJsonPath('enabled', false);
});

it('returns enabled=true when network monitor is enabled in config', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', true);

    $this->getJson('/larafied/api/network/config')
        ->assertOk()
        ->assertJsonPath('enabled', true);
});

// ── GET network/events ────────────────────────────────────────────────────────

it('returns empty events list when nothing captured', function () {
    $this->getJson('/larafied/api/network/events?cursor=0')
        ->assertOk()
        ->assertJsonPath('events', [])
        ->assertJsonPath('cursor', 0)
        ->assertJsonPath('total', 0);
});

it('returns events recorded after the cursor', function () {
    $storage = $this->app->make(WorkspaceStorage::class);

    $storage->recordNetworkEvent([
        'method' => 'GET', 'path' => '/api/users',
        'status' => 200, 'duration_ms' => 12.3, 'ip' => '127.0.0.1',
    ]);
    $storage->recordNetworkEvent([
        'method' => 'POST', 'path' => '/api/orders',
        'status' => 201, 'duration_ms' => 45.0, 'ip' => '127.0.0.1',
    ]);

    $first = $this->getJson('/larafied/api/network/events?cursor=0')
        ->assertOk()
        ->assertJsonCount(2, 'events')
        ->json();

    $cursor = $first['cursor'];

    $storage->recordNetworkEvent([
        'method' => 'DELETE', 'path' => '/api/users/1',
        'status' => 204, 'duration_ms' => 8.0, 'ip' => '127.0.0.1',
    ]);

    $this->getJson("/larafied/api/network/events?cursor={$cursor}")
        ->assertOk()
        ->assertJsonCount(1, 'events')
        ->assertJsonPath('events.0.method', 'DELETE');
});

// ── DELETE network/events ─────────────────────────────────────────────────────

it('clears all network events', function () {
    $storage = $this->app->make(WorkspaceStorage::class);

    $storage->recordNetworkEvent(['method' => 'GET', 'path' => '/ping', 'status' => 200, 'ip' => '127.0.0.1']);
    $storage->recordNetworkEvent(['method' => 'GET', 'path' => '/pong', 'status' => 200, 'ip' => '127.0.0.1']);

    $this->deleteJson('/larafied/api/network/events')->assertNoContent();

    expect($storage->networkEventCount())->toBe(0);
});

// ── NetworkMonitorMiddleware ───────────────────────────────────────────────────

it('middleware is a no-op when disabled', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', false);

    // Route the test route through the middleware
    \Illuminate\Support\Facades\Route::middleware(NetworkMonitorMiddleware::class)
        ->get('/_test/ping', fn () => response()->json(['ok' => true]));

    $this->getJson('/_test/ping')->assertOk();

    $storage = $this->app->make(WorkspaceStorage::class);
    expect($storage->networkEventCount())->toBe(0);
});

it('middleware captures request and response when enabled', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', true);

    \Illuminate\Support\Facades\Route::middleware(NetworkMonitorMiddleware::class)
        ->post('/_test/items', fn () => response()->json(['id' => 1], 201));

    $this->postJson('/_test/items', ['name' => 'Foo'])->assertCreated();

    $storage = $this->app->make(WorkspaceStorage::class);
    expect($storage->networkEventCount())->toBe(1);

    $events = $storage->getNetworkEvents(0, 10);
    $event  = $events->first();

    expect($event['method'])->toBe('POST');
    expect($event['path'])->toBe('/_test/items');
    expect($event['status'])->toBe(201);
    expect($event['duration_ms'])->toBeFloat()->toBeGreaterThan(0);
});

it('middleware skips larafied own routes', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', true);

    // Hit a Larafied API route directly — should not be captured
    $this->getJson('/larafied/api/network/config')->assertOk();

    $storage = $this->app->make(WorkspaceStorage::class);
    expect($storage->networkEventCount())->toBe(0);
});

it('middleware redacts authorization headers', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', true);

    \Illuminate\Support\Facades\Route::middleware(NetworkMonitorMiddleware::class)
        ->get('/_test/secure', fn () => response()->json(['ok' => true]));

    $this->getJson('/_test/secure', ['Authorization' => 'Bearer secret-token'])->assertOk();

    $storage = $this->app->make(WorkspaceStorage::class);
    $event   = $storage->getNetworkEvents(0, 1)->first();

    expect($event['req_headers'])->not()->toHaveKey('authorization');
    expect($event['req_headers'])->not()->toHaveKey('Authorization');
});

it('middleware truncates large request bodies', function () {
    $this->app['config']->set('larafied.network_monitor.enabled', true);
    $this->app['config']->set('larafied.network_monitor.max_body_size', 100);

    \Illuminate\Support\Facades\Route::middleware(NetworkMonitorMiddleware::class)
        ->post('/_test/upload', fn () => response()->json(['ok' => true]));

    $largeBody = str_repeat('x', 500);
    $this->call('POST', '/_test/upload', [], [], [], [], $largeBody);

    $storage = $this->app->make(WorkspaceStorage::class);
    $event   = $storage->getNetworkEvents(0, 1)->first();

    expect(strlen($event['req_body']))->toBeLessThan(200);
    expect($event['req_body'])->toContain('…[truncated]');
});
