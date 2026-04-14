<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Exceptions\SsrfException;
use Larafied\Http\Requests\ProxyRequest;
use Larafied\Services\FeatureFlags;
use Larafied\Services\RequestProxy;
use Larafied\Storage\WorkspaceStorage;
use Illuminate\Http\JsonResponse;

final class ProxyController extends Controller
{
    public function __construct(
        private readonly RequestProxy $proxy,
        private readonly FeatureFlags $featureFlags,
        private readonly WorkspaceStorage $storage,
    ) {}

    public function send(ProxyRequest $request): JsonResponse
    {
        $debug = $request->boolean('debug') && $this->featureFlags->isEnabled('query_log');

        try {
            $response = $this->proxy->send($request, $debug);

            $this->storage->saveToHistory([
                'method'      => $request->validated('method'),
                'url'         => $request->validated('url'),
                'headers'     => $request->validated('headers', []) ?? [],
                'body'        => $request->validated('body'),
                'status'      => $response->status,
                'duration_ms' => $response->durationMs,
            ]);

            return response()->json($response->toArray());
        } catch (SsrfException $e) {
            return response()->json(['error' => $e->getMessage(), 'type' => 'ssrf'], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'type' => 'connection'], 422);
        }
    }
}
