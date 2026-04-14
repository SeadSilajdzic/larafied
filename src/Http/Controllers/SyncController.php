<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Larafied\Services\FeatureFlags;
use Larafied\Services\SyncService;

final class SyncController extends Controller
{
    public function __construct(
        private readonly SyncService  $syncService,
        private readonly FeatureFlags $featureFlags,
    ) {}

    public function push(): JsonResponse
    {
        if (! $this->featureFlags->isEnabled('cloud_sync')) {
            return response()->json(['error' => 'Cloud sync requires Team or Agency plan.'], 403);
        }

        $result = $this->syncService->push();

        return response()->json($result, isset($result['error']) ? 502 : 200);
    }

    public function pull(): JsonResponse
    {
        if (! $this->featureFlags->isEnabled('cloud_sync')) {
            return response()->json(['error' => 'Cloud sync requires Team or Agency plan.'], 403);
        }

        $result = $this->syncService->pull();

        return response()->json($result, isset($result['error']) ? 502 : 200);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => $this->featureFlags->isEnabled('cloud_sync'),
        ]);
    }
}
