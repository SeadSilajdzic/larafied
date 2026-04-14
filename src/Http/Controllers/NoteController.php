<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NoteController extends Controller
{
    public function __construct(private readonly WorkspaceStorage $storage) {}

    public function index(): JsonResponse
    {
        return response()->json($this->storage->allRouteNotes()->values());
    }

    public function show(Request $request): JsonResponse
    {
        $method = strtoupper((string) $request->query('method', ''));
        $uri    = ltrim((string) $request->query('uri', ''), '/');

        $note = $this->storage->findRouteNote($method, $uri);

        return $note
            ? response()->json($note)
            : response()->json(null, 404);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string'],
            'uri'    => ['required', 'string'],
            'note'   => ['required', 'string', 'max:5000'],
        ]);

        $note = $this->storage->saveRouteNote(
            strtoupper($validated['method']),
            ltrim($validated['uri'], '/'),
            $validated['note'],
        );

        return response()->json($note);
    }

    public function destroy(Request $request): JsonResponse
    {
        $method = strtoupper((string) $request->query('method', ''));
        $uri    = ltrim((string) $request->query('uri', ''), '/');

        $this->storage->deleteRouteNote($method, $uri);

        return response()->json(null, 204);
    }
}
