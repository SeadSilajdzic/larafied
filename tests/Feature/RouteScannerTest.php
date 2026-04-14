<?php

declare(strict_types=1);

use Larafied\Services\RouteScanner;
use Illuminate\Routing\Router;

beforeEach(function () {
    $this->router  = $this->app->make(Router::class);
    $this->scanner = new RouteScanner($this->router, ['larafied.*', 'debugbar.*']);
});

it('scans registered routes', function () {
    $this->router->get('/api/users', fn () => 'users')->name('users.index');

    $groups = $this->scanner->scan();

    $apiGroup = $groups->firstWhere('group', 'api');
    expect($apiGroup)->not->toBeNull()
        ->and($apiGroup['routes'])->toHaveCount(1)
        ->and($apiGroup['routes'][0]['uri'])->toBe('api/users')
        ->and($apiGroup['routes'][0]['methods'])->toContain('GET');
});

it('groups routes by uri prefix', function () {
    $this->router->get('/api/users', fn () => '');
    $this->router->get('/api/posts', fn () => '');
    $this->router->get('/admin/dashboard', fn () => '');

    $groups  = $this->scanner->scan();
    $groupNames = $groups->pluck('group')->all();

    expect($groupNames)->toContain('api')
        ->and($groupNames)->toContain('admin');

    $apiGroup = $groups->firstWhere('group', 'api');
    expect($apiGroup['routes'])->toHaveCount(2);
});

it('excludes routes matching exclude patterns', function () {
    $this->router->get('/api/users', fn () => '')->name('users.index');
    $this->router->get('/larafied/api/routes', fn () => '')->name('larafied.api.routes');
    $this->router->get('/debugbar/info', fn () => '')->name('debugbar.info');

    $allRoutes = $this->scanner->scan()->flatMap(fn ($g) => $g['routes']);

    $names = collect($allRoutes)->pluck('name')->all();

    expect($names)->not->toContain('larafied.api.routes')
        ->and($names)->not->toContain('debugbar.info')
        ->and($names)->toContain('users.index');
});

it('strips HEAD method from route methods', function () {
    $this->router->get('/api/users', fn () => '');

    $routes = $this->scanner->scan()->flatMap(fn ($g) => $g['routes']);

    expect($routes->first()['methods'])->not->toContain('HEAD');
});

it('assigns root group to routes without prefix', function () {
    $this->router->get('/', fn () => '')->name('home');

    $groups = $this->scanner->scan();
    $root   = $groups->firstWhere('group', 'root');

    expect($root)->not->toBeNull();
});

it('filters routes by only_middleware', function () {
    $this->router->get('/api/users', fn () => '')->middleware('api')->name('api.users');
    $this->router->get('/about', fn () => '')->middleware('web')->name('web.about');

    $scanner = new RouteScanner(
        router:          $this->router,
        excludePatterns: [],
        onlyMiddleware:  ['api'],
    );

    $routes = $scanner->scan()->flatMap(fn ($g) => $g['routes']);
    $names  = collect($routes)->pluck('name')->all();

    expect($names)->toContain('api.users')
        ->and($names)->not->toContain('web.about');
});

it('filters routes by only_prefix', function () {
    $this->router->get('/api/users', fn () => '')->name('api.users');
    $this->router->get('/about', fn () => '')->name('web.about');
    $this->router->get('/api/posts', fn () => '')->name('api.posts');

    $scanner = new RouteScanner(
        router:          $this->router,
        excludePatterns: [],
        onlyPrefix:      ['api'],
    );

    $routes = $scanner->scan()->flatMap(fn ($g) => $g['routes']);
    $names  = collect($routes)->pluck('name')->all();

    expect($names)->toContain('api.users')
        ->and($names)->toContain('api.posts')
        ->and($names)->not->toContain('web.about');
});

it('applies both middleware and prefix filters together', function () {
    $this->router->get('/api/users', fn () => '')->middleware('api')->name('api.users');
    $this->router->get('/api/admin', fn () => '')->middleware('web')->name('api.admin.web');
    $this->router->get('/other', fn () => '')->middleware('api')->name('other.api');

    $scanner = new RouteScanner(
        router:          $this->router,
        excludePatterns: [],
        onlyMiddleware:  ['api'],
        onlyPrefix:      ['api'],
    );

    $routes = $scanner->scan()->flatMap(fn ($g) => $g['routes']);
    $names  = collect($routes)->pluck('name')->all();

    expect($names)->toContain('api.users')
        ->and($names)->not->toContain('api.admin.web')
        ->and($names)->not->toContain('other.api');
});
