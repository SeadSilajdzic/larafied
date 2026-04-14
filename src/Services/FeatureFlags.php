<?php

declare(strict_types=1);

namespace Larafied\Services;

use DateTime;

final class FeatureFlags
{
    /**
     * Canonical feature list per tier — package-owned source of truth.
     * Cloud cache features[] is additive; this map ensures new package
     * features unlock for the correct tier even before the cloud app
     * is updated to include them in its response.
     */
    private const TIER_FEATURES = [
        'pro'    => [
            'unlimited_collections', 'environments',
            'auth_helpers', 'import_export',
            'graphql', 'sql_console', 'query_log', 'request_history',
            'pre_request_scripts',
        ],
        'team'   => [
            'unlimited_collections', 'environments',
            'auth_helpers', 'import_export',
            'graphql', 'sql_console', 'query_log', 'request_history',
            'pre_request_scripts',
            'cloud_sync', 'shared_collections',
        ],
        'agency' => [
            'unlimited_collections', 'environments',
            'auth_helpers', 'import_export',
            'graphql', 'sql_console', 'query_log', 'request_history',
            'pre_request_scripts',
            'cloud_sync', 'shared_collections', 'white_label',
        ],
    ];

    public function __construct(
        private readonly LicenseValidator $licenseValidator,
    ) {}

    public function tier(): string
    {
        $cache = $this->licenseValidator->readCache();

        if ($cache === null) {
            return 'free';
        }

        if (! $this->cacheIsActive($cache)) {
            return 'free';
        }

        return $cache['tier'] ?? 'free';
    }

    /**
     * Returns the full list of features enabled for the current tier.
     * Merges TIER_FEATURES (authoritative) with cloud cache features (additive).
     */
    public function features(): array
    {
        $cache = $this->licenseValidator->readCache();

        if ($cache === null || ! $this->cacheIsActive($cache)) {
            return [];
        }

        $tier         = $cache['tier'] ?? 'free';
        $tierFeatures = self::TIER_FEATURES[$tier] ?? [];
        $cloudFeatures = $cache['features'] ?? [];

        return array_values(array_unique(array_merge($tierFeatures, $cloudFeatures)));
    }

    public function isEnabled(string $feature): bool
    {
        $cache = $this->licenseValidator->readCache();

        if ($cache === null) {
            return false;
        }

        if (! $this->cacheIsActive($cache)) {
            return false;
        }

        $tier = $cache['tier'] ?? 'free';

        // Tier-based map is authoritative — covers features added after the
        // cloud cache was last written (avoids stale cache locking new features).
        if (in_array($feature, self::TIER_FEATURES[$tier] ?? [], true)) {
            return true;
        }

        // Fall back to explicit features array from cloud (future extensibility)
        return in_array($feature, $cache['features'] ?? [], true);
    }

    private function cacheIsActive(array $cache): bool
    {
        if ($cache['grace_until'] === null) {
            return true;
        }

        return new DateTime() <= new DateTime($cache['grace_until']);
    }
}
