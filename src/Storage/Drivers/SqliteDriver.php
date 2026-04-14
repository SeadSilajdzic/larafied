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

    public function topLevelRequestsForCollection(string $collectionId): Collection
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM requests WHERE collection_id = ? AND folder_id IS NULL ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$collectionId]);

        return collect($stmt->fetchAll())->map(fn (array $row) => $this->deserializeRequest($row));
    }

    public function requestsInFolder(string $folderId): Collection
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM requests WHERE folder_id = ? ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$folderId]);

        return collect($stmt->fetchAll())->map(fn (array $row) => $this->deserializeRequest($row));
    }

    public function saveRequest(array $data): array
    {
        $now        = time();
        $id         = $data['id'] ?? (string) Str::ulid();
        $serialized = json_encode($data['data'] ?? [], JSON_THROW_ON_ERROR);

        if (isset($data['id']) && $this->findRequest($data['id']) !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE requests SET collection_id = ?, folder_id = ?, name = ?, data = ?, sort_order = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([
                $data['collection_id'] ?? null,
                $data['folder_id'] ?? null,
                $data['name'],
                $serialized,
                $data['sort_order'] ?? 0,
                $now,
                $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO requests (id, collection_id, folder_id, name, data, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $id,
                $data['collection_id'] ?? null,
                $data['folder_id'] ?? null,
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
    // Folders
    // -------------------------------------------------------------------------

    public function foldersForCollection(string $collectionId): Collection
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM folders WHERE collection_id = ? ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$collectionId]);

        return collect($stmt->fetchAll())->map(fn (array $row) => $this->deserializeFolder($row));
    }

    public function findFolder(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM folders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->deserializeFolder($row) : null;
    }

    public function saveFolder(array $data): array
    {
        $now = time();
        $id  = $data['id'] ?? (string) Str::ulid();

        if (isset($data['id']) && $this->findFolder($data['id']) !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE folders SET name = ?, description = ?, sort_order = ?, updated_at = ? WHERE id = ?'
            );
            $stmt->execute([$data['name'], $data['description'] ?? null, $data['sort_order'] ?? 0, $now, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO folders (id, collection_id, name, description, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $id,
                $data['collection_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['sort_order'] ?? 0,
                $now,
                $now,
            ]);
        }

        return $this->findFolder($id);
    }

    public function deleteFolder(string $id): void
    {
        $this->pdo->prepare('DELETE FROM folders WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // History
    // -------------------------------------------------------------------------

    public function getHistory(): Collection
    {
        $rows = $this->pdo->query(
            'SELECT * FROM history ORDER BY created_at DESC, id DESC LIMIT 50'
        )->fetchAll();

        return collect($rows)->map(fn (array $row) => $this->deserializeHistory($row));
    }

    public function saveToHistory(array $data): array
    {
        $id  = (string) Str::ulid();
        $now = time();

        $stmt = $this->pdo->prepare(
            'INSERT INTO history (id, method, url, headers, body, status, duration_ms, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $data['method'],
            $data['url'],
            json_encode($data['headers'] ?? [], JSON_THROW_ON_ERROR),
            $data['body'] ?? null,
            $data['status'] ?? null,
            $data['duration_ms'] ?? null,
            $now,
        ]);

        // Trim to most recent 50
        $this->pdo->exec(
            "DELETE FROM history WHERE id NOT IN (
                SELECT id FROM history ORDER BY created_at DESC, id DESC LIMIT 50
            )"
        );

        $stmt = $this->pdo->prepare('SELECT * FROM history WHERE id = ?');
        $stmt->execute([$id]);

        return $this->deserializeHistory($stmt->fetch());
    }

    public function clearHistory(): void
    {
        $this->pdo->exec('DELETE FROM history');
    }

    // -------------------------------------------------------------------------
    // Network Monitor Events
    // -------------------------------------------------------------------------

    public function recordNetworkEvent(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO network_events
                (method, path, query, status, duration_ms, req_headers, req_body, res_headers, res_body, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['method'],
            $data['path'],
            $data['query'] ?? null,
            $data['status'] ?? null,
            $data['duration_ms'] ?? null,
            json_encode($data['req_headers'] ?? [], JSON_THROW_ON_ERROR),
            $data['req_body'] ?? null,
            json_encode($data['res_headers'] ?? [], JSON_THROW_ON_ERROR),
            $data['res_body'] ?? null,
            $data['ip'] ?? null,
            time(),
        ]);

        $id = (int) $this->pdo->lastInsertId();

        // Prune oldest events beyond the configured limit
        $max = (int) config('larafied.network_monitor.max_events', 500);
        $this->pdo->exec(
            "DELETE FROM network_events WHERE id NOT IN (
                SELECT id FROM network_events ORDER BY id DESC LIMIT {$max}
            )"
        );

        return $this->findNetworkEvent($id);
    }

    public function findNetworkEvent(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM network_events WHERE id = ?');
        $stmt->execute([$id]);
        return $this->deserializeNetworkEvent($stmt->fetch());
    }

    public function getNetworkEvents(int $cursor = 0, int $limit = 50): Collection
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM network_events WHERE id > ? ORDER BY id ASC LIMIT ?'
        );
        $stmt->execute([$cursor, $limit]);

        return collect($stmt->fetchAll())->map(fn (array $row) => $this->deserializeNetworkEvent($row));
    }

    public function clearNetworkEvents(): void
    {
        $this->pdo->exec('DELETE FROM network_events');
    }

    public function networkEventCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM network_events')->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Route Notes
    // -------------------------------------------------------------------------

    public function allRouteNotes(): Collection
    {
        return collect($this->pdo->query('SELECT * FROM route_notes')->fetchAll());
    }

    public function findRouteNote(string $method, string $uri): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM route_notes WHERE method = ? AND uri = ?');
        $stmt->execute([$method, $uri]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function saveRouteNote(string $method, string $uri, string $note): array
    {
        $now = time();
        $id  = (string) Str::ulid();

        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO route_notes (id, method, uri, note, updated_at)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(method, uri) DO UPDATE SET note = excluded.note, updated_at = excluded.updated_at
        SQL);
        $stmt->execute([$id, $method, $uri, $note, $now]);

        return $this->findRouteNote($method, $uri);
    }

    public function deleteRouteNote(string $method, string $uri): void
    {
        $this->pdo->prepare('DELETE FROM route_notes WHERE method = ? AND uri = ?')->execute([$method, $uri]);
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

            CREATE TABLE IF NOT EXISTS folders (
                id            TEXT    PRIMARY KEY,
                collection_id TEXT    NOT NULL,
                name          TEXT    NOT NULL,
                description   TEXT,
                sort_order    INTEGER NOT NULL DEFAULT 0,
                created_at    INTEGER NOT NULL,
                updated_at    INTEGER NOT NULL,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS requests (
                id            TEXT    PRIMARY KEY,
                collection_id TEXT,
                folder_id     TEXT,
                name          TEXT    NOT NULL,
                data          TEXT    NOT NULL DEFAULT '{}',
                sort_order    INTEGER NOT NULL DEFAULT 0,
                created_at    INTEGER NOT NULL,
                updated_at    INTEGER NOT NULL,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
                FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS route_notes (
                id         TEXT    PRIMARY KEY,
                method     TEXT    NOT NULL,
                uri        TEXT    NOT NULL,
                note       TEXT    NOT NULL DEFAULT '',
                updated_at INTEGER NOT NULL,
                UNIQUE(method, uri)
            );

            CREATE TABLE IF NOT EXISTS history (
                id          TEXT    PRIMARY KEY,
                method      TEXT    NOT NULL,
                url         TEXT    NOT NULL,
                headers     TEXT    NOT NULL DEFAULT '{}',
                body        TEXT,
                status      INTEGER,
                duration_ms REAL,
                created_at  INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS network_events (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                method      TEXT    NOT NULL,
                path        TEXT    NOT NULL,
                query       TEXT,
                status      INTEGER,
                duration_ms REAL,
                req_headers TEXT    NOT NULL DEFAULT '{}',
                req_body    TEXT,
                res_headers TEXT    NOT NULL DEFAULT '{}',
                res_body    TEXT,
                ip          TEXT,
                created_at  INTEGER NOT NULL
            );
        SQL);

        // Migrate existing requests table: add folder_id if missing
        try {
            $this->pdo->exec('ALTER TABLE requests ADD COLUMN folder_id TEXT REFERENCES folders(id) ON DELETE SET NULL');
        } catch (\PDOException) {
            // Column already exists — safe to ignore
        }
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

    private function deserializeFolder(array $row): array
    {
        return [
            'id'            => $row['id'],
            'collection_id' => $row['collection_id'],
            'name'          => $row['name'],
            'description'   => $row['description'],
            'sort_order'    => (int) $row['sort_order'],
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at'],
        ];
    }

    private function deserializeHistory(array $row): array
    {
        return [
            'id'          => $row['id'],
            'method'      => $row['method'],
            'url'         => $row['url'],
            'headers'     => json_decode($row['headers'], true) ?? [],
            'body'        => $row['body'],
            'status'      => $row['status'] !== null ? (int) $row['status'] : null,
            'duration_ms' => $row['duration_ms'] !== null ? (float) $row['duration_ms'] : null,
            'created_at'  => $row['created_at'],
        ];
    }

    private function deserializeRequest(array $row): array
    {
        return [
            'id'            => $row['id'],
            'collection_id' => $row['collection_id'],
            'folder_id'     => $row['folder_id'] ?? null,
            'name'          => $row['name'],
            'data'          => json_decode($row['data'], true) ?? [],
            'sort_order'    => (int) $row['sort_order'],
            'created_at'    => $row['created_at'],
            'updated_at'    => $row['updated_at'],
        ];
    }

    private function deserializeNetworkEvent(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'method'      => $row['method'],
            'path'        => $row['path'],
            'query'       => $row['query'],
            'status'      => $row['status'] !== null ? (int) $row['status'] : null,
            'duration_ms' => $row['duration_ms'] !== null ? (float) $row['duration_ms'] : null,
            'req_headers' => json_decode($row['req_headers'], true) ?? [],
            'req_body'    => $row['req_body'],
            'res_headers' => json_decode($row['res_headers'], true) ?? [],
            'res_body'    => $row['res_body'],
            'ip'          => $row['ip'],
            'created_at'  => (int) $row['created_at'],
        ];
    }
}
