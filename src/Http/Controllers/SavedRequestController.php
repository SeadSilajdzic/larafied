<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Http\Requests\StoreSavedRequestRequest;
use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SavedRequestController extends Controller
{
    public function __construct(private readonly WorkspaceStorage $storage) {}

    public function index(string $collectionId): JsonResponse
    {
        if ($this->storage->findCollection($collectionId) === null) {
            return response()->json(['message' => 'Collection not found.'], 404);
        }

        return response()->json($this->storage->requestsForCollection($collectionId)->values());
    }

    public function store(StoreSavedRequestRequest $request, string $collectionId): JsonResponse
    {
        if ($this->storage->findCollection($collectionId) === null) {
            return response()->json(['message' => 'Collection not found.'], 404);
        }

        $saved = $this->storage->saveRequest([
            ...$request->validated(),
            'collection_id' => $collectionId,
        ]);

        return response()->json($saved, 201);
    }

    public function update(StoreSavedRequestRequest $request, string $collectionId, string $id): JsonResponse
    {
        if ($this->storage->findRequest($id) === null) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $saved = $this->storage->saveRequest([
            ...$request->validated(),
            'id'            => $id,
            'collection_id' => $collectionId,
        ]);

        return response()->json($saved);
    }

    public function destroy(string $collectionId, string $id): JsonResponse
    {
        return $this->deleteById($id);
    }

    /** Standalone delete — no collection ID in path (used by SPA). */
    public function destroyRequest(string $id): JsonResponse
    {
        return $this->deleteById($id);
    }

    public function duplicate(string $id): JsonResponse
    {
        $existing = $this->storage->findRequest($id);

        if ($existing === null) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $copy = $this->storage->saveRequest([
            'collection_id' => $existing['collection_id'],
            'folder_id'     => $existing['folder_id'] ?? null,
            'name'          => 'Copy of ' . $existing['name'],
            'sort_order'    => ($existing['sort_order'] ?? 0) + 1,
            'data'          => $existing['data'] ?? [],
        ]);

        return response()->json($copy, 201);
    }

    private function deleteById(string $id): JsonResponse
    {
        if ($this->storage->findRequest($id) === null) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $this->storage->deleteRequest($id);

        return response()->json(null, 204);
    }

    public function move(Request $request, string $id): JsonResponse
    {
        $existing = $this->storage->findRequest($id);

        if ($existing === null) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $validated = $request->validate([
            'collection_id' => ['required', 'string'],
            'folder_id'     => ['nullable', 'string'],
        ]);

        $saved = $this->storage->saveRequest([
            ...$existing,
            'collection_id' => $validated['collection_id'],
            'folder_id'     => $validated['folder_id'] ?? null,
        ]);

        return response()->json($saved);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            $existing = $this->storage->findRequest((string) $id);
            if ($existing !== null) {
                $this->storage->saveRequest([...$existing, 'sort_order' => $index]);
            }
        }

        return response()->json(null, 204);
    }
}
