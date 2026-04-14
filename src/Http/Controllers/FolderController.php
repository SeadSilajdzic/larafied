<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FolderController extends Controller
{
    public function __construct(private readonly WorkspaceStorage $storage) {}

    public function store(Request $request, string $collectionId): JsonResponse
    {
        if ($this->storage->findCollection($collectionId) === null) {
            return response()->json(['message' => 'Collection not found.'], 404);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = $this->storage->saveFolder([
            ...$validated,
            'collection_id' => $collectionId,
        ]);

        return response()->json($folder, 201);
    }

    public function update(Request $request, string $collectionId, string $id): JsonResponse
    {
        if ($this->storage->findFolder($id) === null) {
            return response()->json(['message' => 'Folder not found.'], 404);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = $this->storage->saveFolder([
            ...$validated,
            'id'            => $id,
            'collection_id' => $collectionId,
        ]);

        return response()->json($folder);
    }

    public function destroy(string $collectionId, string $id): JsonResponse
    {
        if ($this->storage->findFolder($id) === null) {
            return response()->json(['message' => 'Folder not found.'], 404);
        }

        $this->storage->deleteFolder($id);

        return response()->json(null, 204);
    }

    public function reorder(Request $request, string $collectionId): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            $existing = $this->storage->findFolder((string) $id);
            if ($existing !== null) {
                $this->storage->saveFolder([...$existing, 'sort_order' => $index]);
            }
        }

        return response()->json(null, 204);
    }
}
