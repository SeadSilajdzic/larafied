<?php

declare(strict_types=1);

use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    $this->tempPath = sys_get_temp_dir().'/aw-test-req-'.uniqid();
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

$validPayload = [
    'name' => 'Get Users',
    'data' => [
        'method'  => 'GET',
        'url'     => 'https://api.example.com/users',
        'headers' => [],
        'body'    => null,
        'query'   => [],
    ],
];

it('returns empty requests list for a collection', function () {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);

    $this->getJson("/larafied/api/collections/{$collection['id']}/requests")
        ->assertOk()
        ->assertJson([]);
});

it('returns 404 for non-existent collection on index', function () {
    $this->getJson('/larafied/api/collections/nonexistent/requests')
        ->assertNotFound();
});

it('creates a saved request', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);

    $this->postJson("/larafied/api/collections/{$collection['id']}/requests", $validPayload)
        ->assertCreated()
        ->assertJsonPath('name', 'Get Users');
});

it('returns 404 when creating request for non-existent collection', function () use (&$validPayload) {
    $this->postJson('/larafied/api/collections/nonexistent/requests', $validPayload)
        ->assertNotFound();
});

it('validates required fields on store', function () {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);

    $this->postJson("/larafied/api/collections/{$collection['id']}/requests", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'data']);
});

it('updates a saved request', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);
    $saved      = $storage->saveRequest([...$validPayload, 'collection_id' => $collection['id']]);

    $updated = array_merge($validPayload, ['name' => 'Get All Users']);

    $this->putJson("/larafied/api/collections/{$collection['id']}/requests/{$saved['id']}", $updated)
        ->assertOk()
        ->assertJsonPath('name', 'Get All Users');
});

it('returns 404 when updating non-existent request', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);

    $this->putJson("/larafied/api/collections/{$collection['id']}/requests/nonexistent", $validPayload)
        ->assertNotFound();
});

it('deletes a saved request', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);
    $saved      = $storage->saveRequest([...$validPayload, 'collection_id' => $collection['id']]);

    $this->deleteJson("/larafied/api/collections/{$collection['id']}/requests/{$saved['id']}")
        ->assertNoContent();

    expect($storage->findRequest($saved['id']))->toBeNull();
});

it('returns 404 when deleting non-existent request', function () {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);

    $this->deleteJson("/larafied/api/collections/{$collection['id']}/requests/nonexistent")
        ->assertNotFound();
});

it('deletes a saved request via standalone route', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);
    $saved      = $storage->saveRequest([...$validPayload, 'collection_id' => $collection['id']]);

    $this->deleteJson("/larafied/api/requests/{$saved['id']}")
        ->assertNoContent();

    expect($storage->findRequest($saved['id']))->toBeNull();
});

it('returns 404 on standalone delete for non-existent request', function () {
    $this->deleteJson('/larafied/api/requests/nonexistent')
        ->assertNotFound();
});

it('duplicates a saved request', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);
    $saved      = $storage->saveRequest([...$validPayload, 'collection_id' => $collection['id']]);

    $this->postJson("/larafied/api/requests/{$saved['id']}/duplicate")
        ->assertCreated()
        ->assertJsonPath('name', 'Copy of Get Users')
        ->assertJsonPath('collection_id', $collection['id']);

    expect($storage->requestsForCollection($collection['id']))->toHaveCount(2);
});

it('returns 404 when duplicating non-existent request', function () {
    $this->postJson('/larafied/api/requests/nonexistent/duplicate')
        ->assertNotFound();
});

it('duplicate preserves folder assignment', function () use (&$validPayload) {
    $storage    = $this->app->make(WorkspaceStorage::class);
    $collection = $storage->saveCollection(['name' => 'My API']);
    $folder     = $storage->saveFolder(['collection_id' => $collection['id'], 'name' => 'Auth']);
    $saved      = $storage->saveRequest([
        ...$validPayload,
        'collection_id' => $collection['id'],
        'folder_id'     => $folder['id'],
    ]);

    $response = $this->postJson("/larafied/api/requests/{$saved['id']}/duplicate")
        ->assertCreated();

    expect($response->json('folder_id'))->toBe($folder['id']);
});

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/collections/any/requests')
        ->assertForbidden();
});
