<?php

declare(strict_types=1);

namespace Larafied\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Larafied\Storage\WorkspaceStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures every incoming HTTP request + response into the network_events
 * table so they can be inspected live in the Larafied UI.
 *
 * Opt-in: this middleware is a no-op unless
 * `larafied.network_monitor.enabled` is true in config.
 *
 * Register it in your application's middleware stack:
 *   bootstrap/app.php  →  ->withMiddleware(fn($m) => $m->append(NetworkMonitorMiddleware::class))
 *   Http/Kernel.php    →  $middleware[] = NetworkMonitorMiddleware::class
 */
final class NetworkMonitorMiddleware
{
    /** Headers that are never recorded (security). */
    private const REDACTED_HEADERS = [
        'authorization', 'cookie', 'set-cookie',
        'x-csrf-token', 'x-xsrf-token',
    ];

    public function __construct(private readonly WorkspaceStorage $storage) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('larafied.network_monitor.enabled', false)) {
            return $next($request);
        }

        // Skip Larafied's own routes to avoid infinite capture loops.
        $prefix = trim((string) config('larafied.prefix', 'larafied'), '/');
        if (str_starts_with(ltrim($request->path(), '/'), $prefix . '/')) {
            return $next($request);
        }

        $start    = hrtime(true);
        $response = $next($request);
        $duration = round((hrtime(true) - $start) / 1_000_000, 2); // ms

        $max = (int) config('larafied.network_monitor.max_body_size', 4096);

        try {
            $this->storage->recordNetworkEvent([
                'method'      => $request->method(),
                'path'        => '/' . ltrim($request->path(), '/'),
                'query'       => $request->getQueryString() ?: null,
                'status'      => $response->getStatusCode(),
                'duration_ms' => $duration,
                'req_headers' => $this->sanitizeHeaders($request->headers->all()),
                'req_body'    => $this->truncate((string) $request->getContent(), $max),
                'res_headers' => $this->sanitizeHeaders($response->headers->all()),
                'res_body'    => $this->truncate($this->extractBody($response), $max),
                'ip'          => $request->ip(),
            ]);
        } catch (\Throwable) {
            // Never interrupt the response due to monitoring failures.
        }

        return $response;
    }

    /** Flatten header arrays, remove sensitive keys. */
    private function sanitizeHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $values) {
            if (in_array(strtolower($key), self::REDACTED_HEADERS, true)) {
                continue;
            }
            $result[$key] = is_array($values) ? implode(', ', $values) : $values;
        }
        return $result;
    }

    private function truncate(string $content, int $max): string
    {
        if ($content === '' || strlen($content) <= $max) {
            return $content;
        }
        return substr($content, 0, $max) . ' …[truncated]';
    }

    private function extractBody(Response $response): string
    {
        // StreamedResponse does not have accessible content; skip it.
        if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return '';
        }
        return (string) $response->getContent();
    }
}
