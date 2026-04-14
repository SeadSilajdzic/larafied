<?php

declare(strict_types=1);

namespace Larafied\Storage;

use Larafied\Contracts\StorageDriverContract;
use Illuminate\Support\Collection;

final class WorkspaceStorage
{
    public function __construct(private readonly StorageDriverContract $driver) {}

    public function collections(): Collection
    {
        return $this->driver->collections();
    }

    public function findCollection(string $id): ?array
    {
        return $this->driver->findCollection($id);
    }

    public function saveCollection(array $data): array
    {
        return $this->driver->saveCollection($data);
    }

    public function deleteCollection(string $id): void
    {
        $this->driver->deleteCollection($id);
    }

    public function collectionCount(): int
    {
        return $this->driver->collectionCount();
    }

    public function environments(): Collection
    {
        return $this->driver->environments();
    }

    public function findEnvironment(string $id): ?array
    {
        return $this->driver->findEnvironment($id);
    }

    public function saveEnvironment(array $data): array
    {
        return $this->driver->saveEnvironment($data);
    }

    public function deleteEnvironment(string $id): void
    {
        $this->driver->deleteEnvironment($id);
    }

    public function activateEnvironment(string $id): void
    {
        $this->driver->activateEnvironment($id);
    }

    public function requestsForCollection(string $collectionId): Collection
    {
        return $this->driver->requestsForCollection($collectionId);
    }

    public function topLevelRequestsForCollection(string $collectionId): Collection
    {
        return $this->driver->topLevelRequestsForCollection($collectionId);
    }

    public function requestsInFolder(string $folderId): Collection
    {
        return $this->driver->requestsInFolder($folderId);
    }

    public function findRequest(string $id): ?array
    {
        return $this->driver->findRequest($id);
    }

    public function saveRequest(array $data): array
    {
        return $this->driver->saveRequest($data);
    }

    public function deleteRequest(string $id): void
    {
        $this->driver->deleteRequest($id);
    }

    public function foldersForCollection(string $collectionId): Collection
    {
        return $this->driver->foldersForCollection($collectionId);
    }

    public function findFolder(string $id): ?array
    {
        return $this->driver->findFolder($id);
    }

    public function saveFolder(array $data): array
    {
        return $this->driver->saveFolder($data);
    }

    public function deleteFolder(string $id): void
    {
        $this->driver->deleteFolder($id);
    }

    public function getHistory(): Collection
    {
        return $this->driver->getHistory();
    }

    public function saveToHistory(array $data): array
    {
        return $this->driver->saveToHistory($data);
    }

    public function clearHistory(): void
    {
        $this->driver->clearHistory();
    }

    public function allRouteNotes(): Collection
    {
        return $this->driver->allRouteNotes();
    }

    public function findRouteNote(string $method, string $uri): ?array
    {
        return $this->driver->findRouteNote($method, $uri);
    }

    public function saveRouteNote(string $method, string $uri, string $note): array
    {
        return $this->driver->saveRouteNote($method, $uri, $note);
    }

    public function deleteRouteNote(string $method, string $uri): void
    {
        $this->driver->deleteRouteNote($method, $uri);
    }

    public function recordNetworkEvent(array $data): array
    {
        return $this->driver->recordNetworkEvent($data);
    }

    public function getNetworkEvents(int $cursor = 0, int $limit = 50): Collection
    {
        return $this->driver->getNetworkEvents($cursor, $limit);
    }

    public function clearNetworkEvents(): void
    {
        $this->driver->clearNetworkEvents();
    }

    public function networkEventCount(): int
    {
        return $this->driver->networkEventCount();
    }
}
