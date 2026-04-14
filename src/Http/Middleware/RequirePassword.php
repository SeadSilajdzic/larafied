<?php

declare(strict_types=1);

namespace Larafied\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('larafied.password');

        if (! $password) {
            return $next($request);
        }

        if ($request->routeIs('larafied.unlock*')) {
            return $next($request);
        }

        if ($request->session()->get('larafied_unlocked') === true) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'password_required'], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('larafied.unlock');
    }
}
