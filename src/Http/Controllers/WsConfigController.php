<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class WsConfigController extends Controller
{
    /**
     * Return WebSocket connection details derived from the host app's
     * broadcasting configuration.  Supports Laravel Reverb and Pusher.
     * Returns { enabled: false } for all other drivers.
     */
    public function config(): JsonResponse
    {
        $driver = (string) config('broadcasting.default', 'null');

        if ($driver === 'reverb') {
            $conn = (array) config('broadcasting.connections.reverb', []);
            $opts = (array) ($conn['options'] ?? []);

            return response()->json([
                'enabled' => true,
                'driver'  => 'reverb',
                'host'    => (string) ($opts['host'] ?? env('REVERB_HOST', '127.0.0.1')),
                'port'    => (int)    ($opts['port'] ?? env('REVERB_PORT', 8080)),
                'scheme'  => (string) ($opts['scheme'] ?? env('REVERB_SCHEME', 'http')),
                'app_key' => (string) ($conn['key'] ?? env('REVERB_APP_KEY', '')),
                'path'    => '/app',
            ]);
        }

        if ($driver === 'pusher') {
            $conn = (array) config('broadcasting.connections.pusher', []);
            $opts = (array) ($conn['options'] ?? []);
            $tls  = (bool) ($opts['useTLS'] ?? false);

            return response()->json([
                'enabled' => true,
                'driver'  => 'pusher',
                'host'    => (string) ($opts['host'] ?? 'ws.pusherapp.com'),
                'port'    => (int)    ($opts['port'] ?? ($tls ? 443 : 80)),
                'scheme'  => $tls ? 'https' : 'http',
                'app_key' => (string) ($conn['key'] ?? env('PUSHER_APP_KEY', '')),
                'cluster' => (string) ($opts['cluster'] ?? 'mt1'),
                'path'    => '/app',
            ]);
        }

        return response()->json(['enabled' => false, 'driver' => $driver]);
    }
}
