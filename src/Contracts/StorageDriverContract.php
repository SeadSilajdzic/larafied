<?php

declare(strict_types=1);

namespace Larafied\Contracts;

use Illuminate\Support\Collection;

interface StorageDriverContract
{
    // Collections

    public function collections(): Collection;

    public function findCollection(string $id): ?array;

    public function saveCollection(array $data): array;

    public function deleteCollection(string $id): void;

    public function collectionCount(): int;

    // Environments

    public function environments(): Collection;

    public function findEnvironment(string $id): ?array;

    public function saveEnvironment(array $data): array;

    public function deleteEnvironment(string $id): void;

    public function activateEnvironment(string $id): void;

    // Saved Requests

    public function requestsForCollection(string $collectionId): Collection;

    public function topLevelRequestsForCollection(string $collectionId): Collection;

    public function requestsInFolder(string $folderId): Collection;

    public function findRequest(string $id): ?array;

    public function saveRequest(array $data): array;

    public function deleteRequest(string $id): void;

    // Folders

    public function foldersForCollection(string $collectionId): Collection;

    public function findFolder(string $id): ?array;

    public function saveFolder(array $data): array;

    public function deleteFolder(string $id): void;

    // History

    public function getHistory(): Collection;

    public function saveToHistory(array $data): array;

    public function clearHistory(): void;

    // Route Notes

    public function allRouteNotes(): Collection;

    public function findRouteNote(string $method, string $uri): ?array;

    public function saveRouteNote(string $method, string $uri, string $note): array;

    public function deleteRouteNote(string $method, string $uri): void;

    // Network Monitor Events

    public function recordNetworkEvent(array $data): array;

    public function getNetworkEvents(int $cursor = 0, int $limit = 50): Collection;

    public function clearNetworkEvents(): void;

    public function networkEventCount(): int;
}
