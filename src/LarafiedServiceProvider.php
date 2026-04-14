<?php

declare(strict_types=1);

namespace Larafied;

use Larafied\Commands\InstallCommand;
use Larafied\Commands\TestCommand;
use Larafied\Services\AssertionRunner;
use Larafied\Services\SyncService;
use Larafied\Contracts\StorageDriverContract;
use Larafied\Http\Middleware\QueryLogMiddleware;
use Larafied\Http\Middleware\RequirePassword;
use Larafied\Http\Middleware\RestrictToAllowedEnvironments;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;
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

        $this->app->singleton(SsrfGuard::class, function ($app) {
            return new SsrfGuard(
                allowPrivateHosts: (bool) $app['config']->get('larafied.allow_private_hosts', true),
            );
        });

        $this->app->singleton(RouteScanner::class, function ($app) {
            $filters = $app['config']->get('larafied.route_filters', []);

            return new RouteScanner(
                router:          $app->make(Router::class),
                excludePatterns: $app['config']->get('larafied.exclude_routes', []),
                onlyMiddleware:  $filters['only_middleware'] ?? [],
                onlyPrefix:      $filters['only_prefix'] ?? [],
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

        $this->app->singleton(LicenseValidator::class, function ($app) {
            return new LicenseValidator(
                httpClient:      new Client(),
                storagePath:     storage_path('larafied'),
                licenseKey:      $app['config']->get('larafied.license_key') ?? '',
                cloudUrl:        $app['config']->get('larafied.cloud_url', 'https://api.larafied.com'),
                gracePeriodDays: (int) $app['config']->get('larafied.grace_period_days', 7),
                logger:          $app->make(\Psr\Log\LoggerInterface::class),
            );
        });

        $this->app->singleton(AssertionRunner::class, fn () => new AssertionRunner());

        $this->app->singleton(SyncService::class, function ($app) {
            return new SyncService(
                httpClient:       new Client(),
                storage:          $app->make(WorkspaceStorage::class),
                licenseValidator: $app->make(LicenseValidator::class),
                cloudUrl:         (string) $app['config']->get('larafied.cloud_url', 'https://api.larafied.com'),
            );
        });

        $this->app->singleton(FeatureFlags::class, function ($app) {
            return new FeatureFlags(
                licenseValidator: $app->make(LicenseValidator::class),
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

            $this->commands([InstallCommand::class, TestCommand::class]);
        }

        $this->registerRoutes();
        $this->registerMiddleware();
    }

    private function registerMiddleware(): void
    {
        // Must be global (not just 'web') so it also runs on API routes
        // that are in the 'api' middleware group.
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->pushMiddleware(QueryLogMiddleware::class);
    }

    private function registerRoutes(): void
    {
        $this->app->make(Router::class)->group([
            'prefix'     => config('larafied.prefix', 'larafied'),
            'middleware' => array_merge(
                (array) config('larafied.middleware', ['web']),
                [RestrictToAllowedEnvironments::class, RequirePassword::class],
            ),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }
}
