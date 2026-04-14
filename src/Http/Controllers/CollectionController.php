<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Http\Requests\StoreCollectionRequest;
use Larafied\Services\FeatureFlags;
use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CollectionController extends Controller
{
    private const FREE_LIMIT = 5;

    public function __construct(
        private readonly WorkspaceStorage $storage,
        private readonly FeatureFlags $featureFlags,
    ) {}

    public function index(): JsonResponse
    {
        $collections = $this->storage->collections()->map(function (array $collection) {
            $collection['requests'] = $this->storage->topLevelRequestsForCollection($collection['id'])->values()->all();
            $collection['folders']  = $this->storage->foldersForCollection($collection['id'])->map(function (array $folder) {
                $folder['requests'] = $this->storage->requestsInFolder($folder['id'])->values()->all();
                return $folder;
            })->values()->all();
            return $collection;
        });

        return response()->json([
            'data' => $collections->values(),
            'meta' => [
                'count'         => $collections->count(),
                'limit'         => self::FREE_LIMIT,
                'limit_reached' => ! $this->featureFlags->isEnabled('unlimited_collections')
                                   && $collections->count() >= self::FREE_LIMIT,
            ],
        ]);
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        if (! $this->featureFlags->isEnabled('unlimited_collections')
            && $this->storage->collectionCount() >= self::FREE_LIMIT
        ) {
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

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        foreach ($validated['ids'] as $id) {
            if ($this->storage->findCollection((string) $id) !== null) {
                $this->storage->deleteCollection((string) $id);
            }
        }

        return response()->json(null, 204);
    }
}
