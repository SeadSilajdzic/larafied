<?php

declare(strict_types=1);

use Larafied\Storage\WorkspaceStorage;

beforeEach(function () {
    $this->tempPath = sys_get_temp_dir().'/aw-test-env-'.uniqid();
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

it('returns empty environments list', function () {
    $this->getJson('/larafied/api/environments')
        ->assertOk()
        ->assertJson([]);
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

    // Activating B should deactivate A
    $this->postJson("/larafied/api/environments/{$envB['id']}/activate")
        ->assertOk()
        ->assertJsonPath('is_active', true);

    expect($storage->findEnvironment($envA['id'])['is_active'])->toBeFalsy();
});

it('returns 404 when activating non-existent environment', function () {
    $this->postJson('/larafied/api/environments/nonexistent/activate')
        ->assertNotFound();
});

it('is blocked in production environment', function () {
    $this->app['config']->set('app.env', 'production');

    $this->getJson('/larafied/api/environments')
        ->assertForbidden();
});
