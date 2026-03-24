<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Exceptions\SsrfException;
use Larafied\Http\Requests\ProxyRequest;
use Larafied\Services\RequestProxy;
use Illuminate\Http\JsonResponse;

final class ProxyController extends Controller
{
    public function __construct(private readonly RequestProxy $proxy) {}

    public function send(ProxyRequest $request): JsonResponse
    {
        try {
            $response = $this->proxy->send($request);

            return response()->json($response->toArray());
        } catch (SsrfException $e) {
            return response()->json(['error' => $e->getMessage(), 'type' => 'ssrf'], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage(), 'type' => 'connection'], 422);
        }
    }
}
