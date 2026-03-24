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

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/collections')
        ->assertForbidden();
});
