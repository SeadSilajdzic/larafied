<?php

declare(strict_types=1);

namespace Larafied\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Larafied\Storage\WorkspaceStorage;

final class SyncService
{
    public function __construct(
        private readonly Client           $httpClient,
        private readonly WorkspaceStorage $storage,
        private readonly LicenseValidator $licenseValidator,
        private readonly string           $cloudUrl,
    ) {}

    /**
     * Push the current workspace to the cloud.
     * Returns ['ok' => true, 'checksum' => '...'] or ['error' => '...'].
     */
    public function push(): array
    {
        $key = $this->resolveKey();
        if ($key === null) {
            return ['error' => 'No license key configured.'];
        }

        $payload = $this->exportWorkspace();

        try {
            $response = $this->httpClient->post(
                $this->cloudUrl . '/api/v1/workspace/sync',
                [
                    'headers' => ['X-Larafied-Key' => $key, 'Accept' => 'application/json'],
                    'json'    => ['payload' => $payload],
                    'timeout' => 30,
                ],
            );

            $body = json_decode((string) $response->getBody(), true) ?? [];

            if (isset($body['synced']) && $body['synced'] === true) {
                return ['ok' => true, 'checksum' => $body['checksum'] ?? null];
            }

            return ['error' => $body['error'] ?? 'Unexpected response from cloud.'];
        } catch (GuzzleException $e) {
            return ['error' => 'Cloud unreachable: ' . $e->getMessage()];
        }
    }

    /**
     * Pull the latest workspace snapshot from the cloud and restore it.
     * Returns ['ok' => true, 'updated_at' => '...'] or ['error' => '...'].
     */
    public function pull(): array
    {
        $key = $this->resolveKey();
        if ($key === null) {
            return ['error' => 'No license key configured.'];
        }

        try {
            $response = $this->httpClient->get(
                $this->cloudUrl . '/api/v1/workspace/sync',
                [
                    'headers' => ['X-Larafied-Key' => $key, 'Accept' => 'application/json'],
                    'timeout' => 30,
                ],
            );

            $body = json_decode((string) $response->getBody(), true) ?? [];

            if (! isset($body['snapshot']) || $body['snapshot'] === null) {
                return ['error' => 'No snapshot found in the cloud.'];
            }

            $this->importWorkspace($body['snapshot']['payload']);

            return ['ok' => true, 'updated_at' => $body['snapshot']['updated_at'] ?? null];
        } catch (GuzzleException $e) {
            return ['error' => 'Cloud unreachable: ' . $e->getMessage()];
        }
    }

    private function resolveKey(): ?string
    {
        $cache = $this->licenseValidator->readCache();
        if ($cache === null) {
            return null;
        }
        $key = $cache['key'] ?? null;
        return is_string($key) && $key !== '' ? $key : null;
    }

    private function exportWorkspace(): array
    {
        $collections  = $this->storage->collections()->all();
        $environments = $this->storage->environments()->all();

        // Eager-load requests per collection
        $collectionsWithRequests = array_map(function (array $col) {
            $col['requests'] = $this->storage->requestsForCollection($col['id']);
            return $col;
        }, $collections);

        return [
            'version'      => 1,
            'collections'  => $collectionsWithRequests,
            'environments' => $environments,
        ];
    }

    private function importWorkspace(array $payload): void
    {
        // Import is additive: existing data is preserved; remote collections with
        // the same name are skipped to avoid duplicates on repeated pulls.
        $existingNames = array_column(
            $this->storage->collections()->all(),
            'name',
        );

        foreach ($payload['collections'] ?? [] as $col) {
            if (in_array($col['name'], $existingNames, true)) {
                continue;
            }

            $newCol = $this->storage->saveCollection([
                'name'        => $col['name'],
                'description' => $col['description'] ?? null,
            ]);

            foreach ($col['requests'] ?? [] as $req) {
                $this->storage->saveRequest([
                    'collection_id' => $newCol['id'],
                    'folder_id'     => null,
                    'name'          => $req['name'],
                    'sort_order'    => $req['sort_order'] ?? 0,
                    'data'          => $req['data'] ?? [],
                ]);
            }
        }
    }
}
