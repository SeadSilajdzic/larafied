<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Http\Requests\StoreCollectionRequest;
use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;

final class CollectionController extends Controller
{
    private const FREE_LIMIT = 5;

    public function __construct(private readonly WorkspaceStorage $storage) {}

    public function index(): JsonResponse
    {
        $collections = $this->storage->collections()->map(function (array $collection) {
            $collection['requests'] = $this->storage->requestsForCollection($collection['id'])->values()->all();
            return $collection;
        });

        return response()->json([
            'data' => $collections->values(),
            'meta' => [
                'count'        => $collections->count(),
                'limit'        => self::FREE_LIMIT,
                'limit_reached' => $collections->count() >= self::FREE_LIMIT,
            ],
        ]);
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        if ($this->storage->collectionCount() >= self::FREE_LIMIT) {
            return response()->json([
                'message' => 'You have reached the free tier limit of '.self::FREE_LIMIT.' collections. Upgrade to Pro for unlimited collections.',
                'upgrade' => true,
            ], 422);
        }

        $collection = $this->storage->saveCollection($request->validated());

        return response()->json($collection, 201);
    }

    public function update(StoreCollectionRequest $request, string $id): JsonResponse
    {
        if ($this->storage->findCollection($id) === null) {
            return response()->json(['message' => 'Collection not found.'], 404);
        }

        $collection = $this->storage->saveCollection([...$request->validated(), 'id' => $id]);

        return response()->json($collection);
    }

    public function destroy(string $id): JsonResponse
    {
        if ($this->storage->findCollection($id) === null) {
            return response()->json(['message' => 'Collection not found.'], 404);
        }

        $this->storage->deleteCollection($id);

        return response()->json(null, 204);
    }
}
