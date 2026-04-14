<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Larafied\Contracts\StorageDriverContract;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;
use Larafied\Services\SyncService;
use Larafied\Storage\WorkspaceStorage;

// ── Helpers ────────────────────────────────────────────────────────────────

function syncStoragePath(): string
{
    $path = sys_get_temp_dir().'/la-sync-test-'.uniqid();
    mkdir($path, 0755, true);
    return $path;
}

function bindSyncDeps(
    mixed $app,
    string $storagePath,
    MockHandler $cloudMock,
    string $tier = 'team',
): void {
    // LicenseValidator with a cached license
    $licenseValidator = new LicenseValidator(
        httpClient:      new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        storagePath:     $storagePath,
        licenseKey:      'SYNC-TEST-KEY',
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );

    // Use the validator's own cache() method so the HMAC is correct
    $licenseValidator->cache([
        'key'          => 'SYNC-TEST-KEY',
        'tier'         => $tier,
        'features'     => [],
        'expires_at'   => now()->addYear()->toIso8601String(),
        'validated_at' => now()->toIso8601String(),
        'grace_until'  => null,
    ]);

    $app->instance(LicenseValidator::class, $licenseValidator);
    $app->instance(FeatureFlags::class, new FeatureFlags($licenseValidator));

    // SyncService backed by the mock HTTP client
    $app->instance(SyncService::class, new SyncService(
        httpClient:       new Client(['handler' => HandlerStack::create($cloudMock)]),
        storage:          $app->make(WorkspaceStorage::class),
        licenseValidator: $licenseValidator,
        cloudUrl:         'https://api.larafied.com',
    ));
}

function bindFreeTierDeps(mixed $app, string $storagePath): void
{
    $licenseValidator = new LicenseValidator(
        httpClient:      new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        storagePath:     $storagePath,
        licenseKey:      '',
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );

    // No license cache → free tier
    $app->instance(LicenseValidator::class, $licenseValidator);
    $app->instance(FeatureFlags::class, new FeatureFlags($licenseValidator));
}

// ── Setup / Teardown ──────────────────────────────────────────────────────

beforeEach(function () {
    $this->storagePath = syncStoragePath();

    // Isolated SQLite workspace for each test
    $this->app->singleton(StorageDriverContract::class, function () {
        return new \Larafied\Storage\Drivers\SqliteDriver($this->storagePath);
    });
    $this->app->singleton(WorkspaceStorage::class, function ($app) {
        return new WorkspaceStorage($app->make(StorageDriverContract::class));
    });
});

afterEach(function () {
    $this->app->forgetInstance(LicenseValidator::class);
    $this->app->forgetInstance(FeatureFlags::class);
    $this->app->forgetInstance(SyncService::class);
    $this->app->forgetInstance(StorageDriverContract::class);
    $this->app->forgetInstance(WorkspaceStorage::class);

    // Clean up temp files
    foreach (glob($this->storagePath.'/*') as $file) {
        @unlink($file);
    }
    @rmdir($this->storagePath);
});

// ── GET sync/status ───────────────────────────────────────────────────────

it('returns enabled=false for free tier', function () {
    bindFreeTierDeps($this->app, $this->storagePath);

    $this->getJson('/larafied/api/sync/status')
        ->assertOk()
        ->assertJsonPath('enabled', false);
});

it('returns enabled=true for team tier', function () {
    bindSyncDeps($this->app, $this->storagePath, new MockHandler([]));

    $this->getJson('/larafied/api/sync/status')
        ->assertOk()
        ->assertJsonPath('enabled', true);
});

// ── POST sync/push ────────────────────────────────────────────────────────

it('push returns 403 for free tier', function () {
    bindFreeTierDeps($this->app, $this->storagePath);

    $this->postJson('/larafied/api/sync/push', [])
        ->assertForbidden()
        ->assertJsonPath('error', 'Cloud sync requires Team or Agency plan.');
});

it('push sends workspace to cloud and returns ok', function () {
    $mock = new MockHandler([
        new GuzzleResponse(200, [], json_encode(['synced' => true, 'checksum' => 'abc123'])),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock);

    $this->postJson('/larafied/api/sync/push', [])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('checksum', 'abc123');
});

it('push returns 502 when cloud is unreachable', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new GuzzleRequest('POST', '/')),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock);

    $this->postJson('/larafied/api/sync/push', [])
        ->assertStatus(502)
        ->assertJsonStructure(['error']);
});

it('push returns 502 when cloud returns error', function () {
    $mock = new MockHandler([
        new GuzzleResponse(400, [], json_encode(['error' => 'Invalid payload'])),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock);

    $this->postJson('/larafied/api/sync/push', [])
        ->assertStatus(502)
        ->assertJsonStructure(['error']);
});

// ── POST sync/pull ────────────────────────────────────────────────────────

it('pull returns 403 for free tier', function () {
    bindFreeTierDeps($this->app, $this->storagePath);

    $this->postJson('/larafied/api/sync/pull', [])
        ->assertForbidden()
        ->assertJsonPath('error', 'Cloud sync requires Team or Agency plan.');
});

it('pull returns error when cloud has no snapshot', function () {
    $mock = new MockHandler([
        new GuzzleResponse(200, [], json_encode(['snapshot' => null])),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock);

    $this->postJson('/larafied/api/sync/pull', [])
        ->assertStatus(502)
        ->assertJsonPath('error', 'No snapshot found in the cloud.');
});

it('pull imports collections additively', function () {
    $storage = $this->app->make(WorkspaceStorage::class);

    // Pre-existing collection
    $storage->saveCollection(['name' => 'Existing', 'description' => null]);

    $snapshot = [
        'version'      => 1,
        'collections'  => [
            ['name' => 'Existing',  'description' => null, 'requests' => []],
            ['name' => 'New Remote', 'description' => null, 'requests' => [
                ['name' => 'GET Users', 'sort_order' => 0, 'data' => ['method' => 'GET', 'url' => '/users']],
            ]],
        ],
        'environments' => [],
    ];

    $mock = new MockHandler([
        new GuzzleResponse(200, [], json_encode([
            'snapshot' => ['payload' => $snapshot, 'updated_at' => '2026-04-07T00:00:00Z'],
        ])),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock);

    $this->postJson('/larafied/api/sync/pull', [])
        ->assertOk()
        ->assertJsonPath('ok', true);

    // Only one new collection added; existing not duplicated
    expect($storage->collections()->count())->toBe(2);

    $newCol = $storage->collections()->firstWhere('name', 'New Remote');
    expect($newCol)->not()->toBeNull();
    expect($storage->requestsForCollection($newCol['id'])->count())->toBe(1);
});

it('pull returns 502 when cloud is unreachable', function () {
    $mock = new MockHandler([
        new ConnectException('Connection refused', new GuzzleRequest('GET', '/')),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock);

    $this->postJson('/larafied/api/sync/pull', [])
        ->assertStatus(502)
        ->assertJsonStructure(['error']);
});

it('agency tier also has cloud sync enabled', function () {
    $mock = new MockHandler([
        new GuzzleResponse(200, [], json_encode(['synced' => true, 'checksum' => 'xyz'])),
    ]);

    bindSyncDeps($this->app, $this->storagePath, $mock, tier: 'agency');

    $this->getJson('/larafied/api/sync/status')
        ->assertOk()
        ->assertJsonPath('enabled', true);

    $this->postJson('/larafied/api/sync/push', [])
        ->assertOk()
        ->assertJsonPath('ok', true);
});
