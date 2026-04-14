<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Larafied\Exceptions\LicenseException;
use Larafied\Services\LicenseValidator;

beforeEach(function () {
    $this->storagePath = sys_get_temp_dir().'/lv-test-'.uniqid();
    mkdir($this->storagePath, 0755, true);
});

afterEach(function () {
    $cache = $this->storagePath.DIRECTORY_SEPARATOR.'license.json';
    if (file_exists($cache)) {
        @unlink($cache);
    }
    if (is_dir($this->storagePath)) {
        @rmdir($this->storagePath);
    }
});

function makeValidator(MockHandler $mock, string $storagePath, string $licenseKey = 'AW-TEST-KEY'): LicenseValidator
{
    $client = new Client(['handler' => HandlerStack::create($mock)]);

    return new LicenseValidator(
        httpClient:      $client,
        storagePath:     $storagePath,
        licenseKey:      $licenseKey,
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );
}

// --- validate() ---

it('returns tier and features on successful cloud validation', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'valid'             => true,
            'tier'              => 'pro',
            'features'          => ['unlimited_collections', 'environments'],
            'expires_at'        => null,
            'cache_for_seconds' => 86400,
        ])),
    ]);

    $result = makeValidator($mock, $this->storagePath)
        ->validate('AW-TEST-KEY', 'myapp.local');

    expect($result['tier'])->toBe('pro');
    expect($result['features'])->toContain('unlimited_collections');
    expect($result['grace_until'])->toBeNull();
});

it('writes cache after successful validation', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'valid'             => true,
            'tier'              => 'pro',
            'features'          => ['unlimited_collections'],
            'expires_at'        => null,
            'cache_for_seconds' => 86400,
        ])),
    ]);

    makeValidator($mock, $this->storagePath)->validate('AW-TEST-KEY', 'myapp.local');

    expect(file_exists($this->storagePath.DIRECTORY_SEPARATOR.'license.json'))->toBeTrue();
});

it('throws LicenseException when cloud returns valid false', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'valid'  => false,
            'reason' => 'license_revoked',
        ])),
    ]);

    expect(fn () => makeValidator($mock, $this->storagePath)->validate('AW-BAD', 'myapp.local'))
        ->toThrow(LicenseException::class, 'license_revoked');
});

it('falls back to cache when cloud is unreachable', function () {
    // Pre-populate a valid cache
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $cached    = [
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ];
    $validator->cache($cached);

    // Now simulate a ConnectException
    $mock      = new MockHandler([new ConnectException('Connection refused', new Request('POST', '/'))]);
    $validator = makeValidator($mock, $this->storagePath);
    $result    = $validator->validate('AW-TEST-KEY', 'myapp.local');

    expect($result['tier'])->toBe('pro');
});

it('sets grace_until when falling back to cache for the first time', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    $mock   = new MockHandler([new ConnectException('Connection refused', new Request('POST', '/'))]);
    $result = makeValidator($mock, $this->storagePath)->validate('AW-TEST-KEY', 'myapp.local');

    expect($result['grace_until'])->not->toBeNull();
});

it('throws LicenseException when cloud unreachable and no cache exists', function () {
    $mock = new MockHandler([new ConnectException('Connection refused', new Request('POST', '/'))]);

    expect(fn () => makeValidator($mock, $this->storagePath)->validate('AW-TEST-KEY', 'myapp.local'))
        ->toThrow(LicenseException::class);
});

// --- cache() / readCache() ---

it('reads back a valid cache file', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $data      = [
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections'],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ];

    $validator->cache($data);

    expect($validator->readCache())->not->toBeNull();
    expect($validator->readCache()['tier'])->toBe('pro');
});

it('returns null when cache file does not exist', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);

    expect($validator->readCache())->toBeNull();
});

it('returns null when cache hmac has been tampered', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    // Tamper the file
    $path    = $this->storagePath.DIRECTORY_SEPARATOR.'license.json';
    $content = json_decode(file_get_contents($path), true);
    $content['tier'] = 'team'; // change tier without updating hmac
    file_put_contents($path, json_encode($content));

    expect($validator->readCache())->toBeNull();
});

// --- isWithinGracePeriod() ---

it('is within grace period when grace_until is in the future', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('+3 days'))->format(DateTime::ATOM),
    ]);

    expect($validator->isWithinGracePeriod())->toBeTrue();
});

it('is not within grace period when grace_until has passed', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime('-10 days'))->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('-1 day'))->format(DateTime::ATOM),
    ]);

    expect($validator->isWithinGracePeriod())->toBeFalse();
});

it('is within grace period when grace_until is null (no offline period)', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    expect($validator->isWithinGracePeriod())->toBeTrue();
});

// --- graceWarning() ---

it('returns false for grace_warning when no grace period is active', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    expect($validator->graceWarning())->toBeFalse();
});

it('returns false for grace_warning when more than 2 days remain', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('+3 days'))->format(DateTime::ATOM),
    ]);

    expect($validator->graceWarning())->toBeFalse();
});

it('returns true for grace_warning when 2 or fewer days remain', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('+1 day'))->format(DateTime::ATOM),
    ]);

    expect($validator->graceWarning())->toBeTrue();
});

it('returns false for grace_warning when grace has already expired', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime('-10 days'))->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('-1 day'))->format(DateTime::ATOM),
    ]);

    expect($validator->graceWarning())->toBeFalse();
});

// --- graceLogWarning() ---

it('returns false for grace log warning when no grace is active', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ]);

    expect($validator->graceLogWarning())->toBeFalse();
});

it('returns true for grace log warning when 2 to 4 days remain', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('+3 days'))->format(DateTime::ATOM),
    ]);

    expect($validator->graceLogWarning())->toBeTrue();
});

it('returns false for grace log warning when more than 4 days remain', function () {
    $validator = makeValidator(new MockHandler([]), $this->storagePath);
    $validator->cache([
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => [],
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => (new DateTime('+5 days'))->format(DateTime::ATOM),
    ]);

    expect($validator->graceLogWarning())->toBeFalse();
});
