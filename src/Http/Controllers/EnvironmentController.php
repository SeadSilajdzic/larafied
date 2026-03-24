<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Http\Requests\StoreEnvironmentRequest;
use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;

final class EnvironmentController extends Controller
{
    public function __construct(private readonly WorkspaceStorage $storage) {}

    public function index(): JsonResponse
    {
        return response()->json($this->storage->environments()->values());
    }

    public function store(StoreEnvironmentRequest $request): JsonResponse
    {
        $environment = $this->storage->saveEnvironment($request->validated());

        return response()->json($environment, 201);
    }

    public function update(StoreEnvironmentRequest $request, string $id): JsonResponse
    {
        if ($this->storage->findEnvironment($id) === null) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $environment = $this->storage->saveEnvironment([...$request->validated(), 'id' => $id]);

        return response()->json($environment);
    }

    public function destroy(string $id): JsonResponse
    {
        if ($this->storage->findEnvironment($id) === null) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $this->storage->deleteEnvironment($id);

        return response()->json(null, 204);
    }

    public function activate(string $id): JsonResponse
    {
        if ($this->storage->findEnvironment($id) === null) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $this->storage->activateEnvironment($id);

        return response()->json($this->storage->findEnvironment($id));
    }
}
