<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;
use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    $this->storage    = app(WorkspaceStorage::class);
    $this->tempPaths  = [];
});

afterEach(function () {
    $this->storage->clearHistory();

    foreach ($this->tempPaths as $path) {
        @unlink($path.DIRECTORY_SEPARATOR.'license.json');
        @rmdir($path);
    }
});

it('returns empty history for pro user', function () {
    bindProFeatureFlags($this, ['request_history']);

    $this->getJson('/larafied/api/history')
        ->assertOk()
        ->assertJson(['data' => []]);
});

it('returns history entries for pro user', function () {
    bindProFeatureFlags($this, ['request_history']);

    $this->storage->saveToHistory([
        'method'      => 'GET',
        'url'         => 'https://example.com/api/users',
        'status'      => 200,
        'duration_ms' => 50.0,
    ]);

    $this->getJson('/larafied/api/history')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.method', 'GET')
        ->assertJsonPath('data.0.status', 200);
});

it('clears history', function () {
    bindProFeatureFlags($this, ['request_history']);

    $this->storage->saveToHistory(['method' => 'GET', 'url' => 'https://example.com', 'status' => 200]);

    $this->deleteJson('/larafied/api/history')->assertNoContent();

    $this->getJson('/larafied/api/history')
        ->assertOk()
        ->assertJson(['data' => []]);
});

it('returns 403 with upgrade flag for free tier', function () {
    $this->getJson('/larafied/api/history')
        ->assertForbidden()
        ->assertJsonPath('upgrade', true);
});

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/history')->assertForbidden();
});

// ─── helpers ─────────────────────────────────────────────────────────────────

function bindProFeatureFlags(object $test, array $features): void
{
    $path = sys_get_temp_dir().'/larafied-hist-'.uniqid();
    mkdir($path, 0755, true);
    $test->tempPaths[] = $path;

    $validator = new LicenseValidator(
        httpClient:      new Client(),
        storagePath:     $path,
        licenseKey:      'AW-TEST-KEY',
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => $features,
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    app()->instance(LicenseValidator::class, $validator);
    app()->instance(FeatureFlags::class, new FeatureFlags($validator));
}
