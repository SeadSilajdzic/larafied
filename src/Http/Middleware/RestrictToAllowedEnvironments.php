<?php

declare(strict_types=1);

namespace Larafied\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictToAllowedEnvironments
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('larafied.allowed_environments', ['local']);
        $current = config('app.env', 'production');

        if (! in_array($current, (array) $allowed, true)) {
            abort(403, "API Workspace is not available in the '{$current}' environment.");
        }

        return $next($request);
    }
}
