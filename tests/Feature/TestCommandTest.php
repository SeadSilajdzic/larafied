<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Larafied\Contracts\StorageDriverContract;
use Larafied\Services\AssertionRunner;
use Larafied\Services\RequestProxy;
use Larafied\Services\SsrfGuard;
use Larafied\Storage\Drivers\SqliteDriver;
use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    // Isolated temp SQLite DB — same pattern as CollectionApiTest
    $this->tempPath = sys_get_temp_dir().'/larafied-cmd-test-'.uniqid();
    $this->app->singleton(StorageDriverContract::class, function () {
        return new SqliteDriver($this->tempPath);
    });
    $this->app->singleton(WorkspaceStorage::class, function ($app) {
        return new WorkspaceStorage($app->make(StorageDriverContract::class));
    });

    // Seed a collection with one saved request + assertions
    $storage = app(WorkspaceStorage::class);

    $col = $storage->saveCollection(['name' => 'My API', 'description' => null]);

    $storage->saveRequest([
        'collection_id' => $col['id'],
        'folder_id'     => null,
        'name'          => 'Get users',
        'sort_order'    => 0,
        'data'          => [
            'method'     => 'GET',
            'url'        => 'https://example.com/api/users',
            'headers'    => [],
            'assertions' => [
                ['type' => 'status_equals', 'value' => '200'],
            ],
        ],
    ]);
});

afterEach(function () {
    $this->app->forgetInstance(StorageDriverContract::class);
    $this->app->forgetInstance(WorkspaceStorage::class);

    $db = $this->tempPath.DIRECTORY_SEPARATOR.'workspace.db';
    if (file_exists($db)) {
        @unlink($db);
    }
    if (is_dir($this->tempPath)) {
        @rmdir($this->tempPath);
    }
});

function mockProxyForCommand(array $responses): void
{
    $mock    = new MockHandler($responses);
    $handler = HandlerStack::create($mock);
    $client  = new Client(['handler' => $handler]);

    $proxy = new RequestProxy(new SsrfGuard(allowPrivateHosts: true), $client);

    app()->instance(RequestProxy::class, $proxy);
}


it('runs all requests in a collection and reports pass', function () {
    mockProxyForCommand([new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}')]);

    $this->artisan('larafied:test', ['--collection' => 'My API'])
        ->assertSuccessful();
});

it('exits with code 1 when an assertion fails', function () {
    mockProxyForCommand([new Response(404, [], 'not found')]);

    $this->artisan('larafied:test', ['--collection' => 'My API'])
        ->assertFailed();
});

it('outputs table with request name and pass/fail', function () {
    mockProxyForCommand([new Response(200, [], '{}')]);

    $this->artisan('larafied:test', ['--collection' => 'My API'])
        ->expectsOutputToContain('Get users')
        ->assertSuccessful();
});

it('exits with code 2 when collection is not found', function () {
    $this->artisan('larafied:test', ['--collection' => 'Nonexistent'])
        ->expectsOutputToContain('Nonexistent')
        ->assertExitCode(2);
});

it('produces junit xml output when --format=junit', function () {
    mockProxyForCommand([new Response(200, [], '{}')]);

    $exitCode = \Illuminate\Support\Facades\Artisan::call('larafied:test', [
        '--collection' => 'My API',
        '--format'     => 'junit',
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('<?xml')
        ->and($output)->toContain('testsuite');
});

it('runs all collections when no --collection given', function () {
    $storage = app(WorkspaceStorage::class);
    $col2    = $storage->saveCollection(['name' => 'Second', 'description' => null]);
    $storage->saveRequest([
        'collection_id' => $col2['id'],
        'folder_id'     => null,
        'name'          => 'Other request',
        'sort_order'    => 0,
        'data'          => [
            'method'     => 'GET',
            'url'        => 'https://example.com/other',
            'assertions' => [['type' => 'status_equals', 'value' => '200']],
        ],
    ]);

    mockProxyForCommand([
        new Response(200, [], '{}'),
        new Response(200, [], '{}'),
    ]);

    $this->artisan('larafied:test')
        ->expectsOutputToContain('My API')
        ->expectsOutputToContain('Second')
        ->assertSuccessful();
});

