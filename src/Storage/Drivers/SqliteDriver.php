<?php

declare(strict_types=1);

namespace Larafied\Storage\Drivers;

use Larafied\Contracts\StorageDriverContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PDO;

final class SqliteDriver implements StorageDriverContract
{
    private PDO $pdo;

    public function __construct(private readonly string $storagePath)
    {
        $this->connect();
        $this->ensureSchema();
    }

    // -------------------------------------------------------------------------
    // Collections
    // -------------------------------------------------------------------------

    public function collections(): Collection
    {
        $rows = $this->pdo->query('SELECT * FROM collections ORDER BY name ASC')->fetchAll();

        return collect($rows)->map(fn (array $row) => $this->deserializeCollection($row));
    }

    public function findCollection(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM collections WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->deserializeCollection($row) : null;
    }

    public function saveCollection(array $data): array
    {
        $now = time();
        $id  = $data['id'] ?? (string) Str::ulid();

        if (isset($data['id']) && $this->findCollection($data['id']) !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE collections SET name = ?, description = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([$data['name'], $data['description'] ?? null, $now, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO collections (id, name, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$id, $data['name'], $data['description'] ?? null, $now, $now]);
        }

        return $this->findCollection($id);
    }

    public function deleteCollection(string $id): void
    {
        $this->pdo->prepare('DELETE FROM collections WHERE id = ?')->execute([$id]);
    }

    public function collectionCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM collections')->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Environments
    // -------------------------------------------------------------------------

    public function environments(): Collection
    {
        $rows = $this->pdo->query('SELECT * FROM environments ORDER BY name ASC')->fetchAll();

        return collect($rows)->map(fn (array $row) => $this->deserializeEnvironment($row));
    }

    public function findEnvironment(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM environments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->deserializeEnvironment($row) : null;
    }

    public function saveEnvironment(array $data): array
    {
        $now       = time();
        $id        = $data['id'] ?? (string) Str::ulid();
        $variables = json_encode($data['variables'] ?? [], JSON_THROW_ON_ERROR);
        $isActive  = isset($data['is_active']) && $data['is_active'] ? 1 : 0;

        if (isset($data['id']) && $this->findEnvironment($data['id']) !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE environments SET name = ?, variables = ?, is_active = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([$data['name'], $variables, $isActive, $now, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO environments (id, name, variables, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$id, $data['name'], $variables, $isActive, $now, $now]);
        }

        return $this->findEnvironment($id);
    }

    public function deleteEnvironment(string $id): void
    {
        $this->pdo->prepare('DELETE FROM environments WHERE id = ?')->execute([$id]);
    }

    public function activateEnvironment(string $id): void
    {
        $this->pdo->exec('UPDATE environments SET is_active = 0');
        $this->pdo->prepare('UPDATE environments SET is_active = 1, updated_at = ? WHERE id = ?')
            ->execute([time(), $id]);
    }

    // -------------------------------------------------------------------------
    // Saved Requests
    // -------------------------------------------------------------------------

    public function requestsForCollection(string $collectionId): Collection
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM requests WHERE collection_id = ? ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$collectionId]);

        return collect($stmt->fetchAll())->map(fn (array $row) => $this->deserializeRequest($row));
    }

    public function findRequest(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM requests WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->deserializeRequest($row) : null;
    }

    public function saveRequest(array $data): array
    {
        $now        = time();
        $id         = $data['id'] ?? (string) Str::ulid();
        $serialized = json_encode($data['data'] ?? [], JSON_THROW_ON_ERROR);

        if (isset($data['id']) && $this->findRequest($data['id']) !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE requests SET collection_id = ?, name = ?, data = ?, sort_order = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([
                $data['collection_id'] ?? null,
                $data['name'],
                $serialized,
                $data['sort_order'] ?? 0,
                $now,
                $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO requests (id, collection_id, name, data, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $id,
                $data['collection_id'] ?? null,
                $data['name'],
                $serialized,
                $data['sort_order'] ?? 0,
                $now,
                $now,
            ]);
        }

        return $this->findRequest($id);
    }

    public function deleteRequest(string $id): void
    {
        $this->pdo->prepare('DELETE FROM requests WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function connect(): void
    {
        if (! is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $this->pdo = new PDO('sqlite:'.$this->storagePath.DIRECTORY_SEPARATOR.'workspace.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS collections (
                id          TEXT    PRIMARY KEY,
                name        TEXT    NOT NULL,
                description TEXT,
                created_at  INTEGER NOT NULL,
                updated_at  INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS environments (
                id          TEXT    PRIMARY KEY,
                name        TEXT    NOT NULL,
                variables   TEXT    NOT NULL DEFAULT '[]',
                is_active   INTEGER NOT NULL DEFAULT 0,
                created_at  INTEGER NOT NULL,
                updated_at  INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS requests (
                id            TEXT    PRIMARY KEY,
                collection_id TEXT,
                name          TEXT    NOT NULL,
                data          TEXT    NOT NULL DEFAULT '{}',
                sort_order    INTEGER NOT NULL DEFAULT 0,
                created_at    INTEGER NOT NULL,
                updated_at    INTEGER NOT NULL,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
            );
        SQL);
    }

    private function deserializeCollection(array $row): array
    {
        return [
            'id'          => $row['id'],
            'name'        => $row['name'],
            'description' => $row['description'],
            'created_at'  => $row['created_at'],
            'updated_at'  => $row['updated_at'],
        ];
    }

    private function deserializeEnvironment(array $row): array
    {
        return [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'variables'  => json_decode($row['variables'], true) ?? [],
            'is_active'  => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private function deserializeRequest(array $row): array
    {
        return [
            'id'            => $row['id'],
            'collection_id' => $row['collection_id'],
            'name'          => $row['name'],
            'data'          => json_decode($row['data'], true) ?? [],
            'sort_order'    => (int) $row['sort_order'],
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at'],
        ];
    }
}
