<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Larafied\Storage\WorkspaceStorage;

final class NetworkController extends Controller
{
    public function __construct(private readonly WorkspaceStorage $storage) {}

    /**
     * Poll for new network events since the given cursor (integer event id).
     * Returns up to 50 events and the new cursor.
     */
    public function index(Request $request): JsonResponse
    {
        $cursor = (int) $request->query('cursor', 0);
        $events = $this->storage->getNetworkEvents($cursor, 50);

        return response()->json([
            'events' => $events->values(),
            'cursor' => $events->isEmpty() ? $cursor : (int) $events->last()['id'],
            'total'  => $this->storage->networkEventCount(),
        ]);
    }

    /** Delete all captured network events. */
    public function clear(): JsonResponse
    {
        $this->storage->clearNetworkEvents();
        return response()->json(null, 204);
    }

    /** Whether the network monitor is enabled in config. */
    public function config(): JsonResponse
    {
        return response()->json([
            'enabled' => (bool) config('larafied.network_monitor.enabled', false),
        ]);
    }
}
