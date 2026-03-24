<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Http\Requests\StoreSavedRequestRequest;
use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;

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
        if ($this->storage->findRequest($id) === null) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        $this->storage->deleteRequest($id);

        return response()->json(null, 204);
    }
}
