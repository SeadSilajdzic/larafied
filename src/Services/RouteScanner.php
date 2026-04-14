<?php

declare(strict_types=1);

namespace Larafied\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;

final class RouteScanner
{
    public function __construct(
        private readonly Router $router,
        private readonly array $excludePatterns = [],
        private readonly array $onlyMiddleware = [],
        private readonly array $onlyPrefix = [],
    ) {}

    public function scan(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->reject(fn (Route $route) => $this->isExcluded($route))
            ->filter(fn (Route $route) => $this->isIncluded($route))
            ->map(fn (Route $route) => $this->toArray($route))
            ->groupBy('group')
            ->map(fn (Collection $routes, string $group) => [
                'group'  => $group,
                'routes' => $routes->values()->all(),
            ])
            ->values();
    }

    private function toArray(Route $route): array
    {
        return [
            'methods'    => array_values(array_diff($route->methods(), ['HEAD'])),
            'uri'        => $route->uri(),
            'name'       => $route->getName(),
            'middleware' => $route->middleware(),
            'group'      => $this->resolveGroup($route),
            'parameters' => $route->parameterNames(),
            'action'     => $this->resolveAction($route),
        ];
    }

    private function resolveGroup(Route $route): string
    {
        $uri = ltrim($route->uri(), '/');
        $parts = explode('/', $uri);

        return ($parts[0] !== '' && $parts[0] !== null) ? $parts[0] : 'root';
    }

    private function resolveAction(Route $route): string
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return 'Closure';
        }

        // Shorten FQCN: App\Http\Controllers\UserController@index → UserController@index
        if (str_contains($action, '\\')) {
            $parts = explode('\\', $action);
            return end($parts);
        }

        return $action;
    }

    private function isIncluded(Route $route): bool
    {
        if ($this->onlyMiddleware !== []) {
            $routeMiddleware = $route->middleware();
            $matched = false;
            foreach ($this->onlyMiddleware as $required) {
                foreach ($routeMiddleware as $m) {
                    // Match both 'api' and 'api:*' style middleware aliases
                    if ($m === $required || str_starts_with($m, $required . ':')) {
                        $matched = true;
                        break 2;
                    }
                }
            }
            if (! $matched) {
                return false;
            }
        }

        if ($this->onlyPrefix !== []) {
            $uri = ltrim($route->uri(), '/');
            $matched = false;
            foreach ($this->onlyPrefix as $prefix) {
                $prefix = ltrim($prefix, '/');
                if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    private function isExcluded(Route $route): bool
    {
        $name = $route->getName() ?? '';
        $uri  = $route->uri();

        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch($pattern, $name) || fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}
