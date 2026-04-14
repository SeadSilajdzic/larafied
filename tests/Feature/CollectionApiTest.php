<?php

declare(strict_types=1);

use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    $this->app['config']->set('app.env', 'local');
    $this->tempPath = sys_get_temp_dir().'/aw-test-'.uniqid();
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
    if (file_exists($db)) {
        @unlink($db);
    }
    if (is_dir($this->tempPath)) {
        @rmdir($this->tempPath);
    }
});

it('returns empty collections list', function () {
    $this->getJson('/larafied/api/collections')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.count', 0);
});

it('creates a collection', function () {
    $this->postJson('/larafied/api/collections', ['name' => 'Auth API'])
        ->assertCreated()
        ->assertJsonPath('name', 'Auth API');
});

it('validates required name on create', function () {
    $this->postJson('/larafied/api/collections', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('updates a collection', function () {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'Original']);

    $this->putJson("/larafied/api/collections/{$collection['id']}", ['name' => 'Updated'])
        ->assertOk()
        ->assertJsonPath('name', 'Updated');
});

it('returns 404 when updating non-existent collection', function () {
    $this->putJson('/larafied/api/collections/nonexistent', ['name' => 'X'])
        ->assertNotFound();
});

it('deletes a collection', function () {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'To Delete']);

    $this->deleteJson("/larafied/api/collections/{$collection['id']}")
        ->assertNoContent();

    expect($storage->findCollection($collection['id']))->toBeNull();
});

it('enforces free tier limit of 5 collections', function () {
    $storage = $this->app->make(WorkspaceStorage::class);

    foreach (range(1, 5) as $i) {
        $storage->saveCollection(['name' => "Collection {$i}"]);
    }

    $this->postJson('/larafied/api/collections', ['name' => 'Collection 6'])
        ->assertUnprocessable()
        ->assertJsonPath('upgrade', true);
});

it('allows pro tier to exceed free limit', function () {
    $storage = $this->app->make(\Larafied\Storage\WorkspaceStorage::class);

    // Set up a pro-tier license cache
    $storagePath = sys_get_temp_dir().'/pro-test-'.uniqid();
    mkdir($storagePath, 0755, true);

    $validator = new \Larafied\Services\LicenseValidator(
        httpClient:      new \GuzzleHttp\Client(),
        storagePath:     $storagePath,
        licenseKey:      'AW-TEST-KEY',
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections'],
        'expires_at'   => null,
        'validated_at' => (new \DateTime())->format(\DateTime::ATOM),
        'grace_until'  => null,
    ]);

    $this->app->instance(\Larafied\Services\LicenseValidator::class, $validator);
    $this->app->instance(\Larafied\Services\FeatureFlags::class, new \Larafied\Services\FeatureFlags($validator));

    foreach (range(1, 5) as $i) {
        $storage->saveCollection(['name' => "Collection {$i}"]);
    }

    $this->postJson('/larafied/api/collections', ['name' => 'Collection 6'])
        ->assertCreated();

    @unlink($storagePath.DIRECTORY_SEPARATOR.'license.json');
    @rmdir($storagePath);
});

it('bulk deletes multiple collections', function () {
    $storage = $this->app->make(WorkspaceStorage::class);
    $a = $storage->saveCollection(['name' => 'A']);
    $b = $storage->saveCollection(['name' => 'B']);
    $c = $storage->saveCollection(['name' => 'C']);

    $this->deleteJson('/larafied/api/collections', ['ids' => [$a['id'], $b['id']]])
        ->assertNoContent();

    expect($storage->findCollection($a['id']))->toBeNull();
    expect($storage->findCollection($b['id']))->toBeNull();
    expect($storage->findCollection($c['id']))->not()->toBeNull();
});

it('bulk delete validates ids array', function () {
    $this->deleteJson('/larafied/api/collections', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

it('bulk delete silently skips non-existent ids', function () {
    $storage = $this->app->make(WorkspaceStorage::class);
    $a = $storage->saveCollection(['name' => 'A']);

    $this->deleteJson('/larafied/api/collections', ['ids' => [$a['id'], 'nonexistent']])
        ->assertNoContent();

    expect($storage->findCollection($a['id']))->toBeNull();
});

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/collections')
        ->assertForbidden();
});
