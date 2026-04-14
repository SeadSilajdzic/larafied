<?php

declare(strict_types=1);

use Larafied\Storage\Drivers\SqliteDriver;

beforeEach(function () {
    $this->storagePath = sys_get_temp_dir().'/larafied-test-'.uniqid();
    $this->driver      = new SqliteDriver($this->storagePath);
});

afterEach(function () {
    // Null out the driver to release the PDO file handle before cleanup.
    // On Windows, an open PDO handle prevents unlink() from succeeding.
    $this->driver = null;

    $db = $this->storagePath.DIRECTORY_SEPARATOR.'workspace.db';
    if (file_exists($db)) {
        @unlink($db);
    }
    if (is_dir($this->storagePath)) {
        @rmdir($this->storagePath);
    }
});

// Collections

it('starts with zero collections', function () {
    expect($this->driver->collectionCount())->toBe(0);
    expect($this->driver->collections())->toHaveCount(0);
});

it('saves and retrieves a collection', function () {
    $saved = $this->driver->saveCollection(['name' => 'Auth API', 'description' => 'Auth endpoints']);

    expect($saved)->toMatchArray(['name' => 'Auth API', 'description' => 'Auth endpoints'])
        ->and($saved['id'])->not->toBeEmpty()
        ->and($this->driver->collectionCount())->toBe(1);
});

it('updates an existing collection', function () {
    $created = $this->driver->saveCollection(['name' => 'Original']);
    $updated = $this->driver->saveCollection(['id' => $created['id'], 'name' => 'Updated']);

    expect($updated['name'])->toBe('Updated')
        ->and($this->driver->collectionCount())->toBe(1);
});

it('deletes a collection', function () {
    $collection = $this->driver->saveCollection(['name' => 'To Delete']);
    $this->driver->deleteCollection($collection['id']);

    expect($this->driver->collectionCount())->toBe(0)
        ->and($this->driver->findCollection($collection['id']))->toBeNull();
});

it('returns null for missing collection', function () {
    expect($this->driver->findCollection('nonexistent-id'))->toBeNull();
});

// Environments

it('saves and retrieves an environment', function () {
    $saved = $this->driver->saveEnvironment([
        'name'      => 'Local',
        'variables' => [
            ['key' => 'base_url', 'value' => 'http://localhost', 'secret' => false],
        ],
        'is_active' => true,
    ]);

    expect($saved['name'])->toBe('Local')
        ->and($saved['is_active'])->toBeTrue()
        ->and($saved['variables'])->toHaveCount(1)
        ->and($saved['variables'][0]['key'])->toBe('base_url');
});

it('activates only one environment at a time', function () {
    $env1 = $this->driver->saveEnvironment(['name' => 'Local', 'variables' => [], 'is_active' => false]);
    $env2 = $this->driver->saveEnvironment(['name' => 'Staging', 'variables' => [], 'is_active' => false]);

    $this->driver->activateEnvironment($env1['id']);
    $this->driver->activateEnvironment($env2['id']);

    expect($this->driver->findEnvironment($env1['id'])['is_active'])->toBeFalse()
        ->and($this->driver->findEnvironment($env2['id'])['is_active'])->toBeTrue();
});

// Saved Requests

it('saves a request to a collection', function () {
    $collection = $this->driver->saveCollection(['name' => 'Auth']);

    $saved = $this->driver->saveRequest([
        'collection_id' => $collection['id'],
        'name'          => 'Login',
        'data'          => ['method' => 'POST', 'url' => '/api/login'],
    ]);

    expect($saved['name'])->toBe('Login')
        ->and($saved['data']['method'])->toBe('POST')
        ->and($this->driver->requestsForCollection($collection['id']))->toHaveCount(1);
});

it('cascades delete requests when collection is deleted', function () {
    $collection = $this->driver->saveCollection(['name' => 'Auth']);
    $request    = $this->driver->saveRequest([
        'collection_id' => $collection['id'],
        'name'          => 'Login',
        'data'          => ['method' => 'POST', 'url' => '/api/login'],
    ]);

    $this->driver->deleteCollection($collection['id']);

    expect($this->driver->findRequest($request['id']))->toBeNull();
});

// History

it('starts with empty history', function () {
    expect($this->driver->getHistory())->toBeEmpty();
});

it('saves and retrieves a history entry', function () {
    $entry = $this->driver->saveToHistory([
        'method'      => 'GET',
        'url'         => 'https://example.com/api/users',
        'headers'     => ['Accept' => 'application/json'],
        'body'        => null,
        'status'      => 200,
        'duration_ms' => 123.4,
    ]);

    expect($entry)->toHaveKeys(['id', 'method', 'url', 'headers', 'body', 'status', 'duration_ms', 'created_at'])
        ->and($entry['method'])->toBe('GET')
        ->and($entry['url'])->toBe('https://example.com/api/users')
        ->and($entry['status'])->toBe(200)
        ->and($entry['headers'])->toBe(['Accept' => 'application/json']);

    expect($this->driver->getHistory())->toHaveCount(1);
});

it('returns history ordered newest first', function () {
    $this->driver->saveToHistory(['method' => 'GET',  'url' => 'https://example.com/1', 'status' => 200]);
    $this->driver->saveToHistory(['method' => 'POST', 'url' => 'https://example.com/2', 'status' => 201]);

    $history = $this->driver->getHistory();

    expect($history->first()['url'])->toBe('https://example.com/2')
        ->and($history->last()['url'])->toBe('https://example.com/1');
});

it('trims history to 50 entries', function () {
    foreach (range(1, 55) as $i) {
        $this->driver->saveToHistory([
            'method' => 'GET',
            'url'    => "https://example.com/api/{$i}",
            'status' => 200,
        ]);
    }

    expect($this->driver->getHistory())->toHaveCount(50);
});

it('clears all history', function () {
    $this->driver->saveToHistory(['method' => 'GET', 'url' => 'https://example.com', 'status' => 200]);
    $this->driver->clearHistory();

    expect($this->driver->getHistory())->toBeEmpty();
});
