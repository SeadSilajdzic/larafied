<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;
use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    // Isolated SQLite storage
    $this->tempPath = sys_get_temp_dir().'/aw-test-env-'.uniqid();
    $this->app->singleton(\Larafied\Contracts\StorageDriverContract::class, function () {
        return new \Larafied\Storage\Drivers\SqliteDriver($this->tempPath);
    });
    $this->app->singleton(WorkspaceStorage::class, function ($app) {
        return new WorkspaceStorage($app->make(\Larafied\Contracts\StorageDriverContract::class));
    });

    // Pro license for tests that need it
    $this->licensePath = sys_get_temp_dir().'/aw-test-lic-'.uniqid();
    mkdir($this->licensePath, 0755, true);
    $validator = new LicenseValidator(
        httpClient:      new Client(),
        storagePath:     $this->licensePath,
        licenseKey:      'AW-TEST-KEY',
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections', 'environments', 'auth_helpers', 'import_export'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);
    app()->instance(LicenseValidator::class, $validator);
    app()->instance(FeatureFlags::class, new FeatureFlags($validator));
});

afterEach(function () {
    $this->app->forgetInstance(\Larafied\Contracts\StorageDriverContract::class);
    $this->app->forgetInstance(WorkspaceStorage::class);

    $db = $this->tempPath.DIRECTORY_SEPARATOR.'workspace.db';
    if (file_exists($db)) {
        @unlink($db);
    }
    if (is_dir($this->tempPath)) {
        @rmdir($this->tempPath);
    }
    @unlink($this->licensePath.DIRECTORY_SEPARATOR.'license.json');
    @rmdir($this->licensePath);
});

it('returns empty environments list for pro user', function () {
    $this->getJson('/larafied/api/environments')
        ->assertOk()
        ->assertJson([]);
});

it('returns 403 with upgrade flag for free tier', function () {
    app()->instance(FeatureFlags::class, new FeatureFlags(
        new LicenseValidator(
            httpClient:      new Client(),
            storagePath:     sys_get_temp_dir().'/ff-free-'.uniqid(),
            licenseKey:      '',
            cloudUrl:        'https://api.larafied.com',
            gracePeriodDays: 7,
        )
    ));

    $this->getJson('/larafied/api/environments')
        ->assertForbidden()
        ->assertJsonPath('upgrade', true);
});

it('creates an environment', function () {
    $this->postJson('/larafied/api/environments', ['name' => 'Staging'])
        ->assertCreated()
        ->assertJsonPath('name', 'Staging');
});

it('validates required name on create', function () {
    $this->postJson('/larafied/api/environments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates an environment', function () {
    $storage = $this->app->make(WorkspaceStorage::class);
    $env     = $storage->saveEnvironment(['name' => 'Local']);

    $this->putJson("/larafied/api/environments/{$env['id']}", ['name' => 'Production'])
        ->assertOk()
        ->assertJsonPath('name', 'Production');
});

it('returns 404 when updating non-existent environment', function () {
    $this->putJson('/larafied/api/environments/nonexistent', ['name' => 'X'])
        ->assertNotFound();
});

it('deletes an environment', function () {
    $storage = $this->app->make(WorkspaceStorage::class);
    $env     = $storage->saveEnvironment(['name' => 'To Delete']);

    $this->deleteJson("/larafied/api/environments/{$env['id']}")
        ->assertNoContent();

    expect($storage->findEnvironment($env['id']))->toBeNull();
});

it('returns 404 when deleting non-existent environment', function () {
    $this->deleteJson('/larafied/api/environments/nonexistent')
        ->assertNotFound();
});

it('activates an environment', function () {
    $storage = $this->app->make(WorkspaceStorage::class);
    $envA    = $storage->saveEnvironment(['name' => 'Env A']);
    $envB    = $storage->saveEnvironment(['name' => 'Env B']);

    $this->postJson("/larafied/api/environments/{$envA['id']}/activate")
        ->assertOk()
        ->assertJsonPath('is_active', true);

    $this->postJson("/larafied/api/environments/{$envB['id']}/activate")
        ->assertOk()
        ->assertJsonPath('is_active', true);

    expect($storage->findEnvironment($envA['id'])['is_active'])->toBeFalsy();
});

it('returns 404 when activating non-existent environment', function () {
    $this->postJson('/larafied/api/environments/nonexistent/activate')
        ->assertNotFound();
});

it('returns 403 on update for free tier', function () {
    app()->instance(FeatureFlags::class, new FeatureFlags(
        new LicenseValidator(
            httpClient:      new Client(),
            storagePath:     sys_get_temp_dir().'/ff-free-upd-'.uniqid(),
            licenseKey:      '',
            cloudUrl:        'https://api.larafied.com',
            gracePeriodDays: 7,
        )
    ));

    $this->putJson('/larafied/api/environments/some-id', ['name' => 'X'])
        ->assertForbidden()
        ->assertJsonPath('upgrade', true);
});

it('returns 403 on destroy for free tier', function () {
    app()->instance(FeatureFlags::class, new FeatureFlags(
        new LicenseValidator(
            httpClient:      new Client(),
            storagePath:     sys_get_temp_dir().'/ff-free-del-'.uniqid(),
            licenseKey:      '',
            cloudUrl:        'https://api.larafied.com',
            gracePeriodDays: 7,
        )
    ));

    $this->deleteJson('/larafied/api/environments/some-id')
        ->assertForbidden()
        ->assertJsonPath('upgrade', true);
});

it('returns 403 on activate for free tier', function () {
    app()->instance(FeatureFlags::class, new FeatureFlags(
        new LicenseValidator(
            httpClient:      new Client(),
            storagePath:     sys_get_temp_dir().'/ff-free-act-'.uniqid(),
            licenseKey:      '',
            cloudUrl:        'https://api.larafied.com',
            gracePeriodDays: 7,
        )
    ));

    $this->postJson('/larafied/api/environments/some-id/activate')
        ->assertForbidden()
        ->assertJsonPath('upgrade', true);
});

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/environments')
        ->assertForbidden();
});
