<?php

declare(strict_types=1);

namespace Larafied\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class QueryLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Larafied-Debug') !== '1') {
            return $next($request);
        }

        DB::enableQueryLog();

        /** @var Response $response */
        $response = $next($request);

        $queries = array_map(fn (array $q) => [
            'sql'      => $q['query'],
            'bindings' => $q['bindings'],
            'time_ms'  => $q['time'],
        ], DB::getQueryLog());

        $response->headers->set(
            'X-Larafied-Debug-Queries',
            (string) json_encode($queries, JSON_UNESCAPED_UNICODE),
        );

        return $response;
    }
}
