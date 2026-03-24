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
    ) {}

    public function scan(): Collection
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->reject(fn (Route $route) => $this->isExcluded($route))
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
