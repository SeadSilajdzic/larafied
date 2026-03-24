<?php

declare(strict_types=1);

namespace Larafied;

use Larafied\Commands\InstallCommand;
use Larafied\Contracts\StorageDriverContract;
use Larafied\Http\Middleware\RestrictToAllowedEnvironments;
use Larafied\Services\RequestProxy;
use Larafied\Services\RouteScanner;
use Larafied\Services\SsrfGuard;
use Larafied\Storage\Drivers\SqliteDriver;
use Larafied\Storage\WorkspaceStorage;
use GuzzleHttp\Client;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class LarafiedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larafied.php', 'larafied');

        $this->app->singleton(SsrfGuard::class);

        $this->app->singleton(RouteScanner::class, function ($app) {
            return new RouteScanner(
                router:          $app->make(Router::class),
                excludePatterns: $app['config']->get('larafied.exclude_routes', []),
            );
        });

        $this->app->singleton(StorageDriverContract::class, function () {
            return new SqliteDriver(
                storagePath: storage_path('larafied'),
            );
        });

        $this->app->singleton(WorkspaceStorage::class, function ($app) {
            return new WorkspaceStorage(
                driver: $app->make(StorageDriverContract::class),
            );
        });

        $this->app->singleton(RequestProxy::class, function ($app) {
            return new RequestProxy(
                ssrfGuard:  $app->make(SsrfGuard::class),
                httpClient: new Client(),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'larafied');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/larafied.php' => config_path('larafied.php'),
            ], 'larafied-config');

            $this->publishes([
                __DIR__.'/../public' => public_path('vendor/larafied'),
            ], 'larafied-assets');

            $this->commands([InstallCommand::class]);
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $this->app->make(Router::class)->group([
            'prefix'     => config('larafied.prefix', 'larafied'),
            'middleware' => array_merge(
                (array) config('larafied.middleware', ['web']),
                [RestrictToAllowedEnvironments::class],
            ),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
}
