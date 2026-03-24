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
}
