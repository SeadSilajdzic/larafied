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
