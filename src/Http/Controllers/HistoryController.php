<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Larafied\Services\FeatureFlags;
use Larafied\Storage\WorkspaceStorage;

final class HistoryController extends Controller
{
    public function __construct(
        private readonly WorkspaceStorage $storage,
        private readonly FeatureFlags $featureFlags,
    ) {}

    public function index(): JsonResponse
    {
        if (! $this->featureFlags->isEnabled('request_history')) {
            return response()->json([
                'error'   => 'Request history requires a Pro license.',
                'upgrade' => true,
            ], 403);
        }

        return response()->json([
            'data' => $this->storage->getHistory()->values(),
        ]);
    }

    public function destroy(): Response
    {
        $this->storage->clearHistory();

        return response()->noContent();
    }
}
