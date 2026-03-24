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

    public function findRequest(string $id): ?array;

    public function saveRequest(array $data): array;

    public function deleteRequest(string $id): void;
}
