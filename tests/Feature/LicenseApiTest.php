<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;

beforeEach(function () {
    $this->storagePath = sys_get_temp_dir().'/la-test-'.uniqid();
    mkdir($this->storagePath, 0755, true);
});

afterEach(function () {
    $this->app->forgetInstance(LicenseValidator::class);
    $this->app->forgetInstance(FeatureFlags::class);

    $cache = $this->storagePath.DIRECTORY_SEPARATOR.'license.json';
    if (file_exists($cache)) {
        @unlink($cache);
    }
    if (is_dir($this->storagePath)) {
        @rmdir($this->storagePath);
    }
});

function bindValidator(mixed $app, string $storagePath, MockHandler $mock): LicenseValidator
{
    $validator = new LicenseValidator(
        httpClient:      new Client(['handler' => HandlerStack::create($mock)]),
        storagePath:     $storagePath,
        licenseKey:      'AW-TEST-KEY',
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );

    $app->instance(LicenseValidator::class, $validator);
    $app->instance(FeatureFlags::class, new FeatureFlags($validator));

    return $validator;
}

// --- GET /larafied/api/license ---

it('returns free tier when no license is cached', function () {
    bindValidator($this->app, $this->storagePath, new MockHandler([]));

    $this->getJson('/larafied/api/license')
        ->assertOk()
        ->assertJsonPath('tier', 'free')
        ->assertJsonPath('features', []);
});

it('returns cached tier and full tier features', function () {
    $validator = bindValidator($this->app, $this->storagePath, new MockHandler([]));
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections', 'environments'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    $response = $this->getJson('/larafied/api/license')
        ->assertOk()
        ->assertJsonPath('tier', 'pro');

    // Should return all pro tier features, not just the outdated cloud cache subset
    $features = $response->json('features');
    expect($features)->toContain('graphql')
        ->and($features)->toContain('sql_console')
        ->and($features)->toContain('query_log')
        ->and($features)->toContain('request_history');
});

it('returns all pro features for team tier even when cloud cache is outdated', function () {
    // Regression: cloud cache written before graphql/sql_console/query_log/request_history
    // were added. Team tier should still expose all pro features via TIER_FEATURES map.
    $validator = bindValidator($this->app, $this->storagePath, new MockHandler([]));
    $validator->cache([
        'key'          => 'AW-TEAM-KEY',
        'tier'         => 'team',
        'features'     => ['unlimited_collections', 'environments', 'auth_helpers', 'import_export',
                           'cloud_sync', 'shared_collections'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    $response = $this->getJson('/larafied/api/license')
        ->assertOk()
        ->assertJsonPath('tier', 'team');

    $features = $response->json('features');
    expect($features)
        ->toContain('graphql')
        ->toContain('sql_console')
        ->toContain('query_log')
        ->toContain('request_history')
        ->toContain('cloud_sync')
        ->toContain('shared_collections');
});

// --- POST /larafied/api/license/activate ---

it('activates a valid license key', function () {
    bindValidator($this->app, $this->storagePath, new MockHandler([
        new Response(200, [], json_encode([
            'valid'             => true,
            'tier'              => 'pro',
            'features'          => ['unlimited_collections'],
            'expires_at'        => null,
            'cache_for_seconds' => 86400,
        ])),
    ]));

    $this->postJson('/larafied/api/license/activate', [
        'key'    => 'AW-TEST-KEY',
        'domain' => 'myapp.local',
    ])
        ->assertOk()
        ->assertJsonPath('tier', 'pro');
});

it('returns 422 when key is missing', function () {
    bindValidator($this->app, $this->storagePath, new MockHandler([]));

    $this->postJson('/larafied/api/license/activate', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);
});

it('returns 422 with reason when cloud rejects the key', function () {
    bindValidator($this->app, $this->storagePath, new MockHandler([
        new Response(200, [], json_encode([
            'valid'  => false,
            'reason' => 'license_revoked',
        ])),
    ]));

    $this->postJson('/larafied/api/license/activate', [
        'key'    => 'AW-BAD-KEY',
        'domain' => 'myapp.local',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'license_revoked');
});

it('includes grace_warning true when fewer than 2 days remain', function () {
    $validator = bindValidator($this->app, $this->storagePath, new MockHandler([]));
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('+1 day'))->format(DateTime::ATOM),
    ]);

    $this->getJson('/larafied/api/license')
        ->assertOk()
        ->assertJsonPath('grace_warning', true);
});

it('includes grace_warning false when no grace is active', function () {
    $validator = bindValidator($this->app, $this->storagePath, new MockHandler([]));
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    $this->getJson('/larafied/api/license')
        ->assertOk()
        ->assertJsonPath('grace_warning', false);
});

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/license')
        ->assertForbidden();
});
