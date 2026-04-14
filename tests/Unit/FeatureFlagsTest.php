<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;

function makeFeatureFlags(array $cacheData = null, string $licenseKey = 'AW-TEST-KEY'): FeatureFlags
{
    $storagePath = sys_get_temp_dir().'/ff-test-'.uniqid();
    mkdir($storagePath, 0755, true);

    $validator = new LicenseValidator(
        httpClient:      new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        storagePath:     $storagePath,
        licenseKey:      $licenseKey,
        cloudUrl:        'https://api.larafied.com',
        gracePeriodDays: 7,
    );

    if ($cacheData !== null) {
        $validator->cache($cacheData);
    }

    return new FeatureFlags($validator);
}

function cacheFor(string $tier, ?string $graceUntil = null): array
{
    return [
        'key'          => 'AW-TEST-KEY',
        'tier'         => $tier,
        'features'     => match ($tier) {
            'pro'    => ['unlimited_collections', 'environments', 'auth_helpers', 'import_export'],
            'team'   => ['unlimited_collections', 'environments', 'auth_helpers', 'import_export', 'cloud_sync', 'shared_collections'],
            'agency' => ['unlimited_collections', 'environments', 'auth_helpers', 'import_export', 'cloud_sync', 'shared_collections', 'white_label'],
            default  => [],
        },
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => $graceUntil,
    ];
}

// --- tier() ---

it('returns free tier when no cache exists', function () {
    expect(makeFeatureFlags()->tier())->toBe('free');
});

it('returns the cached tier', function () {
    expect(makeFeatureFlags(cacheFor('pro'))->tier())->toBe('pro');
    expect(makeFeatureFlags(cacheFor('team'))->tier())->toBe('team');
});

it('returns free tier when grace period has expired', function () {
    $cache = cacheFor('pro', (new DateTime('-1 day'))->format(DateTime::ATOM));

    expect(makeFeatureFlags($cache)->tier())->toBe('free');
});

it('returns cached tier when within grace period', function () {
    $cache = cacheFor('pro', (new DateTime('+3 days'))->format(DateTime::ATOM));

    expect(makeFeatureFlags($cache)->tier())->toBe('pro');
});

// --- isEnabled() ---

it('returns false when no cache exists', function () {
    expect(makeFeatureFlags()->isEnabled('unlimited_collections'))->toBeFalse();
});

it('returns true for a feature enabled on the current tier', function () {
    expect(makeFeatureFlags(cacheFor('pro'))->isEnabled('unlimited_collections'))->toBeTrue();
    expect(makeFeatureFlags(cacheFor('pro'))->isEnabled('environments'))->toBeTrue();
});

it('returns false for a feature not included on the current tier', function () {
    expect(makeFeatureFlags(cacheFor('pro'))->isEnabled('cloud_sync'))->toBeFalse();
    expect(makeFeatureFlags(cacheFor('pro'))->isEnabled('white_label'))->toBeFalse();
});

it('returns false for all features when grace period has expired', function () {
    $cache = cacheFor('pro', (new DateTime('-1 day'))->format(DateTime::ATOM));

    expect(makeFeatureFlags($cache)->isEnabled('unlimited_collections'))->toBeFalse();
});

it('team tier includes all pro features plus cloud_sync and shared_collections', function () {
    $flags = makeFeatureFlags(cacheFor('team'));

    expect($flags->isEnabled('unlimited_collections'))->toBeTrue();
    expect($flags->isEnabled('cloud_sync'))->toBeTrue();
    expect($flags->isEnabled('shared_collections'))->toBeTrue();
    expect($flags->isEnabled('white_label'))->toBeFalse();
});

it('pro tier enables new package features even when cloud cache predates them', function () {
    // Simulate old cloud cache that only has legacy features
    $oldCache = [
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'pro',
        'features'     => ['unlimited_collections', 'environments'], // old cache, missing new features
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ];
    $flags = makeFeatureFlags($oldCache);

    expect($flags->isEnabled('graphql'))->toBeTrue();
    expect($flags->isEnabled('sql_console'))->toBeTrue();
    expect($flags->isEnabled('query_log'))->toBeTrue();
    expect($flags->isEnabled('request_history'))->toBeTrue();
    expect($flags->isEnabled('pre_request_scripts'))->toBeTrue();
    // Team-only features still locked
    expect($flags->isEnabled('cloud_sync'))->toBeFalse();
});

it('team tier enables all pro and team features regardless of cloud cache content', function () {
    $oldCache = [
        'key'          => 'AW-TEST-KEY',
        'tier'         => 'team',
        'features'     => ['unlimited_collections'], // minimal old cache
        'expires_at'   => null,
        'validated_at' => (new DateTime())->format(DateTime::ATOM),
        'grace_until'  => null,
    ];
    $flags = makeFeatureFlags($oldCache);

    expect($flags->isEnabled('graphql'))->toBeTrue();
    expect($flags->isEnabled('sql_console'))->toBeTrue();
    expect($flags->isEnabled('cloud_sync'))->toBeTrue();
    expect($flags->isEnabled('shared_collections'))->toBeTrue();
    expect($flags->isEnabled('white_label'))->toBeFalse();
});
